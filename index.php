<?php
set_include_path($_SERVER['DOCUMENT_ROOT']);
$stats_page = 'index';
require_once(__DIR__ . '/include/log.php');
require_once(__DIR__ . '/include/consts.php');
$tr = load_tr($lang, 'index');
$title = tr($tr, 'title'); ?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<?php require_once(__DIR__ . '/include/header.php'); ?>
<body>
<?php require_once(__DIR__ . '/include/banner.php'); ?>
<div id="container">
<main id="contenu">
<?php if (isset($_GET['contactconfirm']) && $_GET['contactconfirm'])
{
    echo '<p role="alert" id="contactconfirm">'.tr($tr, 'mailconfirmtext').'</p>';
} ?>
<h2 style="margin:0;"><?= tr($tr, 'texttitle') ?></h2>
<?php if (date('m') === '01')
{
    echo str_replace('{{year}}', date('Y'), tr($tr, 'happynewyear'));
} ?>
<?= tr($tr, 'maintext', ['lastosv' => $lastosv]) ?>
</main>
</div>
<script src="/scripts/jquery.js"></script>
<?php require_once(__DIR__ . '/include/footer.php'); ?>
</body>
</html>
