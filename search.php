<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) { exit; }
$this->need('header.php');

// ==================== 1. 关键词获取 ====================
// 优先用 Typecho 自带的 search keyword 解析（var/Widget/Archive.php searchHandle），
// 它会按伪静态/非伪静态自动适配，避免 PATH_INFO 在不同 rewrite 配置下吞错字符
$keywords = trim((string) $this->request->filter('url', 'search')->get('keywords'));

// 清理尾部斜杠、.html、零宽字符
$keywords = rtrim($keywords, '/');
$keywords = preg_replace('/\.html$/i', '', $keywords);
$keywords = preg_replace('/^[\s\x{200B}-\x{200D}\x{FEFF}]+|[\s\x{200B}-\x{200D}\x{FEFF}]+$/u', '', $keywords);

// 保险：拒掉任何包含路径分隔符的"伪 keyword"（如某些 rewrite 规则把 index.php 也写进 PATH_INFO）
if (strpbrk($keywords, "/?#") !== false) {
    $keywords = '';
}
$keywords = preg_replace('/^[\s\x{200B}-\x{200D}\x{FEFF}]+|[\s\x{200B}-\x{200D}\x{FEFF}]+$/u', '', $keywords);

$total = (int) $this->getTotal();
$currentPage = max(1, (int) $this->getCurrentPage());
$pageSize = max(1, (int) ($this->options->pageSize ?? 10));

$activeCategory = trim((string) $this->request->get('category', ''));
$activeTag      = trim((string) $this->request->get('tag', ''));
$activeTime     = trim((string) $this->request->get('time', ''));
$activeOrder    = trim((string) $this->request->get('order', ''));

$hasFilter = ($activeCategory !== '' || $activeTag !== '' || $activeTime !== '' || $activeOrder !== '');

// ==================== 2. URL 构建 ====================
$currentUri = $_SERVER['REQUEST_URI'] ?? '/';
$currentPath = parse_url($currentUri, PHP_URL_PATH) ?: '/';

$currentQuery = [];
foreach (['s', 'category', 'tag', 'time', 'order'] as $k) {
    $v = $this->request->get($k);
    if ($v !== null && $v !== '') {
        $currentQuery[$k] = $v;
    }
}

$allowedKeys = ['s', 'category', 'tag', 'time', 'order'];

function srBuildUrl(string $currentPath, array $currentQuery, array $allowedKeys, array $override = [], bool $resetPage = true): string {
    $base = array_intersect_key($currentQuery, array_flip($allowedKeys));
    foreach ($override as $k => $v) {
        if ($v === null) {
            unset($base[$k]);
        } else {
            $base[$k] = $v;
        }
    }
    $clean = [];
    foreach ($base as $k => $v) {
        if ($v === '' || $v === null || $v === 'all' || $v === 'relevance') continue;
        $clean[$k] = $v;
    }
    if ($resetPage) {
        unset($clean['page']);
    }
    if (empty($clean)) return $currentPath;
    return $currentPath . '?' . http_build_query($clean);
}

// ==================== 3. 侧边栏数据查询 ====================
$db = Typecho\Db::get();

$categories = $db->fetchAll(
    $db->select('mid', 'name', 'slug', 'count')
        ->from('table.metas')
        ->where('type = ?', 'category')
        ->order('count', Typecho\Db::SORT_DESC)
);

$tags = $db->fetchAll(
    $db->select('mid', 'name', 'slug', 'count')
        ->from('table.metas')
        ->where('type = ?', 'tag')
        ->order('count', Typecho\Db::SORT_DESC)
        ->limit(20)
);

// ==================== 4. 高亮辅助 ====================
$highlight = function(?string $text) use ($keywords): string {
    $text = (string) $text;
    if ($keywords === '' || $text === '') {
        return $text;
    }
    $replacement = '<span class="sr-highlight">' . htmlspecialchars($keywords, ENT_QUOTES, 'UTF-8') . '</span>';
    return str_ireplace($keywords, $replacement, $text);
};

// ==================== 5. 结果收集 + 统计 + 过滤 + 排序 ====================
$rawResults = [];
if ($this->have()) {
    while ($this->next()) {
        $cats = [];
        foreach ($this->categories ?? [] as $c) {
            $cats[] = [
                'mid' => is_object($c) ? (int) ($c->mid ?? 0) : (int) ($c['mid'] ?? 0),
                'name' => is_object($c) ? (string) ($c->name ?? '') : (string) ($c['name'] ?? ''),
                'slug' => is_object($c) ? (string) ($c->slug ?? '') : (string) ($c['slug'] ?? ''),
                'permalink' => is_object($c) ? (string) ($c->permalink ?? '') : (string) ($c['permalink'] ?? ''),
            ];
        }
        $ts = [];
        foreach ($this->tags ?? [] as $t) {
            $ts[] = [
                'mid' => is_object($t) ? (int) ($t->mid ?? 0) : (int) ($t['mid'] ?? 0),
                'name' => is_object($t) ? (string) ($t->name ?? '') : (string) ($t['name'] ?? ''),
                'slug' => is_object($t) ? (string) ($t->slug ?? '') : (string) ($t['slug'] ?? ''),
                'permalink' => is_object($t) ? (string) ($t->permalink ?? '') : (string) ($t['permalink'] ?? ''),
            ];
        }
        $rawResults[] = [
            'title'       => (string) $this->title,
            'permalink'   => (string) $this->permalink,
            'cid'         => (int) $this->cid,
            'created'     => (int) $this->created,
            'content'     => (string) $this->content,
            'categories'  => $cats,
            'tags'        => $ts,
            'coverImg'    => ThumbnailHelper::showThumbnail($this, true),
            'cat'         => AstroPro::getPostCategory($this->cid, $this),
            'excerpt'     => AstroPro::excerpt($this->content, 160),
            'readingTime' => AstroPro::readingTime($this),
            'views'       => AstroPro::getPostView($this, 1),
        ];
    }
}

$categoryCounts = [];
$tagCounts = [];
foreach ($rawResults as $post) {
    foreach ($post['categories'] as $c) {
        if (!empty($c['slug'])) {
            $categoryCounts[$c['slug']] = ($categoryCounts[$c['slug']] ?? 0) + 1;
        }
    }
    foreach ($post['tags'] as $t) {
        if (!empty($t['slug'])) {
            $tagCounts[$t['slug']] = ($tagCounts[$t['slug']] ?? 0) + 1;
        }
    }
}

$results = $rawResults;
if ($hasFilter) {
    $results = array_filter($rawResults, function ($post) use ($activeCategory, $activeTag, $activeTime) {
        if ($activeCategory !== '') {
            $has = false;
            foreach ($post['categories'] as $c) {
                if (($c['slug'] ?? '') === $activeCategory) {
                    $has = true;
                    break;
                }
            }
            if (!$has) return false;
        }
        if ($activeTag !== '') {
            $has = false;
            foreach ($post['tags'] as $t) {
                if (($t['slug'] ?? '') === $activeTag) {
                    $has = true;
                    break;
                }
            }
            if (!$has) return false;
        }
        if ($activeTime !== '') {
            $now = time();
            $created = $post['created'];
            $pass = false;
            switch ($activeTime) {
                case 'day':   $pass = ($now - $created) < 86400; break;
                case 'week':  $pass = ($now - $created) < 604800; break;
                case 'month': $pass = ($now - $created) < 2592000; break;
                case 'year':  $pass = ($now - $created) < 31536000; break;
            }
            if (!$pass) return false;
        }
        return true;
    });

    switch ($activeOrder) {
        case 'newest':
            usort($results, fn ($a, $b) => $b['created'] <=> $a['created']);
            break;
        case 'oldest':
            usort($results, fn ($a, $b) => $a['created'] <=> $b['created']);
            break;
        case 'popular':
            usort($results, fn ($a, $b) => $b['views'] <=> $a['views']);
            break;
        default:
            break;
    }
}

$filteredTotal = count($results);
$start = $filteredTotal > 0 ? (($currentPage - 1) * $pageSize + 1) : 0;
$end = min($currentPage * $pageSize, $filteredTotal);

if ($hasFilter) {
    $results = array_slice($results, ($currentPage - 1) * $pageSize, $pageSize);
}
?>

<div class="search-container">
<main id="main-content" class="sr-wrapper">
    <!-- 搜索状态栏 -->
    <div class="sr-status-bar">
        <div class="sr-status-left">
            <a class="btn btn-ghost" href="<?php $this->options->index(); ?>">
                <span class="material-icons" aria-hidden="true">arrow_back</span>返回首页
            </a>
            <p class="sr-status-text">
                <?php if ($filteredTotal > 0): ?>
                    找到 <strong><?= $filteredTotal ?></strong> 篇<?php if ($keywords !== ''): ?>与 <strong>「<?php AstroPro::esc($keywords); ?>」</strong> 相关<?php endif; ?>的文章
                <?php else: ?>
                    <?php if ($keywords !== '' || $hasFilter): ?>
                        未找到<?php if ($keywords !== ''): ?>与 <strong>「<?php AstroPro::esc($keywords); ?>」</strong> 相关<?php endif; ?>的文章
                    <?php else: ?>
                        请输入关键词进行搜索
                    <?php endif; ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="sr-search-box-inline">
            <span class="material-icons sr-search-icon-inline">search</span>
            <input 
                aria-label="搜索文章" 
                autocomplete="off" 
                class="sr-search-input-inline" 
                id="srSearchInput" 
                placeholder="输入关键词搜索文章…" 
                type="text" 
                value="<?php AstroPro::esc($keywords); ?>"
            >
            <button aria-label="清空搜索" class="sr-search-clear-inline" id="srSearchClear" style="display:none" type="button">
                <span class="material-icons" style="font-size:16px;">cancel</span>
            </button>
        </div>
    </div>

    <div class="sr-layout">
        <aside aria-label="搜索结果过滤" class="sr-sidebar">
            <!-- 时间过滤 -->
            <div class="sr-filter-card">
                <h2 class="sr-filter-title">
                    <span class="material-icons" aria-hidden="true">calendar_month</span>发布时间
                </h2>
                <nav aria-label="按时间筛选" class="sr-time-filters">
                    <?php
                    $timeFilters = [
                        '' => ['icon' => 'all_inclusive', 'label' => '全部时间'],
                        'day' => ['icon' => 'today', 'label' => '最近一天'],
                        'week' => ['icon' => 'date_range', 'label' => '最近一周'],
                        'month' => ['icon' => 'calendar_view_month', 'label' => '最近一月'],
                        'year' => ['icon' => 'calendar_today', 'label' => '最近一年'],
                    ];
                    foreach ($timeFilters as $key => $item):
                        $isActive = $activeTime === $key;
                        $url = $isActive 
                            ? srBuildUrl($currentPath, $currentQuery, $allowedKeys, ['time' => null]) 
                            : ($key === '' 
                                ? srBuildUrl($currentPath, $currentQuery, $allowedKeys, ['time' => null]) 
                                : srBuildUrl($currentPath, $currentQuery, $allowedKeys, ['time' => $key]));
                    ?>
                    <a class="sr-time-item <?= $isActive ? 'active' : '' ?>" href="<?= $url ?>">
                        <span class="material-icons" aria-hidden="true"><?= $item['icon'] ?></span><?= $item['label'] ?>
                    </a>
                    <?php endforeach; ?>
                </nav>
            </div>

            <!-- 分类过滤 -->
            <?php if (!empty($categories)): ?>
            <div class="sr-filter-card">
                <h2 class="sr-filter-title">
                    <span class="material-icons" aria-hidden="true">folder</span>分类筛选
                </h2>
                <nav aria-label="按分类筛选" class="sr-filter-list">
                    <?php 
                    $allActive = empty($activeCategory);
                    $allUrl = srBuildUrl($currentPath, $currentQuery, $allowedKeys, ['category' => null]);
                    ?>
                    <a class="sr-filter-item <?= $allActive ? 'active' : '' ?>" href="<?= $allUrl ?>">
                        <span class="material-icons" aria-hidden="true">apps</span>全部<span class="sr-filter-count"><?= $filteredTotal ?></span>
                    </a>
                    <?php foreach ($categories as $cat): 
                        $catSlug = $cat['slug'] ?? '';
                        $catCount = $categoryCounts[$catSlug] ?? 0;
                        if ($catCount === 0 && $activeCategory !== $catSlug) continue;
                        
                        $isActive = $activeCategory === $catSlug;
                        $url = $isActive 
                            ? srBuildUrl($currentPath, $currentQuery, $allowedKeys, ['category' => null]) 
                            : srBuildUrl($currentPath, $currentQuery, $allowedKeys, ['category' => $catSlug]);
                    ?>
                    <a class="sr-filter-item <?= $isActive ? 'active' : '' ?>" href="<?= $url ?>">
                        <?php AstroPro::icon('category', (int) ($cat['mid'] ?? 0)); ?>
                        <?php AstroPro::esc($cat['name']); ?>
                        <span class="sr-filter-count"><?= $catCount ?></span>
                    </a>
                    <?php endforeach; ?>
                </nav>
            </div>
            <?php endif; ?>

            <!-- 标签过滤 -->
            <?php if (!empty($tags)): ?>
            <div class="sr-filter-card">
                <h2 class="sr-filter-title">
                    <span class="material-icons" aria-hidden="true">sell</span>标签筛选
                </h2>
                <nav aria-label="按标签筛选" class="sr-tag-filters">
                    <?php foreach ($tags as $tag): 
                        $tagSlug = $tag['slug'] ?? '';
                        $tagCount = $tagCounts[$tagSlug] ?? 0;
                        if ($tagCount === 0 && $activeTag !== $tagSlug) continue;
                        
                        $isActive = $activeTag === $tagSlug;
                        $url = $isActive 
                            ? srBuildUrl($currentPath, $currentQuery, $allowedKeys, ['tag' => null]) 
                            : srBuildUrl($currentPath, $currentQuery, $allowedKeys, ['tag' => $tagSlug]);
                    ?>
                    <a class="sr-tag-filter <?= $isActive ? 'active' : '' ?>" href="<?= $url ?>">
                        <?php AstroPro::esc($tag['name']); ?>
                        <?php if ($tagCount > 0): ?><span class="sr-tag-count"><?= $tagCount ?></span><?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </nav>
            </div>
            <?php endif; ?>
        </aside>

        <section aria-label="搜索结果列表" class="sr-results-area">
            <div class="sr-results-toolbar">
                <p class="sr-results-info">
                    <?php if ($filteredTotal > 0): ?>
                        显示第 <strong><?= $start ?></strong> - <strong><?= $end ?></strong> 条，共 <strong><?= $filteredTotal ?></strong> 条结果
                    <?php else: ?>
                        共 <strong>0</strong> 条结果
                    <?php endif; ?>
                </p>
                <div class="sr-sort-wrap">
                    <span class="sr-sort-label">排序：</span>
                    <label class="sr-sr-only" for="srSort" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;">排序方式</label>
                    <select aria-label="排序方式" class="sr-sort-select" id="srSort">
                        <?php
                        $orders = [
                            '' => '默认排序',
                            'newest' => '最新发布',
                            'oldest' => '最早发布',
                            'popular' => '最受欢迎',
                        ];
                        foreach ($orders as $key => $label):
                            $url = srBuildUrl($currentPath, $currentQuery, $allowedKeys, ['order' => $key]);
                            $isSelected = $activeOrder === $key || (empty($activeOrder) && empty($key));
                        ?>
                        <option value="<?= $url ?>" <?= $isSelected ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="sr-results-list">
                <?php if (!empty($results)): ?>
                    <?php 
                    $index = 0;
                    foreach ($results as $post): 
                        $index++;
                        $day = date('d', $post['created']);
                        $month = date('M', $post['created']);
                        $year = date('Y', $post['created']);
                        $titleHtml = $highlight($post['title']);
                        $excerptHtml = $highlight($post['excerpt']);
                    ?>
                    <article class="sr-result-card">
                        <span class="sr-result-index"><?= str_pad((string) $index, 2, '0', STR_PAD_LEFT) ?></span>
                        <div class="sr-result-date-block">
                            <span class="sr-result-day"><?= $day ?></span>
                            <span class="sr-result-month"><?= $month ?></span>
                            <span class="sr-result-year"><?= $year ?></span>
                        </div>
                        <div class="sr-result-body">
                            <?php if (!empty($post['coverImg'])): ?>
                            <div class="sr-result-thumb mobile-thumb">
                                <a href="<?= $post['permalink'] ?>">
                                    <img alt="<?php AstroPro::esc($post['title']); ?>" loading="lazy" src="<?= $post['coverImg'] ?>"/>
                                </a>
                            </div>
                            <?php endif; ?>
                            <div class="sr-result-meta-row">
                                <?php if ($post['cat']): ?>
                                <span class="sr-result-cat-badge">
                                    <a href="<?= $post['cat']['permalink'] ?>">
                                        <?php AstroPro::icon('category', (int) $post['cat']['mid']); ?>
                                        <?php AstroPro::esc($post['cat']['name']); ?>
                                    </a>
                                </span>
                                <span class="sr-result-meta-dot"></span>
                                <?php endif; ?>
                                <span class="sr-result-read-time">
                                    <span class="material-icons" aria-hidden="true">schedule</span>
                                    阅读约 <?= $post['readingTime'] ?>
                                </span>
                            </div>
                            <h2 class="sr-result-title">
                                <a href="<?= $post['permalink'] ?>"><?= $titleHtml ?></a>
                            </h2>
                            <p class="sr-result-excerpt"><?= $excerptHtml ?></p>
                            <div class="sr-result-footer">
                                <div class="sr-result-tags">
                                    <?php if (!empty($post['tags'])): ?>
                                        <?php foreach ($post['tags'] as $tag): ?>
                                        <a class="sr-result-tag" href="<?= $tag['permalink'] ?>">
                                            <span class="material-icons" aria-hidden="true">local_offer</span>
                                            <?php AstroPro::esc($tag['name']); ?>
                                        </a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($post['coverImg'])): ?>
                                <div class="sr-result-thumb">
                                    <a href="<?= $post['permalink'] ?>">
                                        <img alt="<?php AstroPro::esc($post['title']); ?>" loading="lazy" src="<?= $post['coverImg'] ?>"/>
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="sr-result-arrow-hint">
                            <span class="material-icons" aria-hidden="true">arrow_forward</span>
                        </div>
                    </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="sr-no-results" style="padding: 48px 0; text-align: center; color: var(--text-muted);">
                        <span class="material-icons" style="font-size: 48px; margin-bottom: 16px; display: block; opacity: 0.5;">search_off</span>
                        <p style="font-size: 1.125rem; font-weight: 600; margin-bottom: 8px;">未找到相关文章</p>
                        <p style="font-size: 0.875rem;">
                            <?php if ($keywords !== '' || $hasFilter): ?>试试其他关键词或筛选条件吧<?php else: ?>请输入关键词开始搜索<?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($filteredTotal > $pageSize): ?>
            <?php $this->pageNav(
                '<span class="material-icons" aria-hidden="true">chevron_left</span>',
                '<span class="material-icons" aria-hidden="true">chevron_right</span>',
                2,
                '...',
                [
                    'wrapTag'      => 'div',
                    'wrapClass'    => 'sr-pagination',
                    'itemTag'      => '',
                    'currentClass' => 'sr-page-btn active',
                    'prevClass'    => 'sr-page-btn',
                    'nextClass'    => 'sr-page-btn',
                    'textClass'    => 'sr-page-btn'
                ]
            ); ?>
            <?php endif; ?>
        </section>
    </div>
</main>

<script>
(function() {
    'use strict';
    var srInput = document.getElementById('srSearchInput');
    var srClear = document.getElementById('srSearchClear');
    if (!srInput || srInput.dataset.srBound) return;
    srInput.dataset.srBound = '1';

    // 复用全局 ghost a 触发 pjax（由 footer 选区接住）
    var ghost = document.getElementById('__pjax_search_trigger');
    if (!ghost) {
        ghost = document.createElement('a');
        ghost.id = '__pjax_search_trigger';
        ghost.style.display = 'none';
        ghost.setAttribute('aria-hidden', 'true');
        ghost.setAttribute('tabindex', '-1');
        ghost.href = '/';
        document.body.appendChild(ghost);
    }

    srInput.addEventListener('input', function() {
        if (srClear) srClear.style.display = this.value ? 'flex' : 'none';
    });
    if (srInput.value && srClear) srClear.style.display = 'flex';

    if (srClear) {
        srClear.addEventListener('click', function() {
            srInput.value = '';
            srInput.focus();
            srClear.style.display = 'none';
        });
    }

    srInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            var val = this.value.trim();
            if (!val) return;
            e.preventDefault();

            // 记录热搜（与 popup submit 共用 record 接口；失败不影响跳转）
            try {
                var recordUrl = '/?action=record&keyword=' + encodeURIComponent(val);
                if (navigator.sendBeacon) {
                    navigator.sendBeacon(recordUrl);
                } else {
                    fetch(recordUrl, { keepalive: true, method: 'GET' }).catch(function() {});
                }
            } catch (err) { /* 静默失败 */ }

            // 标记本次提交了一个搜索，closeSearch 时会预刷一次热搜
            window.__searchPendingRecord = true;
            // 走 Typecho 规范 search URL：__searchUrlTpl = siteUrl + '/index.php/search/{keyword}/'
            // 避免 ?s=xxx 被 Typecho 路由 302 跳到 /index.php/search/（丢 keyword） 的坑
            var tpl = (window.__searchUrlTpl || '/index.php/search/{keyword}/');
            ghost.href = tpl.replace('{keyword}', encodeURIComponent(val));
            ghost.click();
        }
    });
})();
</script>

</div>

<?php $this->need('footer.php'); ?>