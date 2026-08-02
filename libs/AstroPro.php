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
 * 该工具类为 AstroPro 主题提供了一系列公共 API，涵盖：
 * - 字符串转义（防 XSS）
 * - 置顶文章解析与获取
 * - 社交链接、轮播图、页脚链接的短代码解析
 * - 分类/页面图标管理
 * - 头像生成（Gravatar）
 * - 用户信息查询
 * - 文章阅读量统计（含防刷新的 Cookie 机制）
 * - 文章分类链与最深分类获取
 * - 摘要生成（支持智能截断和代码块过滤）
 * - 文章目录（TOC）解析（自动生成标题 ID）
 * - 阅读时间估算
 * - 点赞系统（支持用户身份识别、去重）
 *
 * 所有方法均为静态调用，内部采用进程级缓存优化性能（如摘要缓存、置顶缓存）。
 * 数据库扩展字段（views, likes, likesData）会在首次使用时自动创建（ALTER TABLE）。
 *
 * @package AstroPro
 * @author  AstroPro Team
 */
class AstroPro
{
    /**
     * 进程级摘要缓存
     *
     * 键为 content、长度等参数的 MD5，值为生成的摘要字符串。
     * 用于避免同一文章在列表页多次渲染时重复计算。
     *
     * @var array<string,string>
     */
    private static array $excerptCache = [];

    /**
     * 置顶文章 CID 缓存
     *
     * 按配置键（如 'sticky'）缓存解析后的 CID 数组，
     * 避免重复解析逗号分隔的字符串。
     *
     * @var array<string,array>
     */
    private static array $stickyCidCache = [];

    /**
     * 字段自检成功标记
     *
     * 记录哪些表的字段已经通过 ALTER 添加成功（如 views, likes），
     * 避免重复执行检查/修改语句。
     *
     * @var array<string,bool>
     */
    private static array $columnEnsured = [];

    /**
     * 数据库单例句柄
     *
     * 复用 Db 实例，减少重复获取开销。
     *
     * @var Db|null
     */
    private static ?Db $db = null;

    /**
     * 主题选项单例句柄
     *
     * 复用 Widget_Options 实例，便于快速读取主题配置。
     *
     * @var \Widget\Options|null
     */
    private static ?\Widget\Options $options = null;

    /* ============================================================
     *  公共 API
     * ============================================================ */

    /**
     * 对字符串进行 HTML 转义（防 XSS）
     *
     * 默认直接输出，若 $return 为 true 则返回转义后的字符串。
     *
     * @param string $text   待转义的文本
     * @param bool   $return 是否返回结果（true 返回，false 直接输出）
     * @return string 当 $return 为 true 时返回转义后的字符串；否则返回空字符串（但会输出）
     */
    public static function esc($text = '', bool $return = false): string
    {
        $escaped = htmlspecialchars((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($return) {
            return $escaped;
        }
        echo $escaped;
        return $escaped;
    }

    /**
     * 解析置顶文章的 CID 列表
     *
     * 从主题选项（如 'sticky'）读取逗号分隔的 CID 字符串，
     * 过滤非数字值，并缓存结果。
     *
     * @param string $optionKey 主题选项键名（默认 'sticky'）
     * @return array<int,int> 置顶文章 CID 的索引数组（值已转为整数）
     */
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

    /**
     * 判断给定的 CID 是否为置顶文章
     *
     * @param int|string $cid 文章 CID
     * @return bool 若该 CID 在置顶列表中则返回 true
     */
    public static function isStickyCid($cid): bool
    {
        return in_array((string) $cid, self::parseStickyCids(), true);
    }

    /**
     * 获取置顶文章列表（仅首页第一页生效）
     *
     * 根据主题选项中的置顶 CID，按指定顺序查询并返回对应的 Archive 对象数组。
     * 结果按用户定义的顺序排序（而非发布时间）。
     *
     * @param \Widget\Archive $archive    当前归档对象（用于复用 Widget）
     * @param string          $optionKey  置顶配置键名（默认 'sticky'）
     * @return \Widget\Archive[] 置顶文章对象列表（若无则返回空数组）
     */
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

    /**
     * 应用置顶文章修正到归档对象
     *
     * 仅对首页生效：
     * - 从归档堆栈中移除置顶文章，仅保留普通文章
     * - 第一页显示 pageSize - stickyCount 条普通文章
     * - 第二页起使用修正后的偏移量，保证分页连续
     * - 置顶文章不计入总页数
     *
     * @param \Widget\Archive $archive   当前归档对象
     * @param string          $optionKey 置顶配置键名（默认 'sticky'）
     * @return void
     */
    public static function applyStickyPagination(\Widget\Archive $archive, string $optionKey = 'sticky'): void
    {
        if (!$archive->is('index')) {
            return;
        }

        $stickyCids  = self::parseStickyCids($optionKey);
        $stickyCount = count($stickyCids);
        if ($stickyCount === 0) {
            return;
        }

        $pageSize    = (int) $archive->parameter->pageSize;
        $currentPage = $archive->getCurrentPage();
        $db          = self::_getDb();
        $now         = (int) self::_getOptions()->time;
        $user        = Widget::widget('Widget_User');

        // 与 Widget_Archive 的 index 归档保持一致：仅展示公开文章，登录用户额外展示自己的私密文章
        $applyStatus = static function ($select) use ($user) {
            if ($user->hasLogin()) {
                return $select->where(
                    'table.contents.status = ? OR (table.contents.status = ? AND table.contents.authorId = ?)',
                    'publish',
                    'private',
                    $user->uid
                );
            }
            return $select->where('table.contents.status = ?', 'publish');
        };

        $selectNormal = $archive->select()->where('type = ?', 'post');
        $selectNormal = $applyStatus($selectNormal);
        $selectNormal->where('table.contents.created < ?', $now);

        // 排除置顶文章
        foreach ($stickyCids as $cid) {
            $selectNormal->where('table.contents.cid != ?', $cid);
        }

        $originalTotal = $archive->getTotal();

        // 清空归档堆栈，重新压入仅包含普通文章（stack/length/row 为受保护属性，需用反射写入）
        self::_resetArchiveStack($archive);

        if ($currentPage === 1) {
            $normalLimit  = max(0, $pageSize - $stickyCount);
            $normalOffset = 0;
        } else {
            $normalLimit  = $pageSize;
            $normalOffset = ($currentPage - 2) * $pageSize + ($pageSize - $stickyCount);
        }

        $normalPosts = $db->fetchAll(
            $selectNormal
                ->order('table.contents.created', Db::SORT_DESC)
                ->limit($normalLimit)
                ->offset($normalOffset)
        );
        foreach ($normalPosts as $post) {
            $archive->push($post);
        }

        // 修正总数：置顶文章不计入总页数
        $archive->setTotal(max(0, $originalTotal - $stickyCount));
    }

    /**
     * 解析社交链接（从主题选项的短代码块）
     *
     * 格式：每行一个 [icon="xxx" url="http://..." tooltip="提示"]，
     * 按顺序最多取 $limit 个。
     *
     * @param int $limit 最多返回的链接数量（默认 4）
     * @return array<int,array{icon:string,url:string,tooltip:string}> 社交链接数组
     */
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

    /**
     * 解析轮播图数据（从主题选项的短代码块）
     *
     * 支持两种格式：
     * 1. 直接定义：[title="标题" url="链接" pic="图片" Rbadge="右角标" Lbadge="左角标" excerpt="简介"]
     * 2. 引用文章/页面：[post="123"] 或 [page="456"]，会自动提取标题、链接、缩略图等
     *
     * @param int $limit 最多返回的项数（默认 4）
     * @return array<int,array> 轮播项数组，具体字段根据类型不同
     */
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

    /**
     * 获取分类或页面的图标名称
     *
     * 优先从新版配置（categoryIcons / pagesIcons）读取，若未找到则回退到旧版配置（Categories_icon / Pages_icon）。
     *
     * @param string      $type  类型：'category' 或 'page'
     * @param int|string $key   分类 mid 或页面 cid（或 slug）
     * @param bool       $out   若为 true 则直接返回图标名称，否则输出（历史兼容）
     * @return string|null 图标名称（若存在），无则返回 null 或空字符串（取决于 $out）
     */
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

    /**
     * 输出分类或页面的图标 HTML（Material Icons 标签）
     *
     * 若图标名称为空则不输出。
     *
     * @param string      $type  类型：'category' 或 'page'
     * @param int|string $key   标识键
     * @param string     $class 附加 CSS 类
     * @return void
     */
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

    /**
     * 生成 Gravatar 头像 URL
     *
     * 使用主题选项中配置的 gravatars 源（默认为 https://gravatar.loli.net/avatar/），
     * 结合邮箱 MD5 和尺寸参数。
     *
     * @param string $mail 用户邮箱
     * @param int    $size 头像尺寸（像素）
     * @param bool   $out  若为 true 直接返回 URL，否则输出
     * @return string 头像 URL
     */
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

    /**
     * 根据用户 ID 获取其指定字段的值
     *
     * @param int    $userID 用户 UID
     * @param string $field  字段名（如 'screenName', 'name', 'mail' 等）
     * @return string 字段值，若不存在则返回空字符串
     */
    public static function getUserInfo(int $userID, string $field = 'screenName'): string
    {
        $row = self::_getDb()->fetchRow(
            self::_getDb()->select($field)->from('table.users')->where('uid = ?', $userID)
        );
        return $row ? (string) ($row[$field] ?? '') : '';
    }

    /**
     * 获取第一个管理员用户的指定字段值
     *
     * 常用于获取管理员昵称或邮箱。
     *
     * @param string $field 字段名（默认 'screenName'）
     * @return string 字段值，若无管理员则返回空字符串
     */
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

    /**
     * 获取文章阅读量，并（在单页时）自动增加浏览量（防刷 Cookie 机制）
     *
     * @param mixed $archive 文章对象（必须包含 cid 属性）
     * @param int   $r       0 表示直接输出浏览量，非 0 表示返回浏览量数值
     * @return int|null 当 $r != 0 时返回浏览量，否则输出并返回 null
     */
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

    /**
     * 获取文章的最深分类（即叶子分类）
     *
     * 若文章无分类或分类链异常，则返回 null。
     *
     * @param int         $cid    文章 CID
     * @param object|null $widget 可选的已实例化的 Archive 对象（若提供则优先使用其 categories 属性）
     * @return array|null 关联数组，包含 mid, name, slug, parent, permalink
     */
    public static function getPostCategory(int $cid, ?object $widget = null): ?array
    {
        return self::_pickDeepest(self::_resolveCategories($cid, $widget));
    }

    /**
     * 获取文章的分类链（从根到叶子）
     *
     * @param int         $cid    文章 CID
     * @param object|null $widget 可选的已实例化的 Archive 对象
     * @return array<int,array> 分类链数组，从最顶层到最深分类
     */
    public static function getPostCategoryChain(int $cid, ?object $widget = null): array
    {
        return self::_buildChain(self::_resolveCategories($cid, $widget));
    }

    /**
     * 生成文章摘要
     *
     * 支持：
     * - 自动移除代码块（可开关）
     * - 智能截断：若在句号、问号等结束符附近截断，则尽可能完整截断
     * - 结果缓存（相同参数只计算一次）
     *
     * @param string $content  文章原始内容（HTML 或 Markdown 均可，但会 strip_tags）
     * @param int    $length   摘要最大长度（字符数，UTF-8）
     * @param string $suffix   截断时追加的后缀（默认 '...'）
     * @param bool   $smartCut 是否启用智能截断（在标点处截断）
     * @param bool   $skipCode 是否移除代码块（``` 或 <pre> 等）
     * @return string 生成的摘要文本
     */
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

    /**
     * 从 HTML 内容中解析文章目录（TOC）
     *
     * 提取所有 <h2> 和 <h3> 标签，并自动为没有 id 的标题生成唯一 id。
     *
     * @param string $content 文章内容（HTML）
     * @return array<int,array{level:int,id:string,title:string}> TOC 项数组
     */
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

    /**
     * 估算文章阅读时间
     *
     * 中文按每个字符计 1 词，英文按 str_word_count 统计，总词数除以 WPM（默认 300）。
     *
     * @param string|object $content   文章内容或包含 content 属性的对象
     * @param int           $wpm       每分钟阅读词数（默认 300）
     * @param bool          $returnRaw 若为 true 返回分钟数（整数），否则返回本地化字符串（如 "3分钟"）
     * @return int|string 根据 $returnRaw 决定返回类型
     */
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

    /**
     * 获取文章点赞数
     *
     * @param int $cid 文章 CID
     * @return int 点赞数
     */
    public static function getPostLikes(int $cid): int
    {
        self::_ensureLikesFields();
        $row = self::_getDb()->fetchRow(
            self::_getDb()->select('likes')->from('table.contents')->where('cid = ?', $cid)
        );
        return (int) ($row['likes'] ?? 0);
    }

    /**
     * 检查当前用户是否已点赞某篇文章
     *
     * 识别依据：已登录用户用 uid，未登录用户用 IP + User-Agent 的 MD5。
     *
     * @param int $cid 文章 CID
     * @return bool 若已点赞则返回 true
     */
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

    /**
     * 切换点赞状态（点赞/取消点赞）
     *
     * 自动更新 likes 字段和 likesData（存储用户标识列表）。
     *
     * @param int $cid 文章 CID
     * @return array{likes:int, liked:bool} 返回最新点赞数及当前是否已点赞
     */
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

    /**
     * 解析页脚链接（从主题选项的短代码块）
     *
     * 格式每行：[title="链接名" url="http://..." img="图标图片" icon="图标名称"]
     *
     * @param string   $optionName 选项键名（默认 'footerLinks'）
     * @param int|null $limit      最大返回数量，若 null 则返回全部
     * @return array<int,array{title:string,url:string,img:string,icon:string}> 链接数组
     */
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

    /**
     * 获取数据库单例
     *
     * @return Db
     */
    private static function _getDb(): Db
    {
        return self::$db ??= Db::get();
    }

    /**
     * 获取主题选项单例
     *
     * @return \Widget\Options
     */
    private static function _getOptions(): \Widget\Options
    {
        return self::$options ??= Widget::widget('Widget_Options');
    }

    /**
     * 清空归档对象的内部数据堆栈
     *
     * stack/length/row 为 Widget 受保护属性，从主题静态方法中直接赋值会走 __set，
     * 导致原始堆栈未被真正清空，从而出现文章重复。这里使用反射直接写入真实属性。
     *
     * @param \Widget\Archive $archive 当前归档对象
     */
    private static function _resetArchiveStack(\Widget\Archive $archive): void
    {
        $reflection = new ReflectionClass($archive);

        $rowProp = $reflection->getProperty('row');
        $rowProp->setValue($archive, []);

        $stackProp = $reflection->getProperty('stack');
        $stackProp->setValue($archive, []);

        $lengthProp = $reflection->getProperty('length');
        $lengthProp->setValue($archive, 0);
    }

    /**
     * 解析短代码块（每行一个 [key="value" ...]）
     *
     * @param string $content 多行文本
     * @return array<int,array> 每行解析出的属性数组
     */
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

    /**
     * 解析键值对字符串（key="value"）
     *
     * @param string $body 如 'title="Hello" url="..."'
     * @return array<string,string> 属性名到值的映射
     */
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

    /**
     * 将字符串转为 URL 友好的 slug（用于生成 id）
     *
     * @param string $str 输入字符串
     * @return string 转换后的 slug，若结果为空则返回 'heading'
     */
    private static function _slugify(string $str): string
    {
        $str = preg_replace('/[^\p{L}\p{N}_-]/u', '-', $str) ?? '';
        $str = preg_replace('/-+/', '-', $str) ?? '';
        $str = trim($str, '-');
        $str = strtolower($str);
        return $str !== '' ? $str : 'heading';
    }

    /**
     * 解析轮播图单行短代码，尝试从文章/页面引用或直接属性生成轮播项
     *
     * @param array $attrs 已解析的属性数组
     * @return array|null 轮播项数组，若无效则返回 null
     */
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

    /**
     * 根据已获取的文章对象生成轮播项数据（用于引用文章/页面）
     *
     * @param mixed  $widget  Archive 对象（已调用 next()）
     * @param string $type    'post' 或 'page'
     * @return array 轮播项数组
     */
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
     * 解析文章的所有分类（从缓存或数据库）
     *
     * @param int         $cid    文章 CID
     * @param object|null $widget 可选的 Archive 对象（若提供则优先使用其 categories 属性）
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

    /**
     * 将分类对象格式化为标准数组
     *
     * @param object $cat 分类对象（必须包含 mid, name, slug, parent, permalink）
     * @return array 标准分类数组
     */
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

    /**
     * 为分类数组填充 permalink 字段（若缺失则根据 slug 生成）
     *
     * @param array &$cats 分类数组（引用）
     */
    private static function _fillPermalinks(array &$cats): void
    {
        $index = self::_getOptions()->index;
        foreach ($cats as &$cat) {
            if (empty($cat['permalink']) && !empty($cat['slug'])) {
                $cat['permalink'] = Router::url('category', ['slug' => $cat['slug']], $index);
            }
        }
    }

    /**
     * 从分类数组中选取最深（叶子）分类
     *
     * @param array $cats 分类数组
     * @return array|null 叶子分类，若无则返回第一个分类或 null
     */
    private static function _pickDeepest(array $cats): ?array
    {
        if (empty($cats)) {
            return null;
        }
        $children = array_filter($cats, fn ($c) => $c['parent'] !== 0 && isset($cats[$c['parent']]));
        return !empty($children) ? reset($children) : reset($cats);
    }

    /**
     * 从分类数组构建从根到叶子的链
     *
     * @param array $cats 分类数组
     * @return array<int,array> 有序分类链（从根到最深）
     */
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

    /**
     * 确保 contents 表存在 views 字段（若不存在则添加）
     *
     * @param Db $db 数据库实例
     */
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

    /**
     * 确保 contents 表存在 likes 和 likesData 字段（若不存在则添加）
     */
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

    /**
     * 获取当前用户的点赞身份标识
     *
     * 已登录用户：'user_{uid}'，未登录：'ip_{md5(ip+user_agent)}'
     *
     * @return string 唯一标识符
     */
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