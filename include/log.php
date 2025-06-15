<?php

$logged = false;

require_once('Rights.php');

function check_login($session, $connectid)
{
    global $bdd, $bdd2, $login, $nolog, $settings, $admin_name;
    require_once($_SERVER['DOCUMENT_ROOT'].'/include/dbconnect.php');
    $SQL = <<<SQL
        SELECT sessions.id AS session_id, sessions.session, sessions.connectid, sessions.expire, sessions.token, accounts.id, accounts.id64, accounts.email, accounts.username, accounts.signup_date, accounts.password, accounts.settings, accounts.confirmed, accounts.subscribed_comments, accounts.rank, accounts.rights AS member_rights, accounts.twofa_enabled, accounts.twofa_secret, team.id AS team_id, team.works AS works, team.short_name AS short_name, team.rights AS admin_rights
        FROM sessions
        LEFT JOIN accounts ON accounts.id = sessions.account
        LEFT JOIN team ON team.account_id = sessions.account
        WHERE sessions.connectid=:connectid AND sessions.expire>:expire LIMIT 1
        SQL;
    $req = $bdd2->prepare($SQL);
    $req->execute([':connectid' => $connectid, ':expire' => time()]);
    if ($login = $req->fetch())
    {
        if (!isset($login['id']) || !$login['id'])
        {
            unset($login);
            return false;
        }

        if (password_verify((string) $session, (string) $login['session']))
        {
            if (isset($nolog) && $nolog)
            {
                $req->closeCursor();
                header('Location: /');
                exit();
            }
            $SQL = <<<SQL
                UPDATE sessions SET expire=:expire WHERE id=:id
                SQL;
            $req = $bdd2->prepare($SQL);
            $req->execute([':expire' => time() + 31557600, ':id' => $login['session_id']]);
            # check settings cookies
            $settings = json_decode((string) $login['settings'], true);
            $sets = ['menu', 'fontsize', 'audio', 'date', 'infosdef'];
            foreach ($sets as &$setting)
            {
                if (isset($settings[$setting]) && (!isset($_COOKIE[$setting]) || (isset($_COOKIE[$setting]) && $_COOKIE[$setting] !== $settings[$setting])))
                {
                    setcookie($setting, (string) $settings[$setting], ['expires' => time() + 31557600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'strict']);
                }
            }
            unset($setting);
            unset($sets);
            $login['member_rights'] ??= '';
            $login['works'] = isset($login['works']) ? (string)$login['works'] : '0';
            $admin_name = $login['short_name'] ?? '';
            $login['admin_rights'] ??= '';
            $req->closeCursor();
            return true;
        }
        else
        {
            unset($login);
        }
    }
    $req->closeCursor();
    return false;
}

if (isset($_COOKIE['session']) && isset($_COOKIE['connectid']))
{
    $logged = check_login($_COOKIE['session'], $_COOKIE['connectid']);
}

if (!$logged && isset($_GET['ses']) && isset($_GET['cid']))
{
    if ($logged = check_login($_GET['ses'], $_GET['cid']))
    {
        $expire = time() + 31557600;
        setcookie('session', (string) $_GET['ses'], ['expires' => $expire, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'strict']);
        setcookie('connectid', $_GET['cid'], ['expires' => $expire, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'strict']);
    }
}

if (!$logged && isset($logonly) && $logonly)
{
    if (session_status() !== PHP_SESSION_ACTIVE)
    {
        session_start();
    }
    $_SESSION['intended_after_login'] = $_SERVER['REQUEST_URI'];
    http_response_code(403);
    header('Location: /login.php?logonly');
    exit();
}
if ($logged && $login['rank'] === 'b' && basename((string) $_SERVER['SCRIPT_NAME']) !== 'contact_form.php')
{
    http_response_code(403);
    require_once($_SERVER['DOCUMENT_ROOT'].'/403/403B.php');
    exit();
}
if (isset($adminonly) && $adminonly && $login['rank'] !== 'a')
{
    http_response_code(403);
    require_once($_SERVER['DOCUMENT_ROOT'].'/403/403.php');
    exit();
}
if (isset($justpa) && $justpa && $login['works'] === '0')
{
    header('Location: https://www.nael-accessvision.com/admin/');
    exit();
}
