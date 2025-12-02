<?php

declare(strict_types=1);

$params = '';
if (isset($_GET['id']) && preg_match('/\d+/', (string) $_GET['id']))
{
    $params .= 'id='.$_GET['id'];
}

if ($params !== '' && $params !== '0')
{
    header('Location: /article.php?'.$params);
    exit();
}
