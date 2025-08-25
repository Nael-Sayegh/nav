<?php
require_once'config.local.php';
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

    // Formatage du XML pour avoir une meilleure lisibilité
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = true;
    $dom->loadXML($xmlstr);
    $formattedXml = $dom->saveXML();

    file_put_contents($sitemap, $formattedXml);

    // Signaler à Google que le sitemap a été mis à jour
    notify_google_sitemap_update();

    return true;
}


/**
 * Notifie Google que le sitemap a été mis à jour
 * @param string $sitemapUrl URL complète du sitemap (optionnel)
 * @return bool
 */
function notify_google_sitemap_update($sitemapUrl = null) {
    if ($sitemapUrl === null) {
        $sitemapUrl = rtrim(SITE_URL, '/') . '/sitemap.xml';
    }

    // URL de l'API Google pour signaler la mise à jour du sitemap
    $googlePingUrl = 'https://www.google.com/ping?sitemap=' . urlencode($sitemapUrl);

    // Envoyer la requête à Google
    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'method' => 'GET',
            'header' => 'User-Agent: Mozilla/5.0 (compatible; SitemapNotifier/1.0)'
        ]
    ]);

    try {
        $response = @file_get_contents($googlePingUrl, false, $context);

        // Optionnel : log de la notification
        if (function_exists('error_log')) {
            if ($response !== false) {
                error_log("Sitemap notification sent to Google: $sitemapUrl");
            } else {
                error_log("Failed to notify Google about sitemap update: $sitemapUrl");
            }
        }

        return $response !== false;
    } catch (Exception $e) {
        if (function_exists('error_log')) {
            error_log("Error notifying Google about sitemap: " . $e->getMessage());
        }
        return false;
    }
}

/**
 * Notifie Bing que le sitemap a été mis à jour
 * @param string $sitemapUrl URL complète du sitemap (optionnel)
 * @return bool
 */
function notify_bing_sitemap_update($sitemapUrl = null) {
    if ($sitemapUrl === null) {
        $sitemapUrl = rtrim(SITE_URL, '/') . '/sitemap.xml';
    }

    // URL de l'API Bing pour signaler la mise à jour du sitemap
    $bingPingUrl = 'https://www.bing.com/ping?sitemap=' . urlencode($sitemapUrl);

    // Envoyer la requête à Bing
    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'method' => 'GET',
            'header' => 'User-Agent: Mozilla/5.0 (compatible; SitemapNotifier/1.0)'
        ]
    ]);

    try {
        $response = @file_get_contents($bingPingUrl, false, $context);

        // Optionnel : log de la notification
        if (function_exists('error_log')) {
            if ($response !== false) {
                error_log("Sitemap notification sent to Bing: $sitemapUrl");
            } else {
                error_log("Failed to notify Bing about sitemap update: $sitemapUrl");
            }
        }

        return $response !== false;
    } catch (Exception $e) {
        if (function_exists('error_log')) {
            error_log("Error notifying Bing about sitemap: " . $e->getMessage());
        }
        return false;
    }
}

/**
 * Notifie tous les moteurs de recherche supportés
 * @param string $sitemapUrl URL complète du sitemap (optionnel)
 * @return array Résultats des notifications [moteur => success]
 */
function notify_all_search_engines($sitemapUrl = null) {
    $results = [];
    $results['google'] = notify_google_sitemap_update($sitemapUrl);
    $results['bing'] = notify_bing_sitemap_update($sitemapUrl);
    return $results;
}
/**
 * Supprime une URL du sitemap.xml
 * @param string $url URL complète à supprimer
 * @param string $sitemap Chemin du sitemap.xml
 * @return bool
 */

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

    // Formatage du XML pour avoir une meilleure lisibilité
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = true;
    $dom->loadXML($xmlstr);
    $formattedXml = $dom->saveXML();

    file_put_contents($sitemap, $formattedXml);

    // Signaler à Google que le sitemap a été mis à jour
    notify_google_sitemap_update();

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
        'nl.php',
        'pull_repo.php',
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
            $sql = 'SELECT id, date FROM softwares';
            $req = $bdd->query($sql);
            while ($row = $req->fetch()) {
                $url = $baseUrl . 'a' . $row['id'];
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
            while ($row = $req->fetch()) {
                $url = $baseUrl . 'c' . $row['id'];
                $u = $xml->addChild('url');
                $u->addChild('loc', htmlspecialchars($url));
                $u->addChild('lastmod', date('Y-m-d'));
                $added++;
            }
    }

/**
 * Met à jour le sitemap.xml (wrapper simple)
 * @param array $options Options à passer à generate_sitemap
 * @return int Nombre d'URLs générées
 */

    $xmlStr = $xml->asXML();

    // Formatage du XML pour avoir une meilleure lisibilité
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = true;
    $dom->loadXML($xmlStr);
    $formattedXml = $dom->saveXML();

    // Génère le sitemap à la racine du projet (../sitemap.xml depuis admin/)
    file_put_contents($rootDir . '/sitemap.xml', $formattedXml);

    // Signaler à Google que le sitemap a été mis à jour
    notify_google_sitemap_update();

    return $added;
}

// --- Exécution directe (CLI ou navigateur) ---

if (php_sapi_name() === 'cli' || isset($_SERVER['REQUEST_METHOD'])) {
    // Vérification des prérequis
    if (!defined('SITE_URL') || SITE_URL === 'BASE DOMAIN OF YOUR WEBSITE') {
        echo "ERREUR: Veuillez configurer SITE_URL dans votre fichier config.local.php\n";
        exit(1);
    }

    echo "Génération du sitemap en cours...\n";
    echo "URL de base: " . SITE_URL . "\n";

    // L'URL de base est prise automatiquement depuis SITE_URL (config.local.php)
    $nb = generate_sitemap([
        // 'manualUrls' => [SITE_URL . '/page-speciale'],
    ]);
    echo "Sitemap généré avec succès ($nb URLs).\n";
}
