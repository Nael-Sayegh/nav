<?php

declare(strict_types=1);

// Traite une campagne de newsletter (cf. include/newsletter_campaigns.php) par
// petites passes auto-limitées en temps : jamais toute la file en une seule
// exécution, toujours un arrêt volontaire avant la limite d'exécution du
// serveur (connue ou non), avec reprise garantie par l'état persisté en base.
// Voir plan_fiabilisation_newsletter.txt, sections 3.2 et 3.3.
//
// Ce script s'invoque uniquement en CLI, avec l'identifiant de campagne en
// premier argument : il n'est jamais exposé en HTTP. Il se relance lui-même
// (cf. launchNewsletterCampaignPass()) tant qu'il reste des destinataires à
// traiter, sans dépendre d'un cron.

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
        // On ne consomme qu'une partie du budget réel : la marge restante
        // couvre le temps de connexion SMTP, la génération du mail suivant,
        // etc., pour être certain de s'arrêter avant d'être tué.
        return max(5, (int) floor($configured * 0.7));
    }

    // Le serveur peut annoncer une limite "illimitée" (0) qui ne l'est pas
    // toujours réellement : on garde quand même un plafond de sécurité par
    // défaut plutôt que de dépendre de cette hypothèse.
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
    fwrite(STDERR, 'Campagne introuvable : '.$campaignId."\n");
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
