<?php

// Chargement de la configuration avec fallback
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
} else {
    require_once __DIR__ . '/config.php';
}

try
{
    $bdd = new PDO(DB_STRING, DB_USER, DB_PSW);
    $bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}
catch (PDOException $e)
{
    error_log('DB connect error: '.$e->getMessage());
    throw new Exception('Erreur de connexion à la base de données: ' . $e->getMessage());
}
