<?php
require_once 'config.local.php';
require_once 'dbconnect.php';

/**
 * Ajoute ou met à jour une URL dans sitemap.xml
 * @param string $url URL complète (ex: SITE_URL.'/article.php?id=123')
 * @param string $lastmod Date de dernière modif (Y-m-d), optionnel
 * @param string $sitemap Chemin du sitemap.xml
 * @return bool
 */

function update_sitemap_url($url, $lastmod = null, $sitemap = null) {
    if ($sitemap === null) {
        $sitemap =  $document_root . '/sitemap.xml';
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
    file_put_contents($sitemap, $xmlstr);
    return true;
    }


/**
 * Supprime une URL du sitemap.xml
 * @param string $url URL complète à supprimer
 * @param string $sitemap Chemin du sitemap.xml
 * @return bool
 */

function remove_sitemap_url($url, $sitemap = null) {
    if ($sitemap === null) {
        $sitemap = $document_root . '/sitemap.xml';
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
    file_put_contents($sitemap, $xmlstr);
    return true;
}

// Chargement de la config et de la connexion BDD comme dans le reste du site

/**
 * Générateur de sitemap.xml personnalisable
 *
 * - Peut être appelé en CLI, navigateur, ou inclus dans un autre script
 * - Permet d'ajouter des URLs manuelles
 * - Génère dynamiquement les URLs d'articles et de catégories
 *
 * Utilisation CLI :
 *   php generate_sitemap.php
 * Utilisation PHP :
 *   require 'generate_sitemap.php';
 *   generate_sitemap([...options...]);
 */



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
    $baseUrl = $SITE_URL ? rtrim($SITE_URL, '/') . '/' : '';
    $rootDir = $options['rootDir'] ?? $document_root;
    $exclude = $options['exclude'] ?? [
        'admin',
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
        'favicon.ico',
        'robots.txt',
        'sitemap.xml',
        'composer.json',
        'composer.lock',
        'CRONTAB',
        'LICENSE',
        'URL_Rewrites.txt',
        '.git',
        '.gitignore',
        '.vscode',
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

    if (($addArticles || $addCategories) && isset($bdd)) {
        if ($addArticles) {
            $sql = 'SELECT id, date FROM softwares';
            $req = $bdd->query($sql);
            if ($req) {
                while ($row = $req->fetch()) {
                    $url = $baseUrl . 'article.php?id=' . $row['id'];
                    $u = $xml->addChild('url');
                    $u->addChild('loc', htmlspecialchars($url));
                    $u->addChild('lastmod', date('Y-m-d', $row['date']));
                    $added++;
                }
            }
        }
        if ($addCategories) {
            $sql = 'SELECT id FROM softwares_categories';
            $req = $bdd->query($sql);
            if ($req) {
                while ($row = $req->fetch()) {
                    $url = $baseUrl . 'cat.php?id=' . $row['id'];
                    $u = $xml->addChild('url');
                    $u->addChild('loc', htmlspecialchars($url));
                    $u->addChild('lastmod', date('Y-m-d'));
                    $added++;
                }
            }
        }
    }
/**
 * Met à jour le sitemap.xml (wrapper simple)
 * @param array $options Options à passer à generate_sitemap
 * @return int Nombre d'URLs générées
 */

    $xmlStr = $xml->asXML();
    // Génère le sitemap à la racine du projet (../sitemap.xml depuis admin/)
    file_put_contents($rootDir . '/sitemap.xml', $xmlStr);
    return $added;
}

// --- Exécution directe (CLI ou navigateur) ---

if (php_sapi_name() === 'cli' || isset($_SERVER['REQUEST_METHOD'])) {
    // L'URL de base est prise automatiquement depuis SITE_URL (config.local.php)
    $nb = generate_sitemap([
        // 'manualUrls' => [SITE_URL . '/page-speciale'],
    ]);
    echo "Sitemap généré avec succès ($nb URLs).\n";
}
