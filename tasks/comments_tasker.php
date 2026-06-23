<?php

declare(strict_types=1);

$document_root = __DIR__.'/..';
require_once($document_root.'/include/consts.php');

// supprimer les IPs de plus de 28 jours
$SQL = <<<SQL
    UPDATE softwares_comments SET ip="rm" WHERE date<:exp
    SQL;
$req = $bdd->prepare($SQL);
$req->execute([':exp' => time() - 2419200]);
