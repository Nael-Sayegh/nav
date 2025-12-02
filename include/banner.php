<header id="hautpage">
<h1><a href="/" title="<?= tr($tr0, 'banner_homelink') ?>"><?php print $site_name; ?></a></h1>
<?php
if (isset($_SERVER['HTTP_USER_AGENT']) && str_contains((string) $_SERVER['HTTP_USER_AGENT'], 'Trident'))
{
    require_once __DIR__ . '/trident.php';
}
//include 'include/loginbox.php';
require_once __DIR__ . '/searchtool.php'; ?>
</header>
<?php require_once __DIR__ . '/menu.php'; ?>
