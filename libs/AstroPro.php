<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

use Typecho\Db;
use Typecho\Router;
use Typecho\Cookie;
use Typecho\Widget;

/**
 * AstroPro 主题工具类
 *
 * 公共 API 与旧版完全兼容，模板无需修改。
 * 内部按职责分组，使用下划线前缀的私有工具方法。
 *
 * @package AstroPro
 * @author  AstroPro Team
 */
class AstroPro
{
    /* ============================================================
     *  进程级缓存 & 单例句柄
     * ============================================================ */

    /** @var array<string,string> 摘要缓存（列表页调用 10+ 次，值得缓存） */
    private static array $excerptCache = [];

    /** @var array<string,array> 置顶 CID 缓存（isStickyCid / getStickyPosts 等会多次查） */
    private static array $stickyCidCache = [];

    /** @var array<string,bool> 字段自检成功标记（防止重复 ALTER） */
    private static array $columnEnsured = [];

    /** @var Db|null 数据库句柄 */
    private static ?Db $db = null;

    /** @var \Widget\Options|null 主题选项句柄 */
    private static ?\Widget\Options $options = null;

    /* ============================================================
     *  公共 API
     * ============================================================ */

    public static function esc($text = '', bool $return = false): string
    {
        $escaped = htmlspecialchars((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($return) {
            return $escaped;
        }
        echo $escaped;
        return $escaped;
    }

    public static function parseStickyCids(string $optionKey = 'sticky'): array
    {
        if (isset(self::$stickyCidCache[$optionKey])) {
            return self::$stickyCidCache[$optionKey];
        }

        $raw = (string) self::_getOptions()->{$optionKey};
        if ($raw === '') {
            return self::$stickyCidCache[$optionKey] = [];
        }

        $cids = array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            'is_numeric'
        ));
        return self::$stickyCidCache[$optionKey] = $cids;
    }

    public static function isStickyCid($cid): bool
    {
        return in_array((string) $cid, self::parseStickyCids(), true);
    }

    public static function getStickyPosts(\Widget\Archive $archive, string $optionKey = 'sticky'): array
    {
        $stickyCids = self::parseStickyCids($optionKey);
        if (empty($stickyCids) || !$archive->is('index') || ($archive->currentPage ?? 1) != 1) {
            return [];
        }

        $db = self::_getDb();
        $placeholders = implode(',', array_fill(0, count($stickyCids), '?'));
        $rows = $db->fetchAll(
            $db->select()->from('table.contents')
                ->where('type = ?', 'post')
                ->where('status = ?', 'publish')
                ->where('cid IN (' . $placeholders . ')', ...array_map('intval', $stickyCids))
                ->where('created <= ?', time())
        );

        $order = array_flip(array_map('intval', $stickyCids));
        usort($rows, static function ($a, $b) use ($order) {
            return ($order[(int) ($a['cid'] ?? 0)] ?? PHP_INT_MAX)
                 - ($order[(int) ($b['cid'] ?? 0)] ?? PHP_INT_MAX);
        });

        $posts = [];
        foreach ($rows as $row) {
            $cid = (int) ($row['cid'] ?? 0);
            if ($cid === 0) {
                continue;
            }
            try {
                $widget = $archive->widget('Widget_Archive@sticky' . $cid, 'type=post', 'cid=' . $cid);
            } catch (Throwable) {
                continue;
            }
            if ($widget->have()) {
                $widget->next();
                $posts[] = $widget;
            }
        }
        return $posts;
    }

    public static function parseSocials(int $limit = 4): array
    {
        $raw = (string) (self::_getOptions()->isSocials ?? '');
        $links = [];
        foreach (self::_parseShortcodeBlock($raw) as $attrs) {
            if (empty($attrs['icon']) || empty($attrs['url'])) {
                continue;
            }
            $links[] = [
                'icon'    => $attrs['icon'],
                'url'     => $attrs['url'],
                'tooltip' => $attrs['tooltip'] ?? '',
            ];
            if (count($links) >= $limit) {
                break;
            }
        }
        return $links;
    }

    public static function parseCarousel(int $limit = 4): array
    {
        $raw = (string) (self::_getOptions()->carouselBanner ?? '');
        $items = [];
        foreach (self::_parseShortcodeBlock($raw) as $attrs) {
            $item = self::_resolveCarouselLine($attrs);
            if ($item === null || empty($item['title'])) {
                continue;
            }
            $items[] = $item;
            if (count($items) >= $limit) {
                break;
            }
        }
        return $items;
    }

    public static function getIcon($type = 'category', $key = null, bool $out = false)
    {
        $icon = null;
        if ($key !== null) {
            $options = self::_getOptions();
            $config  = json_decode((string) ($options->categoryIcons ?? '{}'), true);
            $map     = (is_array($config) ? ($config[$type === 'category' ? 'categories' : 'pages'] ?? []) : []);
            $icon    = $map[$key] ?? '';

            if ($icon === '' || $icon === null) {
                $legacyField = $type === 'category' ? 'Categories_icon' : 'Pages_icon';
                $legacy      = json_decode((string) ($options->{$legacyField} ?? '{}'), true);
                if (is_array($legacy) && isset($legacy[$key])) {
                    $icon = $legacy[$key];
                }
            }
        }

        if ($out) {
            return $icon;
        }
        echo $icon ?? '';
        return null;
    }

    public static function icon($type = 'category', $key = null, string $class = ''): void
    {
        $iconName = self::getIcon($type, $key, true);
        if (empty($iconName)) {
            return;
        }
        $extra = $class !== '' ? ' ' . $class : '';
        echo '<span class="material-icons' . $extra . '" aria-hidden="true">'
           . htmlspecialchars((string) $iconName, ENT_QUOTES, 'UTF-8')
           . '</span>';
    }

    public static function avatar(string $mail, int $size = 100, bool $out = false): string
    {
        $source = rtrim((string) (self::_getOptions()->gravatars ?? 'https://gravatar.loli.net/avatar/'), '/');
        $mail   = strtolower(trim($mail));
        $url    = $source . '/' . md5($mail) . '?s=' . $size . '&d=mp';
        if ($out) {
            return $url;
        }
        echo $url;
        return $url;
    }

    public static function getUserInfo(int $userID, string $field = 'screenName'): string
    {
        $row = self::_getDb()->fetchRow(
            self::_getDb()->select($field)->from('table.users')->where('uid = ?', $userID)
        );
        return $row ? (string) ($row[$field] ?? '') : '';
    }

    public static function getAdminInfo(string $field = 'screenName'): string
    {
        $db  = self::_getDb();
        $row = $db->fetchRow(
            $db->select('screenName', 'name', 'mail')
                ->from('table.users')
                ->where('group = ?', 'administrator')
                ->limit(1)
        );
        return (string) (($row[$field] ?? '') ?? '');
    }

    public static function getPostView($archive, $r = 0)
    {
        $cid = (int) ($archive->cid ?? 0);
        if ($cid <= 0) {
            return $r == 0 ? null : 0;
        }
        $db = self::_getDb();
        self::_ensureViewsField($db);

        $row   = $db->fetchRow($db->select('views')->from('table.contents')->where('cid = ?', $cid));
        $views = $row ? (int) $row['views'] : 0;

        if (!empty($archive->is('single'))) {
            $cookieName = 'extend_contents_views';
            $visited = array_filter(
                array_map('trim', explode(',', (string) Cookie::get($cookieName))),
                'strlen'
            );
            if (!in_array((string) $cid, $visited, true)) {
                $db->query(
                    $db->update('table.contents')
                        ->rows(['views' => $views + 1])
                        ->where('cid = ?', $cid)
                );
                $visited[] = (string) $cid;
                Cookie::set($cookieName, implode(',', $visited));
                $views++;
            }
        }

        if ($r == 0) {
            echo $views;
            return null;
        }
        return $views;
    }

    public static function getPostCategory(int $cid, ?object $widget = null): ?array
    {
        return self::_pickDeepest(self::_resolveCategories($cid, $widget));
    }

    public static function getPostCategoryChain(int $cid, ?object $widget = null): array
    {
        return self::_buildChain(self::_resolveCategories($cid, $widget));
    }

    public static function excerpt(string $content = '', int $length = 160, string $suffix = '...', bool $smartCut = true, bool $skipCode = true): string
    {
        $key = md5($content . '|' . $length . '|' . (int) $smartCut . '|' . (int) $skipCode);
        if (isset(self::$excerptCache[$key])) {
            return self::$excerptCache[$key];
        }

        if ($skipCode) {
            $content = preg_replace('/```[\s\S]*?```/', '', $content) ?? $content;
            $content = preg_replace('/<pre[\s\S]*?<\/pre>/i', '', $content) ?? $content;
            $content = preg_replace('/<code[\s\S]*?<\/code>/i', '', $content) ?? $content;
        }

        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($content)));
        if (mb_strlen($text, 'UTF-8') <= $length) {
            return self::$excerptCache[$key] = $text;
        }

        $cut = mb_substr($text, 0, $length, 'UTF-8');
        if ($smartCut) {
            $endings = ['。', '！', '？', '；', '.', '!', '?', ';'];
            $maxPos  = 0;
            foreach ($endings as $e) {
                $pos = mb_strrpos($cut, $e, 0, 'UTF-8');
                if ($pos !== false && $pos > $maxPos) {
                    $maxPos = $pos;
                }
            }
            if ($maxPos > (int) ($length * 0.5)) {
                return self::$excerptCache[$key] = mb_substr($cut, 0, $maxPos + 1, 'UTF-8');
            }
        }
        return self::$excerptCache[$key] = $cut . $suffix;
    }

    public static function parseToc(string $content): array
    {
        $items = [];
        if ($content === '' || !preg_match_all('/<h([23])\b([^>]*)>(.*?)<\/h\1>/si', $content, $matches, PREG_SET_ORDER)) {
            return $items;
        }
        $used = [];
        foreach ($matches as $match) {
            $level = (int) $match[1];
            $title = trim(strip_tags($match[3]));
            if ($title === '') {
                continue;
            }
            $id = '';
            if (preg_match('/\bid=["\']([^"\']+)["\']/i', $match[2], $idMatch)) {
                $id = $idMatch[1];
            }
            if ($id === '') {
                $base = self::_slugify($title);
                $id   = $base;
                $i    = 1;
                while (in_array($id, $used, true)) {
                    $id = $base . '-' . $i++;
                }
                $used[] = $id;
            }
            $items[] = ['level' => $level, 'id' => $id, 'title' => $title];
        }
        return $items;
    }

    public static function readingTime($content = '', int $wpm = 300, bool $returnRaw = false)
    {
        $text = is_object($content) ? (string) ($content->content ?? '') : (string) $content;
        $text = trim(strip_tags($text));
        if ($text === '') {
            return $returnRaw ? 1 : '1分钟';
        }
        $chinese = preg_match_all('/[\x{4e00}-\x{9fff}]/u', $text) ?: 0;
        $english = str_word_count(preg_replace('/[\x{4e00}-\x{9fff}]/u', ' ', $text) ?? '');
        $wpm     = max(60, $wpm);
        $minutes = max(1, (int) ceil(($chinese + $english) / $wpm));
        return $returnRaw ? $minutes : $minutes . '分钟';
    }

    public static function getPostLikes(int $cid): int
    {
        self::_ensureLikesFields();
        $row = self::_getDb()->fetchRow(
            self::_getDb()->select('likes')->from('table.contents')->where('cid = ?', $cid)
        );
        return (int) ($row['likes'] ?? 0);
    }

    public static function hasUserLiked(int $cid): bool
    {
        self::_ensureLikesFields();
        $row = self::_getDb()->fetchRow(
            self::_getDb()->select('likesData')->from('table.contents')->where('cid = ?', $cid)
        );
        if (empty($row['likesData'])) {
            return false;
        }
        $list = json_decode($row['likesData'], true);
        return is_array($list) && in_array(self::_getLikeIdentity(), $list, true);
    }

    public static function toggleLike(int $cid): array
    {
        self::_ensureLikesFields();
        $db  = self::_getDb();
        $row = $db->fetchRow(
            $db->select('likes', 'likesData')->from('table.contents')->where('cid = ?', $cid)
        );
        if (!$row) {
            return ['likes' => 0, 'liked' => false];
        }

        $likes = (int) ($row['likes'] ?? 0);
        $list  = [];
        if (!empty($row['likesData'])) {
            $decoded = json_decode($row['likesData'], true);
            if (is_array($decoded)) {
                $list = $decoded;
            }
        }

        $identity = self::_getLikeIdentity();
        $liked    = in_array($identity, $list, true);
        if ($liked) {
            $likes = max(0, $likes - 1);
            $list  = array_values(array_diff($list, [$identity]));
        } else {
            $likes++;
            $list[] = $identity;
        }

        $db->query(
            $db->update('table.contents')->rows([
                'likes'     => $likes,
                'likesData' => json_encode($list, JSON_UNESCAPED_UNICODE),
            ])->where('cid = ?', $cid)
        );
        return ['likes' => $likes, 'liked' => !$liked];
    }

    public static function parseFooterLinks(string $optionName = 'footerLinks', ?int $limit = null): array
    {
        $raw = (string) (self::_getOptions()->{$optionName} ?? '');
        $links = [];
        foreach (self::_parseShortcodeBlock($raw) as $attrs) {
            if (empty($attrs['title']) || empty($attrs['url'])) {
                continue;
            }
            $links[] = [
                'title' => $attrs['title'],
                'url'   => $attrs['url'],
                'img'   => $attrs['img'] ?? '',
                'icon'  => $attrs['icon'] ?? '',
            ];
            if ($limit !== null && count($links) >= $limit) {
                break;
            }
        }
        return $links;
    }

    /* ============================================================
     *  内部工具方法（下划线前缀）
     * ============================================================ */

    private static function _getDb(): Db
    {
        return self::$db ??= Db::get();
    }

    private static function _getOptions(): \Widget\Options
    {
        return self::$options ??= Widget::widget('Widget_Options');
    }

    private static function _parseShortcodeBlock(string $content): array
    {
        $items = [];
        if ($content === '') {
            return $items;
        }
        foreach (preg_split("/\r?\n/", $content) as $line) {
            $line = trim($line);
            if ($line === '' || !preg_match('/^\[(.+)\]$/', $line, $matches)) {
                continue;
            }
            $items[] = self::_parseAttributes($matches[1]);
        }
        return $items;
    }

    private static function _parseAttributes(string $body): array
    {
        $attrs = [];
        if (preg_match_all('/(\w+)\s*=\s*"([^"]*)"/', $body, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $attrs[$m[1]] = $m[2];
            }
        }
        return $attrs;
    }

    private static function _slugify(string $str): string
    {
        $str = preg_replace('/[^\p{L}\p{N}_-]/u', '-', $str) ?? '';
        $str = preg_replace('/-+/', '-', $str) ?? '';
        $str = trim($str, '-');
        $str = strtolower($str);
        return $str !== '' ? $str : 'heading';
    }

    private static function _resolveCarouselLine(array $attrs): ?array
    {
        if (isset($attrs['post']) || isset($attrs['page'])) {
            $type = isset($attrs['page']) ? 'page' : 'post';
            $cid  = (int) ($attrs[$type] ?? 0);
            if ($cid <= 0) {
                return null;
            }
            $db  = self::_getDb();
            $row = $db->fetchRow(
                $db->select(['cid'])->from('table.contents')
                    ->where('cid = ?', $cid)
                    ->where('type = ?', $type)
                    ->where('status = ?', 'publish')
                    ->where('password IS NULL')
                    ->where('created <= ?', time())
            );
            if (!$row) {
                return null;
            }
            try {
                $widget = Widget::widget("Widget_Archive@carousel_{$cid}", "type={$type}", "cid={$cid}");
            } catch (Throwable) {
                return null;
            }
            if (!$widget->have()) {
                return null;
            }
            $widget->next();
            return self::_buildCarouselItem($widget, $type);
        }

        $title = $attrs['title'] ?? '';
        if ($title === '') {
            return null;
        }
        return [
            'title'   => $title,
            'url'     => $attrs['url'] ?? '',
            'pic'     => $attrs['pic'] ?? '',
            'Rbadge'  => $attrs['Rbadge'] ?? '',
            'Lbadge'  => $attrs['Lbadge'] ?? '',
            'excerpt' => $attrs['excerpt'] ?? '',
        ];
    }

    private static function _buildCarouselItem($widget, string $type): array
    {
        $cid     = (int) $widget->cid;
        $created = (int) $widget->created;

        $cat     = $type === 'post' ? self::getPostCategory($cid, $widget) : null;
        $excerpt = self::excerpt((string) $widget->content, 120);
        $pic     = ThumbnailHelper::showThumbnail($widget, true) ?? '';

        return [
            'title'     => (string) $widget->title,
            'url'       => (string) $widget->permalink,
            'pic'       => $pic,
            'Lbadge'    => $type === 'post' ? ($cat['name'] ?? '精选') : '页面',
            'LbadgeMid' => $type === 'post' ? (int) ($cat['mid'] ?? 0) : $cid,
            'Rbadge'    => $created > 0 ? date('Y-m-d', $created) : '',
            'excerpt'   => $excerpt,
            'iconType'  => $type === 'post' ? 'category' : 'page',
        ];
    }

    /**
     * @return array<int,array{mid:int,name:string,slug:string,parent:int,permalink:string}>
     */
    private static function _resolveCategories(int $cid, ?object $widget): array
    {
        $cats = [];

        if ($widget !== null && !empty($widget->categories) && is_array($widget->categories)) {
            foreach ($widget->categories as $cat) {
                if (!is_object($cat) || empty($cat->mid)) {
                    continue;
                }
                $cats[(int) $cat->mid] = self::_formatCategory($cat);
            }
            if (!empty($cats)) {
                self::_fillPermalinks($cats);
                return $cats;
            }
        }

        $db   = self::_getDb();
        $rows = $db->fetchAll($db->select('mid')->from('table.relationships')->where('cid = ?', $cid));
        if (empty($rows)) {
            return [];
        }
        $mids         = array_map('intval', array_column($rows, 'mid'));
        $placeholders = implode(',', array_fill(0, count($mids), '?'));
        $metas        = $db->fetchAll(
            $db->select('mid', 'name', 'slug', 'parent')
                ->from('table.metas')
                ->where('mid IN (' . $placeholders . ') AND type = ?', ...array_merge($mids, ['category']))
        );
        foreach ($metas as $meta) {
            $cats[(int) $meta['mid']] = [
                'mid'       => (int) $meta['mid'],
                'name'      => (string) $meta['name'],
                'slug'      => (string) $meta['slug'],
                'parent'    => (int) $meta['parent'],
                'permalink' => '',
            ];
        }
        self::_fillPermalinks($cats);
        return $cats;
    }

    private static function _formatCategory(object $cat): array
    {
        return [
            'mid'       => (int) $cat->mid,
            'name'      => (string) $cat->name,
            'slug'      => (string) $cat->slug,
            'parent'    => (int) $cat->parent,
            'permalink' => (string) ($cat->permalink ?? ''),
        ];
    }

    private static function _fillPermalinks(array &$cats): void
    {
        $index = self::_getOptions()->index;
        foreach ($cats as &$cat) {
            if (empty($cat['permalink']) && !empty($cat['slug'])) {
                $cat['permalink'] = Router::url('category', ['slug' => $cat['slug']], $index);
            }
        }
    }

    private static function _pickDeepest(array $cats): ?array
    {
        if (empty($cats)) {
            return null;
        }
        $children = array_filter($cats, fn ($c) => $c['parent'] !== 0 && isset($cats[$c['parent']]));
        return !empty($children) ? reset($children) : reset($cats);
    }

    private static function _buildChain(array $cats): array
    {
        if (empty($cats)) {
            return [];
        }
        $deepestMid = null;
        foreach ($cats as $mid => $cat) {
            if ($cat['parent'] !== 0 && isset($cats[$cat['parent']])) {
                $deepestMid = $mid;
            }
        }
        if ($deepestMid === null) {
            $deepestMid = array_key_first($cats);
        }
        $chain   = [];
        $current = $deepestMid;
        $visited = [];
        while ($current !== null && isset($cats[$current]) && !isset($visited[$current])) {
            $visited[$current] = true;
            $chain[] = $cats[$current];
            $parent  = (int) $cats[$current]['parent'];
            $current = ($parent !== 0 && isset($cats[$parent])) ? $parent : null;
        }
        return array_reverse($chain);
    }

    private static function _ensureViewsField(Db $db): void
    {
        $key = 'contents.views';
        if (isset(self::$columnEnsured[$key])) {
            return;
        }
        try {
            $table = $db->getPrefix() . 'contents';
            $row   = $db->fetchRow($db->query("SHOW COLUMNS FROM `{$table}` LIKE 'views'"));
            if (!$row) {
                $db->query("ALTER TABLE `{$table}` ADD `views` INT(10) DEFAULT 0");
            }
            self::$columnEnsured[$key] = true;
        } catch (Throwable) {
            // 失败不标记，下次重试
        }
    }

    private static function _ensureLikesFields(): void
    {
        $key = 'contents.likes';
        if (isset(self::$columnEnsured[$key])) {
            return;
        }
        try {
            $db      = self::_getDb();
            $table   = $db->getPrefix() . 'contents';
            $columns = $db->fetchAll($db->query("SHOW COLUMNS FROM `{$table}`"));
            $names   = array_column($columns, 'Field');
            if (!in_array('likes', $names, true)) {
                $db->query("ALTER TABLE `{$table}` ADD `likes` INT(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '点赞数'");
            }
            if (!in_array('likesData', $names, true)) {
                $db->query("ALTER TABLE `{$table}` ADD `likesData` TEXT NULL COMMENT '点赞用户标识JSON'");
            }
            self::$columnEnsured[$key] = true;
        } catch (Throwable) {
            // 失败不标记
        }
    }

    private static function _getLikeIdentity(): string
    {
        $user = Widget::widget('Widget_User');
        if ($user->hasLogin()) {
            return 'user_' . (int) $user->uid;
        }
        $ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return 'ip_' . md5($ip . $agent);
    }
}
