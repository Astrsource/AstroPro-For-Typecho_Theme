<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) { exit; }

/**
 * 站点导航栏模板
 * 
 * @var \Widget_Archive $this
 */

// 站点基础信息
$options   = $this->options;
$siteUrl   = rtrim((string) $options->siteUrl, '/');
$logoUrl   = (string) ($options->logoUrl ?? $siteUrl . '/logo.png');
$logoDarkUrl = (string) ($options->logoDarkUrl ?? $siteUrl . '/logo-dark.png');
$siteTitle = (string) $options->title;

// 当前路由状态
$isHome     = $this->is('index');
$isCategory = $this->is('category');
$isPage     = $this->is('page');

$currentCategorySlug = $isCategory ? ((string) $this->getArchiveSlug()) : '';
$currentPageSlug     = $isPage     ? ((string) $this->slug) : '';

// 获取分类并构建层级树
$allCategories = [];
$topCategories = [];

$categories = \Typecho\Widget::widget('Widget_Metas_Category_List');
while ($categories->next()) {
    $mid    = (int) $categories->mid;
    $parent = (int) $categories->parent;
    
    $allCategories[$mid] = [
        'mid'       => $mid,
        'name'      => (string) $categories->name,
        'slug'      => (string) $categories->slug,
        'permalink' => (string) $categories->permalink,
        'parent'    => $parent,
        'children'  => [],
    ];
}

foreach ($allCategories as $mid => $cat) {
    if ($cat['parent'] > 0 && isset($allCategories[$cat['parent']])) {
        $allCategories[$cat['parent']]['children'][] = &$allCategories[$mid];
    } else {
        $topCategories[] = &$allCategories[$mid];
    }
}

// 获取独立页面并构建层级树
$allPages = [];
$topPages = [];

$pages = \Typecho\Widget::widget('Widget_Contents_Page_List');
while ($pages->next()) {
    $cid    = (int) $pages->cid;
    $parent = (int) $pages->parent;
    
    $allPages[$cid] = [
        'cid'       => $cid,
        'title'     => (string) $pages->title,
        'slug'      => (string) $pages->slug,
        'permalink' => (string) $pages->permalink,
        'parent'    => $parent,
        'children'  => [],
    ];
}

foreach ($allPages as $cid => $page) {
    if ($page['parent'] > 0 && isset($allPages[$page['parent']])) {
        $allPages[$page['parent']]['children'][] = &$allPages[$cid];
    } else {
        $topPages[] = &$allPages[$cid];
    }
}

// 管理员信息
$userName  = AstroPro::getAdminInfo('screenName') ?: AstroPro::getAdminInfo('name');
$userEmail = AstroPro::getAdminInfo('mail');

// 头像
$userAvatar = getAuthorAvatar($this->author);

// active 类拼接辅助
function navActiveClass(bool $isActive): string {
    return $isActive ? 'active' : '';
}

// 辅助函数：渲染 PC 端分类导航项（递归子菜单）
function renderCategoryNavItem(array $cat, string $currentSlug): void {
    $hasChildren = !empty($cat['children']);
    $isActive    = ($currentSlug === $cat['slug']);
    $activeClass = navActiveClass($isActive);
    
    if ($hasChildren) {
        $triggerClass = 'menu-trigger' . ($isActive ? ' active' : '');
        echo '<li class="has-submenu">';
        printf(
            '<a href="%s" class="%s">',
            $cat['permalink'],
            $triggerClass
        );
        AstroPro::icon('category', $cat['mid']);
        AstroPro::esc($cat['name']);
        echo '<span class="material-icons" style="font-size:16px;">expand_more</span></a>';
        echo '<ul class="submenu">';
        foreach ($cat['children'] as $child) {
            renderCategorySubItem($child, $currentSlug);
        }
        echo '</ul>';
        echo '</li>';
    } else {
        printf(
            '<li><a href="%s" class="%s">',
            $cat['permalink'],
            $activeClass
        );
        AstroPro::icon('category', $cat['mid']);
        AstroPro::esc($cat['name']);
        echo '</a></li>';
    }
}

function renderCategorySubItem(array $cat, string $currentSlug): void {
    $hasChildren = !empty($cat['children']);
    $isActive    = ($currentSlug === $cat['slug']);
    $activeClass = navActiveClass($isActive);
    
    if ($hasChildren) {
        $triggerClass = 'menu-trigger' . ($isActive ? ' active' : '');
        echo '<li class="has-submenu">';
        printf(
            '<a href="%s" class="%s">',
            $cat['permalink'],
            $triggerClass
        );
        AstroPro::icon('category', $cat['mid']);
        AstroPro::esc($cat['name']);
        echo '<span class="material-icons" style="font-size:16px;">expand_more</span></a>';
        echo '<ul class="submenu submenu-level">';
        foreach ($cat['children'] as $child) {
            renderCategorySubItem($child, $currentSlug);
        }
        echo '</ul>';
        echo '</li>';
    } else {
        printf(
            '<li><a href="%s" class="%s">',
            $cat['permalink'],
            $activeClass
        );
        AstroPro::icon('category', $cat['mid']);
        AstroPro::esc($cat['name']);
        echo '</a></li>';
    }
}

// ── 辅助函数：渲染 PC 端页面导航项（递归子菜单） ──
function renderPageNavItem(array $page, string $currentSlug): void {
    $hasChildren = !empty($page['children']);
    $isActive    = ($currentSlug === $page['slug']);
    $activeClass = navActiveClass($isActive);
    
    if ($hasChildren) {
        $triggerClass = 'menu-trigger' . ($isActive ? ' active' : '');
        echo '<li class="has-submenu">';
        printf(
            '<a href="%s" class="%s">',
            $page['permalink'],
            $triggerClass
        );
        AstroPro::icon('page', $page['cid']);
        AstroPro::esc($page['title']);
        echo '<span class="material-icons" style="font-size:16px;">expand_more</span></a>';
        echo '<ul class="submenu">';
        foreach ($page['children'] as $child) {
            renderPageSubItem($child, $currentSlug);
        }
        echo '</ul>';
        echo '</li>';
    } else {
        printf(
            '<li><a href="%s" class="%s">',
            $page['permalink'],
            $activeClass
        );
        AstroPro::icon('page', $page['cid']);
        AstroPro::esc($page['title']);
        echo '</a></li>';
    }
}

function renderPageSubItem(array $page, string $currentSlug): void {
    $hasChildren = !empty($page['children']);
    $isActive    = ($currentSlug === $page['slug']);
    $activeClass = navActiveClass($isActive);
    
    if ($hasChildren) {
        $triggerClass = 'menu-trigger' . ($isActive ? ' active' : '');
        echo '<li class="has-submenu">';
        printf(
            '<a href="%s" class="%s">',
            $page['permalink'],
            $triggerClass
        );
        AstroPro::icon('page', $page['cid']);
        AstroPro::esc($page['title']);
        echo '<span class="material-icons" style="font-size:16px;">expand_more</span></a>';
        echo '<ul class="submenu submenu-level">';
        foreach ($page['children'] as $child) {
            renderPageSubItem($child, $currentSlug);
        }
        echo '</ul>';
        echo '</li>';
    } else {
        printf(
            '<li><a href="%s" class="%s">',
            $page['permalink'],
            $activeClass
        );
        AstroPro::icon('page', $page['cid']);
        AstroPro::esc($page['title']);
        echo '</a></li>';
    }
}

// ── 辅助函数：渲染移动端分类（递归） ──
function renderMobileCategory(array $cat, string $currentSlug): void {
    $hasChildren = !empty($cat['children']);
    $isActive    = ($currentSlug === $cat['slug']);
    $activeClass = navActiveClass($isActive);
    
    if ($hasChildren) {
        $itemClass = 'mobile-nav-item' . ($isActive ? ' active' : '');
        printf(
            '<a href="%s" class="%s" data-has-submenu="">',
            $cat['permalink'],
            $itemClass
        );
        AstroPro::icon('category', $cat['mid']);
        AstroPro::esc($cat['name']);
        echo ' <span class="material-icons nav-arrow">expand_more</span></a>';
        echo '<div class="mobile-submenu">';
        foreach ($cat['children'] as $child) {
            renderMobileCategory($child, $currentSlug);
        }
        echo '</div>';
    } else {
        printf(
            '<a class="mobile-nav-item %s" href="%s">',
            $activeClass,
            $cat['permalink']
        );
        AstroPro::icon('category', $cat['mid']);
        echo ($cat['name'] ? ' ' : '');
        AstroPro::esc($cat['name']);
        echo '</a>';
    }
}

// ── 辅助函数：渲染移动端页面（递归） ──
function renderMobilePage(array $page, string $currentSlug): void {
    $hasChildren = !empty($page['children']);
    $isActive    = ($currentSlug === $page['slug']);
    $activeClass = navActiveClass($isActive);
    
    if ($hasChildren) {
        $itemClass = 'mobile-nav-item' . ($isActive ? ' active' : '');
        printf(
            '<a href="%s" class="%s" data-has-submenu="">',
            $page['permalink'],
            $itemClass
        );
        AstroPro::icon('page', $page['cid']);
        AstroPro::esc($page['title']);
        echo ' <span class="material-icons nav-arrow">expand_more</span></a>';
        echo '<div class="mobile-submenu">';
        foreach ($page['children'] as $child) {
            renderMobilePage($child, $currentSlug);
        }
        echo '</div>';
    } else {
        printf(
            '<a class="mobile-nav-item %s" href="%s">',
            $activeClass,
            $page['permalink']
        );
        AstroPro::icon('page', $page['cid']);
        echo ($page['title'] ? ' ' : '');
        AstroPro::esc($page['title']);
        echo '</a>';
    }
}
?>

<nav class="site-navigation navbar" role="navigation" aria-label="主导航">
    <div class="navbar-inner">
        <!-- 左侧：Logo -->
        <div class="nav-left">
            <a class="nav-brand" href="<?= $siteUrl ?>/">
                <img alt="logo" src="<?= $logoUrl ?>" class="logo-light">
                <img alt="logo" src="<?= $logoDarkUrl ?>" class="logo-dark">
               <?php if ($options->logoTitle) { ?>
                    <?php AstroPro::esc($siteTitle); ?>
                <?php } ?>
            </a>
        </div>

        <!-- 中间：导航菜单-->
        <div class="nav-center">
            <ul class="nav-menu">
                <!-- 1. 首页 -->
                <li>
                    <a class="<?= navActiveClass($isHome) ?>" href="<?= $siteUrl ?>/">
                        <span class="material-icons" aria-hidden="true">home</span>首页
                    </a>
                </li>

                <!-- 2. 全部分类（平铺，有子分类的显示子菜单） -->
                <?php foreach ($topCategories as $cat) { ?>
                    <?php renderCategoryNavItem($cat, $currentCategorySlug); ?>
                <?php } ?>

                <!-- 3. 全部页面（平铺，有子页面的显示子菜单） -->
                <?php foreach ($topPages as $page) { ?>
                    <?php renderPageNavItem($page, $currentPageSlug); ?>
                <?php } ?>
            </ul>
        </div>

        <!-- 右侧：功能区 -->
        <div class="nav-right">
            <button aria-label="回到顶部" class="back-to-top" id="backToTopBtn" type="button">
                <span class="material-icons" aria-hidden="true">arrow_upward</span>
            </button>
            <button aria-label="切换主题" class="theme-toggle-single" id="themeToggle" type="button">
                <span class="material-icons" aria-hidden="true">light_mode</span>
            </button>
            <button aria-label="搜索" class="search-trigger-btn" id="searchTriggerBtn" type="button">
                <span class="material-icons" aria-hidden="true">search</span>
            </button>
            <button aria-label="菜单" class="menu-toggle" id="menuToggle" type="button">
                <span class="material-icons" aria-hidden="true">menu</span>
            </button>
        </div>
    </div>
</nav>

<!-- ==================== 移动端菜单 ==================== -->
<div class="mobile-menu-overlay" id="mobileMenuOverlay"></div>
<div class="mobile-menu" id="mobileMenu" role="dialog" aria-modal="true" aria-label="移动端导航菜单">
    <div class="mobile-menu-header">
        <img alt="作者头像" class="mobile-menu-avatar" loading="lazy" src="<?php AstroPro::esc($userAvatar); ?>"/>
        <div class="mobile-menu-user">
            <span class="mobile-menu-username"><?php AstroPro::esc($userName); ?></span>
            <span class="mobile-menu-email"><?php AstroPro::esc($userEmail); ?></span>
        </div>
        <button class="mobile-menu-close" id="mobileMenuClose" type="button">
            <span class="material-icons" aria-hidden="true">close</span>
        </button>
    </div>

    <div class="mobile-nav-wrapper">
        <!-- 主导航 -->
        <div class="mobile-nav-group">
            <span class="mobile-nav-label">主导航</span>
            <a class="mobile-nav-item <?= navActiveClass($isHome) ?>" href="<?= $siteUrl ?>/">
                <span class="material-icons" aria-hidden="true">home</span> 首页
            </a>
        </div>

        <!-- 全部分类 -->
        <div class="mobile-nav-group">
            <span class="mobile-nav-label">分类</span>
            <?php foreach ($topCategories as $cat) { ?>
                <?php renderMobileCategory($cat, $currentCategorySlug); ?>
            <?php } ?>
        </div>

        <!-- 全部页面 -->
        <div class="mobile-nav-group">
            <span class="mobile-nav-label">页面</span>
            <?php foreach ($topPages as $page) { ?>
                <?php renderMobilePage($page, $currentPageSlug); ?>
            <?php } ?>
        </div>
    </div>

    <div class="mobile-menu-footer">
        <a href="#" id="mobileSearchTrigger">
            <span class="material-icons" aria-hidden="true">search</span> 搜索
        </a>
        <a href="<?= (string) ($options->feedUrl ?? $siteUrl . '/feed/') ?>">
            <span class="material-icons" aria-hidden="true">rss_feed</span> RSS
        </a>
        <a href="#" id="mobileThemeToggle">
            <span class="material-icons" aria-hidden="true">dark_mode</span> 主题
        </a>
    </div>
</div>