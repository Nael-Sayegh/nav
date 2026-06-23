<?php

declare(strict_types=1);

$document_root = __DIR__.'/../../..';
require_once($document_root.'/include/config.local.php');
require_once($document_root.'/vendor/autoload.php');

function send_mastodon($message): void
{
    if (!isDev() && (defined('MASTODON_TOKEN') && constant('MASTODON_TOKEN')) && (defined('MASTODON_URL') && constant('MASTODON_URL')) && (defined('MASTODON_VISIBILITY') && constant('MASTODON_VISIBILITY')) && (defined('MASTODON_LANG') && constant('MASTODON_LANG')))
    {
        $token = MASTODON_TOKEN;
        $base_url = MASTODON_URL;
        $visibility = MASTODON_VISIBILITY;
        $language = MASTODON_LANG;
        $mastodon = new MastodonAPI($token, $base_url);
        $status_data = ['visibility' => $visibility, 'language' => $language, 'status' => $message];
        $mastodon->postStatus($status_data);
    }
}
