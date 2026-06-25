<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

use Typecho\Widget;
use Widget\Contents\Attachment\Related;

/**
 * 缩略图工具类
 * 
 * 缓存策略：
 * - key = cid:pattern:checkAttachments:allowRandom，生命周期为当前 HTTP 请求
 * - forceRandom=true 时跳过缓存，每次重新生成
 * - 无 cid 时退化为无缓存模式
 */
class ThumbnailHelper
{
    /** @var array<string, string> 页面级静态缓存 */
    private static array $cache = [];

    /**
     * 输出缩略图
     *
     * 优先级：自定义字段 > 正文HTML（含懒加载）> Markdown > 附件（可选）> 随机图（可选）
     *
     * @param \Widget\Archive|\Widget\Base\Contents $widget 文章对象
     * @param bool $return 是否返回（默认直接输出）
     * @param bool $forceRandom 是否强制使用随机图（跳过缓存与提取逻辑）
     * @param string|null $randomPattern 随机图路径模板，含 {n} 占位符（0-9）
     * @param bool $checkAttachments 是否查询附件表作为缩略图来源（默认关闭，避免列表页 N+1）
     * @param bool $allowRandom 当所有来源均无图时，是否使用随机图兜底
     * @return string|null
     */
    public static function showThumbnail(
        $widget,
        bool $return = false,
        bool $forceRandom = false,
        ?string $randomPattern = null,
        bool $checkAttachments = false,
        bool $allowRandom = true
    ): ?string {
        $cid = $widget->cid ?? 0;

        // 强制随机模式：每次都重新生成，不缓存
        if ($forceRandom) {
            $img = self::getRandomThumbnail($widget, $randomPattern);
            return self::dispatch($img, $return);
        }

        // 组装缓存 key（参数变化会影响结果，必须纳入）
        $cacheKey = null;
        if ($cid > 0) {
            $cacheKey = $cid . ':' . ($randomPattern ?? 'default')
                      . ':' . ($checkAttachments ? '1' : '0')
                      . ':' . ($allowRandom ? '1' : '0');

            if (isset(self::$cache[$cacheKey])) {
                return self::dispatch(self::$cache[$cacheKey], $return);
            }
        }

        $img = null;

        // 1. 自定义字段 thumb（支持 \n 或 , 分隔的多图，取首张有效）
        if (!empty($widget->fields->thumb)) {
            $candidates = preg_split('/[\r\n,]+/', $widget->fields->thumb, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($candidates as $candidate) {
                $candidate = trim($candidate);
                if (!empty($candidate) && !self::isBlocked($candidate)) {
                    $img = $candidate;
                    break;
                }
            }
        }

        // 2. 正文 HTML 图片（优先 data-src，兼容懒加载）
        if (empty($img) && !empty($widget->content)) {
            $img = self::extractFromHtml($widget->content);
        }

        // 3. Markdown 格式图片（标准 + 脚注式）
        if (empty($img) && !empty($widget->content)) {
            $img = self::extractFromMarkdown($widget->content);
        }

        // 4. 附件图片（受 $checkAttachments 控制，默认关闭避免列表页 N+1）
        if (empty($img) && $checkAttachments && $cid > 0) {
            $img = self::extractFromAttachments($cid);
        }

        // 5. 随机图兜底（受 $allowRandom 控制）
        if (empty($img) && $allowRandom) {
            $img = self::getRandomThumbnail($widget, $randomPattern);
        }

        // 最终过滤：若命中黑名单，按 $allowRandom 决定回退策略
        if (!empty($img) && self::isBlocked($img)) {
            $img = $allowRandom
                ? self::getRandomThumbnail($widget, $randomPattern)
                : null;
        }

        // 写入静态缓存
        if ($cid > 0 && $cacheKey !== null) {
            self::$cache[$cacheKey] = $img ?? '';
        }

        return self::dispatch($img, $return);
    }

    /**
     * 从 HTML 提取首张有效图片
     */
    private static function extractFromHtml(string $content): ?string
    {
        $ext = 'jpg|jpeg|gif|png|webp|bmp|avif';

        // 优先匹配懒加载 data-src
        if (preg_match('/<img[^>]*?\sdata-src=["\']([^"\']+?\.(?:' . $ext . '))(?:\?[^"\']*)?["\'][^>]*>/i', $content, $match)) {
            return $match[1];
        }

        // 匹配普通 src
        if (preg_match('/<img[^>]*?\ssrc=["\']([^"\']+?\.(?:' . $ext . '))(?:\?[^"\']*)?["\'][^>]*>/i', $content, $match)) {
            return $match[1];
        }

        return null;
    }

    /**
     * 从 Markdown 提取首张有效图片
     */
    private static function extractFromMarkdown(string $content): ?string
    {
        $ext = 'jpg|jpeg|gif|png|webp|bmp|avif';

        // 标准 Markdown: ![alt](url)
        if (preg_match('/!\[.*?\]\((https?:\/\/[^)\s]+?\.(?:' . $ext . '))(?:\?[^)\s]*)?(?:\s+["\'].*?["\'])?\)/i', $content, $match)) {
            return $match[1];
        }

        // 脚注式 Markdown: [id]: url
        if (preg_match('/\[.*?\]:\s*(https?:\/\/[^\s]+?\.(?:' . $ext . '))(?:\?[^\s]*)?/i', $content, $match)) {
            return $match[1];
        }

        return null;
    }

    /**
     * 从附件提取首张图片
     */
    private static function extractFromAttachments(int $cid): ?string
    {
        try {
            Related::allocWithAlias(
                '@content-' . $cid,
                'parentId=' . $cid
            )->to($attachments);

            while ($attachments->next()) {
                if (!empty($attachments->isImage) && $attachments->isImage == 1 && !empty($attachments->url)) {
                    return $attachments->url;
                }
            }
        } catch (Exception $e) {
            // 静默失败，避免附件查询异常导致页面崩溃
        }

        return null;
    }

    /**
     * 获取随机缩略图
     * 
     * 优先读取主题配置 randomThumbnailList，未配置则使用默认 {n}.jpg
     */
    private static function getRandomThumbnail($widget, ?string $pattern = null): string
    {
        $options = Widget::widget('Widget_Options');
        $themeUrl = rtrim($options->themeUrl, '/');

        // 1. 优先使用主题配置的随机图片列表
        $rawList = (string) ($options->randomThumbnailList ?? '');
        if (!empty($rawList)) {
            $lines = array_filter(array_map('trim', explode("\n", $rawList)));
            if (!empty($lines)) {
                $img = $lines[array_rand($lines)];
                // 相对路径拼接 themeUrl，完整 URL 直接返回
                if (!preg_match('/^https?:\/\//i', $img)) {
                    $img = $themeUrl . '/' . ltrim($img, '/');
                }
                return $img;
            }
        }

        // 2. 回退：使用默认 {n}.jpg 模板
        if (empty($pattern)) {
            $pattern = $themeUrl . '/assets/img/random/{n}.jpg';
        }
        return str_replace('{n}', (string) random_int(0, 9), $pattern);
    }

    /**
     * 黑名单过滤（插件图标、base64 内嵌等）
     */
    private static function isBlocked(string $url): bool
    {
        if (strpos($url, __TYPECHO_PLUGIN_DIR__ . '/TePass') !== false) {
            return true;
        }

        if (strpos($url, 'data:image') === 0) {
            return true;
        }

        return false;
    }

    /**
     * 输出或返回结果
     */
    private static function dispatch(?string $img, bool $return): ?string
    {
        if ($return) {
            return $img ?? '';
        }

        echo $img ?? '';
        return null;
    }
}