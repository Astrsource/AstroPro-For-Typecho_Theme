<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) { exit; }
$this->need('header.php');

http_response_code(404);
?>

<!-- ==================== 404 页面内容 ==================== -->
<main id="main-content" class="er-wrapper">
<!-- 背景动态光点装饰 -->
<div aria-hidden="true" class="er-bg-decor">
<div class="er-orb orb-1"></div>
<div class="er-orb orb-2"></div>
<div class="er-orb orb-3"></div>
<div class="er-orb orb-4"></div>
</div>
<!-- 中央容器 -->
<div class="er-content">
<!-- 大数字 404 -->
<div class="er-number-block">
<span class="er-num-layer layer-back">404</span>
<span class="er-num-layer layer-mid">404</span>
<span class="er-num-layer layer-front">404</span>
</div>
<!-- 标题 -->
<h1 class="er-title">页面不见了</h1>
<!-- 描述 -->
<p class="er-desc">你寻找的页面可能已被移动、删除，<br/>或者你输入了一个错误的地址。</p>
<!-- 操作按钮 -->
<div class="er-actions">
<a class="er-btn er-btn-primary" href="<?= $this->options->siteUrl(); ?>"><span class="material-icons" aria-hidden="true">home</span> 返回首页</a>
<button class="er-btn er-btn-ghost" onclick="document.querySelector('.search-overlay')?.classList.add('active')"><span class="material-icons" aria-hidden="true">search</span> 搜索文章</button>
</div>
</div>
</main>
<?php $this->need('footer.php'); ?>