<?php

require_once('config.local.php');
require_once('Rights.php');
require_once('tr.php');

function urlsafe_b64encode($str)
{
    return strtr(preg_replace('/[\=]+\z/', '', base64_encode((string) $str)), '+/=', '-_');
}

function urlsafe_b64decode($data)
{
    $data = preg_replace('/[\t-\x0d\s]/', '', strtr($data, '-_', '+/'));
    $mod4 = strlen((string) $data) % 4;
    if ($mod4)
    {
        $data .= substr('====', $mod4);
    }
    return base64_decode($data);
}

function zeros($n, $d = 3)
{
    $l = floor(log10($n) + 1);
    if ($l < $d)
    {
        return str_repeat('0', (int)($d - $l)) . $n;
    }
    else
    {
        return strval($n);
    }
}

function args_html_form($args)
{
    $r = '';
    foreach ($args as $name => $value)
    {
        $r .= '<input type="hidden" name="'.$name.'" value="'.$value.'">';
    }
    return $r;
}

function bparse($text, $vars)
{
    global $site_name, $slogan, $site_url;
    $vars['site'] = $site_name;
    $vars['slogan'] = $slogan;
    $vars['url'] = $site_url;
    foreach ($vars as $var1 => $var2)
    {
        if ($var2 === null)
        {
            $var2 = '';
        }
        $text = str_replace('{{'.$var1.'}}', $var2, $text);
    }
    return $text;
}

function numberlocale($n)
{
    global $tr0;
    return str_replace('.', tr($tr0, 'decimal_separator'), strval($n));
}

function getFormattedDate($timestamp, $format)
{
    global $tr0, $lang;
    $timestamp = (int) $timestamp;
    $dateTimeObj = new DateTime('@' . $timestamp);
    $dateTimeObj->setTimezone(new DateTimeZone(tr($tr0, 'timezone')));
    return IntlDateFormatter::formatObject($dateTimeObj, $format, $lang);
}

function human_filesize($bytes, $decimals = 1)
{
    $sz = ' kMGTP';
    $factor = floor((strlen((string) $bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / 1024 ** $factor) . ' ' . @$sz[$factor];
}

function get_article_trs($article_id)
{
    global $bdd;
    $SQL = <<<SQL
        SELECT softwares_tr.id, softwares_tr.lang, softwares_tr.name, softwares_tr.description, softwares_tr.sw_id, softwares.hits, softwares.downloads, softwares.date, softwares.category
        FROM softwares
        LEFT JOIN softwares_tr ON softwares.id=softwares_tr.sw_id
        WHERE softwares.id=:swid
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':swid' => $article_id]);
    if ($data = $req->fetch())
    {
        $article = ['cat' => $data['category'], 'hits' => $data['hits'], 'dl' => $data['downloads'], 'date' => $data['date'], 'trs' => []];
        $article['trs'][$data['lang']] = ['id' => $data['id'], 'title' => $data['name'], 'desc' => $data['description']];
        return $article;
    }
    return false;
}

function get_article_prefered_tr($article_id, $lang)
{
    global $langs_prio;
    if (!$article = get_article_trs($article_id))
    {
        return false;
    }
    $tr = '';
    if (array_key_exists($lang, $article['trs']))
    {
        $tr = $lang;
    }
    else
    {
        foreach ($langs_prio as &$i_lang)
        {
            if (array_key_exists($i_lang, $article['trs']))
            {
                $tr = $i_lang;
                break;
            }
        }
    }
    $article['prefered_tr'] = $tr;
    return $article;
}

function getVersionFromGit()
{
    global $tr0, $site_name;
    $gitDir = $_SERVER['DOCUMENT_ROOT'].'/'.GIT_DIR;
    $hash = trim(shell_exec('git --git-dir="'.$gitDir.'" rev-parse --verify HEAD'));
    $shortHash = trim(shell_exec('git --git-dir="'.$gitDir.'" show -s --format=%h ' . $hash));
    $link = '<a href="' . GIT_COMMIT_BASE_URL . $hash . '">(' . $shortHash . ')</a>';

    if (isDev())
    {
        $timestamp = trim(shell_exec('git --git-dir="'.$gitDir.'" show -s --format=%ct ' . $hash));
        $commitVersion = getFormattedDate($timestamp, 'yy.MM.dd.HHmm');
    }
    else
    {
        $tag = trim(shell_exec('git --git-dir="'.$gitDir.'" describe --tags --abbrev=0 2>/dev/null'));
        $commitVersion = $tag ?: 'unknown';
    }

    echo tr($tr0, 'footer_lastcommit', ['commit_url' => $commitVersion . $link]);
}

function getContentLastModif()
{
    global $tr, $tr0, $lang, $sw_tr;

    if (isset($sw_tr, $sw_tr['date']))
    {
        $date = getFormattedDate($sw_tr['date'], tr($tr0, 'fndate'));
        $time = getFormattedDate($sw_tr['date'], tr($tr0, 'ftime'));
        echo tr($tr0, 'lasttranslate', ['date' => $date, 'time' => $time, 'lang' => getLangLabel($lang)]);
    }
    elseif (isset($tr))
    {
        if (tr($tr, '_last_modif') !== null)
        {
            $date = getFormattedDate(tr($tr, '_last_modif'), tr($tr0, 'fndate'));
            $time = getFormattedDate(tr($tr, '_last_modif'), tr($tr0, 'ftime'));
            echo tr($tr0, 'lasttranslate', ['date' => $date, 'time' => $time, 'lang' => getLangLabel($lang)]);
        }
        else
        {
            echo tr($tr0, 'no_lasttranslate');
        }
    }
    else
    {
        echo tr($tr0, 'no_translate');
    }
}

function isDev()
{
    if ((isset($_SERVER['HTTP_HOST']) && strstr((string) $_SERVER['HTTP_HOST'], 'dev.')) || DEV === true)
    {
        return true;
    }
    else
    {
        return false;
    }
}

function setTimeZone($timezone, $lc_code)
{
    date_default_timezone_set($timezone);
    setlocale(LC_ALL, $lc_code);
}

function getUsernameById($id)
{
    global $bdd2;
    if (is_numeric($id))
    {
        $id = (int) $id;
        $SQL = <<<SQL
            SELECT * FROM accounts WHERE id=:id
            SQL;
        $req = $bdd2->prepare($SQL);
        $req->execute([':id' => $id]);
        if ($user = $req->fetch())
        {
            if ($user['rank'] === "a")
            {
                $req2 = $bdd->prepare('SELECT short_name FROM team WHERE account_id = '.$user['id']);
                $req2->execute();
                if ($admin = $req2->fetch())
                {
                    return $admin['short_name'];
                }
            }
            else
            {
                return $user['username'];
            }
        }
    }
    return false;
}

function getTeamEmails(?string $right = null)
{
    global $bdd2;

    $sql = <<<SQL
        SELECT
            accounts.email,
            team.rights
        FROM team
        LEFT JOIN accounts
            ON accounts.id = team.account_id
        WHERE team.works IN ('1', '2')
        SQL;

    $req = $bdd2->prepare($sql);
    $req->execute();
    $rows = $req->fetchAll(PDO::FETCH_ASSOC);
    $emails = [];
    foreach ($rows as $row)
    {
        if ($right !== null)
        {
            $granted = getAdminRights($row['rights']);
            if (!in_array($right, $granted, true))
            {
                continue;
            }
        }
        $emails[] = $row['email'];
    }

    return $emails;
}

function get_categories()
{
    $json = file_get_contents(__DIR__.'/../cache/menu_categories.json');
    return json_decode($json, true) ?: [];
}

if (!(isset($noct) && $noct))
{
    header('Content-Type: text/html; charset=UTF-8');
}
ini_set('default_charset', 'utf-8');
include_once 'maintenance_mode.php';
if (isset($modemaintenance) && $modemaintenance && !(isset($logged) && $logged && $login['rank'] === 'a') && !(isset($nomm) && $nomm))
{
    http_response_code(503);
    echo <<<HTML
        <!DOCTYPE html>
        <html lang="{$lang}">
        <head>
        <meta charset="utf-8">
        <meta name="robots" content="noindex, nofollow">
        <title>Site en maintenance</title>
        <audio src="/audio/forbidden.mp3" autoplay></audio>
        </head>
        <body>
        <h1>Maintenance en cours</h1>
        <p><b>Chantier interdit au public !</b><br>
        Une opération de maintenance est en cours.<br>
        À votre prochaine visite, vous découvrirez peut-être une fonctionnalité incroyable, un rapport de bug minuscule, ou un "chantier interdit au public".</p>
        </body>
        </html>
        HTML;
    exit();
}
require_once 'dbconnect.php';

// LANGUAGE
include_once DOCUMENT_ROOT.'/cache/langs.php';
$lang = '';
if (isset($_GET['lang']) && !empty($_GET['lang']) && in_array($_GET['lang'], $langs_prio))
{
    $lang = $_GET['lang'];
    setcookie('lang', (string) $lang, ['expires' => time() + 31557600, 'path' => '/', 'secure' => true, 'httponly' => false, 'samesite' => 'strict']);
}
elseif (isset($_COOKIE['lang']) && strlen((string) $_COOKIE['lang']) === 2)
{
    $lang = $_COOKIE['lang'];
}
elseif (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']))
{
    $lang = substr((string) $_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
}
if (!in_array($lang, $langs_prio))
{
    $lang = $langs_prio[0];
}
putenv('LANG='.$lang);

// MISC CONSTS/VARS
$tr0 = load_tr($lang, 'default');
$site_name = (isDev() ? SITE_NAME.'-Dev' : SITE_NAME);
$site_url = SITE_URL;
$css_path = '<link rel="stylesheet" href="/css/default.css">';
$admin_css_path = '<link rel="stylesheet" href="/admin/css/admin.css">';
$slogan = tr($tr0, 'slogan');
$lastosv = '17.0';
$tr_todo = [0 => 'Référence', 1 => 'OK', 2 => 'À vérifier', 3 => 'À modifier', 4 => 'À terminer'];
$args = [];
setTimeZone(tr($tr0, 'timezone'), tr($tr0, 'lc_code'));
// VERSION
$lastVersion = '';
$versionName = '';
$versionDate = 0;
$versionId = 0;
$SQL = <<<SQL
    SELECT * FROM site_updates ORDER BY date DESC LIMIT 1
    SQL;
$req = $bdd->prepare($SQL);
$req->execute();
if ($data = $req->fetch())
{
    $lastVersion = 'V'.$data['id'];
    $versionName = substr((string) $data['name'], 1);
    $versionDate = getFormattedDate($data['date'], tr($tr0, 'fndatetime'));
    $versionId = $data['id'];
}
