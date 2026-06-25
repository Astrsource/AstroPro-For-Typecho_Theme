<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) { exit; }

/**
 * 文章列表模板
 * 
 * @var \Widget_Archive $this 
 */

// 遍历文章列表
while ($this->next()) {
    
    // 只在首页跳过已置顶的 CID，避免分类页/标签页丢失文章
    if ($this->is('index') && AstroPro::isStickyCid($this->cid)) {
        continue;
    }

    // 缩略图
    $thumb = ThumbnailHelper::showThumbnail($this, true);
    
    // 分类
    $cat = AstroPro::getPostCategory((int) $this->cid, $this);
    $categoryName = $cat['name'];
    $categoryUrl  = $cat['permalink'];
    $categoryMid  = $cat['mid'];
    
    // 日期
    $dateTime = (int) $this->created;
    $dateStr  = $dateTime > 0 ? date('Y-m-d', $dateTime) : '';
    
    // 摘要
    $excerpt = AstroPro::excerpt((string) $this->content, 120);
?>

<article class="glass-card">
    <div class="card-media">
        <div class="image-wrapper">
            <?php if (!empty($thumb)) { ?>
            <a href="<?= (string) $this->permalink ?>">
                <img alt="<?php AstroPro::esc($this->title); ?>" loading="lazy" src="<?= $thumb ?>">
            </a>
            <?php } ?>
            <div class="top-meta">
                <a href="<?= $categoryUrl ?>">
                    <span class="badge">
                        <?php AstroPro::icon('category', $categoryMid); ?>
                        <?= AstroPro::esc($categoryName, true) ?>
                    </span>
                </a>
                <?php if ($dateStr) { ?>
                <span class="badge">
                    <span class="material-icons">calendar_today</span><?= $dateStr ?>
                </span>
                <?php } ?>
            </div>
            <div class="floating-title">
                <h3><a href="<?= (string) $this->permalink ?>"><?php AstroPro::esc($this->title); ?></a></h3>
            </div>
        </div>
    </div>
    <div class="card-content">
        <span class="excerpt-badge">📄 摘要</span>
        <div class="excerpt"><?php AstroPro::esc($excerpt); ?></div>
    </div>
</article>

<?php } ?>