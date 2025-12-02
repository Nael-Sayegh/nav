<?php

declare(strict_types=1);

$document_root = __DIR__.'/..';
require_once($document_root.'/include/consts.php');
$SQL = <<<SQL
    SELECT * FROM softwares_files WHERE date>=:date ORDER BY date DESC
    SQL;
$req = $bdd->prepare($SQL);
$req->execute([':date' => time() - 86400]);# modifiés aujourd'hui
$files = '';
while ($data = $req->fetch())
{
    $files .= "\n".$data['title'].' '.SITE_URL.'/dl/';
    if (!empty($data['label']))
    {
        $files .= $data['label'];
    }
    else
    {
        $files .= $data['id'];
    }
}

$SQL = <<<SQL
    SELECT * FROM softwares_mirrors WHERE date>=:date ORDER BY date DESC
    SQL;
$req = $bdd->prepare($SQL);
$req->execute([':date' => time() - 86400]);# modifiés aujourd'hui
while ($data = $req->fetch())
{
    $files .= "\n".$data['title'].' '.SITE_URL.'/r?m&';
    if (!empty($data['label']))
    {
        $files .= 'p='.$data['label'];
    }
    else
    {
        $files .= 'i='.$data['id'];
    }
}

if ($files !== '' && $files !== '0')
{
    $message = 'Mises à jour d\'aujourd\'hui :'.$files;
    if (isset($debug))
    {
        echo $message;
    }
    else
    {
        require_once($document_root.'/include/lib/facebook/fb_publisher.php');
        send_facebook($message);
    }
}
