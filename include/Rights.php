<?php

const ALL_ADMIN_RIGHTS = [
    'manage_content'         => 'Gérer le contenu utilisateur',
    'manage_translations'    => 'Gérer les traductions',
    'manage_categories'      => 'Gérer les catégories',
    'update_caches'          => 'Mettre à jour les caches',
    'manage_comments'        => 'Gérer les commentaires des articles',
    'manage_tickets'         => 'Gérer les tickets',
    'manage_publications'    => 'Gérer les publications sociales',
    'manage_newsletter'      => 'Gérer la lettre d\'informations',
    'publish_versions'       => 'Publier des versions',
    'view_stats'             => 'Voir les statistiques',
    'maintenance'            => 'Gérer le mode maintenance',
    'manage_team'            => 'Gérer l’équipe',
    'manage_members'         => 'Gérer les membres',
    'manage_db'              => 'Accéder à Adminer / Gérer les BDD',
    'access_control_panel'   => 'Accéder au panneau de contrôle du serveur',
    'access_webmail'         => 'Accéder au Webmail',
    'view_phpinfo'           => 'Voir phpinfo()',
    'manage_sitemap'         => 'Gérer le sitemap',
];

const ALL_MEMBER_RIGHTS = [
    'comment_articles'         => 'Commenter des articles',
    'rate_articles'         => 'Noter des articles',
    'view_members'         => 'Voir la liste des membres',
];

function getRights(string $role, ?string $rawJson = null)
{
    global $login;

    if ($rawJson === null)
    {
        $fieldName = $role === 'admin' ? 'admin_rights' : 'member_rights';
        $rawJson   = $login[$fieldName] ?? '';
    }

    $all = $role === 'admin' ? ALL_ADMIN_RIGHTS : ALL_MEMBER_RIGHTS;

    if (is_array($rawJson))
    {
        $map = $rawJson;
    }
    else
    {
        $map = json_decode((string)$rawJson, true);
    }
    if (!is_array($map))
    {
        return array_keys($all);
    }

    $granted = [];
    foreach ($all as $key => $_label)
    {
        if (!array_key_exists($key, $map) || (bool) $map[$key])
        {
            $granted[] = $key;
        }
    }
    return $granted;
}

function getAdminRights(?string $rawJson = null)
{
    return getRights('admin', $rawJson);
}

function getMemberRights(?string $rawJson = null)
{
    return getRights('member', $rawJson);
}

function checkRights(string $role, string $right)
{
    return in_array($right, getRights($role), true);
}

function checkAdminRights(string $right)
{
    return checkRights('admin', $right);
}

function checkMemberRights(string $right)
{
    return checkRights('member', $right);
}

function requireRight(string $role, string $right)
{
    if (!checkRights($role, $right))
    {
        http_response_code(403);
        if (session_status() !== PHP_SESSION_ACTIVE)
        {
            session_start();
        }

        $requested = $_SERVER['REQUEST_URI'];
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        $pathRef = parse_url((string) $referrer, PHP_URL_PATH) ?: '';
        $default = ($role === 'admin') ? '/admin/index.php' : '/index.php';
        $_SESSION['intended_403'] = ($pathRef && $pathRef !== $requested) ? $referrer : $default;

        $label = ($role === 'admin' ? ALL_ADMIN_RIGHTS : ALL_MEMBER_RIGHTS)[$right] ?? $right;
        require_once $_SERVER['DOCUMENT_ROOT'].'/403/403.php';
        exit();
    }
}

function requireAdminRight(string $right)
{
    requireRight('admin', $right);
}

function requireMemberRight(string $right)
{
    requireRight('member', $right);
}

function renderAdminMenu(array $structure)
{
    echo '<table><thead><tr><th>Catégorie</th><th>Option</th></tr></thead><tbody>';
    foreach ($structure as $cat => $items)
    {
        $rowspan = count($items);
        $first   = true;
        foreach ($items as $item)
        {
            if (!checkAdminRights($item['right']))
            {
                continue;
            }
            echo '<tr>';
            if ($first)
            {
                echo '<td rowspan="'.$rowspan.'" role="heading" aria-level="3">'.htmlspecialchars($cat).'</td>';
                $first = false;
            }
            printf(
                '<td><a href="%s">%s</a></td>',
                htmlspecialchars((string) $item['href']),
                htmlspecialchars((string) $item['label'])
            );
            echo '</tr>';
        }
    }
    echo '</tbody></table>';
}
