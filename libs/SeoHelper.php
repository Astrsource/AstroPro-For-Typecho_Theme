<?php
declare(strict_types=1);

/**
 * SEO 辅助类
 * 
 * 封装所有 SEO 元标签生成逻辑，零输出缓冲，直接流式输出。
 */
final class SeoHelper
{
    private const ROBOTS_INDEX = 'index, follow, max-snippet:-1, max-video-preview:-1, max-image-preview:large';
    private const ROBOTS_NOINDEX = 'noindex, follow';
    private const ROBOTS_NONE = 'noindex, nofollow';

    /** @var \Widget\Options 缓存的站点配置 */
    private readonly \Widget\Options $options;

    public function __construct(
        private readonly \Widget\Archive $archive
    ) {
        // 若 archive->options 为 null（如 404 页），回退到 Helper::options()
        $this->options = $this->archive->options ?? \Helper::options();
    }

    /* ==================== 核心 SEO ==================== */

    public function robots(): void
    {
        [$robots, $canonical] = $this->resolveRobotsAndCanonical();

        printf('<meta name="robots" content="%s">' . "\n", $robots);
        if ($canonical !== null) {
            printf('<link rel="canonical" href="%s">' . "\n", $canonical);
        }
    }

    /** @return array{string, string|null} */
    private function resolveRobotsAndCanonical(): array
    {
        return match (true) {
            $this->archive->is('search'), $this->archive->is('404') => 
                [self::ROBOTS_NONE, null],

            $this->archive->is('archive') => 
                [self::ROBOTS_NOINDEX, $this->canonicalUrl()],

            $this->archive->is('index') && $this->archive->getCurrentPage() > 1 => 
                [self::ROBOTS_NOINDEX, $this->canonicalUrl()],

            $this->archive->is('category'), $this->archive->is('tag') => 
                [$this->archive->getCurrentPage() > 1 ? self::ROBOTS_NOINDEX : self::ROBOTS_INDEX, $this->canonicalUrl()],

            $this->archive->is('post') => 
                [($this->archive->_commentsCurrentPage ?? 1) > 1 ? self::ROBOTS_NOINDEX : self::ROBOTS_INDEX, $this->archive->permalink],

            $this->archive->is('page') => 
                [self::ROBOTS_INDEX, $this->archive->permalink],

            default => [self::ROBOTS_INDEX, $this->canonicalUrl()],
        };
    }

    private function canonicalUrl(): string
    {
        $url = $this->archive->archiveUrl ?? '';
        if (empty($url)) {
            $url = $this->options->siteUrl ?? '';
        }
        return rtrim((string) $url, '/') . '/';
    }

    /* ==================== Keywords / Description ==================== */

    public function keywords(): void
    {
        // 单页（文章/独立页）：优先读自定义字段 k，没有则走 Typecho 默认逻辑
        if ($this->archive->is('single')) {
            $k = $this->archive->fields?->k ?? '';
            if ($k !== '') {
                echo $k;
                return;
            }
            // 文章页默认输出标签
            $this->archive->keywords();
            return;
        }

        // 非单页（首页、分类、标签、归档、搜索等）：直接读后台设置的站点 keywords
        // 避免 Typecho 1.3 在循环后把最后一篇文章的字段当成站点值
        echo htmlspecialchars(
            (string) ($this->options->keywords ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }

    public function description(): void
    {
        // 单页（文章/独立页）：优先读自定义字段 d，没有则生成智能摘要
        if ($this->archive->is('single')) {
            $d = $this->archive->fields?->d ?? '';
            if ($d !== '') {
                echo htmlspecialchars($d, ENT_QUOTES, 'UTF-8');
                return;
            }
            echo AstroPro::excerpt(
                (string) ($this->archive->content ?? ''),
                160,
                '...',
                true,
                true
            );
            return;
        }

        // 非单页（首页、分类、标签、归档、搜索等）：直接读后台设置的站点 description
        // 避免 Typecho 1.3 在循环后把最后一篇文章的字段当成站点值
        echo htmlspecialchars(
            (string) ($this->options->description ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }

    /* ==================== 文章页专属 ==================== */

    public function articleMeta(): void
    {
        if (!$this->archive->is('post')) {
            return;
        }

        $created  = date('c', $this->archive->created);
        $modified = date('c', $this->archive->modified);

        echo "    <meta property=\"article:published_time\" content=\"{$created}\">\n";
        echo "    <meta property=\"article:modified_time\" content=\"{$modified}\">\n";
        echo "    <meta property=\"bytedance:published_time\" content=\"{$created}\">\n";
        echo "    <meta property=\"bytedance:updated_time\" content=\"{$modified}\">\n";
        echo "    <meta property=\"article:author\" content=\"";
        $this->archive->author();
        echo "\">";

        if (!empty($this->archive->tags)) {
            foreach ($this->archive->tags as $tag) {
                $name = htmlspecialchars($tag['name'] ?? '');
                echo "\n    <meta property=\"article:tag\" content=\"{$name}\">";
            }
        }
    }

    /* ==================== Open Graph ==================== */

    public function og(string $metaImage, string $favicon): void
    {
        $siteName = htmlspecialchars((string) ($this->options->title ?? ''));
        $type     = $this->archive->is('post') || $this->archive->is('page') ? 'article' : 'website';
        $url      = $this->archive->is('single') ? $this->archive->permalink : ($this->options->rootUrl ?? '');
        
        // 单页：优先用文章缩略图；若缩略图为空则回退到 favicon
        // 首页/分类等：统一使用 favicon
        $image    = $this->archive->is('single') ? ($metaImage ?: $favicon) : $favicon;

        echo "    <meta property=\"og:locale\" content=\"zh_CN\">\n";
        echo "    <meta property=\"og:site_name\" content=\"{$siteName}\">\n";
        echo "    <meta property=\"og:type\" content=\"{$type}\">\n";
        echo "    <meta property=\"og:url\" content=\"{$url}\">\n";
        echo "    <meta property=\"og:title\" content=\"";
        $this->baseTitle();
        echo "\">\n";
        echo "    <meta property=\"og:description\" content=\"";
        $this->description();
        echo "\">\n";
        echo "    <meta property=\"og:image\" content=\"{$image}\">\n";
        echo "    <meta property=\"og:image:secure_url\" content=\"{$image}\">\n"; 
        echo "    <meta property=\"og:image:alt\" content=\"";
        $this->baseTitle();
        echo "\">";
    }

    /* ==================== Twitter Card ==================== */

    public function twitter(string $metaImage, string $favicon): void
    {
        $url   = $this->archive->is('single') ? $this->archive->permalink : ($this->options->rootUrl ?? '');
        $image = $this->archive->is('single') ? $metaImage : ($this->options->ogImage ?? $favicon);

        echo "    <meta name=\"twitter:card\" content=\"summary_large_image\">\n";
        echo "    <meta property=\"twitter:creator\" content=\"";
        $this->archive->author();
        echo "\">";
        echo "    <meta name=\"twitter:title\" content=\"";
        $this->baseTitle();
        echo "\">\n";
        echo "    <meta name=\"twitter:description\" content=\"";
        $this->description();
        echo "\">\n";
        echo "    <meta name=\"twitter:image\" content=\"{$image}\">\n";
        echo "    <meta name=\"twitter:image:alt\" content=\"";
        $this->baseTitle();
        echo "\">\n";
        echo "    <meta name=\"twitter:url\" content=\"{$url}\">";
    }

    /* ==================== Title ==================== */

    /**
     * 基础标题（用于 OG / Twitter / 面包屑等）
     */
    public function baseTitle(): void
    {
        if ($this->archive->_currentPage > 1) {
            echo '第 ', $this->archive->_currentPage, ' 页 - ';
        }
        $this->archive->archiveTitle([
            'category' => '%s',
            'search'   => '包含"%s"的搜索结果',
            'tag'      => '%s',
            'author'   => '%s 发布的文章'
        ], '', ' - ');
        $this->options->title();
    }

    /**
     * 输出 <title>（含首页 SEO 后缀）
     */
    public function title(): void
    {
        echo '<title>';
        $this->baseTitle();
        if ($this->archive->is('index') && $this->archive->_currentPage == 1) {
            echo ' - ', htmlspecialchars((string) ($this->options->description ?? '')), ' | ', htmlspecialchars((string) ($this->options->keywords ?? ''));
        }
        echo '</title>';
    }
}