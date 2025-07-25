<?php $logonly = true;
$adminonly = true;
$justna = true;
$titlePAdm = 'Envoyer la lettre d\'informations';
require_once($_SERVER['DOCUMENT_ROOT'].'/include/log.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/include/consts.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/include/sendMail.php');
requireAdminRight('manage_newsletter');

if (isset($_GET['act']) && $_GET['act'] === 'form')
{
    if (isset($_POST['mail']) && !empty($_POST['mail']))
    {
        $debug = $_POST['mail'];
    }
    if (isset($_POST['simulate']))
    {
        $simulate = true;
    }
    header('Content-type: text/plain');
    header('Content-disposition: inline');
    require_once($_SERVER['DOCUMENT_ROOT'].'/tasks/nl_manager.php');
    exit();
}
if (isset($_GET['act']) && $_GET['act'] === 'sendnl')
{
    $site = $_POST['site'] ?? 'site1';
    $allMails = [];
    $buildSQL = function(string $column): string
    {
        return "SELECT mail FROM newsletter_mails WHERE confirm = true AND {$column} = true";
    };
    if ($site === 'site1' || $site === 'both')
    {
        $req = $bdd->prepare($buildSQL('notif_upd'));
        $req->execute();
        while ($data = $req->fetch())
        {
            $allMails[$data['mail']] = $data['hash'];
        }
    }
    if ($site === 'site2' || $site === 'both')
    {
        $req = $bdd->prepare($buildSQL('notif_upd_n'));
        $req->execute();
        while ($data = $req->fetch())
        {
            $allMails[$data['mail']] = $data['hash'];
        }
    }
    foreach ($allMails as $email => $hash)
    {
        sendMail($email, $_POST['obj'], str_replace('{userid}', $hash, $_POST['text']), "Ce mail est uniquement disponible au format HTML");
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Administration - <?php print $site_name; ?></title>
<?php print $admin_css_path; ?>
<script type="text/javascript" src="/scripts/default.js"></script>
</head>
<body>
<?php require_once('include/banner.php'); ?>
<h2>Envoyer une newsletter aux abonnés</h2>
<p>Remplir le formulaire ci-dessous pour envoyer une newsletter à tous les abonnés</p>
<form action="?act=sendnl" method="post">
<label for="f_obj">Sujet&nbsp;:</label>
<input type="text" name="obj" id="f_obj" required><br>
<label for="f_text">Texte (HTML)&nbsp;:</label>
<textarea name="text" id="f_text" maxlength="20000"><p>Pour vous désinscrire de cette newsletter, cliquez sur le lien suivant&nbsp;: <a href="<?php echo SITE_URL; ?>/nlmod.php?id={userid}"><?php echo SITE_URL; ?>/nlmod.php?id={userid}</a>.</p></textarea><br>
<label for="f_site">Envoyer aux abonnés de&nbsp;:</label>
<select name="site" id="f_site">
<option value="site1"><?php echo $site_name; ?></option>
<option value="site2">Blog</option>
<option value="both">Les deux sites</option>
</select><br>
<input type="submit" value="Envoyer">
</form>
<hr>
<h2>Debuguer l'envoi de la newsletter automatique</h2>
<form action="?act=form" method="post">
<label for="maildebug">Debuguer pour&nbsp;:</label>
<input type="email" name="mail" id="maildebug"><br>
<label for="mailsimulate">Simulation (n'envoie aucun mail, ne modifie pas la bdd)&nbsp;:</label>
<input type="checkbox" name="simulate" id="mailsimulate"><br>
<input type="submit" value="Envoyer">
</form>
</body>
</html>
