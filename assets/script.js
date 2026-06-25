$(function () {
    // ==================== 共享变量 ====================
    const $html = $('html');
    const $backToTopBtn = $('#backToTopBtn');
    const $navbar = $('.navbar');

    // ==================== 全局 pjax 触发器 ====================
    // 三个搜索框（popup / archives form / search.php 内联框）都通过这个 ghost a 触发 pjax：
    //  - ghost 自己绑了 click 监听：优先用 $.pjax() 直接调用（不依赖 footer 选区时序）
    //  - pjax 失败时 fallback 到 native 跳转（与 jQuery-pjax 内部 hard-reload 行为一致）
    //  - 这样不论 footer 选区是否已就绪、jQuery-pjax 是否加载完，都能稳定工作
    // 必须最早挂上（这里），保证所有搜索 IIFE 都能复用同一个 ghost
    (function ensurePjaxTrigger() {
        if (document.getElementById('__pjax_search_trigger')) return;
        var ghost = document.createElement('a');
        ghost.id = '__pjax_search_trigger';
        ghost.style.display = 'none';
        ghost.setAttribute('aria-hidden', 'true');
        ghost.setAttribute('tabindex', '-1');
        ghost.href = '/';
        document.body.appendChild(ghost);

        ghost.addEventListener('click', function(ev) {
            var url = ghost.getAttribute('href') || ghost.href;
            if (!url || url === '/' || url === window.location.href) return; // 没设置就放行 native
            // 不阻止 native 跳转：pjax 内部失败时会自己 hard-reload
            // 只确保 pjax 主动接管；不阻止 native 是为了保留 jQuery-pjax 的硬兜底
            if (typeof $ !== 'undefined' && $.fn.pjax) {
                ev.preventDefault();  // 阻止 native，让 pjax 接管
                $.pjax({
                    url: url,
                    container: '#pjax-container',
                    fragment: '#pjax-container',
                    timeout: 10000,
                    scrollTo: 0
                });
            }
            // 否则 fall through，让 a 标签自己跳（整页跳，兜底）
        }, true); // capture 阶段，优先于 footer 选区
    })();

    // ==================== 1. 主题切换（一次性） ====================
    let themeIcons = [];

    function collectThemeIcons() {
        themeIcons = $('#themeToggle .material-icons, #mobileThemeToggle .material-icons').toArray();
    }

    function getInitialTheme() {
        const stored = localStorage.getItem('theme');
        if (stored === 'dark' || stored === 'light') return stored;
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) return 'dark';
        return 'light';
    }

    function updateAllThemeIcons(theme) {
        const iconName = theme === 'dark' ? 'dark_mode' : 'light_mode';
        $(themeIcons).text(iconName);
    }

    function applyTheme(theme, save) {
        $html.attr('data-theme', theme);
        updateAllThemeIcons(theme);
        if (save) {
            localStorage.setItem('theme', theme);
        }
    }

    function toggleTheme() {
        const current = $html.attr('data-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        applyTheme(next, true);
    }

    collectThemeIcons();

    const initialTheme = getInitialTheme();
    const hasUserPreference = !!localStorage.getItem('theme');
    applyTheme(initialTheme, hasUserPreference);

    $('#themeToggle').on('click', toggleTheme);
    $('#mobileThemeToggle').on('click', function (e) {
        e.preventDefault();
        toggleTheme();
    });

    if (window.matchMedia) {
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

        function handleSystemThemeChange(e) {
            if (!localStorage.getItem('theme')) {
                applyTheme(e.matches ? 'dark' : 'light', false);
            }
        }

        if (mediaQuery.addEventListener) {
            mediaQuery.addEventListener('change', handleSystemThemeChange);
        } else if (mediaQuery.addListener) {
            mediaQuery.addListener(handleSystemThemeChange);
        }
    }

    // ==================== 2. 回到顶部 & 导航栏模式（一次性） ====================
    const navMode = $html.attr('data-nav-mode') || 'shrink';

    if ($navbar.length) {
        $navbar.removeClass('fixed-mode pill-mode');
        if (navMode === 'fixed') {
            $navbar.removeClass('scrolled').addClass('fixed-mode');
        } else if (navMode === 'fixed-pill') {
            $navbar.addClass('scrolled pill-mode');
        } else {
            $navbar.toggleClass('scrolled', $(window).scrollTop() > 50);
        }
    }

    function onScroll() {
        const scrollY = $(window).scrollTop();
        $backToTopBtn.toggleClass('visible', scrollY > 400);
        if (navMode === 'shrink' && $navbar.length) {
            $navbar.toggleClass('scrolled', scrollY > 50);
        }
        const $readingProgress = $('#readingProgress');
        if ($readingProgress.length) {
            const progress = scrollY / ($(document).height() - $(window).height());
            $readingProgress.css('width', (progress * 100) + '%');
        }
    }

    $(window).on('scroll', onScroll);
    $backToTopBtn.on('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
    onScroll();

    // ==================== 3. 移动端菜单（一次性） ====================
    const $menuToggle = $('#menuToggle');
    const $mobileMenu = $('#mobileMenu');
    const $mobileMenuOverlay = $('#mobileMenuOverlay');
    const $mobileMenuClose = $('#mobileMenuClose');

    function openMobileMenu() {
        $mobileMenu.addClass('active');
        $mobileMenuOverlay.addClass('active');
        $('body').css('overflow', 'hidden');
    }

    function closeMobileMenu() {
        $mobileMenu.removeClass('active');
        $mobileMenuOverlay.removeClass('active');
        $('body').css('overflow', '');
    }

    $menuToggle.on('click', openMobileMenu);
    $mobileMenuClose.on('click', closeMobileMenu);
    $mobileMenuOverlay.on('click', closeMobileMenu);

    $(document).on('click', '.mobile-nav-item[data-has-submenu]', function (e) {
        e.preventDefault();
        const $submenu = $(this).next('.mobile-submenu');
        if (!$submenu.length) return;
        const isOpen = $submenu.hasClass('open');
        $('.mobile-submenu.open').removeClass('open');
        $('.mobile-nav-item.expanded').removeClass('expanded');
        if (!isOpen) {
            $submenu.addClass('open');
            $(this).addClass('expanded');
        }
    });

    $(document).on('click', '.mobile-nav-item:not([data-has-submenu]), .mobile-submenu a', closeMobileMenu);

    // ==================== 4. TOC 目录高亮/折叠（可重入） ====================
    function initToc() {
        const NAV_OFFSET = 120;
        const $tocLinks = $('.toc-link');
        const headings = [];

        $tocLinks.each(function () {
            const $link = $(this);
            const href = $link.attr('href');
            if (!href) return;
            const $target = $(href);
            if ($target.length) headings.push({ link: this, target: $target[0] });
        });

        $(document).off('click.toc').on('click.toc', '.toc-link', function (e) {
            e.preventDefault();
            const href = $(this).attr('href');
            if (!href) return;
            const $target = $(href);
            if (!$target.length) return;
            const targetOffset = $target.offset().top - NAV_OFFSET;
            window.scrollTo({ top: targetOffset, behavior: 'smooth' });
        });

        const $tocCard = $('#tocCard');
        const $tocToggle = $('#tocToggle');
        let autoExpandDisabled = false;

        function toggleToc() {
            if ($tocCard.hasClass('collapsed')) {
                $tocCard.removeClass('collapsed');
                autoExpandDisabled = false;
            } else {
                $tocCard.addClass('collapsed');
                autoExpandDisabled = true;
            }
        }

        $tocToggle.off('click.toc').on('click.toc', toggleToc);

        function updateActiveToc() {
            const scrollY = $(window).scrollTop() + NAV_OFFSET + 1;
            let activeIndex = -1;

            if (headings.length > 0) {
                const firstTop = $(headings[0].target).offset().top;
                if (firstTop > scrollY) {
                    $tocLinks.removeClass('active');
                    return;
                }
            }

            for (let i = 0; i < headings.length; i++) {
                const top = $(headings[i].target).offset().top;
                if (top > scrollY) {
                    activeIndex = i - 1;
                    break;
                }
            }

            if (activeIndex === -1 && headings.length > 0) {
                const $last = $(headings[headings.length - 1].target);
                if ($last.length && $last.offset().top + $last.height() > scrollY - NAV_OFFSET) {
                    activeIndex = headings.length - 1;
                }
            }

            $tocLinks.removeClass('active');
            if (activeIndex >= 0) {
                $(headings[activeIndex].link).addClass('active');
            }

            if (activeIndex >= 0 && $tocCard.length && $tocCard.hasClass('collapsed') && !autoExpandDisabled) {
                $tocCard.removeClass('collapsed');
            }
        }

        let tocTicking = false;
        $(window).off('scroll.toc').on('scroll.toc', function () {
            if (!tocTicking) {
                requestAnimationFrame(function () {
                    updateActiveToc();
                    tocTicking = false;
                });
                tocTicking = true;
            }
        });
        $(window).off('load.toc').on('load.toc', updateActiveToc);
        $(window).off('resize.toc').on('resize.toc', updateActiveToc);
        setTimeout(updateActiveToc, 100);
    }

    // ==================== 5. 选项卡（可重入） ====================
    function initTabs() {
        $('[data-tab-container]').each(function () {
            var $container = $(this);
            var $tabBtns = $container.find('.tab-btn');
            var $tabPanels = $container.find('.tab-panel');
            if (!$tabBtns.length || !$tabPanels.length) return;

            $tabBtns.off('click.tab').on('click.tab', function () {
                var targetId = $(this).attr('data-tab');
                if (!targetId) return;
                $tabBtns.removeClass('active');
                $tabPanels.removeClass('active');
                $(this).addClass('active');
                $('#' + targetId).addClass('active');
            });
        });
    }

    // ==================== 5. 手风琴（可重入） ====================
    function initAccordion() {
        $('[data-accordion]').each(function () {
            var $container = $(this);
            var mode = $container.attr('data-accordion');
            var $headers = $container.find('.accordion-header');
            var $panels = $container.find('.accordion-panel');

            $panels.each(function () {
                var $panel = $(this);
                var $header = $panel.prev('.accordion-header');
                if (!$header.length) return;
                var isExpanded = $header.attr('aria-expanded') === 'true';
                $panel.css('maxHeight', isExpanded ? '9999px' : '0');
            });

            $headers.off('click.accordion').on('click.accordion', function () {
                var $header = $(this);
                var $panel = $header.next('.accordion-panel');
                if (!$panel.length) return;
                var isExpanded = $header.attr('aria-expanded') === 'true';

                if (mode === 'single') {
                    $headers.attr('aria-expanded', 'false');
                    $panels.css('maxHeight', '0');
                }

                if (mode === 'single' && isExpanded) {
                    return;
                }

                var newExpanded = !isExpanded;
                $header.attr('aria-expanded', newExpanded ? 'true' : 'false');
                $panel.css('maxHeight', newExpanded ? $panel.prop('scrollHeight') + 'px' : '0');
            });
        });
    }

    // ==================== 6. 点赞 ====================
    $(document).on('click', '.post-action-btn.like-btn', function () {
        var $btn = $(this);
        var cid  = $btn.data('cid');
        if (!cid || $btn.prop('disabled')) return;

        $btn.prop('disabled', true).css('transform', 'scale(1.2)');
        fetch('/?action=like', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'cid=' + encodeURIComponent(cid)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                $btn.find('.action-count').text(data.likes);
                $btn.find('.material-icons')
                    .text(data.liked ? 'favorite' : 'favorite_border')
                    .css('color', data.liked ? '#ef4444' : '');
                $btn.toggleClass('liked', data.liked);
                showSnackbar(data.liked ? '感谢认可，已点赞' : '已取消点赞', data.liked ? 'success' : 'info');
            }
        })
        .finally(() => setTimeout(() => $btn.css('transform', '').prop('disabled', false), 200));
    });

    // ==================== 7. 加载更多评论（可重入） ====================
    

    // ==================== 8. 轮播图（可重入） ====================
    function initCarousel() {
        (function () {
            const $thumbCards = $('.hero-carousel-thumbs .thumb-card');
            const $magazineCards = $('.hero-article > .magazine-card');
            const $thumbsContainer = $('.hero-carousel-thumbs');

            if (!$magazineCards.length || !$thumbsContainer.length) return;

            let currentSlide = 1;
            const totalSlides = $magazineCards.length;

            if (window._carouselInterval) {
                clearInterval(window._carouselInterval);
                window._carouselInterval = null;
            }

            function switchToSlide(slideIndex) {
                const index = parseInt(slideIndex, 10);
                if (isNaN(index) || index < 1 || index > totalSlides) return;
                currentSlide = index;
                $magazineCards.removeClass('active').filter('[data-slide="' + index + '"]').addClass('active');
                $thumbCards.removeClass('active').filter('[data-slide="' + index + '"]').addClass('active');
            }

            function nextSlide() {
                currentSlide = currentSlide % totalSlides + 1;
                switchToSlide(currentSlide);
            }

            function startAutoPlay() {
                stopAutoPlay();
                window._carouselInterval = setInterval(nextSlide, 5000);
            }

            function stopAutoPlay() {
                if (window._carouselInterval) {
                    clearInterval(window._carouselInterval);
                    window._carouselInterval = null;
                }
            }

            const $initialActiveThumb = $thumbCards.filter('.active').first();
            if ($initialActiveThumb.length) switchToSlide($initialActiveThumb.attr('data-slide'));
            startAutoPlay();

            $thumbsContainer.off('mouseenter.carousel').on('mouseenter.carousel', stopAutoPlay);
            $thumbsContainer.off('mouseleave.carousel').on('mouseleave.carousel', startAutoPlay);
            $thumbCards.off('mouseenter.carousel').on('mouseenter.carousel', function () {
                switchToSlide($(this).attr('data-slide'));
            });
        })();
    }

    // ==================== 9. 搜索覆盖层（pjax 外，一次性） ====================
    (function () {
        const searchOverlay = document.getElementById('searchOverlay');
        const searchInput = document.getElementById('searchInput');
        const searchClearBtn = document.getElementById('searchClearBtn');
        const searchDefaultView = document.getElementById('searchDefaultView');
        const searchResultsView = document.getElementById('searchResultsView');
        const searchResultsList = document.getElementById('searchResultsList');
        const searchNoResults = document.getElementById('searchNoResults');

        if (!searchOverlay || !searchInput) return;

        const searchTriggerBtn = document.getElementById('searchTriggerBtn');
        const mobileSearchTrigger = document.getElementById('mobileSearchTrigger');
        const searchCloseBtn = document.getElementById('searchCloseBtn');
        const searchHistoryContainer = document.getElementById('searchHistoryContainer');
        const searchBackdrop = document.querySelector('.search-backdrop');

        const HISTORY_KEY = 'glassblog_search_history';
        const MAX_HISTORY = 5;

        function getHistory() {
            try { return JSON.parse(localStorage.getItem(HISTORY_KEY)) || []; }
            catch { return []; }
        }

        function saveHistory(terms) {
            localStorage.setItem(HISTORY_KEY, JSON.stringify(terms.slice(0, MAX_HISTORY)));
        }

        function addToHistory(term) {
            if (!term.trim()) return;
            let history = getHistory().filter(t => t.toLowerCase() !== term.trim().toLowerCase());
            history.unshift(term.trim());
            saveHistory(history);
            renderHistory();

            var url = '/?action=record&keyword=' + encodeURIComponent(term.trim());
            if (navigator.sendBeacon) {
                navigator.sendBeacon(url);
            } else {
                fetch(url, { keepalive: true }).catch(e => console.warn('记录搜索失败', e));
            }
        }

        function removeHistoryItem(term) {
            let history = getHistory().filter(t => t !== term);
            saveHistory(history);
            renderHistory();
        }

        function clearAllHistory() {
            localStorage.removeItem(HISTORY_KEY);
            renderHistory();
        }

        function escapeHTML(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function highlight(text, query) {
            if (!query.trim()) return escapeHTML(text);
            const escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const regex = new RegExp('(' + escaped + ')', 'gi');
            return escapeHTML(text).replace(regex, '<mark>$1</mark>');
        }

        function safeClosest(element, selector) {
            return element && typeof element.closest === 'function' ? element.closest(selector) : null;
        }

        function insertClearAllButton(container) {
            const section = container.closest('.search-section');
            if (!section || section.querySelector('.history-clear-all')) return;
            const header = document.createElement('div');
            header.className = 'search-history-header';
            const label = section.querySelector('.search-section-label');
            if (label) {
                label.parentNode.insertBefore(header, label);
                header.appendChild(label);
            }
            const clearBtn = document.createElement('button');
            clearBtn.className = 'history-clear-all';
            clearBtn.textContent = '清除';
            clearBtn.addEventListener('click', clearAllHistory);
            header.appendChild(clearBtn);
        }

        function renderHistory() {
            if (!searchHistoryContainer) return;
            insertClearAllButton(searchHistoryContainer);
            const history = getHistory();
            searchHistoryContainer.innerHTML = '';

            if (history.length === 0) {
                searchHistoryContainer.innerHTML = '<span style="font-size:0.75rem;color:var(--text-muted);padding:4px 0;">暂无搜索记录</span>';
                return;
            }

            history.forEach(term => {
                const chip = document.createElement('span');
                chip.className = 'search-history-chip';
                chip.innerHTML = '<span class="material-icons">schedule</span> ' + escapeHTML(term);
                chip.addEventListener('click', (e) => {
                    if (e.target.classList.contains('chip-delete') || safeClosest(e.target, '.chip-delete')) return;
                    searchInput.value = term;
                    performSearch(term);
                    searchInput.focus();
                });
                const delBtn = document.createElement('button');
                delBtn.className = 'chip-delete';
                delBtn.innerHTML = '&times;';
                delBtn.setAttribute('aria-label', '删除');
                delBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    removeHistoryItem(term);
                });
                chip.appendChild(delBtn);
                searchHistoryContainer.appendChild(chip);
            });
        }

        let searchTimer = null;
        function performSearch(term) {
            const trimmed = term.trim();
            if (!trimmed) {
                searchDefaultView.style.display = 'block';
                searchResultsView.style.display = 'none';
                searchClearBtn.style.display = 'none';
                return;
            }
            searchClearBtn.style.display = 'flex';
            searchDefaultView.style.display = 'none';
            searchResultsView.style.display = 'block';
            searchResultsList.innerHTML = '<div class="search-loading">🔍 搜索中...</div>';
            searchNoResults.style.display = 'none';

            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                fetch('/?action=search&keyword=' + encodeURIComponent(trimmed))
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            searchResultsList.innerHTML = '';
                            searchNoResults.innerHTML = '<span class="material-icons">error</span><p>' + escapeHTML(data.error) + '</p>';
                            searchNoResults.style.display = 'flex';
                            return;
                        }
                        if (!data.success || !data.data || data.data.length === 0) {
                            searchResultsList.innerHTML = '';
                            searchNoResults.innerHTML = '<span class="material-icons">search_off</span><p>未找到相关文章</p><span>试试其他关键词吧</span>';
                            searchNoResults.style.display = 'flex';
                            return;
                        }
                        searchNoResults.style.display = 'none';
                        renderResults(data.data, trimmed);
                    })
                    .catch(() => {
                        searchResultsList.innerHTML = '';
                        searchNoResults.innerHTML = '<span class="material-icons">error</span><p>搜索时发生错误，请稍后重试</p>';
                        searchNoResults.style.display = 'flex';
                    });
            }, 200);
        }

        function renderResults(items, keyword) {
            let html = '';
            items.forEach((item, index) => {
                const icon = item.type === 'post' ? 'article' : 'description';
                html += `
                    <a class="search-result-item" href="${escapeHTML(item.url)}" data-index="${index}">
                        <div class="search-result-icon"><span class="material-icons">${icon}</span></div>
                        <div class="search-result-info">
                            <span class="search-result-title">${highlight(item.title, keyword)}</span>
                            <div class="search-result-meta">
                                <span class="search-result-badge">${item.category || '未分类'}</span>
                                <span>${highlight(item.excerpt, keyword).substring(0, 40)}…</span>
                            </div>
                        </div>
                    </a>
                `;
            });
            searchResultsList.innerHTML = html;
            searchResultsList.querySelectorAll('.search-result-item').forEach(link => {
                link.addEventListener('click', () => addToHistory(keyword));
            });
        }

        function openSearch() {
            searchOverlay.classList.add('active');
            searchOverlay.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            searchInput.value = '';
            searchDefaultView.style.display = 'block';
            searchResultsView.style.display = 'none';
            searchClearBtn.style.display = 'none';
            renderHistory();
            setTimeout(() => searchInput.focus(), 150);
            refreshHotSearch();
        }

        async function refreshHotSearch() {
            let container = document.querySelector('.search-hot-tags');
            // 兜底：极端情况下容器没渲染出来（理论上 popupsearch.php 总会输出容器），
            // 自己创建一个塞进热门搜索区段，避免 "首次搜索后找不到节点" 的死循环
            if (!container) {
                const section = document.querySelector('#searchDefaultView .search-section');
                if (!section) return;
                container = document.createElement('div');
                container.className = 'search-hot-tags';
                section.appendChild(container);
            }
            try {
                const response = await fetch('/?action=hotSearchHTML&t=' + Date.now());
                if (response.ok) {
                    const html = await response.text();
                    const newContainer = document.createElement('div');
                    newContainer.innerHTML = html;
                    const newHotTags = newContainer.querySelector('.search-hot-tags');
                    if (newHotTags) {
                        container.parentNode.replaceChild(newHotTags, container);
                        // 重新绑新生成的热搜词 click
                        document.querySelectorAll('.search-hot-tag').forEach(tag => {
                            tag.addEventListener('click', function() {
                                const term = this.textContent.trim();
                                searchInput.value = term;
                                performSearch(term);
                                addToHistory(term);
                                searchInput.focus();
                            });
                        });
                    }
                }
            } catch (e) {
                console.warn('刷新热门搜索失败', e);
            }
        }

        function closeSearch() {
            searchOverlay.classList.remove('active');
            searchOverlay.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            searchInput.value = '';
            // 关闭弹窗时也预刷一次：保证本次提交的 record 已经落盘后，
            // 下次打开弹窗就能直接看到新热搜值，不用手动 F5
            // 仅当当前页面是 search 结果页或刚提交了搜索时才预刷，节省请求
            if (window.__searchPendingRecord) {
                window.__searchPendingRecord = false;
                // 延迟一点点让 sendBeacon 落盘
                setTimeout(function() { refreshHotSearch(); }, 200);
            }
        }

        if (searchTriggerBtn) searchTriggerBtn.addEventListener('click', openSearch);
        if (mobileSearchTrigger) {
            mobileSearchTrigger.addEventListener('click', (e) => {
                e.preventDefault();
                const mobileMenu = document.getElementById('mobileMenu');
                if (mobileMenu && mobileMenu.classList.contains('active')) {
                    mobileMenu.classList.remove('active');
                    document.getElementById('mobileMenuOverlay').classList.remove('active');
                    document.body.style.overflow = '';
                    setTimeout(openSearch, 300);
                } else {
                    openSearch();
                }
            });
        }
        if (searchCloseBtn) searchCloseBtn.addEventListener('click', closeSearch);
        if (searchBackdrop) searchBackdrop.addEventListener('click', closeSearch);

        searchInput.addEventListener('input', () => {
            const val = searchInput.value;
            searchClearBtn.style.display = val.trim() ? 'flex' : 'none';
            performSearch(val);
        });

        searchClearBtn.addEventListener('click', () => {
            searchInput.value = '';
            searchClearBtn.style.display = 'none';
            searchDefaultView.style.display = 'block';
            searchResultsView.style.display = 'none';
            searchInput.focus();
        });

        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (searchInput.value.trim()) {
                    searchInput.value = '';
                    searchClearBtn.style.display = 'none';
                    searchDefaultView.style.display = 'block';
                    searchResultsView.style.display = 'none';
                    e.preventDefault();
                } else {
                    closeSearch();
                }
                return;
            }
            if (e.key === 'Enter') {
                const items = searchResultsList.querySelectorAll('.search-result-item');
                const currentKeyword = searchInput.value.trim();
                if (items.length > 0) {
                    const active = searchResultsList.querySelector('.search-result-item.active');
                    if (active) active.click();
                    else items[0].click();
                } else if (currentKeyword) {
                    addToHistory(currentKeyword);
                }
                e.preventDefault();
            }
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                const items = Array.from(searchResultsList.querySelectorAll('.search-result-item'));
                if (items.length === 0) return;
                let currentIndex = items.findIndex(item => item.classList.contains('active'));
                if (e.key === 'ArrowDown') {
                    currentIndex = (currentIndex + 1) % items.length;
                } else {
                    currentIndex = (currentIndex - 1 + items.length) % items.length;
                }
                items.forEach(item => item.classList.remove('active'));
                items[currentIndex].classList.add('active');
                items[currentIndex].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            }
        });

        document.querySelectorAll('.search-hot-tag').forEach(tag => {
            tag.addEventListener('click', function () {
                const term = this.textContent.trim();
                searchInput.value = term;
                performSearch(term);
                addToHistory(term);
                searchInput.focus();
            });
        });

        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                searchOverlay.classList.contains('active') ? closeSearch() : openSearch();
            }
            if (e.key === '/' && !searchOverlay.classList.contains('active')) {
                const activeEl = document.activeElement;
                if (!activeEl || (activeEl.tagName !== 'INPUT' && activeEl.tagName !== 'TEXTAREA' && activeEl.contentEditable !== 'true')) {
                    e.preventDefault();
                    openSearch();
                }
            }
        });

        if (searchHistoryContainer) renderHistory();
        
    })();

    // ==================== 10. Tooltip 气泡 ====================
    (function () {
        const tooltipAttr = 'data-tooltip';
        let $activeTooltip = null;
        let tooltipTimeout = null;

        function createTooltipEl() {
            return $('<div class="tooltip-bubble"></div>').css({
                position: 'fixed',
                zIndex: 2500,
                background: 'rgba(255,255,255,0.75)',
                backdropFilter: 'blur(18px) saturate(180%)',
                WebkitBackdropFilter: 'blur(18px) saturate(180%)',
                border: '1px solid rgba(255,255,255,0.5)',
                borderRadius: '12px',
                padding: '8px 14px',
                fontSize: '0.8rem',
                fontWeight: '500',
                color: '#0a0a0a',
                boxShadow: '0 12px 28px rgba(0,0,0,0.12)',
                pointerEvents: 'none',
                opacity: 0,
                transition: 'opacity 0.2s',
                whiteSpace: 'nowrap'
            });
        }

        function updateDarkModeStyles($bubble) {
            const isDark = $html.attr('data-theme') === 'dark';
            $bubble.css({
                background: isDark ? 'rgba(15,23,42,0.85)' : 'rgba(255,255,255,0.75)',
                borderColor: isDark ? 'rgba(148,163,184,0.3)' : 'rgba(255,255,255,0.5)',
                color: isDark ? '#f1f5f9' : '#0a0a0a'
            });
        }

        function positionTooltip($bubble, $trigger) {
            const pos = $trigger.attr('data-tooltip-pos') || 'top';
            const gap = 10;
            const triggerRect = $trigger[0].getBoundingClientRect();
            const bubbleRect = $bubble[0].getBoundingClientRect();
            let x, y;

            switch (pos) {
                case 'bottom':
                    x = triggerRect.left + triggerRect.width / 2 - bubbleRect.width / 2;
                    y = triggerRect.bottom + gap;
                    break;
                case 'left':
                    x = triggerRect.left - bubbleRect.width - gap;
                    y = triggerRect.top + triggerRect.height / 2 - bubbleRect.height / 2;
                    break;
                case 'right':
                    x = triggerRect.right + gap;
                    y = triggerRect.top + triggerRect.height / 2 - bubbleRect.height / 2;
                    break;
                default:
                    x = triggerRect.left + triggerRect.width / 2 - bubbleRect.width / 2;
                    y = triggerRect.top - bubbleRect.height - gap;
            }

            x = Math.max(8, Math.min(x, window.innerWidth - bubbleRect.width - 8));
            y = Math.max(8, Math.min(y, window.innerHeight - bubbleRect.height - 8));

            $bubble.css({ left: x + 'px', top: y + 'px' });
        }

        function showTooltip($trigger) {
            const text = $trigger.attr(tooltipAttr);
            if (!text) return;

            if (!$activeTooltip) {
                $activeTooltip = createTooltipEl();
                $('body').append($activeTooltip);
            }

            $activeTooltip.text(text);
            updateDarkModeStyles($activeTooltip);
            positionTooltip($activeTooltip, $trigger);
            $activeTooltip.css('opacity', '1');
        }

        function hideTooltip() {
            if ($activeTooltip) {
                $activeTooltip.css('opacity', '0');
                if (tooltipTimeout) clearTimeout(tooltipTimeout);
                tooltipTimeout = setTimeout(function () {
                    if ($activeTooltip && $activeTooltip.css('opacity') === '0') {
                        $activeTooltip.remove();
                        $activeTooltip = null;
                    }
                }, 250);
            }
        }

        $(document).on('mouseenter', '[data-tooltip]', function () {
            const $trigger = $(this);
            if (($trigger.attr('data-tooltip-trigger') || 'hover') === 'click') return;
            showTooltip($trigger);
        });

        $(document).on('mouseleave', '[data-tooltip]', function () {
            const $trigger = $(this);
            if (($trigger.attr('data-tooltip-trigger') || 'hover') === 'click') return;
            hideTooltip();
        });

        $(document).on('click', '[data-tooltip]', function () {
            const $trigger = $(this);
            if (($trigger.attr('data-tooltip-trigger') || 'hover') === 'click') {
                if ($activeTooltip && $activeTooltip.css('opacity') === '1' && $activeTooltip.text() === $trigger.attr(tooltipAttr)) {
                    hideTooltip();
                } else {
                    showTooltip($trigger);
                }
            } else {
                hideTooltip();
            }
        });

        new MutationObserver(function () {
            if ($activeTooltip) updateDarkModeStyles($activeTooltip);
        }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

        $(window).on('scroll', hideTooltip);
        $(window).on('resize', hideTooltip);
    })();

    // ==================== 11. Snackbar 全局方法（一次性） ====================
    window.showSnackbar = function (message, type) {
        type = type || 'info';
        const $container = $('#snackbarContainer');
        if (!$container.length) return;

        const icons = {
            success: 'check_circle',
            warning: 'warning',
            error: 'error',
            info: 'info'
        };
        const iconName = icons[type] || 'info';

        const $snackbar = $('<div class="glass-snackbar ' + type + '"><span class="material-icons">' + iconName + '</span> ' + message + '</div>');
        $container.append($snackbar);

        $snackbar.on('animationend', function (e) {
            if (e.originalEvent.animationName === 'snackbarOut') {
                $(this).remove();
            }
        });

        const $all = $container.find('.glass-snackbar');
        if ($all.length > 3) $all.first().remove();
    };

    // ==================== 12. 归档页专用：热图（真实数据）====================
    function initArchiveHeatmap(heatmapData) {
        var $grid = $('#dotHeatmapGrid');
        if (!$grid.length) return;

        var weekLabels = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
        var monthLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        var monthWeeks = [5,4,5,4,4,4,5,4,4,5,4,4];
        var totalWeeks = 52;

        $grid.css('grid-template-columns', '28px repeat(' + totalWeeks + ', minmax(8px, 1fr))');

        var currentYear = new Date().getFullYear();
        var heatData = Array.from({length: 7}, function() { return Array(12).fill(0); });

        Object.keys(heatmapData || {}).forEach(function(monthKey) {
            var parts = monthKey.split('-').map(Number);
            var year = parts[0], month = parts[1];
            if (year === currentYear && month >= 1 && month <= 12) {
                var weekCounts = heatmapData[monthKey];
                for (var w = 0; w < 7; w++) {
                    heatData[w][month - 1] += (weekCounts[w] || 0);
                }
            }
        });

        var html = '';
        html += '<div style="grid-column:1;grid-row:1;"></div>';
        var colStart = 2;
        for (var m = 0; m < 12; m++) {
            var span = monthWeeks[m];
            html += '<div class="dot-month-label" style="grid-column:' + colStart + ' / span ' + span + ';grid-row:1;">' + monthLabels[m] + '</div>';
            colStart += span;
        }

        var colIdx = 0;
        for (var w = 0; w < 7; w++) {
            var rowIndex = w + 2;
            var showLabel = (w === 1 || w === 3 || w === 5) ? weekLabels[w] : '';
            html += '<div class="dot-week-label" style="grid-column:1;grid-row:' + rowIndex + ';">' + showLabel + '</div>';

            colIdx = 0;
            for (var m = 0; m < 12; m++) {
                var weeksInMonth = monthWeeks[m];
                for (var wk = 0; wk < weeksInMonth; wk++) {
                    var val = heatData[w][m];
                    var levelClass = 'dot-level-' + Math.min(val, 4);
                    var title = monthLabels[m] + ' · Week' + (colIdx + 1) + ' · ' + weekLabels[w] + ': ' + val + ' 篇文章';
                    html += '<div class="dot-cell ' + levelClass + '" style="grid-column:' + (colIdx + 2) + ';grid-row:' + rowIndex + ';" title="' + title + '"></div>';
                    colIdx++;
                }
            }
        }

        $grid.html(html);
    }

    // ==================== 13. 归档页专用：雷达图（真实数据）====================
    function initArchiveRadarChart(selector, rawData) {
        var $canvas = $(selector);
        if (!$canvas.length || !rawData || !rawData.length) return;

        var canvas = $canvas[0];
        var ctx = canvas.getContext('2d');
        var tooltipSelector = selector.replace('Chart', 'Tooltip');
        var $tooltip = $(tooltipSelector);

        var TOP_COUNT = 5;
        var displayData = [];
        var animProgress = 0;
        var targetProgress = 1;
        var animationId = null;
        var hoveredIdx = -1;
        var canvasSize = 300;
        var centerX, centerY, chartRadius;
        var pointPositions = [];

        var isTags = selector.toLowerCase().indexOf('tag') !== -1;
        var color = isTags
            ? { main: '#8b5cf6', glow: 'rgba(139,92,246,0.5)', fill: 'rgba(139,92,246,0.15)' }
            : { main: '#6366f1', glow: 'rgba(99,102,241,0.5)', fill: 'rgba(99,102,241,0.15)' };

        function aggregateData(raw) {
            if (raw.length <= TOP_COUNT) return raw.slice();
            var sorted = raw.slice().sort(function(a, b) { return b.value - a.value; });
            return sorted.slice(0, TOP_COUNT);
        }

        function maxValue(data) {
            var max = 1;
            for (var i = 0; i < data.length; i++) {
                if (data[i].value > max) max = data[i].value;
            }
            return max;
        }

        function setupCanvas() {
            var wrap = canvas.parentElement;
            var cssSize = Math.min(wrap.clientWidth - 8, 300);
            var dpr = window.devicePixelRatio || 1;
            canvasSize = cssSize;
            canvas.width = canvasSize * dpr;
            canvas.height = canvasSize * dpr;
            canvas.style.width = canvasSize + 'px';
            canvas.style.height = canvasSize + 'px';
            ctx.setTransform(1, 0, 0, 1, 0, 0);
            ctx.scale(dpr, dpr);
            centerX = canvasSize / 2;
            centerY = canvasSize / 2;
            chartRadius = canvasSize * 0.36;
        }

        function calcRadarPositions(data) {
            var N = data.length;
            var maxV = maxValue(data);
            var positions = [];
            for (var i = 0; i < N; i++) {
                var angle = -Math.PI / 2 + (2 * Math.PI * i) / N;
                var rawDist = maxV > 0 ? (data[i].value / maxV) : 0;
                var dist = rawDist * chartRadius * animProgress;
                positions.push({
                    x: centerX + Math.cos(angle) * dist,
                    y: centerY + Math.sin(angle) * dist,
                    angle: angle,
                    value: data[i].value,
                    label: data[i].label
                });
            }
            return positions;
        }

        function drawRadar(positions, data) {
            var N = positions.length;
            ctx.clearRect(0, 0, canvasSize, canvasSize);

            var rings = 5;
            for (var r = 1; r <= rings; r++) {
                var radius = (chartRadius / rings) * r;
                ctx.beginPath();
                for (var i = 0; i <= N; i++) {
                    var a = -Math.PI / 2 + (2 * Math.PI * i) / N;
                    var x = centerX + Math.cos(a) * radius;
                    var y = centerY + Math.sin(a) * radius;
                    if (i === 0) ctx.moveTo(x, y);
                    else ctx.lineTo(x, y);
                }
                ctx.closePath();
                ctx.strokeStyle = 'rgba(128,128,128,0.1)';
                ctx.lineWidth = r === rings ? 1.2 : 0.5;
                ctx.stroke();
            }

            for (var i = 0; i < N; i++) {
                var a = -Math.PI / 2 + (2 * Math.PI * i) / N;
                var ex = centerX + Math.cos(a) * chartRadius;
                var ey = centerY + Math.sin(a) * chartRadius;
                ctx.beginPath();
                ctx.moveTo(centerX, centerY);
                ctx.lineTo(ex, ey);
                ctx.strokeStyle = 'rgba(128,128,128,0.07)';
                ctx.lineWidth = 0.7;
                ctx.stroke();
            }

            if (positions.length >= 3) {
                ctx.beginPath();
                for (var i = 0; i < positions.length; i++) {
                    if (i === 0) ctx.moveTo(positions[i].x, positions[i].y);
                    else ctx.lineTo(positions[i].x, positions[i].y);
                }
                ctx.closePath();
                ctx.fillStyle = color.fill;
                ctx.fill();
                ctx.strokeStyle = color.main;
                ctx.lineWidth = 2.2;
                ctx.shadowColor = color.glow;
                ctx.shadowBlur = 12;
                ctx.stroke();
                ctx.shadowBlur = 0;
            }

            for (var i = 0; i < positions.length; i++) {
                var isHovered = i === hoveredIdx;
                ctx.beginPath();
                ctx.arc(positions[i].x, positions[i].y, isHovered ? 11 : 7, 0, Math.PI * 2);
                ctx.fillStyle = color.glow;
                ctx.fill();
                ctx.beginPath();
                ctx.arc(positions[i].x, positions[i].y, isHovered ? 5 : 3.2, 0, Math.PI * 2);
                ctx.fillStyle = isHovered ? '#fff' : color.main;
                ctx.fill();
                ctx.strokeStyle = '#fff';
                ctx.lineWidth = 1.5;
                ctx.stroke();
            }

            ctx.beginPath();
            ctx.arc(centerX, centerY, 2.5, 0, Math.PI * 2);
            ctx.fillStyle = 'rgba(128,128,128,0.35)';
            ctx.fill();

            var isDark = $('html').attr('data-theme') === 'dark';
            var fontSize = Math.max(9.5, canvasSize * 0.034);
            ctx.font = '600 ' + fontSize + 'px var(--font-sans, sans-serif)';
            ctx.fillStyle = isDark ? '#cbd5e1' : '#2d2d2d';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            var offset = chartRadius + fontSize * 1.8;
            for (var i = 0; i < N; i++) {
                var a = -Math.PI / 2 + (2 * Math.PI * i) / N;
                var lx = centerX + Math.cos(a) * offset;
                var ly = centerY + Math.sin(a) * offset;
                ctx.fillText(data[i].label, lx, ly);
            }
        }

        function render() {
            if (displayData.length === 0) return;
            var positions = calcRadarPositions(displayData);
            pointPositions = positions;
            drawRadar(positions, displayData);
        }

        function animateLoop() {
            if (Math.abs(animProgress - targetProgress) < 0.004) {
                animProgress = targetProgress;
                render();
                animationId = null;
                return;
            }
            animProgress += (targetProgress - animProgress) * 0.1;
            render();
            animationId = requestAnimationFrame(animateLoop);
        }

        function triggerAnimation() {
            if (animationId) cancelAnimationFrame(animationId);
            animProgress = 0;
            targetProgress = 1;
            render();
            animationId = requestAnimationFrame(animateLoop);
        }

        displayData = aggregateData(rawData || []);
        setupCanvas();
        triggerAnimation();

        function getHoveredIndexRadar(mx, my, positions) {
            for (var i = 0; i < positions.length; i++) {
                var p = positions[i];
                var dx = mx - p.x;
                var dy = my - p.y;
                if (Math.sqrt(dx * dx + dy * dy) < 16) return i;
            }
            return -1;
        }

        $canvas.off('mousemove.radar').on('mousemove.radar', function (e) {
            var rect = canvas.getBoundingClientRect();
            var scaleX = canvasSize / rect.width;
            var scaleY = canvasSize / rect.height;
            var mx = (e.clientX - rect.left) * scaleX;
            var my = (e.clientY - rect.top) * scaleY;
            var idx = getHoveredIndexRadar(mx, my, pointPositions);
            if (idx !== hoveredIdx) {
                hoveredIdx = idx;
                render();
                if (idx >= 0 && displayData[idx]) {
                    $tooltip.text(displayData[idx].label + ': ' + displayData[idx].value).show();
                    var p = pointPositions[idx];
                    $tooltip.css({ left: (p.x / scaleX) + 'px', top: (p.y / scaleY - 18) + 'px' });
                } else {
                    $tooltip.hide();
                }
            }
        });

        $canvas.off('mouseleave.radar').on('mouseleave.radar', function () {
            hoveredIdx = -1;
            render();
            $tooltip.hide();
        });

        var resizeTimer;
        $(window).off('resize.radar').on('resize.radar', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () {
                setupCanvas();
                render();
            }, 200);
        });

        window._radarCharts = window._radarCharts || {};
        window._radarCharts[selector] = {
            render: render,
            setupCanvas: setupCanvas
        };
    }

    // ==================== 14. Fancybox v6 增强初始化（可重入） ====================
    function initFancybox() {
        $('.post-content img:not(.no-fancybox)').each(function () {
            if (!$(this).parent().is('a')) {
                $(this).wrap('<a href="' + this.src + '" data-fancybox="gallery" data-type="image" data-caption="' + (this.alt || '') + '"></a>');
            }
        });

        function _getFancyboxTheme() {
            var stored = localStorage.getItem('theme');
            if (stored === 'dark' || stored === 'light') return stored;
            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) return 'dark';
            return 'light';
        }

        if (typeof window.Fancybox !== 'undefined') {
            var _fbTheme = _getFancyboxTheme();

            function _initFancybox() {
                if (window.Fancybox.getInstances) {
                    window.Fancybox.getInstances().forEach(function (instance) {
                        instance.destroy();
                    });
                }
                window.Fancybox.bind('[data-fancybox]', {
                    theme: _fbTheme,
                    loop: true,
                    Image: { protect: true },
                    Carousel: {
                        Toolbar: {
                            display: {
                                left: ['counter'],
                                middle: ['zoomIn','zoomOut','toggle1to1','rotateCCW','rotateCW','flipX','flipY'],
                                right: ['autoplay','download','slideshow','thumbs','close']
                            }
                        },
                        Thumbs: {
                            type: 'classic',
                            showOnStart: false,
                            Carousel: {
                                vertical: true,
                                center: function(ref) {
                                    return ref.getTotalSlideDim() > ref.getViewportDim();
                                }
                            }
                        }
                    },
                    Slideshow: { timeout: 5000 }
                });
            }

            _initFancybox();

            if (!window._fancyboxThemeBound) {
                window._fancyboxThemeBound = true;

                function _updateFancyboxTheme() {
                    var newTheme = _getFancyboxTheme();
                    if (newTheme === _fbTheme) return;
                    _fbTheme = newTheme;
                    _initFancybox();
                }

                $(window).on('storage', function (e) {
                    if (e.originalEvent && e.originalEvent.key === 'theme') {
                        _updateFancyboxTheme();
                    }
                });

                if (window.matchMedia) {
                    var colorSchemeMQ = window.matchMedia('(prefers-color-scheme: dark)');
                    function onColorSchemeChange() {
                        if (!localStorage.getItem('theme')) {
                            _updateFancyboxTheme();
                        }
                    }
                    if (colorSchemeMQ.addEventListener) {
                        colorSchemeMQ.addEventListener('change', onColorSchemeChange);
                    } else if (colorSchemeMQ.addListener) {
                        colorSchemeMQ.addListener(onColorSchemeChange);
                    }
                }

                $(document).on('click', '#themeToggle, #mobileThemeToggle', function () {
                    setTimeout(_updateFancyboxTheme, 50);
                });
            }
        }
    }

    // ==================== 15. 代码块增强（可重入） ====================
    function initCodeBlocks() {
        $('.code-block').each(function () {
            const $block = $(this);
            const $pre = $block.find('pre');
            if (!$pre.length) return;

            const $langSpan = $block.find('.code-lang');
            const langText = $langSpan.text().trim();
            const langMap = {
                'CSS': 'css', 'JavaScript': 'javascript', 'JS': 'javascript',
                'HTML': 'xml', 'TypeScript': 'typescript', 'TS': 'typescript',
                'JSON': 'json', 'Bash': 'bash', 'Shell': 'bash',
                'Python': 'python', 'PHP': 'php', 'SQL': 'sql',
                'Markdown': 'markdown', 'YAML': 'yaml'
            };
            const langClass = langMap[langText] || langText.toLowerCase();

            let $code = $pre.children('code');
            if (!$code.length) {
                const rawText = $pre.text();
                $pre.empty();
                $code = $('<code></code>').text(rawText);
                $pre.append($code);
            }
            $code.removeClass().addClass('language-' + langClass);

            if (typeof hljs !== 'undefined') {
                hljs.highlightElement($code[0]);
            }

            const $header = $block.find('.code-header');
            if ($header.length) {
                $header.empty();

                const $dots = $('<span class="code-dots"></span>');
                $dots.append('<span class="code-dot dot-red"></span>');
                $dots.append('<span class="code-dot dot-yellow"></span>');
                $dots.append('<span class="code-dot dot-green"></span>');
                $header.append($dots);

                $header.append('<span class="code-lang-text">' + langText + '</span>');

                const $copyBtn = $('<button class="code-copy-btn" data-tooltip="复制代码"><span class="material-icons" style="font-size: 12px;">content_copy</span></button>');
                $header.append($copyBtn);

                $copyBtn.on('click', function (e) {
                    e.stopPropagation();
                    const codeText = $pre.text();
                    navigator.clipboard.writeText(codeText).then(function () {
                        $copyBtn.text('已复制');
                        setTimeout(function () { $copyBtn.text('复制'); }, 1500);
                        if (typeof showSnackbar === 'function') {
                            showSnackbar('代码已复制', 'success');
                        }
                    }).catch(function () {
                        const $textarea = $('<textarea></textarea>');
                        $textarea.val(codeText).css({ position: 'fixed', opacity: 0 });
                        $('body').append($textarea);
                        $textarea[0].select();
                        try { document.execCommand('copy'); } catch (e) {}
                        $textarea.remove();
                        if (typeof showSnackbar === 'function') {
                            showSnackbar('代码已复制', 'success');
                        }
                    });
                });
            }
        });

        if (typeof hljs !== 'undefined') {
            hljs.highlightAll();
        }
    }

    // ==================== 15.5 GitHub 短代码卡片异步加载（可重入） ====================
    // 把 footer.php 的 loadGhCards 移过来：所有页面 reinit 时都跑一次，
    // 不再依赖 footer 仅在 post/page 渲染时挂 pjax:complete 监听
    // （从非 post/page 页面 pjax 跳到带 gh-async 的文章时，之前会因监听缺失而占位符不替换）
    function loadGhCards() {
        $('.gh-async').each(function () {
            const $card = $(this);
            const repo = $card.data('repo');
            if (!repo) return;

            $.ajax({
                url: 'https://api.github.com/repos/' + repo,
                method: 'GET',
                headers: {
                    'Accept': 'application/vnd.github.v3+json'
                },
                dataType: 'json',
                timeout: 8000,
                success: function (data) {
                    if (data.message) {
                        $card.html(
                            '<div class="gh-card-error">'
                            + '<span class="material-icons" aria-hidden="true">error_outline</span> '
                            + '<span>GitHub 加载失败，请检查网络后刷新</span>'
                            + '</div>'
                        ).removeClass('gh-async');
                    } else {
                        const langColors = {
                            'TypeScript': '#3178c6', 'JavaScript': '#f1e05a', 'Python': '#3572A5',
                            'PHP': '#4F5D95', 'CSS': '#563d7c', 'HTML': '#e34c26', 'Java': '#b07219',
                            'Go': '#00ADD8', 'Rust': '#dea584', 'Vue': '#41b883', 'C++': '#f34b7d',
                            'C': '#555555', 'C#': '#178600', 'Ruby': '#701516', 'Swift': '#ffac45',
                            'Kotlin': '#A97BFF', 'Dart': '#00B4B4', 'Shell': '#89e051', 'Markdown': '#083fa1'
                        };
                        const owner = data.owner ? data.owner.login : repo.split('/')[0];
                        const name = data.name || repo.split('/')[1];
                        const desc = data.description || '暂无描述';
                        const lang = data.language || 'Unknown';
                        const langColor = langColors[lang] || '#3178c6';
                        const stars = data.stargazers_count || 0;
                        const forks = data.forks_count || 0;
                        const fmt = (n) => n >= 1000000 ? (n / 1000000).toFixed(1) + 'M' : n >= 1000 ? (n / 1000).toFixed(1) + 'k' : n.toString();
                        const url = data.html_url || ('https://github.com/' + repo);

                        const html = '<a class="gh-card-link" href="' + url + '" target="_blank" rel="noopener noreferrer">'
                            + '<span class="gh-corner-icon"><span class="material-icons" aria-hidden="true">open_in_new</span></span>'
                            + '<div class="gh-card-header">'
                            + '<span class="gh-repo-icon"><span class="material-icons" aria-hidden="true">bookmark</span></span>'
                            + '<div class="gh-repo-info">'
                            + '<span class="gh-repo-owner">' + owner + '</span>'
                            + '<span class="gh-repo-name">' + name + '</span>'
                            + '</div></div>'
                            + '<p class="gh-repo-desc">' + desc + '</p>'
                            + '<div class="gh-repo-meta">'
                            + '<span class="gh-lang">'
                            + '<span class="gh-lang-dot" style="--lang-color:' + langColor + ';"></span>' + lang
                            + '</span>'
                            + '<span><span class="material-icons" aria-hidden="true">star</span> ' + fmt(stars) + '</span>'
                            + '<span><span class="material-icons" aria-hidden="true">call_split</span> ' + fmt(forks) + '</span>'
                            + '</div></a>';

                        $card.html(html).removeClass('gh-async');
                    }
                },
                error: function () {
                    $card.html(
                        '<div class="gh-card-error">'
                        + '<span class="material-icons" aria-hidden="true">error_outline</span> '
                        + '<span>GitHub 加载失败，请检查网络后刷新</span>'
                        + '</div>'
                    ).removeClass('gh-async');
                }
            });
        });
    }

    // ==================== 16. 评论系统（可重入） ====================
    function initComments() {
        var $doc = $(document);

        // 同步邮件通知开关状态（cookie）
        var v = (document.cookie.match(/(?:^|; )comment_notify=([^;]*)/) || [])[1];
        $('#commentNotifyToggle').prop('checked', v !== '0');

        // 邮件通知开关
        $doc.off('change.notify', '#commentNotifyToggle').on('change.notify', '#commentNotifyToggle', function () {
            document.cookie = 'comment_notify=' + (this.checked ? '1' : '0') + '; path=/; max-age=' + (365*24*3600);
        });

        // 缓存 DOM 引用
        var $mainForm = $('#commentForm');
        var $formWrap = $('#commentFormWrap');
        var $formAnchor = $('#commentFormAnchor');
        var $parentInput = $('#commentParent');
        var $textarea = $('#commentTextarea');
        var $cancelReplyBtn = $('#commentCancelReply');
        var $submitBtn = $mainForm.find('.comment-submit-btn');
        var $submitText = $mainForm.find('.comment-submit-text');
        var defaultPlaceholder = $textarea.attr('placeholder') || '写下你的想法…';

        function esc(s) {
            return String(s == null ? '' : s).replace(/[&<<>"']/g, function (m) {
                return ({'&':'&amp;','<<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m];
            });
        }
        function validate(data, opts) {
            if (!data.author) return '请输入名称';
            if (!data.text)   return '请输入评论内容';
            if (opts.requireMail && !data.mail) return '请输入邮箱';
            if (data.mail && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.mail)) return '邮箱格式不正确';
            if (opts.requireUrl && !data.url) return '请输入网站';
            if (data.url && !/^https?:\/\/[^\s/$.?#].[^\s]*$/i.test(data.url)) return '网站地址格式不正确';
            return null;
        }
        function bumpCount($c, d) {
            var n = parseInt($c.text(), 10); if (isNaN(n)) n = 0;
            $c.text(Math.max(0, n + d) + ' 条');
        }
        function renderCommentItem(c) {
            var authorHtml = c.url
                ? '<a href="' + esc(c.url) + '" target="_blank" rel="noopener noreferrer" class="comment-author link">' + esc(c.author) + '</a>'
                : '<span class="comment-author">' + esc(c.author) + '</span>';
            var badgeHtml = c.isAuthor ? '<span class="comment-badge author-badge">✍ 作者</span>' : '';
            var statusHtml = c.status === 'waiting' ? '<span class="comment-pending" style="color:#e67e22;font-size:0.85em;margin-left:6px;">待审核</span>' : '';
            var atHtml = '';
            if (c.parent && c.parent > 0 && c.parentAuthor) {
                atHtml = '<a href="#li-comment-' + c.parent + '" class="comment-at">@' + esc(c.parentAuthor) + '</a> ';
            }
            return $(
                '<li class="comment-item" role="listitem" id="li-comment-' + c.coid + '" data-coid="' + c.coid + '" data-parent="' + (c.parent || 0) + '">' +
                    '<img alt="' + esc(c.author) + '头像" class="comment-avatar" loading="lazy" src="' + esc(c.avatar) + '">' +
                    '<div class="comment-main">' +
                        '<div class="comment-header">' + authorHtml + badgeHtml +
                            '<span class="comment-time"><span class="material-icons" aria-hidden="true">schedule</span>' + (c.datetime || '刚刚') + '</span>' +
                            statusHtml +
                        '</div>' +
                        '<div class="comment-bubble"><div class="comment-text">' + atHtml + (c.content || '') + '</div></div>' +
                        '<div class="comment-actions-row">' +
                            '<button aria-label="点赞" class="comment-action-btn like-comment-btn" type="button" data-coid="' + c.coid + '">' +
                                '<span class="material-icons" aria-hidden="true">thumb_up</span>' +
                                '<span class="action-count">0</span>' +
                            '</button>' +
                            '<button aria-label="回复" class="comment-action-btn reply-trigger" type="button" data-coid="' + c.coid + '" data-author="' + esc(c.author) + '">' +
                                '<span class="material-icons" aria-hidden="true">reply</span> 回复' +
                            '</button>' +
                        '</div>' +
                    '</div>' +
                '</li>'
            );
        }
        function renderTopCommentOpen(c) {
            var authorHtml = c.url
                ? '<a href="' + esc(c.url) + '" target="_blank" rel="noopener noreferrer" class="comment-author link">' + esc(c.author) + '</a>'
                : '<span class="comment-author">' + esc(c.author) + '</span>';
            var badgeHtml = c.isAuthor ? '<span class="comment-badge author-badge">✍ 作者</span>' : '';
            var statusHtml = c.status === 'waiting' ? '<span class="comment-pending" style="color:#e67e22;font-size:0.85em;margin-left:6px;">待审核</span>' : '';
            return $(
                '<li class="comment-item" role="listitem" id="li-comment-' + c.coid + '" data-coid="' + c.coid + '" data-parent="0">' +
                    '<img alt="' + esc(c.author) + '头像" class="comment-avatar" loading="lazy" src="' + esc(c.avatar) + '">' +
                    '<div class="comment-main">' +
                        '<div class="comment-header">' + authorHtml + badgeHtml +
                            '<span class="comment-time"><span class="material-icons" aria-hidden="true">schedule</span>' + (c.datetime || '刚刚') + '</span>' +
                            statusHtml +
                        '</div>' +
                        '<div class="comment-bubble"><div class="comment-text">' + (c.content || '') + '</div></div>' +
                        '<div class="comment-actions-row">' +
                            '<button aria-label="点赞" class="comment-action-btn like-comment-btn" type="button" data-coid="' + c.coid + '">' +
                                '<span class="material-icons" aria-hidden="true">thumb_up</span>' +
                                '<span class="action-count">0</span>' +
                            '</button>' +
                            '<button aria-label="回复" class="comment-action-btn reply-trigger" type="button" data-coid="' + c.coid + '" data-author="' + esc(c.author) + '">' +
                                '<span class="material-icons" aria-hidden="true">reply</span> 回复' +
                            '</button>' +
                        '</div>'
            );
        }

        function openReply($item, coid, author) {
            if (!$formWrap.length || !$formAnchor.length) return;
            var $main = $item.children('.comment-main');
            if (!$main.length) return;
            $main.append($formWrap);
            $parentInput.val(coid);
            $textarea.attr('placeholder', '回复 @' + author + '…');
            $cancelReplyBtn.show();
            $submitText.text('回复');
            $formWrap.addClass('is-reply-mode');
            $main.addClass('has-pending-reply');
            $textarea.trigger('focus');
        }
        function closeReply(opts) {
            if (!$formWrap.length || !$formAnchor.length) return;
            $formAnchor.append($formWrap);
            $parentInput.val(0);
            $textarea.attr('placeholder', defaultPlaceholder);
            $cancelReplyBtn.hide();
            $submitText.text('发布');
            $formWrap.removeClass('is-reply-mode');
            $('.comment-main.has-pending-reply').removeClass('has-pending-reply');
            if (!opts || opts.clearText !== false) $textarea.val('');
        }

        // 表单提交
        $doc.off('submit', '#commentForm').on('submit', '#commentForm', function (e) {
            e.preventDefault();
            var $form = $(this), $btn = $form.find('.comment-submit-btn');
            if ($btn.prop('disabled')) return;
            var data = {
                author: $form.find('input[name="author"]').val().trim(),
                mail: $form.find('input[name="mail"]').val().trim(),
                url: $form.find('input[name="url"]').val().trim(),
                text: $form.find('textarea[name="text"]').val().trim()
            };
            var err = validate(data, { requireMail: $form.data('require-mail')==='1', requireUrl: $form.data('require-url')==='1' });
            if (err) { showSnackbar(err, 'warning'); return; }
            var parent = parseInt($form.find('input[name="parent"]').val(), 10) || 0;
            $btn.prop('disabled', true);
            var origHtml = $btn.html();
            $btn.html('<span class="material-icons">hourglass_top</span> ' + (parent > 0 ? '发送中…' : '发布中…'));
            $.ajax({
                url: window.location.href, type: 'POST', dataType: 'json', timeout: 20000,
                data: { themeAction: 'comment', cid: $form.data('cid'), parent: parent,
                    author: data.author, mail: data.mail, url: data.url, text: data.text },
                success: function (resp) {
                    if (resp && resp.status === 1 && resp.comment) {
                        var newComment = resp.comment;
                        var parentCoid = newComment.parent || 0;
                        if (parentCoid === 0) {
                            var $topOpen = renderTopCommentOpen(newComment);
                            var $nested = $('<ul class="comments-nested"></ul>');
                            $topOpen.append($nested).append('</div></li>');
                            $('#commentsList').prepend($topOpen);
                        } else {
                            var $parentComment = $('#li-comment-' + parentCoid);
                            if ($parentComment.length) {
                                var $topComment = $parentComment.closest('.comment-item[data-parent="0"]');
                                if (!$topComment.length) $topComment = $parentComment;
                                var $nestedList = $topComment.find('> .comment-main > .comments-nested');
                                if (!$nestedList.length) {
                                    $nestedList = $('<ul class="comments-nested"></ul>');
                                    $topComment.children('.comment-main').append($nestedList);
                                }
                                $nestedList.append(renderCommentItem(newComment));
                            } else {
                                $('#commentsList').append(renderCommentItem(newComment));
                            }
                        }
                        bumpCount($('.comments-count'), 1);
                        closeReply();
                        showSnackbar(
                            newComment.status === 'waiting'
                                ? (parentCoid > 0 ? '回复已提交，等待审核' : '评论已提交，等待审核')
                                : (parentCoid > 0 ? '回复成功' : '评论发布成功'),
                            'success');
                    } else {
                        showSnackbar((resp && resp.msg) || '评论发布失败', 'error');
                    }
                },
                error: function (xhr, status) {
                    showSnackbar(status === 'timeout' ? '请求超时，请重试' : '网络错误，请重试', 'error');
                },
                complete: function () { $btn.prop('disabled', false).html(origHtml); }
            });
        });

        // 回复按钮
        $doc.off('click', '.reply-trigger').on('click', '.reply-trigger', function (e) {
            e.preventDefault();
            var $btn = $(this), coid = $btn.data('coid'), author = $btn.data('author'),
                $item = $btn.closest('.comment-item');
            if ($item.children('.comment-main').find('#commentFormWrap').length) {
                $textarea.trigger('focus'); return;
            }
            openReply($item, coid, author);
        });

        // 取消回复
        $doc.off('click', '#commentCancelReply').on('click', '#commentCancelReply', function (e) {
            e.preventDefault(); closeReply();
        });

        // 点击外部关闭回复
        $doc.off('click', '#commentsList').on('click', '#commentsList', function (e) {
            if (!$formWrap.hasClass('is-reply-mode')) return;
            if ($(e.target).closest('#commentFormWrap').length) return;
            if ($(e.target).closest('.reply-trigger').length) return;
            closeReply();
        });

        // 加载更多评论
        $doc.off('click.aploadmore', '#loadMoreComments').on('click.aploadmore', '#loadMoreComments', function () {
            var $btn = $(this); if ($btn.prop('disabled')) return;
            var nextPage = parseInt($btn.data('page'), 10) + 1;
            $btn.prop('disabled', true);
            $btn.find('.load-more-text').text('加载中…');
            $.ajax({
                url: window.location.href, type: 'GET', dataType: 'json', timeout: 20000,
                data: { themeAction: 'loadMoreComments', cid: $btn.data('cid'),
                        page: nextPage, pageSize: $btn.data('page-size'), order: $btn.data('order') },
                success: function (resp) {
                    if (resp && resp.status === 1 && resp.comments) {
                        var $list = $('#commentsList');
                        resp.comments.forEach(function (top) {
                            var $topOpen = renderTopCommentOpen(top);
                            if (top.descendants && top.descendants.length) {
                                var $nested = $('<ul class="comments-nested"></ul>');
                                top.descendants.forEach(function (child) {
                                    $nested.append(renderCommentItem(child));
                                });
                                $topOpen.append($nested);
                            } else {
                                $topOpen.append($('<ul class="comments-nested"></ul>'));
                            }
                            $topOpen.append('</div></li>');
                            $list.append($topOpen);
                        });
                        $btn.data('page', nextPage);
                        if (!resp.hasMore) {
                            $btn.off('click.aploadmore').prop('disabled', true).css({opacity:'0.6',cursor:'default'});
                            $btn.find('.load-more-text').text('没有更多评论了');
                            $btn.find('.material-icons').text('check');
                        } else {
                            $btn.find('.load-more-text').text('加载更多评论');
                        }
                    } else {
                        $btn.find('.load-more-text').text('加载失败，重试');
                    }
                },
                error: function () { $btn.find('.load-more-text').text('加载失败，重试'); },
                complete: function () { $btn.prop('disabled', false); }
            });
        });

        // 评论点赞
        $doc.off('click', '.like-comment-btn').on('click', '.like-comment-btn', function () {
            var $btn = $(this), $count = $btn.find('.action-count'),
                n = parseInt($count.text(), 10) || 0;
            if ($btn.hasClass('liked')) {
                $btn.removeClass('liked');
                $count.text(Math.max(0, n - 1));
                $btn.find('.material-icons').text('thumb_up');
            } else {
                $btn.addClass('liked');
                $count.text(n + 1);
                $btn.find('.material-icons').text('thumb_up_off');
            }
        });
    }

    // ==================== 17. 复制提取码（全局方法，一次性） ====================
    window.copyRefCode = function (event, codeId) {
        event.preventDefault();
        event.stopPropagation();
        const $codeSpan = $('#' + codeId);
        if (!$codeSpan.length) return;
        const code = $codeSpan.text().trim();

        navigator.clipboard.writeText(code).then(function () {
            if (typeof showSnackbar === 'function') {
                showSnackbar('提取码已复制！', 'success');
            }
        }).catch(function () {
            const $textarea = $('<textarea></textarea>');
            $textarea.val(code).css({ position: 'fixed', opacity: 0 });
            $('body').append($textarea);
            $textarea[0].select();
            try { document.execCommand('copy'); } catch (e) {}
            $textarea.remove();
            if (typeof showSnackbar === 'function') {
                showSnackbar('提取码已复制！', 'success');
            }
        });
    };

// ==================== 18. 音乐播放器（一次性，保持状态） ====================
(function() {
    const $player = $('#musicPlayer');
    const $collapsed = $('#musicPlayerCollapsed');
    //const $expanded = $('#musicPlayerExpanded');
    const $toggleBtn = $('#musicToggleBtn');
    const $closeBtn = $('#musicCloseBtn');
    const $playBtn = $('#musicPlayBtn, #musicMainPlayBtn');
    const $playIcon = $('#playIcon, #mainPlayIcon');
    const $prevBtn = $('#musicPrevBtn');
    const $nextBtn = $('#musicNextBtn');
    const $shuffleBtn = $('#musicShuffleBtn');
    const $repeatBtn = $('#musicRepeatBtn');
    const $progressBar = $('#musicProgressBar');
    const $progressFill = $('#musicProgressFill');
    const $progressThumb = $('#musicProgressThumb');
    const $currentTime = $('#musicCurrentTime');
    const $duration = $('#musicDuration');
    const $volumeBar = $('#musicVolumeBar');
    const $volumeFill = $('#musicVolumeFill');
    const $volumeThumb = $('#musicVolumeThumb');
    const $volBtn = $('#musicVolBtn');
    const $volIcon = $('#volIcon');
    const $randomAutoplayBtn = $('#musicRandomAutoplayBtn');
    const $randomAutoplayIcon = $('#randomAutoplayIcon');
    const $playlist = $('#musicPlaylist');
    const $albumArt = $('#musicAlbumArt');
    const $trackTitle = $('#musicTrackTitle');
    const $trackArtist = $('#musicTrackArtist');
    const $waveBars = $('#musicWaveBars');

    if (!$player.length) return;

    const playlist = window._glassblogMusicPlaylist || [];
    if (!playlist.length) return;

    const audio = new Audio();
    audio.preload = 'metadata';

    let isPlaying = false;
    let currentIndex = 0;
    let isShuffle = false;
    let isRepeat = false;
    let volume = 0.5;
    let isDragging = false;
    let isVolumeDragging = false;
    let isSimulated = false;
    let simTimer = null;
    let simCurrentTime = 0;
    let simDuration = 225;
    let autoplayBlocked = false;
    let pendingAutoplay = false;
    let dragPercent = 0;

    // ========== 修复：前台 localStorage 优先级高于后台配置 ==========
    let isRandomAutoplay;
    const storedRandomAutoplay = localStorage.getItem('glassblog_music_random_autoplay');
    if (storedRandomAutoplay !== null) {
        isRandomAutoplay = storedRandomAutoplay === 'true';
    } else if (typeof window._glassblogMusicConfig !== 'undefined' && 
        window._glassblogMusicConfig.randomAutoplay !== undefined) {
        isRandomAutoplay = !!window._glassblogMusicConfig.randomAutoplay;
    } else {
        isRandomAutoplay = false;
    }
    // ==============================================================

    function onFirstUserInteraction() {
        if (autoplayBlocked && pendingAutoplay) {
            autoplayBlocked = false;
            pendingAutoplay = false;
            setTimeout(function() {
                if (!isPlaying) {
                    togglePlay();
                }
            }, 50);
        }
        $(document).off('click.firstInteraction keydown.firstInteraction touchstart.firstInteraction');
    }

    function bindFirstInteraction() {
        $(document).one('click.firstInteraction keydown.firstInteraction touchstart.firstInteraction', onFirstUserInteraction);
    }

    function expandPlayer() {
        $player.addClass('expanded');
    }
    function collapsePlayer() {
        $player.addClass('collapsing');
        setTimeout(function() {
            $player.removeClass('expanded collapsing');
        }, 100);
    }
    $toggleBtn.on('click', function(e) {
        e.stopPropagation();
        expandPlayer();
    });
    $collapsed.on('click', function() {
        expandPlayer();
    });
    $closeBtn.on('click', function() {
        collapsePlayer();
    });

    function togglePlay() {
        if (isSimulated) {
            isPlaying = !isPlaying;
            updatePlayState();
            if (isPlaying) {
                startSimProgress();
                showSnackbar('开始播放：' + playlist[currentIndex].title, 'info');
            } else {
                stopSimProgress();
                showSnackbar('已暂停播放歌曲', 'info');
            }
            return;
        }

        if (audio.paused) {
            audio.play().then(function() {
                isPlaying = true;
                autoplayBlocked = false;
                pendingAutoplay = false;
                updatePlayState();
                showSnackbar('开始播放：' + playlist[currentIndex].title, 'info');
            }).catch(function(err) {
                if (err.name === 'NotAllowedError') {
                    autoplayBlocked = true;
                    pendingAutoplay = true;
                    bindFirstInteraction();
                    console.log('[MusicPlayer] 自动播放被浏览器阻止，等待用户首次交互...');
                    showSnackbar('点击页面任意位置开始播放音乐', 'info');
                } else {
                    console.error('播放失败:', err);
                    showSnackbar('音频加载失败，请检查文件路径', 'error');
                }
            });
        } else {
            audio.pause();
            isPlaying = false;
            updatePlayState();
            showSnackbar('已暂停播放歌曲', 'info');
        }
    }

    function updatePlayState() {
        $player.toggleClass('playing', isPlaying);
        $playIcon.text(isPlaying ? 'pause' : 'play_arrow');
        if ($waveBars.length) {
            $waveBars.toggleClass('paused', !isPlaying);
        }
    }

    $playBtn.on('click', function(e) {
        e.stopPropagation();
        togglePlay();
    });

    function updateProgressUI(percent) {
        $progressFill.css('width', percent + '%');
        if ($progressThumb.length) {
            $progressThumb.css('left', percent + '%');
        }
    }

    // 修改：增加 apply 参数，拖动中只更新 UI，不跳转
    function updateProgressFromEvent(e, apply) {
        const rect = $progressBar[0].getBoundingClientRect();
        const x = Math.max(0, Math.min(rect.width, e.clientX - rect.left));
        const percent = x / rect.width;
        dragPercent = percent;   // 保存当前百分比

        // 始终更新 UI（进度条、缩略图）
        updateProgressUI(percent * 100);

        let displayTime;
        if (isSimulated) {
            displayTime = Math.floor(percent * simDuration);
            if (apply) {
                simCurrentTime = displayTime;
            }
        } else if (audio.duration) {
            displayTime = percent * audio.duration;
            if (apply) {
                audio.currentTime = displayTime;
            }
        }
        $currentTime.text(formatTime(displayTime));
    }

    // 新增：真正执行跳转
    function applySeek(percent) {
        if (isSimulated) {
            simCurrentTime = Math.floor(percent * simDuration);
            $currentTime.text(formatTime(simCurrentTime));
        } else if (audio.duration) {
            audio.currentTime = percent * audio.duration;
            $currentTime.text(formatTime(audio.currentTime));
        }
    }

    // ========== 进度条拖动事件（修正光标） ==========
    $progressBar.on('mousedown', function(e) {
        isDragging = true;
        $(document.body).css('cursor', 'grabbing');
        $progressBar.css('user-select', 'none');
        updateProgressFromEvent(e, false);
    });

    $(document).on('mousemove', function(e) {
        if (isDragging) {
            $(document.body).css('cursor', 'grabbing');
            updateProgressFromEvent(e, false);
        }
        if (isVolumeDragging) {
            // 音量拖动时已经在 mousedown 设置了光标，这里不需要重复设置，但保持也行
            $(document.body).css('cursor', 'grabbing');
            updateVolumeFromEvent(e);
        }
    });

    $(document).on('mouseup', function() {
        if (isDragging) {
            applySeek(dragPercent);
            isDragging = false;
            $(document.body).css('cursor', '');
            $progressBar.css('user-select', '');
        }
        if (isVolumeDragging) {
            isVolumeDragging = false;
            $(document.body).css('cursor', '');
            $volumeBar.css('user-select', '');
            hideVolumeTooltip();
        }
    });
    // ============================================

    audio.addEventListener('timeupdate', function() {
        if (!isSimulated && !isDragging && audio.duration) {
            const percent = (audio.currentTime / audio.duration) * 100;
            updateProgressUI(percent);
            $currentTime.text(formatTime(audio.currentTime));
        }
    });

    audio.addEventListener('loadedmetadata', function() {
        if (!isSimulated && audio.duration) {
            const actualDuration = formatTime(audio.duration);
            $duration.text(actualDuration);
            playlist[currentIndex].fallbackDuration = actualDuration;
            $playlist.find('.music-playlist-item[data-index="' + currentIndex + '"] .playlist-duration').text(actualDuration);
        }
    });

    audio.addEventListener('ended', function() {
        if (!isSimulated) {
            if (isRepeat) {
                audio.currentTime = 0;
                audio.play();
            } else {
                nextTrack();
            }
        }
    });

    audio.addEventListener('error', function(e) {
        console.error('音频加载错误:', e);
        if (isSimulated) return;

        const track = playlist[currentIndex];
        if (!track || !track.src) {
            showSnackbar('无音频源，切换为演示模式', 'warning');
            enterSimulatedMode();
            return;
        }

        // 如果已经尝试过刷新，直接进入模拟模式
        if (track._refreshed) {
            showSnackbar('链接已刷新过但仍无效，切换为演示模式', 'warning');
            enterSimulatedMode();
            return;
        }

        // 有 source / rawId 且尚未在刷新中 → 请求后端刷新链接
        if (track.source && track.rawId && !track._refreshing) {
            track._refreshing = true;
            showSnackbar('链接过期，正在获取新链接...', 'info');

            fetch('/?action=refresh-music-url&source=' + encodeURIComponent(track.source) + '&raw_id=' + encodeURIComponent(track.rawId))
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    track._refreshing = false;
                    if (data.success && data.url) {
                        // 更新播放列表中的链接并加载
                        track.src = data.url;
                        track._refreshed = true;
                        loadTrack(currentIndex);
                        if (isPlaying) {
                            audio.play().catch(function() {
                                showSnackbar('新链接播放失败，切换为演示模式', 'warning');
                                enterSimulatedMode();
                            });
                        }
                    } else {
                        track._refreshed = true;
                        showSnackbar('链接刷新失败，切换为演示模式', 'error');
                        enterSimulatedMode();
                    }
                })
                .catch(function() {
                    track._refreshing = false;
                    track._refreshed = true;
                    showSnackbar('网络错误，刷新失败，切换为演示模式', 'error');
                    enterSimulatedMode();
                });
        } else {
            // 无法刷新（缺 source / rawId 或正在刷新中）
            showSnackbar('无法自动刷新链接，切换为演示模式，请手动刷新页面', 'warning');
            enterSimulatedMode();
        }
    });

    function formatTime(seconds) {
        if (isNaN(seconds)) return '0:00';
        const m = Math.floor(seconds / 60);
        const s = Math.floor(seconds % 60);
        return m + ':' + (s < 10 ? '0' : '') + s;
    }

    function startSimProgress() {
        stopSimProgress();
        simTimer = setInterval(function() {
            if (!isDragging) {
                simCurrentTime += 1;
                if (simCurrentTime >= simDuration) {
                    if (isRepeat) {
                        simCurrentTime = 0;
                    } else {
                        nextTrack();
                        return;
                    }
                }
                const percent = (simCurrentTime / simDuration) * 100;
                updateProgressUI(percent);
                $currentTime.text(formatTime(simCurrentTime));
            }
        }, 1000);
    }
    function stopSimProgress() {
        if (simTimer) {
            clearInterval(simTimer);
            simTimer = null;
        }
    }

    function setVolume(vol) {
        volume = Math.max(0, Math.min(1, vol));
        var volPercent = volume * 100;
        $volumeFill.css('width', volPercent + '%');
        if ($volumeThumb.length) {
            $volumeThumb.css('left', volPercent + '%');
        }
        if (!isSimulated) {
            audio.volume = volume;
        }
        updateVolumeIcon();
    }

    // ========== 音量条拖动事件（修正光标） ==========
    $volumeBar.on('mousedown', function(e) {
        isVolumeDragging = true;
        $(document.body).css('cursor', 'grabbing');
        $volumeBar.css('user-select', 'none');
        updateVolumeFromEvent(e);
    });
    // ===============================================

    function updateVolumeFromEvent(e) {
        const rect = $volumeBar[0].getBoundingClientRect();
        const x = Math.max(0, Math.min(rect.width, e.clientX - rect.left));
        const percent = (x / rect.width) * 100;
        setVolume(x / rect.width);
        showVolumeTooltip(percent, e.clientX, e.clientY);
    }

    let $volumeTooltip = null;
    function showVolumeTooltip(percent, mouseX, mouseY) {
        if (!$volumeTooltip) {
            $volumeTooltip = $('<div class="tooltip-bubble"></div>').css({
                position: 'fixed',
                zIndex: 2500,
                background: 'rgba(255,255,255,0.75)',
                backdropFilter: 'blur(18px) saturate(180%)',
                WebkitBackdropFilter: 'blur(18px) saturate(180%)',
                border: '1px solid rgba(255,255,255,0.5)',
                borderRadius: '12px',
                padding: '8px 14px',
                fontSize: '0.8rem',
                fontWeight: '500',
                color: '#0a0a0a',
                boxShadow: '0 12px 28px rgba(0,0,0,0.12)',
                pointerEvents: 'none',
                opacity: 0,
                transition: 'opacity 0.2s',
                whiteSpace: 'nowrap'
            });
            $('body').append($volumeTooltip);
        }
        var isDark = $html.attr('data-theme') === 'dark';
        $volumeTooltip.css({
            background: isDark ? 'rgba(15,23,42,0.85)' : 'rgba(255,255,255,0.75)',
            borderColor: isDark ? 'rgba(148,163,184,0.3)' : 'rgba(255,255,255,0.5)',
            color: isDark ? '#f1f5f9' : '#0a0a0a'
        });
        $volumeTooltip.text('音量: ' + Math.round(percent) + '%');
        var tw = $volumeTooltip[0].offsetWidth || 80;
        var th = $volumeTooltip[0].offsetHeight || 36;
        var tx = Math.max(8, Math.min(mouseX - tw / 2, window.innerWidth - tw - 8));
        var ty = Math.max(8, mouseY - th - 12);
        $volumeTooltip.css({ left: tx + 'px', top: ty + 'px', opacity: 1 });
    }

    function hideVolumeTooltip() {
        if ($volumeTooltip) {
            $volumeTooltip.css('opacity', 0);
            setTimeout(function() {
                if ($volumeTooltip && $volumeTooltip.css('opacity') === '0') {
                    $volumeTooltip.remove();
                    $volumeTooltip = null;
                }
            }, 250);
        }
    }

    $volBtn.on('click', function() {
        if (volume > 0) {
            audio._lastVolume = volume;
            setVolume(0);
        } else {
            setVolume(audio._lastVolume || 0.7);
        }
    });

    function updateVolumeIcon() {
        if (volume === 0) {
            $volIcon.text('volume_off');
        } else if (volume < 0.5) {
            $volIcon.text('volume_down');
        } else {
            $volIcon.text('volume_up');
        }
    }

    function renderPlaylist() {
        $playlist.empty();
        playlist.forEach(function(track, index) {
            const num = String(index + 1).padStart(2, '0');
            const $item = $(
                '<li class="music-playlist-item" data-index="' + index + '">' +
                    '<span class="playlist-number">' + num + '</span>' +
                    '<div class="playlist-info">' +
                        '<span class="playlist-title">' + escapeHtml(track.title) + '</span>' +
                        '<span class="playlist-artist">' + escapeHtml(track.artist) + '</span>' +
                    '</div>' +
                    '<span class="playlist-duration">' + track.fallbackDuration + '</span>' +
                    '<div class="playlist-playing-indicator">' +
                        '<span class="bar"></span>' +
                        '<span class="bar"></span>' +
                        '<span class="bar"></span>' +
                    '</div>' +
                '</li>'
            );
            $playlist.append($item);
        });
        $('#playlistCount').text(playlist.length);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function preloadAllMetadata() {
        playlist.forEach(function(track, index) {
            if (!track.src || index === currentIndex) return;
            const tempAudio = new Audio();
            tempAudio.preload = 'metadata';
            tempAudio.src = track.src;
            tempAudio.addEventListener('loadedmetadata', function() {
                if (tempAudio.duration && tempAudio.duration > 0) {
                    const actualDuration = formatTime(tempAudio.duration);
                    track.fallbackDuration = actualDuration;
                    $playlist.find('.music-playlist-item[data-index="' + index + '"] .playlist-duration').text(actualDuration);
                }
            });
            tempAudio.addEventListener('error', function() {});
        });
    }

    function enterSimulatedMode() {
        isSimulated = true;
        audio.pause();
        audio.src = '';
        simCurrentTime = 0;
        simDuration = parseDuration(playlist[currentIndex].fallbackDuration);
        $duration.text(playlist[currentIndex].fallbackDuration);
        updateProgressUI(0);
        $currentTime.text('0:00');
    }

    function enterRealMode(src) {
        isSimulated = false;
        stopSimProgress();
        audio.src = src;
        audio.volume = volume;
        audio.load();
        $duration.text(playlist[currentIndex].fallbackDuration);
    }

    function loadTrack(index) {
        currentIndex = index;
        const track = playlist[index];

        $trackTitle.text(track.title);
        $trackArtist.text(track.artist);
        $albumArt.attr('src', track.img);

        $playlist.find('.music-playlist-item').removeClass('active');
        $playlist.find('.music-playlist-item[data-index="' + index + '"]').addClass('active');

        if (track.src) {
            enterRealMode(track.src);
        } else {
            enterSimulatedMode();
        }

        updateProgressUI(0);
        $currentTime.text('0:00');
    }

    function parseDuration(timeStr) {
        const parts = timeStr.split(':');
        return parseInt(parts[0]) * 60 + parseInt(parts[1]);
    }

    function nextTrack() {
        let next;
        if (isShuffle) {
            next = Math.floor(Math.random() * playlist.length);
            if (playlist.length > 1 && next === currentIndex) {
                next = (next + 1) % playlist.length;
            }
        } else {
            next = (currentIndex + 1) % playlist.length;
        }
        loadTrack(next);

        if (isSimulated) {
            simCurrentTime = 0;
            updateProgressUI(0);
            $currentTime.text('0:00');
            startSimProgress();
            isPlaying = true;
            updatePlayState();
            showSnackbar('正在播放：' + playlist[currentIndex].title, 'info');
        } else {
            audio.currentTime = 0;
            audio.play().then(function() {
                isPlaying = true;
                autoplayBlocked = false;
                pendingAutoplay = false;
                updatePlayState();
                showSnackbar('正在播放：' + playlist[currentIndex].title, 'info');
            }).catch(function(err) {
                if (err.name === 'NotAllowedError') {
                    autoplayBlocked = true;
                    pendingAutoplay = true;
                    isPlaying = false;
                    updatePlayState();
                    bindFirstInteraction();
                }
                // 其他错误（403 等）交给 audio 'error' 事件统一处理
                // 不再直接进入模拟模式，让刷新逻辑有机会执行
            });
        }
    }

    function prevTrack() {
        const prev = (currentIndex - 1 + playlist.length) % playlist.length;
        loadTrack(prev);

        if (isSimulated) {
            simCurrentTime = 0;
            updateProgressUI(0);
            $currentTime.text('0:00');
            startSimProgress();
            isPlaying = true;
            updatePlayState();
            showSnackbar('正在播放：' + playlist[currentIndex].title, 'info');
        } else {
            audio.currentTime = 0;
            audio.play().then(function() {
                isPlaying = true;
                autoplayBlocked = false;
                pendingAutoplay = false;
                updatePlayState();
                showSnackbar('正在播放：' + playlist[currentIndex].title, 'info');
            }).catch(function(err) {
                if (err.name === 'NotAllowedError') {
                    autoplayBlocked = true;
                    pendingAutoplay = true;
                    isPlaying = false;
                    updatePlayState();
                    bindFirstInteraction();
                } else {
                    console.error('切歌播放失败:', err);
                    showSnackbar('音频加载失败，切换为演示模式', 'warning');
                    enterSimulatedMode();
                    startSimProgress();
                    isPlaying = true;
                    updatePlayState();
                }
            });
        }
    }

    $nextBtn.on('click', nextTrack);
    $prevBtn.on('click', prevTrack);

    $playlist.on('click', '.music-playlist-item', function() {
        const index = parseInt($(this).attr('data-index'));
        if (index === currentIndex) {
            togglePlay();
            return;
        }
        loadTrack(index);
        if (!isPlaying) {
            togglePlay();
        } else {
            if (isSimulated) {
                simCurrentTime = 0;
                updateProgressUI(0);
                $currentTime.text('0:00');
                startSimProgress();
            } else {
                audio.currentTime = 0;
                audio.play().catch(function(err) {
                    if (err.name === 'NotAllowedError') {
                        autoplayBlocked = true;
                        pendingAutoplay = true;
                        isPlaying = false;
                        updatePlayState();
                        bindFirstInteraction();
                    } else {
                        enterSimulatedMode();
                        startSimProgress();
                    }
                });
            }
            showSnackbar('正在播放：' + playlist[index].title, 'info');
        }
    });

    function handleAutoplay() {
        if (isRandomAutoplay) {
            const randomIndex = Math.floor(Math.random() * playlist.length);
            loadTrack(randomIndex);
            setTimeout(function() {
                if (!isPlaying) {
                    audio.play().then(function() {
                        isPlaying = true;
                        autoplayBlocked = false;
                        pendingAutoplay = false;
                        updatePlayState();
                        showSnackbar('随机自动播放：' + playlist[currentIndex].title, 'info');
                    }).catch(function(err) {
                        if (err.name === 'NotAllowedError') {
                            autoplayBlocked = true;
                            pendingAutoplay = true;
                            bindFirstInteraction();
                            console.log('[MusicPlayer] 随机自动播放被阻止，等待用户首次交互...');
                            showSnackbar('点击页面任意位置开始播放音乐', 'info');
                        } else {
                            console.error('随机自动播放失败:', err);
                            showSnackbar('音频加载失败，切换为演示模式', 'warning');
                            enterSimulatedMode();
                            startSimProgress();
                            isPlaying = true;
                            updatePlayState();
                        }
                    });
                }
            }, 300);
            return;
        }

        const autoIndex = playlist.findIndex(function(track) {
            return track.autoplay === true;
        });
        if (autoIndex !== -1) {
            if (autoIndex !== currentIndex) {
                loadTrack(autoIndex);
            }
            setTimeout(function() {
                if (!isPlaying) {
                    audio.play().then(function() {
                        isPlaying = true;
                        autoplayBlocked = false;
                        pendingAutoplay = false;
                        updatePlayState();
                        showSnackbar('开始播放：' + playlist[currentIndex].title, 'info');
                    }).catch(function(err) {
                        if (err.name === 'NotAllowedError') {
                            autoplayBlocked = true;
                            pendingAutoplay = true;
                            bindFirstInteraction();
                            console.log('[MusicPlayer] 自动播放被阻止，等待用户首次交互...');
                            showSnackbar('点击页面任意位置开始播放音乐', 'info');
                        } else {
                            console.error('自动播放失败:', err);
                            showSnackbar('音频加载失败，请检查文件路径', 'error');
                        }
                    });
                }
            }, 300);
        }
    }

    $shuffleBtn.on('click', function() {
        isShuffle = !isShuffle;
        $(this).toggleClass('active', isShuffle);
        if (isShuffle && isRepeat) {
            isRepeat = false;
            $repeatBtn.removeClass('active');
        }
    });

    $repeatBtn.on('click', function() {
        isRepeat = !isRepeat;
        $(this).toggleClass('active', isRepeat);
        if (isRepeat && isShuffle) {
            isShuffle = false;
            $shuffleBtn.removeClass('active');
        }
    });

    $(document).on('keydown', function(e) {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

        if (e.code === 'Space' && $player.hasClass('expanded')) {
            e.preventDefault();
            togglePlay();
        }
        if (e.code === 'ArrowRight' && e.ctrlKey && $player.hasClass('expanded')) {
            e.preventDefault();
            nextTrack();
        }
        if (e.code === 'ArrowLeft' && e.ctrlKey && $player.hasClass('expanded')) {
            e.preventDefault();
            prevTrack();
        }
    });

    $('#musicDownloadBtn').on('click', function () {
        window.open(playlist[currentIndex].src, '_blank');
    });

    function updateRandomAutoplayUI() {
        if ($randomAutoplayBtn.length) {
            $randomAutoplayBtn.toggleClass('active', isRandomAutoplay);
            if ($randomAutoplayIcon.length) {
                $randomAutoplayIcon.text(isRandomAutoplay ? 'motion_photos_auto' : 'motion_photos_off');
            }
        }
    }
    updateRandomAutoplayUI();

    $randomAutoplayBtn.on('click', function() {
        isRandomAutoplay = !isRandomAutoplay;
        localStorage.setItem('glassblog_music_random_autoplay', isRandomAutoplay);
        updateRandomAutoplayUI();
        if (isRandomAutoplay) {
            showSnackbar('已开启：访问页面自动随机播放歌曲（与指定自动播放互斥）', 'info');
        } else {
            showSnackbar('已关闭：访问页面自动随机播放歌曲', 'info');
        }
    });

    window.setMusicRandomAutoplay = function(enabled) {
        var newState = !!enabled;
        if (isRandomAutoplay === newState) return;
        isRandomAutoplay = newState;
        localStorage.setItem('glassblog_music_random_autoplay', isRandomAutoplay);
        updateRandomAutoplayUI();
        if (typeof showSnackbar === 'function') {
            showSnackbar(isRandomAutoplay ? '已开启：访问页面自动随机播放歌曲（与指定自动播放互斥）' : '已关闭：访问页面自动随机播放歌曲', 'info');
        }
    };
    window.getMusicRandomAutoplay = function() {
        return isRandomAutoplay;
    };

    renderPlaylist();
    preloadAllMetadata();
    loadTrack(0);
    setVolume(volume);
    handleAutoplay();
})();

    // ==================== 19. 搜索框跳转（pjax 外，一次性） ====================
    // 走 pjax + 先 record 热搜值（仅当能搜到结果时由 HotSearch::log 真正落盘）
    // 关键：走 footer 的 pjax 选区（a 标签委托）—— 而不是直接 $.pjax()，
    //       这样 pjax 失败时有标准 hard-reload 兜底，且和 footer 选区行为完全一致
    (function() {
        var input = document.getElementById('searchInput');
        var submitBtn = document.getElementById('searchSubmitBtn');
        if (!input || !submitBtn) return;

        // 复用脚本入口处已挂的全局 ghost a
        var ghost = document.getElementById('__pjax_search_trigger');
        if (!ghost) {
            // 兜底：极少数情况下入口处未挂上（如 script 加载顺序异常）
            ghost = document.createElement('a');
            ghost.id = '__pjax_search_trigger';
            ghost.style.display = 'none';
            ghost.setAttribute('aria-hidden', 'true');
            ghost.setAttribute('tabindex', '-1');
            ghost.href = '/';
            document.body.appendChild(ghost);
        }

        function recordSearch(keyword) {
            try {
                // 用 keepalive fetch，绕开 sendBeacon 的 POST+body 歧义；
                // 后端 functions.php 的 record 路径在 PHP 里 $_GET 优先级最高，query string 永远有效
                var url = '/?action=record&keyword=' + encodeURIComponent(keyword);
                if (navigator.sendBeacon) {
                    // 兼容：sendBeacon 对空 body + URL query 也能让 PHP 收到 $_GET
                    navigator.sendBeacon(url);
                } else {
                    fetch(url, { keepalive: true, method: 'GET' }).catch(function() {});
                }
            } catch (e) { /* 静默失败，不阻塞跳转 */ }
        }

        function doSearch() {
            var q = input.value.trim();
            if (!q) { input.focus(); return; }
            recordSearch(q);
            // 标记本次提交了一个搜索，closeSearch 时会预刷一次热搜
            window.__searchPendingRecord = true;
            // 走 Typecho 规范 search URL：__searchUrlTpl = siteUrl + '/index.php/search/{keyword}/'
            // 避免 ?s=xxx 被 Typecho 路由 302 跳到 /index.php/search/（丢 keyword） 的坑
            var tpl = (window.__searchUrlTpl || '/index.php/search/{keyword}/');
            ghost.href = tpl.replace('{keyword}', encodeURIComponent(q));
            ghost.click();
        }

        submitBtn.addEventListener('click', doSearch);
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                // 弹窗主块在 line 648 起已经有自己的 keydown 处理（含 addToHistory / 上下导航）
                // 若该处已 preventDefault，这里就不再重复提交
                if (e.defaultPrevented) return;
                doSearch();
            }
        });
    })();

    // ==================== PJAX 页面级重初始化（核心） ====================
    window.reinitAfterPjax = function() {
        // ==================== 搜索结果页初始化（PJAX 安全） ====================
        (function initSearchPage() {
            // 仅在搜索结果页执行
            if (!document.querySelector('.sr-wrapper')) return;

            // 1. 排序下拉：走 PJAX，不整页刷新
            var sortSelect = document.getElementById('srSort');
            if (sortSelect) {
                sortSelect.addEventListener('change', function() {
                    var url = this.value;
                    if (!url) return;
                    if (typeof $ !== 'undefined' && $.fn.pjax) {
                        $.pjax({ url: url, container: '#pjax-container', fragment: '#pjax-container', timeout: 10000, scrollTo: 0 });
                    } else {
                        window.location.href = url;
                    }
                });
            }

            // 2. 内联搜索框：回车跳转
            var srInput = document.getElementById('srSearchInput');
            var srClear = document.getElementById('srSearchClear');
            if (srInput && !srInput.dataset.srBound) {
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

                        // 记录热搜值（与 popup 共用 record 接口）
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
            }

            // 3. 修复分页链接：追加 category / tag / time / order 参数
            // Typecho pageNav 默认只保留 s，翻页会丢失筛选条件，此处 JS 补丁修复
            var params = new URLSearchParams(window.location.search);
            var extraKeys = ['category', 'tag', 'time', 'order'];
            var extraPairs = [];
            extraKeys.forEach(function(k) {
                var v = params.get(k);
                if (v) extraPairs.push(k + '=' + encodeURIComponent(v));
            });
            if (extraPairs.length) {
                var suffix = extraPairs.join('&');
                document.querySelectorAll('.sr-pagination a').forEach(function(a) {
                    var href = a.getAttribute('href');
                    if (!href || href.indexOf('javascript:') === 0) return;
                    // 避免重复追加
                    var alreadyHas = extraPairs.some(function(p) {
                        return href.indexOf(p.split('=')[0] + '=') !== -1;
                    });
                    if (alreadyHas) return;
                    var sep = href.indexOf('?') !== -1 ? '&' : '?';
                    a.setAttribute('href', href + sep + suffix);
                });
            }
        })();
        
        initToc();
        initTabs();
        initAccordion();
        //initComments();
        initCarousel();

        // 归档页
        // 归档页搜索框：form submit 拦截 → 走 pjax + 记录热搜
        (function initArchiveSearch() {
            if (!document.querySelector('.archive-wrapper')) return;
            var form = document.getElementById('search');
            if (!form || form.dataset.archiveBound) return;
            form.dataset.archiveBound = '1';
            var input = form.querySelector('input[name="s"]');

            // 复用 popup 的全局 ghost a；不存在就建一个（pjax 不会跨页删 body 元素，但保险起见）
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

            function recordSearch(keyword) {
                try {
                    var url = '/?action=record&keyword=' + encodeURIComponent(keyword);
                    if (navigator.sendBeacon) {
                        navigator.sendBeacon(url);
                    } else {
                        fetch(url, { keepalive: true, method: 'GET' }).catch(function() {});
                    }
                } catch (e) { /* 静默失败，不阻塞跳转 */ }
            }

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                if (!input) return;
                var val = (input.value || '').trim();
                if (!val) { input.focus(); return; }
                recordSearch(val);
                // 标记本次提交了一个搜索，closeSearch 时会预刷一次热搜
                window.__searchPendingRecord = true;
                // 走 Typecho 规范 search URL：__searchUrlTpl = siteUrl + '/index.php/search/{keyword}/'
                // 避免 ?s=xxx 被 Typecho 路由 302 跳到 /index.php/search/（丢 keyword） 的坑
                var tpl = (window.__searchUrlTpl || '/index.php/search/{keyword}/');
                ghost.href = tpl.replace('{keyword}', encodeURIComponent(val));
                ghost.click();
            });
        })();

        if (window.__archiveData) {
            initArchiveHeatmap(window.__archiveData.heatmap);
            initArchiveRadarChart('#radarCategoriesChart', window.__archiveData.categories);
            initArchiveRadarChart('#radarTagsChart', window.__archiveData.tags);
            initArchiveRadarChart('#radarCategoriesChartMobile', window.__archiveData.categories);
            initArchiveRadarChart('#radarTagsChartMobile', window.__archiveData.tags);

            // 归档页移动端雷达图 Tab 切换重绘
            $('.archive-radar-tab-mobile .tab-btn').off('click.archiveradar').on('click.archiveradar', function() {
                var targetId = $(this).attr('data-tab');
                var canvasId = targetId === 'radarTabCategories' ? '#radarCategoriesChartMobile' : '#radarTagsChartMobile';
                requestAnimationFrame(function() {
                    var chart = window._radarCharts && window._radarCharts[canvasId];
                    if (chart && chart.setupCanvas && chart.render) {
                        chart.setupCanvas();
                        chart.render();
                    } else {
                        $(window).trigger('resize');
                    }
                });
            });
        }

        initFancybox();
        initCodeBlocks();
        initComments();
        // GitHub 短代码卡片：所有页面 reinit 时都跑，
        // 这样从非 post/page 页面 pjax 跳到带 [github] 短代码的文章时也能正常替换
        loadGhCards();
        // 重置阅读进度
        $('#readingProgress').css('width', '0%');
        // 触发滚动更新
        $(window).trigger('scroll');
    };
    
    // ==================== PJAX Meta 同步 ====================
    (function() {
        /**
         * 从 PJAX 响应的完整 HTML 中提取并同步 <head> 中的动态 SEO 标签
         */
        function syncMetaFromResponse(responseText) {
            if (!responseText) return;
            
            var parser = new DOMParser();
            var newDoc = parser.parseFromString(responseText, 'text/html');
            if (!newDoc || !newDoc.head) return;

            var currentHead = document.head;

            // 1. 同步 Title
            var newTitle = newDoc.querySelector('title');
            if (newTitle) {
                var curTitle = currentHead.querySelector('title');
                if (curTitle) {
                    curTitle.textContent = newTitle.textContent;
                }
            }

            // 2. 定义需要同步的 SEO 相关标签选择器
            // 注意：charset / viewport / CSP / 静态资源等不列入，避免重复或冲突
            var selectors = [
                'meta[name="robots"]',
                'meta[name="keywords"]',
                'meta[name="description"]',
                'meta[name="author"]',
                'meta[name="copyright"]',
                'meta[property^="og:"]',
                'meta[property^="article:"]',
                'meta[property^="bytedance:"]',
                'meta[property="twitter:creator"]',
                'meta[name^="twitter:"]',
                'link[rel="canonical"]',
                'link[rel="author"]',
                'link[rel="publisher"]',
                'link[rel="me"]'
            ];

            // 3. 移除旧标签
            selectors.forEach(function(sel) {
                var nodes = currentHead.querySelectorAll(sel);
                for (var i = 0; i < nodes.length; i++) {
                    nodes[i].remove();
                }
            });

            // 4. 插入新标签
            selectors.forEach(function(sel) {
                var nodes = newDoc.head.querySelectorAll(sel);
                for (var i = 0; i < nodes.length; i++) {
                    currentHead.appendChild(nodes[i].cloneNode(true));
                }
            });
        }

        // 监听 PJAX 成功事件，从原始 XHR 响应中同步 Meta
        $(document).on('pjax:success', function(event, data, status, xhr) {
            if (xhr && xhr.responseText) {
                syncMetaFromResponse(xhr.responseText);
            }
        });
    })();

    // 首次加载执行所有页面级初始化
    window.reinitAfterPjax();
});