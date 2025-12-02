<?php

declare(strict_types=1);

$pages = ['/', '/newsletter.php', '/rss_feed.xml', '/history.php', '/settings.php', '/gadgets.php', '/contact.php', '/contact_form.php', '/privacy.php'];
if (isset($_GET['d']) && !empty($_GET['d']) && (in_array($_GET['d'], $pages, true) || preg_match('#^/c\d{1,3}$#', (string) $_GET['d'])))
{
    header('Location: '.$_GET['d']);
    exit();
}

header('Location: /');
exit();
