<?php

set_include_path($_SERVER['DOCUMENT_ROOT']);
require_once('include/consts.php');
require_once('include/sendMail.php');

if (isset($_GET['id']) && isset($_GET['h']))
{
    $SQL = <<<SQL
        SELECT id, username, email, signup_date, settings FROM accounts WHERE id=:id AND signup_date<:date AND confirmed=false
        SQL;
    $req = $bdd2->prepare($SQL);
    $req->execute([':id' => $_GET['id'], ':date' => time() + 86400]);
    while ($data = $req->fetch())
    {
        if (json_decode((string) $data['settings'], true)['mhash'] === $_GET['h'])
        {
            $countReq = $bdd2->query('SELECT COUNT(*) FROM accounts');
            $totalAccounts = (int) $countReq->fetchColumn();
            $SQL = "UPDATE accounts SET confirmed = true";
            if ($totalAccounts === 1)
            {
                $SQL .= ", rank = :adminRank";
            }
            $SQL .= " WHERE id = :id";
            $req = $bdd2->prepare($SQL);
            $params = [':id' => $data['id']];
            if ($totalAccounts === 1)
            {
                $params[':adminRank'] = 'a';
            }
            $req->execute($params);
            $subject = 'Vos informations de membre';
            $username = htmlentities((string) $data['username']);
            $memberSignupDate = date('d/m/Y à H:i', $data['signup_date']);
            $body = <<<HTML
                <h2>Bonjour {$username} et bienvenue dans la communauté {$site_name}</h2>
                Vos informations sont les suivantes :</p>
                <ul>
                <li>Nom d'utilisateur : {$username}</li>
                <li>Adresse mail : {$data['email']}</li>
                <li>Numéro de membre : M{$data['id']}</li>
                <li>Date d'inscription : {$memberSignupDate}</li>
                </ul>
                HTML;
            $altBody = <<<TEXT
                Bonjour {$username} et bienvenue dans la communauté {$site_name}

                Vos informations sont les suivantes :
                - Nom d'utilisateur : {$username}
                - Adresse mail : {$data['email']}
                - Numéro de membre : M{$data['id']}
                - Date d'inscription : {$memberSignupDate}

                TEXT;
            sendMail($data['email'], $subject, $body, $altBody);
            header('Location: /login.php?confirmed');
            $SQL2 = <<<SQL
                UPDATE newsletter_mails SET confirm=true, lastmail=:last WHERE mail=:mail
                SQL;
            $req2 = $bdd->prepare($SQL2);
            $req2->execute([':last' => time(), ':mail' => $data['email']]);
            exit();
        }
    }
}
header('Location: /login.php?confirm_err');
exit();
