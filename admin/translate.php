<?php
$logonly = true;
$adminonly = true;
$justna = true;
$titlePAdm = 'Traductions';
require_once($_SERVER['DOCUMENT_ROOT'].'/include/log.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/include/consts.php');
require_once $_SERVER['DOCUMENT_ROOT'].'/include/lib/MDConverter.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/include/sitemap.php';
requireAdminRight('manage_translations');
$tr_todo = [0 => 'Référence', 1 => 'OK', 2 => 'À vérifier', 3 => 'À modifier', 4 => 'À terminer'];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Traductions &#8211; <?php print $site_name; ?></title>
<?php print $admin_css_path; ?>
<link rel="stylesheet" href="css/translate.css">
<script type="text/javascript" src="/scripts/default.js"></script>
<script type="text/javascript" src="/scripts/jquery.js"></script>
<script type="text/javascript" src="js/translate.js"></script>
</head>
<body>
<?php require_once(__DIR__ . '/include/banner.php'); ?>
<a href="translate_todo.php">Toutes les traductions</a><br>

<?php
if (isset($_GET['type']) && ($_GET['type'] === 'article' && isset($_GET['id'])))
{
    $SQL = <<<SQL
            SELECT softwares.*, softwares_categories.name AS category_name
            FROM softwares
            LEFT JOIN softwares_categories ON softwares_categories.id=softwares.category
            WHERE softwares.id=:id LIMIT 1
            SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':id' => $_GET['id']]);
    if ($data = $req->fetch())
    {
        // form: back
        if (isset($_GET['a']) && ((isset($_GET['token']) && $_GET['token'] === $login['token']) || (isset($_POST['token']) && $_POST['token'] === $login['token'])))
        {
            if ($_GET['a'] === 'rm' && isset($_GET['tr']))
            {
                $SQL2 = <<<SQL
                        DELETE FROM softwares_tr WHERE id=:id AND sw_id=:swid
                        SQL;
                $req2 = $bdd->prepare($SQL2);
                $req2->execute([':id' => $_GET['tr'], ':swid' => $data['id']]);
                header('Location: ?type=article&id='.$data['id']);
                exit();
            }
            if (($_GET['a'] === 'pub' || $_GET['a'] === 'priv') && isset($_GET['tr']))
            {
                $SQL2 = <<<SQL
                        UPDATE softwares_tr SET published=:pub WHERE id=:id AND sw_id=:swid
                        SQL;
                $req2 = $bdd->prepare($SQL2);
                $req2->execute([':pub' => ($_GET['a'] === 'pub'), ':id' => $_GET['tr'], ':swid' => $data['id']]);
                if ($_GET['a'] === 'pub')
                {
                    update_sitemap_url($site_url.'/a'.$data['id']);
                }
                else
                {
                    remove_sitemap_url($site_url.'/a'.$data['id']);
                }
                header('Location: ?type=article&id='.$data['id']);
                exit();
            }
            if ($_GET['a'] === 'new2')
            {
                $tr_lang = '';
                if (isset($_POST['lang']) && in_array($_POST['lang'], $langs_prio, true))
                {
                    $tr_lang = $_POST['lang'];
                }
                $tr_name = '';
                if (isset($_POST['tr_name']) && strlen((string) $_POST['tr_name']) <= 255)
                {
                    $tr_name = $_POST['tr_name'];
                }
                $tr_text = '';
                if (isset($_POST['tr_text']) && strlen((string) $_POST['tr_text']) <= 65535)
                {
                    $tr_text = $_POST['tr_text'];
                }
                $tr_tags = '';
                if (isset($_POST['tr_tags']) && strlen((string) $_POST['tr_tags']) <= 512)
                {
                    $tr_tags = $_POST['tr_tags'];
                }
                $tr_description = '';
                if (isset($_POST['tr_description']) && strlen((string) $_POST['tr_description']) <= 512)
                {
                    $tr_description = $_POST['tr_description'];
                }
                $tr_website = '';
                if (isset($_POST['tr_website']) && strlen((string) $_POST['tr_website']) <= 255)
                {
                    $tr_website = $_POST['tr_website'];
                }
                $published = !empty($_POST['ref']);
                $todo_level = isset($_POST['ref']) ? 0 : 2;
                $SQL2 = <<<SQL
                        INSERT INTO softwares_tr (sw_id, lang, date, name, text, keywords, description, website, author, published, todo_level) VALUES (:swid,:lng,:date,:name,:text,:keywords,:desc,:website,:author,:pub,:lvl)
                        SQL;
                $req2 = $bdd->prepare($SQL2);
                $req2->execute([':swid' => $data['id'], ':lng' => $tr_lang, ':date' => time(), ':name' => $tr_name, ':text' => $tr_text, ':keywords' => $tr_tags, ':desc' => $tr_description, ':website' => $tr_website, ':author' => $admin_name, ':pub' => $published, ':lvl' => $todo_level]);
                if (isset($_POST['update_article_date']))
                {
                    $SQL2 = <<<SQL
                            UPDATE softwares SET date=:date, author=:author WHERE id=:id
                            SQL;
                    $req2 = $bdd->prepare($SQL2);
                    $req2->execute([':date' => time(), ':author' => $admin_name, ':id' => $data['id']]);
                }
                header('Location: ?type=article&id='.$data['id']);
                exit();
            }
            if ($_GET['a'] === 'edit2' && isset($_GET['tr']))
            {
                $tr_lang = '';
                if (isset($_POST['lang']) && in_array($_POST['lang'], $langs_prio, true))
                {
                    $tr_lang = $_POST['lang'];
                }
                $tr_name = '';
                if (isset($_POST['tr_name']) && strlen((string) $_POST['tr_name']) <= 255)
                {
                    $tr_name = $_POST['tr_name'];
                }
                $tr_text = '';
                if (isset($_POST['tr_text']) && strlen((string) $_POST['tr_text']) <= 65535)
                {
                    $tr_text = $_POST['tr_text'];
                }
                $tr_tags = '';
                if (isset($_POST['tr_tags']) && strlen((string) $_POST['tr_tags']) <= 512)
                {
                    $tr_tags = $_POST['tr_tags'];
                }
                $tr_description = '';
                if (isset($_POST['tr_description']) && strlen((string) $_POST['tr_description']) <= 512)
                {
                    $tr_description = $_POST['tr_description'];
                }
                $tr_website = '';
                if (isset($_POST['tr_website']) && strlen((string) $_POST['tr_website']) <= 255)
                {
                    $tr_website = $_POST['tr_website'];
                }
                $SQL2 = <<<SQL
                        UPDATE softwares_tr SET lang=:lng, date=:date, name=:name, text=:text, keywords=:keywords, description=:desc, website=:website, author=:author WHERE id=:id AND sw_id=:swid
                        SQL;
                $req2 = $bdd->prepare($SQL2);
                $req2->execute([':lng' => $tr_lang, ':date' => time(), ':name' => $tr_name, ':text' => $tr_text, ':keywords' => $tr_tags, ':desc' => $tr_description, ':website' => $tr_website, ':author' => $admin_name, ':id' => $_GET['tr'], ':swid' => $data['id']]);
                if (isset($_POST['update_article_date']))
                {
                    $SQL2 = <<<SQL
                            UPDATE softwares SET date=:date, author=:author WHERE id=:id
                            SQL;
                    $req2 = $bdd->prepare($SQL2);
                    $req2->execute([':date' => time(), ':author' => $admin_name, ':id' => $data['id']]);
                }
                header('Location: ?type=article&id='.$data['id']);
                exit();
            }
            if ($_GET['a'] === 'todo' && isset($_GET['tr_todo'], $_GET['s']))
            {
                foreach ($_GET['s'] as &$i)
                {
                    $SQL2 = <<<SQL
                            UPDATE softwares_tr SET todo_level=:lvl WHERE id=:id
                            SQL;
                    $req2 = $bdd->prepare($SQL2);
                    $req2->execute([':lvl' => $_GET['tr_todo'], ':id' => $i]);
                }
                header('Location: ?type=article&id='.$data['id']);
                exit();
            }
        }

        echo '<p><strong>Article</strong>&nbsp;: <a href="sw_mod.php?id='.$data['id'].'">'.htmlentities((string) $data['name']).'</a><br>Catégorie&nbsp;: <em>'.htmlentities((string) $data['category_name']).'</em><br>Dernier auteur&nbsp;: '.htmlentities((string) $data['author']).'</p>';

        // form: front
        if (isset($_GET['a']) && $_GET['a'] === 'new')
        {
            $model = false;
            if (isset($_GET['model']) && !empty($_GET['model']))
            {
                $SQL2 = <<<SQL
                        SELECT * FROM softwares_tr WHERE id=:id AND sw_id=:swid LIMIT 1
                        SQL;
                $req2 = $bdd->prepare($SQL2);
                $req2->execute([':id' => $_GET['model'], ':swid' => $data['id']]);
                $model = $req2->fetch();
            } ?>
<h2>Nouvelle traduction</h2>
<form method="post" action="?type=article&id=<?= $data['id'] ?>&a=new2">
<input type="hidden" name="token" value="<?= $login['token'] ?>">
<?php if (isset($_GET['ref']))
{
    echo '<input type="hidden" name="ref" value="1">';
} ?>
<label for="tr_sw_new_lang">Langue&nbsp;:</label>
<select id="tr_sw_new_lang" name="lang" autocomplete="off"><?= $langs_html_opts ?></select>
<table class="trtable">
<thead><tr><?php echo $model ? '<th>Modèle</th>' : ''; ?><th>Nouveau</th></tr></thead>
<tbody>
<tr>
<?php if ($model)
{ ?>
<td class="trform2"><label for="tr_sw_new_model_name">Titre modèle&nbsp;:</label><br><input type="text" id="tr_sw_new_model_name" readonly value="<?= htmlentities((string) $model['name']) ?>"></td>
<?php } ?>
<td class="trform<?php echo $model ? '2' : '1'; ?>"><label for="tr_sw_new_name">Titre nouveau&nbsp;:</label><br><input type="text" id="tr_sw_new_name" name="tr_name" maxlength="255" autocomplete="off"></td>
</tr>
<tr>
<?php if ($model)
{ ?>
<td class="trform2"><label for="tr_sw_new_model_text">Texte modèle&nbsp;:</label><br><textarea id="tr_sw_new_model_text" readonly><?= htmlentities((string) $model['text']) ?></textarea></td>
<?php } ?>
<td class="trform<?php echo $model ? '2' : '1'; ?>"><label for="tr_sw_new_text">Texte nouveau&nbsp;:</label><br><textarea id="tr_sw_new_text" name="tr_text" maxlength="35535" autocomplete="off" onkeyup="close_confirm=true"></textarea></td>
</tr>
<tr>
<?php if ($model)
{ ?>
<td class="trform2"><label for="tr_sw_new_model_tags">Mots-clefs modèle&nbsp;:</label><br><textarea id="tr_sw_new_model_tags" readonly><?= htmlentities((string) $model['keywords']) ?></textarea></td>
<?php } ?>
<td class="trform<?php echo $model ? '2' : '1'; ?>"><label for="tr_sw_new_tags">Mots-clefs nouveau&nbsp;:</label><br><textarea id="tr_sw_new_tags" name="tr_tags" maxlength="512" autocomplete="off"></textarea></td>
</tr>
<tr>
<?php if ($model)
{ ?>
<td class="trform2"><label for="tr_sw_new_model_description">Description modèle&nbsp;:</label><br><textarea id="tr_sw_new_model_description" readonly><?= htmlentities((string) $model['description']) ?></textarea></td>
<?php } ?>
<td class="trform<?php echo $model ? '2' : '1'; ?>"><label for="tr_sw_new_description">Description nouveau&nbsp;:</label><br><textarea id="tr_sw_new_description" name="tr_description" maxlength="512" autocomplete="off"></textarea></td>
</tr>
<tr>
<?php if ($model)
{ ?>
<td class="trform2"><label for="tr_sw_new_model_website">Site officiel modèle&nbsp;:</label><br><input type="text" id="tr_sw_new_model_website" value="<?= htmlentities((string) $model['website']) ?>" readonly></td>
<?php } ?>
<td class="trform<?php echo $model ? '2' : '1'; ?>"><label for="tr_sw_new_website">Site officiel nouveau&nbsp;:</label><br><input type="text" id="tr_sw_new_website" name="tr_website" maxlength="255" autocomplete="off"></td>
</tr>
</tbody>
</table>
<label for="tr_sw_new_uad">Mettre à jour la date de l'article</label>
<input type="checkbox" id="tr_sw_new_uad" name="update_article_date" autocomplete="off"<?php if (isset($_GET['ref']))
{
    echo 'checked';
} ?>><br>
<input type="submit" value="Envoyer">
</form>
<script type="text/javascript">init_close_confirm();</script>
<hr>
<?php
        }
        if (isset($_GET['edit']))
        {
            $SQL2 = <<<SQL
                    SELECT * FROM softwares_tr WHERE id=:id AND sw_id=:swid LIMIT 1
                    SQL;
            $req2 = $bdd->prepare($SQL2);
            $req2->execute([':id' => $_GET['edit'], ':swid' => $data['id']]);
            $tr_mod = $req2->fetch();
            $model = false;
            if (isset($_GET['model']) && !empty($_GET['model']))
            {
                $SQL2 = <<<SQL
                        SELECT * FROM softwares_tr WHERE id=:id AND sw_id=:swid LIMIT 1
                        SQL;
                $req2 = $bdd->prepare($SQL2);
                $req2->execute([':id' => $_GET['model'], ':swid' => $data['id']]);
                $model = $req2->fetch();
            } ?>
<h2>Modifier une traduction</h2>
<form method="post" action="?type=article&id=<?= $data['id'] ?>&a=edit2&tr=<?= $tr_mod['id'] ?>">
<input type="hidden" name="token" value="<?= $login['token'] ?>">
<label for="tr_sw_edit_lang">Langue&nbsp;:</label>
<select id="tr_sw_edit_lang" name="lang" autocomplete="off"><?= langs_html_opts($tr_mod['lang']) ?></select>
<table class="trtable">
<thead><tr><?php echo $model ? '<th>Modèle</th>' : ''; ?><th>En modification</th></tr></thead>
<tbody>
<tr>
<?php if ($model)
{ ?>
<td class="trform2"><label for="tr_sw_edit_model_name">Titre modèle&nbsp;:</label><br><input type="text" id="tr_sw_edit_model_name" readonly value="<?= htmlentities((string) $model['name']) ?>"></td>
<?php } ?>
<td class="trform<?php echo $model ? '2' : '1'; ?>"><label for="tr_sw_edit_name">Titre en modification&nbsp;:</label><br><input type="text" id="tr_sw_edit_name" name="tr_name" maxlength="255" autocomplete="off" value="<?= htmlentities((string) $tr_mod['name']) ?>"></td>
</tr>
<tr>
<?php if ($model)
{ ?>
<td class="trform2"><label for="tr_sw_edit_model_text">Texte modèle&nbsp;:</label><br><textarea id="tr_sw_edit_model_text" readonly><?= htmlentities((string) $model['text']) ?></textarea></td>
<?php } ?>
<td class="trform<?php echo $model ? '2' : '1'; ?>"><label for="tr_sw_edit_text">Texte en modification&nbsp;:</label><br><textarea id="tr_sw_edit_text" name="tr_text" autocomplete="off" maxlength="35535" onkeyup="close_confirm=true"><?php echo convertToMD(htmlentities((string) $tr_mod['text'])); ?></textarea></td>
</tr>
<tr>
<?php if ($model)
{ ?>
<td class="trform2"><label for="tr_sw_edit_model_tags">Mots-clefs modèle&nbsp;:</label><br><textarea id="tr_sw_edit_model_tags" readonly><?= htmlentities((string) $model['keywords']) ?></textarea></td>
<?php } ?>
<td class="trform<?php echo $model ? '2' : '1'; ?>"><label for="tr_sw_edit_tags">Mots-clefs en modification&nbsp;:</label><br><textarea id="tr_sw_edit_tags" name="tr_tags" maxlength="512" autocomplete="off"><?= htmlentities((string) $tr_mod['keywords']) ?></textarea></td>
</tr>
<tr>
<?php if ($model)
{ ?>
<td class="trform2"><label for="tr_sw_edit_model_description">Description modèle&nbsp;:</label><br><textarea id="tr_sw_edit_model_description" readonly><?= htmlentities((string) $model['description']) ?></textarea></td>
<?php } ?>
<td class="trform<?php echo $model ? '2' : '1'; ?>"><label for="tr_sw_edit_description">Description en modification&nbsp;:</label><br><textarea id="tr_sw_edit_description" name="tr_description" maxlength="512" autocomplete="off"><?= htmlentities((string) $tr_mod['description']) ?></textarea></td>
</tr>
<tr>
<?php if ($model)
{ ?>
<td class="trform2"><label for="tr_sw_edit_model_website">Site officiel modèle&nbsp;:</label><br><input type="text" id="tr_sw_edit_model_website" value="<?= htmlentities((string) $model['website']) ?>" readonly></td>
<?php } ?>
<td class="trform<?php echo $model ? '2' : '1'; ?>"><label for="tr_sw_edit_website">Site officiel en modification&nbsp;:</label><br><input type="text" id="tr_sw_edit_website" name="tr_website" value="<?= htmlentities((string) $tr_mod['website']) ?>" maxlength="255" autocomplete="off"></td>
</tr>
</tbody>
</table>
<label for="tr_sw_edit_uad">Mettre à jour la date de l'article</label>
<input type="checkbox" id="tr_sw_edit_uad" name="update_article_date" autocomplete="off"<?php if ($tr_mod['todo_level'] === 0)
{
    echo 'checked';
} ?>><br>
<input type="submit" value="Envoyer">
</form>
<script type="text/javascript">init_close_confirm();</script>
<hr>
<?php
        } ?>
<h2>Traductions</h2>
<form action="translate.php" method="get">
<input type="hidden" name="type" value="article">
<input type="hidden" name="id" value="<?= $data['id'] ?>">
<input type="hidden" name="token" value="<?= $login['token'] ?>">
<table border="1">
<thead><tr><th></th><th>Langue</th><th>Dernier auteur</th><th>Dernière modif</th><th>État</th><th>Publiée</th><th>Actions</th></tr></thead>
<tbody><?php
        $SQL2 = <<<SQL
                SELECT softwares_tr.*, languages.name AS language FROM softwares_tr
                LEFT JOIN languages ON languages.lang=softwares_tr.lang
                WHERE sw_id=:swid
                SQL;
        $req2 = $bdd->prepare($SQL2);
        $req2->execute([':swid' => $data['id']]);
        while ($data2 = $req2->fetch())
        {
            echo '<tr>
<td><input type="checkbox" name="s[]" value="'.$data2['id'].'" aria-label="Sélectionner '.$data2['language'].' (pour suppression)" title="Sélectionner"></td>
<td title="'.$data2['lang'].'">'.$data2['language'].'</td>
<td>'.htmlentities((string) $data2['author']).'</td>
<td>'.date('d/m/Y H:i', $data2['date']).'</td>
<td class="tr_todo'.$data2['todo_level'].'">'.$tr_todo[$data2['todo_level']].'</td>
<td class="tr_published'.$data2['published'].'">'.($data2['published'] ? 'Public' : 'Privé').'</td>
<td>
<input type="radio" title="Modèle" aria-label="Sélectionner '.$data2['language'].' (comme modèle)" name="model" value="'.$data2['id'].'">
<a href="?type=article&id='.$data['id'].'&tr='.$data2['id'].'&token='.$login['token'].'&a='.($data2['published'] ? 'priv">Fermer' : 'pub">Publier').'</a>
<button type="submit" name="edit" value="'.$data2['id'].'" aria-label="Modifier avec le modèle sélectionné" title="Modifié avec le modèle sélectionné">Modifier</button>
<a href="?type=article&id='.$data['id'].'&tr='.$data2['id'].'&a=rm&token='.$login['token'].'">Supprimer</a>
</td>
</tr>';
        } ?>
</tbody>
</table>
<fieldset><legend>Pour le modèle sélectionné</legend>
<label for="f_sw_nomodel">Pas de modèle</label> <input id="f_sw_nomodel" type="radio" name="model" value="" checked>
<button type="submit" name="a" value="new">Nouvelle traduction</button>
</fieldset>
<fieldset><legend>Pour les items sélectionnés</legend>
<label for="tr_sw_new_todo">Changer l'état&nbsp;:</label> <select id="tr_sw_new_todo" name="tr_todo"><option value="0">Référence</option><option value="1">OK</option><option value="2">À vérifier</option><option value="3">À modifier</option></select>
<button type="submit" name="a" value="todo">Changer l'état</button>
</fieldset>
</form><?php
    }
}
?>
<hr>
<h3>Licence</h3>
<p>Les données de traduction envoyées et gérées par cette page sont sous licence <a href="https://creativecommons.org/licenses/by-sa/4.0/">CC BY-SA 4.0</a> au nom de "L'équipe <?= $site_name ?>". Le contenu du site et ses traductions sont une œuvre collaborative et libre.</p>
</body>
</html>
