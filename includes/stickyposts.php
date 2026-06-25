<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) { exit; }

/**
 * 置顶文章列表模板（仅首页第一页显示）
 * 
 * @var \Widget_Archive $this 
*/

// 获取置顶文章（仅首页第一页显示）
$stickyPosts = AstroPro::getStickyPosts($this);

foreach ($stickyPosts as $post) {
    // 缩略图
    $thumb = ThumbnailHelper::showThumbnail($post, true) ?? '';
    
    // 摘要
    $excerpt = AstroPro::excerpt((string) $post->content, 180);
        
    // 作者信息
    $authorName = (string) ($post->author->screenName ?? $post->author->name);
    
    // 作者头像
    $authorAvatar = getAuthorAvatar($post->author);

    // 分类（直接通过 CID 查库，确保 mid / permalink 正确）
    $cat = AstroPro::getPostCategory((int) $post->cid, $this);
    $categoryName = $cat['name'];
    $categoryUrl  = $cat['permalink'];
        
    // 日期
    $dateTime  = (int) ($post->created ?? 0);
    $dateStr   = $dateTime > 0 ? date('Y-m-d', $dateTime) : '';
    $dateAttr  = $dateTime > 0 ? date('Y-m-d\TH:i:s', $dateTime) : '';
        
    // 阅读时间估算
    $readTime = AstroPro::readingTime($post);
?>

<article class="pinned-card">
    <!-- 顶部大图 -->
    <?php if (!empty($thumb)) { ?>
    <div class="pinned-cover">
        <a href="<?= (string) $post->permalink ?>">
            <img alt="<?php AstroPro::esc($post->title); ?>" loading="lazy" src="<?= $thumb ?>">
        </a>
    </div>
    <?php } ?>
    
    <!-- 左上置顶标签 -->
    <div class="pinned-badge">
        <span class="material-icons" aria-hidden="true">push_pin</span> 置顶
    </div>
    
    <!-- 右上作者信息横条 -->
    <div class="pinned-cover-meta">
        <img alt="<?php AstroPro::esc($authorName); ?>" class="pinned-meta-avatar" loading="lazy" src="<?= $authorAvatar ?>">
        <a class="pinned-meta-name" href="<?= (string) $post->author->permalink ?>"><?php AstroPro::esc($authorName); ?></a>
        <?php if (!empty($categoryName)) { ?>
        <a class="pinned-meta-cat" href="<?= $categoryUrl ?>">
            <?php AstroPro::esc($categoryName); ?>
        </a>
        <?php } ?>
        <?php if ($dateStr) { ?>
        <span class="pinned-meta-date">
            <time datetime="<?= $dateAttr ?>"><?= $dateStr ?></time>
        </span>
        <?php } ?>
        <span class="pinned-meta-read"><?= $readTime ?></span>
    </div>
    
    <!-- 标题 -->
    <div class="pinned-title-wrap">
        <h2 class="pinned-title">
            <a href="<?= (string) $post->permalink ?>"><?php AstroPro::esc($post->title); ?></a>
        </h2>
    </div>
    
    <!-- 正文摘要 -->
    <div class="pinned-body">
        <p class="pinned-excerpt"><?php AstroPro::esc($excerpt); ?></p>
    </div>
</article>
<?php
}