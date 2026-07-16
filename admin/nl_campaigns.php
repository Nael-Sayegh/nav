<?php $logonly = true;
$adminonly = true;
$justna = true;
$titlePAdm = "Suivi des envois de la lettre d'informations";
require_once($_SERVER['DOCUMENT_ROOT'].'/include/log.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/include/consts.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/include/newsletter_campaigns.php');
requireAdminRight('manage_newsletter');

$log = '';
if (isset($_GET['resume']) && preg_match('/^\d+$/', (string) $_GET['resume']))
{
    launchNewsletterCampaignPass((int) $_GET['resume']);
    $log = "Reprise de l'envoi déclenchée.";
}
elseif (isset($_GET['id']) && preg_match('/^\d+$/', (string) $_GET['id']))
{
    $log = "La campagne a bien été créée, l'envoi vient de démarrer.";
}

function nlCampaignStatusLabel(string $status): string
{
    return match ($status)
    {
        'pending' => 'En attente de démarrage',
        'running' => 'En cours',
        'done'    => 'Terminée',
        default   => $status,
    };
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Suivi des envois - <?php print $site_name; ?></title>
<?php print $admin_css_path; ?>
<script type="text/javascript" src="/scripts/default.js"></script>
</head>
<body>
<?php require_once(__DIR__ . '/include/banner.php'); ?>
<div id="alertZone" role="alert" aria-live="assertive"></div>
<?php if ($log !== ''): ?>
<noscript>
<p role="alert"><b><?= $log ?></b></p>
</noscript>
<script>
    window.addEventListener('DOMContentLoaded', () =>
    {
        const alertZone = document.getElementById('alertZone');
        alertZone.innerHTML = '<p><b><?= addslashes($log) ?></b></p>';
    });
</script>
<?php endif; ?>
<table>
<thead>
<tr><th>Sujet</th><th>Statut</th><th>Total</th><th>Envoyés</th><th>En échec</th><th>En attente</th><th>Créée le</th><th>Dernière progression</th><th>Action</th></tr>
</thead>
<tbody>
<?php
foreach (listNewsletterCampaigns() as $campaign)
{
    $counts = getNewsletterCampaignRecipientCounts((int) $campaign['id']);
    $enEchec = $counts['failed'] + $counts['failed_permanent'];
    echo '<tr>';
    echo '<td>'.htmlspecialchars((string) $campaign['subject'], ENT_QUOTES, 'UTF-8').'</td>';
    echo '<td>'.htmlspecialchars(nlCampaignStatusLabel((string) $campaign['status']), ENT_QUOTES, 'UTF-8').'</td>';
    echo '<td>'.$counts['total'].'</td>';
    echo '<td>'.$counts['sent'].'</td>';
    echo '<td>'.$enEchec.'</td>';
    echo '<td>'.$counts['pending'].'</td>';
    echo '<td>'.date('d/m/Y H:i', (int) $campaign['created_at']).'</td>';
    echo '<td>'.((int) $campaign['last_progress_at'] > 0 ? date('d/m/Y H:i', (int) $campaign['last_progress_at']) : '-').'</td>';
    echo '<td>';
    if ($campaign['status'] !== 'done')
    {
        printf(
            '<a href="?resume=%d">Reprendre l\'envoi<span class="sr_only"> de la campagne « %s »</span></a>',
            (int) $campaign['id'],
            htmlspecialchars((string) $campaign['subject'], ENT_QUOTES, 'UTF-8'),
        );
    }
    else
    {
        echo '-';
    }
    echo '</td>';
    echo '</tr>';
}
?>
</tbody>
</table>
</body>
</html>
