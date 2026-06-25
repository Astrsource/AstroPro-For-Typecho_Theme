<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$musicList = getThemeMusicList(true);
$musicRandomAutoplay = Typecho\Widget::widget('Widget_Options')->musicRandomAutoplay === '1';
?>
</div><!-- .{name}-container 主容器结束  -->
</div><!-- #pjax-container 主容器结束 -->
<!-- ==================== 页脚 ==================== -->
<footer class="footer" role="contentinfo">
<div class="footer-inner">
<div class="footer-top">
<div class="footer-left">
<?php $this->options->title(); ?><span class="footer-copyright">© <?= date('Y'); ?></span>
</div>
<nav class="footer-right">
<?php 
$footerRightLinks = AstroPro::parseFooterLinks('footerRightLinks');
if (!empty($footerRightLinks)) {
    foreach ($footerRightLinks as $link) : 
?>
<a href="<?php AstroPro::esc($link['url']) ?>" target="_blank" rel="noopener noreferrer">
    <?php if (!empty($link['icon'])) { ?>
    <span class="material-icons" aria-hidden="true"><?php AstroPro::esc($link['icon']) ?></span>
    <?php } ?>
    <?php AstroPro::esc($link['title']); ?>
</a>
<?php 
    endforeach;
}?>
</nav>
</div>
<?php
$footerLinks = AstroPro::parseFooterLinks();
if (!empty($footerLinks)) { ?>
<div class="footer-sponsors">
    <?php foreach ($footerLinks as $link) : ?>
    <a href="<?= $link['url'] ?>" class="sponsor-item" target="_blank" rel="noopener noreferrer">
        <img src="<?= $link['img'] ?>" alt="<?php AstroPro::esc($link['title']) ?>" class="sponsor-avatar">
        <span><?php AstroPro::esc($link['title']) ?></span>
    </a>
    <?php endforeach; ?>
</div>
<?php } ?>
</div>
</footer>
<?php if (!empty($musicList)) { ?>
<!-- ==================== 音乐播放器 ==================== -->
<script>
window._glassblogMusicConfig = {
    randomAutoplay: <?php echo (Typecho\Widget::widget('Widget_Options')->musicRandomAutoplay === '1') ? 'true' : 'false'; ?>
};
window._glassblogMusicPlaylist = <?php 
echo json_encode(array_map(function($item) {
    return [
        'title' => $item['name'] ?? '未知歌曲',
        'artist' => $item['artist'] ?? '未知歌手',
        'fallbackDuration' => '0:00',
        'img' => $item['pic'] ?? '',
        'src' => $item['url'] ?? '',
        'autoplay' => !empty($item['autoplay']),
        'source' => $item['source'] ?? '',
        'rawId'  => $item['raw_id'] ?? ''
    ];
}, $musicList), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); 
?>;
</script>
<div class="music-player" id="musicPlayer" role="complementary" aria-label="音乐播放器">
<!-- 折叠状态：迷你波形条 -->
<div class="music-player-collapsed" id="musicPlayerCollapsed">
<button aria-label="展开音乐播放器" class="music-toggle-btn" id="musicToggleBtn" type="button">
<span class="material-icons" aria-hidden="true">music_note</span>
</button>
<div class="music-wave-mini">
<span class="wave-bar"></span>
<span class="wave-bar"></span>
<span class="wave-bar"></span>
<span class="wave-bar"></span>
</div>
</div>
<!-- 展开状态：完整播放器 -->
<div class="music-player-expanded" id="musicPlayerExpanded">
<!-- 顶部拖拽条/折叠按钮 -->
<div class="music-player-header">
<div class="music-drag-handle" id="musicDragHandle">
<span class="drag-line"></span>
</div>
<button aria-label="收起播放器" class="music-close-btn" id="musicCloseBtn" type="button">
<span class="material-icons" aria-hidden="true">expand_more</span>
</button>
</div>
<!-- 主内容区 -->
<div class="music-player-body">
<!-- 左侧：专辑封面 -->
<div class="music-album-section">
<div class="music-album-art">
<img alt="专辑封面" id="musicAlbumArt" loading="lazy" src="/"/>
<div class="music-album-glow"></div>
<div class="music-play-overlay">
<button class="music-play-btn" id="musicPlayBtn" type="button">
<span class="material-icons" id="playIcon">play_arrow</span>
</button>
</div>
</div>
<!-- 旋转光碟效果 -->
<div class="music-disc-ring" id="musicDiscRing">
<svg class="disc-svg" viewbox="0 0 100 100">
<circle cx="50" cy="50" fill="none" opacity="0.3" r="48" stroke="var(--accent)" stroke-width="0.5"></circle>
<circle cx="50" cy="50" fill="none" opacity="0.2" r="35" stroke="var(--accent)" stroke-width="0.3"></circle>
<circle cx="50" cy="50" fill="none" opacity="0.15" r="20" stroke="var(--accent)" stroke-width="0.3"></circle>
</svg>
</div>
</div>
<!-- 中间：歌曲信息 + 控制 -->
<div class="music-info-section">
<div class="music-track-info">
<h4 class="music-track-title" id="musicTrackTitle">Glass Dreams</h4>
<p class="music-track-artist" id="musicTrackArtist">Ambient Echoes</p>
</div>
<!-- 可视化波形 -->
<div class="music-visualizer" id="musicVisualizer">
<div class="music-wave-bars" id="musicWaveBars">
<span class="wave-bar"></span>
<span class="wave-bar"></span>
<span class="wave-bar"></span>
<span class="wave-bar"></span>
<span class="wave-bar"></span>
<span class="wave-bar"></span>
<span class="wave-bar"></span>
<span class="wave-bar"></span>
<span class="wave-bar"></span>
<span class="wave-bar"></span>
<span class="wave-bar"></span>
<span class="wave-bar"></span>
<span class="wave-bar"></span>
<span class="wave-bar"></span>
<span class="wave-bar"></span>
<span class="wave-bar"></span>
<span class="wave-bar"></span>
<span class="wave-bar"></span>
<span class="wave-bar"></span>
<span class="wave-bar"></span>
</div>
</div>
<!-- 进度条 -->
<div class="music-progress-area">
<span class="music-time" id="musicCurrentTime">0:00</span>
<div class="music-progress-bar" id="musicProgressBar">
<div class="music-progress-track">
<div class="music-progress-fill" id="musicProgressFill" style="width: 0%"></div>
<div class="music-progress-thumb" id="musicProgressThumb"></div>
</div>
</div>
<span class="music-time" id="musicDuration">0:00</span>
</div>
<!-- 控制按钮 -->
<div class="music-controls">
<button class="music-ctrl-btn" data-tooltip="随机播放" id="musicShuffleBtn" type="button">
<span class="material-icons" aria-hidden="true">shuffle</span>
</button>
<button class="music-ctrl-btn" id="musicPrevBtn" type="button">
<span class="material-icons" aria-hidden="true">skip_previous</span>
</button>
<button class="music-ctrl-btn music-ctrl-main" id="musicMainPlayBtn" type="button">
<span class="material-icons" id="mainPlayIcon">play_arrow</span>
</button>
<button class="music-ctrl-btn" id="musicNextBtn" type="button">
<span class="material-icons" aria-hidden="true">skip_next</span>
</button>
<button class="music-ctrl-btn" data-tooltip="循环播放" id="musicRepeatBtn" type="button">
<span class="material-icons" aria-hidden="true">repeat</span>
</button>
</div>
</div>
<!-- 右侧：播放列表 -->
<div class="music-playlist-section">
<div class="music-playlist-header">
<span class="material-icons" aria-hidden="true">queue_music</span>
<span>播放列表</span>
<span class="music-playlist-count" id="playlistCount">0</span>
</div>
<div class="music-playlist-scroll">
<ul class="music-playlist" id="musicPlaylist"></ul>
</div>
</div>
</div>
<!-- 底部音量 -->
<div class="music-player-footer">
<div class="music-volume-area">
<button class="music-vol-btn" id="musicVolBtn" type="button">
<span class="material-icons" id="volIcon">volume_up</span>
</button>
<div class="music-volume-bar" id="musicVolumeBar">
<div class="music-volume-track">
<div class="music-volume-fill" id="musicVolumeFill"></div>
<div class="music-volume-thumb" id="musicVolumeThumb"></div>
</div>
</div>
</div>
<div class="music-extra-actions">
<button class="music-ctrl-btn music-ctrl-sm" data-tooltip="歌词" onclick="showSnackbar('没有写此功能', 'info')" type="button">
<span class="material-icons" aria-hidden="true">lyrics</span>
</button>
<button class="music-ctrl-btn music-ctrl-sm" data-tooltip="下载" id="musicDownloadBtn" type="button">
<span class="material-icons" aria-hidden="true">download</span>
</button>
<button class="music-ctrl-btn music-ctrl-sm" data-tooltip="访问随机播放" id="musicRandomAutoplayBtn" type="button">
<span class="material-icons" id="randomAutoplayIcon">motion_photos_off</span>
</button>
</div>
</div>
</div>
</div>
<?php } ?>
<!-- Snackbar 容器 -->
<div class="snackbar-container" id="snackbarContainer"></div>
<!-- ==================== JavaScript 资源加载 ==================== -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-pjax@2.0.1/jquery.pjax.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@6.1.14/dist/fancybox/fancybox.umd.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@highlightjs/cdn-assets@11.11.1/highlight.min.js"></script>
<script src="<?php $this->options->themeUrl('assets/script.js'); ?>?v=<?= filemtime(__DIR__ . '/assets/script.js') ?>"></script>
<script>
(function() {
    if (typeof $.fn.pjax === 'undefined') return;
    var siteUrl = '<?php echo rtrim((string)($this->options->siteUrl ?? ''), '/'); ?>';
    window.__siteUrl = siteUrl;
    // 搜索路由的 URL 模板：自动适配是否开启伪静态
    //  - 开启伪静态：<siteUrl>/search/<keyword>/（无 index.php）
    //  - 未开启伪静态：<siteUrl>/index.php/search/<keyword>/
    // 这样在两种部署下都能正确生成搜索 URL，避免多出 /index.php 前缀
    // options->index 内部已经处理好（var/Widget/Options.php ___index）
    window.__searchUrlTpl = '<?php echo rtrim((string)($this->options->index ?? ''), '/'); ?>' + '/search/{keyword}/';
    var pjaxSelector = 'a[href^="' + siteUrl + '"]:not([target="_blank"]):not([href*="#"]):not([data-no-pjax]):not([href*="admin"]):not([href*="login"]):not([href*="action=search"]):not([href*="action=record"]), ' +
                       'a[href^="/"]:not([href^="//"]):not([target="_blank"]):not([href*="#"]):not([data-no-pjax]):not([href*="/admin"]):not([href*="/login"]):not([href*="action=search"]):not([href*="action=record"])';
    $(document).pjax(pjaxSelector, '#pjax-container', {
        fragment: '#pjax-container',
        timeout: 10000,
        scrollTo: 0,
        maxCacheLength: 20
    });

    // ---------- 发送 ----------
    $(document).on('pjax:send', function() {
        var searchOverlay = document.getElementById('searchOverlay');
        if (searchOverlay && searchOverlay.classList.contains('active')) {
            searchOverlay.classList.remove('active');
            searchOverlay.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            var searchInput = document.getElementById('searchInput');
            var searchDefaultView = document.getElementById('searchDefaultView');
            var searchResultsView = document.getElementById('searchResultsView');
            var searchClearBtn = document.getElementById('searchClearBtn');
            if (searchInput) searchInput.value = '';
            if (searchDefaultView) searchDefaultView.style.display = 'block';
            if (searchResultsView) searchResultsView.style.display = 'none';
            if (searchClearBtn) searchClearBtn.style.display = 'none';
        }

        $('body').addClass('pjax-loading');
        if (!$('#pjaxProgressBar').length) {
            $('body').append('<div id="pjaxProgressBar" style="position:fixed;top:0;left:0;height:3px;background:var(--accent);z-index:9999;width:0%;transition:width 0.3s ease;"></div>');
        }
        setTimeout(function() { $('#pjaxProgressBar').css('width', '60%'); }, 50);
    });

    // ---------- 完成 ----------
    $(document).on('pjax:complete', function() {
        $('#pjaxProgressBar').css('width', '100%');
        setTimeout(function() { $('#pjaxProgressBar').remove(); $('body').removeClass('pjax-loading'); }, 350);
    });

    // ---------- 错误 ----------
    $(document).on('pjax:error', function(event, xhr, textStatus) {
        $('#pjaxProgressBar').remove();
        $('body').removeClass('pjax-loading');
        if (textStatus !== 'abort') {
            if (typeof showSnackbar === 'function') showSnackbar('页面加载失败，正在刷新...', 'error');
            setTimeout(function() { window.location.href = event.relatedTarget.href; }, 800);
        }
    });

    // ---------- 结束：JS 回调 + 导航高亮 ----------
    $(document).on('pjax:end', function() {
        if (typeof window.reinitAfterPjax === 'function') window.reinitAfterPjax();
        
        var currentPath = location.pathname + location.search;
        $('.navbar a, .mobile-nav-item, .mobile-submenu a').each(function() {
            var $a = $(this);
            var href = $a.attr('href');
            if (!href) return;
            var linkPath = href.replace(/^https?:\/\/[^\/]+/, '');
            $a.removeClass('active current');
            var $parent = $a.closest('.nav-item, .has-submenu, .mobile-nav-item');
            $parent.removeClass('active');
            if (linkPath === currentPath || (currentPath.indexOf(linkPath) === 0 && linkPath !== '/')) {
                $a.addClass('active');
                $parent.addClass('active');
            }
        });
    });
})();
</script>
<?php $this->footer(); ?>
</body>
</html>