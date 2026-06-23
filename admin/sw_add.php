<?php
$logonly = true;
$adminonly = true;
$justna = true;
$titlePAdm = "Ajout d'un article";
require_once($_SERVER['DOCUMENT_ROOT'].'/include/log.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/include/consts.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/include/sitemap.php');
requireAdminRight('manage_content');

$categories = [];
$SQL = <<<SQL
    SELECT * FROM softwares_categories ORDER BY name ASC
    SQL;
foreach ($bdd->query($SQL) as $data)
{
    $categories[$data['id']] = $data['name'];
}

if (isset($_GET['form'], $_POST['sname'], $_POST['category']))
{
    $sname = '';
    if (strlen((string) $_POST['sname']) < 256 && !empty($_POST['sname']))
    {
        $sname = $_POST['sname'];
    }
    else
    {
        $log .= '<li>Le nom interne de l\'article doit comporter entre 1 et 255 caractères.</li>';
    }

    $category = '';
    if (isset($categories[$_POST['category']]))
    {
        $category = $_POST['category'];
    }
    else
    {
        $log .= '<li>La catégorie choisie n\'existe pas.</li>';
    }

    $f_lang = '';
    if (isset($_POST['lang']) && !empty($_POST['lang']))
    {
        if (in_array($_POST['lang'], $langs_prio, true))
        {
            $f_lang = $_POST['lang'];
        }
        else
        {
            $log .= '<li>La langue choisie n\'est pas répertoriée.</li>';
        }

        $name = '';
        if (strlen((string) $_POST['name']) < 256 && !empty($_POST['name']))
        {
            $name = $_POST['name'];
        }
        else
        {
            $log .= '<li>Le nom traduit de l\'article doit comporter entre 1 et 255 caractères.</li>';
        }

        $keywords = '';
        if (strlen((string) $_POST['keywords']) < 511 && !empty($_POST['keywords']))
        {
            $keywords = $_POST['keywords'];
        }
        else
        {
            $log .= '<li>Les mots-clef doivent comporter entre 1 et 511 caractères.</li>';
        }

        $description = '';
        if (strlen((string) $_POST['description']) < 511 && !empty($_POST['description']))
        {
            $description = $_POST['description'];
        }
        else
        {
            $log .= '<li>La description courte doit comporter entre 1 et 511 caractères.</li>';
        }

        $website = '';
        if (strlen((string) $_POST['website']) < 256)
        {
            $website = $_POST['website'];
        }
        else
        {
            $log .= '<li>L\'adresse du site officiel doit comporter moins de 256 caractères.</li>';
        }

        $text = '';
        if (strlen((string) $_POST['text']) <= 20000 && !empty($_POST['text']))
        {
            $text = $_POST['text'];
        }
        else
        {
            $log .= '<li>Le texte de l\'article doit comporter entre 1 et 20&#8239;000 caractères.</li>';
        }

        $social = isset($_POST['social']) && $_POST['social'] === 'on';
        $published = empty($_POST['published']) ? 0 : 1;
    }

    if (empty($log))
    {
        $SQL = <<<SQL
            INSERT INTO softwares (name, category, date, author) VALUES (:name,:cat,:date,:author)
            SQL;
        $req = $bdd->prepare($SQL);
        $req->execute([':name' => $sname, ':cat' => $category, ':date' => time(), ':author' => $admin_name]);
        $lastid = $bdd->lastInsertId();

        if (!empty($f_lang))
        {
            $SQL = <<<SQL
                INSERT INTO softwares_tr (sw_id, lang, date, name, text, keywords, description, website, author, published, todo_level) VALUES (:swid,:lng,:date,:name,:text,:keywords,:desc,:website,:author,:published,0)
                SQL;
            $req = $bdd->prepare($SQL);
            $req->execute([':swid' => $lastid, ':lng' => $f_lang, ':date' => time(), ':name' => $name, ':text' => $text, ':keywords' => $keywords, ':desc' => $description, ':website' => $website, ':author' => $admin_name, ':published' => $published]);
            if ($published)
            {
                update_sitemap_url($site_url.'/a'.$lastid);
            }

            if ($social)
            {
                $somsg = 'Nouvel article : '.$name.' (A'.$lastid.').'."\n".SITE_URL.'/a'.$lastid."\n".$admin_name;
                require_once($_SERVER['DOCUMENT_ROOT'].'/include/lib/facebook/fb_publisher.php');
                send_facebook($somsg);
                require_once($_SERVER['DOCUMENT_ROOT'].'/include/lib/Mastodon/mastodon_publisher.php');
                send_mastodon($somsg);
                require_once($_SERVER['DOCUMENT_ROOT'].'/include/lib/discord_publisher.php');
                send_discord($somsg);
            }
            require_once($_SERVER['DOCUMENT_ROOT'].'/tasks/history_cache.php');
        }

        header('Location: sw_mod.php?listfiles='.$lastid);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Ajout d'un logiciel sur <?php print $site_name; ?></title>
<?php print $admin_css_path; ?>
<script type="text/javascript" src="/scripts/default.js"></script>
</head>
<body>
<?php require_once(__DIR__ . '/include/banner.php'); ?>
<div id="alertZone" role="alert" aria-live="assertive"></div>
<?php if (!empty($log)): ?>
<noscript>
<ul role="alert"><?= $log ?></ul>
</noscript>
<script>
    window.addEventListener('DOMContentLoaded', () =>
    {
        const alertZone = document.getElementById('alertZone');
        alertZone.innerHTML = '<ul><?= addslashes((string) $log) ?></ul>';
    });
</script>
<?php endif; ?>
<form action="?form" method="post">
<fieldset><legend>Structure</legend>
<label for="f_sname">Nom (en interne)&nbsp;:</label>
<input type="text" name="sname" id="f_sname"<?php if (isset($sname))
{
    echo ' value="'.htmlentities($sname).'"';
} ?> maxlength="255" required><br>
<label for="f_category">Catégorie&nbsp;:</label>
<select name="category" id="f_category"><?php foreach ($categories as $cid => $cname)
{
    echo '<option value="'.$cid.'"'.((isset($category) && $category === $cid) ? ' selected' : '').'>'.$cname.'</option>';
} ?></select>
</fieldset>
<fieldset><legend>Données de référence</legend>
<label for="f_lang">Langue&nbsp;:</label>
<select id="f_lang" name="lang" autocomplete="off"><option value=""<?php if (isset($f_lang) && $f_lang === '')
{
    echo ' selected';
} ?>>Ne pas créer de traduction initiale</option><?= langs_html_opts($f_lang ?? $lang) ?></select><br>
<label for="f_name">Nom&nbsp;:</label>
<input type="text" name="name" id="f_name"<?php if (isset($name))
{
    echo ' value="'.htmlentities($name).'"';
} ?> maxlength="255"><br>
<label for="f_keywords">Mots clés&nbsp;:</label>
<input type="text" name="keywords" id="f_keywords"<?php if (isset($keywords))
{
    echo ' value="'.htmlentities($keywords).'"';
} ?> maxlength="511"><br>
<label for="f_description">Description courte&nbsp;:</label>
<input type="text" name="description" id="f_description"<?php if (isset($description))
{
    echo ' value="'.htmlentities($description).'"';
} ?> maxlength="511"><br>
<label for="f_website">Adresse du site officiel (facultatif)&nbsp;:</label>
<input type="url" name="website" id="f_website"<?php if (isset($website))
{
    echo ' value="'.htmlentities($website).'"';
} ?> maxlength="255"><br>
<label for="f_text">Texte long (HTML)&nbsp;:</label><br>
<textarea name="text" id="f_text" maxlength="20000" style="width:100%;height:10em;" onkeyup="close_confirm=true"><?php if (isset($text))
{
    echo htmlentities($text);
} ?></textarea><br>
<p>Il est possible de modifier ces informations et de rajouter des liens et fichiers ultérieurement.</p>
<label for="f_so">Annoncer l'ajout sur les réseaux sociaux&nbsp;:</label>
<input type="checkbox" id="f_so" name="social"<?php if ((isset($social) && $social) || !isset($social))
{
    echo ' checked';
} ?>><br>
<label for="f_published">Publier&nbsp;:</label>
<input type="checkbox" id="f_published" name="published"<?php if ((isset($published) && $published) || !isset($published))
{
    echo ' checked';
} ?>>
</fieldset>
<input type="submit" value="Ajouter">
</form>
<script type="text/javascript">init_close_confirm();</script>
</body>
</html>
