<?php
$logonly = true;
$adminonly = true;
$justpa = true;
$titlePAdm = 'Gestion des comptes membres';
require_once($_SERVER['DOCUMENT_ROOT'].'/include/log.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/include/consts.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/include/sendMail.php');
requireAdminRight('manage_members');
if (isset($_GET['delete']))
{
    $SQL = <<<SQL
        DELETE FROM accounts WHERE id=:id
        SQL;
    $req = $bdd2->prepare($SQL);
    $req->execute([':id' => $_GET['delete']]);
}
if (isset($_GET['mod2']) && isset($_POST['username'], $_POST['email'], $_POST['rank']))
{
    $SQLOld = <<<SQL
        SELECT rights, twofa_enabled FROM accounts WHERE id = :id
        SQL;
    $reqOld = $bdd2->prepare($SQLOld);
    $reqOld->execute([':id' => $_GET['mod2']]);
    if ($oldData = $reqOld->fetch())
    {
        $oldMap = json_decode((string) $oldData['rights'], true);
        if (!is_array($oldMap))
        {
            $oldMap = [];
        }
        $posted = $_POST['rights'] ?? [];
        $rightsMap = [];
        foreach (ALL_MEMBER_RIGHTS as $key => $_label)
        {
            $rightsMap[$key] = in_array($key, $posted, true) ? 1 : 0;
        }
        $json = json_encode($rightsMap, JSON_PRESERVE_ZERO_FRACTION);
        $changes = [];
        foreach (ALL_MEMBER_RIGHTS as $key => $label)
        {
            $oldVal = !empty($oldMap[$key]);
            $newVal = !empty($rightsMap[$key]);
            if ($oldVal !== $newVal)
            {
                $changes[$key] = $newVal;
            }
        }
        $twofaDisabled = false;
        if ($oldData['twofa_enabled'] && isset($_POST['disable2fa']) && $_POST['disable2fa'] === '1')
        {
            $twofaDisabled = true;
        }
    }
    if (isset($_POST['password']) && !empty($_POST['password']))
    {
        $rawPassword = null;
        if (!empty($_POST['password']))
        {
            $rawPassword = (string)$_POST['password'];
            $password = password_hash((string) $rawPassword, PASSWORD_DEFAULT);
        }
        $SQL = <<<SQL
            UPDATE accounts SET username=:username, email=:email, password=:psw, rank=:rank, rights=:rights WHERE id=:id
            SQL;
        $req = $bdd2->prepare($SQL);
        $req->execute([':username' => htmlentities((string) $_POST['username']), ':email' => $_POST['email'], ':psw' => $password, ':rank' => $_POST['rank'], ':rights' => $json, ':id' => $_GET['mod2']]);
    }
    else
    {
        $SQL = <<<SQL
            UPDATE accounts SET username=:username, email=:email, rank=:rank, rights=:rights WHERE id=:id
            SQL;
        $req = $bdd2->prepare($SQL);
        $req->execute([':username' => htmlentities((string) $_POST['username']), ':email' => $_POST['email'], ':rank' => $_POST['rank'], ':rights' => $json, ':id' => $_GET['mod2']]);
    }
    $subject = 'Modification de votre compte membre';
    $username = htmlentities((string) $_POST['username']);
    $body = <<<HTML
                <h2>Bonjour {$username}</h2>
                <p>{$admin_name} vient d'apporter des modifications à votre compte membre sur {$site_name}<br>
                Vos nouvelles informations sont les suivantes :</p>
                <ul>
                <li>Nom d'utilisateur : {$username}</li>
                <li>Adresse mail : {$_POST['email']}</li>
                </ul>
        HTML;
    $altBody = <<<TEXT
                Bonjour {$username}

                {$admin_name} vient d'apporter des modifications à votre compte membre sur {$site_name}
                Vos nouvelles informations sont les suivantes :
                - Nom d'utilisateur : {$username}
                - Adresse mail : {$_POST['email']}

        TEXT;
    if (isset($twofaDisabled) && $twofaDisabled === true)
    {
        $SQL = <<<SQL
            UPDATE accounts SET twofa_enabled=false, twofa_secret=NULL WHERE id=:id
            SQL;
        $req = $bdd2->prepare($SQL);
        $req->execute([':id' => $_GET['mod2']]);
        $body .= <<<HTML
                        <p><strong>L'authentification à 2 facteurs sur votre compte a été désactivée.</strong></p>
            HTML;
        $altBody .= <<<TEXT
                        L'authentification à 2 facteurs sur votre compte a été désactivée.

            TEXT;
    }
    if ($rawPassword !== null)
    {
        $body .= <<<HTML
                        <h3>Votre nouveau mot de passe</h3>
                        <p><strong>{$rawPassword}</strong></p>
            HTML;
        $altBody .= <<<TEXT
                        Votre nouveau mot de passe : {$rawPassword}

            TEXT;
    }
    if (!empty($changes))
    {
        $body .= <<<HTML
                        <h3>Modifications des droits :</h3><ul>
            HTML;
        $altBody .= <<<TEXT
                        Modifications des droits :

            TEXT;
        foreach ($changes as $key => $activated)
        {
            $label = htmlspecialchars(ALL_MEMBER_RIGHTS[$key], ENT_QUOTES, 'UTF-8');
            $state = $activated ? 'Activé' : 'Désactivé';
            $body .= <<<HTML
                                <li>{$label} : <strong>{$state}</strong></li>
                HTML;
            $altBody .= <<<TEXT
                                - {$label} : {$state}

                TEXT;
        }
        $body .= <<<HTML
                        </ul>
            HTML;
    }
    if ($rawPassword === null)
    {
        $body .= <<<HTML
                    <p>Si vous avez perdu votre mot de passe, vous pouvez en demander un nouveau sur <a href="{SITE_URL}/fg_password.php">la page de réinitialisation de mot de passe</a>.</p>
            HTML;
        $altBody .= <<<TEXT
                    Si vous avez perdu votre mot de passe, vous pouvez en demander un nouveau sur la page de réinitialisation de mot de passe: {SITE_URL}/fg_password.php
            TEXT;
    }
    sendMail($_POST['email'], $subject, $body, $altBody);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Gestion des membres <?php print $site_name; ?></title>
<?php print $admin_css_path; ?>
<script type="text/javascript" src="/scripts/default.js"></script>
</head>
<body>
<?php require_once('include/banner.php'); ?>
<table border="1">
<thead><tr><th>Nom d'utilisateur</th><th>Adresse mail</th><th>Rang</th><th>Droits</th><th>Statut A2F</th><th>Actions</th></tr></thead>
<tbody>
<?php
include_once $_SERVER['DOCUMENT_ROOT'].'/include/user_rank.php';
$SQL = <<<SQL
    SELECT * FROM accounts
    SQL;
foreach ($bdd2->query($SQL) as $data)
{
    echo '<tr><td>'.$data['username'].'</td><td><a href="mailto:'.$data['email'].'" title="Envoyer un mail">'.$data['email'].'</a></td><td>'.urank($data['rank']).'</td><td><details><summary>Droits</summary><ul>';
    $rightsMap = json_decode((string) $data['rights'], true) ?: [];
    foreach (ALL_MEMBER_RIGHTS as $key => $label)
    {
        $activated = !array_key_exists($key, $rightsMap) || (bool)$rightsMap[$key];
        printf('<li>%s&nbsp;: <strong>%s</strong></li>', htmlspecialchars($label, ENT_QUOTES, 'UTF-8'), $activated ? 'Activé' : 'Désactivé');
    }
    echo '</ul></details></td><td>'.($data['twofa_enabled'] ? 'Activée' : 'Désactivée').'</td><td><a href="?mod='.$data['id'].'#mod">Modifier</a> | <a href="?delete='.$data['id'].'" onclick="return confirm(\'Faut-il vraiment supprimer le membre '.$data['username'].'&nbsp;?\')">Supprimer</a></td></tr>';
}
?>
</tbody>
</table>
<?php
if (isset($_GET['mod']))
{
    $SQL = <<<SQL
        SELECT * FROM accounts WHERE id=:id LIMIT 1
        SQL;
    $req = $bdd2->prepare($SQL);
    $req->execute([':id' => $_GET['mod']]);
    if ($data = $req->fetch())
    {
        ?>
<h3 id="mod">Modifier</h3>
<form action="?mod2=<?= $data['id'] ?>" method="post">
<label for="f2_username">Nom d'utilisateur&nbsp;:</label>
<input type="text" name="username" id="f2_username" maxlength="32" value="<?= $data['username'] ?>" required><br>
<label for="f2_email">Adresse mail&nbsp;:</label>
<input type="email" name="email" id="f2_email" maxlength="255" value="<?= $data['email'] ?>" required><br>
<label for="f2_psw">Mot de passe&nbsp;:</label>
<input type="password" name="password" id="f2_psw" maxlength="64"><br>
<label for="f2_rank">Rang&nbsp;:</label>
<select id="f2_rank" name="rank">
<?php foreach (RANK_LABELS as $value => $trKey): ?>
<?php $label = tr($tr0, $trKey); ?>
<option value="<?= $value ?>"
<?= $data['rank'] === $value ? 'selected' : '' ?>>
<?= htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') ?>
</option>
<?php endforeach; ?>
</select><br>
<label><input type="checkbox" name="disable2fa" value="1" <?= $data['twofa_enabled'] ? '' : 'disabled' ?>>Désactiver l’authentification à deux facteurs (A2F)</label><br>
<fieldset>
<legend>Droits</legend>
<?php foreach (ALL_MEMBER_RIGHTS as $key => $label): ?>
<label>
<input type="checkbox" name="rights[]" value="<?= $key ?>"
<?= in_array($key, getMemberRights($data['rights']), true) ? 'checked' : '' ?>>
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
</body>
</html>
