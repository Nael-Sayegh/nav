<?php

declare(strict_types=1);

require_once(__DIR__ . '/include/config.local.php');

header('Content-Type: text/plain; charset=utf-8');

$headers = function_exists('getallheaders') ? getallheaders() : [];

$payload = file_get_contents('php://input') ?: '';

if (GIT_TYPE === 'GL')
{
    $token = $headers['X-Gitlab-Token'] ?? '';

    if ($token === '')
    {
        http_response_code(401);
        echo "Missing GitLab token\n";
        exit;
    }

    if ($token !== GIT_WEBHOOK_TOKEN)
    {
        http_response_code(403);
        echo "Invalid GitLab token\n";
        exit;
    }

    echo "GitLab authentication OK\n\n";
}
elseif (GIT_TYPE === 'GH')
{
    $signature = $headers['X-Hub-Signature-256'] ?? ($headers['X-Hub-Signature'] ?? '');

    if ($signature === '')
    {
        http_response_code(401);
        echo "Missing GitHub signature\n";
        exit;
    }

    $expectedSha256 = 'sha256=' . hash_hmac('sha256', $payload, GIT_WEBHOOK_TOKEN);

    $expectedSha1 = 'sha1=' . hash_hmac('sha1', $payload, GIT_WEBHOOK_TOKEN);

    if (!hash_equals($expectedSha256, $signature) && !hash_equals($expectedSha1, $signature))
    {
        http_response_code(403);
        echo "Invalid GitHub signature\n";
        exit;
    }

    echo "GitHub authentication OK\n\n";
}
else
{
    http_response_code(500);
    echo "Invalid GIT_TYPE (expected GL or GH)\n";
    exit;
}

$cmd = 'git --git-dir=' . escapeshellarg(GIT_DIR) . ' pull 2>&1';

$out = [];
$code = 0;
exec($cmd, $out, $code);

echo "=== git pull ===\n";
echo implode("\n", $out) . "\n\n";

if ($code !== 0)
{
    http_response_code(500);
    echo "pull NOT OK (exit {$code})\n";
    exit;
}

echo "pull OK\n";


$cmd = 'cd ' . escapeshellarg(__DIR__) . ' && composer install 2>&1';

$out = [];
$code = 0;
exec($cmd, $out, $code);

echo "\n=== composer install ===\n";
echo implode("\n", $out) . "\n\n";

if ($code !== 0)
{
    http_response_code(500);
    echo "composer install NOT OK (exit {$code})\n";
    exit;
}

http_response_code(200);
echo "composer install OK\n";
