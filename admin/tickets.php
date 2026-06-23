<?php $logonly = true;
$adminonly = true;
$justna = true;
$titlePAdm = 'Tickets';
require_once($_SERVER['DOCUMENT_ROOT'].'/include/log.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/include/consts.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/include/sendMail.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/include/lib/MDConverter.php');
requireAdminRight('manage_tickets');
if (isset($_GET['archive']))
{
    $SQL = <<<SQL
        UPDATE tickets SET status=3 WHERE id=:id
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':id' => $_GET['archive']]);
}
if (isset($_GET['waiting']))
{
    $SQL = <<<SQL
        UPDATE tickets SET status=2 WHERE id=:id
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':id' => $_GET['waiting']]);
}
if (isset($_GET['close'], $_POST['clo'])   && $_POST['clo'] === 'FERMER')
{
    $SQL = <<<SQL
        SELECT * FROM tickets WHERE id=:id LIMIT 1
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':id' => $_GET['close']]);
    if ($data = $req->fetch())
    {
        $subject = sprintf('Ticket %s fermé', $data['id']);
        $body = <<<HTML
            <h2>Fermeture du ticket {$data['subject']}</h2>
            <p>{$admin_name} vient de fermer votre ticket numéro {$data['id']} sur {$site_name}.</p>
            <p>Vous pouvez réouvrir ce ticket dans les 24 heures simplement en y répondant, passez cette date la discussion sur ce ticket s'arrêtera définitivement et vous devrez réouvrir un ticket pour continuer à discuter avec nous.</p>
            <hr>
            <a href="{SITE_URL}/contact_form.php?reply={$data['id']}&h={$data['hash']}">Répondre au ticket pour le réouvrir</a>.</p>
            HTML;
        $altBody = <<<TEXT
            Fermeture du ticket {$data['subject']}
            {$admin_name} vient de fermer votre ticket numéro {$data['id']} sur {$site_name}.
            Vous pouvez réouvrir ce ticket dans les 24 heures simplement en y répondant, passez cette date la discussion sur ce ticket s'arrêtera définitivement et vous devrez réouvrir un ticket pour continuer à discuter avec nous.
            Répondre pour réouvrir: {SITE_URL}/contact_form.php?reply={$data['id']}&h={$data['hash']}
            TEXT;
        $teamBody = <<<HTML
            <h2>Fermeture du ticket {$data['subject']}</h2>
            <p>{$admin_name} vient de fermer le ticket numéro {$data['id']} de {$data['expeditor_name']} sur {$site_name}.</p>
            <p>Vous pouvez réouvrir ce ticket dans les 24 heures simplement en y répondant, passez cette date la discussion sur ce ticket s'arrêtera définitivement.</p>
            <hr>
            <a href="{SITE_URL}/admin/tickets.php?ticket={$data['id']}">Consulter le ticket complet</a>.</p>
            HTML;
        $teamAltBody = <<<TEXT
            Fermeture du ticket {$data['subject']}
            {$admin_name} vient de fermer le ticket numéro {$data['id']} de {$data['expeditor_name']} sur {$site_name}.
            Vous pouvez réouvrir ce ticket dans les 24 heures simplement en y répondant, passez cette date la discussion sur ce ticket s'arrêtera définitivement.
            Consulter le ticket à l'adresse suivante:
            {SITE_URL}/admin/tickets.php?ticket={$data['id']}
            TEXT;
        $log = 'Ticket fermé, mails envoyés';
        header('Connection: close');
        ignore_user_abort(true);
        if (function_exists('fastcgi_finish_request'))
        {
            fastcgi_finish_request();
        }
        else
        {
            @ob_end_flush();
            @flush();
        }
        sendMail(getTeamEmails('manage_tickets'), $subject, $teamBody, $teamAltBody);
        sendMail($data['expeditor_email'], $subject, $body, $altBody);
    }
    $SQL = <<<SQL
        UPDATE tickets SET status=4, date=:date WHERE id=:id
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':date' => time(), ':id' => $_GET['close']]);
}

if (isset($_GET['delete'], $_POST['del'])   && $_POST['del'] === 'SUPPRIMER')
{
    $SQL = <<<SQL
        DELETE FROM tickets WHERE id=:id
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':id' => $_GET['delete']]);
}
if (isset($_GET['send'], $_POST['msg']))
{
    $SQL = <<<SQL
        SELECT * FROM tickets WHERE id=:id LIMIT 1
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':id' => $_GET['send']]);
    if ($data = $req->fetch())
    {
        $msg = str_replace("\n\n", '</p><p>', htmlspecialchars((string) $_POST['msg']));
        $msg = '<p>'.str_replace("\n", '<br>', convertToMD($msg)).'</p>';
        $msgs = json_decode((string) $data['messages'], true);
        $time = time();
        $msgs[] = ['e' => $admin_name, 'm' => 1, 'd' => $time, 't' => $msg];
        $larname = $admin_name.' (Admin)';
        $SQL2 = <<<SQL
            UPDATE tickets SET messages=:msg, status=2, date=:date, lastadmreply=:adm WHERE id=:id
            SQL;
        $req2 = $bdd->prepare($SQL2);
        $req2->execute([':msg' => json_encode($msgs), ':date' => $time, ':adm' => $larname, ':id' => $data['id']]);
        $css = <<<CSS
            #response
            {
                border-left:1px solid #0080FF;
                margin-left:8px;
                padding: 8px;
            }
            CSS;
        $subject = sprintf('Re: %s (Ticket #%s#)', $data['subject'], $data['id']);
        $body = <<<HTML
            <p>## Ne pas écrire en-dessous de cette ligne ##</p>
            <h2>Réponse à votre ticket {$data['subject']}</h2>
            <p>Vous avez reçu une réponse de {$admin_name} pour votre ticket numéro {$data['id']}.</p>
            <div id="response"><p><blockquote>{$msg}</blockquote></p></div>
            <hr>
            <p>Pour poursuivre la discussion, utilisez le lien ci-dessous ou répondez simplement à ce message sans en modifier l'objet.<br>
            <a href="{SITE_URL}/contact_form.php?reply={$data['id']}&h={$data['hash']}">Répondre au ticket</a>.</p>
            HTML;
        $altBody = <<<TEXT
            ## Ne pas écrire en-dessous de cette ligne ##
            Réponse à votre ticket {$data['subject']}
            Vous avez reçu une réponse de {$admin_name} pour votre ticket numéro {$data['id']}.
            {$msg}

            Pour poursuivre la discussion, utilisez le lien ci-dessous ou répondez simplement à ce message sans en modifier l'objet.
            {SITE_URL}/contact_form.php?reply={$data['id']}&h={$data['hash']}
            TEXT;
        $teamBody  = <<<HTML
            <p>## Ne pas écrire en-dessous de cette ligne ##</p>
            <h2>Réponse au ticket {$data['subject']}</h2>
            <p>{$admin_name} a répondu au ticket {$data['id']} de {$data['expeditor_name']}</p>
            <div id="response"><p><blockquote>{$msg}</blockquote></p></div>
            <p><a href="{SITE_URL}/admin/tickets.php?ticket={$data['id']}">Consulter le ticket complet</a> ou répondez à ce message sans en modifier l'objet pour poursuivre la discussion.</p>
            HTML;
        $teamAltBody = <<<TEXT
            ## Ne pas écrire en-dessous de cette ligne ##
            Réponse au ticket {$data['subject']}
            {$admin_name} a répondu au ticket {$data['id']} de {$data['expeditor_name']}
            {$msg}
            Pour continuer la discussion, répondez à ce message sans en modifier l'objet ou consultez le ticket à l'adresse suivante:
            {SITE_URL}/admin/tickets.php?ticket={$data['id']}
            TEXT;
        $log = 'Réponse envoyée';
        header('Connection: close');
        ignore_user_abort(true);
        if (function_exists('fastcgi_finish_request'))
        {
            fastcgi_finish_request();
        }
        else
        {
            @ob_end_flush();
            @flush();
        }
        sendMail(getTeamEmails('manage_tickets'), $subject, $teamBody, $teamAltBody, [TICKETS_BOT_MAIL, $site_name . ' Tickets Bot'], ['css' => $css, 'includeAutoReplyNotice' => false]);
        sendMail($data['expeditor_email'], $subject, $body, $altBody, [TICKETS_BOT_MAIL, $site_name . ' Tickets Bot'], ['css' => $css, 'includeAutoReplyNotice' => false]);
    }
}
if (isset($_GET['create']) && $_POST['name'] && $_POST['mail'] && $_POST['obj'] && $_POST['msg'])
{
    $msg = str_replace("\n\n", '</p><p>', htmlspecialchars((string) $_POST['msg']));
    $msg = '<p>'.str_replace("\n", '<br>', convertToMD($msg)).'</p>';
    $time = time();
    $SQL = <<<SQL
        INSERT INTO tickets (subject,expeditor_email,expeditor_name,messages,status,hash,date) VALUES (:subject,:mail,:name,:msg,0,:hash,:date)
        SQL;
    $req = $bdd->prepare($SQL);
    $message = json_encode([['e' => $_POST['name'],'m' => 0,'d' => $time, 't' => $msg]]);
    $hash = hash('sha512', strval(time()).strval(random_int(0, mt_getrandmax())).$_POST['name'].strval(random_int(0, mt_getrandmax())));
    $req->execute([':subject' => $_POST['obj'], ':mail' => $_POST['mail'], ':name' => $_POST['name'], ':msg' => $message, ':hash' => $hash, ':date' => $time]);
    $ticketId = $bdd->lastInsertId();
    $subject = sprintf('%s (Ticket #%s#)', $_POST['obj'], $ticketId);
    $teamBody = <<<HTML
        <p>## Ne pas écrire en-dessous de cette ligne ##</p>
        <h2>Création du ticket {$_POST['obj']}</h2>
        <p>{$admin_name} a créé un ticket pour {$_POST['name']} sur {$site_name}<br>
        Il a été enregistré sous le numéro {$ticketId} et en voici le contenu</p>
        <p><blockquote>{$msg}</blockquote></p>
        <p><a href="{SITE_URL}/admin/tickets.php?ticket={$ticketId}">Consultez le ticket</a> ou répondez à ce message sans en modifier l'objet pour y répondre.</p>
        HTML;
    $teamAltBody = <<<TEXT
        ## Ne pas écrire en-dessous de cette ligne ##
        Création du ticket {$_POST['obj']}
        {$admin_name} a créé un ticket pour {$_POST['name']} sur {$site_name}
        Il a été enregistré sous le numéro {$ticketId} et en voici le contenu
        {$msg}
        Pour y répondre, répondez à ce message sans en modifier l'objet ou consultez le ticket à l'adresse suivante:
        {SITE_URL}/admin/tickets.php?ticket={$ticketId}
        TEXT;
    $body = <<<HTML
        <p>## Ne pas écrire en-dessous de cette ligne ##</p>
        <h2>Création du ticket {$_POST['obj']}</h2>
        <p>{$_POST['name']}, {$admin_name} vous a créé un ticket sur {$site_name}<br>
        Il a été enregistré sous le numéro {$ticketId} et en voici le contenu</p>
        <p><blockquote>{$msg}</blockquote></p>
        <p>Nous y répondrons très bientôt.</p>
        HTML;
    $altBody = <<<TEXT
        ## Ne pas écrire en-dessous de cette ligne ##
        Création du ticket {$_POST['obj']}
        {$_POST['name']}, {$admin_name} vous a créé un ticket sur {$site_name}
        Il a été enregistré sous le numéro {$ticketId} et en voici le contenu
        {$msg}
        Nous y répondrons très bientôt.
        TEXT;
    $log = 'Ticket créé, mails envoyés';
    header('Connection: close');
    ignore_user_abort(true);
    if (function_exists('fastcgi_finish_request'))
    {
        fastcgi_finish_request();
    }
    else
    {
        @ob_end_flush();
        @flush();
    }
    sendMail(getTeamEmails('manage_tickets'), $subject, $teamBody, $teamAltBody, [TICKETS_BOT_MAIL, $site_name . ' Tickets Bot'], ['includeAutoReplyNotice' => false]);
    sendMail($_POST['mail'], $subject, $body, $altBody);
}
function getStatus($status, $asTd = false): string
{
    $map = [
        0 => ['color' => 'C00000', 'label' => 'Nouveau'],
        1 => ['color' => '606000', 'label' => 'Non lu'],
        2 => ['color' => '00C000', 'label' => 'En attente'],
        3 => ['color' => '0000C0', 'label' => 'Archivé'],
        4 => ['color' => '000C00', 'label' => 'Fermé'],
    ];
    $entry = $map[$status] ?? ['color' => '000000', 'label' => 'Erreur'];
    $hex   = $entry['color'];
    $label = htmlspecialchars($entry['label'], ENT_QUOTES, 'UTF-8');

    if ($asTd)
    {
        return sprintf('<td class="ticket_%s">%s</td>', $hex, $label);
    }
    return sprintf('<b style="color:#%s">%s</b>', $hex, $label);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Tickets <?php print $site_name; ?></title>
<?php print $admin_css_path; ?>
<link rel="stylesheet" href="/admin/css/tickets.css">
<script type="text/javascript" src="/scripts/default.js"></script>
</head>
<body>
<?php require_once(__DIR__ . '/include/banner.php');
if (isset($_GET['ticket']))
{ ?>
<ul>
<li><a href="tickets.php">Liste des tickets</a></li>
</ul>
<?php } ?>
<div id="alertZone" role="alert" aria-live="assertive"></div>
<?php if (!empty($log)): ?>
<noscript>
<p role="alert"><b><?= $log ?></b></p>
</noscript>
<script>
    window.addEventListener('DOMContentLoaded', () =>
    {
        const alertZone = document.getElementById('alertZone');
        alertZone.innerHTML = '<p><b><?= addslashes((string) $log) ?></b></p>';
    });
</script>
<?php endif;
if (isset($_GET['ticket']))
{
    $SQL = <<<SQL
        SELECT * FROM tickets WHERE id=:id LIMIT 1
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':id' => $_GET['ticket']]);
    if ($data = $req->fetch())
    {
        echo '<p>Sujet&nbsp;: <b>'.htmlspecialchars((string) $data['subject']).'</b><br>Expéditeur&nbsp: <b><a href="mailto:'.htmlspecialchars((string) $data['expeditor_email']).'" title="'.htmlspecialchars((string) $data['expeditor_email']).'">'.htmlspecialchars((string) $data['expeditor_name']).'</a></b><br>Dernière activité&nbsp;: le '.date('d/m/Y H:i', $data['date']).'<br>Statut&nbsp: '.getStatus($data['status']).'</p><table id="ticket_msgs">';
        $messages = json_decode((string) $data['messages'], true);
        foreach ($messages as &$msg)
        {
            echo '<tr class="ticket_msg'.strval($msg['m']).'"><td rowspan="2" class="ticket_msgtd"></td>';
            echo '<td class="ticket_msginfo">';
            if ($msg['m'] === 1)
            {
                echo '<img alt="'.$site_name.'" src="/images/logo16.png"> ';
            }
            echo '<b>'.htmlspecialchars((string) $msg['e']).'</b> '.date('d/m/Y H:i', $msg['d']).'</td></tr><tr><td>'.$msg['t'].'</td></tr>';
        }
        unset($msg);
        echo '</table>';
        if ($data['status'] !== 2)
        {
            echo '<p><a href="?waiting='.$data['id'].'">Marquer comme lu</a></p>';
        }
        if ($data['status'] !== 3)
        {
            echo '<p><a href="?archive='.$data['id'].'">Archiver ce ticket</a></p>';
        }
        if ($data['status'] === 4 && $data['date'] < (time() - 86400))
        {
            echo 'Ce ticket a été fermé il y a plus de 24 heures, il est impossible d\'y répondre.';
        }
        else
        { ?>
<form action="?send=<?= $data['id'] ?>" method="post">
<fieldset><legend>Répondre</legend>
<label for="f1_msg">Message&nbsp;:</label><br>
<textarea id="f1_msg" name="msg" required rows="20" cols="500"><?= "\n\n".$admin_name.' (Équipe '.$site_name.')' ?></textarea><br>
<input type="submit" value="Répondre">
</fieldset>
</form>
<script>init_close_confirm();</script>
<?php } ?>
<form action="?close=<?php echo $data['id']; ?>" method="post" aria-label="Fermer le ticket">
<fieldset><legend>Fermer</legend>
<label for="f2_clo">Écrire FERMER en majuscules pour fermer le ticket.</label><br>
<input type="text" id="f2_clo" name="clo" required><br>
<input type="submit" value="Fermer">
</fieldset>
</form>
<form action="?delete=<?= $data['id'] ?>" method="post">
<fieldset><legend>Supprimer</legend>
<label for="f2_del">Écrire SUPPRIMER en majuscules pour supprimer le ticket.</label><br>
<input type="text" id="f2_del" name="del" required><br>
<input type="submit" onclick="return confirm('Faut-il vraiment supprimer le ticket <?= htmlspecialchars((string) $data['subject']) ?>&nbsp;?')" value="Supprimer">
</fieldset>
</form>
<?php
    }
    else
    {
        echo "<p>Le ticket n'existe pas.</p>";
    }
}
else
{
    ?>
<div id="js-ticket-sort-container" hidden style="margin:1em 0;">
<label for="js_ticket_sort">Trier les tickets&nbsp;:</label>
<select id="js_ticket_sort">
<option value="date">Par date</option>
<option value="status">Par statut</option>
</select>
</div>
<table id="tickets">
<thead>
<tr><th>Statut</th><th>Sujet</th><th>Correspondant</th><th>Dernière activité</th></tr>
</thead>
<tbody id="ticket-list">
<?php
    $SQL = <<<SQL
        SELECT * FROM tickets ORDER BY status ASC, date DESC
        SQL;
    $tr2 = false;
    foreach ($bdd->query($SQL) as $data)
    {
        echo '<tr class="ticket';
        if ($tr2)
        {
            echo ' ticket2';
        }
        else
        {
            echo ' ticket1';
        }
        $tr2 = !$tr2;
        echo '" data-date="'.$data['date'].'" data-status="'.$data['status'].'">'.getStatus($data['status'], true).'<td><a href="?ticket='.$data['id'].'">'.htmlspecialchars((string) $data['subject']).'</a></td><td>'.htmlspecialchars((string) $data['expeditor_name']).'</td><td>Le '.date('d/m/Y à H:i', $data['date']).'</td></tr>';
    }
    ?>
</tbody>
</table>
<?php } ?>
<details><summary role="heading" aria-level="3">Créer un nouveau ticket</summary>
<p>Remplir le formulaire ci-dessous pour créer un ticket pour un utilisateur</p>
<form action="?create" method="post">
<fieldset><legend>Informations personnelles</legend>
<table>
<tr><td><label for="f_name">Nom de l'utilisateur&nbsp;:</label></td><td><input type="text" name="name" id="f_name" maxlength="255" required></td></tr>
<tr><td><label for="f_mail">Adresse e-mail de l'utilisateur&nbsp;:</label></td><td><input type="email" name="mail" id="f_mail" maxlength="255" required></td></tr>
</table>
</fieldset>
<fieldset><legend>Message</legend>
<label for="f_obj">Sujet du message&nbsp;:</label>
<input type="text" id="f_obj" name="obj" required><br>

<label for="f_msg">Message&nbsp;:</label><br>
<textarea id="f_msg" name="msg" maxlength="8192" style="width: calc(100% - 10px);min-height: 100px;margin-bottom: 10px;" required onkeyup="close_confirm=true"></textarea><br>
<input type="submit" value="Envoyer et créer le ticket" ?>">
</fieldset>
</form>
<script>init_close_confirm();</script>
</details>
<script>
    document.addEventListener('DOMContentLoaded', function()
    {
        const ctr    = document.getElementById('js-ticket-sort-container');
        const select = document.getElementById('js_ticket_sort');
        const tbody  = document.getElementById('ticket-list');
        const rows   = Array.from(tbody.querySelectorAll('tr'));
        if (ctr) ctr.hidden = false;
        function sortRows(key)
        {
            const sorted = rows.slice().sort((a, b) =>
            {
                let va = a.dataset[key], vb = b.dataset[key];
                va = parseInt(va, 10) || 0;
                vb = parseInt(vb, 10) || 0;
                return vb - va;
            });
            tbody.innerHTML = '';
            sorted.forEach(tr => tbody.appendChild(tr));
        }
        if (select)
        {
            sortRows(select.value);
            select.addEventListener('change', () => sortRows(select.value));
        }
    });
</script>
</body>
</html>
