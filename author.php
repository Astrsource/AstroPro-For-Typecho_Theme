<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) { exit; }
$this->need('header.php');

// ── 作者对象 ──
$author = $this->author;

// ── 基础信息（复用 index.php 逻辑 / Typecho 原生） ──
$authorAvatar = getAuthorAvatar($author);
$userName     = (string) ($author->screenName ?: $author->name);
$authorBio    = (string) ($this->options->isBio ?? $this->options->title);
$authorDesc   = (string) ($this->options->isDescription ?? $this->options->description);

// ── 社交链接（复用 index.php） ──
$socialLinks = AstroPro::parseSocials(4);

// ── 统计数据 ──
$db = \Typecho\Db::get();
$uid = (int) $author->uid;

$statRow = $db->fetchRow(
    $db->select(
        ['COUNT(cid)' => 'post_count'],
        ['SUM(likes)' => 'total_likes'],
        ['SUM(views)' => 'total_views']
    )
    ->from('table.contents')
    ->where('authorId = ?', $uid)
    ->where('type = ?', 'post')
    ->where('status = ?', 'publish')
);

$postCount  = (int) ($statRow['post_count'] ?? 0);
$totalLikes = (int) ($statRow['total_likes'] ?? 0);
$totalViews = (int) ($statRow['total_views'] ?? 0);

$commentCount = (int) $db->fetchObject(
    $db->select(['COUNT(coid)' => 'num'])
        ->from('table.comments')
        ->where('authorId = ?', $uid)
)->num;

// 数字格式化
$fmtNum = function (int $num): string {
    if ($num >= 100000) return round($num / 10000) . 'w';
    if ($num >= 10000)  return round($num / 1000, 1) . 'k';
    if ($num >= 1000)   return number_format($num);
    return (string) $num;
};

// ── 最近评论（关联文章标题，使用完整表名） ──
$recentComments = $db->fetchAll(
    $db->select(
        'table.comments.coid',
        'table.comments.cid',
        'table.comments.created',
        'table.comments.text',
        'table.contents.title',
        'table.contents.slug'
    )
    ->from('table.comments')
    ->join('table.contents', 'table.comments.cid = table.contents.cid')
    ->where('table.comments.authorId = ?', $uid)
    ->order('table.comments.created', \Typecho\Db::SORT_DESC)
    ->limit(10)
);

// 评论回复数
$replyMap = [];
if (!empty($recentComments)) {
    $coids = array_map(fn(array $c): int => (int) $c['coid'], $recentComments);
    $placeholders = implode(',', array_fill(0, count($coids), '?'));
    $rows = $db->fetchAll(
        $db->select(['parent' => 'pid'], ['COUNT(coid)' => 'num'])
            ->from('table.comments')
            ->where("parent IN ({$placeholders})", ...$coids)
            ->group('parent')
    );
    foreach ($rows as $r) {
        $replyMap[(int) $r['pid']] = (int) $r['num'];
    }
}
?>
<!-- ==================== 用户页面主体 ==================== -->
<div class="user-page-wrapper">
<!-- 两栏布局 -->
<div class="user-layout">
<!-- ==================== 左侧边栏：作者信息 ==================== -->
<aside class="user-sidebar" aria-label="作者信息">
<!-- 个人资料卡片 -->
<div class="user-profile-card">
<div class="user-profile-cover"></div>
<div class="user-profile-body">
<div class="user-avatar-wrap">
<img alt="<?php AstroPro::esc($userName); ?>" class="user-avatar" loading="lazy" src="<?= $authorAvatar ?>">
</div>
<h2 class="user-name"><?php AstroPro::esc($userName); ?></h2>
<div class="user-title-badge"><?php AstroPro::esc($authorBio); ?></div>
<p class="user-bio"><?php AstroPro::esc($authorDesc); ?></p>
<div class="user-meta-list">
<?php if (!empty($author->url)): ?>
<span class="user-meta-item"><span class="material-icons" aria-hidden="true">link</span> <a href="<?php AstroPro::esc($author->url); ?>" target="_blank" rel="noopener noreferrer"><?php AstroPro::esc($author->url); ?></a></span>
<?php endif; ?>
<?php if (!empty($author->logged)): ?>
<span class="user-meta-item"><span class="material-icons" aria-hidden="true">calendar_today</span> <?= date('Y-m-d', (int) $author->logged) ?> 上次登录</span>
<?php endif; ?>
</div>
<div class="user-social-links">
<?php if (!empty($socialLinks)) { ?>
    <?php foreach ($socialLinks as $social) { ?>
    <a class="user-social-link" href="<?= $social['url'] ?>" target="_blank" rel="noopener noreferrer"<?php if (!empty($social['tooltip'])) { ?> data-tooltip="<?php AstroPro::esc($social['tooltip']); ?>"<?php } ?>>
        <span class="material-icons" aria-hidden="true"><?php AstroPro::esc($social['icon']); ?></span>
    </a>
    <?php } ?>
<?php } else { ?>
    <a class="user-social-link" href="<?= $this->options->feedUrl() ?>" target="_blank" rel="noopener noreferrer" data-tooltip="RSS"><span class="material-icons" aria-hidden="true">rss_feed</span></a>
    <a class="user-social-link" href="<?= $this->options->commentsFeedUrl() ?>" target="_blank" rel="noopener noreferrer" data-tooltip="评论RSS"><span class="material-icons" aria-hidden="true">comment</span></a>
    <a class="user-social-link" href="mailto:<?= (string) ($author->mail ?? '') ?>" target="_blank" rel="noopener noreferrer" data-tooltip="邮箱"><span class="material-icons" aria-hidden="true">mail_outline</span></a>
<?php } ?>
</div>
</div>
</div>
<!-- 统计卡片 -->
<div class="user-stats-card">
<div class="user-stats-grid">
<div class="user-stat-item">
<span class="user-stat-icon material-icons">article</span>
<span class="user-stat-value"><?= $fmtNum($postCount) ?></span>
<span class="user-stat-label">文章</span>
</div>
<div class="user-stat-item">
<span class="user-stat-icon material-icons">chat_bubble_outline</span>
<span class="user-stat-value"><?= $fmtNum($commentCount) ?></span>
<span class="user-stat-label">评论</span>
</div>
<div class="user-stat-item">
<span class="user-stat-icon material-icons">favorite</span>
<span class="user-stat-value"><?= $fmtNum($totalLikes) ?></span>
<span class="user-stat-label">获赞</span>
</div>
<div class="user-stat-item">
<span class="user-stat-icon material-icons">visibility</span>
<span class="user-stat-value"><?= $fmtNum($totalViews) ?></span>
<span class="user-stat-label">浏览</span>
</div>
</div>
</div>
</aside>
<!-- ==================== 右侧主内容区 ==================== -->
<main id="main-content" class="user-main">
<!-- 选项卡导航 -->
<div class="user-tabs-wrap" data-tab-container="" id="userTabs">
<div class="tab-nav">
<button class="tab-btn active" data-tab="user-articles-panel" role="tab" type="button">
<span class="material-icons" aria-hidden="true">article</span> 文章
</button>
<button class="tab-btn" data-tab="user-comments-panel" role="tab" type="button">
<span class="material-icons" aria-hidden="true">chat_bubble_outline</span> 评论
</button>
</div>
<!-- 文章面板 -->
<div class="tab-panel active" id="user-articles-panel" role="tabpanel">
<div class="user-articles-list">
<?php if ($this->have()) { ?>
    <?php $this->need('includes/articlelists.php'); ?>
<?php } else { ?>
    <p class="no-content">该作者暂无文章。</p>
<?php } ?>
<?php if ($this->have()) { ?>
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
<?php } ?>
</div>
</div>
<!-- 评论面板 -->
<div class="tab-panel" id="user-comments-panel" role="tabpanel">
<div class="user-comments-list">
<?php if (!empty($recentComments)) { ?>
    <?php foreach ($recentComments as $comment) { ?>
        <?php
        $coid = (int) ($comment['coid'] ?? 0);
        $cid  = (int) ($comment['cid'] ?? 0);
        $commentUrl = \Typecho\Common::url(
            \Typecho\Router::url('post', ['cid' => $cid, 'slug' => (string) ($comment['slug'] ?? '')]),
            $this->options->index
        ) . '#comment-' . $coid;
        $commentText = strip_tags((string) ($comment['text'] ?? ''));
        $commentDate = (int) ($comment['created'] ?? 0);
        $replies = $replyMap[$coid] ?? 0;
        ?>
    <article class="user-comment-card">
    <div class="user-comment-accent"></div>
    <div class="user-comment-body">
    <a class="user-comment-context" href="<?= $commentUrl ?>">
    <span class="material-icons" aria-hidden="true">link</span>
    <?php AstroPro::esc((string) ($comment['title'] ?? '未知文章')); ?>
    </a>
    <p class="user-comment-text">
    <?php AstroPro::esc(AstroPro::excerpt($commentText, 200)); ?>
    </p>
    <div class="user-comment-footer">
    <span class="user-comment-time"><span class="material-icons" aria-hidden="true">schedule</span> <?= $commentDate > 0 ? date('Y-m-d', $commentDate) : ''; ?></span>
    <span class="user-comment-stats">
    <span class="material-icons" aria-hidden="true">thumb_up</span> <?= $fmtNum((int) AstroPro::getPostLikes($cid)); ?>
    <span class="material-icons" aria-hidden="true">chat_bubble</span> <?= $replies ?>
    </span>
    </div>
    </div>
    </article>
    <?php } ?>
<?php } else { ?>
    <p class="no-content">该作者暂无评论。</p>
<?php } ?>
</div>
</div>
</div>
</main>
</div>
</div>
<?php $this->need('footer.php'); ?>