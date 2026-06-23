<?php

declare(strict_types=1);

$document_root = __DIR__.'/../..';
require_once $document_root.'/include/config.local.php';
require_once $document_root.'/include/consts.php';
function send_discord($message): void
{
    global $site_name;
    if (!isDev() && (defined('DISCORD_WEBHOOK_URL') && constant('DISCORD_WEBHOOK_URL')))
    {
        $timestamp = date('c', strtotime('now'));
        $msg = json_encode(['content' => $message, 'username' => $site_name], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $ch = curl_init(DISCORD_WEBHOOK_URL);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-type: application/json']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $msg);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
    }
}
