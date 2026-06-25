<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * Typecho 热门搜索管理器
 * 
 * 特性：
 * - 关键词忽略大小写（Typecho = typecho = TYPECHO）
 * - 存储时保留首次输入的大小写形式作为显示
 * - 支持调用处通过 $config 自定义 HTML 模板
 */

final class HotSearch
{
    private const LIMIT = 10;
    
    /** 默认外层容器模板（必须包含 {items} 占位符） */
    private const WRAPPER_TMPL = '<ul class="hot-search-list">{items}</ul>';
    
    /** 默认单项模板（可用占位符：{url}, {keyword}, {count}, {index}, {articles}） */
    private const ITEM_TMPL = '<li><a href="{url}">{keyword}<span>({count})</span></a></li>';
    
    /** 默认空数据模板 */
    private const EMPTY_TMPL = '<p class="hot-search-empty">暂无热门搜索</p>';

    private static ?array $data = null;
    private static ?string $file = null;
    private static bool $init = false;

    private static function initPath(): void
    {
        if (self::$init) return;
        $cacheDir = __DIR__ . '/cache';
        if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
        self::$file = is_writable($cacheDir)
            ? $cacheDir . '/hotsearch.json'
            : sys_get_temp_dir() . '/hotsearch_' . md5(__DIR__) . '.json';
        self::$init = true;
    }

    private static function load(): array
    {
        if (self::$data !== null) return self::$data;
        self::initPath();
        if (!file_exists(self::$file)) {
            self::$data = [];
            return [];
        }
        $content = @file_get_contents(self::$file);
        $raw = json_decode($content, true);
        self::$data = is_array($raw) ? $raw : [];
        return self::$data;
    }

    private static function save(array $data): void
    {
        if (self::$file === null) self::initPath();
        $tmp = self::$file . '.tmp';
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (file_put_contents($tmp, $json, LOCK_EX) !== false) {
            rename($tmp, self::$file);
        }
        self::$data = $data;
    }

    private static function timeRange(): int
    {
        $opt = \Helper::options()->hotSearchTimeRange ?? '0';
        return max(0, (int)$opt);
    }

    private static function articleCount(string $keyword): int
    {
        $db = \Typecho\Db::get();
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $keyword) . '%';
        try {
            $count = $db->fetchObject(
                $db->select(['COUNT(DISTINCT cid)' => 'num'])
                    ->from('table.contents')
                    ->where('type = ?', 'post')
                    ->where('status = ?', 'publish')
                    ->where('title LIKE ? OR text LIKE ?', $like, $like)
            )->num ?? 0;
            return (int)$count;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * 记录搜索关键词（大小写不敏感）
     */
    public static function log(string $keyword): void
    {
        $keyword = trim($keyword);
        if ($keyword === '' || strlen($keyword) > 255) return;

        // 无匹配文章则不记录
        $articles = self::articleCount($keyword);
        if ($articles === 0) return;

        // 判断字符类型
        $isChineseOnly = preg_match('/^[\x{4e00}-\x{9fff}\x{3400}-\x{4dbf}]+$/u', $keyword);
        $isAsciiOnly   = preg_match('/^[\x00-\x7F]+$/', $keyword);

        if ($isChineseOnly) {
            // 纯中文：2 个汉字起记
            if (mb_strlen($keyword, 'UTF-8') < 2) return;
        } elseif ($isAsciiOnly) {
            // 纯英文/数字：4 个字符起记
            if (strlen($keyword) < 4) return;
        } else {
            // 混写：3 个字符起记
            if (mb_strlen($keyword, 'UTF-8') < 3) return;
        }

        $data = self::load();
        $now = time();
        $found = false;

        // 忽略大小写查找是否存在相同关键词
        foreach ($data as &$item) {
            if (strcasecmp($item['keyword'], $keyword) === 0) {
                $item['count']++;
                $item['lastsearch'] = $now;
                $item['articles'] = $articles;
                $found = true;
                break;
            }
        }

        if ($found) {
            self::save($data);
        } else {
            $data[] = [
                'keyword'    => $keyword,
                'count'      => 1,
                'lastsearch' => $now,
                'articles'   => $articles,
            ];
            self::save($data);
        }
    }

    public static function getList(?int $limit = null): array
    {
        $data = self::load();
        if (empty($data)) return [];

        $range = self::timeRange();
        $now = time();
        $filtered = [];

        foreach ($data as $item) {
            if ($range > 0 && ($now - $item['lastsearch']) > $range * 86400) continue;
            $filtered[] = $item;
        }

        usort($filtered, fn($a, $b) => $b['count'] <=> $a['count'] ?: $b['lastsearch'] <=> $a['lastsearch']);

        $limit = $limit ?? self::LIMIT;
        return array_slice($filtered, 0, $limit);
    }

    /**
     * 渲染热门搜索
     * 
     * @param int|null $limit 显示数量
     * @param array $config 自定义模板配置：
     *   - 'wrapper' => 外层容器模板，必须包含 {items} 占位符
     *   - 'item'    => 单项模板，可用占位符：{url}, {keyword}, {count}, {index}, {articles}
     *   - 'empty'   => 无数据时模板
     */
    public static function render(?int $limit = null, array $config = []): void
    {
        $list = self::getList($limit);
        
        $wrapper = $config['wrapper'] ?? self::WRAPPER_TMPL;
        $itemTpl = $config['item'] ?? self::ITEM_TMPL;
        $empty   = $config['empty'] ?? self::EMPTY_TMPL;

        if (empty($list)) {
            echo $empty;
            return;
        }

        $items = '';
        foreach ($list as $idx => $item) {
            $url = \Typecho\Router::url('search', ['keywords' => urlencode($item['keyword'])], \Helper::options()->index);
            $items .= strtr($itemTpl, [
                '{url}'      => $url,
                '{keyword}'  => htmlspecialchars($item['keyword'], ENT_QUOTES, 'UTF-8'),
                '{count}'    => (string) $item['count'],
                '{index}'    => (string) ($idx + 1),
                '{articles}' => (string) $item['articles'],
            ]);
        }
        echo strtr($wrapper, ['{items}' => $items]);
    }

    public static function clear(): void
    {
        if (self::$file && file_exists(self::$file)) unlink(self::$file);
        self::$data = null;
    }
}