<?php

declare(strict_types=1);

$document_root = realpath(__DIR__.'/../');
require_once($document_root.'/include/consts.php');

$include_dirs = [
    '',
    '403',
    'a',
    'admin',
    'api',
    'c',
    'css',
    'gadgets',
    'include',
    'include/lib/facebook',
    'locales',
    'r',
    'scripts',
    'tasks',
    'u',
];

$exclude_paths = [
    'cache',
    'vendor',
    'include/lib/facebook/vendor',
    'include/lib/mtcaptcha',
    'admin/adminer',
];

$allowed_exts = ['php', 'css', 'js', 'xml', 'txt'];

function list_files_recursive(string $root, array $include_dirs, array $exclude_paths, array $allowed_exts): array
{
    $files = [];

    $rii = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($rii as $file)
    {
        if (!$file->isFile())
        {
            continue;
        }

        $rel = substr((string) $file->getPathname(), strlen($root) + 1);

        foreach ($exclude_paths as $exclude_path)
        {
            $exclude_path = rtrim((string) $exclude_path, '/');
            if (str_starts_with($rel, $exclude_path.'/'))
            {
                continue 2;
            }

            if ($rel === $exclude_path)
            {
                continue 2;
            }
        }

        $included = false;
        foreach ($include_dirs as $include_dir)
        {
            $include_dir = rtrim((string) $include_dir, '/');
            if ($include_dir === '' && !str_contains($rel, '/'))
            {
                $included = true;
                break;
            }

            if (str_starts_with($rel, $include_dir.'/'))
            {
                $included = true;
                break;
            }
        }

        if (!$included)
        {
            continue;
        }

        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        if ($ext === '')
        {
            continue;
        }

        if (!in_array($ext, $allowed_exts, true))
        {
            continue;
        }

        $files[] = $rel;
    }

    return array_values(array_unique($files));
}

$files = list_files_recursive($document_root, $include_dirs, $exclude_paths, $allowed_exts);

$n_files = 0;
$n_lines = 0;
$n_chars = 0;

foreach ($files as $file)
{
    $n_files++;
    $path = $document_root.'/'.$file;
    if ($f = fopen($path, 'r'))
    {
        while (!feof($f))
        {
            fgets($f, 8192);
            $n_lines++;
        }

        fclose($f);
        $n_chars += filesize($path);
    }
    else
    {
        echo sprintf('Not found: %s%s', $file, PHP_EOL);
    }
}

$outfile = fopen($document_root.'/cache/codestatc.php', 'w');
fwrite($outfile, '<?php $codestat_n_files='.$n_files.';$codestat_n_lines='.$n_lines.';$codestat_n_chars='.$n_chars.'; ?>');
fclose($outfile);
