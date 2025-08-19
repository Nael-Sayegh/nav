<?php

$document_root = __DIR__.'/..';

require_once $document_root.'/include/consts.php';
require_once $document_root.'/include/sendMail.php';
require_once $document_root.'/include/lib/MDConverter.php';

$mbox = @imap_open(IMAP_INBOX, TICKETS_BOT_MAIL, TICKETS_BOT_PSW);
if (!$mbox)
{
    error_log('IMAP open failed: ' . imap_last_error());
    exit;
}

function get_mime_type($structure)
{
    $primary = ['TEXT','MULTIPART','MESSAGE','APPLICATION','AUDIO','IMAGE','VIDEO','OTHER'];
    if (!empty($structure->subtype))
    {
        return $primary[(int)$structure->type] . '/' . $structure->subtype;
    }
    return 'TEXT/PLAIN';
}

function get_part($imap, $uid, $mimetype, $structure = null, $partNumber = null)
{
    if (!$structure)
    {
        $structure = imap_fetchstructure($imap, $uid, FT_UID);
    }
    if ($structure)
    {
        if (get_mime_type($structure) === $mimetype)
        {
            $partNumber = $partNumber ?: 1;
            $text = imap_fetchbody($imap, $uid, $partNumber, FT_UID);
            return match ($structure->encoding)
            {
                3 => imap_base64($text),
                4 => imap_qprint($text),
                default => imap_utf8($text),
            };
        }
        if ($structure->type === 1 && !empty($structure->parts))
        {
            foreach ($structure->parts as $index => $sub)
            {
                $prefix = $partNumber ? "$partNumber." : '';
                if ($data = get_part($imap, $uid, $mimetype, $sub, $prefix . ($index + 1)))
                {
                    return $data;
                }
            }
        }
    }
    return '';
}

function getBody($uid, $imap)
{
    $html = html_entity_decode((string)get_part($imap, $uid, 'TEXT/HTML'));
    if (trim($html) === '')
    {
        $plain = (string)get_part($imap, $uid, 'TEXT/PLAIN');
        $html = nl2br(htmlentities($plain, ENT_QUOTES, 'UTF-8'));
    }
    return explode('## Ne pas écrire en-dessous de cette ligne ##', $html)[0];
}

$info = imap_check($mbox);
if (!$info || $info->Nmsgs < 1)
{
    imap_close($mbox);
    exit;
}

$mails = imap_fetch_overview($mbox, '1:' . min(50, $info->Nmsgs), 0);
if (!$mails)
{
    imap_close($mbox);
    exit;
}

foreach ($mails as $mail)
{
    $hdrText = imap_fetchheader($mbox, $mail->uid, FT_UID);
    $hdr = imap_rfc822_parse_headers($hdrText);
    $from = $hdr->from[0]->mailbox . '@' . $hdr->from[0]->host;
    $rawBodyHtml = convertToMD(getBody($mail->uid, $mbox));
    $rawBodyText = trim(strip_tags((string) $rawBodyHtml));

    $subject = iconv_mime_decode((string)$mail->subject, 0, 'UTF-8');
    if (!str_contains($subject, '(Ticket #'))
    {
        imap_delete($mbox, $mail->uid, FT_UID);
        continue;
    }
    if (!preg_match('/\(Ticket #(\d+)#\)/', $subject, $m))
    {
        imap_delete($mbox, $mail->uid, FT_UID);
        continue;
    }
    $ticketId = $m[1];
    $SQL = <<<SQL
        SELECT * FROM tickets WHERE id = :id LIMIT 1
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':id' => $ticketId]);
    $ticket = $req->fetch(PDO::FETCH_ASSOC);
    if (!$ticket)
    {
        $mailSubj = "Re: [SANS OBJET] (Ticket #{$ticketId}#)";
        $body   = <<<HTML
            <h2>Réponse au ticket {$ticketId}</h2>
            <p>Vous avez tenté de répondre par mail à un ticket de {$site_name}.<br>
            Nous n'avons pas pu traiter votre réponse car le ticket {$ticketId} n'existe pas ou plus.<br>
            Si vous souhaitez nous contacter, veuillez ouvrir un nouveau ticket via le <a href="{SITE_URL}/contact_form.php">formulaire de contact</a>.</p>
            HTML;
        $altBody = <<<TEXT
            Réponse au ticket {$ticketId}
            Vous avez tenté de répondre par mail à un ticket de {$site_name}.
            Nous n'avons pas pu traiter votre réponse car le ticket {$ticketId} n'existe pas ou plus.
            Si vous souhaitez nous contacter, veuillez ouvrir un nouveau ticket via le formulaire de contact: {SITE_URL}/contact_form.php
            TEXT;
        sendMail($from, $mailSubj, $body, $altBody);
        imap_delete($mbox, $mail->uid, FT_UID);
        continue;
    }
    $mailSubj   = "Re: {$ticket['subject']} (Ticket #{$ticketId}#)";
    if ($ticket['status'] === 4 && (time() - $ticket['date']) > 24 * 3600)
    {
        $body   = <<<HTML
            <h2>Réponse au ticket {$ticket['subject']}</h2>
            <p>Vous avez tenté de répondre par mail à un ticket de {$site_name}.<br>
            Nous n'avons pas pu traiter votre réponse car le ticket {$ticketId} a été fermé il y a plus de 24h.<br>
            Si vous souhaitez nous contacter, veuillez ouvrir un nouveau ticket via le <a href="{SITE_URL}/contact_form.php">formulaire de contact</a>.</p>
            HTML;
        $altBody = <<<TEXT
            Réponse au ticket {$ticket['subject']}
            Vous avez tenté de répondre par mail à un ticket de {$site_name}.
            Nous n'avons pas pu traiter votre réponse car le ticket {$ticketId} a été fermé il y a plus de 24h.
            Si vous souhaitez nous contacter, veuillez ouvrir un nouveau ticket via le formulaire de contact: {SITE_URL}/contact_form.php
            TEXT;
        sendMail($from, $mailSubj, $body, $altBody);
        imap_delete($mbox, $mail->uid, FT_UID);
        continue;
    }
    $admins = getTeamEmails('manage_tickets');
    if ($ticket['expeditor_email'] === $from)
    {
        $name = $ticket['expeditor_name'] ?: 'Expéditeur inconnu';
        $messages = json_decode((string) $ticket['messages'], true) ?: [];
        $messages[] = ['e' => $name, 'm' => 0, 'd' => time(), 't' => $rawBodyText];
        $SQLUpd = <<<SQL
            UPDATE tickets SET messages = :msg, status = 1, date = :date WHERE id = :id
            SQL;
        $upd = $bdd->prepare($SQLUpd);
        $upd->execute([
            ':msg'  => json_encode($messages),
            ':date' => time(),
            ':id'   => $ticketId
        ]);

        $css = <<<CSS
            #response
            {
                border-left:1px solid #0080FF;
                margin-left:8px;
                padding: 8px;
            }
            CSS;
        $htmlBody   = <<<HTML
            <p>## Ne pas écrire en-dessous de cette ligne ##</p>
            <h2>Réponse au ticket {$ticket['subject']}</h2>
            <p>Une réponse a été envoyée par {$name} pour le ticket {$ticketId} via les réponses par mail de {$site_name}.</p>
            <div id="response"><blockquote>{$rawBodyHtml}</blockquote></div>
            <p><a href="{SITE_URL}/admin/tickets.php?ticket={$ticketId}">Consultez le ticket</a> ou répondez à ce message sans en modifier l'objet pour continuer la discussion.</p>
            HTML;
        $textBody = <<<TEXT
            ## Ne pas écrire en-dessous de cette ligne ##
            Réponse au ticket {$ticket['subject']}
            Une réponse a été envoyée par {$name} pour le ticket {$ticketId} via les réponses par mail de {$site_name}.
            {$rawBodyText}
            Pour continuer la discussion, répondez à ce message sans en modifier l'objet ou consultez le ticket à l'adresse suivante:
            {SITE_URL}/admin/tickets.php?ticket={$ticketId}
            TEXT;

        sendMail($admins, $mailSubj, $htmlBody, $textBody, [TICKETS_BOT_MAIL, "{$site_name} Tickets Bot"], ['css' => $css, 'includeAutoReplyNotice' => false]);

    }
    elseif (in_array($from, $admins, true))
    {
        $q = $bdd->prepare(
            'SELECT a.id, t.short_name
             FROM accounts a
             LEFT JOIN team t ON t.account_id = a.id
             WHERE a.email = ? LIMIT 1'
        );
        $q->execute([$from]);
        $r = $q->fetch(PDO::FETCH_ASSOC);
        $admName = $r['short_name'] ?: 'Un admin';

        $messages = json_decode((string) $ticket['messages'], true) ?: [];
        $messages[] = ['e' => $admName, 'm' => 1, 'd' => time(), 't' => $rawBodyText];
        $upd = $bdd->prepare(
            'UPDATE tickets
             SET messages = :msg, status = 2, date = :date, lastadmreply = :last
             WHERE id = :id'
        );
        $upd->execute([
            ':msg'  => json_encode($messages),
            ':date' => time(),
            ':last' => $admName,
            ':id'   => $ticketId
        ]);

        $css = <<<CSS
            #response
            {
                border-left:1px solid #0080FF;
                margin-left:8px;
                padding: 8px;
            }
            CSS;
        $htmlBody = <<<HTML
            <p>## Ne pas écrire en-dessous de cette ligne ##</p>
            <h2>Réponse à votre ticket {$ticket['subject']}</h2>
            <p>Vous avez reçu une réponse de {$admName} pour votre ticket numéro {$ticketId}.</p>
            <div id="response"><p><blockquote>{$rawBodyHtml}</blockquote></p></div>
            <hr>
            <p>Pour poursuivre la discussion, utilisez le lien ci-dessous ou répondez simplement à ce message sans en modifier l'objet.<br>
            <a href="{SITE_URL}/contact_form.php?reply={$ticket['id']}&h={$ticket['hash']}">Répondre au ticket</a>.</p>
            HTML;
        $textBody = <<<TEXT
            ## Ne pas écrire en-dessous de cette ligne ##
            Réponse à votre ticket {$ticket['subject']}
            Vous avez reçu une réponse de {$admName} pour votre ticket numéro {$ticketId}
            {$rawBodyText}
            Pour poursuivre la discussion, utilisez le lien suivant ou répondez simplement à ce message sans en modifier l'objet
            {SITE_URL}/contact_form.php?reply={$ticket['id']}&h={$ticket['hash']}
            TEXT;

        sendMail($ticket['expeditor_email'], $mailSubj, $htmlBody, $textBody, [TICKETS_BOT_MAIL, "{$site_name} Tickets Bot"], ['css' => $css, 'includeAutoReplyNotice' => false]);

        $admHtml  = <<<HTML
            <p>## Ne pas écrire en-dessous de cette ligne ##</p>
            <h2>Réponse au ticket {$ticket['subject']}</h2>
            <p>{$admName} a répondu au ticket {$ticketId} de {$ticket['expeditor_name']}</p>
            <div id="response"><blockquote>{$rawBodyHtml}</blockquote></div>
            <p><a href="{SITE_URL}/admin/tickets.php?ticket={$ticketId}">Consulter le ticket complet</a> ou répondez à ce message sans en modifier l'objet pour poursuivre la discussion.</p>
            HTML;
        $admText = <<<TEXT
            ## Ne pas écrire en-dessous de cette ligne ##
            Réponse au ticket {$ticket['subject']}
            {$admName} a répondu au ticket {$ticketId} de {$ticket['expeditor_name']}
            {$rawBodyText}
            Pour continuer la discussion, répondez à ce message sans en modifier l'objet ou consultez le ticket à l'adresse suivante:
            {SITE_URL}/admin/tickets.php?ticket={$ticketId}
            TEXT;

        sendMail($admins, $mailSubj, $admHtml, $admText, [TICKETS_BOT_MAIL, "{$site_name} Tickets Bot"], ['css' => $css, 'includeAutoReplyNotice' => false]);
    }
    else
    {
        $body   = <<<HTML
            <h2>Réponse au ticket {$ticket['subject']}</h2>
            <p>Vous avez tenté de répondre par mail au ticket {$ticketId} de {$site_name}.<br>
            Nous n'avons pas pu traiter votre réponse car votre adresse mail ne correspond ni à celle de l'expéditeur originel du ticket, ni à celle d'un administrateur ayant les droits de gérer les tickets.<br>
            Merci de bien vouloir renvoyer votre message depuis une adresse remplissant les critères ci-dessus.</p>
            HTML;
        $altBody = <<<TEXT
            ## Ne pas écrire en-dessous de cette ligne ##
            Vous avez tenté de répondre par mail au ticket {$ticketId} de {$site_name}.
            Nous n'avons pas pu traiter votre réponse car votre adresse mail ne correspond ni à celle de l'expéditeur originel du ticket, ni à celle d'un administrateur ayant les droits de gérer les tickets.
            Merci de bien vouloir renvoyer votre message depuis une adresse remplissant les critères ci-dessus.
            TEXT;
        sendMail($from, $mailSubj, $body, $altBody);
        imap_delete($mbox, $mail->uid, FT_UID);
        continue;
    }

    imap_delete($mbox, $mail->uid, FT_UID);
}

imap_expunge($mbox);
imap_close($mbox);
