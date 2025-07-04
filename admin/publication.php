<?php $logonly = true;
$adminonly = true;
$justna = true;
$titlePAdm = 'Publier sur les réseaux sociaux';
require_once($_SERVER['DOCUMENT_ROOT'].'/include/log.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/include/consts.php');
requireAdminRight('manage_publications');
$log = '';

// Mastodon: link = 23
// Split a message into submessages under a given maximum length.
// In case splitting is needed, submessages are numbered:
// " xx/yy" (6 chars) is written at the end.
// The function tries to split at spaces.
function split_msg(string $msg, int $minlen, int $maxlen)
{
    $len = strlen((string) $msg);
    if ($len <= $maxlen)
    {
        return [$msg];
    }
    $msgs = [];
    $from = 0;
    $maxlen -= 6;// Account for numbering

    while (true)
    {
        $to = $from + $maxlen;
        if ($to >= $len)
        {
            // Last submessage
            $msgs[] = substr((string) $msg, $from);
            break;
        }
        $removed = 1;
        while ($msg[$to - 1] != ' ' && $msg[$to - 1] != '\n')
        {
            if ($to - $from <= $minlen)
            {
                $to = $from + $maxlen;
                $removed = 0;
                break;
            }
            $to--;
        }
        $msgs[] = substr((string) $msg, $from, $to - $from - $removed);
        $from = $to;
    }

    for ($i = 0; $i < count($msgs); $i++)
    {
        $msgs[$i] .= "\n" . ($i + 1) . '/' . count($msgs);
    }

    return $msgs;
}

if (isset($_GET['form']) && isset($_POST['pf']) && isset($_POST['msg']))
{
    $plainmsg = $_POST['msg'];

    if (in_array('facebook', $_POST['pf']))
    {
        require_once($_SERVER['DOCUMENT_ROOT'].'/include/lib/facebook/fb_publisher.php');
        send_facebook($plainmsg);
        $log .= '<li>Message Facebook envoyé.</li>';
    }
    if (in_array('discord', $_POST['pf']))
    {
        require_once($_SERVER['DOCUMENT_ROOT'].'/include/lib/discord_publisher.php');
        send_discord($plainmsg);
        $log .= '<li>Message Discord envoyé.</li>';
    }
    if (in_array('mastodon', $_POST['pf']))
    {
        require_once($_SERVER['DOCUMENT_ROOT'].'/include/lib/Mastodon/mastodon_publisher.php');
        $msgs = split_msg($plainmsg, 100, MASTODON_MAX_LEN);
        foreach ($msgs as $submsg)
        {
            send_mastodon($submsg);
        }
        $log .= '<li>Message Mastodon envoyé en '.count($msgs).' morceaux.</li>';
    }
}

/*if(isset($_GET['form']) and isset($_POST['platform']) and isset($_POST['msg']) and strlen($_POST['msg']) <= '280') {
    if($_POST['platform'] == '1' or $_POST['platform'] == '2' or $_POST['platform'] == '5' or $_POST['platform'] == '6') {
        require_once($_SERVER['DOCUMENT_ROOT'].'/include/lib/facebook/fb_publisher.php');
        send_facebook($_POST['msg']);
        $log .= 'Publication postée ! ';
    }
    if($_POST['platform'] == '1' or $_POST['platform'] == '4' or $_POST['platform'] == '6' or $_POST['platform'] == '7') {
        require_once('Discord/DiscordBot.php');
        $log .= 'Discord envoyé ! ';
    }
}*/

if (isset($_GET['nl']))
{
    $message = 'La lettre d\'infos du '.$datejour.' est envoyée à '.date('H:i').'!'."\n\n".$admin_name;
    if ($_POST['nl'] === 'fb' || $_POST['nl'] === 'all')
    {
        require_once($_SERVER['DOCUMENT_ROOT'].'/include/lib/facebook/fb_publisher.php');
        send_facebook($message);
        $log .= '<li>Publication lettre d\'infos postée</li>';
    }
}
if (isset($_GET['swfb']))
{
    if (isset($_GET['debug']))
    {
        $debug = true;
    }
    header('Content-type: text/plain');
    require_once($_SERVER['DOCUMENT_ROOT'].'/tasks/facebook_publisher.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Publication sur les réseaux - <?php print $site_name; ?></title>
<?php print $admin_css_path; ?>
<script type="text/javascript" src="/scripts/default.js"></script>
</head>
<body>
<?php require_once('include/banner.php'); ?>
<div id="alertZone" role="alert" aria-live="assertive"></div>
<?php if (!empty($log)): ?>
<noscript>
<ul role="alert"><?= $log ?></ul>
</noscript>
<script>
    window.addEventListener('DOMContentLoaded', () =>
    {
        const alertZone = document.getElementById('alertZone');
        alertZone.innerHTML = '<ul><?= addslashes($log) ?></ul>';
    });
</script>
<?php endif; ?>
<form action="?form" method="post">
<label for="f_platform">Publier&nbsp;:</label>
<ul id="f_platform">
<li><input id="f_platform_facebook" type="checkbox" name="pf[]" value="facebook" checked> <label for="f_platform_facebook">Facebook</label></li>
<li><input id="f_platform_discord" type="checkbox" name="pf[]" value="discord" checked> <label for="f_platform_discord">Discord</label></li>
<li><input id="f_platform_mastodon" type="checkbox" name="pf[]" value="mastodon" checked> <label for="f_platform_mastodon">Mastodon</label></li>
</ul>

<label for="f_msg">Message&nbsp;:</label><br>
<textarea id="f_msg" name="msg" autocomplete="off" rows="20" style="width: 100%;" required></textarea><br>
<input type="submit" value="Publier">
</form>
<!--<a href="?swfb">Publier le message Facebook des logiciels mis à jour.</a><br>
<a href="?swfb&debug">Debug message Facebook des logiciels mis à jour.</a>-->
</body>
</html>
