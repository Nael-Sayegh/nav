<?php $logonly = true;
$adminonly = true;
$justna = true;
require_once($_SERVER['DOCUMENT_ROOT'].'/include/log.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/include/consts.php');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Administration - <?php print $site_name; ?></title>
<?php print $admin_css_path; ?>
<script src="/scripts/default.js"></script>
</head>
<body>
<?php require_once(__DIR__ . '/include/banner.php'); ?>
<h2>Statistiques</h2>
<p><?php
require_once($_SERVER['DOCUMENT_ROOT'].'/cache/codestatc.php');
if (isset($codestat_n_files))
{
    echo $codestat_n_files.' fichiers, ';
}
if (isset($codestat_n_lines))
{
    echo $codestat_n_lines.' lignes, ';
}
if (isset($codestat_n_chars))
{
    echo $codestat_n_chars.' octets';
}
?></p>
<p>La géolocalisation utilise <a href="https://www.maxmind.com/">MaxMind</a></p>
</body>
</html>
