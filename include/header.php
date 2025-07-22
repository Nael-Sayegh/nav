<head>
<meta charset="utf-8">
<title><?= $title.' – '.$site_name ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
<link title="<?= $site_name ?>" type="application/opensearchdescription+xml" rel="search" href="/opensearch.xml.php">
<?php print $css_path; ?>
<style>body {font-size: <?php if (isset($_COOKIE['fontsize']) && preg_match('#[0-9]{1,2}#', (string) $_COOKIE['fontsize']))
{
    echo $_COOKIE['fontsize'];
}
else
{
    echo '20';
} ?>px;}</style>
<script src="/scripts/default.js"></script>
<meta property="og:title" content="<?php print $title; ?>">
<link rel="stylesheet" href="/css/mastodonShare.css">
<script src="/scripts/mastodonShare.js.php"></script>
<?php if ($_SERVER['SCRIPT_NAME'] === '/contact_form.php' || $_SERVER['SCRIPT_NAME'] === '/signup.php')
{ ?>
<script>
var mtcaptchaConfig = {
  "sitekey": "<?php print MTCAPTCHA_PUBLIC; ?>",
  "lang": "<?php print $lang; ?>"
  };
  (function(){var mt_service = document.createElement('script');mt_service.async = true;mt_service.src = 'https://service.mtcaptcha.com/mtcv1/client/mtcaptcha.min.js';(document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(mt_service);
  var mt_service2 = document.createElement('script');mt_service2.async = true;mt_service2.src = 'https://service2.mtcaptcha.com/mtcv1/client/mtcaptcha2.min.js';(document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(mt_service2);}) ();
</script>
<?php } ?>
</head>
