<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) { exit; }

/**
 * 搜索弹窗模板
 * 
 * @var \Widget_Archive $this 
 */

// ── 使用 Widget 获取随机推荐阅读 ──
$randomPosts = Typecho_Widget::widget('Widget_Random_Posts');
$recommendedPosts = [];
while ($randomPosts->next()) {
    $recommendedPosts[] = [
        'title' => (string) $randomPosts->title,
        'url'   => (string) $randomPosts->permalink,
    ];
}
?>

<div class="search-overlay" id="searchOverlay" aria-hidden="true">
  <div class="search-backdrop"></div>
  <div class="search-dialog" role="dialog" aria-modal="true" aria-label="搜索">
    <div class="search-glow-bar"></div>
    <div class="search-header">
      <span class="search-header-icon material-icons">search</span>
      <span class="search-header-title">搜索文章</span>
      <button class="search-close-btn" id="searchCloseBtn" aria-label="关闭搜索">
        <span class="material-icons">close</span>
      </button>
    </div>
    <div class="search-input-row">
      <div class="search-input-wrap">
        <span class="material-icons search-input-icon">search</span>
        <input aria-label="搜索关键词" autocomplete="off" class="search-input" id="searchInput" placeholder="输入关键词搜索…" type="text"/>
        <button aria-label="清空" class="search-clear-btn" id="searchClearBtn" style="display:none" type="button">
          <span class="material-icons" aria-hidden="true">cancel</span>
        </button>
      </div>
      <button aria-label="前往搜索" class="search-submit-btn" id="searchSubmitBtn" type="button">
        <span class="material-icons" aria-hidden="true">arrow_forward</span>
      </button>
    </div>

    <div class="search-default-view" id="searchDefaultView">
      <div class="search-section">
        <span class="search-section-label">热门搜索</span>
        <?php HotSearch::render(5, [
            'wrapper' => '<div class="search-hot-tags">{items}</div>',
            'item'    => '<span class="search-hot-tag" role="button" tabindex="0" data-keyword="{keyword}">{keyword}</span>',
            // empty 也包含 .search-hot-tags 容器，让前端的 refreshHotSearch() 始终能找到替换目标
            // 否则首次搜索后容器结构变了，JS 找不到节点就不会刷新
            'empty'   => '<div class="search-hot-tags"><span style="color:var(--text-muted);font-size:0.75rem;">暂无热门搜索</span></div>',
        ]); ?>
      </div>

      <div class="search-section">
        <span class="search-section-label">推荐阅读</span>
        <div class="search-suggestions">
          <?php if (!empty($recommendedPosts)) { ?>
            <?php foreach ($recommendedPosts as $post) { ?>
              <a href="<?= $post['url'] ?>" class="search-suggest-item">
                <span class="material-icons">article</span>
                <span><?php AstroPro::esc($post['title']); ?></span>
              </a>
            <?php } ?>
          <?php } else { ?>
            <span style="color:var(--text-muted);font-size:0.75rem;">暂无文章</span>
          <?php } ?>
        </div>
      </div>

      <div class="search-section">
        <span class="search-section-label">最近搜索</span>
        <div class="search-history-static" id="searchHistoryContainer"></div>
      </div>
    </div>

    <div class="search-results-view" id="searchResultsView" style="display:none">
      <div class="search-results-list" id="searchResultsList"></div>
      <div class="search-no-results" id="searchNoResults" style="display:none">
        <span class="material-icons">search_off</span>
        <p>未找到相关文章</p>
        <span>试试其他关键词吧</span>
      </div>
    </div>

    <div class="search-footer-hint">
      <kbd>Esc</kbd> 关闭 · <kbd>↑</kbd><kbd>↓</kbd> 导航 · <kbd>Enter</kbd> 打开
    </div>
  </div>
</div>