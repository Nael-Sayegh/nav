<?php $logonly = true;
$adminonly = true;
$justna = true;
$titlePAdm = 'Réinitialiser la date de dernier envoi';
require_once($_SERVER['DOCUMENT_ROOT'].'/include/log.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/include/consts.php');
requireAdminRight('manage_newsletter');

if (isset($_GET['act']) && $_GET['act'] === 'form')
{
    $SQL = <<<SQL
        UPDATE newsletter_mails SET lastmail=0
        SQL;
    $bdd->query($SQL);
    $log = 'OK';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Administration - <?php print $site_name; ?></title>
<?php print $admin_css_path; ?>
<script type="text/javascript" src="/scripts/default.js"></script>
</head>
<body>
<?php require_once(__DIR__ . '/include/banner.php'); ?>
<div id="alertZone" role="alert" aria-live="assertive"></div>
<?php if (!empty($log)): ?>
<noscript>
<p role="alert"><b><?= $log ?></b></p>
</noscript>
<script>
    window.addEventListener('DOMContentLoaded', () =>
    {
        const alertZone = document.getElementById('alertZone');
        alertZone.innerHTML = '<p><b><?= addslashes((string) $log) ?></b></p>';
    });
</script>
<?php endif; ?>
<p>Appuyez sur le bouton ci-dessous pour réinitialiser la date de dernier envoi aux abonnés à la lettre d'informations</p>
<form action="?act=form" method="post">
<input type="submit" value="Envoyer">
</form>
</body>
</html>
