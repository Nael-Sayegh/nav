<?php

require_once 'config.local.php';

try
{
    $bdd = new PDO(DB_STRING, DB_USER, DB_PSW);
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}
catch (PDOException $e)
{
    print 'Erreur de connexion à la base de données 1';
    error_log('DB connect error: '.$e->getMessage());
}

try
{
    $bdd2 = new PDO(DB_STRING2, DB_USER2, DB_PSW2);
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}
catch (PDOException $e)
{
    print 'Erreur de connexion à la base de données 2';
    error_log('DB connect error: '.$e->getMessage());
}
