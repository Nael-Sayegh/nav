<?php
$nolog = true;
require_once('include/log.php');
$stats_page = 'signup';
set_include_path($_SERVER['DOCUMENT_ROOT']);
require_once('include/consts.php');
require_once('include/lib/mtcaptcha/lib/class.mtcaptchalib.php');
$title = 'Se créer un compte '.$site_name;

$log = '';
if (isset($_GET['a']) && $_GET['a'] === 'form' && isset($_POST['username']) && isset($_POST['mail']) && isset($_POST['psw']) && isset($_POST['rpsw']))
{
    if (strlen((string) $_POST['username']) > 32 || strlen((string) $_POST['username']) < 3)
    {
        $log .= '<li>Votre nom d\'utilisateur doit comporter entre 3 et 32 caractères.</li>';
    }
    if (strlen($_POST['mail']) > 255 || empty($_POST['mail']))
    {
        $log .= '<li>Votre adresse e-mail ne doit pas dépasser 255 caractères.</li>';
    }
    if ($_POST['psw'] !== $_POST['rpsw'])
    {
        $log .= '<li>Veuillez rentrer deux fois le mot de passe identique.</li>';
    }
    if (strlen($_POST['psw']) > 128 || strlen($_POST['psw']) < 8)
    {
        $log .= '<li>Votre mot de passe doit comporter entre 8 et 64 caractères.</li>';
    }
    $MTCaptchaSDK = new MTCaptchaLib(MTCAPTCHA_PRIVATE);
    $result = $MTCaptchaSDK->validate_token($_POST['mtcaptcha-verifiedtoken']);
    if (!$result)
    {
        $log .= '<li>Le code de vérification antispam est incorrect</li>';
    }
    if (empty($log))
    {
        $username = $_POST['username'];
        $SQL = <<<SQL
            SELECT username,email FROM accounts WHERE username=:username OR email=:mail LIMIT 1
            SQL;
        $req = $bdd2->prepare($SQL);
        $req->execute([':username' => $username, ':mail' => $_POST['mail']]);
        if ($data = $req->fetch())
        {
            if ($data['username'] === $username)
            {
                $log .= '<li>Ce nom d\'utilisateur est déjà utilisé&#8239;!</li>';
            }
            if ($data['email'] === $_POST['mail'])
            {
                $log .= '<li>Cette adresse e-mail est déjà utilisée&#8239;!</li>';
            }
        }
        else
        {
            $ok = 100;
            while ($ok > 0)
            {
                $id64 = base64_encode(hash('sha256', time().random_int(1000000, 9999999).$username.random_int(10000000, 99999999), true));
                $id64 = str_replace('/', '-', $id64);
                $id64 = str_replace('+', '_', $id64);
                $id64 = str_replace('=', '.', $id64);
                $SQL = <<<SQL
                    SELECT id FROM accounts WHERE id64=:id
                    SQL;
                $req = $bdd2->prepare($SQL);
                $req->execute([':id' => $id64]);
                if ($req->fetch())
                {
                    $ok -= 1;
                }
                else
                {
                    $ok = 0;
                }
                if ($ok === 1)
                {
                    print 'Erreur, veuillez réessayer';
                    exit();
                }
            }
            $password = password_hash($_POST['psw'], PASSWORD_DEFAULT);
            $mhash = hash('sha512', strval(time() + random_int(1000000, 99999999)).$password.strval(random_int(100000, 99999999)));
            $settings = ['mhash' => $mhash,'menu' => '0','fontsize' => '16','date' => '0','infosdef' => '1'];
            if (isset($_COOKIE['menu']) && $_COOKIE['menu'] === '1')
            {
                $settings['menu'] = '1';
            }
            if (isset($_COOKIE['fontsize']) && in_array($_COOKIE['fontsize'], ['11','16','20','24']))
            {
                $settings['fontsize'] = $_COOKIE['fontsize'];
            }
            if (isset($_COOKIE['date']) && $_COOKIE['date'] === '1')
            {
                $settings['date'] = '1';
            }
            if (isset($_COOKIE['infosdef']) && $_COOKIE['infosdef'] === '0')
            {
                $settings['infosdef'] = '0';
            }
            $right = ['view_members' => 0];
            $email = $_POST['mail'];
            $SQL = <<<SQL
                INSERT INTO accounts (username, email, id64, password, signup_date, settings, rights) VALUES(:username,:mail,:id,:psw,:date,:set,:rights)
                SQL;
            $req = $bdd2->prepare($SQL);
            $req->execute([':username' => $username, ':mail' => $email, ':id' => $id64, ':psw' => $password, ':date' => time(), ':set' => json_encode($settings), ':rights' => json_encode($right)]);
            $id = $bdd->lastInsertId();


            include('include/sendconfirm.php');
            send_confirm($id, $email, $mhash, $username);
            header('Location: /login.php?signed='.$id.'&mail='.sha1((string) $email));

            if (isset($_POST['nl']) && $_POST['nl'] === 'on')
            {
                $SQL = <<<SQL
                    SELECT id FROM newsletter_mails WHERE mail=:mail LIMIT 1
                    SQL;
                $req = $bdd->prepare($SQL);
                $req->execute([':mail' => $email]);
                if ($req->fetch())
                {
                    exit();
                }
                $SQL = <<<SQL
                    INSERT INTO newsletter_mails (hash, mail, expire, freq, notif_site, notif_upd, confirm) VALUES (:hash, :mail, :exp, 3, true, 1, false)
                    SQL;
                $req = $bdd->prepare($SQL);
                $req->execute([':hash' => sha1(strval(random_int(0, mt_getrandmax()) + time()).$email).sha1($email.$_SERVER['REMOTE_ADDR'].strval(random_int(0, mt_getrandmax()))), ':mail' => $email, ':exp' => time() + 86400]);
            }
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<?php require_once('include/header.php'); ?>
<body>
<?php require_once('include/banner.php'); ?>
<main id="container">
<h1 id="contenu"><?php print $title; ?></h1>
<div id="alertZone" role="alert" aria-live="assertive"></div>
<?php if (!empty($log)): ?>
<noscript>
<ul id="log" role="alert"><?= $log ?></ul>
</noscript>
<script>
    window.addEventListener('DOMContentLoaded', () =>
    {
        const alertZone = document.getElementById('alertZone');
        alertZone.innerHTML = '<ul id="log"><?= addslashes($log) ?></ul>';
    });
</script>
<?php endif; ?>
<form action="?a=form" method="post">
<table>
<tr><td class="formlabel"><label for="f_username">Nom d'utilisateur&nbsp;:</label></td>
<td><input type="text" id="f_username" name="username" maxlength="32" autocomplete="username" required></td></tr>
<tr><td class="formlabel"><label for="f_mail">Adresse e-mail&nbsp;:</label></td>
<td><input type="email" id="f_mail" name="mail" maxlength="255" required></td></tr>
<tr><td class="formlabel"><label for="f_psw">Mot de passe&nbsp;:</label></td>
<td><input type="password" id="f_psw" name="psw" maxlength="64" autocomplete="new-password" required></td></tr>
<tr hidden id="js-gen-psw">
<td colspan="2"><button type="button" id="btn-generate-psw">Générer un mot de passe</button><br></td>
</tr>
<tr><td class="formlabel"><label for="f_rpsw">Mot de passe (vérification)&nbsp;:</label></td>
<td><input type="password" id="f_rpsw" name="rpsw" maxlength="64" autocomplete="new-password" required></td></tr>
<tr><td class="formlabel"><label for="f_nl">S'inscrire à la lettre d'information&nbsp;:</label></td>
<td><input type="checkbox" id="f_nl" name="nl"> <span>(mail hebdomadaire pour rester informer des mises à jours)</span></td></tr>
</table>
<div class="mtcaptcha"></div>
<noscript>
<p><em>Activez JavaScript si vous souhaitez générer un mot de passe via le site</em></p>
</noscript>
<p>L'usage des cookies est nécessaire pour utiliser l'espace membres. Vous créer un compte <?= $site_name ?> confirme que vous acceptez les cookies en vous identifiant.<br>Nous ne partagerons pas votre adresse e-mail avec des tiers. Vous pourrez modifier les paramètres de votre compte ou le supprimer à tout moment.</p>
<input type="submit" value="S'inscrire">
</form>
</main>
<?php require_once('include/footer.php'); ?>
<script>
    document.addEventListener('DOMContentLoaded', function()
    {
        const genRow = document.getElementById('js-gen-psw');
        if (genRow) genRow.hidden = false;
    });
    (function()
    {
        const CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()-_=+[]{};:,.<>?';
        const DEFAULT_LENGTH = 16;
        function generatePassword(length = DEFAULT_LENGTH)
        {
            const array = new Uint32Array(length);
            window.crypto.getRandomValues(array);
            return Array.from(array, num => CHARS[num % CHARS.length]).join('');
        }
        document.getElementById('btn-generate-psw').addEventListener('click', function()
        {
            const pwd = generatePassword();
            document.getElementById('f_psw').value = pwd;
            document.getElementById('f_psw').type = 'text';
            document.getElementById('f_rpsw').value = pwd;
            document.getElementById('f_psw').focus();
            document.getElementById('f_psw').select();
        });
    })();
</script>
</body>
</html>
