<?php

declare(strict_types=1);

const NL_CAMPAIGN_MAX_ATTEMPTS = 3;

function createNewsletterCampaign(string $subject, string $bodyHtml, string $bodyText, string $site, ?int $createdBy): int
{
    global $bdd;

    $allMails = [];
    $buildSQL = (fn (string $column): string => sprintf('SELECT mail,hash FROM newsletter_mails WHERE %s = true AND confirm = true', $column));
    if ($site === 'site1' || $site === 'both')
    {
        $req = $bdd->prepare($buildSQL('notif_upd'));
        $req->execute();
        while ($data = $req->fetch())
        {
            $allMails[$data['mail']] = $data['hash'];
        }
    }

    if ($site === 'site2' || $site === 'both')
    {
        $req = $bdd->prepare($buildSQL('notif_upd_n'));
        $req->execute();
        while ($data = $req->fetch())
        {
            if (isset($allMails[$data['mail']]))
            {
                continue;
            }

            $allMails[$data['mail']] = $data['hash'];
        }
    }

    $now = time();
    $SQL = <<<SQL
        INSERT INTO newsletter_campaigns (subject, body_html, body_text, site, status, created_by, created_at, last_progress_at)
        VALUES (:subject, :body_html, :body_text, :site, 'pending', :created_by, :created_at, :created_at)
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([
        ':subject'    => $subject,
        ':body_html'  => $bodyHtml,
        ':body_text'  => $bodyText,
        ':site'       => $site,
        ':created_by' => $createdBy,
        ':created_at' => $now,
    ]);
    $campaignId = (int) $bdd->lastInsertId();

    $SQL = <<<SQL
        INSERT INTO newsletter_campaign_recipients (campaign_id, mail, hash, status)
        VALUES (:campaign_id, :mail, :hash, 'pending')
        SQL;
    $req = $bdd->prepare($SQL);
    foreach ($allMails as $mail => $hash)
    {
        $req->execute([':campaign_id' => $campaignId, ':mail' => $mail, ':hash' => $hash]);
    }

    return $campaignId;
}

function getNewsletterCampaign(int $campaignId): array|false
{
    global $bdd;

    $SQL = <<<SQL
        SELECT * FROM newsletter_campaigns WHERE id = :id LIMIT 1
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':id' => $campaignId]);
    return $req->fetch();
}

/**
 * @return list<array<string, mixed>>
 */
function listNewsletterCampaigns(): array
{
    global $bdd;

    $SQL = <<<SQL
        SELECT * FROM newsletter_campaigns ORDER BY id DESC
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute();
    return $req->fetchAll();
}

/**
 * @return array{total: int, pending: int, sent: int, failed: int, failed_permanent: int}
 */
function getNewsletterCampaignRecipientCounts(int $campaignId): array
{
    global $bdd;

    $SQL = <<<SQL
        SELECT status, count(*) AS n FROM newsletter_campaign_recipients WHERE campaign_id = :campaign_id GROUP BY status
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':campaign_id' => $campaignId]);

    $counts = ['total' => 0, 'pending' => 0, 'sent' => 0, 'failed' => 0, 'failed_permanent' => 0];
    while ($row = $req->fetch())
    {
        $counts[$row['status']] = (int) $row['n'];
        $counts['total'] += (int) $row['n'];
    }

    return $counts;
}

function newsletterCampaignHasPendingWork(int $campaignId): bool
{
    global $bdd;

    $SQL = <<<SQL
        SELECT 1 FROM newsletter_campaign_recipients
        WHERE campaign_id = :campaign_id
          AND (status = 'pending' OR (status = 'failed' AND attempts < :max_attempts))
        LIMIT 1
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':campaign_id' => $campaignId, ':max_attempts' => NL_CAMPAIGN_MAX_ATTEMPTS]);
    return (bool) $req->fetchColumn();
}

function markNewsletterCampaignRunning(int $campaignId): void
{
    global $bdd;

    $SQL = <<<SQL
        UPDATE newsletter_campaigns SET status = 'running' WHERE id = :id AND status = 'pending'
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':id' => $campaignId]);
}

function markNewsletterCampaignDone(int $campaignId): void
{
    global $bdd;

    $SQL = <<<SQL
        UPDATE newsletter_campaigns SET status = 'done' WHERE id = :id
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':id' => $campaignId]);
}

/**
 * Processes a single recipient from the queue atomically: the selection
 * (with row locking) and the status update happen in the same transaction,
 * so a concurrent pass (e.g. a manual retry triggered while a background
 * chain is still running) never processes the same recipient twice.
 *
 * Returns false when there is currently nothing left to process.
 */
function processNextNewsletterCampaignRecipient(array $campaign): bool
{
    global $bdd;

    $bdd->beginTransaction();
    try
    {
        $SQL = <<<SQL
            SELECT id, mail, hash, attempts FROM newsletter_campaign_recipients
            WHERE campaign_id = :campaign_id
              AND (status = 'pending' OR (status = 'failed' AND attempts < :max_attempts))
            ORDER BY id ASC
            LIMIT 1
            FOR UPDATE SKIP LOCKED
            SQL;
        $req = $bdd->prepare($SQL);
        $req->execute([':campaign_id' => $campaign['id'], ':max_attempts' => NL_CAMPAIGN_MAX_ATTEMPTS]);
        $recipient = $req->fetch();

        if (!$recipient)
        {
            $bdd->commit();
            return false;
        }

        $html = str_replace('{userid}', (string) $recipient['hash'], $campaign['body_html']);
        $sent = sendMail($recipient['mail'], $campaign['subject'], $html, $campaign['body_text']);

        if ($sent)
        {
            $update = $bdd->prepare(<<<SQL
                UPDATE newsletter_campaign_recipients SET status = 'sent', sent_at = :now WHERE id = :id
                SQL);
            $update->execute([':now' => time(), ':id' => $recipient['id']]);
        }
        else
        {
            $attempts = ((int) $recipient['attempts']) + 1;
            $status = $attempts >= NL_CAMPAIGN_MAX_ATTEMPTS ? 'failed_permanent' : 'failed';
            $update = $bdd->prepare(<<<SQL
                UPDATE newsletter_campaign_recipients
                SET status = :status, attempts = :attempts, last_error = :error
                WHERE id = :id
                SQL);
            $update->execute([
                ':status'   => $status,
                ':attempts' => $attempts,
                ':error'    => 'Échec d\'envoi via sendMail() (voir les logs SMTP du serveur)',
                ':id'       => $recipient['id'],
            ]);
        }

        $touch = $bdd->prepare(<<<SQL
            UPDATE newsletter_campaigns SET last_progress_at = :now WHERE id = :id
            SQL);
        $touch->execute([':now' => time(), ':id' => $campaign['id']]);

        $bdd->commit();
        return true;
    }
    catch (Throwable $throwable)
    {
        $bdd->rollBack();
        throw $throwable;
    }
}

/**
 * Launches a new background pass of the sending engine for the given
 * campaign, detached from the current request. Never blocks the caller
 * (an admin submitting the send form, or a pass that just used up its time
 * budget): this self-relaunching chain of short passes is what drives the
 * queue to completion without relying on a cron job.
 */
function launchNewsletterCampaignPass(int $campaignId): void
{
    $script = DOCUMENT_ROOT.'/tasks/nl_campaign_sender.php';
    $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg((string) $campaignId);
    exec($cmd.' > /dev/null 2>&1 &');
}
