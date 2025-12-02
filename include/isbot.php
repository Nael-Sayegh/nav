<?php

declare(strict_types=1);

$isbot = false;
if (!empty($_SERVER['HTTP_USER_AGENT']))
{
    $uabots = ['DotBot','bingbot','Googlebot','Ahrefsbot','Twitterbot','applebot','PaperLiBot','SemrushBot','SurdotlyBot','SocialRankIOBot','ubermetrics','facebookexternalhit','LivelapBot','TrendsmapResolver','bot@linkfluence.com','YandexBot','MJ12bot','Mastodon','Akkoma','spider','HaloBot','PetalBot','MojeekBot','OAI-SearchBot','GPTBot','ClaudeBot','coccocbot-web','sqlmap','AdsBot','SemanticScholarBot','meta-externalagent','IbouBot','Thinkbot','ChatGPT-User','Amazonbot','PerplexityBot','Lumibot','nbertaupete95','python-requests','GoogleImageProxy','bot'];
    foreach ($uabots as &$uabot)
    {
        if (stripos((string) $_SERVER['HTTP_USER_AGENT'], $uabot) !== false || $_SERVER['HTTP_USER_AGENT'] === '-' || $_SERVER['HTTP_USER_AGENT'] === 'Mozilla/5.0')
        {
            $isbot = true;
            break;
        }
    }

    $blockedRanges = [
        // Googlebot + Google Cloud
        ['66.249.64.0', '66.249.95.255'],
        ['64.233.160.0', '64.233.191.255'],
        ['216.239.32.0', '216.239.63.255'],
        ['34.64.0.0', '34.127.255.255'],
        ['35.192.0.0', '35.207.255.255'],
        // Microsoft Bing + Azure
        ['40.77.167.0', '40.77.167.255'],
        ['13.64.0.0', '13.107.255.255'],
        ['52.167.0.0', '52.191.255.255'],
        // Amazon AWS / Amazonbot
        ['18.208.0.0', '18.255.255.255'],
        ['52.0.0.0', '52.95.255.255'],
        ['54.144.0.0', '54.191.255.255'],
        // Hetzner
        ['94.130.0.0', '94.130.255.255'],
        ['88.198.0.0', '88.198.255.255'],
        ['78.46.0.0', '78.46.255.255'],
        // OVH
        ['51.68.0.0', '51.75.255.255'],
        ['51.77.0.0', '51.83.255.255'],
        // Yandex
        ['5.255.192.0', '5.255.255.255'],
        ['77.88.0.0', '77.88.63.255'],
        // Bytedance / TikTok (Bytespider)
        ['47.88.0.0', '47.95.255.255'],
        ['47.128.0.0', '47.135.255.255'],
        // Huawei PetalBot
        ['114.119.128.0', '114.119.159.255'],
        // Apple
        ['17.0.0.0', '17.255.255.255'],
    ];

    $ip = ip2long($_SERVER['REMOTE_ADDR']);
    foreach ($blockedRanges as [$start, $end])
    {
        if ($ip >= ip2long($start) && $ip <= ip2long($end))
        {
            $isbot = true;
            break;
        }
    }
}
