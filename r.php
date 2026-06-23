<?php

declare(strict_types=1);

require_once(__DIR__ . '/include/log.php');
require_once(__DIR__ . '/include/consts.php');
if (!empty($_SERVER['HTTP_REFERER']) && substr_count('commentcamarche.net', (string) $_SERVER['HTTP_REFERER']) > 0)
{
    header('Location: /');
    exit();
}

if (isset($_GET['id']) && $_GET['id'] !== '')
{
    require_once(__DIR__ . '/include/dbconnect.php');

    if (isset($_GET['m']))
    {
        $SQL = <<<SQL
            SELECT * FROM softwares_mirrors WHERE id=:id
            SQL;
        $req = $bdd->prepare($SQL);
        $req->execute([':id' => $_GET['id']]);
        if ($data = $req->fetch())
        {
            $links = json_decode((string) $data['links'], true);
            if (empty($_GET['m']))
            {
                header('Location: '.$links[random_int(0, count($links) - 1)][1]);
                exit();
            }

            header('Location: '.$links[intval($_GET['m'])][1]);
            exit();
        }

        echo 'Erreur: Miroir introuvable';

        $req->closeCursor();
    }
    else
    {
        $SQL = <<<SQL
            SELECT * FROM softwares_files WHERE id=:id
            SQL;
        $req = $bdd->prepare($SQL);
        $req->execute([':id' => $_GET['id']]);
        if ($data = $req->fetch())
        {
            $file = fopen('files/'.$data['hash'], 'rb');
            if (false === $file)
            {
                exit('Erreur grave: Fichier inexistant');
            }

            header('Content-type: '.$data['filetype']);
            header('Content-Disposition: attachment; filename="'.str_replace('"', '', $data['name']).'"');
            header('Content-Length: '.$data['filesize']);
            while (!feof($file))
            {
                echo fread($file, 8192);
            }

            fclose($file);
            require_once(__DIR__ . '/include/isbot.php');
            if (!(isset($_COOKIE['admincookie_nostats']) && $_COOKIE['admincookie_nostats'] === 'f537856b32e9e5e0418b224167576240') && !$isbot)
            {
                $SQL2 = <<<SQL
                    UPDATE softwares_files SET hits=hits+1, total_hits=total_hits+1 WHERE id=:id
                    SQL;
                $req2 = $bdd->prepare($SQL2);
                $req2->execute([':id' => $_GET['id']]);
                $SQL2 = <<<SQL
                    UPDATE softwares SET downloads=downloads+1 WHERE id=:id
                    SQL;
                $req2 = $bdd->prepare($SQL2);
                $req2->execute([':id' => $data['sw_id']]);
            }

            exit();
        }

        echo 'Erreur: Fichier introuvable';

        $req->closeCursor();
    }
}
elseif (isset($_GET['p']) && $_GET['p'] !== '')
{
    require_once(__DIR__ . '/include/dbconnect.php');

    if (isset($_GET['m']))
    {
        $SQL = <<<SQL
            SELECT * FROM softwares_mirrors WHERE label=:lbl
            SQL;
        $req = $bdd->prepare($SQL);
        $req->execute([':lbl' => $_GET['p']]);
        if ($data = $req->fetch())
        {
            $links = json_decode((string) $data['links'], true);
            if (empty($_GET['m']))
            {
                header('Location: '.$links[random_int(0, count($links) - 1)][1]);
                exit();
            }

            header('Location: '.$links[intval($_GET['m'])][1]);
            exit();
        }

        echo 'Erreur: Miroir introuvable';

        $req->closeCursor();
    }
    else
    {
        $SQL = <<<SQL
            SELECT * FROM softwares_files WHERE label=:lbl LIMIT 1
            SQL;
        $req = $bdd->prepare($SQL);
        $req->execute([':lbl' => $_GET['p']]);
        if ($data = $req->fetch())
        {
            $file = fopen('files/'.$data['hash'], 'rb');
            if (false === $file)
            {
                exit('Erreur grave: Fichier inexistant');
            }

            header('Content-type: '.$data['filetype']);
            header('Content-Disposition: attachment; filename="'.str_replace('"', '', $data['name']).'"');
            header('Content-Length: '.$data['filesize']);
            while (!feof($file))
            {
                echo fread($file, 8192);
            }

            fclose($file);
            require_once(__DIR__ . '/include/isbot.php');
            if (!(isset($_COOKIE['admincookie_nostats']) && $_COOKIE['admincookie_nostats'] === 'f537856b32e9e5e0418b224167576240') && !$isbot)
            {
                $SQL2 = <<<SQL
                    UPDATE softwares_files SET hits=hits+1, total_hits=total_hits+1 WHERE id=:id
                    SQL;
                $req2 = $bdd->prepare($SQL2);
                $req2->execute([':id' => $data['id']]);
                $SQL2 = <<<SQL
                    UPDATE softwares SET downloads=downloads+1 WHERE id=:id
                    SQL;
                $req2 = $bdd->prepare($SQL2);
                $req2->execute([':id' => $data['sw_id']]);
            }

            exit();
        }

        echo 'Erreur: Fichier introuvable';

        $req->closeCursor();
    }
}
else
{
    header('Location: /');
    exit();
}
