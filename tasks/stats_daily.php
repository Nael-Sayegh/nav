<?php

declare(strict_types=1);

$document_root = __DIR__.'/..';
require_once($document_root.'/include/consts.php');

# delete visitors entries up to 1 week
$SQL = <<<SQL
    DELETE FROM count_visitors WHERE lastvisit<:last
    SQL;
$req = $bdd->prepare($SQL);
$req->execute([':last' => time() - 604800]);

# count daily visitors
$visitors = [];
$date = date('Y-m-d', strtotime('-1 day'));
$SQL = <<<SQL
    SELECT domain FROM count_visitors WHERE lastvisit BETWEEN :beg AND :end
    SQL;
$req = $bdd->prepare($SQL);
$req->execute([':beg' => time() - 86400, ':end' => time()]);
while ($data = $req->fetch())
{
    if (isset($visitors[$data['domain']]))
    {
        $visitors[$data['domain']][0]++;
    }
    else
    {
        $visitors[$data['domain']] = [1, $data['domain']];
    }
}

foreach ($visitors as &$visitor)
{
    $SQL = <<<SQL
        INSERT INTO daily_visitors (date,visitors,domain) VALUES (:date,:v,:d)
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':date' => $date, ':v' => $visitor[0], ':d' => $visitor[1]]);
}
