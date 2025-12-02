<?php

declare(strict_types=1);

$document_root = __DIR__.'/..';
require_once($document_root.'/include/dbconnect.php');

$SQL = <<<SQL
    SELECT lang, name FROM languages ORDER BY name ASC
    SQL;
$langs = [];
$langs_alpha = [];
foreach ($bdd->query($SQL) as $data)
{
    $langs[$data['lang']] = $data['name'];
    $langs_alpha[] = $data['lang'];
}

$SQL = <<<SQL
    SELECT lang FROM languages ORDER BY priority ASC
    SQL;
$langs_prio = [];
foreach ($bdd->query($SQL) as $data)
{
    $langs_prio[] = $data['lang'];
}

$langs_html_opts = '';
foreach ($langs_alpha as $i)
{
    $escaped = str_replace("'", '\\\'', str_replace('\\', '\\\\', $langs[$i]));
    $langs_html_opts .= '<option value="'.$i.'" title="'.$i.'">'.$escaped.'</option>';
}

$available_trs = [];
$available_trs_index = [];
$all = scandir($document_root.'/locales');
if ($all === false)
{
    throw new RuntimeException(sprintf('Impossible de lister %s/locales', $document_root));
}

$trsdirs = array_filter($all, function (string $d) use ($document_root): bool {
    if (in_array($d, ['.', '..', 'LICENSE.txt'], true))
    {
        return false;
    }

    return is_dir($document_root . '/locales/' . $d);
});
foreach ($trsdirs as $trsdir)
{
    $dir = $document_root . '/locales/' . $trsdir;
    $files = scandir($dir);
    if ($files === false)
    {
        continue;
    }

    $trsfiles = array_filter($files, fn (string $f): int|false => preg_match('/^(.+)\.tr\.php$/', $f));
    foreach ($trsfiles as $trsfile)
    {
        if (preg_match('/^(.+)\\.tr\\.php$/', $trsfile, $match))
        {
            $available_trs[$trsdir][] = $match[1];
            $filename = $document_root.'/locales/'.$trsdir.'/'.$match[1].'.tr.php';
            include $filename;
            $available_trs_index[$trsdir][$tr['_']] = [
                'todo_level' => $tr['_todo_level'] ?? null,
                'last_author' => $tr['_last_author'] ?? null,
                'last_modif' => $tr['_last_modif'] ?? null,
            ];
        }
    }
}

file_put_contents($document_root.'/cache/langs.php', '<?php
$langs = '.var_export($langs, true).';
$langs_prio = '.var_export($langs_prio, true).';
$langs_html_opts = \''.$langs_html_opts.'\';
$available_trs = '.var_export($available_trs, true).';
');

file_put_contents($document_root.'/cache/langs_index.php', '<?php
$available_trs_index = '.var_export($available_trs_index, true).';
');
