<?php
/**
 *  AstroPro 评论模板
 *
 */

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/** @var \Typecho\Widget $this */
$options = \Typecho\Widget::widget('Widget_Options');
$user    = \Typecho\Widget::widget('Widget_User');
$db      = \Typecho\Db::get();

// ==================== 后台可调参数 ====================
$requireMail     = (bool) $options->commentsRequireMail;
$requireUrl      = (bool) ($options->commentsRequireURL ?? $options->commentsRequireUrl);
$pageSize        = max(1, (int) $options->commentsPageSize);
$order           = ((string) $options->commentsOrder === 'DESC') ? 'DESC' : 'ASC';
$intervalEnable  = (bool) $options->commentsPostIntervalEnable;
$postInterval    = max(0, (int) $options->commentsPostInterval);

$hasLogin     = $user->hasLogin();
$postAuthorId = (int) ($this->author->uid ?? 0);
$allowComment = (bool) $this->allow('comment');
$cid          = (int) $this->cid;
$commentsNum  = (int) $this->commentsNum;

$rememberAuthor = (string) $this->remember('author', true);
$rememberMail   = (string) $this->remember('mail', true);
$rememberUrl    = (string) $this->remember('url', true);
$notifyChecked  = (($_COOKIE['comment_notify'] ?? '1') === '1');

// ==================== 工具函数 ====================
if (!function_exists('apCommentTimesince')) {
    function apCommentTimesince(int $time): string {
        $t = time() - $time;
        if ($t < 0)         return '刚刚';
        if ($t < 60)        return $t . '秒前';
        if ($t < 3600)      return (int)($t / 60) . '分钟前';
        if ($t < 86400)     return (int)($t / 3600) . '小时前';
        if ($t < 259200)    return (int)($t / 86400) . '天前';
        return date('Y-m-d', $time);
    }
}

if (!function_exists('apIsFrequentCommenter')) {
    function apIsFrequentCommenter(string $author, string $mail): bool {
        if ($author === '' || $mail === '') return false;
        try {
            $db = \Typecho\Db::get();
            $count = (int) $db->fetchObject(
                $db->select(['COUNT(coid)' => 'c'])->from('table.comments')
                    ->where('author = ?', $author)->where('mail = ?', $mail)
                    ->where('status = ?', 'approved')
            )->c;
            return $count >= 3;
        } catch (\Throwable) { return false; }
    }
}

/**
 * 渲染一条完整的评论（用于后代评论，内部 .comment-main 已闭合，不含子容器）
 */
function apRenderOneComment(array $c, bool $allowComment): string {
    $coid        = (int) $c['coid'];
    $authorId    = (int) ($c['authorId'] ?? 0);
    $ownerId     = (int) ($c['ownerId'] ?? 0);
    $authorName  = (string) ($c['author'] ?? '匿名');
    $authorUrl   = (string) ($c['url'] ?? '');
    $authorMail  = (string) ($c['mail'] ?? '');
    $created     = (int) ($c['created'] ?? time());
    $parentCoid  = (int) ($c['parent'] ?? 0);
    $status      = (string) ($c['status'] ?? '');
    $isWaiting   = $status === 'waiting';
    $isAuthor    = $authorId > 0 && $authorId === $ownerId;
    $isFrequent  = !$isAuthor && apIsFrequentCommenter($authorName, $authorMail);

    $avatar   = AstroPro::avatar($authorMail, 80, true);
    $time     = apCommentTimesince($created);
    $rawText  = (string) ($c['text'] ?? '');
    $content  = '<p>' . nl2br(htmlspecialchars($rawText, ENT_QUOTES, 'UTF-8')) . '</p>';

    $authorSafe = htmlspecialchars($authorName, ENT_QUOTES, 'UTF-8');
    $authorHtml = $authorUrl !== ''
        ? '<a href="' . htmlspecialchars($authorUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer" class="comment-author link">' . $authorSafe . '</a>'
        : '<span class="comment-author">' . $authorSafe . '</span>';

    $badgeHtml = $isAuthor
        ? '<span class="comment-badge author-badge">✍ 作者</span>'
        : ($isFrequent ? '<span class="comment-badge">🎖 常客</span>' : '');

    $statusHtml = $isWaiting
        ? '<span class="comment-pending" style="color:#e67e22;font-size:0.85em;margin-left:6px;">待审核</span>'
        : '';

    // @父链接
    $atHtml = '';
    static $paCache = [];
    if ($parentCoid > 0) {
        if (!array_key_exists($parentCoid, $paCache)) {
            try {
                $row = \Typecho\Db::get()->fetchRow(
                    \Typecho\Db::get()->select('author')->from('table.comments')
                        ->where('coid = ?', $parentCoid)->limit(1)
                );
                $paCache[$parentCoid] = (string) ($row['author'] ?? '');
            } catch (\Throwable) {
                $paCache[$parentCoid] = '';
            }
        }
        if ($paCache[$parentCoid] !== '') {
            $atHtml = '<a href="#li-comment-' . $parentCoid . '" class="comment-at">@'
                    . htmlspecialchars($paCache[$parentCoid], ENT_QUOTES, 'UTF-8') . '</a> ';
        }
    }

    $replyBtn = ($allowComment && !$isWaiting)
        ? '<button aria-label="回复" class="comment-action-btn reply-trigger" type="button" data-coid="' . $coid . '" data-author="' . $authorSafe . '"><span class="material-icons" aria-hidden="true">reply</span> 回复</button>'
        : '';

    return '<li class="comment-item" role="listitem" id="li-comment-' . $coid . '" data-coid="' . $coid . '" data-parent="' . $parentCoid . '">'
         . '<img alt="' . $authorSafe . '头像" class="comment-avatar" loading="lazy" src="'
         . htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') . '">'
         . '<div class="comment-main">'
         . '<div class="comment-header">' . $authorHtml . $badgeHtml
         . '<span class="comment-time"><span class="material-icons" aria-hidden="true">schedule</span>'
         . htmlspecialchars($time, ENT_QUOTES, 'UTF-8') . '</span>' . $statusHtml
         . '</div>'
         . '<div class="comment-bubble"><div class="comment-text">' . $atHtml . $content . '</div></div>'
         . '<div class="comment-actions-row">'
         . '<button aria-label="点赞" class="comment-action-btn like-comment-btn" type="button" data-coid="' . $coid . '">'
         . '<span class="material-icons" aria-hidden="true">thumb_up</span>'
         . '<span class="action-count">0</span></button>'
         . $replyBtn
         . '</div>'   // 闭合 .comment-actions-row
         . '</div>'   // 闭合 .comment-main
         . '</li>';
}

/**
 * 渲染顶级评论的开头（不闭合 .comment-main，也不闭合 li，以便内部追加子容器）
 */
function apRenderTopCommentOpen(array $c, bool $allowComment): string {
    $coid        = (int) $c['coid'];
    $authorId    = (int) ($c['authorId'] ?? 0);
    $ownerId     = (int) ($c['ownerId'] ?? 0);
    $authorName  = (string) ($c['author'] ?? '匿名');
    $authorUrl   = (string) ($c['url'] ?? '');
    $authorMail  = (string) ($c['mail'] ?? '');
    $created     = (int) ($c['created'] ?? time());
    $status      = (string) ($c['status'] ?? '');
    $isWaiting   = $status === 'waiting';
    $isAuthor    = $authorId > 0 && $authorId === $ownerId;
    $isFrequent  = !$isAuthor && apIsFrequentCommenter($authorName, $authorMail);

    $avatar   = AstroPro::avatar($authorMail, 80, true);
    $time     = apCommentTimesince($created);
    $rawText  = (string) ($c['text'] ?? '');
    $content  = '<p>' . nl2br(htmlspecialchars($rawText, ENT_QUOTES, 'UTF-8')) . '</p>';

    $authorSafe = htmlspecialchars($authorName, ENT_QUOTES, 'UTF-8');
    $authorHtml = $authorUrl !== ''
        ? '<a href="' . htmlspecialchars($authorUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer" class="comment-author link">' . $authorSafe . '</a>'
        : '<span class="comment-author">' . $authorSafe . '</span>';

    $badgeHtml = $isAuthor
        ? '<span class="comment-badge author-badge">✍ 作者</span>'
        : ($isFrequent ? '<span class="comment-badge">🎖 常客</span>' : '');

    $statusHtml = $isWaiting
        ? '<span class="comment-pending" style="color:#e67e22;font-size:0.85em;margin-left:6px;">待审核</span>'
        : '';

    $replyBtn = ($allowComment && !$isWaiting)
        ? '<button aria-label="回复" class="comment-action-btn reply-trigger" type="button" data-coid="' . $coid . '" data-author="' . $authorSafe . '"><span class="material-icons" aria-hidden="true">reply</span> 回复</button>'
        : '';

    return '<li class="comment-item" role="listitem" id="li-comment-' . $coid . '" data-coid="' . $coid . '" data-parent="0">'
         . '<img alt="' . $authorSafe . '头像" class="comment-avatar" loading="lazy" src="'
         . htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') . '">'
         . '<div class="comment-main">'
         . '<div class="comment-header">' . $authorHtml . $badgeHtml
         . '<span class="comment-time"><span class="material-icons" aria-hidden="true">schedule</span>'
         . htmlspecialchars($time, ENT_QUOTES, 'UTF-8') . '</span>' . $statusHtml
         . '</div>'
         . '<div class="comment-bubble"><div class="comment-text">' . $content . '</div></div>'
         . '<div class="comment-actions-row">'
         . '<button aria-label="点赞" class="comment-action-btn like-comment-btn" type="button" data-coid="' . $coid . '">'
         . '<span class="material-icons" aria-hidden="true">thumb_up</span>'
         . '<span class="action-count">0</span></button>'
         . $replyBtn
         . '</div>';   // 注意：这里不闭合 .comment-main
}

/**
 * 获取某个顶级评论下的所有后代评论（平铺，按 created 升序）
 */
function getFlatDescendants(int $topCoid, array $childrenMap, array $allRows): array {
    $descendants = [];
    $queue = $childrenMap[$topCoid] ?? [];
    while (!empty($queue)) {
        $current = array_shift($queue);
        $descendants[] = $current;
        $coid = (int) $current['coid'];
        if (!empty($childrenMap[$coid])) {
            $queue = array_merge($queue, $childrenMap[$coid]);
        }
    }
    usort($descendants, function($a, $b) {
        return ($a['created'] ?? 0) - ($b['created'] ?? 0);
    });
    return $descendants;
}

// ==================== SQL 查评论 ====================
$rows = [];
try {
    $rows = $db->fetchAll(
        $db->select('coid', 'cid', 'parent', 'author', 'mail', 'url', 'text',
                    'status', 'created', 'authorId', 'ownerId')
            ->from('table.comments')
            ->where('cid = ?', $cid)
            ->where('status = ?', 'approved')
            ->order('created', \Typecho\Db::SORT_ASC)
    );
} catch (\Throwable) {
    $rows = [];
}

// 构建父子映射
$childrenMap = [];
$topRows = [];
foreach ($rows as $r) {
    $p = (int) $r['parent'];
    if ($p === 0) {
        $topRows[] = $r;
    } else {
        $childrenMap[$p][] = $r;
    }
}

$GLOBALS['apFormAvatar'] = AstroPro::avatar(
    $hasLogin ? (string) $user->mail : $rememberMail,
    80,
    true
);
?>
<section class="comments-section glass-card-post" id="comments">
  <div class="comments-header">
    <h2 class="comments-title">
      <span class="material-icons" aria-hidden="true">chat_bubble_outline</span>
      评论 <span class="comments-count"><?= $commentsNum ?> 条</span>
    </h2>
  </div>

  <?php if ($allowComment) { ?>
  <div class="comment-form-anchor" id="commentFormAnchor">
    <div class="comment-form-wrap" id="commentFormWrap">
      <img alt="我的头像" class="comment-form-avatar" loading="lazy"
           src="<?= htmlspecialchars((string) $GLOBALS['apFormAvatar'], ENT_QUOTES, 'UTF-8') ?>">
      <form class="comment-form" id="commentForm"
            action="<?= htmlspecialchars((string) $this->commentUrl(), ENT_QUOTES, 'UTF-8') ?>"
            method="post"
            data-cid="<?= $cid ?>"
            data-respond-id="<?= htmlspecialchars((string) $this->respondId(), ENT_QUOTES, 'UTF-8') ?>"
            data-page-size="<?= $pageSize ?>"
            data-require-mail="<?= $requireMail ? '1' : '0' ?>"
            data-require-url="<?= $requireUrl ? '1' : '0' ?>"
            data-post-interval="<?= $postInterval ?>"
            data-interval-enable="<?= $intervalEnable ? '1' : '0' ?>">
        <input type="hidden" name="parent" id="commentParent" value="0">
        <div class="comment-form-fields">
          <input aria-label="您的名称" class="comment-input" id="commentName" placeholder="名称 *"
                 type="text" name="author" maxlength="200"
                 value="<?= htmlspecialchars($hasLogin ? (string) $user->screenName : $rememberAuthor, ENT_QUOTES, 'UTF-8') ?>"
                 required>
          <input aria-label="您的邮箱（不会公开）" class="comment-input" id="commentEmail" placeholder="邮箱（不会公开）"
                 type="email" name="mail" maxlength="200"
                 value="<?= htmlspecialchars($hasLogin ? (string) $user->mail : $rememberMail, ENT_QUOTES, 'UTF-8') ?>"
                 <?= ($requireMail && !$hasLogin) ? 'required' : '' ?>>
          <input aria-label="您的网站地址" class="comment-input" id="commentWebsite" placeholder="网站 https://"
                 type="url" name="url" maxlength="200"
                 value="<?= htmlspecialchars($hasLogin ? (string) $user->url : $rememberUrl, ENT_QUOTES, 'UTF-8') ?>"
                 <?= ($requireUrl && !$hasLogin) ? 'required' : '' ?>>
        </div>
        <textarea aria-label="评论内容" class="comment-textarea" id="commentTextarea"
                  placeholder="写下你的想法…" rows="3" name="text" required></textarea>
        <div class="comment-form-actions">
          <label class="glass-toggle" data-tooltip="有人回复时邮件通知我">
            <input <?= $notifyChecked ? 'checked' : '' ?> id="commentNotifyToggle" type="checkbox" name="notify" value="1">
            <span class="glass-toggle-track"><span class="glass-toggle-thumb"></span></span>
            <span class="glass-toggle-label"><small>邮件通知</small></span>
          </label>
          <button class="reply-cancel-btn btn btn-sm" type="button" id="commentCancelReply"
                  style="display:none;" data-tooltip="返回主表单">
            <span class="material-icons" aria-hidden="true">close</span> 取消回复
          </button>
          <button class="comment-submit-btn btn btn-sm btn-gradient" type="submit">
            <span class="material-icons" aria-hidden="true">send</span>
            <span class="comment-submit-text">发布</span>
          </button>
        </div>
      </form>
    </div>
  </div>
  <?php } ?>

  <ul class="comments-list" id="commentsList" role="list">
    <?php
    foreach ($topRows as $top) {
        // 输出顶级评论开头（未闭合 .comment-main）
        echo apRenderTopCommentOpen($top, $allowComment);
        // 获取后代并平铺
        $flatChildren = getFlatDescendants((int)$top['coid'], $childrenMap, $rows);
        if (!empty($flatChildren)) {
            echo '<ul class="comments-nested">';
            foreach ($flatChildren as $child) {
                echo apRenderOneComment($child, $allowComment);
            }
            echo '</ul>';
        }
        // 闭合 .comment-main 和 li
        echo '</div></li>';
    }
    ?>
  </ul>

  <?php if ($allowComment && $commentsNum > $pageSize) { ?>
  <button class="load-more-comments" id="loadMoreComments" type="button"
          data-cid="<?= $cid ?>" data-page="1" data-page-size="<?= $pageSize ?>"
          data-order="<?= htmlspecialchars($order, ENT_QUOTES, 'UTF-8') ?>" data-num="<?= $commentsNum ?>">
    <span class="material-icons" aria-hidden="true">expand_more</span>
    <span class="load-more-text">加载更多评论</span>
  </button>
  <?php } ?>
</section>