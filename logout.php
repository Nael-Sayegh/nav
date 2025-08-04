<?php

require_once('include/dbconnect.php');
$logonly = true;
require_once('include/log.php');

if (isset($_GET['token']) && $_GET['token'] === $login['token'])
{
    setcookie('session', '', ['expires' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'strict']);
    setcookie('connectid', '', ['expires' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'strict']);
    $SQL = <<<SQL
        UPDATE sessions SET expire=:exp WHERE id=:id
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':exp' => time() - 1, ':id' => $login['session_id']]);
    header('Location: /');
    exit();
}
else
{
    header('Location: /');
    exit();
}
exit();
