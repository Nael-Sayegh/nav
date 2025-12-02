<?php

declare(strict_types=1);

require_once(realpath(__DIR__.'/../cache/langs.php'));
require_once(realpath(__DIR__.'/../cache/langs_index.php'));

function langs_html_opts(string $selected = ''): string|array
{
    global $langs_html_opts;
    return str_replace('value="'.$selected.'"', 'value="'.$selected.'" selected', $langs_html_opts);
}

function load_tr(string $trlang, string $trname)
{
    global $available_trs;

    if (!isset($available_trs[$trlang]) || !in_array($trname, $available_trs[$trlang], true))
    {
        return [];
    }

    $file = realpath(__DIR__.'/../locales/'.$trlang.'/'.$trname.'.tr.php');
    if (!file_exists($file))
    {
        return [];
    }

    return (static function ($file) {
        include $file;
        return $tr ?? [];
    })($file);
}

function tr(array &$ttr, $tkey, array $vars = []): string|array
{
    if (isset($ttr[$tkey]))
    {
        return bparse($ttr[$tkey], $vars);
    }

    global $langs_prio;

    $trname = $ttr['_'] ?? null;
    if (!$trname)
    {
        return '';
    }

    foreach ($langs_prio as $lang_prio)
    {
        $fallback_tr = load_tr($lang_prio, $trname);
        if (isset($fallback_tr[$tkey]))
        {
            $ttr[$tkey] = $fallback_tr[$tkey];
            return bparse($fallback_tr[$tkey], $vars);
        }
    }

    return '';
}

function getLangLabel(string $lang)
{
    global $langs;
    return $langs[$lang] ?? $lang;
}
