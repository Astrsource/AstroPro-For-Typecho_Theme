<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) { exit; }
$this->need('header.php');

// 作者头像
$authorAvatar = getAuthorAvatar($this->author);

// ── 相邻文章 ──
$adjacent = new AdjacentPosts($this);
$prevPost = $adjacent->getPrev();
$nextPost = $adjacent->getNext();

// ── 封面图 ──
$coverImg = ThumbnailHelper::showThumbnail($this, true);

// ── 分类层级链（面包屑用） ──
$categoryChain = AstroPro::getPostCategoryChain($this->cid, $this);

// ── 辅助：通过相邻文章数据获取其缩略图 ──
$getAdjacentThumb = function (array $post): string {
    $cid  = (int) ($post['cid'] ?? 0);
    $type = (string) ($post['type'] ?? 'post');
    if ($cid <= 0) {
        return '';
    }
    try {
        $w = $this->widget("Widget_Archive@adj_thumb_{$cid}", "type={$type}", "cid={$cid}");
        if ($w && $w->have()) {
            $w->next();
            $thumb = ThumbnailHelper::showThumbnail($w, true);
            if (!empty($thumb)) {
                return $thumb;
            }
        }
    } catch (\Throwable $e) {
        return '';
    }
    return '';
};

// 点赞数
$likes     = AstroPro::getPostLikes($this->cid);
$hasLiked  = AstroPro::hasUserLiked($this->cid);
?>
<!-- 阅读进度条 -->
<div class="reading-progress" id="readingProgress"></div>
<!-- ==================== 文章页面主要内容 ==================== -->
<div class="main-container post-layout">
<!-- ==================== 文章内容 ==================== -->
<main id="main-content" class="post-main">
<!-- ==================== 文章标题卡片 ==================== -->
<header class="post-header glass-card-post">
<div class="post-cover">
<img alt="<?php AstroPro::esc($this->title); ?> - 封面图" loading="lazy" src="<?= $coverImg; ?>">
<div class="post-cover-gradient"></div>
</div>
<div class="post-header-body">
<div class="post-author-row">
<img alt="<?php AstroPro::esc($this->author->screenName); ?> - 头像" class="post-author-avatar" loading="lazy" src="<?= $authorAvatar; ?>">
<div class="post-author-info">
<div class="post-author-name-row">
<a href="<?= (string) $this->author->permalink ?>" class="post-author-name"><?php AstroPro::esc($this->author->screenName); ?></a>
<span class="post-author-badge"><span class="material-icons" aria-hidden="true">verified</span> 作者</span>
</div>
<div class="post-author-title"><span class="material-icons" aria-hidden="true">chat</span><?= $this->options->isDescription ?? $this->options->description ?></div>
</div>
</div>
<div class="post-meta-row">
<span class="post-meta-item"><span class="material-icons" aria-hidden="true">calendar_today</span> <?php $this->date('Y-m-d'); ?></span>
<span class="post-meta-item"><span class="material-icons" aria-hidden="true">schedule</span> 阅读约 <?= AstroPro::readingTime($this); ?></span>
<span class="post-meta-item"><span class="material-icons" aria-hidden="true">visibility</span> <?php AstroPro::getPostView($this); ?> 阅读</span>
</div>
<h1 class="post-title"><?php AstroPro::esc($this->title); ?></h1>
<div class="post-tags-row">
<?php if (!empty($this->tags)) { ?>
    <?php foreach ($this->tags as $tag) { ?>
    <a class="post-tag-chip" href="<?= $tag['permalink']; ?>">
        <span class="material-icons" aria-hidden="true">local_offer</span> <?php AstroPro::esc($tag['name']); ?>
    </a>
    <?php } ?>
<?php } ?>
</div>
<nav aria-label="面包屑导航" class="post-breadcrumb">
<a href="<?php $this->options->index(); ?>"><span class="material-icons" aria-hidden="true">home</span> 首页</a>
<span class="material-icons" aria-hidden="true">chevron_right</span>
<?php if (!empty($categoryChain)) { ?>
    <?php foreach ($categoryChain as $idx => $cat) { ?>
    <a href="<?= $cat['permalink']; ?>">
        <?php AstroPro::icon('category', $cat['mid']); ?>
        <?php AstroPro::esc($cat['name']); ?>
    </a>
    <?php if ($idx < count($categoryChain) - 1) { ?>
    <span class="material-icons" aria-hidden="true">chevron_right</span>
    <?php } ?>
    <?php } ?>
    <span class="material-icons" aria-hidden="true">chevron_right</span>
<?php } else { ?>
    <a href="#">未分类</a>
    <span class="material-icons" aria-hidden="true">chevron_right</span>
<?php } ?>
<span class="current">正文</span>
</nav>
</div>
</header>
<!-- ==================== 文章正文 ==================== -->
<article class="post-content glass-card-post" id="postContent">
<?php 

// $rawContent = $this->content;
// $filteredContent = ContentFilter::parseContent($rawContent, $this, $rawContent);
// echo $filteredContent;

$this->content(); 

?>
</article>
<!-- ==================== 文章导航 ==================== -->
<nav aria-label="文章导航" class="post-actions glass-card-post">
<div class="post-nav-upper">
<?php if ($prevPost) {
    $prevThumb = $getAdjacentThumb($prevPost);
    $prevCat   = AstroPro::getPostCategory((int) ($prevPost['cid']));
?>
<a class="post-nav-item post-nav-prev" href="<?= htmlspecialchars((string) ($prevPost['permalink']), ENT_QUOTES, 'UTF-8'); ?>">
<div class="post-nav-visual">
<div class="post-nav-imgwrap">
<img alt="上一篇" loading="lazy" src="<?= $prevThumb; ?>">
</div>
<span class="post-nav-imgbadge"><span class="material-icons" aria-hidden="true">arrow_back</span></span>
</div>
<div class="post-nav-content">
<span class="post-nav-direction">上一篇</span>
<span class="post-nav-article-title"><?php AstroPro::esc($prevPost['title']); ?></span>
<span class="post-nav-article-meta"><?= date('Y-m-d', (int) ($prevPost['created'])); ?> · <?php AstroPro::esc($prevCat['name']); ?></span>
</div>
</a>
<?php } ?>

<?php if ($prevPost && $nextPost) { ?>
<div class="post-nav-connector">
<span class="post-nav-connector-line"></span>
<span class="post-nav-connector-dot"></span>
<span class="post-nav-connector-line"></span>
</div>
<?php } ?>

<?php if ($nextPost) {
    $nextThumb = $getAdjacentThumb($nextPost);
    $nextCat   = AstroPro::getPostCategory((int) ($nextPost['cid']));
?>
<a class="post-nav-item post-nav-next" href="<?php AstroPro::esc((string) ($nextPost['permalink'])); ?>">
<div class="post-nav-content">
<span class="post-nav-direction">下一篇</span>
<span class="post-nav-article-title"><?php AstroPro::esc($nextPost['title']); ?></span>
<span class="post-nav-article-meta"><?= date('Y-m-d', (int) ($nextPost['created'])); ?> · <?php AstroPro::esc($nextCat['name']); ?></span>
</div>
<div class="post-nav-visual">
<div class="post-nav-imgwrap">
<img alt="下一篇" loading="lazy" src="<?= $nextThumb; ?>">
</div>
<span class="post-nav-imgbadge"><span class="material-icons" aria-hidden="true">arrow_forward</span></span>
</div>
</a>
<?php } ?>
</div>
<div class="post-nav-lower">
<span class="post-license"><span class="material-icons">copyright</span> 本文采用 <a href="#">CC BY-SA 4.0</a> 协议授权，转载请注明出处。</span>
<div class="post-nav-actions">
<button class="post-action-btn like-btn <?= $hasLiked ? 'liked' : '' ?>" data-cid="<?= $this->cid ?>">
    <span class="material-icons"><?= $hasLiked ? 'favorite' : 'favorite_border' ?></span>
    <span class="action-count"><?= $likes ?></span>
</button>
<button aria-label="分享" class="post-action-btn" type="button" onclick="if(navigator.share){navigator.share({title:document.title,url:location.href})}else{showSnackbar('请手动复制链接分享')}"><span class="material-icons" aria-hidden="true">share</span> 分享</button>
<button aria-label="复制链接" class="post-action-btn" data-tooltip="复制链接" type="button" onclick="navigator.clipboard.writeText(window.location.href).then(()=>showSnackbar('链接已复制')).catch(()=>showSnackbar('复制失败'));"><span class="material-icons" aria-hidden="true">content_copy</span> <span class="material-icons" aria-hidden="true">link</span></button>
</div>
</div>
</nav>
<!-- ==================== 评论 ==================== -->
<?php $this->need('comments.php'); ?>
</main>
<!-- ==================== 侧边栏 ==================== -->
<?php $this->need('includes/postsidebar.php'); ?>
<!-- footer.php -->
<?php $this->need('footer.php'); ?>