<?php
declare(strict_types=1);
/**
 * AstroPro theme for Typecho
 *
 * @package AstroPro
 * @author Astrsource
 * @version 1.0
 * @link https://astrsource.com
 */

if (!defined('__TYPECHO_ROOT_DIR__')) { exit; }
$this->need('header.php');
if ($this->options->isBanner) {

    // 作者头像
    $authorAvatar = getAuthorAvatar($this->author);

    // ── 解析轮播图配置（最多 4 个） ──
    $carouselItems = AstroPro::parseCarousel(4);
    $carouselCount = count($carouselItems);

    // ── 获取管理员昵称 ──
    $userName = AstroPro::getAdminInfo('screenName');

    // ── 解析社交链接（最多 4 个） ──
    $socialLinks = AstroPro::parseSocials(4);

}
?>
<!-- ==================== 主要内容 ==================== -->
<div class="main-container">
<?php if ($this->options->isBanner) { ?>
<!-- ==================== 首页 Hero Banner 轮播区 ==================== -->
<section class="hero-banner" aria-label="精选文章轮播">
<div class="hero-profile">
<img alt="<?php AstroPro::esc($userName); ?>" class="hero-avatar" loading="lazy" src="<?= $authorAvatar; ?>">
<h2 class="hero-name"><?= $userName ?></h2>
<div class="hero-title"><?= $this->options->isBio ?? $this->options->title ?></div>
<p class="hero-bio"><?= $this->options->isDescription ?? $this->options->description ?></p>
<div class="hero-socials">
<?php if (!empty($socialLinks)) { ?>
    <?php foreach ($socialLinks as $social) { ?>
    <a class="hero-social-link" href="<?= $social['url'] ?>" target="_blank" rel="noopener noreferrer"<?php if (!empty($social['tooltip'])) { ?> data-tooltip="<?php AstroPro::esc($social['tooltip']); ?>"<?php } ?>>
        <span class="material-icons" aria-hidden="true"><?php AstroPro::esc($social['icon']); ?></span>
    </a>
    <?php } ?>
<?php } else { ?>
    <a class="hero-social-link" href="<?= $this->options->feedUrl() ?>" target="_blank" rel="noopener noreferrer" data-tooltip="RSS"><span class="material-icons" aria-hidden="true">rss_feed</span></a>
    <a class="hero-social-link" href="<?= $this->options->commentsFeedUrl() ?>" target="_blank" rel="noopener noreferrer" data-tooltip="评论RSS"><span class="material-icons" aria-hidden="true">comment</span></a>
    <a class="hero-social-link" href="mailto:<?= AstroPro::getAdminInfo('mail') ?>" target="_blank" rel="noopener noreferrer" data-tooltip="邮箱"><span class="material-icons" aria-hidden="true">mail_outline</span></a>
<?php } ?>
</div>
</div>

<?php if ($carouselCount > 0) { ?>
<div class="hero-article">
<?php foreach ($carouselItems as $idx => $item) { ?>
<div class="magazine-card <?= $idx === 0 ? 'active' : '' ?>" data-slide="<?= $idx + 1 ?>">
<div class="ha-bg">
  <a href="<?= $item['url'] ?>" target="_blank">
    <?php if (!empty($item['pic'])) { ?>
    <img alt="<?php AstroPro::esc(AstroPro::excerpt($item['title'])); ?>" loading="lazy" src="<?= $item['pic'] ?>">
    <?php } ?>
  </a>
</div>
<div class="ha-gradient"></div>
<div class="ha-tags">
  <?php if (!empty($item['Lbadge'])) { ?>
  <span class="ha-meta-badge">
    <?php if (!empty($item['iconType']) && isset($item['LbadgeMid'])) { ?>
        <?php AstroPro::icon($item['iconType'], $item['LbadgeMid']); ?>
    <?php } ?>
    <?php AstroPro::esc($item['Lbadge']); ?>
  </span>
  <?php } ?>
  <?php if (!empty($item['Rbadge']) && ($item['iconType'] ?? '') !== 'page') { ?>
  <span class="ha-meta-badge"><span class="material-icons" style="font-size:12px;">calendar_today</span> <?php AstroPro::esc($item['Rbadge']); ?></span>
  <?php } ?>
</div>
<div class="ha-info">
  <h3 class="ha-title"><a href="<?= $item['url'] ?>" target="_blank"><?php AstroPro::esc(AstroPro::excerpt($item['title'])); ?></a></h3>
  <?php if (!empty($item['excerpt'])) { ?>
  <p class="ha-excerpt"><?php AstroPro::esc($item['excerpt']); ?></p>
  <?php } ?>
</div>
</div>
<?php } ?>
</div>

<div class="hero-carousel-thumbs">
<?php foreach ($carouselItems as $idx => $item) { ?>
<div class="thumb-card <?= $idx === 0 ? 'active' : '' ?>" data-slide="<?= $idx + 1 ?>">
  <?php if (!empty($item['pic'])) { ?>
  <img alt="<?php AstroPro::esc(AstroPro::excerpt($item['title'])); ?>" class="thumb-img" loading="lazy" src="<?= $item['pic'] ?>"> 
  <?php } ?>
  <div class="thumb-info">
    <span class="thumb-title"><span><?php AstroPro::esc(AstroPro::excerpt($item['title'])); ?></span></span>
    <span class="thumb-date">
      <?php if (!empty($item['Rbadge']) && ($item['iconType'] ?? '') !== 'page') { ?>
      <span class="material-icons" aria-hidden="true">calendar_today</span><?php AstroPro::esc($item['Rbadge']); ?>
      <?php } else { ?>
      <span><?php AstroPro::esc($item['excerpt']); ?></span>
      <?php } ?>
    </span>
  </div>
</div>
<?php } ?>
</div>
<?php } else { ?>
<div class="hero-article-empty" style="display:flex;align-items:center;justify-content:center;flex:1;color:var(--text-muted);">
  <span style="font-size:0.875rem;">请在后台「外观设置 → banner轮播解析」配置轮播图</span>
</div>
<?php } ?>
</section>
<?php } ?>
<!-- ==================== 主内容区：文章列表 ==================== -->
<section class="content-area">
<?php if (!empty($this->options->sticky)&& $this->_currentPage == 1) { ?>
<!-- ==================== 置顶文章 ==================== -->
<?php
$this->need('includes/stickyposts.php');
} 
?>
<!-- ==================== 文章列表 ==================== -->
<main id="main-content" class="cards-list">
<?php $this->need('includes/articlelists.php'); ?>
</main>
<!-- 分页 -->
<?php
$this->pageNav(
    '<span class="material-icons" aria-hidden="true">chevron_left</span>',
    '<span class="material-icons" aria-hidden="true">chevron_right</span>',
    2,
    '...',
    [
        'wrapTag'      => 'div',
        'wrapClass'    => 'pagination',
        'itemTag'      => '',
        'currentClass' => 'page-btn active',
        'prevClass'    => 'page-btn',
        'nextClass'    => 'page-btn',
        'textClass'    => 'page-btn'
    ]
);
?>
</section>
<!-- ==================== 侧边栏 ==================== -->
<?php $this->need('includes/sidebar.php'); ?>
<!-- footer.php -->
<?php $this->need('footer.php'); ?>