<?php
$adminonly = true;
$justna = true;
$titlePAdm = "Gérer le sitemap";
require_once($_SERVER['DOCUMENT_ROOT'].'/include/log.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/include/consts.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/include/sitemap.php');
$log = "";
if (isset($_GET['act']) && $_GET['act'] === 'g') {
    $nb = generate_sitemap();
    $log .= "Sitemap généré avec $nb URLs";
}
if (isset($_GET['act']) && $_GET['act'] === 'a') {
    if (isset($_POST['url']) && !empty($_POST['url'])) {
        $r = update_sitemap_url($_POST['url']);
        if ($r) {
            $log .= "URL ajoutée au sitemap : " . htmlspecialchars($_POST['url']);
        } else {
            $log .= "Erreur lors de l'ajout de l'URL au sitemap.";
        }
    }
}
if (isset($_GET['act']) && $_GET['act'] === 'r') {
    if (isset($_POST['url']) && !empty($_POST['url'])) {
        $r = remove_sitemap_url($_POST['url']);
        if ($r) {
            $log .= "URL retirée du sitemap : " . htmlspecialchars($_POST['url']);
        } else {
            $log .= "Erreur lors de la suppression de l'URL du sitemap.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $titlePAdm . ' ' . $site_name; ?></title>
    <?php print $admin_css_path; ?>
    <script type="text/javascript" src="/scripts/default.js"></script>
</head>
<body>
    <?php require_once('include/banner.php');
    if (isset($log) && !empty($log)) {
        echo '<p role="alert">' . $log . '</p>';
    } ?>
    <h2>Gérer le sitemap entier</h2>
    <form method="post" action="?act=g">
        <button type="submit">Générer le sitemap</button>
    </form>
    <h2>Ajouter une URL</h2>
    <form method="post" action="?act=a">
        <input type="text" name="url" placeholder="Ajouter une URL" required>
        <button type="submit">Ajouter</button>
    </form>
    <h2>Retirer une URL</h2>
    <form method="post" action="?act=r">
        <input type="text" name="url" placeholder="Retirer une URL" required>
        <button type="submit">Retirer</button>
    </form>
</body>
</html>
