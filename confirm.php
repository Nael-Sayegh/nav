<?php

set_include_path($_SERVER['DOCUMENT_ROOT']);
$require_once('include/log.php');
require_once('include/consts.php');
require_once('include/sendMail.php');
$tr = load_tr($lang,'confirm');

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
            $subject = tr($tr,'mail_info_subject');
            $username = htmlentities((string) $data['username']);
            $memberSignupDate = date('d/m/Y à H:i', $data['signup_date']);
            $body = tr($tr,'mail_info_body_html', ['username' => $username,
                'email' => $data['email'],
                'id' => $data['id'],
                'signup_date' => $memberSignupDate]
            );
            $altBody = tr($tr,'mail_info_body_text', ['username' => $username,
                'email' => $data['email'],
                'id' => $data['id'],
                'signup_date' => $memberSignupDate]
            );
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
