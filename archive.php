<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) { exit; }
$this->need('header.php');

// ── 归档类型与信息 ──
$archiveType = '';
$archiveIcon = 'code';
$archiveTitle = '';
$archiveDesc = '';
$archiveCount = $this->getTotal();

if ($this->is('category')) {
    $archiveType = '分类';
    $archiveIcon = 'folder';
    $archiveTitle = $this->getArchiveTitle();
    $archiveDesc = $this->getDescription();
} elseif ($this->is('tag')) {
    $archiveType = '标签';  
    $archiveIcon = 'local_offer';
    $archiveTitle = $this->getArchiveTitle();
    $archiveDesc =  null;
} elseif ($this->is('date')) {
    $archiveType = '日期';
    $archiveIcon = 'calendar_today';
    $archiveTitle = $this->getArchiveTitle(); 
}
?>
<!-- ==================== Archive 通用页面 ==================== -->
<main id="main-content" class="ar-wrapper">
<!-- 头部区域 -->
<header class="ar-header">
<div class="ar-header-card">
<div class="ar-header-content">
<div class="ar-icon-wrap">
<span class="material-icons" aria-hidden="true"><?php AstroPro::esc($archiveIcon); ?></span>
</div>
<div class="ar-title-area">
<h1 class="ar-main-title">
<?php AstroPro::esc($archiveType); ?>：<span><?php AstroPro::esc($archiveTitle); ?></span>
</h1>
<div class="ar-meta-row">
<span class="ar-badge">
<span class="material-icons" aria-hidden="true">bookmark</span> 归档类型：<?php AstroPro::esc($archiveType); ?></span>
<span class="ar-count">共 <strong><?= $archiveCount; ?></strong> 篇文章</span>
</div>
<?php if (!empty($archiveDesc)) { ?>
<p class="ar-desc"><?php AstroPro::esc($archiveDesc); ?></p>
<?php } ?>
</div>
</div>
</div>
</header>
<!-- 文章列表（单栏） -->
<div class="ar-list">
<?php while ($this->next()) { ?>
<article class="ar-item">
<div class="ar-item-dot"></div>
<div class="ar-item-card">
<div class="ar-item-body">
<div class="ar-item-meta">
<span class="ar-item-date">
<span class="material-icons" aria-hidden="true">calendar_today</span> <?php $this->date('Y-m-d'); ?></span>
<?php $cat = AstroPro::getPostCategory($this->cid, $this); ?>
<?php if ($cat) { ?>
<a class="ar-item-category" href="<?= $cat['permalink']; ?>">
    <?php AstroPro::icon('category', $cat['mid']); ?>
    <?php AstroPro::esc($cat['name']); ?>
</a>
<?php } ?>
</div>
<h2 class="ar-item-title">
<a href="<?php $this->permalink(); ?>"><?php AstroPro::esc($this->title); ?></a>
</h2>
<p class="ar-item-excerpt"><?php echo AstroPro::excerpt($this->content, 120); ?></p>
<div class="ar-item-tags">
<?php if (!empty($this->tags)) { ?>
    <?php foreach ($this->tags as $tag) { ?>
    <a class="ar-tag" href="<?= $tag['permalink']; ?>"><?php AstroPro::esc($tag['name']); ?></a>
    <?php } ?>
<?php } ?>
</div>
</div>
</div>
</article>
<?php } ?>
</div>
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
</main>
<?php $this->need('footer.php'); ?>