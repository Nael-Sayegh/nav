<?php

declare(strict_types=1);

include_once __DIR__ . '/user_rank.php';

function getUnreadCount()
{
    global $bdd, $login;
    $SQL = <<<SQL
        SELECT COUNT(*) FROM notifs WHERE account=:account AND unread=true
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':account' => $login['id']]);
    return $req->fetchColumn();
}

function getDisplayName(): string
{
    global $login, $settings, $admin_name;
    $name = htmlentities((string) $login['username'], ENT_QUOTES, 'UTF-8');
    if ($login['rank'] === 'a')
    {
        $name .= sprintf(' (%s)', $admin_name);
    }

    $d = getdate();
    $isBd = isset($settings['bd_m'], $settings['bd_d']) && (($settings['bd_m'] == $d['mon'] && $settings['bd_d'] == $d['mday']) || ($settings['bd_m'] == 2 && $d['mon'] == 3 && $settings['bd_d'] == 29 && $d['mday'] == 1 && $d['year'] % 4 === 0));
    return $name . ($isBd ? ' 🎂' : '');
}

function buildUserMenu(int $nNotifs): array
{
    global $login, $tr0, $site_name;
    $displayName = getDisplayName();
    $items = [];

    if ($login['rank'] === 'a')
    {
        if (!str_contains((string) $_SERVER['PHP_SELF'], '/admin/index.php') && in_array($login['works'], ['1','2'], true))
        {
            $items[] = ['href' => '/admin', 'label' => tr($tr0, 'loginbox_adminlink').sprintf(' (%s)', $site_name)];
        }

        if (in_array($login['works'], ['0','2'], true))
        {
            $cid = urlencode((string) $_COOKIE['connectid']);
            $ses = urlencode((string) $_COOKIE['session']);
            $items[] = ['href' => sprintf('https://www.blog.nael-accessvision.com/admin?cid=%s&ses=%s', $cid, $ses), 'label' => tr($tr0, 'loginbox_adminlink').' (Blog nael-accessvision)'];
        }
    }

    if (checkMemberRights('view_members') || $login['rank'] === 'a')
    {
        $items[] = ['href' => '/members_list.php', 'label' => tr($tr0, 'loginbox_alistlink')];
    }

    $items[] = ['href' => '/home.php', 'label' => tr($tr0, 'loginbox_profilelink')];

    $notifLabel = $nNotifs > 0 ? '<strong>'.tr($tr0, 'loginbox_notifs_'.($nNotifs > 1 ? 'pl' : 'sg'), ['n' => $nNotifs]).'</strong>' : tr($tr0, 'loginbox_notifs');
    $items[] = ['href'  => '/home.php#notifs', 'label' => $notifLabel];

    $token = urlencode((string) $login['token']);
    $items[] = ['href' => '/logout.php?token=' . $token, 'label' => tr($tr0, 'loginbox_logoutlink').sprintf(' (%s)', $displayName)];

    return $items;
}

function renderLoginBox($login, $logged, $settings): void
{
    global $bdd, $site_name, $tr0;
    if (isset($logged) && $logged)
    {
        $nNotifs = getUnreadCount();
        $displayName = getDisplayName();

        $menuItems = buildUserMenu($nNotifs);
        echo sprintf('<div id="loginbox"><details><summary>%s</summary><ul>', $displayName);
        foreach ($menuItems as $menuItem)
        {
            echo '<li><a role="menuitem" class="hlink" href="'.$menuItem['href'].'">'.$menuItem['label'].'</a></li>';
        }

        echo '</ul></details></div>';
    }
    else
    {
        $redir = htmlspecialchars((string) $_SERVER['REQUEST_URI'], ENT_QUOTES);
        $memberArea = tr($tr0, 'loginbox_memberarea');
        $loginLabel = tr($tr0, 'loginbox_loginlabel');
        $usernameLabel = tr($tr0, 'loginbox_username');
        $passwordLabel = tr($tr0, 'loginbox_password');
        $loginBtnLabel = tr($tr0, 'loginbox_loginlabel');
        $fgpswLabel = tr($tr0, 'loginbox_forgotpsw');
        $signupLabel = tr($tr0, 'loginbox_signup');
        echo <<<HTML
            <div id="loginbox">
            <details>
            <summary>{$memberArea}</summary>
            <form action="/login.php?a=form" method="post" aria-label="{$loginLabel}">
            <input type="text" name="username" placeholder="{$usernameLabel}" maxlength="32" aria-label="{$usernameLabel}" required><br>
            <input type="password" name="psw" placeholder="{$passwordLabel}" maxlength="64" aria-label="{$passwordLabel}}" required><br>
            <input type="hidden" name="redirect" value="{$redir}">
            <button type="submit">{$loginBtnLabel}</button><br>
            <span><a class="hlink" href="/fg_password.php">{$fgpswLabel}</a> – <a class="hlink" href="/signup.php">{$signupLabel}</a></span>
            </form>
            </details>
            </div>
            HTML;
    }
}

renderLoginBox($login ?? [], $logged ?? false, $settings ?? []);
