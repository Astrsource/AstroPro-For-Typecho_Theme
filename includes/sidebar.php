<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) { exit; }

use Typecho\Widget;

/**
 * 侧边栏模板
 * 
 * @var \Widget\Archive $this 
 */

// 获取最新评论
$recentComments = Widget::widget('Widget\Comments\Recent', 'pageSize=5');

// 相对时间格式化
$getRelativeTime = function (int $created): string {
    $diff = time() - $created;
    if ($diff < 60) return '刚刚';
    if ($diff < 3600) return (int) floor($diff / 60) . '分钟前';
    if ($diff < 86400) return (int) floor($diff / 3600) . '小时前';
    if ($diff < 2592000) return (int) floor($diff / 86400) . '天前';
    return date('Y-m-d', $created);
};

// 后台自定义头像
$customAvatar = (string) ($this->options->isAvatar ?? '');

// DeepSeekComment 插件 AI 邮箱
$aiMail = '';
try {
    $aiMail = (string) (\Helper::options()->plugin('DeepSeekComment')->mail ?? '');
} catch (\Exception $e) {
    $aiMail = '';
}
$aiAvatar = $this->options->themeUrl . '/assets/img/deepseek.png';
?>

<aside class="sidebar" aria-label="侧边栏">

    <?php if ($recentComments->have()) { ?>
    <div class="sidebar-card">
        <div class="sidebar-card-title">
            <span class="material-icons" aria-hidden="true">chat_bubble_outline</span> 最新评论
        </div>
        <ul class="comment-list">
            <?php while ($recentComments->next()) { 
                $commentMail = (string) $recentComments->mail;
                $commentAuthorId = (int) ($recentComments->authorId ?? 0);

                // 优先级：AI 邮箱 → 注册用户自定义头像 → Gravatar
                if (!empty($aiMail) && $commentMail === $aiMail) {
                    $commentAvatar = $aiAvatar;
                } elseif ($commentAuthorId > 0 && !empty($customAvatar)) {
                    $commentAvatar = $customAvatar;
                } else {
                    $commentAvatar = AstroPro::avatar($commentMail, 64, true);
                }
            ?>
            <li class="comment-item" role="listitem">
                <img 
                    alt="<?php AstroPro::esc($recentComments->author . '头像', true); ?>" 
                    class="comment-avatar" 
                    loading="lazy" 
                    src="<?= $commentAvatar ?>"
                >
                <div class="comment-main">
                    <div class="comment-header">
                        <a href="<?php $recentComments->permalink(); ?>">
                            <span class="comment-author"><?php AstroPro::esc($recentComments->author); ?></span>
                        </a>
                        <span class="comment-time">
                            <span class="material-icons" aria-hidden="true">schedule</span>
                            <?= $getRelativeTime((int) $recentComments->created); ?>
                        </span>
                    </div>
                    <div class="comment-bubble">
                        <div class="comment-text">
                            <?php AstroPro::esc(AstroPro::excerpt((string) $recentComments->text, 50)); ?>
                        </div>
                        <span class="comment-post">
                            <a href="<?= strtok($recentComments->permalink, '#'); ?>">
                                <span class="material-icons" aria-hidden="true">link</span>
                                <?php AstroPro::esc($recentComments->title); ?>
                            </a>
                        </span>
                    </div>
                </div>
            </li>
            <?php } ?>
        </ul>
    </div>
    <?php } ?>

    <!-- 热门推荐（按浏览量排序） -->
    <?php $hotPosts = Widget::widget('Widget_Post_hot@hot', 'pageSize=5'); ?>
    <?php if ($hotPosts->have()) { ?>
    <div class="sidebar-card">
        <div class="sidebar-card-title">
            <span class="material-icons" aria-hidden="true">local_fire_department</span> 热门推荐
        </div>
        <ul class="hot-list">
            <?php 
            $idx = 0;
            while ($hotPosts->next()) { 
                $idx++;
                $num = str_pad((string) $idx, 2, '0', STR_PAD_LEFT);
            ?>
            <li>
                <a href="<?php echo $hotPosts->permalink; ?>">
                    <span class="hot-index"><?php echo $num; ?></span>
                    <span class="hot-title"><?php AstroPro::esc($hotPosts->title); ?></span>
                </a>
            </li>
            <?php } ?>
        </ul>
    </div>
    <?php } ?>

    <!-- 热门标签 -->
    <?php $tagCloud = Widget::widget('Widget\Metas\Tag\Cloud', 'sort=count&ignoreZeroCount=1&desc=1&limit=20'); ?>
    <?php if ($tagCloud->have()) { ?>
    <div class="sidebar-card">
        <div class="sidebar-card-title">
            <span class="material-icons" aria-hidden="true">sell</span> 热门标签
        </div>
        <div class="tag-cloud">
            <?php while ($tagCloud->next()) { ?>
            <a class="tag-item" href="<?= $tagCloud->permalink; ?>">
                <span class="material-icons" style="font-size:14px;">local_offer</span>
                <?php AstroPro::esc($tagCloud->name); ?>
                <span class="tag-count"><?= $tagCloud->count; ?></span>
            </a>
            <?php } ?>
        </div>
    </div>
    <?php } ?>

</aside>