<?php

$document_root = __DIR__.'/..';
require_once($document_root.'/include/consts.php');

$SESSION_EXPIRE = 8640000; // time after expiration to delete session (100 days)

// Update account rank
$SQL = <<<SQL
    SELECT * FROM accounts
    SQL;
foreach ($bdd->query($SQL) as $data)
{
    $change = false;
    $rank = $data['rank'];

    if ($data['rank'] === '0' && $data['signup_date'] + 1209600 < time())
    {
        $rank = '1';
        $change = true;
    }

    if ($change)
    {
        $SQL2 = <<<SQL
            UPDATE accounts SET rank=:rk WHERE id=:id
            SQL;
        $req2 = $bdd->prepare($SQL2);
        $req2->execute([':rk' => $rank, ':id' => $data['id']]);
    }
}

// Remove expired sessions
$SQL = <<<SQL
    DELETE FROM sessions WHERE expire<:exp
    SQL;
$req = $bdd->prepare($SQL);
$req->execute([':exp' => time() - $SESSION_EXPIRE]);
