<?php
function is_current(?string $path)
{
    if (!$path)
    {
        return false;
    }
    $current = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    $cur_norm  = rtrim($current, '/');
    $path_norm = rtrim($path, '/');
    return $cur_norm === $path_norm;
}

function render_menu(string $mode, array $items): void
{
    global $lang, $tr0, $args, $site_name;

    if ($mode === 'select')
    {
        echo '<form method="get">';
        echo args_html_form($args);
        echo '<select name="lang" autocomplete="off"aria-label="'.tr($tr0, 'menu_changelang').'"'.'title="'.    tr($tr0, 'menu_changelang').'">'.langs_html_opts($lang).'</select>
        <input type="submit" value="OK">
        </form>
        <form method="get" action="/nav_redirect.php">';
        echo args_html_form($args);
        echo '<label for="menu_select">'.tr($tr0, 'menu_linklistlabel').'</label><select name="d" id="menu_select" onkeypress="redirect(event,this);">';
        $openGroup = false;
        foreach ($items as $item)
        {
            switch ($item['type'] ?? 'link')
            {
                case 'menutitle':
                    if ($openGroup)
                    {
                        echo '</optgroup>';
                    }
                    echo '<optgroup label="'.tr($tr0, $item['label']).'">';
                    $openGroup = true;
                    break;

                case 'separator':
                    if ($openGroup)
                    {
                        echo '</optgroup>';
                        $openGroup = false;
                    }
                    break;

                case 'link':
                default:
                    $sel   = is_current($item['url'])
                           ? ' selected aria-current="page"'
                           : '';
                    if (isset($item['raw_label']))
                    {
                        $label = htmlspecialchars($item['raw_label']);
                    }
                    else
                    {
                        $label = tr($tr0, $item['label'], $item['params'] ?? []);
                    }
                    $title = htmlspecialchars($item['params']['title'] ?? '');
                    printf(
                        '<option value="%s"%s title="%s">%s</option>',
                        $item['url'],
                        $sel,
                        $title,
                        $label,
                    );
                    break;
            }
        }
        if ($openGroup)
        {
            echo '</optgroup>';
        }
        echo '</select><br><input type="submit" value="'.tr($tr0, 'menu_linklistlabelbutton').'"></form>';
    }
    else
    {
        $cls = $mode === 'listjs' ? 'ulmenu_js' : 'ulmenu_njs';
        echo sprintf('<ul role="menu" class="%s">', $cls);
        printf(
            '<li><form method="get">%s<select name="lang" autocomplete="off" aria-label="%s" title="%s">%s</select><input type="submit" value="OK"></form></li>',
            args_html_form($args),
            tr($tr0, 'menu_changelang'),
            tr($tr0, 'menu_changelang'),
            langs_html_opts($lang),
        );
        foreach ($items as $item)
        {
            switch ($item['type'] ?? 'link')
            {
                case 'menutitle':
                    printf(
                        '<li class="menutitle" role="separator" aria-disabled="true" aria-label="%s">%s</li>',
                        tr($tr0, $item['label']),
                        tr($tr0, $item['label']),
                    );
                    break;

                case 'separator':
                    echo '<li role="separator" class="menusep"><hr></li>';
                    break;

                case 'link':
                default:
                    $cur   = is_current($item['url']) ? ' aria-current="page"' : '';
                    if (isset($item['raw_label']))
                    {
                        $label = htmlspecialchars($item['raw_label']);
                    }
                    else
                    {
                        $label = tr($tr0, $item['label'], $item['params'] ?? []);
                    }
                    $title = htmlspecialchars($item['params']['title'] ?? '');
                    printf(
                        '<li role="link"%s><a role="menuitem" href="%s" title="%s">%s</a></li>',
                        $cur,
                        $item['url'],
                        str_replace('{{site}}', $site_name, $title),
                        $label,
                    );
                    break;
            }
        }
        echo '<li class="menusep" aria-hidden="true">&nbsp;</li>
        </ul>';
    }
}

$cats = get_categories();
$items = [];

$items[] = ['type' => 'link', 'url' => '/', 'label' => 'menu_homepage'];

$items[] = ['type' => 'menutitle', 'label' => 'menu_articles'];
foreach ($cats as $cat)
{
    $items[] = [
        'type'      => 'link',
        'url'       => '/c'.$cat['id'],
        'raw_label' => $cat['name'],
        'params'    => ['title' => $cat['title']],
    ];
}
$items[] = ['type' => 'menutitle', 'label' => 'menu_news'];

$items[] = ['type' => 'link', 'url' => '/newsletter.php', 'label' => 'menu_nl'];
$items[] = ['type' => 'link', 'url' => '/rss_feed.xml',  'label' => 'menu_rss'];
$items[] = ['type' => 'link', 'url' => '/history.php',   'label' => 'menu_journal'];
$items[] = ['type' => 'menutitle', 'label' => 'menu_usefull'];

foreach ([
    '/settings.php'     => 'menu_sets',
//    '/gadgets.php'      => 'menu_gadgets',
    '/contact.php'      => 'menu_contact',
//    '/contact_form.php' => 'menu_contact',
    '/privacy.php'      => 'menu_privacy',
] as $url => $key)
{
    $items[] = ['type' => 'link', 'url' => $url, 'label' => $key, 'params' => $params ?? []];
}
?>
<nav id="nav">
<h2 id="menusite"><?= tr($tr0, 'menu_menutitle') ?></h2>
<?php if (!empty($_COOKIE['menu']) && $_COOKIE['menu'] === '1'):
    render_menu('select', $items);
else: ?>
<div id="boutonjs">
<button type="button" id="popup_ulli_menu" onclick="rdisp('ulli_menu','popup_ulli_menu')" aria-haspopup="true" aria-expanded="false"><?= tr($tr0, 'menu_switchmenu') ?></button>
<div id="ulli_menu">
<?php render_menu('listjs', $items); ?>
</div>
</div>
<script>
    document.getElementById("boutonjs").style.display = "block";
    if (window.innerWidth <= 400)
    {
        rdisp("ulli_menu", "popup_ulli_menu");
    }
</script>
<noscript>
<details open>
<summary><?= tr($tr0, 'menu_switchmenu') ?></summary>
<?php render_menu('listnjs', $items); ?>
</details>
</noscript>
<?php endif; ?>
<a href="#hautpage" accesskey="h" class="sr_only"><?= tr($tr0, 'menu_toplink') ?></a>
</nav>
