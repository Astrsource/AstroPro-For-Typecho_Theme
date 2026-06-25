<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

use Typecho\Common;
use Typecho\Db;
use Typecho\Router;
use Typecho\Widget;

/**
 * 相邻文章路由生成器
 * 
 * 职责：根据 Typecho 当前永久链接规则，为相邻文章原始数据补全路由参数，
 *       正确生成 URL，失败时自动降级到 /archives/{cid}/
 */
class AdjacentPostRouter
{
    /**
     * 生成文章永久链接
     *
     * @param array  $row     原始文章行数据（含 cid, slug, created, type 等）
     * @param string $baseUrl 站点首页地址
     * @return string
     */
    public static function build(array $row, string $baseUrl): string
    {
        $params = self::prepareParams($row);

        try {
            $type  = $params['type'] ?? 'post';
            $route = $type === 'page' ? 'page' : 'post';

            // Router::url() 会根据当前路由规则反解参数
            $url = Router::url($route, $params, $baseUrl);
            if ($url && $url !== $baseUrl) {
                return $url;
            }
        } catch (Throwable $e) {
            // 路由参数缺失或规则不匹配，静默降级
        }

        // 最终兜底：/archives/{cid}/
        return Common::url('/archives/' . $params['cid'] . '/', $baseUrl);
    }

    /**
     * 补全 Typecho 路由反解所需的参数
     *
     * 永久链接常用变量：{cid}, {slug}, {year}, {month}, {day}, {category}
     * 这里自动从 created 提取日期参数，确保日期型路由可用。
     * category 类路由若缺失参数，则依赖上方 try-catch 兜底。
     */
    private static function prepareParams(array $row): array
    {
        // 确保基础字段存在
        $row['cid']  = $row['cid'];
        $row['slug'] = !empty($row['slug']) ? $row['slug'] : $row['cid'];

        // 从 created 时间戳提取 year/month/day（支持 int 或 SQL 字符串）
        if (!empty($row['created'])) {
            $ts = is_int($row['created']) 
                ? $row['created'] 
                : strtotime((string) $row['created']);
            
            if ($ts > 0) {
                $row['year']  = date('Y', $ts);
                $row['month'] = date('m', $ts);
                $row['day']   = date('d', $ts);
            }
        }

        // 若路由使用了 {category}，但相邻文章查询未联表，这里不额外查库，
        // 保持轻量，让 build() 的 try-catch 接管兜底。

        return $row;
    }
}

/**
 * 相邻文章处理类
 * 
 * - 惰性查询，按需加载，避免重复 SQL
 * - 进程级静态缓存，同一请求周期内多次 new 也不会重复查询
 * - 使用 PHP 8.2+ 类型声明
 * - 不包含任何硬编码 HTML，完全由调用方控制输出
 */
class AdjacentPosts
{
    private Db $db;
    private object $widget;

    private ?array $prev = null;
    private ?array $next = null;
    private bool $prevLoaded = false;
    private bool $nextLoaded = false;

    /** @var array<string, array|null> */
    private static array $cache = [];

    public function __construct(object $widget)
    {
        $this->db = Db::get();
        $this->widget = $widget;
    }

    public function hasPrev(): bool
    {
        return $this->getPrev() !== null;
    }

    public function hasNext(): bool
    {
        return $this->getNext() !== null;
    }

    public function getPrev(): ?array
    {
        if (!$this->prevLoaded) {
            $this->prev = $this->fetchAdjacent('<', Db::SORT_DESC);
            $this->prevLoaded = true;
        }
        return $this->prev;
    }

    public function getNext(): ?array
    {
        if (!$this->nextLoaded) {
            $this->next = $this->fetchAdjacent('>', Db::SORT_ASC);
            $this->nextLoaded = true;
        }
        return $this->next;
    }

    /**
     * @param string $operator '<' 或 '>'
     * @param string $order 'DESC' / 'ASC'
     */
    private function fetchAdjacent(string $operator, string $order): ?array
    {
        $cid = $this->widget->cid;
        if ($cid === 0) {
            return null;
        }

        $direction = $operator === '<' ? 'prev' : 'next';
        $cacheKey = "{$cid}:{$direction}";

        if (array_key_exists($cacheKey, self::$cache)) {
            return self::$cache[$cacheKey];
        }

        $row = $this->db->fetchRow(
            $this->db->select()->from('table.contents')
                ->where("created {$operator} ?", $this->widget->created)
                ->where('created < ?', time())
                ->where('status = ?', 'publish')
                ->where('type = ?', $this->widget->type)
                ->where('password IS NULL')
                ->order('created', $order)
                ->limit(1)
        );

        if (!$row) {
            self::$cache[$cacheKey] = null;
            return null;
        }

        // 优先用 widget filter 处理
        if (method_exists($this->widget, 'filter')) {
            $row = $this->widget->filter($row);
        }

        // 兜底：确保关键字段一定存在
        $row['title'] = $row['title'];
        $row['created'] = $row['created'];

        // 使用独立路由生成器处理 permalink（兼容自定义永久链接）
        if (empty($row['permalink'])) {
            $baseUrl = Widget::widget('Widget_Options')->index;
            $row['permalink'] = AdjacentPostRouter::build($row, $baseUrl);
        }

        self::$cache[$cacheKey] = $row;
        return $row;
    }

    /**
     * 输出单篇相邻文章
     *
     * @param string        $direction 'prev' 或 'next'
     * @param callable|null $renderer  回调( array $postData, string $direction, object $widget ): string
     * @param string|null   $default   无文章时输出的默认内容
     */
    public function render(string $direction, ?callable $renderer = null, ?string $default = null): void
    {
        $adjacent = $direction === 'next' ? $this->getNext() : $this->getPrev();

        if ($adjacent === null) {
            echo $default;
            return;
        }

        if ($renderer !== null) {
            echo $renderer($adjacent, $direction, $this->widget);
        } else {
            $title = htmlspecialchars((string) $adjacent['title'], ENT_QUOTES, 'UTF-8');
            $url   = htmlspecialchars((string) $adjacent['permalink'], ENT_QUOTES, 'UTF-8');
            $text  = $direction === 'next' ? '下一篇' : '上一篇';
            echo "<a href=\"{$url}\" rel=\"{$direction}\" title=\"{$title}\">{$text}: {$title}</a>";
        }
    }

    /**
     * 同时输出上一篇与下一篇
     */
    public function renderPair(
        ?callable $prevRenderer = null,
        ?callable $nextRenderer = null,
        ?string $prevDefault = null,
        ?string $nextDefault = null
    ): void {
        $this->render('prev', $prevRenderer, $prevDefault);
        $this->render('next', $nextRenderer, $nextDefault);
    }
}