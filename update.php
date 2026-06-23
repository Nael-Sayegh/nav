<?php
ob_start();
set_include_path($_SERVER['DOCUMENT_ROOT']);
require_once(__DIR__ . '/include/log.php');
require_once(__DIR__ . '/include/consts.php');
$title = 'Mise à jour du site';
$stats_page = 'update'; ?>
<!DOCTYPE html>
<html lang="fr">
<?php require_once(__DIR__ . '/include/header.php'); ?>
<body>
<?php require_once(__DIR__ . '/include/banner.php'); ?>
<main id="container">
<h1 id="contenu"><?php print $title; ?></h1>
<?php
if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT) !== false)
{
    $SQL = <<<SQL
        SELECT * FROM site_updates WHERE id=:id LIMIT 1
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':id' => $_GET['id']]);
    if ($data = $req->fetch())
    {
        $versionxx = substr((string) $data['name'], 1);
        echo '<h2>Version '.$versionxx.' (V'.$data['id'].')</h2><a href="/update.php">Liste des versions</a><br>';
        $SQL2 = <<<SQL
            SELECT * FROM site_updates WHERE id<:id ORDER BY date DESC LIMIT 1
            SQL;
        $req2 = $bdd->prepare($SQL2);
        $req2->execute([':id' => $data['id']]);
        if ($data2 = $req2->fetch())
        {
            $versionxx2 = substr((string) $data2['name'], 1);
            echo '<a href="u'.$data2['id'].'">Version précédente&nbsp;: '.$versionxx2.' (V'.$data2['id'].')</a> ('.getFormattedDate($data2['date'], tr($tr0, 'fndatetime')).')<br>';
        }
        $SQL3 = <<<SQL
            SELECT * FROM site_updates WHERE id>:id ORDER BY date ASC LIMIT 1
            SQL;
        $req3 = $bdd->prepare($SQL3);
        $req3->execute([':id' => $data['id']]);
        if ($data3 = $req3->fetch())
        {
            $versionxx3 = substr((string) $data3['name'], 1);
            echo '<a href="u'.$data3['id'].'">Version suivante&nbsp;: '.$versionxx3.' (V'.$data3['id'].')</a> ('.date('d/m/Y H:i', $data3['date']).')<br>';
        }
        echo '<p>Par '.$data['authors'].' ('.getFormattedDate($data['date'], tr($tr0, 'fndatetime')).')</p>'.str_replace('{{site}}', $site_name, $data['text']);
        $codestat = json_decode((string) $data['codestat']);
        if (isset($codestat[0], $codestat[1], $codestat[2])     && $codestat[0] !== -1 && $codestat[1] !== -1 && $codestat[2] !== -1)
        {
            echo '<hr><p>À cette version, le code du site est composé de <strong>'.$codestat[0].'</strong> fichiers, <strong>'.$codestat[1].'</strong> lignes, soit <strong>'.$codestat[2].'</strong> octets ('.human_filesize($codestat[2]).'o).<br>Seuls les fichiers PHP, HTML, CSS, JS, XML et texte brut sont pris en compte. Les fichiers dont nous ne sommes pas les auteurs ne sont pas comptés (bibliothèques, outils), ni les fichiers dynamiques (caches générés automatiquement), ni les fichiers de traduction (ne contenant que du texte).</p>';
        }
    }
    else
    {
        header('Location: /update.php');
        exit();
    }
}
else
{
    if (isDev())
    { ?>
<h2><?= $site_name ?> 20.1</h2>
<p>Lors de la publication de <a href="/u135"><?= $site_name ?> 20.0</a> le 25 Mai 2025, plusieurs évolutions futures ont été annoncées. <?= $site_name ?> 20.1 introduit certaines d'entre elles.</p>
<ul>
<li>Nouveautés et changements
<ul>
<li></li>
</ul></li>
<li>Correctifs
<ul>
<li></li>
</ul></li>
</ul>
    <?php }
    $SQL = <<<SQL
        SELECT * FROM site_updates ORDER BY date DESC
        SQL;
    echo '<ul><li>Numéro de version [identifiant de version] (date)&nbsp;:</li>';
    foreach ($bdd->query($SQL) as $data)
    {
        $versionxx = substr((string) $data['name'], 1);
        echo '<li><a href="/u'.$data['id'].'"><b>'.$versionxx.'</b> [V'.$data['id'].']</a> ('.getFormattedDate($data['date'], tr($tr0, 'fndatetime')).')</li>';
    }
    echo '</ul>';
    $req->closeCursor();
}
?>
</main>
<?php require_once(__DIR__ . '/include/footer.php');
ob_end_flush(); ?>
</body>
</html>
