<?php $logonly = true;
$adminonly = true;
$justna = true;
$titlePAdm = 'Caches';
require_once($_SERVER['DOCUMENT_ROOT'].'/include/log.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/include/consts.php');
requireAdminRight('update_caches');

$obcache = '';
if (isset($_GET['cache']))
{
    $cachedir = $_SERVER['DOCUMENT_ROOT'].'/cache/';

    ob_start();
    if ($_GET['cache'] === 'all' || $_GET['cache'] === 'menu')
    {
        $sql = 'SELECT id, name, text FROM softwares_categories ORDER BY name ASC';
        $categories = [];
        foreach ($bdd->query($sql) as $row)
        {
            $categories[] = ['id' => (int)$row['id'], 'name' => $row['name'], 'title' => ' - '.strip_tags((string) $row['text'])];
        }
        file_put_contents($cachedir.'menu_categories.json', json_encode($categories, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
    if ($_GET['cache'] === 'all' || $_GET['cache'] === 'journal')
    {
        include($_SERVER['DOCUMENT_ROOT'].'/tasks/history_cache.php');
    }
    if ($_GET['cache'] === 'all' || $_GET['cache'] === 'codestat')
    {
        include($_SERVER['DOCUMENT_ROOT'].'/tasks/codestat.php');
    }
    if ($_GET['cache'] === 'all' || $_GET['cache'] === 'langs')
    {
        include($_SERVER['DOCUMENT_ROOT'].'/tasks/langs_cache.php');
    }
    if ($_GET['cache'] === 'all' || $_GET['cache'] === 'accounts')
    {
        include($_SERVER['DOCUMENT_ROOT'].'/tasks/accounts_manager.php');
    }

    $obcache = ob_get_contents();
    ob_end_clean();
    if (in_array($obcache, ['', '0', false], true))
    {
        header('Location: cache_update.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Gestionnaire des caches &#8211; <?php print $site_name; ?></title>
<?php print $admin_css_path; ?>
<script type="text/javascript" src="/scripts/default.js"></script>
</head>
<body>
<?php require_once(__DIR__ . '/include/banner.php'); ?>
<?php
if ($obcache !== '' && $obcache !== '0')
{
    echo "<fieldset><legend>Cachers' stdout</legend>".$obcache.'</fieldset><br>';
}
?>
<a href="?cache=all">Mettre à jour tous les caches</a>
<ul>
<li><a href="?cache=menu">Mettre à jour le cache des menus (catégories)</a></li>
<li><a href="?cache=journal">Mettre à jour le cache du journal des modifications</a></li>
<li><a href="?cache=codestat">Mettre à jour le cache des statistiques du code</a></li>
<li><a href="?cache=langs">Mettre à jour le cache des langues</a></li>
<li><a href="?cache=accounts">Lancer la tâche de gestion des comptes membre</a></li>
</ul>
</body>
</html>
