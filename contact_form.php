<?php set_include_path($_SERVER['DOCUMENT_ROOT']);
include_once(__DIR__ . '/include/log.php');
require_once(__DIR__ . '/include/consts.php');
require_once(__DIR__ . '/include/sendMail.php');
require_once(__DIR__ . '/include/lib/mtcaptcha/lib/class.mtcaptchalib.php');
require_once(__DIR__ . '/include/lib/MDConverter.php');
$tr = load_tr($lang, 'contacter');
$title = 'Contacter l\'équipe '.$site_name;
$stats_page = 'contacter';

$log = '';
$reply = false;
if (isset($_GET['reply'], $_GET['h']))
{
    $SQL = <<<SQL
        SELECT * FROM tickets WHERE id=:id AND hash=:hash
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':id' => $_GET['reply'], ':hash' => $_GET['h']]);
    if ($rdata = $req->fetch())
    {
        $reply = true;
    }
    else
    {
        $log .= '<li>Le lien que vous avez suivi est invalide. Veuillez réessayer.<br>Si le problème persiste, vous pouvez envoyer un nouveau message en faisant référence à l\'ancien dans le texte.</li>';
    }
}

if (isset($_GET['act']) && ($_GET['act'] === 'contact' || $_GET['act'] === 'reply'))
{
    if (!isset($_POST['mtcaptcha-verifiedtoken']))
    {
        $log .= '<li>Veuillez saisir le code de vérification antispam</li>';
    }
    else
    {
        $MTCaptchaSDK = new MTCaptchaLib(MTCAPTCHA_PRIVATE);
        $result = $MTCaptchaSDK->validate_token($_POST['mtcaptcha-verifiedtoken']);
        if (!$result)
        {
            $log .= '<li>Le code de vérification antispam est incorrect</li>';
        }
        else
        {
            $reply2 = false;
            if ($_GET['act'] === 'reply' && isset($_GET['id'], $_GET['h']))
            {
                $SQL = <<<SQL
                    SELECT * FROM tickets WHERE id=:id AND hash=:hash LIMIT 1
                    SQL;
                $req = $bdd->prepare($SQL);
                $req->execute([':id' => $_GET['id'], ':hash' => $_GET['h']]);
                if ($rdata2 = $req->fetch())
                {
                    $reply2 = true;
                }
                else
                {
                    $log .= '<li>Le lien que vous avez suivi est invalide. Veuillez réessayer.<br>Si le problème persiste, vous pouvez envoyer un nouveau message en faisant référence à l\'ancien dans le texte.</li>';
                }
            }
            if (!$reply2)
            {
                if (isset($_POST['name']) && !empty($_POST['name']))
                {
                    if (strlen((string) $_POST['name']) > 255)
                    {
                        $log .= '<li>Votre nom ne doit pas dépasser les 255 caractères.</li>';
                    }
                }
                else
                {
                    $log .= '<li>Veuillez renseigner votre nom ou un pseudonyme de votre choix.</li>';
                }
                if (isset($_POST['mail']) && !empty($_POST['mail']))
                {
                    if (strlen((string) $_POST['mail']) > 255)
                    {
                        $log .= '<li>Votre adresse e-mail ne doit pas dépasser les 255 caractères.</li>';
                    }
                }
                else
                {
                    $log .= '<li>Veuillez renseigner une adresse e-mail valide (elle sera utilisée pour vous répondre).</li>';
                }
                if (isset($_POST['obj']) && !empty($_POST['obj']))
                {
                    if (strlen((string) $_POST['obj']) > 255)
                    {
                        $log .= '<li>Le sujet du message ne doit pas dépasser les 255 caractères.</li>';
                    }
                }
                else
                {
                    $log .= "<li>Veuillez renseigner l'objet de votre message.</li>";
                }
            }
            if (isset($_POST['msg']) && strlen((string) $_POST['msg']) > 10)
            {
                if (strlen((string) $_POST['msg']) > 8192)
                {
                    $log .= '<li>Le message ne doit pas dépasser les 8192 caractères.</li>';
                }
            }
            else
            {
                $log .= '<li>Votre message serait certainement plus utile en comportant plus de 10 caractères.</li>';
            }
            if ($log === '' || $log === '0')
            {
                $msg = str_replace("\n\n", '</p><p>', htmlspecialchars((string) $_POST['msg']));
                $msg = '<p>'.str_replace("\n", '<br>', convertToMD($msg)).'</p>';
                $time = time();
                if ($reply2)
                {
                    $SQL = <<<SQL
                        UPDATE tickets SET messages=:msg, status=1, date=:date WHERE id=:id
                        SQL;
                    $req = $bdd->prepare($SQL);
                    $messages = json_decode((string) $rdata2['messages'], true);
                    $messages[] = ['e' => $rdata2['expeditor_name'],'m' => 0,'d' => $time, 't' => $msg];
                    $req->execute([':msg' => json_encode($messages), ':date' => $time, ':id' => $rdata2['id']]);
                    $tickid = $rdata2['id'];
                }
                else
                {
                    $SQL = <<<SQL
                        INSERT INTO tickets (subject,expeditor_email,expeditor_name,messages,status,hash,date) VALUES (:subject,:mail,:name,:msg,0,:hash,:date)
                        SQL;
                    $req = $bdd->prepare($SQL);
                    $message = json_encode([['e' => $_POST['name'],'m' => 0,'d' => $time, 't' => $msg]]);
                    $hash = hash('sha512', strval(time()).strval(random_int(0, mt_getrandmax())).$_POST['name'].strval(random_int(0, mt_getrandmax())));
                    $req->execute([':subject' => $_POST['obj'], ':mail' => $_POST['mail'], ':name' => $_POST['name'], ':msg' => $message, ':hash' => $hash, ':date' => $time]);
                    $tickid = $bdd->lastInsertId();
                }
                header('Location: /?contactconfirm=1#contactconfirm');
                $subject = ($reply2) ? sprintf('Re: %s (Ticket #%s#)', $rdata2['subject'], $tickid) : sprintf('%s (Ticket #%s#)', $_POST['obj'], $tickid);
                if ($reply2)
                {
                    $introH = <<<HTML
                        <h2>Réponse au ticket {$rdata2['subject']}</h2>
                        <p>Une réponse a été envoyée par {$rdata2['expeditor_name']} pour le ticket {$tickid} via le formulaire de contact de {$site_name}.</p>
                        HTML;
                    $endH = <<<HTML
                        <p><a href="{SITE_URL}/admin/tickets.php?ticket={$tickid}">Consultez le ticket</a> ou répondez à ce message sans en modifier l'objet pour continuer la discussion.</p>
                        HTML;
                    $introT = <<<TEXT
                        Réponse au ticket {$rdata2['subject']}

                        Une réponse a été envoyée par {$rdata2['expeditor_name']} pour le ticket {$tickid} via le formulaire de contact de {$site_name}.
                        TEXT;
                    $endT = <<<TEXT
                        Pour continuer la discussion, répondez à ce message sans en modifier l'objet ou consultez le ticket à l'adresse suivante:
                        {SITE_URL}/admin/tickets.php?ticket={$tickid}
                        TEXT;
                }
                else
                {
                    $introH = <<<HTML
                        <p>{$_POST['name']} a envoyé le message {$_POST['obj']} via le formulaire de contact de {$site_name}.<br>
                        Il a été enregistré sous le numéro {$tickid} et voici son contenu</p>
                        HTML;
                    $endH = <<<HTML
                        <p><a href="{SITE_URL}/admin/tickets.php?ticket={$tickid}">Consultez le ticket</a> ou répondez à ce message sans en modifier l'objet pour y répondre.</p>
                        HTML;
                    $introT = <<<TEXT
                        {$_POST['name']} a envoyé le message {$_POST['obj']} via le formulaire de contact de {$site_name}.

                        Il a été enregistré sous le numéro {$tickid} et voici son contenu
                        TEXT;
                    $endT = <<<TEXT
                        Pour y répondre, répondez à ce message sans en modifier l'objet ou consultez le ticket à l'adresse suivante:
                        {SITE_URL}/admin/tickets.php?ticket={$tickid}
                        TEXT;
                }
                $body = <<<HTML
                    <p>## Ne pas écrire en-dessous de cette ligne ##</p>
                    {$introH}
                    <p><blockquote>{$msg}</blockquote></p>
                    {$endH}
                    HTML;
                $altBody = <<<TEXT
                    ## Ne pas écrire en-dessous de cette ligne ##
                    {$introT}

                    {$msg}

                    {$endT}
                    TEXT;
                sendMail(getTeamEmails('manage_tickets'), $subject, $body, $altBody, [TICKETS_BOT_MAIL, $site_name . ' Tickets Bot'], ['includeAutoReplyNotice' => false]);
                if (isset($_POST['copy']))
                {
                    $dest = ($reply2) ? $rdata2['expeditor_email'] : $_POST['mail'];
                    if ($reply2)
                    {
                        $introCopyH = <<<HTML
                            <h2>Réponse au ticket {$rdata2['subject']}</h2>
                            <p>{$rdata2['expeditor_name']}, nous avons bien reçu votre réponse pour le ticket {$tickid} dont voici pour rappel son contenu</p>
                            HTML;
                        $introCopyT = <<<TEXT
                            Réponse au ticket {$rdata2['subject']}
                            {$rdata2['expeditor_name']}, nous avons bien reçu votre réponse pour le ticket {$tickid} dont voici pour rappel son contenu
                            TEXT;
                    }
                    else
                    {
                        $introCopyH = <<<HTML
                            <h2>Création du ticket {$_POST['obj']}</h2>
                            <p>{$_POST['name']}, nous avons bien reçu votre message enregistré avec le numéro {$tickid}. Nous allons y répondre très bientôt, en voici pour rappel son contenu</p>
                            HTML;
                        $introCopyT = <<<TEXT
                            Création du ticket {$_POST['obj']}
                            {$_POST['name']}, nous avons bien reçu votre message enregistré avec le numéro {$tickid}. Nous allons y répondre très bientôt, en voici pour rappel son contenu
                            TEXT;
                    }
                    $bodyCopy = <<<HTML
                        <p>## Ne pas écrire en-dessous de cette ligne ##</p>
                        {$introCopyH}
                        <p><blockquote>{$msg}</blockquote></p>
                        HTML;
                    $altBodyCopy = <<<TEXT
                        ## Ne pas écrire en-dessous de cette ligne ##

                        {$introCopyT}
                        {$msg}
                        TEXT;
                    sendMail($dest, $subject, $bodyCopy, $altBodyCopy, [TICKETS_BOT_MAIL, $site_name . ' Tickets Bot'], ['includeAutoReplyNotice' => false]);
                }
                exit();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<?php require_once(__DIR__ . '/include/header.php'); ?>
<body>
<?php require_once(__DIR__ . '/include/banner.php'); ?>
<main id="container">
<h1 id="contenu">Contacter l'équipe <?php print $site_name; ?></h1>
<?php echo tr($tr, 'tel', ['site' => $site_name]).'<h2>'.tr($tr, 'mailformtitle').'</h2>'; ?>
<div id="alertZone" role="alert" aria-live="assertive"></div>
<?php if ($log !== '' && $log !== '0'): ?>
<noscript>
<ul id="log" role="alert"><?= $log ?></p>
</noscript>
<script>
    window.addEventListener('DOMContentLoaded', () =>
    {
        const alertZone = document.getElementById('alertZone');
        alertZone.innerHTML = '<ul id="log"><?= addslashes($log) ?></ul>';
    });
</script>
<?php endif; ?>
<form action="?act=<?php if ($reply)
{
    echo 'reply&id='.$rdata['id'].'&h='.$rdata['hash'];
}
else
{
    echo 'contact';
} ?>" method="post" spellcheck="true">
<fieldset><legend>Informations personnelles</legend>
<table>
<tr><td><label for="f_name">Nom&nbsp;:</label></td><td><input type="text" name="name" id="f_name"<?php if ($reply)
{
    echo ' value="'.htmlentities((string) $rdata['expeditor_name']).'" disabled';
}
else
{
    if (isset($_POST['name']))
    {
        echo ' value="'.htmlentities((string) $_POST['name']).'"';
    }echo ' maxlength="255" required';
    if (!isset($_GET['act']))
    {
        echo ' autofocus';
    }
} ?>></td></tr>
<tr><td><label for="f_mail">Adresse e-mail&nbsp;:</label></td><td><input type="email" name="mail" id="f_mail"<?php if ($reply)
{
    echo ' value="'.htmlentities((string) $rdata['expeditor_email']).'" disabled';
}
elseif (isset($_POST['mail']))
{
    echo ' value="'.htmlentities((string) $_POST['mail']).'"';
} ?> maxlength="255" required></td></tr>
</table>
</fieldset>
<fieldset><legend>Message</legend>
<label for="f_obj">Sujet du message&nbsp;:</label>
<?php if ($reply)
{ ?>
<input type="text" id="f_obj" name="obj" value="<?= htmlentities((string) $rdata['subject']) ?>" disabled>
<?php }
else
{ ?>
<input type="text" id="f_obj" name="obj" <?php if (isset($_POST['obj']))
{
    echo ' value="'.htmlentities((string) $_POST['obj']).'"';
} ?> required>
<?php } ?><br>
<label for="f_msg">Votre message&nbsp;:</label><br>
<textarea id="f_msg" name="msg" maxlength="8192" style="width: calc(100% - 10px);min-height: 100px;margin-bottom: 10px;" required><?php if (isset($_POST['msg']))
{
    echo htmlentities((string) $_POST['msg']);
} ?></textarea><br>
<div class="mtcaptcha"></div>
<label for="f_copy">Recevoir une copie de votre message&nbsp;:</label>
<input type="checkbox" id="f_copy" name="copy" checked><br>
<input type="submit" value="Envoyer">
</fieldset>
</form>
</main>
<?php require_once(__DIR__ . '/include/footer.php'); ?>
</body>
</html>
