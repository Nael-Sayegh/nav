<?php set_include_path($_SERVER['DOCUMENT_ROOT']);
require_once(__DIR__ . '/include/log.php');
require_once(__DIR__ . '/include/consts.php');
$tr = load_tr($lang, 'sms_notify');
$title = tr($tr, 'title');
$stats_page = 'sms_notify';
$submitted = isset($_POST['phone']); ?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<?php require_once(__DIR__ . '/include/header.php'); ?>
<body>
<?php require_once(__DIR__ . '/include/banner.php'); ?>
<main id="container">
<h1 id="contenu"><?php print $title; ?></h1>
<?php if ($submitted): ?>
<p role="alert"><?= tr($tr, 'confirm') ?></p>
<?php else: ?>
<p><?= tr($tr, 'intro') ?></p>
<form action="" method="post">
<label for="f_phone"><?= tr($tr, 'label_phone') ?>&nbsp;:</label>
<input type="tel" name="phone" id="f_phone" maxlength="20" required>
<input type="submit" value="<?= tr($tr, 'submit') ?>">
</form>
<?php endif; ?>
</main>
<?php require_once(__DIR__ . '/include/footer.php'); ?>
</body>
</html>
