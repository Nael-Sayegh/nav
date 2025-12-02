<?php $logonly = true;
$adminonly = true;
$justna = true;
$titlePAdm = 'Catégories';
require_once($_SERVER['DOCUMENT_ROOT'].'/include/log.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/include/consts.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/include/sitemap.php');
requireAdminRight('manage_categories');
if (isset($_GET['add'], $_POST['name']))
{
    $SQL = <<<SQL
        INSERT INTO softwares_categories(name,text) VALUES(:name,:text)
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':name' => htmlspecialchars((string) $_POST['name']), ':text' => $_POST['text']]);
    update_sitemap_url($site_url . '/c' . $bdd->lastInsertId());
}
if (isset($_GET['delete']))
{
    $SQL = <<<SQL
        DELETE FROM softwares_categories WHERE id=:id
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':id' => $_GET['delete']]);
    remove_sitemap_url($site_url . '/c' . $_GET['delete']);
}
if (isset($_GET['mod2'], $_POST['name']))
{
    $SQL = <<<SQL
        UPDATE softwares_categories SET name=:name, text=:text WHERE id=:id
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':name' => htmlspecialchars((string) $_POST['name']), ':text' => $_POST['text'], ':id' => $_GET['mod2']]);
    update_sitemap_url($site_url . '/c' . $_GET['mod2']);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Gestion des catégories de <?php print $site_name; ?></title>
<?php print $admin_css_path; ?>
<script type="text/javascript" src="/scripts/default.js"></script>
</head>
<body>
<?php require_once(__DIR__ . '/include/banner.php'); ?>
<table border="1">
<thead><tr><th>Numéro de catégorie</th><th>Nom</th><th>Actions</th></tr></thead>
<tbody>
<?php
$SQL = <<<SQL
    SELECT * FROM softwares_categories ORDER BY name ASC
    SQL;
foreach ($bdd->query($SQL) as $data)
{
    echo '<tr><td>C'.$data['id'].'</td><td>'.$data['name'].'</td><td><a href="?delete='.$data['id'].'" onclick="return confirm(\'Faut-il vraiment supprimer la catégorie '.$data['name'].'&nbsp;?\')">Supprimer</a> | <a href="?mod='.$data['id'].'">Modifier</a></td></tr>';
}
?>
</tbody>
</table>

<?php
if (isset($_GET['mod']))
{
    $SQL = <<<SQL
        SELECT * FROM softwares_categories WHERE id=:id ORDER BY name ASC LIMIT 1
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':id' => $_GET['mod']]);
    if ($data = $req->fetch())
    { ?>
<h3>Modification de la catégorie</h3>
<form action="?mod2=<?= $data['id'] ?>" method="post">
<label for="f2_name">Nom&nbsp;:</label><input type="text" name="name" id="f2_name" maxlength="255" value="<?= $data['name'] ?>" required><br>
<label for="f2_text">Texte d'introduction HTML&nbsp;:</label><br>
<textarea name="text" id="f2_text" maxlength="2047" rows="20" cols="500"><?= $data['text'] ?></textarea><br>
<input type="submit" value="Modifier">
</form>
<?php }
    }
?>

<h2>Ajout d'une catégorie</h2>
<form action="?add" method="post">
<label for="f_name">Nom&nbsp;:</label><input type="text" name="name" id="f_name" maxlength="255" required><br>
<label for="f_text">Texte d'introduction HTML&nbsp;:</label><br>
<textarea name="text" id="f_text" maxlength="2047" rows="20" cols="500"></textarea><br>
<input type="submit" value="Ajouter">
</form>
</body>
</html>
