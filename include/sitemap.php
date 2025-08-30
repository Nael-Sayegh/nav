<?php
require_once'config.local.php';
require_once 'dbconnect.php';

function shouldExclude($path, $exclude) {
    foreach ($exclude as $ex) {
        if (stripos($path, DIRECTORY_SEPARATOR . $ex) !== false || basename($path) === $ex) {
            return true;
        }
    }
    return false;
}

function getFiles($dir, $exclude, $includeExtensions) {
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    $files = [];
    foreach ($rii as $file) {
        if ($file->isDir()) continue;
        $filePath = $file->getPathname();
        if (shouldExclude($filePath, $exclude)) continue;
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (!in_array($ext, $includeExtensions)) continue;
        $files[] = $filePath;
    }
    return $files;
}

function generate_sitemap(array $options = []) {
    global $bdd;

    $baseUrl = SITE_URL ? rtrim(SITE_URL, '/') . '/' : '';
    $rootDir = $options['rootDir'] ?? DOCUMENT_ROOT;
    $exclude = $options['exclude'] ?? [
        '403',
        'a',
        'admin',
        'c',
        'cache',
        'docs',
        'githooks',
        'images',
        'include',
        'locales',
        'r',
        'scripts',
        'tasks',
        'u',
        'vendor',
        '.git',
        '.php-cs-fixer.php',
        '2fa_check.php',
        'article.php',
        'cat.php',
        'confirm.php',
        'contact_form.php',
        'home.php',
        'login_redirect.php',
        'logout.php',
        'members_list.php',
        'nav_redirect.php',
        'nlmod.php',
        'pull_repo.php',
        'search.php',
        'settings.php',
    ];
    $includeExtensions = $options['includeExtensions'] ?? ['php', 'html', 'htm'];
    $manualUrls = $options['manualUrls'] ?? [];
    $addArticles = $options['addArticles'] ?? true;
    $addCategories = $options['addCategories'] ?? true;

    $files = getFiles($rootDir, $exclude, $includeExtensions);

    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>');
    $added = 0;
    foreach ($files as $file) {
        $relativePath = str_replace($rootDir . DIRECTORY_SEPARATOR, '', $file);
        $url = $baseUrl . str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
        $url = preg_replace('/index\\.(php|html?)$/', '', $url); // Nettoie les index.php
        $url = rtrim($url, '/');
        $u = $xml->addChild('url');
        $u->addChild('loc', htmlspecialchars($url));
        $u->addChild('lastmod', date('Y-m-d', filemtime($file)));
        $added++;
    }

    foreach ($manualUrls as $url) {
        $u = $xml->addChild('url');
        $u->addChild('loc', htmlspecialchars($url));
        $u->addChild('lastmod', date('Y-m-d'));
        $added++;
    }

    if ($addArticles || $addCategories) {
        if ($addArticles) {
            $sql = 'SELECT DISTINCT softwares.id, softwares.date
                    FROM softwares
                    LEFT JOIN softwares_tr ON softwares.id = softwares_tr.sw_id
                    WHERE softwares_tr.published = true';
            $req = $bdd->query($sql);
            while ($row = $req->fetch()) {
                $url = $baseUrl . 'a' . $row['id'];
                $u = $xml->addChild('url');
                $u->addChild('loc', htmlspecialchars($url));
                $u->addChild('lastmod', date('Y-m-d', $row['date']));
                $added++;
            }
        }

        if ($addCategories) {
            $sql = 'SELECT id FROM softwares_categories';
            $req = $bdd->query($sql);
            while ($row = $req->fetch()) {
                $url = $baseUrl . 'c' . $row['id'];
                $u = $xml->addChild('url');
                $u->addChild('loc', htmlspecialchars($url));
                $u->addChild('lastmod', date('Y-m-d'));
                $added++;
            }
        }
    }

    $xmlStr = $xml->asXML();

    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = true;
    $dom->loadXML($xmlStr);
    $formattedXml = $dom->saveXML();

    file_put_contents($rootDir . '/sitemap.xml', $formattedXml);
    return $added;
}

function remove_sitemap_url($url, $sitemap = null) {
    if ($sitemap === null) {
        $sitemap = DOCUMENT_ROOT . '/sitemap.xml';
    }
    if (!file_exists($sitemap)) return false;
    $xml = simplexml_load_file($sitemap);
    if (!$xml) return false;
    $toRemove = [];
    foreach ($xml->url as $i => $u) {
        if ((string)$u->loc === $url) {
            $toRemove[] = $i;
        }
    }
    foreach (array_reverse($toRemove) as $i) {
        unset($xml->url[$i]);
    }
    $xmlstr = $xml->asXML();

    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = true;
    $dom->loadXML($xmlstr);
    $formattedXml = $dom->saveXML();

    file_put_contents($sitemap, $formattedXml);
    return true;
}

function update_sitemap_url($url, $lastmod = null, $sitemap = null) {
    if ($sitemap === null) {
        $sitemap =  DOCUMENT_ROOT . '/sitemap.xml';
    }
    if (!file_exists($sitemap)) return false;
    $xml = simplexml_load_file($sitemap);
    if (!$xml) return false;
    $found = false;
    foreach ($xml->url as $u) {
        if ((string)$u->loc === $url) {
            $u->lastmod = $lastmod ?: date('Y-m-d');
            $found = true;
            break;
        }
    }
    if (!$found) {
        $new = $xml->addChild('url');
        $new->addChild('loc', $url);
        $new->addChild('lastmod', $lastmod ?: date('Y-m-d'));
    }
    $xmlstr = $xml->asXML();

    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = true;
    $dom->loadXML($xmlstr);
    $formattedXml = $dom->saveXML();

    file_put_contents($sitemap, $formattedXml);
    return true;
}

if (php_sapi_name() === 'cli') {
    echo "Generation of the sitemap in progress\n";
    $nb = generate_sitemap();
    echo "Sitemap generated successfully ($nb URLs).\n";
}
