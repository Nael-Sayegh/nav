<?php
$permalink = 'https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
$titlemodifie = str_replace(' ', ' ', $title);
?>
<footer id="footer">
<a class="sr_only" href="#hautpage" accesskey="H"><?= tr($tr0, 'footer_toplink') ?></a>
<div id="social_share" role="complementary">
<details>
<summary><?= tr($tr0, 'footer_share') ?></summary>
<ul>
<li><a href="https://www.facebook.com/sharer.php?u=<?php print $permalink; ?>&t=<?php print $titlemodifie; ?>" target="_blank" title="<?= tr($tr0, 'footer_fb') ?>"><img src="/images/facebook.png" alt="<?= tr($tr0, 'footer_fb') ?>"></a></li>
<li><a href="https://x.com/share?url=<?php print $permalink; ?>&text=<?php print $titlemodifie; ?>&via=<?php print $site_name; ?>" target="_blank" title="<?= tr($tr0, 'footer_x') ?>"><img src="/images/x.png" alt="<?= tr($tr0, 'footer_x') ?>"></a></li>
<li><button class="mastodon-share" data-title="<?php print $titlemodifie; ?>" data-href="<?php print $permalink; ?>" role="link"></button></li>
</ul>
</details>
</div>
<a href="contact.php"><?php echo tr($tr0, 'footer_contact'); ?></a><br>
<?= tr($tr0, 'footer_blog'); ?><br>
<h2><?php echo tr($tr0, 'footer_youtube'); ?></h2><br aria-hidden="true">
<span class="youtube"><a href=https://www.youtube.com/channel/UC1Ot4mhqH0LtRJj0C4ctzPw>Nael accessvision</a><br><br aria-hidden="true"></span>
<?php
include(__DIR__ . '/include/stats.php');
if ((defined('FB_URL') && constant('FB_URL')) || (defined('MASTO_URL') && constant('MASTO_URL')) || (defined('CESIUM_URL') && constant('CESIUM_URL'))): ?>
<details open>
<summary><?= tr($tr0, 'footer_sociallinks') ?></summary>
<?php if (defined('FB_URL') && ($fbUrl = constant('FB_URL')))
{ ?>
<a target="_blank" href="<?= $fbUrl ?>" title="<?= tr($tr0, 'footer_link_fb', ['site' => $site_name]) ?>"><img id="facebook" alt="<?= tr($tr0, 'footer_link_fb', ['site' => $site_name]) ?>" src="/images/facebook.png"></a>
<?php }
if (defined('MASTO_URL') && ($mastoUrl = constant('MASTO_URL')))
{ ?>
<a target="_blank" rel="me" href="<?= $mastoUrl ?>" title="<?= tr($tr0, 'footer_link_masto', ['site' => $site_name]) ?>"><img id="mastodon" alt="<?= tr($tr0, 'footer_link_masto', ['site' => $site_name]) ?>" src="/images/mastodon-purple.svg" style="width:32px;height:32px;"></a>
<?php }
if (defined('DISCORD_URL') && ($discordUrl = constant('DISCORD_URL')))
{ ?>
<a target="_blank" href="<?= $discordUrl ?>" title="<?= tr($tr0, 'footer_link_discord', ['site' => $site_name]) ?>"><img id="discord" alt="<?= tr($tr0, 'footer_link_discord', ['site' => $site_name]) ?>" src="/images/discord.svg" style="width:32px;height:32px;"></a>
<?php }
if (defined('CESIUM_URL') && ($cesiumUrl = constant('CESIUM_URL')))
{ ?>
<a target="_blank" href="<?= $cesiumUrl ?>" title="<?= tr($tr0, 'footer_link_g1', ['site' => $site_name]) ?>"><img id="g1" alt="<?= tr($tr0, 'footer_link_g1', ['site' => $site_name]) ?>" src="/images/gbreve-simple.svg" style="width:32px;height:32px;"></a>
<?php } ?>
</details>
<?php endif; ?>
<a href="legal.php"><?php echo tr($tr0, 'footer_mention'); ?></a><br>
Copyright &copy 2020-<?php print date('Y'); ?> <?= tr($tr0, 'footer_copyright', ['site' => $site_name]) ?><br>
<p><?php getContentLastModif(); ?><br>
<?php getVersionFromGit(); ?></p>
</footer>
