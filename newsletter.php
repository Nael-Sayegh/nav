<?php
set_include_path($_SERVER['DOCUMENT_ROOT']);
require_once('include/log.php');
require_once('include/consts.php');
require_once('include/sendMail.php');
$stats_page = 'newsletter';
if (isset($logged) && $logged && (!isset($_GET['noredir']) || isset($_GET['noredir']) && $_GET['noredir'] === false))
{
    $SQL = <<<SQL
        SELECT mail,hash FROM newsletter_mails WHERE mail=:mail LIMIT 1
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':mail' => $login['email']]);
    if ($data = $req->fetch())
    {
        header('Location: nlmod.php?id='.$data['hash'].'&redir=true');
        exit();
    }
}
$log = '';
if (isset($_GET['a']) && $_GET['a'] === 's')
{
    if (!isset($_POST['mail']) || strlen((string) $_POST['mail']) > 255 || empty($_POST['mail']))
    {
        $log .= 'L\'adresse e-mail ne doit pas être vide et ne doit pas excéder les 255 caractères&#8239;!<br>';
    }
    // if (!isset($_POST['freq']) || !($_POST['freq'] === '1' || $_POST['freq'] === '2' || $_POST['freq'] === '3' || $_POST['freq'] === '4' || $_POST['freq'] === '5'))
    // {
    //     $log .= 'Veuillez renseigner une fréquence d\'envoi valide.<br>';
    // }
    if (empty($log))
    {
        $SQL = <<<SQL
            SELECT id FROM newsletter_mails WHERE mail=:mail LIMIT 1
            SQL;
        $req = $bdd->prepare($SQL);
        $req->execute([':mail' => $_POST['mail']]);
        if ($req->fetch())
        {
            $log .= 'Cette adresse est déjà inscrite&#8239;!';
        }
        else
        {
            $hash = sha1(strval(random_int(0, mt_getrandmax()) + time()).$_POST['mail']).sha1($_POST['mail'].$_SERVER['REMOTE_ADDR'].strval(random_int(0, mt_getrandmax())));
            $freq = 3;
            $freq_n = 3;
            $f_site = 1;

            // $f_site = 0;
            // if (isset($_POST['notif_site']) && $_POST['notif_site'] === 'on')
            // {
            //     $f_site = 1;
            // }
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

            $subject = 'Confirmation d\'inscription à la lettre d\'informations';
            $body = <<<HTML
                <div id="content">
                <h2>Bonjour</h2>
                <p>Vous avez bien été inscrit à la lettre d'informations {$site_name}.</p>
                <p>Votre inscription est maintenant active et vous recevrez les prochaines newsletters selon vos préférences.</p>
                <p>Vous pouvez modifier les paramètres de votre abonnement ou vous désinscrire à l'adresse suivante :<br>
                 <a href="{SITE_URL}/nlmod.php?id={$hash}">{SITE_URL}/nlmod.php?id={$hash}</a></p>
                </div>
                HTML;
            $altBody = <<<TEXT
                Bonjour,
                Vous avez bien été inscrit à la lettre d'informations {$site_name}.
                Votre inscription est maintenant active et vous recevrez les prochaines newsletters selon vos préférences.
                Vous pouvez modifier les paramètres de votre abonnement ou vous désinscrire à l'adresse suivante :
                https://{SITE_URL}/nlmod.php?id={$hash}
                TEXT;

            if (sendMail($_POST['mail'], $subject, $body, $altBody))
            {
                $SQL = <<<SQL
                INSERT INTO newsletter_mails (hash, mail, freq, freq_n, notif_site, notif_upd, notif_upd_n, lang, lastmail, lastmail_n) VALUES (:hash, :mail, :frq, :frqn, :notifsite, :notifupd, :notifupdn, :lng, :last, :lastn)
                SQL;
                $req = $bdd->prepare($SQL);
                $req->execute([':hash' => $hash, ':mail' => $_POST['mail'], ':frq' => $freq, ':frqn' => $freq_n,  ':notifsite' => $f_site, ':notifupd' => $f_upd, ':notifupdn' => $f_upd_n, ':lng' => $lang, ':last' => time(), ':lastn' => time()]);
                $log .= 'Vous êtes bien inscrit à la lettre d\'informations '.$site_name.'.<br>Un email de confirmation vous a été envoyé à '.$_POST['mail'].'.';
            }
            else
            {
                $log .= 'Erreur pendant l\'envoi du mail.';
            }
        }
    }
}
if (isset($_GET['stop']))
{
    $log .= 'Vous avez bien été désinscrit de la lettre d\'informations '.$site_name.'. Un mail de désinscription vous a été envoyé. Vous ne recevrez plus aucun mail de notre part.';
}

$title = 'Lettre d\'informations'; ?>
<!DOCTYPE html>
<html lang="fr">
<?php require_once('include/header.php'); ?>
<body>
<?php require_once('include/banner.php'); ?>
<main id="container">
<h1 id="contenu"><?php print $title; ?></h1>
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
<p>Inscrivez-vous à la newsletter <?php print $site_name; ?> pour connaître toutes nos actualités, nouveautés et informations!</p>
<form action="?a=s&noredir=true" method="post">
<label for="f_mail">Adresse e-mail&nbsp;:</label>
<input type="email" name="mail" id="f_mail" maxlength="255" required><br>
<fieldset><legend><?php print $site_name; ?></legend>
<!-- <label for="f_freq">Recevoir un mail&nbsp;:</label>
<select name="freq" id="f_freq"><option value="1">Quotidiennement</option><option value="2">Tous les 2 jours</option><option value="3" selected>Hebdomadairement</option><option value="4">Quinzomadairement</option><option value="5">Mensuellement</option></select><br>
<label for="f_notif_site">Me notifier d'une mise à jour du site&nbsp;:</label>
<input type="checkbox" name="notif_site" id="f_notif_site" checked><br>
-->
<label for="f_notif_up">M'inscrire à la newsletter <?= $site_name; ?></label>
<input type="checkbox" name="notif_up" id="f_notif_up" checked><br>
</fieldset>
<fieldset><legend>Blog Nael-Accessvision</legend>
<!-- <label for="f_freq_n">Recevoir un mail&nbsp;:</label>
<select name="freq_n" id="f_freq_n"><option value="1">Quotidiennement</option><option value="2">Tous les 2 jours</option><option value="3" selected>Hebdomadairement</option><option value="4">Quinzomadairement</option><option value="5">Mensuellement</option></select><br>
-->
<label for="f_notif_up_n">M'inscrire à la newsletter Blog Nael-Accessvision</label>
<input type="checkbox" name="notif_up_n" id="f_notif_up_n" checked><br>
</fieldset>
<p>Votre adresse e-mail ainsi que toutes vos informations personnelles ne seront pas partagées avec des tiers. Cet abonnement peut être annulé à tout moment.</p>
<input type="submit" value="S'abonner">
</form>
</main>
<?php require_once('include/footer.php'); ?>
</body>
</html>
