<?php
if (!isset($_GET['id']))
{
    header('Location: /');
    exit();
}
set_include_path($_SERVER['DOCUMENT_ROOT']);
require_once(__DIR__ . '/include/log.php');
require_once(__DIR__ . '/include/consts.php');
$SQL = <<<SQL
    SELECT * FROM softwares_categories WHERE id=:id
    SQL;
$req = $bdd->prepare($SQL);
$req->execute([':id' => $_GET['id']]);
$data = $req->fetch();
if (!$data)
{
    header('Location: /');
    exit();
}
$tr = load_tr($lang, 'cat');
$cat_id = $data['id'];
$title = str_replace('{{site}}', $site_name, $data['name']);
$cat_text = $data['text'];

$args['id'] = $cat_id;
$stats_page = 'cat'; ?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<?php require_once(__DIR__ . '/include/header.php'); ?>
<body>
<?php require_once(__DIR__ . '/include/banner.php'); ?>
<main id="container">
<h1 id="contenu"><?php print $title; ?></h1>
<?= str_replace('{{site}}', $site_name, $cat_text) ?>
<div id="software-list">
<?php
$entries = [];
$SQL = <<<SQL
    SELECT softwares_tr.id, softwares_tr.lang, softwares_tr.name, softwares_tr.description, softwares_tr.sw_id, softwares.hits, softwares.downloads, softwares.date
    FROM softwares
    LEFT JOIN softwares_tr ON softwares.id=softwares_tr.sw_id
    WHERE softwares.category=:sw_cat AND softwares_tr.published=true
    ORDER BY softwares.date DESC
    SQL;
$req = $bdd->prepare($SQL);
$req->execute([':sw_cat' => $cat_id]);
while ($data = $req->fetch())
{
    if (!isset($entries[$data['sw_id']]))
    {
        $entries[$data['sw_id']] = ['hits' => $data['hits'], 'dl' => $data['downloads'], 'date' => $data['date'], 'trs' => []];
    }
    $entries[$data['sw_id']]['trs'][$data['lang']] = ['id' => $data['id'], 'title' => $data['name'], 'desc' => $data['description']];
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

    printf(
        '<div class="software" data-date="%d" data-hits="%d" data-name="%s">
        <span role="heading" aria-level="2">
        <a class="software_title" href="a%d">%s</a>
    </span>
    <p>%s<br>
    </p></div>',
        $entry['date'],
        $entry['hits'],
        htmlspecialchars(strtolower(str_replace('{{site}}', $site_name, $entry['trs'][$entry_tr]['title']))),
        $sw_id,
        str_replace('{{site}}', $site_name, $entry['trs'][$entry_tr]['title']),
        str_replace('{{site}}', $site_name, $entry['trs'][$entry_tr]['desc']),
    );
}
?>
</div>
</main>
<?php require_once(__DIR__ . '/include/footer.php'); ?>
</body>
</html>
