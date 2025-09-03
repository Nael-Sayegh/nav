<?php
$logonly = true;
$adminonly = true;
$justna = true;
$titlePAdm = 'Gestion de l\'équipe';
require_once($_SERVER['DOCUMENT_ROOT'].'/include/log.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/include/consts.php');
requireAdminRight('manage_team');

if (isset($_GET['add']) && isset($_POST['name']) && isset($_POST['status']) && isset($_POST['age']) && isset($_POST['short_name']) && isset($_POST['bio']) && isset($_POST['works']) && isset($_POST['mastodon']))
{
    $account_id = null;
    if (isset($_POST['account_id']) && !empty($_POST['account_id']))
    {
        $account_id = $_POST['account_id'];
    }
    $posted = $_POST['rights'] ?? [];
    $rightsMap = [];
    foreach (ALL_ADMIN_RIGHTS as $key => $_label)
    {
        $rightsMap[$key] = in_array($key, $posted, true) ? 1 : 0;
    }
    $json = json_encode($rightsMap, JSON_PRESERVE_ZERO_FRACTION);
    $SQL = <<<SQL
        INSERT INTO team (name, status, date, age, account_id, short_name, bio, works, mastodon, rights) VALUES(:name,:status,:date,:age,:acc,:short,:bio,:works,:masto,:rights)
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':name' => $_POST['name'], ':status' => $_POST['status'], ':date' => time(), ':age' => strtotime(preg_replace('/^(\d{2})\/(\d{2})\/(\d{4})$/', '$3-$2-$1', $_POST['age'])), ':acc' => $account_id, ':short' => $_POST['short_name'], ':bio' => $_POST['bio'], ':works' => $_POST['works'], ':masto' => $_POST['mastodon'], ':rights' => $json]);
}
if (isset($_GET['delete']))
{
    $SQL = <<<SQL
        DELETE FROM team WHERE id=:id
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':id' => $_GET['delete']]);
}
if (isset($_GET['mod2']) && isset($_POST['name']) && isset($_POST['status']) && isset($_POST['age']) && isset($_POST['account_id']) && isset($_POST['short_name']) && isset($_POST['bio']) && isset($_POST['works']) && isset($_POST['mastodon']))
{
    $posted = $_POST['rights'] ?? [];
    $rightsMap = [];
    foreach (ALL_ADMIN_RIGHTS as $key => $_label)
    {
        $rightsMap[$key] = in_array($key, $posted, true) ? 1 : 0;
    }
    $json = json_encode($rightsMap, JSON_PRESERVE_ZERO_FRACTION);
    $SQL = <<<SQL
        UPDATE team SET name=:name, status=:status, age=:age, account_id=:acc, short_name=:short, bio=:bio, works=:works, mastodon=:masto, rights=:rights WHERE id=:id
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':name' => htmlentities((string) $_POST['name']), ':status' => $_POST['status'], ':age' => strtotime(preg_replace('/^(\d{2})\/(\d{2})\/(\d{4})$/', '$3-$2-$1', $_POST['age'])), ':acc' => $_POST['account_id'], ':short' => $_POST['short_name'], ':bio' => $_POST['bio'], ':works' => $_POST['works'], ':masto' => $_POST['mastodon'], ':rights' => $json, ':id' => $_GET['mod2']]);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Gestion de l'équipe de <?php print $site_name; ?></title>
<?php print $admin_css_path; ?>
<script type="text/javascript" src="/scripts/default.js"></script>
</head>
<body>
<?php require_once('include/banner.php'); ?>
<table border="1">
<thead><tr><th>Numéro d'équipier</th><th>Nom</th><th>Nom court</th><th>Statut(s)</th><th>Date</th><th>Âge</th><th>Mastodon</th><th>Droits</th><th>Actions</th></tr></thead>
<tbody>
<?php
$SQL = <<<SQL
    SELECT * FROM team ORDER BY name ASC
    SQL;
foreach ($bdd->query($SQL) as $data)
{
    echo '<tr><td>M'.$data['account_id'].'/E'.$data['id'].'</td><td>'.$data['name'].'</td><td>'.$data['short_name'].'</td><td>'.$data['status'].'</td><td>'.date('d/m/Y H:i', $data['date']).'</td><td>'.intval((time() - $data['age']) / 31557600).'</td><td>@'.$data['mastodon'].'</td><td><details><summary>Droits</summary><ul>';
    $rightsMap = json_decode((string) $data['rights'], true) ?: [];
    foreach (ALL_ADMIN_RIGHTS as $key => $label)
    {
        $activated = !array_key_exists($key, $rightsMap) || (bool)$rightsMap[$key];
        printf('<li>%s&nbsp;: <strong>%s</strong></li>', htmlspecialchars($label, ENT_QUOTES, 'UTF-8'), $activated ? 'Activé' : 'Désactivé');
    }
    echo '</ul></details></td><td><a href="?mod='.$data['id'].'#mod">Modifier</a> | <a href="?delete='.$data['id'].'">Supprimer</a></td></tr>';
}
?>
</tbody>
</table>
<?php
if (isset($_GET['mod']))
{
    $SQL = <<<SQL
        SELECT * FROM team WHERE id=:id LIMIT 1
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':id' => $_GET['mod']]);
    if ($data = $req->fetch())
    { ?>
<h3 id="mod">Modifier</h3>
<form action="?mod2=<?= $data['id'] ?>" method="post">
<label for="f2_name">Nom&nbsp;:</label><input type="text" name="name" id="f2_name" maxlength="255" value="<?= $data['name'] ?>" required><br>
<label for="f2_text">Statut(s)&nbsp;:</label><input type="text" name="status" id="f2_text" maxlength="255" value="<?= htmlentities((string) $data['status']) ?>" required><br>
<label for="f2_age">Date de naissance (dd/mm/aaaa)&nbsp;:</label><input type="text" name="age" id="f2_age" value="<?= date('d/m/Y', $data['age']) ?>" maxlength="10" required><br>
<label for="f2_account">Compte membre&nbsp;:</label>
<select id="f2_account" name="account_id" autocompletion="off">
<option value="">Aucun</option>
<?php
        $SQL2 = <<<SQL
            SELECT id, username FROM accounts WHERE rank='a' ORDER BY id ASC
            SQL;
        foreach ($bdd->query($SQL2) as $data2)
        {
            echo '<option value="'.$data2['id'].'"'.(($data2['id'] === $data['account_id']) ? ' selected' : '').'>M'.$data2['id'].' '.htmlentities((string) $data2['username']).'</option>';
        }
        ?>
</select><br>
<label for="f2_short">Nom court&nbsp;:</label>
<input type="text" name="short_name" id="f2_short" value="<?= $data['short_name'] ?>" maxlength="255" required><br>
<label for="f2_bio">Courte bio&nbsp;:</label>
<textarea id="f2_bio" name="bio" style="width:100%;height:10em;"><?= htmlentities((string) $data['bio']) ?></textarea><br>
<label for="f2_works">Travaille pour&nbsp;:</label>
<select id="f2_works" name="works">
<option value="0" <?php if ($data['works'] === '0')
{
    echo 'selected';
} ?>><?= $site_name; ?></option>
<option value="1" <?php if ($data['works'] === '1')
{
    echo 'selected';
} ?>Blog Nael-Accessvision</option>
<option value="2" <?php if ($data['works'] === '2')
{
    echo 'selected';
} ?>><?= $site_name; ?> et Blog Nael-Accessvision</option>
</select><br>
<label for="f2_mastodon">Pseudo Mastodon (sans le @ et avec l'instance si différent de mastodon.progaccess.net)&nbsp;:</label><input type="text" name="mastodon" id="f2_mastodon" maxlength="255" value="<?= $data['mastodon'] ?>"><br>
<fieldset>
<legend>Droits</legend>
<?php foreach (ALL_ADMIN_RIGHTS as $key => $label): ?>
<label>
<input type="checkbox" name="rights[]" value="<?= $key ?>"
<?= in_array($key, getAdminRights($data['rights']), true) ? 'checked' : '' ?>>
<?= htmlspecialchars((string) $label) ?>
</label><br>
<?php endforeach ?>
</fieldset>
<input type="submit" value="Modifier">
</form>
<?php
    }
}
?>

<h2>Ajouter</h2>
<form action="?add" method="post">
<label for="f_name">Nom&nbsp;:</label><input type="text" name="name" id="f_name" maxlength="255" required><br>
<label for="f_text">Statut(s)&nbsp;:</label><input type="text" name="status" id="f_text" maxlength="255" required><br>
<label for="f_age">Date de naissance (dd/mm/aaaa)&nbsp;:</label><input type="text" name="age" id="f_age" maxlength="10" required><br>
<label for="f_account">Compte membre&nbsp;:</label>
<select id="f_account" name="account_id">
<option value="">Aucun</option>
<?php
$SQL = <<<SQL
    SELECT id, username FROM accounts WHERE rank='a' ORDER BY id ASC
    SQL;
foreach ($bdd->query($SQL) as $data)
{
    echo '<option value="'.$data['id'].'">M'.$data['id'].' '.htmlentities((string) $data['username']).'</option>';
}
?>
</select><br>
<label for="f_short">Nom court&nbsp;:</label>
<input type="text" name="short_name" id="f_short" maxlength="255" required><br>
<label for="f_bio">Courte bio&nbsp;:</label>
<textarea id="f_bio" name="bio" style="width:100%;height:10em;"></textarea><br>
<label for="f_works">Travaille pour&nbsp;:</label>
<select id="f_works" name="works">
<option value="0"><?= $site_name; ?></option>
<option value="1">Blog Nael-Accessvision</option>
<option value="2"><?= $site_name; ?> et Blog Nael-Accessvision</option>
</select><br>
<label for="f_mastodon">Pseudo Mastodon (sans le @ et avec l'instance si différent de mastodon.progaccess.net)&nbsp;:</label><input type="text" name="mastodon" id="f_mastodon" maxlength="255"><br>
<fieldset>
<legend>Droits</legend>
<?php foreach (ALL_ADMIN_RIGHTS as $key => $label): ?>
<label>
<input type="checkbox" name="rights[]" value="<?= $key ?>" checked>
<?= htmlspecialchars((string) $label) ?>
</label><br>
<?php endforeach ?>
</fieldset>
<input type="submit" value="Ajouter">
</form>
</body>
</html>
