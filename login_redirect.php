<?php $logonly = true;
set_include_path($_SERVER['DOCUMENT_ROOT']);
require_once(__DIR__ . '/include/log.php');
require_once(__DIR__ . '/include/consts.php');
$tr = load_tr($lang, 'redirlogin');
$title = tr($tr, 'title');
$stats_page = 'redirlogin';
if (session_status() !== PHP_SESSION_ACTIVE)
{
    session_start();
}
$back = $_SESSION['after_login_to'] ?? '/';
unset($_SESSION['after_login_to']);
if (!str_starts_with((string) $back, '/'))
{
    $back = '/';
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<?php require_once(__DIR__ . '/include/header.php'); ?>
<body>
<?php require_once(__DIR__ . '/include/banner.php'); ?>
<main id="container">
<h1 id="contenu"><?php print $title; ?></h1>
<?= str_replace('{{membername}}', $login['username'], tr($tr, 'maintext')) ?>
<ul>
<li><a href="<?= htmlspecialchars((string) $back, ENT_QUOTES) ?>"><?= tr($tr, 'previous_page') ?></a></li>
<?php if (isset($login['rank']) && $login['rank'] === 'a')
{
    if (in_array($login['works'], ['1', '2'], true))
    { ?>
<li><a href="/admin"><?= tr($tr, 'adminlink').' ('.$site_name.')' ?></a></li>
<?php }
    if (in_array($login['works'], ['0', '2'], true))
    { ?>
<li><a href="https://www.nael-accessvision.com/admin?cid=<?php print $_COOKIE['connectid']; ?>&ses=<?php print $_COOKIE['session']; ?>"><?= tr($tr, 'adminlink').' (Nael-Accessvision)' ?></a></li>
<?php }
    } ?>
<li><a href="/"><?= tr($tr, 'homelink') ?></a></li>
<li><a href="/home.php"><?= tr($tr, 'memberlink') ?></a></li>
<?php if (checkMemberRights('view_members') || $login['rank'] === 'a')
{ ?>
<li><a href="/members_list.php"><?= tr($tr, 'memberlistlink') ?></a></li>
<?php } ?>
<li><a href="/logout.php?token=<?= $login['token'] ?>"><?= tr($tr, 'logoutlink') ?></a></li>
</ul>
</main>
<?php require_once(__DIR__ . '/include/footer.php'); ?>
</body>
</html>
