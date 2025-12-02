<?php
set_include_path($_SERVER['DOCUMENT_ROOT']);
require_once(__DIR__ . '/include/log.php');
require_once(__DIR__ . '/include/consts.php');
$tr = load_tr($lang, 'search');
$title = tr($tr, 'title');
$stats_page = 'recherche';
$css_path .= '<link rel="stylesheet" href="/css/search.css">';
$searchterms = '';
if (isset($_GET['q']) && $_GET['q'] !== '' && strlen((string) $_GET['q']) <= 255)
{
    $searchterms = $_GET['q'];
    $args['q'] = $searchterms;
    if ($searchterms === '﷐')
    {
        switch ($lang)
        {
            case 'en': header('Location: https://en.wikipedia.org/wiki/Basilisk');
                break;
            case 'eo': header('Location: https://eo.wikipedia.org/wiki/Bazilisko_(mitologio)');
                break;
            case 'es': header('Location: https://es.wikipedia.org/wiki/Basilisco_(criatura_mitol%C3%B3gica)');
                break;
            case 'fr': header('Location: https://fr.wikipedia.org/wiki/Basilic_(mythologie)');
                break;
        }
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<?php require_once(__DIR__ . '/include/header.php'); ?>
<body>
<?php require_once(__DIR__ . '/include/banner.php'); ?>
<main id="container">
<h1 id="contenu"><?php
if (!empty($searchterms))
{
    echo tr($tr, 'title2', ['terms' => htmlspecialchars((string) $_GET['q'])]);
}
?></h1>
<?php
if (!empty($searchterms))
{
    $SQL = <<<SQL
        SELECT * FROM softwares_categories
        SQL;
    $cats = [];
    foreach ($bdd->query($SQL) as $data)
    {
        $cats[$data['id']] = $data['name'];
    }

    $atime = microtime(true);
    $terms = explode(' ', (string) $searchterms);
    $results = [];
    $where = '';
    $cat = [];
    if (!empty($_GET['c']) && is_array($_GET['c']))
    {
        $catIds = array_filter($_GET['c'], fn ($v): bool => $v !== '');
        if ($catIds !== [])
        {
            $placeholders = [];
            foreach (array_values($catIds) as $i => $catId)
            {
                $ph = ':cat' . $i;
                $placeholders[] = $ph;
                $cat[$ph] = (int)$catId;
            }
            $where = ' WHERE category IN (' . implode(',', $placeholders) . ')';
        }
    }

    $entries = [];
    $SQL = <<<SQL
        SELECT softwares_tr.id, softwares_tr.lang, softwares_tr.name, softwares_tr.keywords, softwares_tr.description, softwares_tr.sw_id, softwares.category, softwares.hits, softwares.downloads
        FROM softwares
        LEFT JOIN softwares_tr ON softwares.id=softwares_tr.sw_id {$where}
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute($cat);
    while ($data = $req->fetch())
    {
        if (!isset($entries[$data['sw_id']]))
        {
            $entries[$data['sw_id']] = ['cat' => $data['category'], 'hits' => $data['hits'], 'dl' => $data['downloads'], 'trs' => []];
        }
        $entries[$data['sw_id']]['trs'][$data['lang']] = ['id' => $data['id'], 'title' => $data['name'], 'tags' => $data['keywords'], 'desc' => $data['description']];
    }

    foreach ($entries as $sw_id => $entry)
    {
        $entry_tr = '';
        if (array_key_exists((string) $lang, $entry['trs']))
        {
            $entry_tr = $lang;
        }
        else
        {
            foreach ($langs_prio as &$lang_prio)
            {
                if (array_key_exists((string) $lang_prio, $entry['trs']))
                {
                    $entry_tr = $lang_prio;
                    break;
                }
            }
        }
        unset($i_lang);
        if ($entry_tr === 0)
        {
            continue;
        }
        if ($entry_tr === '')
        {
            continue;
        }
        if ($entry_tr === '0')
        {
            continue;
        }
        if ($entry_tr === '')
        {
            continue;
        }
        if ($entry_tr === '0')
        {
            continue;
        }

        $tags = explode(' ', (string) $entry['trs'][$entry_tr]['tags']);
        $pts = intval($terms[0] === '*');
        if ($pts !== 0)
        {
            array_shift($tags);
        }
        foreach ($terms as &$term)
        {
            $imp = 3;
            foreach ($tags as &$tag)
            {
                if ($term === $tag)
                {
                    $pts += 12 + $imp ** 2;
                }
                else
                {
                    $lev = levenshtein($term, $tag);
                    if ($lev <= 2)
                    {
                        $pts += 5 - $lev + $imp;
                    }
                    $lev = levenshtein(metaphone($term), metaphone($tag));
                    if ($lev < 2)
                    {
                        $pts += 5 - $lev + $imp;
                    }
                }
            }
            $imp--;
            unset($tag);
        }
        unset($term);
        if ($pts > 0)
        {
            $results[] = ['id' => $sw_id, 'title' => $entry['trs'][$entry_tr]['title'], 'cat' => $entry['cat'], 'desc' => $entry['trs'][$entry_tr]['desc'], 'hits' => $entry['hits'], 'dl' => $entry['dl'], 'pts' => $pts];
        }
    }
    // remove the first occurence of v in a
    /**
     * @return mixed[]
     */
    function array_remove($a, $v): array
    {
        $r = [];
        $o = false;
        foreach ($a as &$k)
        {
            if ($k !== $v || $o)
            {
                $r[] = $k;
            }
            else
            {
                $o = true;
            }
        }
        unset($k);
        return $r;
    }
    $btime = microtime(true) - $atime;
    if ($results === [])
    {
        echo '<span id="log">'.tr($tr, 'noresult', ['terms' => '<span class="log_quote">'.htmlentities((string) $searchterms).'</span>']).'</span>';
    }
    else
    {
        echo '<p id="timelog">'.tr($tr, 'found', ['count' => count($results),'time' => numberlocale(intval($btime * 1000000) / 1000)]).'</p>';
    }
    while ($results !== [])
    {
        $max = ['pts' => 0];
        foreach ($results as &$rs)
        {
            if ($rs['pts'] > $max['pts'])
            {
                $max = $rs;
            }
        }
        unset($rs);
        $results = array_remove($results, $max);
        echo '<div class="result"><a href="/a'.$max['id'].'"><h2 class="rs_title">'.$max['title'].'</h2></a><span class="rs_cat">('.$cats[$max['cat']].')</span>'. (isDev() ? '<span class="rs_pts">'.$max['pts'].'</span>' : '') .'<p class="rs_text">'.$max['desc'].'</p><span class="rs_meta">';
        echo tr($tr, 'result_hits', ['hits' => $max['hits'],'dl' => $max['dl']]).'</span></div>';
    }
}
?>
</main>
<?php require_once(__DIR__ . '/include/footer.php'); ?>
</body>
</html>
