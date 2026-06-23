<?php

declare(strict_types=1);

$changedFiles = trim(shell_exec('git diff --cached --name-only'));

$time = time();
$files = explode("\n", $changedFiles);
foreach ($files as $file)
{
    if (!preg_match('#^locales/.*\.tr\.php$#', $file))
    {
        continue;
    }

    $content = file_get_contents($file);
    if (preg_match('/([\'"]_last_modif[\'"]\s*=>\s*)(\d+)/', $content, $m))
    {
        $newContent = preg_replace('/([\'"]_last_modif[\'"]\s*=>\s*)(\d+)/', '${1}' . $time, $content);
        file_put_contents($file, $newContent);
        // Ajout du fichier modifié à l'index Git
        shell_exec('git add ' . escapeshellarg($file));
    }
}
