<?php

declare(strict_types=1);

const RANK_LABELS = [
    '0' => 'urank_0',
    '1' => 'urank_1',
    'a' => 'urank_a',
    'b' => 'urank_b',
];

function urank(string $rank, string $user = '', bool $er = true): string
{
    global $tr0;

    $trKey = RANK_LABELS[$rank] ?? null;
    if (!$trKey)
    {
        return '';
    }

    $label = tr($tr0, $trKey);
    $mode = $user === '' ? '1' : '2';
    $baseClass = sprintf('rk rk%s rk_%s', $mode, $rank);
    if ($user === '')
    {
        return sprintf('<span class="%s">%s</span>', $baseClass, $label);
    }

    $suffix = '';
    if ($er)
    {
        $suffix = sprintf(
            '<span class="rk2r">&#x20;(%s)</span>',
            $label,
        );
    }

    return sprintf(
        '<span class="%s" title="%s">%s</span>%s',
        $baseClass,
        $label,
        htmlspecialchars($user, ENT_QUOTES, 'UTF-8'),
        $suffix,
    );
}
