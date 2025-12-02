<?php
$stats_page = 'article';
if (!isset($_GET['id']))
{
    header('Location: /art_list.php');
    exit();
}
set_include_path($_SERVER['DOCUMENT_ROOT']);
require_once(__DIR__ . '/include/log.php');
require_once(__DIR__ . '/include/consts.php');
require_once(__DIR__ . '/include/isbot.php');
require_once(__DIR__ . '/include/package_managers.php');
require_once(__DIR__ . '/include/sendMail.php');
require_once(__DIR__ . '/include/lib/MDConverter.php');
if (filter_var($_GET['id'], FILTER_VALIDATE_INT) !== false)
{
    $SQL = <<<SQL
        SELECT * FROM softwares WHERE id=:id
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':id' => $_GET['id']]);
    $sw = $req->fetch();
    if (!$sw)
    {
        header('Location: /art_list.php');
        exit();
    }
}
else
{
    header('Location: /art_list.php');
    exit();
}
if (!(isset($logged) && $logged && $login['rank'] === 'a') && !$isbot)
{
    $SQL = <<<SQL
        UPDATE softwares SET hits=hits+1 WHERE id=:id
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':id' => $sw['id']]);
}
$SQL = <<<SQL
    SELECT * FROM softwares_tr WHERE sw_id=:sw_id AND lang=:lang AND published=true LIMIT 1
    SQL;
$req = $bdd->prepare($SQL);
$req->execute([':sw_id' => $sw['id'], ':lang' => $lang]);
if (!$sw_tr = $req->fetch())
{
    foreach ($langs_prio as &$i)
    {
        $SQL = <<<SQL
            SELECT * FROM softwares_tr WHERE sw_id=:sw_id AND lang=:lang AND published=true LIMIT 1
            SQL;
        $req = $bdd->prepare($SQL);
        $req->execute([':sw_id' => $sw['id'], ':lang' => $i]);
        if ($sw_tr = $req->fetch())
        {
            break;
        }
    }
    unset($i);
    if (!$sw_tr)
    {
        header('Location: /?sw_tr_error');
        exit();
    }
}

$tr = load_tr($lang, 'article');
$args['id'] = $sw['id'];
$title = str_replace('{{site}}', $site_name, $sw_tr['name']);

function getSoftwareFiles($swId)
{
    global $bdd;
    $SQL = <<<SQL
        SELECT * FROM softwares_files WHERE sw_id=:swid ORDER BY date DESC
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':swid' => $swId]);
    return $req->fetchAll();
}

/*function canManageComment(array $comment)
{
    global $logged, $login;
    if (empty($logged) || !$logged)
    {
        return false;
    }
    if ($comment['nickname'] === $login['id'] && $comment['date'] > time() - 86400 && checkMemberRights('comment_articles'))
    {
        return true;
    }
    if ($login['rank'] === 'a' && in_array($login['works'], ['1','2'], true) && checkAdminRights('manage_comments'))
    {
        return true;
    }
    return false;
}

$comlog = '';
if (isset($_GET['comment']) && isset($_POST['text']) && isset($logged) && $logged && checkMemberRights('comment_articles'))
{
    if (strlen((string) $_POST['text']) <= 1023)
    {
        $SQL = <<<SQL
            INSERT INTO softwares_comments(sw_id,date,nickname,text) VALUES(:sw_id,:date,:nickname,:text)
            SQL;
        $req = $bdd->prepare($SQL);
        $req->execute([':sw_id' => $sw['id'], ':date' => time(), ':nickname' => $login['id'], ':text' => $_POST['text']]);
        $comlog = tr($tr, 'comment_sent');
        header('Location: /a'.$sw['id']);

        # Add notification to subscribers
        $notif = json_encode(['type' => 'new_comment', 'article' => $sw['id']]);
        $insertNotifSQL = <<<SQL
            INSERT INTO notifs (date, account, data) VALUES (:date, :account, :data)
            SQL;
        $insertNotif = $bdd->prepare($insertNotifSQL);
        $sql = <<<SQL
            SELECT accounts.id, accounts.rank, accounts.subscribed_comments, subscriptions_comments.id AS is_sub
            FROM accounts
            LEFT JOIN subscriptions_comments
            ON subscriptions_comments.account = accounts.id
            AND subscriptions_comments.article = :swid
            SQL;
        $req2 = $bdd->prepare($sql);
        $req2->execute(['swid' => $sw['id']]);
        while ($row = $req2->fetch())
        {
            $isMember = $row['rank'] !== 'a';
            $isSubscribed = (bool)$row['subscribed_comments'] || (bool)$row['is_sub'];
            if (($isMember && $isSubscribed) || (!$isMember))
            {
                $insertNotif->execute(['date' => time(), 'account' => $row['id'], 'data' => $notif]);
            }
        }
        $subject = sprintf('Nouveau commentaire sur "%s"', $sw_tr['name']);
        $link    = sprintf('%s/a%d', SITE_URL, $sw['id']);
        $body    = <<<HTML
            <h2>{$login['username']} a commenté "{$sw_tr['name']}"</h2>
            <p>{$login['username']} a posté:</p>
            <blockquote>{$_POST['text']}</blockquote>
            <p><a href="{$link}">Voir la discussion</a></p>
            HTML;
        $altBody = <<<TEXT
            {$login['username']} a commenté "{$sw_tr['name']}"
            {$_POST['text']}
            Voir: {$link}
            TEXT;

        $emails = [];
        $SQL = <<<SQL
            SELECT accounts.email FROM accounts JOIN subscriptions_comments ON subscriptions_comments.account = accounts.id AND subscriptions_comments.article = :art WHERE (accounts.settings::jsonb)->>'notif_mail' = 'true' AND accounts.rank <> 'a'
            SQL;
        $req = $bdd->prepare($SQL);
        $req->execute([':art' => $sw['id']]);
        while ($row = $req->fetch())
        {
            $emails[$row['email']] = true;
        }
        $req = $bdd->query("SELECT email FROM accounts WHERE rank = 'a' AND (accounts.settings::jsonb)->>'notif_mail' = 'true'");
        while ($row = $req->fetch())
        {
            $emails[$row['email']] = true;
        }
        if (!empty($emails))
        {
            sendMail(array_keys($emails), $subject, $body, $altBody);
        }
        exit();
    }
    else
    {
        $comlog = tr($tr, 'comment_toolong');
    }
}
if (isset($_GET['cdel']))
{
    $req = $bdd->prepare('SELECT * FROM softwares_comments WHERE id=:id LIMIT 1');
    $req->execute(['id' => $_GET['cdel']]);
    $comment = $req->fetch(PDO::FETCH_ASSOC);
    if ($comment && canManageComment($comment))
    {
        $del = $bdd->prepare('DELETE FROM softwares_comments WHERE id=:id');
        $del->execute(['id' => $_GET['cdel']]);
    }
}
if (isset($_GET['cedit2']) && isset($_POST['text']))
{
    $req = $bdd->prepare('SELECT * FROM softwares_comments WHERE id=:id LIMIT 1');
    $req->execute(['id' => $_GET['cedit2']]);
    $comment = $req->fetch(PDO::FETCH_ASSOC);
    if ($comment && canManageComment($comment))
    {
        $newText = (string) $_POST['text'];
        if (mb_strlen($newText) <= 1023)
        {
            $upd = $bdd->prepare('UPDATE softwares_comments SET text=:text WHERE id=:id');
            $upd->execute(['text' => $newText, 'id'   => $comment['id']]);
            header('Location: /a'.$sw['id']);
            exit();
        }
        else
        {
            $comlog = tr($tr, 'commentmod_toolong');
        }
    }
    else
    {
        $comlog = tr($tr, 'commentmod_error');
    }
    $req->closeCursor();
}
if (isset($_GET['subscribe-comments']) && isset($_GET['token']) && isset($logged) && $logged && $_GET['token'] === $login['token'] && $login['rank'] !== 'a')
{
    $SQL = <<<SQL
        SELECT id FROM subscriptions_comments WHERE account=:acc AND article=:id LIMIT 1
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':acc' => $login['id'], ':id' => $sw['id']]);
    if (!$req->fetch())
    {
        $SQL = <<<SQL
            INSERT INTO subscriptions_comments (account, article) VALUES (:acc, :id)
            SQL;
        $req = $bdd->prepare($SQL);
        $req->execute([':acc' => $login['id'], ':id' => $sw['id']]);
    }
}
elseif (isset($_GET['unsubscribe-comments']) && isset($_GET['token']) && isset($logged) && $logged && $_GET['token'] === $login['token'] && $login['rank'] !== 'a')
{
    $SQL = <<<SQL
        DELETE FROM subscriptions_comments WHERE account=:acc AND article=:id
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':acc' => $login['id'], ':id' => $sw['id']]);
}
if (isset($_GET['rate']) && !empty($_POST['rating']) && isset($logged) && $logged && checkMemberRights('rate_articles'))
{
    $r = (int) $_POST['rating'];
    $sql = <<<SQL
        INSERT INTO softwares_ratings (sw_id, account, rating)
        VALUES (:sw, :acc, :r)
        ON CONFLICT (sw_id, account) DO UPDATE
        SET rating = EXCLUDED.rating
        SQL;
    $req = $bdd->prepare($sql);
    $req->execute([':sw'  => $sw['id'], ':acc' => $login['id'], ':r' => $r]);
    header("Location: /a{$sw['id']}");
    exit();
}
$statsSQL = <<<SQL
    SELECT rating_count, rating_avg FROM softwares WHERE id = :sw
    SQL;
$statsReq = $bdd->prepare($statsSQL);
$statsReq->execute([':sw' => $sw['id']]);
[$rating_count, $rating_avg] = $statsReq->fetch(PDO::FETCH_NUM);
$user_rating = null;
if (isset($logged) && $logged && checkMemberRights('rate_articles'))
{
    $urSQL = <<<SQL
        SELECT rating FROM softwares_ratings
        WHERE sw_id = :sw AND account = :acc
        SQL;
    $urReq = $bdd->prepare($urSQL);
    $urReq->execute([':sw' => $sw['id'], 'acc' => $login['id']]);
    $val = $urReq->fetchColumn();
    $user_rating = ($val === false ? null : (int) $val);
}
if (isset($_GET['deleterating']) && isset($logged) && $logged && checkMemberRights('rate_articles'))
{
    $SQL = <<<SQL
        DELETE FROM softwares_ratings WHERE sw_id = :sw AND account = :acc
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':sw' => $sw['id'], 'acc' => $login['id']]);
    header("Location: /a{$sw['id']}");
    exit();
}*/
$catMap = [];
$SQL = <<<SQL
    SELECT id, name FROM softwares_categories
    SQL;
foreach ($bdd->query($SQL) as $data)
{
    $catMap[$data['id']] = $data['name'];
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<?php require_once(__DIR__ . '/include/header.php'); ?>
<body>
<?php require_once(__DIR__ . '/include/banner.php'); ?>
<main id="container">
<h1 id="contenu"><?php print $title; ?></h1>
<?php
if (isset($logged) && $logged && $login['rank'] === 'a' && in_array($login['works'], ['1', '2'], true) && checkAdminRights('manage_comments'))
{ ?>
<ul>
<li><a href="/admin/sw_mod.php?id=<?= $sw['id'] ?>"><?= str_replace('{{title}}', $title, tr($tr, 'adminlink_article').' '.$sw['name']) ?></a></li>
<li><a href="/admin/sw_mod.php?listfiles=<?= $sw['id'] ?>"><?= str_replace('{{title}}', $title, tr($tr, 'adminlink_listfiles').' '.$sw['name']) ?></a></li>
<li><a href="/admin/translate.php?type=article&id=<?= $sw['id'] ?>"><?= str_replace('{{title}}', $title, tr($tr, 'adminlink_trs').' '.$sw['name']) ?></a></li>
</ul>
<?php
}
/*if (isset($logged) && $logged && $login['rank'] !== 'a')
{
    $SQL = <<<SQL
        SELECT id FROM subscriptions_comments WHERE account=:acc AND article=:id LIMIT 1
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':acc' => $login['id'], ':id' => $sw['id']]);
    $sub = $req->fetch() ? true : false;
    echo '<a id="btunsub1" class="comments_btsubscription" href="?id='.$sw['id'].'&unsubscribe-comments&token='.$login['token'].'" title="'.tr($tr, 'comments_unsubscribe_long').'" onclick="subscribe_comments(event, false)" style="display:'.($sub ? 'initial' : 'none').'">'.tr($tr, 'comments_unsubscribe').'</a>';
    echo '<a id="btsub1" class="comments_btsubscription" href="?id='.$sw['id'].'&subscribe-comments&token='.$login['token'].'" title="'.tr($tr, 'comments_subscribe_long').'" onclick="subscribe_comments(event, true)" style="display:'.($sub ? 'none' : 'initial').'">'.tr($tr, 'comments_subscribe').'</a>';
}*/
echo '<div id="descart" role="article">'.convertToMD(str_replace('{{site}}', $site_name, $sw_tr['text'])).'</div>';
$fichiersexistants = false;
$first = true;
$altc = true;
$files = getSoftwareFiles($sw['id']);
foreach ($files as $data)
{
    if ($first)
    {
        ?>
<span class="sr_only" role="heading" aria-level="2" aria-labelledby="filestitle"></span>
<table id="sw_files">
<caption><strong id="filestitle"><?= tr($tr, 'files_title', ['title' => $title]) ?></strong></caption>
<thead>
<tr>
<th><?= tr($tr, 'files_size') ?></th>
<th><?= tr($tr, 'files_platform') ?></th>
<th><?= tr($tr, 'files_date') ?></th>
<th><?= tr($tr, 'files_hits') ?></th>
<th>MD5, SHA1</th>
</tr>
</thead>
<tbody>
<?php
        $fichiersexistants = true;
        $first = false;
    }
    echo sprintf(
        '<tr class="sw_file%s" data-date="%d" data-hits="%d" data-filesize="%d" data-title="%s" data-name="%s">',
        $altc ? ' altc' : '',
        $data['date'],
        $data['hits'],
        $data['filesize'],
        htmlspecialchars((string) $data['title']),
        htmlspecialchars((string) $data['name']),
    );
    echo '<td class="sw_file_ltd"><a class="sw_file_link" href="/dl/';
    if (empty($data['label']))
    {
        echo $data['id'];
    }
    else
    {
        echo $data['label'];
    }
    echo '">'.str_replace('{{site}}', $site_name, $data['title']).'</a> <span class="sw_file_size">('.numberlocale(human_filesize($data['filesize'])).tr($tr0, 'byte_letter').')</span></td><td>'.($data['platform'] ?? '').' '.($ARCHS[$data['arch']] ?? '').'</td><td class="sw_file_date">'.getFormattedDate($data['date'], tr($tr0, 'fndatetime')).'</td><td class="sw_file_hits">'.tr($tr, 'count_dl', ['dl' => $data['hits'],'total_dl' => $data['total_hits']]).'</td><td><details aria-label="'.tr($tr, 'files_sums').'" title="'.tr($tr, 'files_sums').'"><summary class="sw_file_sum">'.$data['name'].'</summary>md5: '.$data['md5'].'<br>sha1: '.$data['sha1'].'</details></tr>';
    $altc = !$altc;
}
if (!$first)
{
    echo '</tbody></table>';
}

$first = true;
$altc = true;
$SQL = <<<SQL
    SELECT * FROM softwares_mirrors WHERE sw_id=:swid ORDER BY hits DESC
    SQL;
$req = $bdd->prepare($SQL);
$req->execute([':swid' => $sw['id']]);
while ($data = $req->fetch())
{
    if ($first)
    {
        echo '<span class="sr_only" role="heading" aria-level="2" aria-labelledby="mirrorstitle"></span><table id="sw_mirrors"><caption><strong id="mirrorstitle">'.tr($tr, 'mirrors_title', ['title' => $title]).'</strong></caption><thead><tr><th>'.tr($tr, 'mirrors_filetitle').'</th><th>'.tr($tr, 'mirrors_mirrors').'</th><th>'.tr($tr, 'files_date').'</th><th>'.tr($tr, 'files_hits').'</th></tr></thead><tbody>';
        $first = false;
    }
    echo '<tr class="sw_file';
    if ($altc)
    {
        echo ' altc';
    }
    echo '"><td class="sw_file_title"><a class="sw_file_link" href="/r.php?m&';
    if (empty($data['label']))
    {
        echo 'id='.$data['id'];
    }
    else
    {
        echo 'p='.$data['label'];
    }
    echo '">'.str_replace('{{site}}', $site_name, $data['title']).'</a></td><td class="sw_file_ltd">';
    $i = 0;
    $links = json_decode((string) $data['links'], true);
    foreach ($links as $link)
    {
        if ($i !== 0)
        {
            echo ' | ';
        }
        echo '<a class="sw_file_link" href="/r.php?m='.$i.'&';
        if (empty($data['label']))
        {
            echo 'id='.$data['id'];
        }
        else
        {
            echo 'p='.$data['label'];
        }
        echo '">'.$link[0].'</a>';
        $i++;
    }
    echo '</td><td class="sw_file_date">'.getFormattedDate($data['date'], tr($tr0, 'fndatetime')).'</td><td class="sw_file_hits">'.$data['hits'].'</td></tr>';
    $altc = !$altc;
}
if (!$first)
{
    echo '</tbody></table>';
}

$first = true;
$altc = true;
$SQL = <<<SQL
    SELECT * FROM softwares_packages WHERE sw_id=:swid
    SQL;
$req = $bdd->prepare($SQL);
$req->execute([':swid' => $sw['id']]);
while ($data = $req->fetch())
{
    if ($first)
    {
        echo '<span class="sr_only" role="heading" aria-level="2" aria-labelledby="packagestitle"></span><table id="sw_packages"><caption><strong id="packagestitle">'.tr($tr, 'packages_title', ['title' => $title]).'</strong></caption><thead><tr><th>'.tr($tr, 'packages_platform').'</th><th>'.tr($tr, 'packages_manager').'</th><th>'.tr($tr, 'packages_name').'</th><th>'.tr($tr, 'packages_comment').'</th></tr></thead><tbody>';
        $first = false;
    }
    echo '<tr class="sw_file';
    if ($altc)
    {
        echo ' altc';
    }
    echo '"><td>'.$PACKAGE_MANAGERS[$data['manager']]['platforms'].'</td><td>'.$PACKAGE_MANAGERS[$data['manager']]['name'].'</td><td class="sw_file_title"><a class="sw_file_link" href="'.str_replace('{}', $data['name'], $PACKAGE_MANAGERS[$data['manager']]['package_url']).'">'.$data['name'].'</a></td>';
    if (!empty($data['comment']) || array_key_exists('install_cmd', $PACKAGE_MANAGERS[$data['manager']]))
    {
        echo '<td><details><summary>'.tr($tr, 'packages_info').'</summary>';
        if (!empty($data['comments']))
        {
            echo '<p>'.$data['comments'].'</p>';
        }
        if (array_key_exists('install_cmd', $PACKAGE_MANAGERS[$data['manager']]))
        {
            echo '<p>'.tr($tr, 'packages_install_cmd').'</p><pre>'.str_replace('{}', $data['name'], $PACKAGE_MANAGERS[$data['manager']]['install_cmd']).'</pre>';
        }
        echo '</details></td>';
    }
    echo '</tr>';
    $altc = !$altc;
}
if (!$first)
{
    echo '</tbody></table>';
}
?>
<span class="sr_only" role="heading" aria-level="2" aria-labelledby="infostitle"></span>
<table><caption id="infostitle"><?= tr($tr, 'infos') ?></caption>
<tbody>
<?php if ($sw_tr['website'] !== '')
{
    echo '<tr><td>'.tr($tr, 'website').'</td><td><a target="_blank" rel="noopener" href="'.$sw_tr['website'].'" id="owlink">'.$sw_tr['website'].'</a></td></tr>';
} if ($fichiersexistants)
{
    echo '<tr><td>'.tr($tr, 'hits').'</td>
<td>'.$sw['downloads'].'</td></tr>';
} ?>
<tr>
<td><?= tr($tr, 'id') ?></td>
<td>A<?= $sw['id'] ?> (<?= '<a href="/c'.$sw['category'].'">'.$catMap[$sw['category']].'</a>' ?>)</td>
</tr>
<?php
    if ($rating_count > 0)
    {
        $avg = number_format($rating_avg, 2, '.', '');
        $avg = rtrim(rtrim($avg, '0'), '.');
    }
?>
<!--<tr>
<td><?= tr($tr, 'rating_average') ?></td>
<td><?php //if ($rating_count === 0):?>
<em><?= tr($tr, 'rating_no_votes') ?></em>
<?php /*else:
    echo tr($tr, 'rating_details', ['avg' => numberlocale($avg), 'count' => $rating_count]);
endif;*/ ?>
</td>
</tr>-->
</tbody>
</table>
<details>
<summary><?= tr($tr, 'detailskw') ?></summary>
<ul>
<?php foreach (explode(' ', (string) $sw_tr['keywords']) as $keyword)
{
    print '<li>'.$keyword.'</li>';
}
?></ul>
</details>
<!--<h2><?= tr($tr, 'comments_title') ?></h2>-->
<?php /*if (isset($logged) && $logged && checkMemberRights('comment_articles')):*/ ?>
<!--<form action="?id=<?= $sw['id'] ?>&rate" method="post" class="rating-form">
<fieldset>
<legend><?= tr($tr, 'rating_label') ?></legend>
<div class="rating-radios">
<?php /*for ($i = 1; $i <= 5; $i++):*/ ?>
<input type="radio" name="rating" id="rating<?= $i ?>" value="<?= $i ?>"<?= $user_rating === $i ? 'checked' : '' ?>>
<label for="rating<?= $i ?>"><?= $i == 1 ? $i . ' ('.tr($tr, 'rating_1_explanation').')' : ($i == 5 ? $i .' ('.tr($tr, 'rating_5_explanation').')' : $i) ?></label>
<?php //endfor;?>
</div>
</fieldset>
<button type="submit"><?= tr($tr, ($user_rating === null) ? 'rating_submit' : 'rating_update') ?></button><?php /* if ($user_rating !== null)
{
    echo ' | <a href="?deleterating">'.tr($tr, 'delete_rating').'</a>';
} */ ?>
</form>
<?php //else:?>
<p><em><?= tr($tr, 'rating_login_required') ?></em></p>
<?php //endif;?>
<div id="comments">
<?php
/*$SQL = <<<SQL
    SELECT * FROM softwares_comments WHERE sw_id=:swid ORDER BY date DESC LIMIT 20
    SQL;
$req = $bdd->prepare($SQL);
$req->execute([':swid' => $sw['id']]);
while ($data = $req->fetch())
{
    echo '<div class="comment"><span class="comment_h" role="heading" aria-level="3">';
    echo(($user = getUsernameById($data['nickname'])) ? ($user !== false ? $user : tr($tr, 'empty_nickname')) : tr($tr, 'empty_nickname'));
    echo ' ('.date('d/m/Y, H:i', $data['date']).')';
    echo '</span>';
    echo '<blockquote>'.convertToMD(str_replace("\n", '<br>', htmlentities((string) $data['text']))).'</blockquote></div>';
    if (canManageComment($data))
    {
        echo '<span class="comment_a"><a href="?id='.$sw['id'].'&cedit='.$data['id'].'#cedit">'.tr($tr, 'comments_mod').'</a> | <a href="?id='.$sw['id'].'&cdel='.$data['id'].'" onclick="return confirm(\''.tr($tr, 'confirm_del_com').'\')">'.tr($tr, 'comments_rm').'</a></span><span class="sr_only"><br></span>';
    }
}
$req->closeCursor();

if (isset($_GET['cedit']))
{
    $SQL = <<<SQL
            SELECT * FROM softwares_comments WHERE id=:swid AND date>:date
        SQL;
    $req = $bdd->prepare($SQL);
    $req->execute([':swid' => $_GET['cedit'], ':date' => time() - 86400]);
    if ($data = $req->fetch())
    {
        if (canManageComment($data))
        {*/
?>
<form action="?id=<?php echo $sw['id'].'&cedit2='.$data['id'] ?>" method="post" id="cedit">
<fieldset><legend><?= tr($tr, 'comments_mod') ?></legend>
<label for="fc_text"><?= tr($tr, 'comments_text') ?></label><br>
<textarea id="fc_text" class="ta" name="text" maxlength="1023" onkeyup="close_confirm=true"><?= htmlentities((string) $data['text']) ?></textarea><br>
<input type="submit" value="<?= tr($tr, 'comments_ok') ?>">
</fieldset>
</form>
<script>init_close_confirm();</script>
<?php /*}
        } $req->closeCursor();
}
if (isset($logged) && $logged && (checkMemberRights('comment_articles') || ($login['rank'] === 'a' && checkAdminRights('manage_comments'))))
{*/ ?>
<form action="?id=<?php echo $sw['id'] ?>&comment" method="post" id="comment_write">
<?php /*if ($comlog !== '')
{
    echo '<strong>'.$comlog.'</strong>';
}*/ ?>
<fieldset><legend><?= tr($tr, 'comments_send') ?></legend>
<p><?= tr($tr, 'comments_warn') ?></p>
<p><?= tr($tr, 'comments_nickname', ['nickname' => $login['usernamek']]) ?></p>
<label for="fc_text"><?= tr($tr, 'comments_text') ?></label><br>
<textarea id="fc_text" class="ta" name="text" maxlength="1023" onkeyup="close_confirm=true"><?php /*if (isset($_POST['text']) && strlen((string) $_POST['text']) <= 1023)
{
    echo htmlentities((string) $_POST['text']);
}*/ ?></textarea><br>
<input type="submit" value="<?= tr($tr, 'comments_ok') ?>">
</fieldset>
</form>
<script>init_close_confirm();</script>
<?php /*}
else
{
    echo tr($tr, 'limitcommentext');
}*/ ?>-->
</div>
</main>
<?php require_once(__DIR__ . '/include/footer.php');

if (isset($logged) && $logged)
{ ?>

<script type="text/javascript" src="/scripts/jquery.js"></script>
<script type="text/javascript" src="/scripts/pa_api.js"></script>
<script type="text/javascript">
function subscribe_comments(e, mod)
{
    var api_session = new API_Session("/api/");
    api_session.session = <?= json_encode($_COOKIE['session']) ?>;
    api_session.connectid = <?= json_encode($login['connectid']) ?>;
    api_session.token = <?= json_encode($login['token']) ?>;
    if(mod)
    {
        api_subscribe_comments(api_session, <?= json_encode($sw['id']) ?>, function(data)
        {
            if(data["subscribed"]["comments"] != undefined)
            {
                if(data["subscribed"]["comments"].indexOf(<?= json_encode($sw['id']) ?>) != -1)
                {
                    $("#btsub1").attr("style", "display:none;");
                    $("#btunsub1").attr("style", "display:initial;");
                }
            }
        });
    }
    else
    {
        api_unsubscribe_comments(api_session, <?= json_encode($sw['id']) ?>, function(data)
        {
            if(data["unsubscribed"]["comments"] != undefined)
            {
                if(data["unsubscribed"]["comments"].indexOf(<?= json_encode($sw['id']) ?>) != -1)
                {
                    $("#btunsub1").attr("style", "display:none;");
                    $("#btsub1").attr("style", "display:initial;");
                }
            }
        });
    }
    e.preventDefault();
}
</script>
<?php } ?>
</body>
</html>
