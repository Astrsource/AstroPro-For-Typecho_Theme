<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) { exit; }
$this->need('header.php');

// 页面封面图片
$coverImg = ThumbnailHelper::showThumbnail($this, true);
?>
<!-- ==================== 独立页面主内容 ==================== -->
<div class="main-container page-wrapper">
<!-- ==================== 页面内容 ==================== -->
<main id="main-content" class="post-main">
<!-- ==================== 页面标题卡片 ==================== -->
<header class="post-header glass-card-post">
<div class="post-cover">
<img alt="<?php AstroPro::esc($this->title); ?> - 封面图" loading="lazy" src="<?= $coverImg; ?>">
<div class="post-cover-gradient"></div>
</div>
<div class="post-header-body">
<h1 class="post-title"><?php AstroPro::esc($this->title); ?></h1>
</div>
</header>
<!-- ==================== 正文卡片 ==================== -->
<article class="glass-card-post">
<div class="post-content page-content">
<?php $this->content(); ?>
</div>
</article>
<!-- ==================== 评论 ==================== -->
<?php $this->need('comments.php'); ?>
</main>
</div>
<?php $this->need('footer.php'); ?>