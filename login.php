<?php
$nolog = true;
require_once('include/log.php');
$stats_page = 'login';
set_include_path($_SERVER['DOCUMENT_ROOT']);
require_once('include/consts.php');
if (session_status() !== PHP_SESSION_ACTIVE)
{
    session_start();
}
if (isset($_GET['redirect']))
{
    $_SESSION['intended_after_login'] = filter_var($_GET['redirect'], FILTER_SANITIZE_URL);
}
$tr = load_tr($lang, 'login');
$title = tr($tr, 'title');

$log = '';
if (isset($_POST['username']) && isset($_POST['psw']))
{
    $SQL = <<<SQL
        SELECT * FROM accounts WHERE username=:username OR email=:mail LIMIT 2
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':username' => $_POST['username'], ':mail' => $_POST['username']]);

    while ($data = $req->fetch())
    {
        if (password_verify($_POST['psw'], (string) $data['password']))
        {
            if (!empty($data['twofa_enabled']) && !empty($data['twofa_secret']))
            {
                $_SESSION['2fa_pending'] = $data['id'];
                $_SESSION['2fa_temp_data'] = $data;
                header('Location: /2fa_check.php');
                exit();
            }
            $session = hash('sha512', time().random_int(100000, 999999).sha1(random_int(100000, 999999).$_POST['psw']));
            $connectid = hash('sha256', time().random_int(100000, 999999).sha1(random_int(100000, 999999).$data['id']));
            $token = urlsafe_b64encode(hash('sha256', strval(random_int(100000, 999999).$connectid), true));
            $created = time();
            $expire = $created + 31557600;
            setcookie('session', $session, ['expires' => $expire, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'strict']);
            setcookie('connectid', $connectid, ['expires' => $expire, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'strict']);
            $SQL2 = <<<SQL
                INSERT INTO sessions (account, session, connectid, expire, created, token) VALUES (:acc,:session,:connectid,:exp,:create,:token)
                SQL;
            $req2 = $bdd->prepare($SQL2);
            $req2->execute([':acc' => $data['id'], ':session' => password_hash($session, PASSWORD_DEFAULT), ':connectid' => $connectid, ':exp' => $expire, ':create' => $created, ':token' => $token]);
            $to = $_POST['redirect'] ?? ($_SESSION['intended_after_login'] ?? '/');
            unset($_SESSION['intended_after_login']);
            $_SESSION['after_login_to'] = $to;
            header('Location: /login_redirect.php');
            exit();
        }
        else
        {
            $log = tr($tr, 'wrong');
        }
    }
}
if (isset($_GET['signed']) && isset($_GET['mail']))
{
    $SQL = <<<SQL
        SELECT email FROM accounts WHERE id=:id AND confirmed=false LIMIT 1
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':id' => $_GET['signed']]);
    if ($data = $req->fetch())
    {
        if (sha1((string) $data['email']) === $_GET['mail'])
        {
            $log = tr($tr, 'account_created');
        }
    }
}
if (isset($_GET['confirmed']))
{
    $log = tr($tr, 'confirmed');
}
elseif (isset($_GET['confirm_err']))
{
    $log = tr($tr, 'confirm_err');
}
elseif (isset($_GET['logonly']))
{
    $log = tr($tr, 'logonly');
}
elseif (isset($_GET['passwdreseted']))
{
    $log = tr($tr, 'reset_success');
}
elseif (isset($_GET['goodbye']))
{
    $log = tr($tr, 'goodbye');
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<?php require_once('include/header.php'); ?>
<body>
<?php require_once('include/banner.php'); ?>
<main id="container">
<h1 id="contenu"><?php print $title; ?></h1>
<div id="alertZone" role="alert" aria-live="assertive"></div>
<?php if (!empty($log)): ?>
<noscript>
<p id="log" role="alert"><b><?= $log ?></b></p>
</noscript>
<script>
    window.addEventListener('DOMContentLoaded', () =>
    {
        const alertZone = document.getElementById('alertZone');
        alertZone.innerHTML = '<p id="log"><b><?= addslashes((string) $log) ?></b></p>';
    });
</script>
<?php endif;
$redirect = $_SESSION['intended_after_login'] ?? '/';
?>
<form action="?a=form#log" method="post">
<input type="text" id="f1_username" name="username" placeholder="<?= tr($tr, 'username') ?>" maxlength="32" aria-label="<?= tr($tr, 'username') ?>" autofocus><br>
<input type="password" id="f1_psw" name="psw" placeholder="<?= tr($tr, 'password') ?>" maxlength="64" aria-label="<?= tr($tr, 'password') ?>"><br>
<input type="hidden" name="redirect" value="<?= htmlspecialchars((string) $redirect, ENT_QUOTES) ?>">
<input type="submit" id="f1_submit" value="<?= tr($tr, 'bt_login') ?>">
</form>
<a href="/fg_password.php"><?= tr($tr, 'forgot_psw') ?></a><br>
<a href="/signup.php"><?= tr($tr, 'signup') ?></a>
<p><?= tr($tr, 'cookies') ?></p>
</main>
<?php require_once('include/footer.php'); ?>
</body>
</html>
