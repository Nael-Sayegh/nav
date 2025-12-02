<?php
$logonly    = true;
$adminonly  = true;
$justna     = true;
$titlePAdm  = 'Traductions';
require_once($_SERVER['DOCUMENT_ROOT'].'/include/log.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/include/consts.php');
requireAdminRight('manage_translations');

$conditions = [];
$params     = [];

if (!empty($_GET['article_lang']))
{
    $conditions[]        = 'softwares_tr.lang = :lng';
    $params[':lng']      = $_GET['article_lang'];
}

if (isset($_GET['article_todo']) && $_GET['article_todo'] !== '')
{
    $conditions[]         = 'softwares_tr.todo_level = :lvl';
    $params[':lvl']       = (int)$_GET['article_todo'];
}
elseif (!isset($_GET['article_todo']))
{
    $conditions[]         = 'softwares_tr.todo_level = :lvl';
    $params[':lvl']       = 0;
}

$whereClause = '';
if ($conditions !== [])
{
    $whereClause = 'WHERE ' . implode(' AND ', $conditions);
}

$sql = "
    SELECT
        softwares_tr.id AS id, softwares_tr.sw_id AS sw_id, softwares_tr.lang AS lang, softwares_tr.date AS date, softwares_tr.author AS author, softwares_tr.published AS published, softwares_tr.todo_level AS todo_level, softwares.name AS name
    FROM softwares_tr
    LEFT JOIN softwares
        ON softwares.id = softwares_tr.sw_id
    {$whereClause}
    ORDER BY softwares_tr.todo_level DESC
";

$req = $bdd->prepare($sql);
$req->execute($params);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Traductions – <?php print $site_name; ?></title>
    <?php print $admin_css_path; ?>
    <link rel="stylesheet" href="css/translate.css">
    <script src="/scripts/default.js"></script>
</head>
<body>
<?php require_once(__DIR__ . '/include/banner.php'); ?>

<h2 id="tr-articles">Articles</h2>
<form action="translate_todo.php#tr-articles" method="get">
    <!-- … votre formulaire de tri … -->
</form>

<table border="1">
    <thead>
        <tr>
            <th>Article</th><th>Langue</th><th>Dernier auteur</th>
            <th>Dernière modif</th><th>État</th><th>Publiée</th>
        </tr>
    </thead>
    <tbody>
    <?php while ($data = $req->fetch()): ?>
        <tr>
            <td>
                <a href="translate.php?type=article&amp;id=<?= $data['sw_id'] ?>">
                    <?= htmlspecialchars((string) $data['name']) ?>
                </a>
            </td>
            <td title="<?= $data['lang'] ?>">
                <?= htmlspecialchars((string) $langs[$data['lang']]) ?>
            </td>
            <td><?= htmlentities((string) $data['author']) ?></td>
            <td><?= date('d/m/Y H:i', $data['date']) ?></td>
            <td class="tr_todo<?= $data['todo_level'] ?>">
                <?= $tr_todo[$data['todo_level']] ?>
            </td>
            <td class="tr_published<?= $data['published'] ?>">
                <?= $data['published'] ? 'Public' : 'Privé' ?>
            </td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>

</body>
</html>
