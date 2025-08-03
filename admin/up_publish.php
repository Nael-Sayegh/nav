<?php $logonly = true;
$adminonly = true;
$justna = true;
$titlePAdm = 'Versions du site';
require_once($_SERVER['DOCUMENT_ROOT'].'/include/log.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/include/consts.php');
requireAdminRight('publish_versions');
if (isset($_GET['add']) && isset($_POST['name']) && isset($_POST['text']))
{
    require_once($_SERVER['DOCUMENT_ROOT'].'/tasks/codestat.php');
    $codestat_n_files = -1;
    $codestat_n_lines = -1;
    $codestat_n_chars = -1;
    require_once($_SERVER['DOCUMENT_ROOT'].'/cache/codestatc.php');
    $SQL = <<<SQL
        INSERT INTO site_updates (name, text,date,authors,codestat) VALUES(:name,:text,:date,:author,:stats)
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':name' => htmlspecialchars((string) $_POST['name']), ':text' => $_POST['text'], ':date' => time(), ':author' => $admin_name, ':stats' => json_encode([$codestat_n_files, $codestat_n_lines, $codestat_n_chars])]);

    require_once($_SERVER['DOCUMENT_ROOT'].'/tasks/history_cache.php');
    $SQL = <<<SQL
        SELECT id,name FROM site_updates ORDER BY id DESC LIMIT 1
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute();
    if ($data = $req->fetch())
    {
        require_once($_SERVER['DOCUMENT_ROOT'].'/include/lib/facebook/fb_publisher.php');
        send_facebook($site_name.' version '.substr((string) $data['name'], 1).' publié, changements sur '.SITE_URL.'/u'.$data['id'].' '.$admin_name);
        require_once($_SERVER['DOCUMENT_ROOT'].'/include/lib/Mastodon/mastodon_publisher.php');
        send_mastodon($site_name.' version '.substr((string) $data['name'], 1).' publié, changements sur '.SITE_URL.'/u'.$data['id'].' '.$admin_name);
        require_once($_SERVER['DOCUMENT_ROOT'].'/include/lib/discord_publisher.php');
        send_discord($admin_name.' vient de publier '.$site_name.' version '.substr((string) $data['name'], 1).'. Retrouvez tous les détails sur : '.SITE_URL.'/u'.$data['id']);
    }
}
if (isset($_GET['delete']))
{
    $SQL = <<<SQL
        DELETE FROM site_updates WHERE id=:id
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':id' => $_GET['delete']]);
    require_once($_SERVER['DOCUMENT_ROOT'].'/tasks/history_cache.php');
}
if (isset($_GET['mod2']) && isset($_POST['name']) && isset($_POST['text']))
{
    $SQL = <<<SQL
        UPDATE site_updates SET name=:name, text=:text, authors=:author WHERE id=:id
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':name' => htmlspecialchars((string) $_POST['name']), ':text' => $_POST['text'], ':author' => $admin_name, ':id' => $_GET['mod2']]);
    require_once($_SERVER['DOCUMENT_ROOT'].'/tasks/history_cache.php');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Gestion des versions de <?php print $site_name; ?></title>
<?php print $admin_css_path; ?>
<script type="text/javascript" src="/scripts/default.js"></script>
</head>
<body>
<?php require_once('include/banner.php'); ?>
<table border="1">
<thead><tr><th>ID</th><th>Numéro de version</th><th>Date</th><th>Actions</th></tr></thead>
<tbody>
<?php
$SQL = <<<SQL
    SELECT * FROM site_updates ORDER BY date ASC
    SQL;
foreach ($bdd->query($SQL) as $data)
{
    $versionxx = substr((string) $data['name'], 1);
    echo '<tr><td>V'.$data['id'].'</td><td>'.$versionxx.'</td><td>'.date('d/m/Y H:i', $data['date']).'</td><td><a href="?delete='.$data['id'].'" onclick="return confirm(\'Faut-il vraiment supprimer la version '.$versionxx.'&nbsp;?\')">Supprimer</a> | <a href="?mod='.$data['id'].'#mod">Modifier</a></td></tr>';
}
?>
</tbody>
</table>

<?php
if (isset($_GET['mod']))
{
    $SQL = <<<SQL
        SELECT * FROM site_updates WHERE id=:id LIMIT 1
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':id' => $_GET['mod']]);
    if ($data = $req->fetch())
    { ?>
<h3 id="mod">Modification de la mise à jour</h3>
<form action="?mod2=<?= $data['id'] ?>" method="post">
<label for="f2_name">Nom&nbsp;:</label><input type="text" name="name" id="f2_name" maxlength="255" value="<?= $data['name'] ?>" required><br>
<label for="f2_text">Texte descriptif HTML&nbsp;:</label><br>
<textarea name="text" id="f2_text" maxlength="50000" rows="20" cols="500"><?= $data['text'] ?></textarea><br>
<input type="submit" value="Modifier">
</form>
<?php }
    }
?>

<h2>Ajout d'une mise à jour</h2>
<form action="?add" method="post">
<label for="f_name">Nom&nbsp;:</label><input type="text" name="name" id="f_name" maxlength="255" required><br>
<label for="f_text">Texte descriptif HTML&nbsp;:</label><br>
<textarea name="text" id="f_text" maxlength="50000" rows="20" cols="500"></textarea><br>
<input type="submit" value="Ajouter">
</form>
</body>
</html>
