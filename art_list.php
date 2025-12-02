<?php
set_include_path($_SERVER['DOCUMENT_ROOT']);
require_once(__DIR__ . '/include/log.php');
require_once(__DIR__ . '/include/consts.php');
$tr = load_tr($lang, 'art_list');
$title = tr($tr, 'title');
$stats_page = 'art-list';
$catMap = [];
$SQL = <<<SQL
    SELECT id, name FROM softwares_categories
    SQL;
foreach ($bdd->query($SQL) as $data)
{
    $catMap[$data['id']] = $data['name'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<?php require_once(__DIR__ . '/include/header.php'); ?>
<body>
<?php require_once(__DIR__ . '/include/banner.php'); ?>
<main id="container">
<h1 id="contenu"><?php print $title; ?></h1>
<form method="get">
<label for="f1_sort"><?= tr($tr, 'sort_label') ?></label>
<select name="sort" id="f1_sort">
<option value="id"><?= tr($tr, 'sort_article_id') ?></option>
<option value="nom"><?= tr($tr, 'sort_alpha_order') ?></option>
<option value="date"><?= tr($tr, 'sort_date') ?></option>
</select>
<input type="submit" value="<?= tr($tr, 'sort_btn') ?>" style="cursor:pointer;">
</form>
<ul>
<?php
if (isset($_GET['sort']))
{
    switch ($_GET['sort'])
    {
        case 'id': $order = 'id';
            break;
        case 'nom': $order = 'name';
            break;
        case 'date': $order = 'date DESC';
            break;
    }
}
else
{
    $order = 'id';
}
$SQL = <<<SQL
    SELECT softwares_tr.lang, softwares_tr.name, softwares_tr.sw_id, softwares.category
    FROM softwares
    LEFT JOIN softwares_tr ON softwares.id=softwares_tr.sw_id
    ORDER BY softwares.{$order}
    SQL;
foreach ($bdd->query($SQL) as $data)
{
    if (!isset($entries[$data['sw_id']]))
    {
        $entries[$data['sw_id']] = ['cat' => $data['category'], 'trs' => []];
    }
    $entries[$data['sw_id']]['trs'][$data['lang']] = ['title' => $data['name']];
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
    if (empty($entry_tr)) // Error: sw has no translations
    {continue;
    }

    echo '<li><a href="/a'.$sw_id.'" role="heading" aria-level="2">A'.$sw_id.'&nbsp;: '.str_replace('{{site}}', $site_name, $entry['trs'][$entry_tr]['title']).'</a> (<a href="/c'.$entry['cat'].'">'.$catMap[$entry['cat']].'</a>)</li>';
}
$req->closeCursor();
?>
</ul>
<p><b><?= tr($tr, 'nb_found', ['count' => count($entries)]) ?></p>
</main>
<?php require_once(__DIR__ . '/include/footer.php'); ?>
</body>
</html>
