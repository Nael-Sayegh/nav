<?php
set_include_path($_SERVER['DOCUMENT_ROOT']);
require_once('include/log.php');
require_once('include/consts.php');
require_once('include/sendMail.php');
$log = '';

if (!isset($_GET['id']))
{
    header('Location: /newsletter.php');
    exit();
}
$SQL = <<<SQL
    SELECT * FROM newsletter_mails WHERE hash=:hash
    SQL;
$req = $bdd->prepare($SQL);
$req->execute([':hash' => $_GET['id']]);
if ($nldata = $req->fetch())
{
    if (isset($_GET['stop']))
    {
        $SQL2 = <<<SQL
            DELETE FROM newsletter_mails WHERE id=:id
            SQL;
        $req2 = $bdd->prepare($SQL2);
        $req2->execute([':id' => $nldata['id']]);
        $subject = 'Désinscription de la lettre d\'informations';
        $body = <<<HTML
            <div id="content">
            <h2>Bonjour {$nldata['mail']}</h2>
            <p>Vous avez bien été désabonné de la lettre d'informations {$site_name}.</p>
            <p>Ceci sera notre dernier mail, nous sommes tristes de vous voir partir et nous espérons vous revoir bientôt sur <a href="{SITE_URL}">{$site_name}</a>.</p>
            </div>
            HTML;
        $altBody = <<<TEXT
            Bonjour {$nldata['mail']},
            Vous avez bien été désabonné de la lettre d'informations {$site_name}.
            Ceci sera notre dernier mail, nous sommes tristes de vous voir partir et nous espérons vous revoir bientôt sur {SITE_URL}
            TEXT;
        sendMail($nldata['mail'], $subject, $body, $altBody);

        header('Location: /newsletter.php?stop');
        exit();
    }
    if (!$nldata['confirm'])
    {
        $SQL2 = <<<SQL
            UPDATE newsletter_mails SET confirm=true, lastmail=:last, lastmail_n=:lastn WHERE id=:id
            SQL;
        $req2 = $bdd->prepare($SQL2);
        $req2->execute([':last' => time(), ':lastn' => time(), ':id' => $nldata['id']]);
        $log .= 'Votre inscription à la lettre d\'informations '.$site_name.' a bien été confirmée.<br>';
    }
    if (isset($_GET['mod']))
    {
        $freq = $nldata['freq'];
        if (isset($_POST['freq']) && ($_POST['freq'] === '1' || $_POST['freq'] === '2' || $_POST['freq'] === '3' || $_POST['freq'] === '4' || $_POST['freq'] === '5'))
        {
            $freq = $_POST['freq'];
        }

        $freq_n = $nldata['freq_n'];
        if (isset($_POST['freq_n']) && ($_POST['freq_n'] === '1' || $_POST['freq_n'] === '2' || $_POST['freq_n'] === '3' || $_POST['freq_n'] === '4' || $_POST['freq_n'] === '5'))
        {
            $freq_n = $_POST['freq_n'];
        }

        $f_site = 0;
        if (isset($_POST['notif_site']) && $_POST['notif_site'] === 'on')
        {
            $f_site = 1;
        }
        $f_upd = 0;
        if (isset($_POST['notif_up']) && $_POST['notif_up'] === 'on')
        {
            $f_upd = 1;
        }
        $f_upd_n = 0;
        if (isset($_POST['notif_up_n']) && $_POST['notif_up_n'] === 'on')
        {
            $f_upd_n = 1;
        }
        $f_lang = $nldata['lang'];
        if (isset($_POST['lang']) && in_array($_POST['lang'], $langs_prio))
        {
            $f_lang = $_POST['lang'];
        }
        $SQL = <<<SQL
            UPDATE newsletter_mails SET freq=:frq, freq_n=:frqn, notif_site=:notifsite, notif_upd=:notifupd, notif_upd_n=:notifupdn, lang=:lng WHERE id=:id
            SQL;
        $req = $bdd->prepare($SQL);
        $req->execute([':frq' => $freq, ':frqn' => $freq_n, ':notifsite' => $f_site, ':notifupd' => $f_upd, ':notifupdn' => $f_upd_n, ':lng' => $f_lang, ':id' => $nldata['id']]);
        header('Location: nlmod.php?id='.$nldata['hash']);
        exit();
    }
    $args['id'] = $nldata['hash'];
}
else
{
    header('Location: newsletter.php');
    exit();
}

$title = 'Lettre d\'informations';
$stats_page = 'nlmod'; ?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<?php require_once('include/header.php'); ?>
<body>
<?php require_once('include/banner.php'); ?>
<main id="container">
<h1 id="contenu"><?php print $title; ?></h1>
<?php
if (isset($_GET['redir']) && $_GET['redir'])
{
    echo '<p>Comme vous êtes connecté en tant que '.$login['username'].', vous avez été redirigé vers les paramètres de la lettre d\'informations correspondants à l\'adresse mail '.$login['email'].'.<br>Si vous souhaitez inscrire une autre adresse&nbsp;: <a href="newsletter.php?noredir=true">accédez à la page d\'abonnement par défaut</a>.</p>';
} ?>
<div id="alertZone" role="alert" aria-live="assertive"></div>
<?php if (!empty($log)): ?>
<noscript>
<p role="alert"><b><?= $log ?></b></p>
</noscript>
<script>
    window.addEventListener('DOMContentLoaded', () =>
    {
        const alertZone = document.getElementById('alertZone');
        alertZone.innerHTML = '<p><b><?= addslashes($log) ?></b></p>';
    });
</script>
<?php endif; ?>
<p>Sur cette page vous pouvez modifier les paramètres de votre abonnement aux lettres d'informations de <?php print $site_name; ?>.</p>
<form action="?mod&id=<?= $nldata['hash'] ?>" method="post">
<fieldset><legend><?php print $site_name; ?></legend>
<label for="f_lang">Langue préférée&nbsp;:</label>
<select id="f_lang" name="lang" autocomplete="off"><?= langs_html_opts($nldata['lang']) ?></select><br>
<label for="f_freq">Recevoir un mail&nbsp;:</label>
<select name="freq" id="f_freq" autocomplete="off"><option value="1"<?php if ($nldata['freq'] === 1)
{
    echo ' selected';
} ?>>Quotidiennement</option><option value="2"<?php if ($nldata['freq'] === 2)
{
    echo ' selected';
} ?>>Tous les 2 jours</option><option value="3"<?php if ($nldata['freq'] === 3)
{
    echo ' selected';
} ?>>Hebdomadairement</option><option value="4"<?php if ($nldata['freq'] === 4)
{
    echo ' selected';
} ?>>Quinzomadairement</option><option value="5"<?php if ($nldata['freq'] === 5)
{
    echo ' selected';
} ?>>Mensuellement</option></select><br>
<label for="f_notif_site" autocomplete="off">Me notifier d'une mise à jour du site&nbsp;:</label>
<input type="checkbox" name="notif_site" id="f_notif_site"<?php if ($nldata['notif_site'])
{
    echo ' checked="checked"';
} ?>><br>
<label for="f_notif_up">Me notifier de la mise à jour d'un article&nbsp;:</label>
<input type="checkbox" name="notif_up" id="f_notif_up"<?php if ($nldata['notif_upd'])
{
    echo ' checked="checked"';
} ?>><br>
</fieldset>
<fieldset><legend>NVDA.FR</legend>
<label for="f_freq_n">Recevoir un mail&nbsp;:</label>
<select name="freq_n" id="f_freq_n" autocomplete="off"><option value="1"<?php if ($nldata['freq_n'] === 1)
{
    echo ' selected';
} ?>>Quotidiennement</option><option value="2"<?php if ($nldata['freq_n'] === 2)
{
    echo ' selected';
} ?>>Tous les 2 jours</option><option value="3"<?php if ($nldata['freq_n'] === 3)
{
    echo ' selected';
} ?>>Hebdomadairement</option><option value="4"<?php if ($nldata['freq_n'] === 4)
{
    echo ' selected';
} ?>>Quinzomadairement</option><option value="5"<?php if ($nldata['freq_n'] === 5)
{
    echo ' selected';
} ?>>Mensuellement</option></select><br>
<label for="f_notif_up_n">Me notifier de la mise à jour d'un article&nbsp;:</label>
<input type="checkbox" name="notif_up_n" id="f_notif_up_n"<?php if ($nldata['notif_upd_n'])
{
    echo ' checked="checked"';
} ?>><br>
</fieldset>
<input type="submit" value="Modifier l'abonnement">
</form>
<p>Ne plus recevoir de lettres d'information&nbsp;: <a href="?stop&id=<?= $nldata['hash'] ?>">Se désabonner</a></p>
</main>
<?php require_once('include/footer.php'); ?>
</body>
</html>
