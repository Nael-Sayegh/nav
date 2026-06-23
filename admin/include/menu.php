<?php

declare(strict_types=1);

if (basename((string) $_SERVER['SCRIPT_NAME']) === 'index.php')
{

    $menu = [
        'Contenu utilisateur' => [
            ['href' => 'sw_mod.php',         'label' => 'Articles',              'right' => 'manage_content'],
            ['href' => 'translate_todo.php', 'label' => 'Traductions',          'right' => 'manage_translations'],
            ['href' => 'sw_categories.php',  'label' => 'Catégories',           'right' => 'manage_categories'],
            ['href' => 'cache_update.php',   'label' => 'Caches',               'right' => 'update_caches'],
        ],
        'Communication & actualités' => [
            ['href' => 'tickets.php',     'label' => 'Tickets',               'right' => 'manage_tickets'],
            ['href' => 'publication.php', 'label' => 'Publications sociales', 'right' => 'manage_publications'],
        ],
        'Lettre d’informations' => [
            ['href' => 'nl_send.php', 'label' => 'Lancer un envoi maintenant',     'right' => 'manage_newsletter'],
            ['href' => 'nl_last.php', 'label' => 'Réinitialiser date dernier envoi','right' => 'manage_newsletter'],
            ['href' => 'nl_list.php', 'label' => 'Voir les abonnés',               'right' => 'manage_newsletter'],
        ],
        'Contenu technique' => [
            ['href' => 'manage_sitemap.php','label' => 'Gérer le sitemap','right' => 'manage_sitemap'],
            ['href' => 'up_publish.php','label' => 'Versions','right' => 'publish_versions'],
            ['href' => 'showstats.php','label' => 'Statistiques','right' => 'view_stats'],
            ['href' => 'maintenance.php','label' => 'Maintenance','right' => 'maintenance'],
        ],
        'Communauté' => [
            ['href' => 'team_mgr.php',    'label' => 'Équipe',   'right' => 'manage_team'],
            ['href' => 'members_mgr.php','label' => 'Membres',  'right' => 'manage_members'],
        ],
        'Autre' => [
            ['href' => 'adminer/adminer.php','label' => 'Gestion BDD','right' => 'manage_db'],
            ['href' => 'techniques.php',     'label' => 'phpinfo()','right' => 'view_phpinfo'],
        ],
    ];

    if ((defined('CONTROLPANEL_URL') && ($cpUrl = constant('CONTROLPANEL_URL'))) || (defined('CONTROLPANEL_NAME') && ($cpName = constant('CONTROLPANEL_NAME'))))
    {
        $menu['Autre'][] = ['href'  => CONTROLPANEL_URL, 'label' => CONTROLPANEL_NAME, 'right' => 'access_control_panel'];
    }

    if ((defined('WEBMAIL_URL') && ($webMailUrl = constant('WEBMAIL_URL'))) || (defined('WEBMAIL_NAME') && ($webMailName = constant('WEBMAIL_NAME'))))
    {
        $menu['Autre'][] = ['href' => WEBMAIL_URL, 'label' => WEBMAIL_NAME, 'right' => 'access_webmail'];
    }

    renderAdminMenu($menu);
}

switch ($_SERVER['DOCUMENT_URI'])
{
    case '/admin/sw_mod.php':
        echo '<details><summary>Menu</summary><ul style="list-style-type: none;"><li><a href="sw_add.php">Ajouter un article</a></li><li><a href="sw_cat.php">Catégories</a></li><li><a href="translate_todo.php">Traductions</a></li><li><a href="cache_update.php">Caches</a></li></ul></details>';
        break;
    case '/admin/sw_add.php':
        echo '<details><summary>Menu</summary><ul style="list-style-type: none;"><li><a href="sw_mod.php">Modifier un article</a></li><li><a href="sw_cat.php">Catégories</a></li><li><a href="translate_todo.php">Traductions</a></li></ul></details>';
        break;
    case 'showstats.php':
    case 'nl_list.php':
        echo '<details><summary>Menu</summary><ul style="list-style-type: none;"><li><a href="nl_send.php">Envoyer la lettre d\'informations</a></li></ul></details>';
        break;
    case 'nl_send.php':
        echo '<details><summary>Menu</summary><ul style="list-style-type: none;"><li><a href="nl_list.php">Voir les inscrits à la lettre d\'informations</a></li></ul></details>';
        break;
    case 'up_publish.php':
        echo '<details><summary>Menu</summary><ul style="list-style-type: none;"><li><a href="cache_update.php">Caches</a></li></ul></details>';
        break;
    case 'members_gestion':
        echo '<details><summary>Menu</summary><ul style="list-style-type: none;"><li><a href="team_gestion.php">Gérer l\'équipe</a></li></ul></details>';
        break;
    case 'team_gestion.php':
        echo '<details><summary>Menu</summary><ul style="list-style-type: none;"><li><a href="members_gestion.php">Gérer les membres</a></li></ul></details>';
        break;
}
