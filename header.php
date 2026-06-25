<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$meta_image = ThumbnailHelper::showThumbnail($this, true);
$favicon    = !empty($this->options->favicon) ? $this->options->favicon : $this->options->themeUrl.'/assets/img/favicon.png';

// 初始化 SEO 助手
$seo = new SeoHelper($this);
?>
<!DOCTYPE HTML>
<html data-theme="light" lang="zh-CN">
<head>
    <!-- ==================== 基本信息 ==================== -->
    <meta charset="<?= $this->options->charset(); ?>">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="renderer" content="webkit">
    <meta name="applicable-device" content="pc,mobile">

    <!-- ==================== SEO 核心 ==================== -->
    <?php $seo->robots(); ?>

    <!-- ==================== 关键词 / 描述 / 作者 ==================== -->
    <meta name="keywords" content="<?php $seo->keywords(); ?>">
    <meta name="description" content="<?php $seo->description(); ?>">
    <meta name="author" content="<?php $this->author(); ?>">
    <meta name="copyright" content="© <?php echo date('Y'); ?> <?php echo $this->author(); ?>">
    <link rel="author" href="<?php $this->author->permalink(); ?>">
    <link rel="publisher" href="<?php $this->author->permalink(); ?>">
    <link rel="me" href="<?php $this->author->permalink(); ?>">

    <!-- ==================== 文章页专属 ==================== -->
    <?php echo $seo->articleMeta(); ?>

    <!-- ==================== Open Graph ==================== -->
    <?php $seo->og($meta_image, $favicon); ?>

    <!-- ==================== Twitter Card ==================== -->
    <?php $seo->twitter($meta_image, $favicon); ?>
    
    <!-- ==================== RSS XML ==================== -->
    <link rel="alternate" type="application/rss+xml" title="<?php $this->options->title(); ?> » RSS 2.0" href="<?php $this->options->rootUrl(); ?>/feed/">
    <link rel="alternate" type="application/rdf+xml" title="<?php $this->options->title(); ?> » RSS 1.0" href="<?php $this->options->rootUrl(); ?>/feed/rss/">
    <link rel="alternate" type="application/atom+xml" title="<?php $this->options->title(); ?> » ATOM 1.0" href="<?php $this->options->rootUrl(); ?>/feed/atom/">

    <!-- ==================== Favicon ==================== -->
    <link rel="icon" sizes="32x32" href="<?php $this->options->themeUrl('assets/img/favicon.png'); ?>" type="image/png">
    <link rel="icon" sizes="16x16" href="<?php $this->options->themeUrl('assets/img/16-favicon.png'); ?>" type="image/png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php $this->options->themeUrl('assets/img/180-favicon.png'); ?>" type="image/png">
    <link rel="shortcut icon" href="<?php $this->options->themeUrl('assets/img/favicon.png'); ?>" type="image/png">

    <!-- ==================== 标题 ==================== -->
    <?php $seo->title(); ?>

    <!-- ==================== 主题切换：防止闪烁 ==================== -->
    <script>
        (function(){
            var t = localStorage.getItem('theme');
            if (t === 'dark' || t === 'light') {
                document.documentElement.setAttribute('data-theme', t);
                return;
            }
            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>

    <!-- ==================== 静态资源 ==================== -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@6.1.14/dist/fancybox/fancybox.css" rel="stylesheet">
    <link href="<?php $this->options->themeUrl('assets/highlight.css'); ?>" rel="stylesheet">
    <link href="<?php $this->options->themeUrl('assets/style.css'); ?>" rel="stylesheet">
    <style>
    <?php if ($this->options->backgroundUrl) { ?>
    body::before {
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: -1;
        content: "";
        position: fixed;
        background: url(<?= $this->options->backgroundUrl; ?>) center / cover no-repeat;
        transition: filter 0.5s;
        opacity: 0.5;
    }
    <?php } ?>
    <?php if ($this->options->bgFilter) { ?>
    body::after {
        content: "";
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 0;
        pointer-events: none;
        backdrop-filter: blur(var(--bg-blur-amount));
        -webkit-backdrop-filter: blur(var(--bg-blur-amount));
        transition: backdrop-filter 0.4s, -webkit-backdrop-filter 0.4s, background 0.4s;
    }
    <?php } ?>
    </style>
    <!-- ==================== head 函数 ==================== -->
    <?php $this->head(); ?>
</head>
<body>
<!-- ==================== 无障碍：跳转到主要内容 ==================== -->
<a href="#main-content" class="skip-to-content">跳转到主要内容</a>
<!-- ==================== 导航菜单 ==================== -->
<?php $this->need('includes/navmenu‌.php'); ?>
<!-- ==================== 弹出式搜索框 ==================== -->
<?php $this->need('includes/popupsearch.php'); ?>
<!-- ==================== Pjax 容器 ==================== -->
<div id="pjax-container">
