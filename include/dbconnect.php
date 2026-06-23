<?php

declare(strict_types=1);

require_once __DIR__ . '/config.local.php';

try
{
    $bdd = new PDO(DB_STRING, DB_USER, DB_PSW);
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}
catch (PDOException $pdoException)
{
    print 'Erreur de connexion à la base de données 1';
    error_log('DB connect error: '.$pdoException->getMessage());
}
