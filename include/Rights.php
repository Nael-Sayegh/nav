<?php

declare(strict_types=1);

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

/**
 * @return int[]|string[]
 */
function getRights(string $role, ?string $rawJson = null): array
{
    global $login;

    if ($rawJson === null)
    {
        $fieldName = $role === 'admin' ? 'admin_rights' : 'member_rights';
        $rawJson   = $login[$fieldName] ?? '';
    }

    $all = $role === 'admin' ? ALL_ADMIN_RIGHTS : ALL_MEMBER_RIGHTS;
    $map = is_array($rawJson) ? $rawJson : json_decode((string)$rawJson, true);

    if (!is_array($map))
    {
        return array_keys($all);
    }

    $granted = [];
    foreach (array_keys($all) as $key)
    {
        if (!array_key_exists($key, $map) || (bool) $map[$key])
        {
            $granted[] = $key;
        }
    }

    return $granted;
}

function getAdminRights(?string $rawJson = null): array
{
    return getRights('admin', $rawJson);
}

function getMemberRights(?string $rawJson = null): array
{
    return getRights('member', $rawJson);
}

function checkRights(string $role, string $right): bool
{
    return in_array($right, getRights($role), true);
}

function checkAdminRights(string $right): bool
{
    return checkRights('admin', $right);
}

function checkMemberRights(string $right): bool
{
    return checkRights('member', $right);
}

function requireRight(string $role, string $right): void
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

function requireAdminRight(string $right): void
{
    requireRight('admin', $right);
}

function requireMemberRight(string $right): void
{
    requireRight('member', $right);
}

function renderAdminMenu(array $structure): void
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
                echo '<td rowspan="'.$rowspan.'" role="heading" aria-level="3">'.htmlspecialchars((string) $cat).'</td>';
                $first = false;
            }

            printf(
                '<td><a href="%s">%s</a></td>',
                htmlspecialchars((string) $item['href']),
                htmlspecialchars((string) $item['label']),
            );
            echo '</tr>';
        }
    }

    echo '</tbody></table>';
}
