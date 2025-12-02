<?php

declare(strict_types=1);

require_once(__DIR__ . '/dbconnect.php');

$domain = '';
if (!isset($stats_page))
{
    $stats_page = '';
}

if (isDev())
{
    $domain = 'dev';
}
elseif (!isDev())
{
    $domain = 'prod';
}
elseif (isDev() && strstr($_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'], ONION_DOMAIN))
{
    $domain = 'onion_dev';
}
elseif (!isDev() && strstr($_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'], ONION_DOMAIN))
{
    $domain = 'onion';
}

require_once(__DIR__ . '/isbot.php');
if (!(isset($logged) && $logged && $login['rank'] === 'a') && !$isbot)
{
    $SQL = <<<SQL
        SELECT id FROM count_visits WHERE date=CURRENT_DATE AND page=:page AND domain=:domain LIMIT 1
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':page' => $stats_page, ':domain' => $domain]);
    if ($data = $req->fetch())
    {
        $SQL = <<<SQL
            UPDATE count_visits SET visits=visits+1 WHERE date=CURRENT_DATE AND page=:page AND domain=:domain
            SQL;
        $req = $bdd->prepare($SQL);
        $req->execute([':page' => $stats_page, ':domain' => $domain]);
    }
    else
    {
        $SQL = <<<SQL
            INSERT INTO count_visits(date,visits,page,domain) VALUES(CURRENT_DATE,1,:page,:domain)
            SQL;
        $req = $bdd->prepare($SQL);
        $req->execute([':page' => $stats_page, ':domain' => $domain]);
    }

    $SQL = <<<SQL
        SELECT id FROM count_visitors WHERE addr=:addr AND domain=:domain LIMIT 1
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':addr' => sha1((string) $_SERVER['REMOTE_ADDR']), ':domain' => $domain]);
    if ($data = $req->fetch())
    {
        $SQL = <<<SQL
            UPDATE count_visitors SET lastvisit=:lastvisit WHERE addr=:addr AND domain=:domain
            SQL;
        $req = $bdd->prepare($SQL);
        $req->execute([':lastvisit' => time(), ':addr' => sha1((string) $_SERVER['REMOTE_ADDR']), ':domain' => $domain]);
    }
    else
    {
        $SQL = <<<SQL
            INSERT INTO count_visitors(addr,lastvisit,domain) VALUES(:addr,:lastvisit,:domain)
            SQL;
        $req = $bdd->prepare($SQL);
        $req->execute([':addr' => sha1((string) $_SERVER['REMOTE_ADDR']), ':lastvisit' => time(), ':domain' => $domain]);
    }
}

if (!isset($stats_no))
{
    $date = date('Y-m-d');
    $xpage = 0;
    $xpagetoday = 0;
    $xvisits = 0;
    $xvisitstoday = 0;
    $SQL = <<<SQL
        SELECT page,date,visits FROM count_visits WHERE domain=:domain AND date>:date
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':domain' => $domain, ':date' => date('Y-m-d H:i:s', time() - 31557600)]);
    while ($data = $req->fetch())
    {
        if ($data['page'] === $stats_page)
        {
            $xpage += $data['visits'];
            if ($data['date'] === $date)
            {
                $xpagetoday += $data['visits'];
            }
        }

        $xvisits += $data['visits'];
        if ($data['date'] === $date)
        {
            $xvisitstoday += $data['visits'];
        }
    }

    $xvisitors = 0;
    $xconn = 0;
    $xtoday = 0;
    $SQL = <<<SQL
        SELECT lastvisit FROM count_visitors WHERE domain=:domain
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':domain' => $domain]);
    while ($data = $req->fetch())
    {
        if ($data['lastvisit'] > strtotime('midnight'))
        {
            $xtoday++;
        }

        if ($data['lastvisit'] > time() - 600)
        {
            $xconn++;
        }

        $xvisitors++;
    }

    echo '<ul id="compteur">
	<li>'.tr($tr0, 'footer_charged_page', ['xpage' => $xpage,'xpagetoday' => $xpagetoday]).'</li>
	<li>'.tr($tr0, 'footer_total_chaged_pages', ['xvisits' => $xvisits,'xvisitstoday' => $xvisitstoday]).'</li>
	<li>'.tr($tr0, 'footer_visitors', ['xvisitors' => $xvisitors,'xtoday' => $xtoday]).'</li>
	<li>'.tr($tr0, 'footer_connected', ['xconn' => $xconn]).'</li></ul>';
}

$req->closeCursor();
