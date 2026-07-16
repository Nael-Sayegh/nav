<?php

declare(strict_types=1);

// Processes a newsletter campaign (see include/newsletter_campaigns.php) in
// short, time-boxed passes: never the whole queue in a single execution,
// always a voluntary stop before the server's execution time limit (known or
// not), with guaranteed resumption from the state persisted in the database.
//
// This script is only ever invoked from the CLI, with the campaign id as its
// first argument: it is never exposed over HTTP. It relaunches itself (see
// launchNewsletterCampaignPass()) as long as recipients remain, without
// depending on a cron job.

$noct = true;

$document_root = __DIR__.'/..';
require_once($document_root.'/include/config.local.php');
require_once($document_root.'/include/consts.php');
require_once($document_root.'/include/sendMail.php');
require_once($document_root.'/include/newsletter_campaigns.php');

function nlCampaignTimeBudgetSeconds(): int
{
    $configured = (int) ini_get('max_execution_time');
    if ($configured > 0)
    {
        // Only spend part of the real budget: the remaining margin covers
        // the SMTP connection time, building the next email, etc., so the
        // script is sure to stop on its own before being killed.
        return max(5, (int) floor($configured * 0.7));
    }

    // The server may report an "unlimited" time (0) that is not actually
    // unlimited in practice: keep a default safety cap instead of relying
    // on that assumption.
    return 45;
}

$campaignId = isset($argv[1]) ? (int) $argv[1] : 0;
if ($campaignId <= 0)
{
    fwrite(STDERR, "Usage: php nl_campaign_sender.php <campaign_id>\n");
    exit(1);
}

$campaign = getNewsletterCampaign($campaignId);
if (!$campaign)
{
    fwrite(STDERR, 'Campaign not found: '.$campaignId."\n");
    exit(1);
}

if ($campaign['status'] === 'pending')
{
    markNewsletterCampaignRunning($campaignId);
}

$start = time();
$budget = nlCampaignTimeBudgetSeconds();

while ((time() - $start) < $budget)
{
    if (!processNextNewsletterCampaignRecipient($campaign))
    {
        break;
    }
}

if (newsletterCampaignHasPendingWork($campaignId))
{
    launchNewsletterCampaignPass($campaignId);
}
else
{
    markNewsletterCampaignDone($campaignId);
}
