<?php $logonly = true;
$adminonly = true;
$justna = true;
$titlePAdm = 'Modification d\'un article';
require_once($_SERVER['DOCUMENT_ROOT'].'/include/log.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/include/consts.php');
require_once $_SERVER['DOCUMENT_ROOT'].'/include/lib/MDConverter.php';
requireAdminRight('manage_content');
require_once($_SERVER['DOCUMENT_ROOT'].'/include/package_managers.php');
$time = time();
$addfile_hash = '';
$addfile_path = '';
// listing categories
$SQL = <<<SQL
    SELECT * FROM softwares_categories
    SQL;
$cat = [];
foreach ($bdd->query($SQL) as $data)
{
    $cat[$data['id']] = $data['name'];
}

$sw_mode = null;
$sw_id = null;
if (isset($_GET['id']))
{
    $sw_id = $_GET['id'];
    $sw_mode = 1;
}
elseif (isset($_GET['listfiles']))
{
    $sw_id = $_GET['listfiles'];
    $sw_mode = 2;
}
elseif (isset($_GET['addfile']))
{
    $sw_id = $_GET['addfile'];
    $sw_mode = 3;
}

if ((isset($_GET['token']) && $_GET['token'] === $login['token']) || (isset($_POST['token']) && $_POST['token'] === $login['token']))
{
    if (isset($_GET['mod']) && isset($_POST['name']) && isset($_POST['category']))
    {
        $mod_keywords = $_POST['keywords'] ?? '';
        $mod_description = $_POST['description'] ?? '';
        $mod_text = $_POST['text'] ?? '';
        $mod_website = $_POST['website'] ?? '';
        $SQl = <<<SQL
            UPDATE softwares SET name=:name, category=:cat, date=:date, description=:desc, text=:text, keywords=:keywords, website=:website, author=:author WHERE id=:id
            SQL;
        $req = $bdd->prepare($SQL);
        $req->execute([':name' => $_POST['name'], ':cat' => $_POST['category'], ':date' => $time, ':desc' => $mod_description, ':text' => $mod_text, ':keywords' => $mod_keywords, ':website' => $mod_website, ':author' => $admin_name, ':id' => $_GET['mod']]);
        header('Location: sw_mod.php?list='.$_POST['category']);
        include($_SERVER['DOCUMENT_ROOT'].'/tasks/history_cache.php');
        exit();
    }
    if (isset($_POST['rsw']))
    {
        $SQLCat = <<<SQL
            SELECT category FROM softwares WHERE id=:sw_id
            SQL;
        $reqCat = $bdd->prepare($SQLCat);
        $reqCat->execute(['sw_id' => $_POST['rsw']]);
        $rowCat = $reqCat->fetch();
        $catId = $rowCat['category'] ?? null;
        $SQLFiles = <<<SQL
            SELECT hash FROM softwares_files WHERE sw_id = :sw_id
            SQL;
        $reqFiles = $bdd->prepare($SQLFiles);
        $reqFiles->execute(['sw_id' => $_POST['rsw']]);
        while ($file = $reqFiles->fetch())
        {
            $path = $_SERVER['DOCUMENT_ROOT'].'/files/'.$file['hash'];
            if (is_file($path))
            {
                unlink($path);
            }
        }
        try
        {
            $bdd->beginTransaction();
            $tables = [
                'softwares_comments',
                'softwares_files',
                'softwares_mirrors',
                'softwares_packages',
                'softwares_tr',
            ];
            foreach ($tables as $tbl)
            {
                $SQL = <<<SQL
                    DELETE FROM {$tbl} WHERE sw_id = :sw_id
                    SQL;
                $req = $bdd->prepare($SQL);
                $req->execute(['sw_id' => $_POST['rsw']]);
            }
            $SQL = <<<SQL
                DELETE FROM softwares WHERE id = :sw_id
                SQL;
            $req = $bdd->prepare($SQL);
            $req->execute(['sw_id' => $_POST['rsw']]);
            $bdd->commit();
        }
        catch (PDOException $e)
        {
            $bdd->rollBack();
            die('Échec de la suppression : '.$e->getMessage());
        }
        $location = 'sw_mod.php' . (isset($catId) ? '?list='.$catId : '');
        header('Location: ' . $location);
        include($_SERVER['DOCUMENT_ROOT'].'/tasks/history_cache.php');
        exit();
    }
    if (isset($_GET['modf2']))
    {
        $SQL = <<<SQL
            SELECT * FROM softwares_files WHERE id=:id LIMIT 1
            SQL;
        $req = $bdd->prepare($SQL);
        $req->execute([':id' => $_GET['modf2']]);
        if ($data = $req->fetch())
        {
            $nofile = true;
            if (isset($_POST['method']) && !empty($_POST['method']))
            {
                $ok = false;
                $file = $_SERVER['DOCUMENT_ROOT'].'/files/'.$data['hash'];
                $filename = null;
                $filesize = null;
                $filetype = null;
                switch ($_POST['method'])
                {
                    case 'form':
                        if (isset($_FILES['file']) && $_FILES['file'] > 0 && $_FILES['file']['size'] <= 2147483648 && !empty($_FILES['file']['name']))
                        {
                            unlink($file);
                            move_uploaded_file($_FILES['file']['tmp_name'], $file);
                            $filename = (isset($_POST['overwrite_name']) && $_POST['overwrite_name'] === 'on') ? $_FILES['file']['name'] : $_POST['name'];
                            $filesize = $_FILES['file']['size'];
                            $filetype = $_FILES['file']['type'];
                            $ok = true;
                            $nofile = false;
                        }
                        elseif (isset($_POST['name']) && !empty($_POST['name']))
                        {
                            $ok = true;
                            $nofile = true;
                        }
                        break;
                    case 'url':
                        if (isset($_POST['url']) && !empty($_POST['url']) && isset($_POST['name']) && !empty($_POST['name']))
                        {
                            $stream = fopen($_POST['url'], 'r');
                            file_put_contents($file, $stream);
                            fclose($stream);
                            $filename = $_POST['name'];
                            $filesize = filesize($file);
                            $filetype = mime_content_type($file);
                            if ($filetype === false)
                            {
                                $filetype = 'application/octet-stream';
                            }
                            $ok = true;
                            $nofile = false;
                        }
                        break;
                }
                if (!$nofile)
                {
                    if ($ok)
                    {
                        $SQL = <<<SQL
                            UPDATE softwares_files SET name=:name, filetype=:type, title=:title, date=:date, filesize=:size, label=:lbl, md5=:md, sha1=:sha, hits=:hits, arch=:arch, platform=:plat WHERE id=:id
                            SQL;
                        $req = $bdd->prepare($SQL);
                        $req->execute([':name' => $filename, ':type' => $filetype, ':title' => $_POST['title'], ':date' => time(), ':size' => $filesize, ':lbl' => $_POST['label'], ':md' => md5_file($file), ':sha' => sha1_file($file), ':hits' => 0, ':arch' => $_POST['arch'], ':plat' => $_POST['platform'], ':id' => $_GET['modf2']]);
                    }
                    else
                    {
                        die('erreur');
                    }
                }
            }
            if ($nofile)
            {
                $SQL = <<<SQL
                    UPDATE softwares_files SET name=:name, title=:title, label=:lbl, date=:date, arch=:arch, platform=:plat WHERE id=:id
                    SQL;
                $req = $bdd->prepare($SQL);
                $req->execute([':name' => $_POST['name'], ':title' => $_POST['title'], ':lbl' => $_POST['label'], ':date' => time(), ':arch' => $_POST['arch'], ':plat' => $_POST['platform'], ':id' => $_GET['modf2']]);
            }

            header('Location: sw_mod.php?listfiles='.$data['sw_id']);
            $SQL = <<<SQL
                UPDATE softwares SET date=:date, author=:author WHERE id=:id
                SQL;
            $req = $bdd->prepare($SQL);
            $req->execute([':date' => time(), ':author' => $admin_name, ':id' => $data['sw_id']]);
            include($_SERVER['DOCUMENT_ROOT'].'/tasks/history_cache.php');

            if (isset($_POST['social']) && $_POST['social'] === 'on')
            {
                $SQL = <<<SQL
                    SELECT * FROM softwares_files ORDER BY date DESC LIMIT 1
                    SQL;
                $req = $bdd->query($SQL);
                if ($data = $req->fetch())
                {
                    $somsg = $_POST['title'].' : '.SITE_URL.'/dl/'.(!empty($_POST['label']) ? $_POST['label'] : $data['id']).' '.SITE_URL.'/a'.$data['sw_id'].' '.$admin_name;
                    include_once($_SERVER['DOCUMENT_ROOT'].'/include/lib/Mastodon/mastodon_publisher.php');
                    send_mastodon($somsg);
                    require_once($_SERVER['DOCUMENT_ROOT'].'/include/lib/discord_publisher.php');
                    send_discord($somsg);
                }
            }
            exit();
        }
    }
    if (isset($_GET['modm2']))
    {
        $SQL = <<<SQL
            UPDATE softwares_mirrors SET title=:title, links=:links, label=:lbl, date=:date WHERE id=:id
            SQL;
        $req = $bdd->prepare($SQL);
        $req->execute([':title' => $_POST['title'], ':links' => $_POST['urls'], ':lbl' => $_POST['label'], ':date' => time(), ':id' => $_GET['modm2']]);
        header('Location: sw_mod.php?listfiles='.$_GET['modm2']);
        include($_SERVER['DOCUMENT_ROOT'].'/tasks/history_cache.php');
        exit();
    }
    if (isset($_GET['vfile']))
    {
        $SQL = <<<SQL
            SELECT id, sw_id, title, label FROM softwares_files WHERE hash=:hash LIMIT 1
            SQL;
        $req1 = $bdd->prepare($SQL);
        $req1->execute([':hash' => $_GET['vfile']]);
        if ($data = $req1->fetch())
        {
            $file = $_SERVER['DOCUMENT_ROOT'].'/files/'.$_GET['vfile'];
            if (file_exists($file))
            {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $SQL = <<<SQL
                    UPDATE softwares_files SET filetype=:type, date=:date, filesize=:size, md5=:md, sha1=:sha WHERE id=:id
                    SQL;
                $req2 = $bdd->prepare($SQL);
                $req2->execute([':type' => finfo_file($finfo, $file), ':date' => time(), ':size' => filesize($file), ':md' => md5_file($file), ':sha' => sha1_file($file), ':id' => $data['id']]);
                finfo_close($finfo);
                if (isset($_GET['social']) && $_GET['social'] === 'on')
                {
                    $somsg = $data['title'].' : '.SITE_URL.'/dl/'.(!empty($data['label']) ? $data['label'] : $data['id']).' '.SITE_URL.'/a'.$data['sw_id'].' '.$admin_name;
                    include_once($_SERVER['DOCUMENT_ROOT'].'/include/lib/Mastodon/mastodon_publisher.php');
                    send_mastodon($somsg);
                    require_once($_SERVER['DOCUMENT_ROOT'].'/include/lib/discord_publisher.php');
                    send_discord($somsg);
                    include_once($_SERVER['DOCUMENT_ROOT'].'/include/lib/facebook/fb_publisher.php');
                    send_facebook($somsg);
                }
                include($_SERVER['DOCUMENT_ROOT'].'/tasks/history_cache.php');
                header('Location: sw_mod.php?listfiles='.$data['sw_id']);
                exit();
            }
            $addfile_hash = $_GET['vfile'];
            $addfile_path = $file;
        }
        else
        {
            header('Location: sw_mod.php');
            exit();
        }
    }
    if (isset($_GET['addmirror']) && isset($_POST['title']) && isset($_POST['urls']))
    {
        $SQL1 = <<<SQL
            SELECT id FROM softwares WHERE id=:id LIMIT 1
            SQL;
        $req1 = $bdd->prepare($SQL1);
        $req1->execute([':id' => $_GET['addmirror']]);
        if ($req1->fetch())
        {
            if (isset($_POST['label']) && !empty($_POST['label']))
            {
                $label = htmlspecialchars((string) $_POST['label']);
                $SQL = <<<SQL
                    UPDATE softwares_mirrors SET label="" WHERE label=:lbl
                    SQL;
                $req = $bdd->prepare($SQL);
                $req->execute([':lbl' => $label]);
            }
            else
            {
                $label = '';
            }
            $SQL2 = <<<SQL
                INSERT INTO softwares_mirrors (sw_id,links,title,date,label) VALUES (:swid,:links,:title,:date,:lbl)
                SQL;
            $req2 = $bdd->prepare($SQL2);
            $req2->execute([':swid' => $_GET['addmirror'], ':links' => $_POST['urls'], ':title' => $_POST['title'], ':date' => time(), ':lbl' => $label]);
        }
        $SQL = <<<SQL
            SELECT * FROM softwares_mirrors WHERE id=:id
            SQL;
        $req = $bdd->prepare($SQL);
        $req->execute([':id' => $_GET['addmirror']]);
        if ($data = $req->fetch())
        {
            header('Location: sw_mod.php?listfiles='.$_GET['addmirror']);
            exit();
        }
    }
    if (isset($_GET['addpackage']) && isset($_POST['manager']) && isset($_POST['name']))
    {
        $SQL1 = <<<SQL
            SELECT id FROM softwares WHERE id=:id LIMIT 1
            SQL;
        $req1 = $bdd->prepare($SQL1);
        $req1->execute([':id' => $_GET['addpackage']]);
        if ($data = $req1->fetch())
        {
            $comment = '';
            if (isset($_POST['comment']))
            {
                $comment = $_POST['comment'];
            }
            $SQL2 = <<<SQL
                INSERT INTO softwares_packages (sw_id,manager,name,comment) VALUES (:swid,:mgr,:name,:com)
                SQL;
            $req2 = $bdd->prepare($SQL2);
            $req2->execute([':swid' => $_GET['addpackage'], ':mgr' => $_POST['manager'], ':name' => $_POST['name'], ':com' => $comment]);
            header('Location: sw_mod.php?listfiles='.$data['id']);
            exit();
        }
    }
    if (isset($_GET['upload']) && isset($_POST['title']) && isset($_POST['method']))
    {
        $ok = false;
        $complete = false;
        $hash = null;
        $file = null;
        $filename = null;
        $filesize = null;
        $filetype = null;
        $SQL1 = <<<SQL
            SELECT id FROM softwares WHERE id=:id LIMIT 1
            SQL;
        $req1 = $bdd->prepare($SQL1);
        $req1->execute([':id' => $_GET['upload']]);
        if ($article = $req1->fetch())
        {
            $hash = base_convert(sha1($_POST['name'].time()), 16, 36);
            $file = $_SERVER['DOCUMENT_ROOT'].'/files/'.$hash;

            switch ($_POST['method'])
            {
                case 'form':
                    if (!file_exists($file))
                    {
                        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK && $_FILES['file']['size'] <= 2147483648)
                        {
                            move_uploaded_file($_FILES['file']['tmp_name'], $file);
                            $filename = (isset($_POST['name']) && !empty($_POST['name'])) ? $_POST['name'] : $_FILES['file']['name'];
                            $filesize = $_FILES['file']['size'];
                            $filetype = $_FILES['file']['type'];
                            $ok = true;
                            $complete = true;
                        }
                    }
                    break;
                case 'ext':
                    $addfile_hash = $hash;
                    $addfile_path = $file;
                    $addfile_so = isset($_POST['social']) && $_POST['social'] === 'on';
                    $ok = true;
                    break;
                case 'url':
                    if (isset($_POST['name']) && !empty($_POST['name']) && isset($_POST['url']) && !empty($_POST['url']))
                    {
                        $stream = fopen($_POST['url'], 'r');
                        file_put_contents($file, $stream);
                        fclose($stream);
                        $filename = $_POST['name'];
                        $filesize = filesize($file);
                        $filetype = mime_content_type($file);
                        if ($filetype === false)
                        {
                            $filetype = 'application/octet-stream';
                        }
                        $ok = true;
                        $complete = true;
                    }
                    break;
            }

            if ($ok)
            {
                $label = '';
                if (isset($_POST['label']) && !empty($_POST['label']))
                {
                    $label = htmlspecialchars((string) $_POST['label']);
                    $SQL = <<<SQL
                        UPDATE softwares_files SET label="" WHERE label=:lbl
                        SQL;
                    $req = $bdd->prepare($SQL);
                    $req->execute([':lbl' => $label]);
                }
                $SQL = <<<SQL
                    UPDATE softwares SET date=:date, author=:author WHERE id=:id
                    SQL;
                $req = $bdd->prepare($SQL);
                $req->execute([':date' => time(), ':author' => $admin_name, ':id' => $_GET['upload']]);

                if ($complete)
                {
                    $SQL = <<<SQL
                        INSERT INTO softwares_files (sw_id,name,hash,filetype,title,date,filesize,total_hits,hits,label,`md5`,`sha1`,`arch`,`platform`) VALUES(:swid,:name,:hash,:type,:title,:date,:size,0,0,:lbl,:md,:sha,:arch,:plat)
                        SQL;
                    $req = $bdd->prepare($SQL);
                    $req->execute([':swid' => $_GET['upload'], ':name' => $filename, ':hash' => $hash, ':type' => $filetype, ':title' => $_POST['title'], ':date' => time(), ':size' => $filesize, ':lbl' => $label, ':md' => md5_file($file), ':sha' => sha1_file($file), ':arch' => $_POST['arch'], ':plat' => $_POST['platform']]);
                    include($_SERVER['DOCUMENT_ROOT'].'/tasks/history_cache.php');

                    if (isset($_POST['social']) && $_POST['social'] === 'on')
                    {
                        $somsg = $_POST['title'].' :';
                        if (!empty($label))
                        {
                            $somsg .= ' '.SITE_URL.'/dl/'.$label;
                        }
                        $somsg .= ' '.SITE_URL.'/a'.$_GET['upload'].' '.$admin_name;
                        include_once($_SERVER['DOCUMENT_ROOT'].'/include/lib/Mastodon/mastodon_publisher.php');
                        send_mastodon($somsg);
                        require_once($_SERVER['DOCUMENT_ROOT'].'/include/lib/discord_publisher.php');
                        send_discord($somsg);
                        include_once($_SERVER['DOCUMENT_ROOT'].'/include/lib/facebook/fb_publisher.php');
                        send_facebook($somsg);
                    }

                    header('Location: sw_mod.php?listfiles='.$_GET['upload']);
                    exit();
                }
                else
                {
                    $SQL = <<<SQL
                        INSERT INTO softwares_files (sw_id,name,hash,title,label) VALUES(:swid,:name,:hash,:title,:label)
                        SQL;
                    $req = $bdd->prepare($SQL);
                    $req->execute([':swid' => $_GET['upload'], ':name' => $_POST['name'], ':hash' => $hash, ':title' => $_POST['title'], ':label' => $label]);
                }
            }
        }
    }

    if (isset($_GET['rfiles']))
    {
        $SQL1 = <<<SQL
            SELECT id, hash FROM softwares_files WHERE sw_id=:swid
            SQL;
        $req1 = $bdd->prepare($SQL1);
        $req1->execute([':swid' => $_GET['rfiles']]);
        while ($data = $req1->fetch())
        {
            if (isset($_GET['rfile'.$data['id']]))
            {
                $SQL2 = <<<SQL
                    DELETE FROM softwares_files WHERE id=:id
                    SQL;
                $req2 = $bdd->prepare($SQL2);
                $req2->execute([':id' => $data['id']]);
                unlink($_SERVER['DOCUMENT_ROOT'].'/files/'.$data['hash']);
            }
        }
        $SQL1 = <<<SQL
            SELECT id FROM softwares_mirrors WHERE sw_id=:swid
            SQL;
        $req1 = $bdd->prepare($SQL1);
        $req1->execute([':swid' => $_GET['rfiles']]);
        while ($data = $req1->fetch())
        {
            if (isset($_GET['rmir'.$data['id']]))
            {
                $SQL2 = <<<SQL
                    DELETE FROM softwares_mirrors WHERE id=:id
                    SQL;
                $req2 = $bdd->prepare($SQL2);
                $req2->execute([':id' => $data['id']]);
            }
        }
        $SQL1 = <<<SQL
            SELECT id FROM softwares_packages WHERE sw_id=:swid
            SQL;
        $req1 = $bdd->prepare($SQL1);
        $req1->execute([':swid' => $_GET['rfiles']]);
        while ($data = $req1->fetch())
        {
            if (isset($_GET['rpack'.$data['id']]))
            {
                $SQL2 = <<<SQL
                    DELETE FROM softwares_packages WHERE id=:id
                    SQL;
                $req2 = $bdd->prepare($SQL2);
                $req2->execute([':id' => $data['id']]);
            }
        }
        $req1->closeCursor();
        header('Location: sw_mod.php?addfile='.$_GET['rfiles']);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title><?php
if ($sw_id !== null && $sw_mode !== null)
{
    $SQL = <<<SQL
        SELECT name FROM softwares WHERE id=:id LIMIT 1
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':id' => $sw_id]);
    if ($sw_mode === 1)
    {
        echo 'Modifier ';
        $titlePAdm = 'Modifier ';
    }
    if ($sw_mode === 2)
    {
        echo 'Fichiers de ';
        $titlePAdm = 'Fichiers de ';
    }
    if ($sw_mode === 3)
    {
        echo 'Nouveau fichier à ';
        $titlePAdm = 'Nouveau fichier à ';
    }
    if ($data = $req->fetch())
    {
        echo $data['name'];
        $titlePAdm .= $data['name'];
    }
    else
    {
        echo 'un article';
        $titlePAdm .= 'un article';
    }
}
else
{
    echo 'Modifier un article';
}
?> &#8211; admin <?php print $site_name; ?></title>
<?php print $admin_css_path; ?>
<style>
    .upload-progress
    {
        display: none;
        width: 100%;
        margin-top: 1em;
    }
    button:disabled + .upload-progress
    {
        display: block;
    }
</style>
<script type="text/javascript" src="/scripts/default.js"></script>
</head>
<body>
<?php require_once('include/banner.php');
if (empty($_GET))
{
    echo '<ul title="Lister les articles de&nbsp;:">';
    $SQL2 = <<<SQL
        SELECT * FROM softwares_categories ORDER BY name ASC
        SQL;
    foreach ($bdd->query($SQL2) as $data2)
    {
        echo '<li><a href="sw_mod.php?list='.$data2['id'].'">'.$data2['name'].'</a></li>';
    }
    echo '</ul>';
}
else
{
    echo '<a href="sw_mod.php">Retourner à la liste des catégories</a>';
}
if ($addfile_hash !== '' && $addfile_path !== '')
{
    echo '<p>L\'ajout du fichier n\'est pas terminé.<br>Veuillez envoyer le fichier à cet emplacement&nbsp;:<br><strong>'.$addfile_path.'</strong><br>Son nom doit être <br><em>'.$addfile_hash.'</em><br> sans extension.<br>Une fois ceci fait, suivez ce lien&nbsp;:<br><a href="?vfile='.$addfile_hash.(($addfile_so === true) ? '&social=on' : '').'">Vérifier le fichier</a></p>';
}

if (isset($_GET['list'])): ?>
<div id="js-sort-container-sw" hidden style="margin:1em 0;">
<label for="js_sort_sw">Trier les articles&nbsp;:</label>
<select id="js_sort_sw">
<option value="date">Par date</option>
<option value="hits">Par nombre de visites</option>
<option value="name">Par ordre alphabétique</option>
</select>
</div>
<noscript>
<p>Activez JavaScript pour trier les articles</p>
</noscript>
<table border="1" id="software-list">
<thead><tr><th>Nom</th><th>Catégorie</th><th>Dernière modification</th></tr></thead>
<tbody>
<?php
// listing softwares
if (empty($_GET['list']))
{
    $SQL = <<<SQL
        SELECT * FROM softwares ORDER BY name ASC
        SQL;
    $req = $bdd->query($SQL);
}
else
{
    $SQL = <<<SQL
        SELECT * FROM softwares WHERE category=:cat ORDER BY name ASC
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':cat' => $_GET['list']]);
}
while ($data = $req->fetch())
{
    echo '<tr data-date="'.$data['date'].'" data-hits="'.$data['hits'].'" data-name="'.$data['name'].'">
<td><details><summary><h6>'.$data['name'].'</summary><ul role="menu"<li role="menuitem"><a href="?id='.$data['id'].'">Éditer</a></li><li role="menuitem"><a href="?listfiles='.$data['id'].'">Afficher les fichiers</a></li><li role="menuitem"><a href="translate.php?type=article&id='.$data['id'].'">Traductions</a></li><li role="menuitem">'.(($admin_name === $data['author']) ? '
<form method="post" action="sw_mod.php" style="display:inline" onsubmit="return confirm(\'Confirmez-vous la suppression de '.$data['name'].'&nbsp;?\')">
<input type="hidden" name="rsw" value="'.$data['id'].'">
<input type="hidden" name="token" value="'.$login['token'].'">
<button type="submit">Supprimer</button>
</form>' : 'La suppression est réservée au dernier auteur de l\'article').'</li></ul></details></td>
<td>'.$cat[$data['category']].'</td>
<td>'.date('d/m/Y H:i', $data['date']).' par '.$data['author'].'</td></tr>';
}
?>
</tbody>
</table><?php endif;
if (isset($_GET['listfiles']))
{
    $SQL1 = <<<SQL
        SELECT softwares_tr.name, softwares_tr.sw_id, softwares_tr.website, softwares.category, softwares.id
        FROM softwares
        LEFT JOIN softwares_tr ON softwares.id=softwares_tr.sw_id
        WHERE softwares.id=:id AND softwares_tr.lang=:lng
        ORDER BY softwares.date ASC
        SQL;
    $req1 = $bdd->prepare($SQL1);
    $req1->execute([':id' => $_GET['listfiles'], ':lng' => 'fr']);
    if ($data1 = $req1->fetch())
    { ?>
<p>Liste des fichiers de&nbsp;: <a href="?id=<?= $data1['id'] ?>"><?= $data1['name'] ?></a></p>
<ul><li><a href="?addfile=<?= $data1['id'] ?>">Ajouter un fichier<?php if (isDev())
{
    echo ' (Zone dev&nbsp;: lien à l\'usage des développeurs, pour le test uniquement)';
} ?></a></li><li><a href="?list=<?= $data1['category'] ?>"><?= $cat[$data1['category']] ?></a></li><li><a href="translate.php?type=article&id=<?= $data1['id'] ?>">Traductions</a></li><?php if ($data1['website'] !== '')
{
    echo '<li><a target="_blank" rel="noopener" href="'.$data1['website'].'">Site officiel</a></li>';
} ?></ul>
<div id="js-sort-container" hidden style="margin:1em 0;">
<label for="js_sort">Trier les fichiers&nbsp;:</label>
<select id="js_sort">
<option value="date">Par date</option>
<option value="hits">Par nombre de téléchargements</option>
<option value="name">Par nom</option>
<option value="title">Par titre</option>
<option value="filesize">Par taille</option>
</select>
</div>
<noscript>
  <p>Activez JavaScript pour trier les fichiers</p>
</noscript>
<form action="#" method="get">
<input type="hidden" name="token" value="<?= $login['token'] ?>">
<input type="hidden" name="rfiles" value="<?= $data1['id'] ?>">
<table border="1" id="sw_files">
<thead><tr><th>Nom</th><th>Titre</th><th>Label</th><th>Type</th><th>Modifié le</th><th>Taille</th><th>Supprimer</th></tr></thead>
<tbody>
<?php
        $SQL2 = <<<SQL
            SELECT * FROM softwares_files WHERE sw_id=:swid ORDER BY date ASC
            SQL;
        $req2 = $bdd->prepare($SQL2);
        $req2->execute([':swid' => $_GET['listfiles']]);
        while ($data2 = $req2->fetch())
        {
            echo '<tr data-date="'.$data2['date'].'" data-hits="'.$data2['hits'].'" data-filesize="'.$data2['filesize'].'" data-title="'.htmlspecialchars((string) $data2['title']).'" data-name="'.htmlspecialchars((string) $data2['name']).'"><td><a href="?modf='.$data2['id'].'">'.$data2['name'].'</a></td><td>'.$data2['title'].'</td><td><a href="/dl/'.$data2['label'].'">'.$data2['label'].'</a></td><td>'.$data2['filetype'].'</td><td>'.date('d/m/Y H:i', $data2['date']).'</td><td>'.human_filesize($data2['filesize']).'o</td><td><input type="checkbox" name="rfile'.$data2['id'].'" autocomplete="off"></td></tr>';
        } $req2->closeCursor(); ?></tbody>
</table>
<table border="1">
<thead><tr><th>Titre</th><th>Adresses</th><th>Label</th><th>Modifié le</th><th>Supprimer</th></tr></thead>
<tbody>
<?php
        $SQL2 = <<<SQL
            SELECT * FROM softwares_mirrors WHERE sw_id=:swid ORDER BY date ASC
            SQL;
        $req2 = $bdd->prepare($SQL2);
        $req2->execute([':swid' => $_GET['listfiles']]);
        while ($data2 = $req2->fetch())
        {
            echo '<tr><td><a href="?modm='.$data2['id'].'">'.$data2['title'].'</a></td><td><textarea name="lmir'.$data2['id'].'" readonly>'.htmlentities((string) $data2['links']).'</textarea></td><td><a href="/r.php?m&p='.$data2['label'].'">'.$data2['label'].'</a></td><td>'.date('d/m/Y H:i', $data2['date']).'</td><td><input type="checkbox" name="rmir'.$data2['id'].'" autocomplete="off"></td></tr>';
        } $req2->closeCursor(); ?></tbody>
</table>
<table border="1">
<thead><tr><th>Gestionnaire</th><th>Paquet</th><th>Commentaire</th><th>Supprimer</th></tr></thead>
<tbody>
<?php
        $SQL2 = <<<SQL
            SELECT * FROM softwares_packages WHERE sw_id=:swid
            SQL;
        $req2 = $bdd->prepare($SQL2);
        $req2->execute([':swid' => $_GET['listfiles']]);
        while ($data2 = $req2->fetch())
        {
            echo '<tr><td>'.$PACKAGE_MANAGERS[$data2['manager']]['name'].'</td><td>'.$data2['name'].'</td><td><textarea readonly>'.htmlentities((string) $data2['comment']).'</textarea></td><td><input type="checkbox" name="rpack'.$data2['id'].'" autocomplete="off"></td></tr>';
        } $req2->closeCursor(); ?></tbody>
</table>
<input type="submit" onclick="return confirm('Faut-il vraiment supprimer les fichiers sélectionnés&#8239;?')" value="Supprimer"/>
</form>
<?php }
    $req1->closeCursor();
}

if (isset($_GET['id']))
{
    $SQL = <<<SQL
        SELECT * FROM softwares WHERE id=:id
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':id' => $_GET['id']]);
    if ($data = $req->fetch())
    {
        ?>
<a href="?list=<?= $data['category'] ?>"><?= $cat[$data['category']] ?></a><br>
<a href="?listfiles=<?= $data['id'] ?>">Lister les fichiers</a><br>
<a href="translate.php?type=article&id=<?= $data['id'] ?>">Traductions</a><br><br>
<form action="?mod=<?= $_GET['id'] ?>" method="post">
<input type="hidden" name="token" value="<?= $login['token'] ?>">
<label for="f_mod_name">Nom&nbsp;:</label><input type="text" name="name" value="<?= $data['name'] ?>" id="f_mod_name" maxlength="255" required><br>
<label for="f_mod_category">Catégorie&nbsp;:</label><select name="category" id="f_mod_category"><?php
        $SQL3 = <<<SQL
            SELECT * FROM softwares_categories ORDER BY name ASC
            SQL;
        foreach ($bdd->query($SQL3) as $dat2)
        {
            echo '<option value="'.$dat2['id'].'"';
            if ($dat2['id'] === $data['category'])
            {
                echo ' selected';
            }
            echo '>'.$dat2['name'].'</option>';
        }
        $rq2->closeCursor()
        ?></select><br>
<label for="f_mod_keywords">Mots clés&nbsp;:</label><input type="text" name="keywords" value="<?= $data['keywords'] ?>" id="f_mod_keywords" maxlength="255"><br>
<label for="f_mod_description">Description courte&nbsp;:</label><input type="text" name="description" value="<?= $data['description'] ?>" id="f_mod_description" maxlength="1024"><br>
<label for="f_website">Adresse du site officiel (facultatif)&nbsp;:</label><input type="url" name="website" value="<?= $data['website'] ?>" id="f_website" maxlength="255"><br>
<label for="f_mod_text">Texte long (HTML)&nbsp;:</label><br>
<textarea name="text" id="f_mod_text" maxlength="20000" rows="20" cols="500" onkeyup="close_confirm=true"><?php echo convertToMD($data['text']); ?></textarea><br>
<input type="submit" value="Modifier">
</form>
<script type="text/javascript">init_close_confirm();</script><?php }$req->closeCursor();
}
if (isset($_GET['addfile']))
{
    $SQL = <<<SQL
        SELECT * FROM softwares WHERE id=:id ORDER BY name ASC
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':id' => $_GET['addfile']]);
    if ($data = $req->fetch())
    { ?>
<p>Ajouter un fichier pour <a href="?listfiles=<?= $_GET['addfile'] ?>"><?= $data['name'] ?></a></p>
<form id="f_addfile_form" action="?upload=<?= $_GET['addfile'] ?>" method="post" enctype="multipart/form-data">
<input type="hidden" name="token" value="<?= $login['token'] ?>">

<fieldset><legend>Ajouter un fichier</legend>
<label for="f_addfile_title">Titre du fichier&nbsp;:</label>
<input type="text" name="title" id="f_addfile_title" placeholder="Toto Installateur Windows" required><br>

<p>Si le fichier fait plus de 2Go ou si votre connexion est très lente, utilisez la méthode <em>Hors formulaire</em>. Vous nécessiterez généralement un accès FTP. Si le fichier est directement accessible via une URL, vous pouvez essayer la méthode <em>URL</em>. Dans ce dernier cas, utiliser un miroir doit être considéré.</p>

<label for="f_addfile_method">Méthode d'envoi&nbsp;:</label>
<select name="method" id="f_addfile_method" onchange="f_addfile_group_method()">
<option value="form" selected>Simple (&lt; 2Go)</option>
<option value="ext">Hors formulaire</option>
<option value="url">URL</option>
</select><br>

<div id="f_addfile_group_method_form" style="display: none;">
<label for="f_addfile_file">Fichier&nbsp;:</label>
<input type="file" name="file" id="f_addfile_file">
<noscript>Ne choisir un fichier que si la méthode <em>Simple</em> est choisie.</noscript>
<p>Si le nom souhaité est différent du nom actuel du fichier, remplissez le champ suivant. Sinon, laissez vide.</p>
</div>

<div id="f_addfile_group_method_url" style="display: none;">
<label for="f_addfile_url">URL&nbsp;:</label>
<input type="text" name="url" id="f_addfile_url">
<noscript>Ne choisir une URL que si la méthode <em>URL</em> est choisie.</noscript>
</div>

<label for="f_addfile_name">Nom du fichier&nbsp;:</label>
<input type="text" name="name" id="f_addfile_name" placeholder="toto-v1.2.3.installer.exe"><br>

<label for="f_addfile_label">Label&nbsp;:</label>
<input type="text" name="label" id="f_addfile_label" placeholder="toto-win-install"><br>

<label for="f_addfile_arch">Architecture&nbsp;:</label>
<select name="arch" id="f_addfile_arch">
<option value="" selected></option>
<?php
        foreach ($ARCHS as $arch_id => $arch_title)
        {
            echo '<option value="'.$arch_id.'">'.$arch_title.'</option>';
        }
        ?>
</select>

<label for="f_addfile_platform">Plateforme&nbsp;:</label>
<select name="platform" id="f_addfile_platform">
<option value="" selected></option>
<?php
        foreach ($PLATFORMS as $platform)
        {
            echo '<option value="'.$platform.'">'.$platform.'</option>';
        }
        ?>
</select>

<label for="f_addfile_social">Annoncer sur les médias sociaux&nbsp;:</label>
<input type="checkbox" name="social" id="f_addfile_social"<?php if (!isDev())
{
    echo ' checked';
} ?>><br>
<button type="submit">Ajouter</button>
<progress class="upload-progress" id="upload-progress"></progress>

<script>
function f_addfile_group_method() {
var val = document.getElementById("f_addfile_method").value;
switch(val) {
case "form":
document.getElementById("f_addfile_group_method_form").style = "display: block;";
document.getElementById("f_addfile_file").required = true;
document.getElementById("f_addfile_group_method_url").style = "display: none;";
document.getElementById("f_addfile_url").required = false;
document.getElementById("f_addfile_name").required = false;
break;
case "ext":
document.getElementById("f_addfile_group_method_form").style = "display: none;";
document.getElementById("f_addfile_file").required = false;
document.getElementById("f_addfile_group_method_url").style = "display: none;";
document.getElementById("f_addfile_url").required = false;
document.getElementById("f_addfile_name").required = true;
break;
case "url":
document.getElementById("f_addfile_group_method_form").style = "display: none;";
document.getElementById("f_addfile_file").required = false;
document.getElementById("f_addfile_group_method_url").style = "display: block;";
document.getElementById("f_addfile_url").required = true;
document.getElementById("f_addfile_name").required = true;
break;
}
}
f_addfile_group_method();
document.getElementById('f_addfile_form')
    .addEventListener('submit', function(){
      document.getElementById('upload-progress').style.display = 'block';
    });
</script>
</fieldset>
</form>
<form action="?addmirror=<?= $_GET['addfile'] ?>" method="post">
<input type="hidden" name="token" value="<?= $login['token'] ?>">
<fieldset><legend>Ajouter un miroir</legend>
<label for="f_addmirror_title">Titre du fichier&nbsp;:</label>
<input type="text" name="title" id="f_addmirror_title"><br>
<label for="f_addmirror_urls">URLs des miroirs&nbsp;:</label><br>
<textarea name="urls" id="f_addmirror_urls" style="width: 100%;" onkeyup="close_confirm=true"></textarea>
<p>Exemple&nbsp;: [["ZettaScript","https://zettascript.org/fichier.tar.gz"],["CommentÇaMarche","https://commentcamarche.net/download/fichier"]]</p>
<label for="f_addmirror_label">Label&nbsp;:</label>
<input type="text" name="label" id="f_addmirror_label"><br>
<input type="submit" value="Ajouter">
</fieldset>
</form>
<form action="?addpackage=<?= $_GET['addfile'] ?>" method="post">
<input type="hidden" name="token" value="<?= $login['token'] ?>">
<fieldset><legend>Ajouter un paquet</legend>
<label for="f_addpackage_manager">Gestionnaire&nbsp;:</label>
<select name="manager" id="f_addpackage_manager">
<?php
foreach ($PACKAGE_MANAGERS as $manager_id => $manager_data)
{
    echo '<option value="'.$manager_id.'">'.$manager_data['name'].'</option>';
}
        ?>
</select><br/>
<label for="f_addpackage_name">Nom du paquet&nbsp;:</label>
<input type="text" name="name" id="f_addpackage_name"><br>
<label for="f_addpackage_comment">Commentaire&nbsp;:</label><br>
<textarea name="comment" id="f_addpackage_comment" style="width: 100%;" onkeyup="close_confirm=true"></textarea>
<input type="submit" value="Ajouter">
</fieldset>
</form>
<script type="text/javascript">init_close_confirm();</script>
<?php }$req->closeCursor();
}
if (isset($_GET['modf']))
{
    $SQL = <<<SQL
        SELECT * FROM softwares_files WHERE id=:id
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':id' => $_GET['modf']]);
    if ($data = $req->fetch())
    { ?>
<a href="?listfiles=<?= $data['sw_id'] ?>">Liste des fichiers de l'article</a>
<form id="f_modf_form" action="?modf2=<?= $data['id'] ?>" method="post" enctype="multipart/form-data" onsubmit="f_modf_submit(event)">
<input type="hidden" name="token" value="<?= $login['token'] ?>">
<h2>Modifier un fichier</h2>
<fieldset>
<legend>Métadonnées</legend>
<label for="f_modf_title">Titre du fichier&nbsp;:</label>
<input type="text" name="title" id="f_modf_title" value="<?= $data['title'] ?>" required><br>
<label for="f_modf_name">Nom du fichier&nbsp;:</label>
<input type="text" name="name" id="f_modf_name" value="<?= $data['name'] ?>" required><br>
<label for="f_modf_label">Label&nbsp;:</label>
<input type="text" name="label" id="f_modf_label" value="<?= $data['label'] ?>" maxlength="16" readonly=<?php (!empty($data['label']) ? true : false); ?>>
<?php if (!empty($data['label']))
{
    echo '<p>Le label de ce fichier est déjà renseigné, pour le modifier, supprimez ce fichier et ajoutez en un nouveau.</p>';
} ?>

<label for="f_modf_arch">Architecture&nbsp;:</label>
<select name="arch" id="f_modf_arch">
<option value=""<?php if (!in_array($data['arch'], $ARCHS))
{
    echo 'selected';
} ?>></option>
<?php
        foreach ($ARCHS as $arch_id => $arch_title)
        {
            echo '<option value="'.$arch_id.'"';
            if ($data['arch'] === $arch_id)
            {
                echo ' selected';
            } echo '>'.$arch_title.'</option>';
        }
        ?>
</select><br>

<label for="f_modf_platform">Plateforme&nbsp;:</label>
<select name="platform" id="f_modf_platform">
<option value=""<?php if (!in_array($data['platform'], $PLATFORMS))
{
    echo 'selected';
} ?>></option>
<?php
        foreach ($PLATFORMS as $platform)
        {
            echo '<option value="'.$platform.'"';
            if ($data['platform'] === $platform)
            {
                echo ' selected';
            } echo '>'.$platform.'</option>';
        }
        ?>
</select>
</fieldset>
<fieldset>
<legend>Remplacer le fichier</legend>

<label for="f_modf_method">Méthode d'envoi&nbsp;:</label>
<select name="method" id="f_modf_method" onchange="f_modf_group_method()">
<option value="">Ne pas remplacer</option>
<option value="form" selected>Simple (&lt; 2Go)</option>
<option value="url">URL</option>
</select><br>

<noscript>Laissez vides les champs suivants si <em>Ne pas remplacer</em> est choisi.</noscript>

<div id="f_modf_group_method_form">
<label for="f_modf_file">Fichier&nbsp;:</label>
<input type="file" name="file" id="f_modf_file">
<noscript>Ne choisir un fichier que si la méthode <em>Simple</em> est choisie.</noscript><br>
<label for="f_modf_overwrite_name">Utiliser le nom du nouveau fichier envoyé&nbsp;:</label>
<input type="checkbox" name="overwrite_name" id="f_modf_overwrite_name" checked>
</div>

<div id="f_modf_group_method_url">
<label for="f_modf_url">URL&nbsp;:</label>
<input type="text" name="url" id="f_modf_url">
<noscript>Ne choisir une URL que si la méthode <em>URL</em> est choisie.</noscript>
</div>
</fieldset>

<label for="f_modf_social">Annoncer sur les médias sociaux&nbsp;:</label>
<input type="checkbox" name="social" id="f_modf_social"<?php if (!isDev())
{
    echo ' checked';
} ?>><br>
<button type="submit">Modifier</button>
<progress class="upload-progress" id="upload-progress"></progress>

<script>
function f_modf_group_method() {
var val = document.getElementById("f_modf_method").value;
switch(val) {
case "":
document.getElementById("f_modf_group_method_form").style = "display: none;";
document.getElementById("f_modf_group_method_url").style = "display: none;";
document.getElementById("f_modf_url").required = false;
document.getElementById("f_modf_name").required = true;
break;
case "form":
document.getElementById("f_modf_group_method_form").style = "display: block;";
document.getElementById("f_modf_group_method_url").style = "display: none;";
document.getElementById("f_modf_url").required = false;
document.getElementById("f_modf_name").required = false;
break;
case "url":
document.getElementById("f_modf_group_method_form").style = "display: none;";
document.getElementById("f_modf_group_method_url").style = "display: block;";
document.getElementById("f_modf_url").required = true;
document.getElementById("f_modf_name").required = true;
break;
}
}
function f_modf_submit(e) {
if(document.getElementById("f_modf_method").value == "form" && document.getElementById("f_modf_name").value == "" && document.getElementById("f_modf_file").files.length == 0) {
alert("Les champs Nom et Fichier ne doivent pas tous être vides.");
e.preventDefault();
}
}
f_modf_group_method();
  document.getElementById('f_modf_form')
    .addEventListener('submit', function(){
      document.getElementById('upload-progress').style.display = 'block';
    });
</script>
</form>
<?php }$req->closeCursor();
}
if (isset($_GET['modm']))
{
    $SQL = <<<SQL
        SELECT * FROM softwares_mirrors WHERE id=:id LIMIT 1
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':id' => $_GET['modm']]);
    if ($data = $req->fetch())
    { ?>
<form action="?modm2=<?= $data['id'] ?>" method="post">
<input type="hidden" name="token" value="<?= $login['token'] ?>">
<h2>Modifier un miroir</h2>
<label for="f_modf_title">Titre du fichier&nbsp;:</label>
<input type="text" name="title" id="f_modm_title" value="<?= $data['title'] ?>"><br>
<label for="f_modm_urls">URLs des miroirs&nbsp;:</label><br>
<textarea name="urls" id="f_modm_urls"><?= htmlentities((string) $data['links']) ?></textarea><br>
<label for="f_modf_label">Label&nbsp;:</label>
<input type="text" name="label" id="f_modm_label" value="<?= $data['label'] ?>"><br>
<input type="submit" value="Modifier">
</form>
<?php }$req->closeCursor();
}
if (isset($_GET['listfiles']))
{ ?>
<script>
    document.addEventListener('DOMContentLoaded', function()
    {
        const sortContainer = document.getElementById('js-sort-container');
        if (sortContainer) sortContainer.hidden = false;
        const select  = document.getElementById('js_sort');
        const tbody   = document.querySelector('#sw_files tbody');
        const rows    = Array.from(tbody.querySelectorAll('tr'));
        function sortRows(criteria)
        {
            const sorted = rows.slice().sort((a, b) =>
            {
                let va = a.dataset[criteria], vb = b.dataset[criteria];
                if (['date','hits','filesize'].includes(criteria))
                {
                    va = parseInt(va, 10);
                    vb = parseInt(vb, 10);
                }
                else
                {
                    va = va.toLowerCase();
                    vb = vb.toLowerCase();
                }
                if (va < vb) return (['date','hits','filesize'].includes(criteria) ? 1 : -1);
                if (va > vb) return (['date','hits','filesize'].includes(criteria) ? -1 : 1);
                return 0;
            });
            tbody.innerHTML = '';
            sorted.forEach(tr => tbody.appendChild(tr));
        };
        sortRows(select.value);
        select.addEventListener('change', () =>
        {
            sortRows(select.value);
        });
    });
</script>
<?php }
elseif (isset($_GET['list']))
{ ?>
<script>
    document.addEventListener('DOMContentLoaded', function()
    {
        const sortContainer = document.getElementById('js-sort-container-sw');
        if (sortContainer) sortContainer.hidden = false;
        const select  = document.getElementById('js_sort_sw');
        const tbody   = document.querySelector('#software-list tbody');
        const rows    = Array.from(tbody.querySelectorAll('tr'));
        function sortRows(criteria)
        {
            const sorted = rows.slice().sort((a, b) =>
            {
                let va = a.dataset[criteria], vb = b.dataset[criteria];
                if (['date','hits'].includes(criteria))
                {
                    va = parseInt(va, 10);
                    vb = parseInt(vb, 10);
                }
                else
                {
                    va = va.toLowerCase();
                    vb = vb.toLowerCase();
                }
                if (va < vb) return (['date','hits'].includes(criteria) ? 1 : -1);
                if (va > vb) return (['date','hits'].includes(criteria) ? -1 : 1);
                return 0;
            });
            tbody.innerHTML = '';
            sorted.forEach(tr => tbody.appendChild(tr));
        };
        sortRows(select.value);
        select.addEventListener('change', () =>
        {
            sortRows(select.value);
        });
    });
</script>
<?php } ?>
</body>
</html>
