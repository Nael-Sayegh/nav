<?php

$atime = microtime(true);
$noct = true;

$document_root = __DIR__.'/..';
require_once($document_root.'/include/config.local.php');
require_once($document_root.'/include/consts.php');
require_once($document_root.'/include/sendMail.php');
if (!isDev() || isset($debug))
{
    if (isset($simulate))
    {
        echo "--simulate--\n";
    }

    $daydate = getFormattedDate(time(), tr($tr0, 'fndate'));
    $dayhour = getFormattedDate(time(), tr($tr0, 'ftime'));
    // Suppression automatique des abonnements expirés désactivée
    // $SQL = <<<SQL
    //     DELETE FROM newsletter_mails WHERE expire<:exp
    //     SQL;
    // $req = $bdd->prepare($SQL);
    // $req->execute([':exp' => time()]);

    if (isset($debug))
    {
        $SQL = <<<SQL
            SELECT * FROM newsletter_mails WHERE confirm=true AND expire<=:exp AND mail=:mail
            SQL;
        $req = $bdd->prepare($SQL);
        $req->execute([':exp' => time() + 172800, ':mail' => $debug]);
        echo "--debug--\n";
    }
    else
    {
        $SQL = <<<SQL
            SELECT * FROM newsletter_mails WHERE confirm=true AND expire<=:exp
            SQL;
        $req = $bdd->prepare($SQL);
        $req->execute([':exp' => time() + 172800]);
        echo "--prod--\n";
    }
    // Boucle d'expiration désactivée car les abonnements n'expirent plus
    while (false)
    {
        if (!isset($simulate))
        {
            $subject = 'Votre abonnement à la lettre d\'informations expire bientôt';
            $subscribeExp = date('d/m/Y à H:i', $data['expire']);
            $body = <<<HTML
                <p>Bonjour {$data['mail']},</p>
                <p>Votre abonnement à la lettre d'informations {$site_name} expire le {$subscribeExp}.</p>
                <p><a href="{SITE_URL}/nlmod.php?id={$data['hash']}">Renouveler l'abonnement</a>.</p>
                HTML;
            $altBody = <<<TEXT
                Bonjour {$data['mail']},

                Votre abonnement à la lettre d'informations {$site_name} expire le {$subscribeExp}.
                Renouveler l'abonnement : {SITE_URL}/nlmod.php?id={$data['hash']}
                TEXT;
            sendMail($data['mail'], $subject, $body, $altBody);
        }
        echo $data['mail'];
    }

    $r = '(freq=1';
    if (localtime()[3] === 1) # premier jour du mois
    {$r .= ' OR freq=5';
    }
    if (localtime()[6] === 1 && intval(date('W')) % 2 === 0) # lundi et semaine paire
    {$r .= ' OR freq=4';
    }
    if (localtime()[6] === 1) # lundi
    {$r .= ' OR freq=3';
    }
    if (localtime()[7] % 2 === 0) # jour pair sur l'année
    {$r .= ' OR freq=2';
    }
    $r .= ')';

    $cat = [];
    $SQL = <<<SQL
        SELECT * FROM softwares_categories
        SQL;
    foreach ($bdd->query($SQL) as $data)
    {
        $cat[$data['id']] = $data['name'];
    }

    $sft = [];
    $SQL = <<<SQL
        SELECT softwares_tr.lang, softwares_tr.name, softwares_tr.description, softwares_tr.sw_id, softwares.hits, softwares.date, softwares.author, softwares.category
        FROM softwares
        LEFT JOIN softwares_tr ON softwares.id=softwares_tr.sw_id
        WHERE softwares.date>=:date
        ORDER BY softwares.date DESC
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':date' => time() - 2678400]);# récents d'au plus un mois
    while ($data = $req->fetch())
    {
        if (!isset($sft[$data['sw_id']]))
        {
            $sft[$data['sw_id']] = ['category' => $data['category'], 'hits' => $data['hits'], 'date' => $data['date'], 'author' => $data['author'], 'trs' => []];
        }
        $sft[$data['sw_id']]['trs'][$data['lang']] = ['name' => $data['name'], 'description' => $data['description']];
    }
    $SQL = <<<SQL
        SELECT * FROM softwares_files WHERE date>=:date ORDER BY date DESC
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':date' => time() - 2678400]);# récents d'au plus un mois
    $files = [];
    while ($data = $req->fetch())
    {
        $files[] = $data;
    }

    $update_name = '';
    $update_text = '';
    $update_author = '';
    $update_date = 0;
    $SQL = <<<SQL
        SELECT * FROM site_updates ORDER BY date DESC LIMIT 1
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute();
    if ($data = $req->fetch())
    {
        $update_id = 'V'.$data['id'];
        $update_name = substr((string) $data['name'], 1);
        $update_text = $data['text'];
        $update_author = $data['authors'];
        $update_date = $data['date'];
    }
    $subject = '🗞️ Lettre d\'informations du '.$daydate;
    $newsletterCss = <<<CSS
        @font-face
        {
            font-family: Cantarell;
            src: url({SITE_URL}/css/Cantarell-Regular.otf);
        }
        html, body
        {
            margin: 0;
            padding: 0;
            font-family: Cantarell;
        }
        .software
        {
            border-left: 2px dashed black;
            padding-left: 10px;
        }
        .software_title
        {
            margin-bottom: -8px;
        }
        .software_date
        {
            color: #606060;
            margin-left: 15px;
        }
        .software_hits, .software_category
        {
            color: #008000;
        }
        CSS;
    $body1 = <<<HTML
        <h2>Bonjour {{mail_user}},</h2>
        HTML;
    $body2 = <<<HTML
        <hr><div role="complementary" aria-label="Informations sur l'abonnement"><p>Vous recevez la lettre d'informations {$site_name}
        HTML;
    $body3 = <<<HTML
        . <a id="link" href="{SITE_URL}/nlmod.php?id=
        HTML;
    $body4 = <<<HTML
        ">cliquez ici pour modifier vos préférences ou vous désinscrire</a>.</p></div>
        HTML;
    $altBody1 = <<<TEXT
        Bonjour {{mail_user}},
        Retrouvez l'historique des mises à jour sur {SITE_URL}/history.php

        TEXT;
    $altBody2 = <<<TEXT
        Vous recevez la lettre d'informations {$site_name}
        TEXT;
    $altBody3 = <<<TEXT

        Allez à l'adresse suivante pour modifier vos préférences ou vous désinscrire :
        {SITE_URL}/nlmod.php?id=
        TEXT;
    $altBody4 = <<<TEXT

        TEXT;

    if (isset($debug))
    {
        $SQL = <<<SQL
            SELECT * FROM newsletter_mails WHERE confirm=true AND mail=:mail
            SQL;
        $req = $bdd->prepare($SQL);
        $req->execute([':mail' => $debug]);
        echo "--debug--\n";
    }
    else
    {
        $SQL = <<<SQL
            SELECT * FROM newsletter_mails WHERE confirm=true AND {$r}
            SQL;
        $req = $bdd->prepare($SQL);
        $req->execute();
        echo "--prod--\n";
    }
    $nba = 0;
    $nbt = 0;
    $nbk = 0;
    while ($data = $req->fetch())
    {
        if ($data['notif_upd'] === true)
        {
            $nba++;
            $body = '';
            $altBody = '';
            $nbs = 0;# number of updated articles
            $nbf = 0;# number of updated files
            foreach ($sft as $sw_id => $software)
            {
                if ($software['date'] > $data['lastmail'])
                {
                    $entry_tr = '';
                    if (array_key_exists($data['lang'], $software['trs']))
                    {
                        $entry_tr = $data['lang'];
                    }
                    else
                    {
                        foreach ($langs_prio as &$i_lang)
                        {
                            if (array_key_exists($i_lang, $software['trs']))
                            {
                                $entry_tr = $i_lang;
                                break;
                            }
                        }
                    }
                    unset($i_lang);
                    if (empty($entry_tr)) // Error: sw has no translations
                    {continue;
                    }
                    $nbs++;
                    $sftDesc = str_replace('{{site}}', $site_name, $software['trs'][$entry_tr]['description']);
                    $sftHour = date('H:i', $software['date']);
                    $sftDate = date('d/m/Y', $software['date']);
                    $body .= <<<HTML
                        <div class="software">
                        <h3 class="software_title"><a href="{SITE_URL}/a{$sw_id}">{$software['trs'][$entry_tr]['name']}</a> (<a href="{SITE_URL}/c{$software['category']}">{$cat[$software['category']]}</a>)</h3><p>{$sftDesc}<br><span class="software_date">Mis à jour à {$sftHour} le {$sftDate} par {$software['author']}</span><span class="software_hits">, {$software['hits']} visites</span></p><ul>
                        HTML;
                    $altBody .= <<<TEXT
                         * {$software['trs'][$entry_tr]['name']} ({$cat[$software['category']]}) :
                        {$software['trs'][$entry_tr]['description']} ({$software['hits']} visites, mis à jour par {$software['author']} le {$sftDate} à {$sftHour})

                        TEXT;
                    foreach ($files as $file)
                    {
                        if ($file['sw_id'] === $sw_id && $file['date'] > $data['lastmail'])
                        {
                            $nbf++;
                            $body .= <<<HTML
                                <li><a href="{SITE_URL}/dl/{$file['id']}">{$file['title']} (téléchargé {$file['hits']} fois)</a></li>
                                HTML;
                            $altBody .= <<<TEXT
                                 - {$file['title']}, {SITE_URL}/dl/{$file['id']} ({$file['hits']} téléchargements)
                                TEXT;
                        }
                    }
                    unset($file);
                    $body .= <<<HTML
                        </ul></div>
                        HTML;
                    $altBody .= <<<TEXT

                        TEXT;
                }
            }
        }
        unset($software);
        $lastMailDate = date('d/m/Y', $data['lastmail']);
        $body = <<<HTML
            {$body1} <p>Depuis le {$lastMailDate}, <strong>{$nbs}</strong> articles et <strong>{$nbf}</strong> fichiers ont été mis à jour.</p> {$body}
            HTML;
        $altBody = <<<TEXT
            {$altBody1} Depuis le {$lastMailDate}, {$nbs} articles et {$nbf} fichiers ont été mis à jour.

            {$altBody}
            TEXT;
        echo $data['mail'];
        if ($nbs > 0 || $nbf > 0)
        {
            echo ' send';
            if ($data['notif_site'] === true && $data['lastmail'] < $update_date)
            {
                $body .= <<<HTML
                    <h2>{$site_name} version {$update_name} : {$update_id} ({$update_author})</h2><p>{$update_text}</p>
                    HTML;
                $updateTextT = strip_tags(html_entity_decode((string) $update_text));
                $altBody .= <<<TEXT
                    Mise à jour du site : {$site_name} version {$update_name} ({$update_id})
                    {$updateTextT}
                    TEXT;
            }
            $body .= <<<HTML
                {$body2}{$body3}{$data['hash']}{$body4}
                HTML;
            $altBody .= <<<TEXT
                {$altBody2}{$altBody3}{$data['hash']}{$altBody4}
                TEXT;

            $body = str_replace(['{{lang}}', '{{mail}}', '{{mail_user}}', '{{site}}'], [$data['lang'], $data['mail'], ucfirst(explode('@', (string) $data['mail'])[0]), $site_name], $body);
            $altBody = str_replace(['{{mail}}', '{{mail_user}}', '{{site}}'], [$data['mail'], ucfirst(explode('@', (string) $data['mail'])[0]), $site_name], $altBody);

            if (isset($debug))
            {
                print('<p>'.$altBody.'</p>');
            }

            if (!isset($simulate))
            {
                $nbt++;

                if (sendMail($data['mail'], $subject, $body, $altBody, null, ['css' => $newsletterCss]))
                {
                    echo ' OK';
                    $SQL = <<<SQL
                        UPDATE newsletter_mails SET lastmail=:last WHERE id=:id
                        SQL;
                    $req2 = $bdd->prepare($SQL);
                    $req2->execute([':last' => time(), ':id' => $data['id']]);
                    $nbk++;
                }
                else
                {
                    echo ' Error!';
                }
            }
            echo "\n";
        }
    }
    $btime = microtime(true) - $atime;
    echo $nba.' subscribers, '.$nbt.' sents, '.$nbk.' OK, '.$btime."s\n";
    if ($nbk > 0)
    {
        $body = "📤 Mail envoyé :\n-*".(intval($btime * 1000) / 1000)." secondes ;\n-*".$nbt." inscrits !\nConsultez vos mails 📥";
        echo $body;
    }
}
