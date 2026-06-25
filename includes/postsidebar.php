<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) { exit; }

/**
 * 文章页侧边栏模板
 * 
 * @var \Widget_Archive $this
 */

// 文章目录
$tocItems = AstroPro::parseToc((string) ($this->content));
$hasToc   = !empty($tocItems);
// 调试：输出 $tocItems 的数量
echo '<!-- TOC items count: ' . count($tocItems) . ' -->';
// 相关推荐
$related = $this->related(5)->to($relatedPosts);

//热门标签
$tagCloud = \Typecho\Widget::widget('Widget\Metas\Tag\Cloud', 'sort=count&ignoreZeroCount=1&desc=1&limit=10');
?>

<aside class="sidebar post-sidebar" aria-label="文章侧边栏">

    <?php if ($hasToc) { ?>
    <!-- 文章目录 -->
    <div class="sidebar-card toc-card collapsed" id="tocCard">
        <div class="sidebar-card-title" id="tocToggle">
            <span class="material-icons" aria-hidden="true">toc</span> 文章目录
            <span class="material-icons toc-arrow">expand_more</span>
        </div>
        <nav aria-label="文章目录" class="toc-nav" id="tocNav">
            <?php foreach ($tocItems as $item) { 
                $levelClass = $item['level'] === 3 ? 'toc-level-h3' : 'toc-level-h2';
            ?>
            <a class="toc-link <?= $levelClass ?>" href="#<?= htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8') ?>">
                <?php AstroPro::esc($item['title']); ?>
            </a>
            <?php } ?>
        </nav>
    </div>
    <?php } ?>

    <!-- 相关推荐 -->
    <div class="sidebar-card">
        <div class="sidebar-card-title">
            <span class="material-icons" aria-hidden="true">bookmarks</span> 相关推荐
        </div>
        <?php if ($related->have()) { ?>
        <ul class="hot-list">
            <?php 
            $idx = 1;
            while ($related->next()) { 
                $indexStr = str_pad((string) $idx, 2, '0', STR_PAD_LEFT);
            ?>
            <li>
                <a href="<?php $related->permalink(); ?>">
                    <span class="hot-index"><?= $indexStr ?></span>
                    <span class="hot-title"><?php AstroPro::esc($related->title); ?></span>
                </a>
            </li>
            <?php $idx++; } ?>
        </ul>
        <?php } else { ?>
        <div style="padding:1rem;color:var(--text-muted);font-size:0.875rem;text-align:center;">暂无相关文章</div>
        <?php } ?>
    </div>

    <?php if ($tagCloud->have()) { ?>
    <!-- 热门标签 -->
    <div class="sidebar-card">
        <div class="sidebar-card-title">
            <span class="material-icons" aria-hidden="true">sell</span> 热门标签
        </div>
        <div class="tag-cloud">
            <?php while ($tagCloud->next()) { ?>
            <a class="tag-item" href="<?php $tagCloud->permalink(); ?>">
                <span class="material-icons" style="font-size:14px;">local_offer</span> 
                <?php AstroPro::esc($tagCloud->name); ?> 
                <span class="tag-count"><?= (int) ($tagCloud->count ?? 0) ?></span>
            </a>
            <?php } ?>
        </div>
    </div>
    <?php } ?>

</aside>