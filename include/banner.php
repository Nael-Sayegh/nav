<header id="hautpage">
<h1><a href="/" title="<?= tr($tr0, 'banner_homelink') ?>"><?php print $site_name; ?></a></h1>
<?php
if (isset($_SERVER['HTTP_USER_AGENT']) && str_contains((string) $_SERVER['HTTP_USER_AGENT'], 'Trident'))
{
    include __DIR__ . '/include/trident.php';
}
//include 'include/loginbox.php';
include __DIR__ . '/include/searchtool.php'; ?>
</header>
<?php include __DIR__ . '/include/menu.php'; ?>
