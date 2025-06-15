<?php
set_include_path($_SERVER['DOCUMENT_ROOT']);
require_once('include/log.php');
require_once('include/consts.php');
$tr = load_tr($lang, 'settings');
if (isset($_GET['act']) && $_GET['act'] === 'form')
{
    $menu = '0';
    if (isset($_POST['menu']))
    {
        $menu = '1';
    }
    setcookie('menu', $menu, ['expires' => time() + 31536000, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'strict']);

    $fontsize = '16';
    if (isset($_POST['fontsize']) && in_array($_POST['fontsize'], ['11','16','20','24']))
    {
        $fontsize = $_POST['fontsize'];
    }
    setcookie('fontsize', (string) $fontsize, ['expires' => time() + 31536000, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'strict']);

    $infosdef = '0';
    if (isset($_POST['infosdef']))
    {
        $infosdef = '1';
    }
    setcookie('infosdef', $infosdef, ['expires' => time() + 31536000, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'strict']);


    if ($logged && isset($_POST['token']) && $_POST['token'] === $login['token'])
    {
        $settings = json_decode((string) $login['settings'], true);
        $settings['menu'] = $menu;
        $settings['fontsize'] = $fontsize;
        $settings['infosdef'] = $infosdef;
        $SQL = <<<SQL
            UPDATE accounts SET settings=:set WHERE id=:id
            SQL;
        $req = $bdd2->prepare($SQL);
        $req->execute([':set' => json_encode($settings), ':id' => $login['id']]);
    }

    header('Location: /');
    exit();
}
elseif (isset($_GET['act']) && $_GET['act'] === '0')
{
    if ($logged && isset($_POST['token']) && $_POST['token'] === $login['token'])
    {
        $settings = json_decode((string) $login['settings'], true);
        $settings['menu'] = '0';
        $settings['fontsize'] = '16';
        $settings['infosdef'] = '1';
        $SQL = <<<SQL
            UPDATE accounts SET settings=:set WHERE id=:id
            SQL;
        $req = $bdd2->prepare($SQL);
        $req->execute([':set' => json_encode($settings), ':id' => $login['id']]);
    }
    else
    {
        setcookie('menu', '', ['expires' => 0, 'secure' => 0]);
        setcookie('fontsize', '', ['expires' => 0, 'secure' => 0]);
        setcookie('infosdef', '', ['expires' => 0, 'secure' => 0]);
    }
    header('Location: /');
    exit();
}
$stats_page = 'parametres';
$title = tr($tr, 'title'); ?>
<!DOCTYPE html>
<html lang="fr">
<?php require_once('include/header.php'); ?>
<body>
<?php require_once('include/banner.php'); ?>
<main id="container">
<h1 id="contenu"><?php print $title; ?></h1>
<?= tr($tr, 'maintext') ?>
<form action="?act=form" method="post" aria-label="Options">
<?php
if ($logged)
{
    echo '<input type="hidden" name="token" value="'.$login['token'].'">';
}
$menu = $_COOKIE['menu'] ?? '0';
$fontsize = $_COOKIE['font_size'] ?? '16';
$infosdef = $_COOKIE['infosdef'] ?? '1';
?>
<h3><?= tr($tr, 'gui') ?></h3>
<label for="menu_choice"><?= tr($tr, 'combomenu') ?></label>
<input type="checkbox" id="menu_choice" name="menu"<?php if ($menu === '1')
{
    echo ' checked="checked"';
} ?>><br>
<label for="f_fontsize"><?= tr($tr, 'textsize') ?></label>
<select id="f_fontsize" name="fontsize">
<option value="11" style="font-size: 11px;" <?php if ($fontsize === '11')
{
    echo'selected';
}?>><?= tr($tr, '11') ?></option><option value="16" style="font-size: 16px;" <?php if ($fontsize === '16')
{
    echo'selected';
}?>><?= tr($tr, '16') ?></option><option value="20" style="font-size: 20px;" <?php if ($fontsize === '20')
{
    echo'selected';
}?>><?= tr($tr, '20') ?></option><option value="24" style="font-size: 24px;" <?php if ($fontsize === '24')
{
    echo'selected';
}?>><?= tr($tr, '24') ?></option></select><br>
<label for="f_slideridcc"><?= tr($tr, 'slider') ?></label>
<input type="checkbox" id="f_slideridcc" name="infosdef" <?php if ($infosdef === '1')
{
    echo 'checked="checked"';
} ?>><br>
<input type="submit" value="<?= tr($tr, 'savebtn') ?>">
</form>
<form action="?act=0" method="post" aria-label="Réinitialiser">
<?php
if ($logged)
{
    echo '<input type="hidden" name="token" value="'.$login['token'].'">';
}
?>
<input type="submit" value="<?= tr($tr, 'resetbtn') ?>">
</form>
</main>
<?php require_once('include/footer.php'); ?>
</body>
</html>
