<?php
$logonly = true;
require_once('include/log.php');
$stats_page = 'home';
set_include_path($_SERVER['DOCUMENT_ROOT']);
require_once('include/consts.php');
require_once('include/sendMail.php');
require_once('vendor/autoload.php');
use RobThree\Auth\Providers\Qr\EndroidQrCodeProvider;
use RobThree\Auth\TwoFactorAuth;

if (session_status() !== PHP_SESSION_ACTIVE)
{
    session_start();
}
$qrProvider = new EndroidQrCodeProvider();
$tfa = new TwoFactorAuth($qrProvider);

$tr = load_tr($lang, 'home');
$title = tr($tr, 'title');

$log = '';
if ((isset($_GET['token']) && $_GET['token'] === $login['token']) || (isset($_POST['token']) && $_POST['token'] === $login['token']))
{
    if (isset($_GET['sendmail']))
    {
        require_once('include/sendconfirm.php');
        send_confirm($login['id'], $login['email'], $settings['mhash'], $login['username']);
    }
    if (isset($_GET['settings']) && isset($_POST['username']) && isset($_POST['mail']))
    {
        $ok = true;
        $username = $_POST['username'];
        if (strlen((string) $username) < 3 || strlen((string) $username) > 32)
        {
            $ok = false;
            $log .= '<li>'.tr($tr, 'err_name_length').'</li>';
        }
        if (strlen($_POST['mail']) > 255 || empty($_POST['mail']))
        {
            $ok = false;
            $log .= '<li>'.tr($tr, 'err_mail_length').'</li>';
        }
        $SQL = <<<SQL
            SELECT username,email FROM accounts WHERE (username=:username OR email=:email) AND id!=:id LIMIT 1
            SQL;
        $req = $bdd->prepare($SQL);
        $req->execute([':username' => $username, ':email' => $_POST['mail'], ':id' => $login['id']]);
        if ($data = $req->fetch())
        {
            if ($data['username'] === $username)
            {
                $ok = false;
                $log .= '<li>'.tr($tr, 'err_name_used').'</li>';
            }
            if ($data['email'] === $_POST['mail'])
            {
                $ok = false;
                $log .= '<li>'.tr($tr, 'err_mail_used').'</li>';
            }
        }
        $bd_m = 0;
        $bd_d = 0;
        if (isset($_POST['bd_m']) && isset($_POST['bd_d']) && preg_match('/^\d\d?$/', $_POST['bd_m']) && preg_match('/^\d\d?$/', $_POST['bd_d']))
        {
            $bd_m = intval($_POST['bd_m']);
            $bd_d = intval($_POST['bd_d']);
        }
        $comments_sub = intval(isset($_POST['comments_sub']) && $_POST['comments_sub'] === 'on');
        $notif_mail = isset($_POST['notif_mail']) && $_POST['notif_mail'] === 'on';

        if ($ok)
        {
            $settings['bd_m'] = $bd_m;
            $settings['bd_d'] = $bd_d;
            $settings['notif_mail'] = $notif_mail;
            if ($_POST['mail'] !== $login['email'])
            {
                $settings['mhash'] = hash('sha512', strval(time() + random_int(100000, 9999999)).$login['password'].strval(random_int(100000, 9999999)));
                $SQL = <<<SQL
                    UPDATE accounts SET username=:username, email=:email, confirmed=false, settings=:set, subscribed_comments=:sub WHERE id=:id
                    SQL;
                $req = $bdd->prepare($SQL);
                $req->execute([':username' => $username, ':email' => $_POST['mail'], ':set' => json_encode($settings), ':sub' => $comments_sub, ':id' => $login['id']]);
                header('Location: /home.php?settings_ok&mail_sent');
                require_once('include/sendconfirm.php');
                send_confirm($login['id'], $_POST['mail'], $settings['mhash'], $username);
                exit();
            }
            else
            {
                $SQL = <<<SQL
                    UPDATE accounts SET username=:username, email=:email, settings=:set, subscribed_comments=:sub WHERE id=:id
                    SQL;
                $req = $bdd->prepare($SQL);
                $req->execute([':username' => $username, ':email' => $_POST['mail'], ':set' => json_encode($settings), ':sub' => $comments_sub, ':id' => $login['id']]);
                header('Location: /home.php?settings_ok');
                exit();
            }
            exit();
        }
    }
    if (isset($_GET['chpsw']) && isset($_POST['oldpsw']) && isset($_POST['newpsw']) && isset($_POST['newrpsw']))
    {
        $ok = true;
        if ($_POST['newpsw'] !== $_POST['newrpsw'])
        {
            $ok = false;
            $log .= '<li>'.tr($tr, 'err_psw_same').'</li>';
        }
        if (strlen($_POST['newpsw']) < 8 || strlen($_POST['newpsw']) > 64)
        {
            $ok = false;
            $log .= '<li>'.tr($tr, 'err_psw_length').'</li>';
        }
        if (!password_verify((string) $_POST['oldpsw'], (string) $login['password']))
        {
            $ok = false;
            $log .= '<li>'.tr($tr, 'err_psw_old').'</li>';
        }

        if ($ok)
        {
            $SQL = <<<SQL
                UPDATE accounts SET password=:psw WHERE id=:id
                SQL;
            $req = $bdd->prepare($SQL);
            $req->execute([':psw' => password_hash($_POST['newpsw'], PASSWORD_DEFAULT), ':id' => $login['id']]);
            header('Location: /home.php?psw_ok');
            exit();
        }
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove_account' && isset($_POST['psw'], $_POST['msgrm']))
    {
        $ok = true;
        if (strlen($_POST['msgrm']) > 8192)
        {
            $ok = false;
            $log .= '<li>'.tr($tr, 'err_rmmsg_length').'</li>';
        }
        if (!password_verify((string) $_POST['psw'], (string) $login['password']))
        {
            $ok = false;
            $log .= '<li>'.tr($tr, 'err_psw_bad').'</li>';
        }

        if ($ok)
        {
            $SQL = <<<SQL
                DELETE FROM accounts WHERE id=:id
                SQL;
            $req = $bdd->prepare($SQL);
            $req->execute([':id' => $login['id']]);
            $subject = 'Suppression de compte';
            $msgH = strip_tags((string) $_POST['msgrm']);
            $body = <<<HTML
                <h2>{$login['username']} a quitté le navire</h2>
                <p>Bonjour,<br>
                ce message vient informer que {$login['username']} a supprimé son compte {$site_name}.</p>
                <h3>Motif du départ</h3>
                <p>{$msgH}</p>
                HTML;
            $altBody = <<<TEXT
                {$login['username']} a quitté le navire
                Bonjour,
                ce message vient informer que {$login['username']} a supprimé son compte {$site_name}.
                Motif du départ
                {$_POST['msgrm']}
                TEXT;
            sendMail(getTeamEmails('manage_members'), $subject, $body, $altBody);
            header('Location: /login.php?goodbye');
            exit();
        }
    }
    if (isset($_GET['notifs_all_read']))
    {
        $SQL = <<<SQL
            UPDATE notifs SET unread=false WHERE account=:acc AND unread=true
            SQL;
        $req = $bdd->prepare($SQL);
        $req->execute([':acc' => $login['id']], ['token' => $login['token']]);
        $rep['notifs_all_read'] = true;
    }
    if (isset($_GET['notif_read']))
    {
        $SQL = <<<SQL
            UPDATE notifs SET unread=false WHERE id=:id AND account=:acc
            SQL;
        $req = $bdd->prepare($SQL);
        $req->execute([':id' => $_GET['notif_read'], ':acc' => $login['id']]);
    }
    if (isset($_GET['notif_unread']))
    {
        $SQL = <<<SQL
            UPDATE notifs SET unread=true WHERE id=:id AND account=:acc
            SQL;
        $req = $bdd->prepare($SQL);
        $req->execute([':id' => $_GET['notif_unread'], ':acc' => $login['id']]);
    }
    if (isset($_GET['rm_ses']))
    {
        $SQL = <<<SQL
            UPDATE sessions SET expire=:exp WHERE account=:acc AND id=:id AND expire>:exp2
            SQL;
        $req = $bdd->prepare($SQL);
        $req->execute([':exp' => time() - 1, ':acc' => $login['id'], ':id' => $_GET['rm_ses'], ':exp2' => time()]);
    }
    if (isset($_GET['enable2fa']) && isset($_POST['code']) && isset($_SESSION['2fa_secret']))
    {
        $ok = false;
        if ($tfa->verifyCode($_SESSION['2fa_secret'], $_POST['code']))
        {
            $SQL = <<<SQL
                UPDATE accounts SET twofa_enabled=true, twofa_secret=:secret WHERE id=:id
                SQL;
            $req = $bdd->prepare($SQL);
            $req->execute([':secret' => $_SESSION['2fa_secret'], ':id' => $login['id']]);
            unset($_SESSION['2fa_secret']);
            header('Location: /home.php?2fa_enabled');
            exit();
        }
        else
        {
            $log .= '<li>'.tr($tr, 'err_2fa_code_invalid').'</li>';
        }
    }
    if (isset($_GET['disable2fa']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_disable']))
    {
        if (!password_verify((string) $_POST['psw'], (string) $login['password']))
        {
            $log .= '<li>'.tr($tr, 'err_psw_bad').'</li>';
        }
        else
        {
            $SQL = <<<SQL
                UPDATE accounts SET twofa_enabled=false, twofa_secret=NULL WHERE id=:id
                SQL;
            $req = $bdd->prepare($SQL);
            $req->execute([':id' => $login['id']]);
            header('Location: /home.php?2fa_disabled');
            exit();
        }
    }
}
require_once('include/user_rank.php');
if (isset($_GET['settings_ok']))
{
    $log .= '<li>'.tr($tr, 'log_settings_ok').'</li>';
}
if (isset($_GET['psw_ok']))
{
    $log .= '<li>'.tr($tr, 'log_psw_ok').'</li>';
}
if (isset($_GET['mail_sent']))
{
    $log .= '<li>'.tr($tr, 'log_mail_sent').'</li>';
}
if (isset($_GET['2fa_enabled']))
{
    $log .= '<li>'.tr($tr, 'log_2fa_enabled').'</li>';
}
if (isset($_GET['2fa_disabled']))
{
    $log .= '<li>'.tr($tr, 'log_2fa_disabled').'</li>';
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
<ul class="log" role="alert"><?= $log ?></ul>
</noscript>
<script>
    window.addEventListener('DOMContentLoaded', () =>
    {
        const alertZone = document.getElementById('alertZone');
        alertZone.innerHTML = '<ul class="log"><?= addslashes($log) ?></ul>';
    });
</script>
<?php endif; ?>
<?php if ($login['confirmed'] === 0)
{
    echo '<p>'.tr($tr, 'confirm_mail').'<br><a href="/home.php?sendmail&mail_sent&token='.$login['token'].'">'.tr($tr, 'send_mail').'</a></p>';
}
?>
<ul>
<li><?= tr($tr, 'profile_rank', ['rank' => urank($login['rank'])]) ?></li>
<li><?= tr($tr, 'profile_id', ['id' => ($login['team_id'] ? 'E'.$login['team_id'].'' : '') . 'M'.$login['id']]) ?></li>
<li><?= tr($tr, 'profile_signup_date', ['date' => getFormattedDate($login['signup_date'], tr($tr0, 'fndatetime'))]) ?></li>
<?php if (isset($settings['bd_m']) && isset($settings['bd_d']))
{ ?>
<li><?php $date = getdate();
    echo tr($tr, 'profile_birthday', ['date' => ((($settings['bd_m'] === $date['mon'] && $settings['bd_d'] === $date['mday']) || ($settings['bd_m'] === 2 && $date['mon'] === 3 && $settings['bd_d'] === 29 && $date['mday'] === 1 && $date['year'] % 4 === 0)) ? tr($tr, 'profile_happy_birthday').' &#127874;' : zeros($settings['bd_d'], 2).'/'.zeros($settings['bd_m'], 2))]); ?></li>
<?php } ?>
</ul>

<h3 id="notifs"><?= tr($tr, 'notifs') ?></h3>
<a href="?notifs_all_read&token=<?= $login['token'] ?>" onclick="read_all_notifs(event)"><?= tr($tr, 'notifs_read_all') ?></a>
<?php
$notifs_read = '';
$notifs_unread = '';
$SQL = <<<SQL
    SELECT * FROM notifs WHERE account=:acc ORDER BY date DESC
    SQL;
$req = $bdd->prepare($SQL);
$req->execute([':acc' => $login['id']]);
while ($notif = $req->fetch())
{
    $data = json_decode((string) $notif['data'], true);
    $notif_html = '<li id="lnotif'.$notif['id'].'" class="lnotif lnotif_'.($notif['unread'] ? '' : 'un').'read"><span class="lnotif_date">'.getFormattedDate($notif['date'], tr($tr0, 'fndatetime')).'</span> <span class="lnotif_text">';
    if (isset($data['type']))
    {
        if ($data['type'] === 'new_comment' && isset($data['article']))
        {
            $SQL2 = <<<SQL
                SELECT name FROM softwares WHERE id=:id LIMIT 1
                SQL;
            $req2 = $bdd->prepare($SQL2);
            $req2->execute([':id' => $data['article']]);
            if ($tmp = $req2->fetch())
            {
                $notif_html .= tr($tr, 'notifs_new_comment', ['link' => '<a href="/a'.$data['article'].'">'.$tmp['name'].'</a>.']);
            }
        }
    }
    $notif_html .= '</span> <a class="lnotif_readlink" href="?notif_read='.$notif['id'].'&token='.$login['token'].'" onclick="read_notif(event, '.$notif['id'].', true)" style="display:'.($notif['unread'] ? 'initial' : 'none').'">('.tr($tr, 'notifs_read').')</a><a class="lnotif_unreadlink" href="?notif_unread='.$notif['id'].'&token='.$login['token'].'" onclick="read_notif(event, '.$notif['id'].', false)" style="display:'.($notif['unread'] ? 'none' : 'initial').'">('.tr($tr, 'notifs_unread').')</a></li>';
    if ($notif['unread'])
    {
        $notifs_unread .= $notif_html;
    }
    else
    {
        $notifs_read .= $notif_html;
    }
}
echo '<ul id="notifs_unread">' . $notifs_unread . '</ul>';
echo '<details><summary>'.tr($tr, 'notifs_show_read').'</summary><ul id="notifs_read">' . $notifs_read . '</ul></details>';
?>

<h3 id="sessions"><?= tr($tr, 'sessions') ?></h3>
<ul>
<?php
$time = time();
$SQL = <<<SQL
    SELECT * FROM sessions WHERE account=:acc ORDER BY expire DESC
    SQL;
$req = $bdd->prepare($SQL);
$req->execute([':acc' => $login['id']]);
while ($data = $req->fetch())
{
    echo '<li>';
    echo tr($tr, 'session_item', ['created' => getFormattedDate($data['created'], tr($tr0, 'fndatetime')), 'expires' => getFormattedDate($data['expire'], tr($tr0, 'fndatetime'))]);
    if ($data['expire'] > $time)
    {
        echo ' (<a href="?rm_ses='.$data['id'].'&token='.$login['token'].'">'.tr($tr, 'session_item_remove').'</a>)';
    }
    if ($data['id'] === $login['session_id'])
    {
        echo ' (<strong>'.tr($tr, 'session_item_current').'</strong>)';
    }
    echo '</li>';
}
?>
</ul>

<h3 id="settings"><?= tr($tr, 'settings') ?></h3>
<form action="?settings" method="post">
<input type="hidden" name="token" value="<?= $login['token'] ?>">
<fieldset><legend><?= tr($tr, 'settings_account') ?></legend>
<table>
<tr><td class="formlabel"><label for="f1_username"><?= tr($tr, 'settings_name') ?></label></td>
<td><input type="text" id="f1_username" name="username" value="<?= htmlentities((string) $login['username']) ?>" maxlength="32" required></td></tr>
<tr><td class="formlabel"><label for="f1_mail"><?= tr($tr, 'settings_mail') ?></label></td>
<td><input type="email" id="f1_mail" name="mail" value="<?= htmlentities((string) $login['email']) ?>" maxlength="255" required></td></tr>
<?php /*<tr><td class="formlabel"><label for="f1_notifcom"><?= tr($tr,'settings_notifcom') ?></label></td>
                    <td><input type="checkbox" id="f1_notifcom" name="notifcom" autocomplete="off"<?php if(isset($settings['notifcom']) and $settings['notifcom']==1)echo ' checked'; ?>></td></tr>*/ ?>
<tr><td class="formlabel"><?= tr($tr, 'settings_birthday') ?></td>
<td><label for="f1_bd_m"><?= tr($tr, 'settings_birthday_month') ?></label> <input type="number" id="f1_bd_m" name="bd_m" value="<?= $settings['bd_m'] ?? '0' ?>" min="0" max="12" size="4">
<label for="f1_bd_d"><?= tr($tr, 'settings_birthday_day') ?></label> <input type="number" id="f1_bd_d" name="bd_d" value="<?= $settings['bd_d'] ?? '0' ?>" min="0" max="31" size="4"></td></tr>
<tr><td class="formlabel"><label for="f1_comments_sub"><?= tr($tr, 'settings_comments_sub') ?></label></td>
<td><input type="checkbox" id="f1_comments_sub" name="comments_sub"<?= $login['subscribed_comments'] ? ' checked' : '' ?>></td></tr>
<tr><td class="formlabel"><label for="f1_notif_mail"><?= tr($tr, 'settings_notif_mail') ?></label></td>
<td><input type="checkbox" id="f1_notif_mail" name="notif_mail"<?= $settings['notif_mail'] ? ' checked' : '' ?>></td></tr>
<tr><td></td>
<td><input type="submit" value="<?= tr($tr, 'settings_submit') ?>"></td></tr>
</table>
</fieldset>
</form>
<form action="?chpsw" method="post">
<input type="hidden" name="token" value="<?= $login['token'] ?>">
<fieldset><legend><?= tr($tr, 'chpsw') ?></legend>
<table>
<tr><td class="formlabel"><label for="f2_oldpsw"><?= tr($tr, 'chpsw_old') ?></label></td>
<td><input type="password" id="f2_oldpsw" name="oldpsw" maxlength="64" required></td></tr>
<tr><td class="formlabel"><label for="f2_newpsw"><?= tr($tr, 'chpsw_new') ?></label></td>
<td><input type="password" id="f2_newpsw" name="newpsw" maxlength="64" required></td></tr>
<tr><td class="formlabel"><label for="f2_newrpsw"><?= tr($tr, 'chpsw_new_re') ?></label></td>
<td><input type="password" id="f2_newrpsw" name="newrpsw" maxlength="64" required></td></tr>
<tr><td></td>
<td><input type="submit" value="<?= tr($tr, 'chpsw_submit') ?>"></td></tr>
</table>
</fieldset>
</form>
<?php if (!$login['twofa_enabled']) :
    if (!isset($_SESSION['2fa_secret']))
    {
        $_SESSION['2fa_secret'] = $tfa->createSecret();
    }
    $secret = $_SESSION['2fa_secret'];
    $qr = $tfa->getQRCodeImageAsDataUri(SITE_NAME.'('.$login['username'].')', $secret);
    ?>
<h3><?= tr($tr, 'enable_2fa_title') ?></h3>
<p><?= tr($tr, 'enable_2fa_text') ?></p>
<img src="<?= $qr ?>" alt="QR Code TOTP">
<p><strong><?= tr($tr, 'manual_code') ?> :</strong> <code><?= htmlentities((string) $secret) ?></code></p>
<p><?= tr($tr, 'manual_code_hint') ?></p>
<form method="post" action="?enable2fa">
<input type="hidden" name="token" value="<?= $login['token'] ?>">
<label for="f2fa_code"><?= tr($tr, 'enter_2fa_code') ?></label>
<input type="text" name="code" id="f2fa_code" maxlength="6" required>
<input type="submit" value="<?= tr($tr, 'confirm') ?>">
</form>
<?php else: ?>
<h3><?= tr($tr, 'disable_2fa_title') ?></h3>
<p><?= tr($tr, 'disable_2fa_text') ?></p>
<form method="post" action="?disable2fa">
<input type="hidden" name="token" value="<?= $login['token'] ?>">
<label for="f2fa_psw"><?= tr($tr, 'disable_2fa_psw') ?></label>
<input type="password" name="psw" id="f2fa_psw" required maxlength="64">
<input type="submit" name="confirm_disable" value="<?= tr($tr, 'disable_2fa_confirm') ?>">
</form>
<?php endif; ?>
<form method="post">
<input type="hidden" name="token" value="<?= $login['token'] ?>">
<input type="hidden" name="action" value="remove_account">
<fieldset><legend><?= tr($tr, 'remove_account') ?></legend>
<table>
<tr><td colspan="2"><?= tr($tr, 'remove_account_warn') ?></td></tr>
<tr><td class="formlabel"><label for="f3_msg"><?= tr($tr, 'remove_account_msg') ?></label></td>
<td><textarea id="f3_msg" style="width:100%;" name="msgrm" maxlength="8192"><?php if (isset($_POST['msgrm']) && strlen((string) $_POST['msgrm']) <= 8192)
{
    echo htmlentities((string) $_POST['msgrm']);
} ?></textarea></td></tr>
<tr><td class="formlabel"><label for="f3_psw"><?= tr($tr, 'remove_account_psw') ?></label></td>
<td><input type="password" id="f3_psw" name="psw" maxlength="64" required></td></tr>
<tr><td></td>
<td><input type="submit" value="<?= tr($tr, 'remove_account_submit') ?>"></td></tr>
</table>
</fieldset>
</form>
</main>
<?php require_once('include/footer.php'); ?>

<script type="text/javascript" src="/scripts/jquery.js"></script>
<script type="text/javascript" src="/scripts/pa_api.js"></script>
<script type="text/javascript">
var api_session = new API_Session("/api/");
api_session.session = <?= json_encode($_COOKIE['session']) ?>;
api_session.connectid = <?= json_encode($login['connectid']) ?>;
api_session.token = <?= json_encode($login['token']) ?>;

function read_notif(e, notif, mod) {
if(mod) {
api_read_notif(api_session, notif, function(data) {
if(data["read_notif"] == notif) {
$("#lnotif"+notif).attr("class", "lnotif lnotif_read");
$("#lnotif"+notif+" .lnotif_readlink").attr("style", "display: none;");
$("#lnotif"+notif+" .lnotif_unreadlink").attr("style", "display: initial;");
$("#lnotif"+notif).detach().appendTo("#notifs_read");
}
});
}
else {
api_unread_notif(api_session, notif, function(data) {
if(data["unread_notif"] == notif) {
$("#lnotif"+notif).attr("class", "lnotif lnotif_unread");
$("#lnotif"+notif+" .lnotif_unreadlink").attr("style", "display: none;");
$("#lnotif"+notif+" .lnotif_readlink").attr("style", "display: initial;");
$("#lnotif"+notif).detach().appendTo("#notifs_unread");
}
});
}
e.preventDefault();
}

function read_all_notifs(e) {
api_read_all_notifs(api_session, function(data) {
$(".lnotif_unread").each(function(i) {
$(this).attr("class", "lnotif lnotif_read");
$(this).children(".lnotif_readlink").attr("style", "display: none;");
$(this).children(".lnotif_unreadlink").attr("style", "display: initial;");
$(this).detach().appendTo("#notifs_read");
});
});
e.preventDefault();
}
</script>
</body>
</html>
