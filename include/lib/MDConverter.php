<?php

declare(strict_types=1);

$document_root = __DIR__.'/../..';
require_once $document_root.'/vendor/autoload.php';

use League\CommonMark\CommonMarkConverter;

function convertToMD($text): string
{
    $converter = new CommonMarkConverter([
        'html_input' => 'allow',
        'allow_unsafe_links' => false,
    ]);
    return html_entity_decode($converter->convert(htmlspecialchars((string) $text)));
}
