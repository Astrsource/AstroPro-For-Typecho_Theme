<?php
declare(strict_types=1);
/**
 * 文章归档页面模板 - 含点阵热图与雷达分析（本年度数据）
 * @package custom
 */
if (!defined('__TYPECHO_ROOT_DIR__')) { exit; }
$this->need('header.php');

// 基础配置
$db = Typecho_Db::get();
$timezone = new DateTimeZone('Asia/Shanghai');
$options = Typecho_Widget::widget('Widget_Options');

// 本年度起止时间戳（与热图 12 个月标签匹配）
$currentYear = (int) date('Y');
$yearStart = strtotime($currentYear . '-01-01 00:00:00');
$yearEnd = strtotime(($currentYear + 1) . '-01-01 00:00:00') - 1;

// 分页
$pageSize = 10;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $pageSize;

// 当前页文章
$rows = $db->fetchAll(
    $db->select()
        ->from('table.contents')
        ->where('type = ?', 'post')
        ->where('status = ?', 'publish')
        ->order('created', Typecho_Db::SORT_DESC)
        ->limit($pageSize)
        ->offset($offset)
);

// 强制转为整型，避免字符串与整数 === 比较失效
$totalCount = (int) ($db->fetchObject(
    $db->select(array('COUNT(cid)' => 'num'))
        ->from('table.contents')
        ->where('type = ?', 'post')
        ->where('status = ?', 'publish')
)->num ?? 0);

$totalPages = (int) ceil($totalCount / $pageSize);

// 全量统计（侧边栏使用全量）
$allPosts = $db->fetchAll(
    $db->select('cid', 'created')
        ->from('table.contents')
        ->where('type = ?', 'post')
        ->where('status = ?', 'publish')
);

$allCategoryInfo = [];
$allTagInfo = [];
$archiveByYear = [];

// 一次遍历：分类/标签统计 + 按月归档分组（带前导零显示）
foreach ($allPosts as $post) {
    $cid = (int) $post['cid'];
    $date = (new DateTime('@' . $post['created']))->setTimezone($timezone);
    $y = $date->format('Y');
    $m = $date->format('m');
    $key = "$y-$m";

    // 归档分组
    if (!isset($archiveByYear[$y][$key])) {
        $archiveByYear[$y][$key] = [
            'month'     => $m . '月',
            'count'     => 0,
            'permalink' => Typecho_Common::url("$y/$m/", $options->index),
        ];
    }
    $archiveByYear[$y][$key]['count']++;

    // 分类 & 标签一次查询搞定
    foreach ($db->fetchAll(
        $db->select()->from('table.relationships')
            ->join('table.metas', 'table.relationships.mid = table.metas.mid')
            ->where('table.relationships.cid = ?', $cid)
    ) as $r) {
        if ($r['type'] === 'category') {
            $allCategoryInfo[$r['name']] ??= ['slug' => $r['slug'], 'mid' => (int) $r['mid'], 'count' => 0];
            $allCategoryInfo[$r['name']]['count']++;
        } else {
            $allTagInfo[$r['name']] ??= ['slug' => $r['slug'], 'count' => 0];
            $allTagInfo[$r['name']]['count']++;
        }
    }
}

// 排序：年份倒序、月份倒序
krsort($archiveByYear);
foreach ($archiveByYear as &$months) krsort($months);
unset($months);

// 全量排序（侧边栏用）
uasort($allCategoryInfo, function($a, $b) { return $b['count'] - $a['count']; });
uasort($allTagInfo, function($a, $b) { return $b['count'] - $a['count']; });

// 本年度统计（热图 + 雷达，仅在第一页需要）
$categoriesJson = '[]';
$tagsJson = '[]';
$heatmapJson = '{}';

if ($currentPage === 1 && $totalCount > 0) {
    $recentPostsWithDate = $db->fetchAll(
        $db->select('cid', 'created')
            ->from('table.contents')
            ->where('type = ?', 'post')
            ->where('status = ?', 'publish')
            ->where('created >= ?', $yearStart)
            ->where('created <= ?', $yearEnd)
    );

    $recentCategoryInfo = [];
    $recentTagInfo = [];
    $postsByMonthWeekday = [];

    foreach ($recentPostsWithDate as $post) {
        $cid = (int) $post['cid'];
        $date = (new DateTime('@' . $post['created']))->setTimezone($timezone);
        $mk = $date->format('Y-m');
        $postsByMonthWeekday[$mk] ??= array_fill(0, 7, 0);
        $postsByMonthWeekday[$mk][(int) $date->format('w')]++;

        // 本年度分类 & 标签一次查询
        foreach ($db->fetchAll(
            $db->select()->from('table.relationships')
                ->join('table.metas', 'table.relationships.mid = table.metas.mid')
                ->where('table.relationships.cid = ?', $cid)
        ) as $r) {
            if ($r['type'] === 'category') {
                $recentCategoryInfo[$r['name']] ??= ['slug' => $r['slug'], 'count' => 0];
                $recentCategoryInfo[$r['name']]['count']++;
            } else {
                $recentTagInfo[$r['name']] ??= ['slug' => $r['slug'], 'count' => 0];
                $recentTagInfo[$r['name']]['count']++;
            }
        }
    }

    // 雷达 Top5
    uasort($recentCategoryInfo, function($a, $b) { return $b['count'] - $a['count']; });
    uasort($recentTagInfo, function($a, $b) { return $b['count'] - $a['count']; });
    $recentCategoriesTop5 = array_slice($recentCategoryInfo, 0, 5, true);
    $recentTagsTop5 = array_slice($recentTagInfo, 0, 5, true);

    // JSON 数据
    $categoriesJson = json_encode(array_map(function($name, $info) {
        return ['label' => $name, 'value' => $info['count']];
    }, array_keys($recentCategoriesTop5), $recentCategoriesTop5));

    $tagsJson = json_encode(array_map(function($name, $info) {
        return ['label' => $name, 'value' => $info['count']];
    }, array_keys($recentTagsTop5), $recentTagsTop5));

    $heatmapJson = json_encode($postsByMonthWeekday);
}

// 最早发布文章的年份（用于统计区块）—— 确保为字符串
$firstYear = date('Y');
if ($totalCount > 0 && !empty($allPosts)) {
    $firstYear = date('Y', (int) min(array_column($allPosts, 'created')));
}

// 修复分页链接叠加问题：移除当前 URL 中的 page 参数
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$urlParts = parse_url($requestUri);
$queryParams = [];
if (isset($urlParts['query'])) {
    parse_str($urlParts['query'], $queryParams);
}
unset($queryParams['page']);
$basePath = $urlParts['path'] ?? '/';
$queryString = http_build_query($queryParams);
$baseUrl = $basePath . ($queryString ? '?' . $queryString . '&' : '?');

// 辅助函数：增加存在性检查，防止重复定义报错
if (!function_exists('pageUrl')) {
    function pageUrl($baseUrl, $page) {
        return $baseUrl . 'page=' . $page;
    }
}
?>
<div class="archive-container">
<!-- ==================== 文章归档页主内容 ==================== -->
<main id="main-content" class="archive-wrapper">
<header class="archive-header">
    <div class="archive-header-inner">
        <div class="archive-header-left">
            <div class="archive-header-icon"><span class="material-icons" aria-hidden="true">auto_stories</span></div>
            <div>
                <h1 class="archive-header-title">文章归档</h1>
                <p class="archive-header-subtitle">浏览所有已发布的文章</p>
            </div>
        </div>
        <div class="archive-header-stats">
            <div class="archive-stat"><div class="archive-stat-number"><?= (int) $totalCount ?></div><div class="archive-stat-label">篇文章</div></div>
            <div class="archive-stat"><div class="archive-stat-number"><?= (int) count($allCategoryInfo) ?></div><div class="archive-stat-label">个分类</div></div>
            <div class="archive-stat"><div class="archive-stat-number"><?= htmlspecialchars((string) $firstYear) ?></div><div class="archive-stat-label">年至今</div></div>
        </div>
    </div>
</header>

<div class="archive-layout">
    <aside class="archive-sidebar">
        <div class="archive-filter-card">
            <div class="archive-filter-title"><span class="material-icons" aria-hidden="true">search</span> 搜索文章</div>
            <form id="search" method="get" class="archive-search-wrap" action="/" role="search" data-no-pjax>
                <input type="text" id="s" name="s" class="text archive-search-input" placeholder="<?php _e('输入关键词搜索'); ?>" aria-label="搜索文章">
                <span class="material-icons archive-search-icon">search</span>
            </form>
        </div>

        <?php if (!empty($archiveByYear)) { ?>
        <div class="archive-filter-card">
            <div class="archive-filter-title"><span class="material-icons" aria-hidden="true">calendar_month</span> 时间发布</div>
            <div class="accordion-group" data-accordion="single">
                <?php foreach ($archiveByYear as $year => $months) { ?>
                <div class="accordion-item">
                    <button aria-expanded="false" class="accordion-header" type="button">
                        <span><?= htmlspecialchars((string) $year) ?> 年</span>
                        <span class="accordion-arrow material-icons">expand_more</span>
                    </button>
                    <div class="accordion-panel">
                        <ul class="archive-month-list">
                            <?php foreach ($months as $monthKey => $monthData) { ?>
                            <li>
                                <a href="<?= htmlspecialchars((string) $monthData['permalink']) ?>">
                                    <?= htmlspecialchars((string) $monthData['month']) ?>
                                    <span class="month-count"><?= (int) $monthData['count'] ?></span>
                                </a>
                            </li>
                            <?php } ?>
                        </ul>
                    </div>
                </div>
                <?php } ?>
            </div>
        </div>
        <?php } ?>

        <div class="archive-filter-card">
            <div class="archive-filter-title"><span class="material-icons" aria-hidden="true">folder_open</span> 文章分类</div>
            <ul class="archive-category-list">
                <?php foreach ($allCategoryInfo as $name => $info) {
                    $catUrl = Typecho_Router::url('category', ['slug' => $info['slug']], $options->index); ?>
                <li>
                    <a href="<?= htmlspecialchars((string) $catUrl) ?>" class="archive-category-item">
                        <?php AstroPro::icon('category', $info['mid']); ?>
                        <?= htmlspecialchars((string) $name) ?>
                        <span class="category-count"><?= (int) $info['count'] ?></span>
                    </a>
                </li>
                <?php } ?>
            </ul>
        </div>

        <div class="archive-filter-card">
            <div class="archive-filter-title"><span class="material-icons" aria-hidden="true">sell</span> 全部标签</div>
            <div class="archive-tag-cloud">
                <?php foreach ($allTagInfo as $name => $info) {
                    $tagUrl = Typecho_Router::url('tag', ['slug' => $info['slug']], $options->index); ?>
                <a href="<?= htmlspecialchars((string) $tagUrl) ?>" class="archive-tag-item">
                    <span class="material-icons" style="font-size:14px;">local_offer</span>
                    <?= htmlspecialchars((string) $name) ?>
                    <span class="tag-count"><?= (int) $info['count'] ?></span>
                </a>
                <?php } ?>
            </div>
        </div>
    </aside>

    <div class="archive-content">

        <?php if ($currentPage === 1 && $totalCount > 0) { ?>
        <!-- 点阵热图（本年度） -->
        <div class="archive-filter-card archive-dot-card">
            <div class="archive-filter-title"><span class="material-icons" aria-hidden="true">calendar_month</span> 文章发布热图（本年度）</div>
            <div class="dot-heatmap-wrap"><div class="dot-heatmap-scroll"><div class="dot-heatmap-grid" id="dotHeatmapGrid"></div></div></div>
            <div class="dot-legend">
                <span>Less</span>
                <span class="dot-legend-swatch" style="background:var(--dot-level-0, #ebedf0);"></span>
                <span class="dot-legend-swatch" style="background:var(--dot-level-1, #9be9a8);"></span>
                <span class="dot-legend-swatch" style="background:var(--dot-level-2, #40c463);"></span>
                <span class="dot-legend-swatch" style="background:var(--dot-level-3, #30a14e);"></span>
                <span class="dot-legend-swatch" style="background:var(--dot-level-4, #216e39);"></span>
                <span>More</span>
            </div>
            <div class="radar-note-static">方块颜色深浅表示本年度该月该星期几发布文章数量（0~4 篇）。</div>
        </div>

        <!-- 移动端雷达（本年度） -->
        <div class="archive-filter-card archive-radar-tab-mobile">
            <div class="archive-filter-title"><span class="material-icons" aria-hidden="true">radar</span> 雷达分析</div>
            <div class="tab-container" data-tab-container>
                <div class="tab-nav">
                    <button class="tab-btn active" data-tab="radarTabCategories">分类分布</button>
                    <button class="tab-btn" data-tab="radarTabTags">标签分布</button>
                </div>
                <div class="tab-panel active" id="radarTabCategories">
                    <div class="radar-chart-wrap"><canvas id="radarCategoriesChartMobile" aria-label="分类分布雷达图" role="img"></canvas><div class="radar-tooltip" id="radarCategoriesTooltipMobile" style="display:none;"></div></div>
                    <div class="radar-legend"><span class="radar-legend-dot" style="background:#6366f1;"></span><span>Top 5 分类（本年度）</span></div>
                    <div class="radar-note-static">仅展示本年度文章的分类分布 Top 5。</div>
                </div>
                <div class="tab-panel" id="radarTabTags">
                    <div class="radar-chart-wrap"><canvas id="radarTagsChartMobile" aria-label="标签分布雷达图" role="img"></canvas><div class="radar-tooltip" id="radarTagsTooltipMobile" style="display:none;"></div></div>
                    <div class="radar-legend"><span class="radar-legend-dot" style="background:#8b5cf6;"></span><span>Top 5 标签（本年度）</span></div>
                    <div class="radar-note-static">仅展示本年度文章的标签分布 Top 5。</div>
                </div>
            </div>
        </div>

        <!-- 桌面端双雷达（本年度） -->
        <div class="archive-side-flex">
            <div class="archive-filter-card archive-radar-card">
                <div class="archive-filter-title"><span class="material-icons" aria-hidden="true">radar</span> 分类分布</div>
                <div class="radar-chart-wrap"><canvas id="radarCategoriesChart" aria-label="分类分布雷达图" role="img"></canvas><div class="radar-tooltip" id="radarCategoriesTooltip" style="display:none;"></div></div>
                <div class="radar-legend"><span class="radar-legend-dot" style="background:#6366f1;"></span><span>Top 5 分类（本年度）</span></div>
                <div class="radar-note-static">仅展示本年度文章的分类分布 Top 5。</div>
            </div>
            <div class="archive-filter-card archive-radar-card">
                <div class="archive-filter-title"><span class="material-icons" aria-hidden="true">radar</span> 标签分布</div>
                <div class="radar-chart-wrap"><canvas id="radarTagsChart" aria-label="标签分布雷达图" role="img"></canvas><div class="radar-tooltip" id="radarTagsTooltip" style="display:none;"></div></div>
                <div class="radar-legend"><span class="radar-legend-dot" style="background:#8b5cf6;"></span><span>Top 5 标签（本年度）</span></div>
                <div class="radar-note-static">仅展示本年度文章的标签分布 Top 5。</div>
            </div>
        </div>
        <?php } ?>

        <!-- 文章列表（全量分页） -->
        <?php foreach ($rows as $row) {
            $date = new DateTime('@' . $row['created']);
            $date->setTimezone($timezone);
            $day = $date->format('d');
            $month = $date->format('m月');
            $year = $date->format('Y');
            $permalink = Typecho_Router::url('post', ['slug' => $row['slug'], 'cid' => $row['cid']], $options->index);
            $excerpt = Typecho_Common::subStr(strip_tags($row['text']), 0, 120, '...');
            $readingTime = max(1, ceil(mb_strlen(strip_tags($row['text'])) / 500));
            $metas = $db->fetchAll(
                $db->select()
                    ->from('table.relationships')
                    ->join('table.metas', 'table.relationships.mid = table.metas.mid')
                    ->where('table.relationships.cid = ?', $row['cid'])
            );
            $cats = array_filter($metas, fn($m) => $m['type'] === 'category');
            $tags = array_filter($metas, fn($m) => $m['type'] === 'tag');
        ?>
        <article class="archive-item">
            <div class="archive-item-date">
                <span class="date-day"><?= htmlspecialchars((string) $day) ?></span>
                <span class="date-month"><?= htmlspecialchars((string) $month) ?></span>
                <span class="date-year"><?= htmlspecialchars((string) $year) ?></span>
            </div>
            <div class="archive-item-body">
                <h2 class="archive-item-title"><a href="<?= htmlspecialchars((string) $permalink) ?>"><?= htmlspecialchars((string) $row['title']) ?></a></h2>
                <p class="archive-item-excerpt"><?= htmlspecialchars((string) $excerpt) ?></p>
                <div class="archive-item-meta">
                    <?php foreach ($cats as $c) { $catUrl = Typecho_Router::url('category', ['slug' => $c['slug']], $options->index); ?>
                        <a href="<?= htmlspecialchars((string) $catUrl) ?>" class="archive-item-tag">
                            <?php AstroPro::icon('category', (int) $c['mid']); ?>
                            <?= htmlspecialchars((string) $c['name']) ?>
                        </a>
                    <?php } ?>
                    <?php foreach ($tags as $t) { $tagUrl = Typecho_Router::url('tag', ['slug' => $t['slug']], $options->index); ?>
                        <a href="<?= htmlspecialchars((string) $tagUrl) ?>" class="archive-item-tag">
                            <span class="material-icons" aria-hidden="true">local_offer</span>
                            <?= htmlspecialchars((string) $t['name']) ?>
                        </a>
                    <?php } ?>
                    <span class="archive-item-reading-time"><span class="material-icons" aria-hidden="true">schedule</span> <?= (int) $readingTime ?> 分钟</span>
                </div>
            </div>
        </article>
        <?php } ?>

        <?php if ($totalCount === 0) { ?>
        <div class="archive-empty-state" aria-hidden="true"><span class="material-icons" aria-hidden="true">post_add</span><p>暂无可浏览的文章</p></div>
        <?php } ?>

        <!-- 分页（修复后） -->
        <?php if ($totalPages > 1) { ?>
        <div class="pagination" style="margin-top:28px;">
            <?php if ($currentPage > 1) { ?>
            <a href="<?= htmlspecialchars((string) pageUrl($baseUrl, $currentPage - 1)) ?>" class="page-btn" aria-label="上一页"><span class="material-icons">chevron_left</span></a>
            <?php } ?>
            <?php for ($i = 1; $i <= $totalPages; $i++) { ?>
            <a href="<?= htmlspecialchars((string) pageUrl($baseUrl, $i)) ?>" class="page-btn <?= $i === $currentPage ? 'active' : '' ?>"><?= (int) $i ?></a>
            <?php } ?>
            <?php if ($currentPage < $totalPages) { ?>
            <a href="<?= htmlspecialchars((string) pageUrl($baseUrl, $currentPage + 1)) ?>" class="page-btn" aria-label="下一页"><span class="material-icons">chevron_right</span></a>
            <?php } ?>
        </div>
        <?php } ?>
    </div>
</div>
</main>

<?php if ($currentPage === 1 && $totalCount > 0) { ?>
<script>
window.__archiveData = {
    categories: <?= $categoriesJson ?>,
    tags: <?= $tagsJson ?>,
    heatmap: <?= $heatmapJson ?>
};
</script>
<?php } ?>

<?php $this->need('footer.php'); ?>
