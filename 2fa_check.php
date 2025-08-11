<?php
require_once('include/log.php');
require_once('include/consts.php');
require_once('vendor/autoload.php');
use RobThree\Auth\Providers\Qr\EndroidQrCodeProvider;
use RobThree\Auth\TwoFactorAuth;

$qrProvider = new EndroidQrCodeProvider();
$tfa = new TwoFactorAuth($qrProvider);
$tr = load_tr($lang, 'login');

session_start();

if (!isset($_SESSION['2fa_pending']) || !isset($_SESSION['2fa_temp_data']))
{
    header('Location: /login.php');
    exit();
}

$account = $_SESSION['2fa_temp_data'];

if (isset($_POST['code']))
{
    if ($tfa->verifyCode($account['twofa_secret'], $_POST['code']))
    {
        $session = hash('sha512', time().random_int(100000, 999999).sha1(random_int(100000, 999999).$account['id']));
        $connectid = hash('sha256', time().random_int(100000, 999999).sha1(random_int(100000, 999999).$account['id']));
        $token = urlsafe_b64encode(hash('sha256', strval(random_int(100000, 999999).$connectid), true));
        $created = time();
        $expire = $created + 31557600;
        setcookie('session', $session, ['expires' => $expire, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'strict']);
        setcookie('connectid', $connectid, ['expires' => $expire, 'path' => '/', 'secure' => false, 'httponly' => true, 'samesite' => 'strict']);
        $SQL = <<<SQL
            INSERT INTO sessions (account, session, connectid, expire, created, token) VALUES (:acc,:session,:connectid,:exp,:create,:token)
            SQL;
        $req2 = $bdd->prepare($SQL);
        $req2->execute([':acc' => $account['id'], ':session' => password_hash($session, PASSWORD_DEFAULT), ':connectid' => $connectid, ':exp' => $expire, ':create' => $created, ':token' => $token]);
        unset($_SESSION['2fa_pending'], $_SESSION['2fa_temp_data']);
        $_SESSION['after_login_to'] = $_SESSION['intended_after_login'] ?? '/';
        header('Location: /login_redirect.php');
        exit();
    }
    else
    {
        $log = tr($tr, 'wrong_2fa');
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<?php require_once('include/header.php'); ?>
<body>
<main id="container">
<h1 id="contenu"><?= tr($tr, '2fa_check_title') ?></h1>
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
<?php endif; ?>
<form method="post">
<label for="f2fa_code"><?= tr($tr, 'enter_2fa_code') ?></label>
<input type="text" name="code" id="f2fa_code" maxlength="6" required>
<input type="submit" value="<?= tr($tr, 'confirm') ?>">
</form>
</main>
</body>
</html>
