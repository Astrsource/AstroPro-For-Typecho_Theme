<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

use Typecho\Db;

/**
 * 主题内容处理类 — 短代码解析器
 *
 * 支持的短代码：
 *   [divider] / [divider style="gradient|dashed|dotted"]
 *   [button type="primary" url="..." icon="bolt"]文字[/button]
 *   [button type="icon" icon="favorite"]
 *   [tabs]...[tab title="..." icon="..." active]...[/tab]...[/tabs]
 *   [accordion mode="single|multi"]...[item title="..." icon="..." active]...[/item]...[/accordion]
 *   [timeline]...[event date="..." icon="..." title="..."]...[/event]...[/timeline]
 *   [radio name="group" label="..." value="..." checked]
 *   [checkbox label="..." checked]
 *   [progress label="..." value="85" color="accent" striped]
 *   [github repo="owner/repo"]
 *   [cloud name="..." url="..." size="..." date="..." code="..." icon="..."]
 *   [post="cid"] / [page="cid"]
 *   [info title="..."]内容[/info]
 *   [tip title="..."]内容[/tip]
 *   [danger title="..."]内容[/danger]
 *   [warning title="..."]内容[/warning]
 */
class ContentFilter
{
    private static int $counter = 0;
    private static array $githubCache = [];
    private static array $contentCache = [];

    private static array $calloutConfig = [
        'info'    => ['icon' => 'info',      'label' => '信息'],
        'tip'     => ['icon' => 'lightbulb', 'label' => '提示'],
        'danger'  => ['icon' => 'error',     'label' => '危险'],
        'warning' => ['icon' => 'warning',   'label' => '警告'],
    ];

    public static function parseContent($content, $widget, $lastResult = null)
    {
        $content = self::handleShortcodes($content);
        $content = self::addHeadingIds($content);
        return $content;
    }

    private static function handleShortcodes($html)
    {
        // 1. 先处理 <pre> 代码块（添加 code-block 包裹层）
        $html = self::wrapPreCode($html);

        // 2. 保护代码块（防止短代码解析器处理代码块内的短代码语法）
        $protected = [];

        // 保护 <div class="code-block"> 块
        $html = preg_replace_callback(
            '/<div class="code-block">.*?<\/pre><\/div>/is',
            function ($matches) use (&$protected) {
                $id = count($protected);
                $protected[$id] = $matches[0];
                return '<!--PROTECTED_' . $id . '-->';
            },
            $html
        );

        // 保护行内 <code> 标签
        $html = preg_replace_callback(
            '/<code\b[^>]*>.*?<\/code>/is',
            function ($matches) use (&$protected) {
                $id = count($protected);
                $protected[$id] = $matches[0];
                return '<!--PROTECTED_' . $id . '-->';
            },
            $html
        );

        // 3. 移除短代码标签之间的 <br>（Typecho Markdown 解析器自动添加）
        $html = preg_replace('/\](\s*<br\s*\/?>\s*)+\[/i', '][', $html);

        // 4. 其他全局 HTML 标签改造
        $html = self::wrapTable($html);
        $html = self::styleBlockquote($html);
        $html = self::wrapImage($html);

        // 5. 短代码解析（支持嵌套的优先处理，由内向外逐层剥离）
        $html = self::parseTabs($html);
        $html = self::parseAccordion($html);
        $html = self::parseTimeline($html);
        $html = self::parseCallout($html);

        // 6. 不支持嵌套的短代码
        $html = self::parseDivider($html);
        $html = self::parseButton($html);
        $html = self::parseIconButton($html);
        $html = self::parseRadio($html);
        $html = self::parseCheckbox($html);
        $html = self::parseProgress($html);
        $html = self::parseGithub($html);
        $html = self::parseCloud($html);
        $html = self::parsePostCard($html);
        $html = self::parsePageCard($html);
        $html = self::addHrClass($html);

        // 7. 恢复保护的代码块
        foreach ($protected as $id => $block) {
            $html = str_replace('<!--PROTECTED_' . $id . '-->', $block, $html);
        }

        // 8. 给所有带 href 且无 target 的 a 标签添加 target="_blank"
        $html = self::addTargetBlank($html);

        return $html;
    }

    /* ============================================================
     *  全局 HTML 标签改造
     * ============================================================ */

    private static function extractLangFromClass(string $classStr): string
    {
        if (preg_match('/(?:language|lang)-([a-zA-Z0-9_+#-]+)/i', $classStr, $m)) {
            return $m[1];
        }
        return '';
    }

    private static function wrapPreCode(string $html): string
    {
        return preg_replace_callback(
            '/<pre\b([^>]*)>(.*?)<\/pre>/is',
            function ($matches) {
                $preAttrs = $matches[1];
                $inner = $matches[2];

                $lang = '';
                if (preg_match('/class\s*=\s*(["\'])(.*?)\1/i', $preAttrs, $m)) {
                    $lang = self::extractLangFromClass($m[2]);
                }
                if ($lang === '' && preg_match('/<code\b[^>]*class\s*=\s*(["\'])(.*?)\1/i', $inner, $m)) {
                    $lang = self::extractLangFromClass($m[2]);
                }
                $lang = htmlspecialchars($lang, ENT_QUOTES, 'UTF-8');

                $header = '<div class="code-header">'
                    . '<span class="code-dots">'
                    . '<span class="code-dot dot-red"></span>'
                    . '<span class="code-dot dot-yellow"></span>'
                    . '<span class="code-dot dot-green"></span>'
                    . '</span>'
                    . '<span class="code-lang-text">' . $lang . '</span>'
                    . '<button class="code-copy-btn" data-tooltip="复制代码">'
                    . '<span class="material-icons" style="font-size: 12px;">content_copy</span>'
                    . '</button>'
                    . '</div>';

                return '<div class="code-block">' . $header . '<pre' . $preAttrs . '>' . $inner . '</pre></div>';
            },
            $html
        );
    }

    private static function wrapTable(string $html): string
    {
        return preg_replace_callback(
            '/<table\b([^>]*)>(.*?)<\/table>/is',
            function ($matches) {
                $attrs = $matches[1];
                $inner = $matches[2];

                if (preg_match('/class\s*=\s*(["\'])(.*?)\1/i', $attrs, $classMatch)) {
                    $oldClass = $classMatch[2];
                    if (strpos($oldClass, 'styled-table') === false) {
                        $newClass = $oldClass . ' styled-table';
                        $attrs = str_replace($classMatch[0], 'class="' . $newClass . '"', $attrs);
                    }
                } else {
                    $attrs .= ' class="styled-table"';
                }

                return '<div class="table-wrapper"><table' . $attrs . '>' . $inner . '</table></div>';
            },
            $html
        );
    }

    private static function styleBlockquote(string $html): string
    {
        return preg_replace_callback(
            '/<blockquote\b([^>]*)>(.*?)<\/blockquote>/is',
            function ($matches) {
                $attrs = $matches[1];
                $inner = $matches[2];

                if (preg_match('/class\s*=\s*(["\'])(.*?)\1/i', $attrs, $classMatch)) {
                    $oldClass = $classMatch[2];
                    if (strpos($oldClass, 'styled-blockquote') === false) {
                        $newClass = $oldClass . ' styled-blockquote';
                        $attrs = str_replace($classMatch[0], 'class="' . $newClass . '"', $attrs);
                    }
                } else {
                    $attrs .= ' class="styled-blockquote"';
                }

                return '<blockquote' . $attrs . '>' . $inner . '</blockquote>';
            },
            $html
        );
    }

    private static function wrapImage(string $html): string
    {
        return preg_replace_callback(
            '/<img\b([^>]*)>/i',
            function ($matches) {
                $attrs = $matches[1];
                $caption = '';

                if (preg_match('/title\s*=\s*(["\'])(.*?)\1/i', $attrs, $tMatch)) {
                    $caption = $tMatch[2];
                } elseif (preg_match('/alt\s*=\s*(["\'])(.*?)\1/i', $attrs, $aMatch)) {
                    $caption = $aMatch[2];
                }

                $caption = htmlspecialchars($caption, ENT_QUOTES, 'UTF-8');
                $captionHtml = $caption !== '' ? '<figcaption>' . $caption . '</figcaption>' : '<figcaption></figcaption>';

                return '<figure class="post-figure">'
                    . '<img' . $attrs . '>'
                    . $captionHtml
                    . '</figure>';
            },
            $html
        );
    }

    private static function trimBr(string $content): string
    {
        return preg_replace('/^(\s*<br\s*\/?>\s*)+|(\s*<br\s*\/?>\s*)+$/i', '', trim($content));
    }

    /* ============================================================
     *  1. 分割线
     * ============================================================ */

    private static function parseDivider(string $html): string
    {
        return preg_replace_callback(
            '/\[divider\s*([^\]]*)\]/is',
            function ($matches) {
                $attrs = self::parseAttributes($matches[1]);
                $style = !empty($attrs['style']) ? htmlspecialchars(trim($attrs['style']), ENT_QUOTES, 'UTF-8') : 'glass';
                $valid = ['glass', 'gradient', 'dashed', 'dotted'];
                if (!in_array($style, $valid, true)) {
                    $style = 'glass';
                }
                $class = $style !== 'glass' ? "glass-divider {$style}" : 'glass-divider';
                return '<hr class="' . $class . '">';
            },
            $html
        );
    }

    /* ============================================================
     *  2. 按钮
     * ============================================================ */

    private static function parseButton(string $html): string
    {
        return preg_replace_callback(
            '/\[button\s+([^\]]*)\](.*?)\[\/button\]/is',
            function ($matches) {
                $attrs = self::parseAttributes($matches[1]);
                $text  = trim($matches[2]);

                $type = in_array($attrs['type'] ?? '', ['primary', 'secondary', 'outline', 'ghost', 'gradient'], true)
                    ? $attrs['type']
                    : 'primary';
                $url  = !empty($attrs['url']) ? htmlspecialchars($attrs['url'], ENT_QUOTES, 'UTF-8') : '';
                $icon = !empty($attrs['icon']) ? htmlspecialchars($attrs['icon'], ENT_QUOTES, 'UTF-8') : '';

                $iconHtml = $icon !== ''
                    ? '<span class="material-icons" aria-hidden="true">' . $icon . '</span>'
                    : '';

                $tag = $url !== '' ? 'a' : 'button';
                $href = $url !== '' ? ' href="' . $url . '"' : '';
                $typeAttr = $tag === 'button' ? ' type="button"' : '';
                $spacer = ($iconHtml !== '' && $text !== '') ? ' ' : '';

                return '<' . $tag . $href . $typeAttr . ' class="btn btn-' . $type . '">'
                    . $iconHtml . $spacer . $text . '</' . $tag . '>';
            },
            $html
        );
    }

    private static function parseIconButton(string $html): string
    {
        return preg_replace_callback(
            '/\[button\s+([^\]]*)\]/is',
            function ($matches) {
                $attrs = self::parseAttributes($matches[1]);
                if (($attrs['type'] ?? '') !== 'icon') {
                    return $matches[0];
                }
                $icon = !empty($attrs['icon']) ? htmlspecialchars($attrs['icon'], ENT_QUOTES, 'UTF-8') : 'favorite';
                $url  = !empty($attrs['url']) ? htmlspecialchars($attrs['url'], ENT_QUOTES, 'UTF-8') : '';
                $iconHtml = '<span class="material-icons" aria-hidden="true">' . $icon . '</span>';
                if ($url !== '') {
                    return '<a href="' . $url . '" class="btn btn-icon">' . $iconHtml . '</a>';
                }
                return '<button type="button" class="btn btn-icon">' . $iconHtml . '</button>';
            },
            $html
        );
    }

    /* ============================================================
     *  3. 选项卡（支持嵌套）
     * ============================================================ */

    private static function parseTabs(string $html): string
    {
        while (true) {
            // 匹配最内层的 [tabs]...[/tabs]
            if (!preg_match('/\[tabs\b([^\]]*)\]((?:(?!\[tabs\b).)*?)\[\/tabs\]/is', $html, $matches)) {
                break;
            }

            $attrStr = $matches[1];
            $inner   = $matches[2];
            $id      = 'tabs-' . (self::$counter++);

            // 提取 [tab] 子项：禁止跨越其他 [tab 开头，确保嵌套安全
            preg_match_all(
                '/\[tab\s+([^\]]*)\]((?:(?!\[tab\b).)*?)\[\/tab\]/is',
                $inner,
                $tabMatches,
                PREG_SET_ORDER
            );

            if (empty($tabMatches)) {
                $html = self::replaceOnce($html, $matches[0], '');
                continue;
            }

            $navItems = [];
            $panels   = [];
            $index    = 0;

            foreach ($tabMatches as $tab) {
                $tAttrs   = self::parseAttributes($tab[1]);
                $tContent = self::trimBr($tab[2]);
                // 兼容 title / name 两种属性
                $tTitle   = htmlspecialchars($tAttrs['title'] ?? $tAttrs['name'] ?? 'Tab', ENT_QUOTES, 'UTF-8');
                $tIcon    = !empty($tAttrs['icon']) ? htmlspecialchars($tAttrs['icon'], ENT_QUOTES, 'UTF-8') : '';
                $active   = isset($tAttrs['active']);
                $tabId    = $id . '-tab' . $index;

                $iconHtml = $tIcon !== ''
                    ? '<span class="material-icons" aria-hidden="true">' . $tIcon . '</span> '
                    : '';

                $navClass = $active ? 'tab-btn active' : 'tab-btn';
                $navItems[] = '<button class="' . $navClass . '" data-tab="' . $tabId . '" role="tab" type="button">'
                    . $iconHtml . $tTitle . '</button>';

                $panelClass = $active ? 'tab-panel active' : 'tab-panel';
                $panels[] = '<div class="' . $panelClass . '" id="' . $tabId . '" role="tabpanel">' . $tContent . '</div>';
                $index++;
            }

            $replacement = '<div class="tab-container" data-tab-container="" id="' . $id . '">'
                . '<div class="tab-nav">' . implode('', $navItems) . '</div>'
                . '<div class="tab-panels">' . implode('', $panels) . '</div>'
                . '</div>';

            $html = self::replaceOnce($html, $matches[0], $replacement);
        }

        return $html;
    }

    /* ============================================================
     *  4. 手风琴（支持嵌套）
     * ============================================================ */

    private static function parseAccordion(string $html): string
    {
        while (true) {
            // 匹配最内层的 [accordion]...[/accordion]
            if (!preg_match('/\[accordion\b([^\]]*)\]((?:(?!\[accordion\b).)*?)\[\/accordion\]/is', $html, $matches)) {
                break;
            }

            $attrs = self::parseAttributes($matches[1]);
            $inner = $matches[2];
            $mode  = ($attrs['mode'] ?? 'single') === 'multi' ? 'multi' : 'single';

            // 提取 [item] 子项：禁止跨越其他 [item 开头
            preg_match_all(
                '/\[item\s+([^\]]*)\]((?:(?!\[item\b).)*?)\[\/item\]/is',
                $inner,
                $itemMatches,
                PREG_SET_ORDER
            );

            if (empty($itemMatches)) {
                $html = self::replaceOnce($html, $matches[0], '');
                continue;
            }

            $items = [];
            foreach ($itemMatches as $item) {
                $iAttrs   = self::parseAttributes($item[1]);
                $iContent = self::wrapContent(self::trimBr($item[2]));
                $title    = htmlspecialchars($iAttrs['title'] ?? 'Item', ENT_QUOTES, 'UTF-8');
                $icon     = !empty($iAttrs['icon']) ? htmlspecialchars($iAttrs['icon'], ENT_QUOTES, 'UTF-8') : '';
                $active   = isset($iAttrs['active']);

                $iconHtml = $icon !== ''
                    ? '<span class="material-icons" aria-hidden="true">' . $icon . '</span> '
                    : '';

                $expanded = $active ? 'true' : 'false';

                $items[] = '<div class="accordion-item">'
                    . '<button aria-expanded="' . $expanded . '" class="accordion-header" type="button">'
                    . $iconHtml . $title
                    . '<span class="material-icons accordion-arrow">expand_more</span>'
                    . '</button>'
                    . '<div class="accordion-panel">' . $iContent . '</div>'
                    . '</div>';
            }

            $replacement = '<div class="accordion-group" data-accordion="' . $mode . '">'
                . implode('', $items)
                . '</div>';

            $html = self::replaceOnce($html, $matches[0], $replacement);
        }

        return $html;
    }

    /* ============================================================
     *  5. 时间线（支持嵌套）
     * ============================================================ */

    private static function parseTimeline(string $html): string
    {
        while (true) {
            if (!preg_match('/\[timeline\b([^\]]*)\]((?:(?!\[timeline\b).)*?)\[\/timeline\]/is', $html, $matches)) {
                break;
            }

            $inner = $matches[2];

            // 提取 [event] 子项：禁止跨越其他 [event 开头
            preg_match_all(
                '/\[event\s+([^\]]*)\]((?:(?!\[event\b).)*?)\[\/event\]/is',
                $inner,
                $evMatches,
                PREG_SET_ORDER
            );

            if (empty($evMatches)) {
                $html = self::replaceOnce($html, $matches[0], '');
                continue;
            }

            $items = [];
            foreach ($evMatches as $ev) {
                $eAttrs   = self::parseAttributes($ev[1]);
                $eContent = self::wrapContent(self::trimBr($ev[2]));
                $date     = htmlspecialchars($eAttrs['date'] ?? '', ENT_QUOTES, 'UTF-8');
                $icon     = !empty($eAttrs['icon']) ? htmlspecialchars($eAttrs['icon'], ENT_QUOTES, 'UTF-8') : 'event';
                $title    = htmlspecialchars($eAttrs['title'] ?? '', ENT_QUOTES, 'UTF-8');

                $items[] = '<div class="timeline-item">'
                    . '<div class="timeline-icon"><span class="material-icons" aria-hidden="true">' . $icon . '</span></div>'
                    . '<div class="timeline-content">'
                    . ($date ? '<time class="timeline-date">' . $date . '</time>' : '')
                    . ($title ? '<h4>' . $title . '</h4>' : '')
                    . $eContent
                    . '</div>'
                    . '</div>';
            }

            $replacement = '<div class="timeline-container"><div class="timeline">'
                . implode('', $items)
                . '</div></div>';

            $html = self::replaceOnce($html, $matches[0], $replacement);
        }

        return $html;
    }

    /* ============================================================
     *  6. 单选/多选框
     * ============================================================ */

    private static function parseRadio(string $html): string
    {
        return preg_replace_callback(
            '/\[radio\s+([^\]]*)\]/is',
            function ($matches) {
                $attrs = self::parseAttributes($matches[1]);
                $name  = htmlspecialchars($attrs['name'] ?? 'radio-group', ENT_QUOTES, 'UTF-8');
                $label = htmlspecialchars($attrs['label'] ?? '', ENT_QUOTES, 'UTF-8');
                $value = htmlspecialchars($attrs['value'] ?? '', ENT_QUOTES, 'UTF-8');
                $checked = isset($attrs['checked']) ? ' checked' : '';

                return '<label class="glass-radio">'
                    . '<input type="radio" name="' . $name . '" value="' . $value . '"' . $checked . '>'
                    . '<span class="glass-radio-indicator"></span>'
                    . '<span class="glass-radio-text">' . $label . '</span>'
                    . '</label>';
            },
            $html
        );
    }

    private static function parseCheckbox(string $html): string
    {
        return preg_replace_callback(
            '/\[checkbox\s+([^\]]*)\]/is',
            function ($matches) {
                $attrs = self::parseAttributes($matches[1]);
                $label = htmlspecialchars($attrs['label'] ?? '', ENT_QUOTES, 'UTF-8');
                $checked = isset($attrs['checked']) ? ' checked' : '';

                return '<label class="glass-checkbox">'
                    . '<input type="checkbox"' . $checked . '>'
                    . '<span class="glass-check-indicator"><span class="material-icons" aria-hidden="true">check</span></span>'
                    . '<span class="glass-check-text">' . $label . '</span>'
                    . '</label>';
            },
            $html
        );
    }

    /* ============================================================
     *  7. 进度条
     * ============================================================ */

    private static function parseProgress(string $html): string
    {
        return preg_replace_callback(
            '/\[progress\s+([^\]]*)\]/is',
            function ($matches) {
                $attrs  = self::parseAttributes($matches[1]);
                $label  = htmlspecialchars($attrs['label'] ?? '', ENT_QUOTES, 'UTF-8');
                $value  = (int) ($attrs['value'] ?? 0);
                $value  = max(0, min(100, $value));
                $color  = in_array($attrs['color'] ?? '', ['accent', 'gradient', 'success', 'warning'], true)
                    ? $attrs['color']
                    : 'accent';
                $striped = isset($attrs['striped']) ? ' striped' : '';
                $small   = isset($attrs['small']) ? ' glass-progress-sm' : '';

                return '<div class="glass-progress-wrap">'
                    . '<div class="glass-progress-info"><span>' . $label . '</span><span>' . $value . '%</span></div>'
                    . '<div class="glass-progress' . $small . '">'
                    . '<div class="glass-progress-fill' . $striped . '" data-color="' . $color . '" style="--progress: ' . $value . '%;"></div>'
                    . '</div>'
                    . '</div>';
            },
            $html
        );
    }

    /* ============================================================
     *  8. GitHub 项目卡片（jQuery 前端异步渲染）
     * ============================================================ */

    private static function parseGithub(string $html): string
    {
        return preg_replace_callback(
            '/\[github\s+repo\s*=\s*(["\'])(.*?)\1\s*\]/is',
            function ($matches) {
                $repo = htmlspecialchars(trim($matches[2]), ENT_QUOTES, 'UTF-8');
                if (!str_contains($repo, '/')) {
                    return '<div class="gh-project-card" style="border-color:var(--error);">'
                        . '<p class="gh-repo-desc">GitHub 仓库格式错误，应为 owner/repo</p></div>';
                }

                // 后端零阻塞：只输出占位 div + 骨架屏，由前端 jQuery 异步填充
                return '<div class="gh-project-card gh-async" data-repo="' . $repo . '">'
                    . '<div class="gh-skeleton">'
                    . '<div class="gh-skeleton-head">'
                    . '<div class="gh-skeleton-ico"></div>'
                    . '<div class="gh-skeleton-lines"><div></div><div class="short"></div></div>'
                    . '</div>'
                    . '<div class="gh-skeleton-body"><div></div><div class="short"></div></div>'
                    . '<div class="gh-skeleton-foot"><div class="short"></div><div class="short"></div></div>'
                    . '</div>'
                    . '</div>';
            },
            $html
        );
    }

    /* ============================================================
     *  9. 网盘引用卡片
     * ============================================================ */

    private static function parseCloud(string $html): string
    {
        return preg_replace_callback(
            '/\[cloud\s+([^\]]*)\]/is',
            function ($matches) {
                $attrs  = self::parseAttributes($matches[1]);
                $name   = htmlspecialchars($attrs['name'] ?? '文件', ENT_QUOTES, 'UTF-8');
                $url    = htmlspecialchars($attrs['url'] ?? '#', ENT_QUOTES, 'UTF-8');
                $size   = htmlspecialchars($attrs['size'] ?? '', ENT_QUOTES, 'UTF-8');
                $date   = htmlspecialchars($attrs['date'] ?? '', ENT_QUOTES, 'UTF-8');
                $code   = htmlspecialchars($attrs['code'] ?? '', ENT_QUOTES, 'UTF-8');
                $icon   = htmlspecialchars($attrs['icon'] ?? 'folder', ENT_QUOTES, 'UTF-8');
                $id     = 'refCode-' . (self::$counter++);

                $metaHtml = '';
                if ($size !== '' || $date !== '') {
                    $metaParts = [];
                    if ($size !== '') {
                        $metaParts[] = '<span class="cloud-ref-size">' . $size . '</span>';
                    }
                    if ($date !== '') {
                        $metaParts[] = '<span class="cloud-ref-date"><time datetime="' . $date . '">' . $date . '</time></span>';
                    }
                    $metaHtml = '<div class="cloud-ref-meta">' . implode('', $metaParts) . '</div>';
                }

                $codeHtml = '';
                if ($code !== '') {
                    $codeHtml = '<div class="cloud-ref-code-row">'
                        . '<span class="cloud-ref-code-label">提取码</span>'
                        . '<span class="cloud-ref-code" id="' . $id . '">' . $code . '</span>'
                        . '<button class="cloud-ref-copy-btn" onclick="copyRefCode(event, \'' . $id . '\')" title="复制提取码" type="button">'
                        . '<span class="material-icons" aria-hidden="true">content_copy</span>'
                        . '</button>'
                        . '</div>';
                }

                return '<a class="cloud-ref-card" href="' . $url . '" rel="noopener" target="_blank">'
                    . '<div class="cloud-ref-icon"><span class="material-icons" aria-hidden="true">' . $icon . '</span></div>'
                    . '<div class="cloud-ref-body">'
                    . '<h4 class="cloud-ref-title">' . $name . '</h4>'
                    . $metaHtml
                    . $codeHtml
                    . '</div>'
                    . '<div class="cloud-ref-arrow"><span class="material-icons" aria-hidden="true">open_in_new</span></div>'
                    . '</a>';
            },
            $html
        );
    }

    /* ============================================================
     *  10. 文章/页面卡片（自动获取）
     * ============================================================ */

    private static function parsePostCard(string $html): string
    {
        return preg_replace_callback(
            '/\[post\s*=\s*["\']?(\d+)["\']?\s*\]/is',
            function ($matches) {
                return self::buildContentCard((int) $matches[1], 'post');
            },
            $html
        );
    }

    private static function parsePageCard(string $html): string
    {
        return preg_replace_callback(
            '/\[page\s*=\s*["\']?(\d+)["\']?\s*\]/is',
            function ($matches) {
                return self::buildContentCard((int) $matches[1], 'page');
            },
            $html
        );
    }

    /* ============================================================
     *  11. 提示短代码（支持嵌套）
     * ============================================================ */

    private static function parseCallout(string $html): string
    {
        foreach (array_keys(self::$calloutConfig) as $type) {
            while (true) {
                $openTag = '\[' . $type . '\b';
                $closeTag = '\[\/' . $type . '\]';
                $pattern = '/' . $openTag . '([^\]]*)\]((?:(?!' . $openTag . ').)*?)' . $closeTag . '/is';

                if (!preg_match($pattern, $html, $matches)) {
                    break;
                }

                $attrs   = self::parseAttributes($matches[1]);
                $content = self::trimBr($matches[2]);
                $config  = self::$calloutConfig[$type];
                $title   = !empty($attrs['title']) ? htmlspecialchars($attrs['title'], ENT_QUOTES, 'UTF-8') : '';
                $icon    = !empty($attrs['icon']) ? htmlspecialchars($attrs['icon'], ENT_QUOTES, 'UTF-8') : $config['icon'];

                $titleHtml = $title !== '' ? '<strong>' . $title . '</strong> ' : '';
                $bodyHtml = '<p>' . $content . '</p>';

                $replacement = '<div class="callout callout-' . $type . '">'
                    . '<div class="callout-icon"><span class="material-icons" aria-hidden="true">' . $icon . '</span></div>'
                    . '<div class="callout-body">' . $titleHtml . $bodyHtml . '</div>'
                    . '</div>';

                $html = self::replaceOnce($html, $matches[0], $replacement);
            }
        }
        return $html;
    }

    /* ============================================================
     *  12. 所有 <hr> 默认添加 glass-divider 类
     * ============================================================ */

    private static function addHrClass(string $html): string
    {
        return preg_replace_callback(
            '/<hr\b([^>]*)>/i',
            function ($matches) {
                $attrs = $matches[1];
                if (preg_match('/class\s*=\s*(["\'])(.*?)\1/i', $attrs, $classMatch)) {
                    $oldClass = $classMatch[2];
                    if (strpos($oldClass, 'glass-divider') === false) {
                        $newClass = $oldClass . ' glass-divider';
                        return str_replace($classMatch[0], 'class="' . $newClass . '"', $matches[0]);
                    }
                    return $matches[0];
                }
                return '<hr class="glass-divider"' . $attrs . '>';
            },
            $html
        );
    }

    /* ============================================================
     *  13. 给所有 a 标签添加 target="_blank"
     * ============================================================ */

    /**
     * 为所有带 href 且没有 target 的 <a> 标签追加 target="_blank" rel="noopener noreferrer"
     * 排除纯锚点链接（href 以 # 开头）避免页面内跳转也弹新窗口
     */
    private static function addTargetBlank(string $html): string
    {
        return preg_replace_callback(
            '/<a\b([^>]*)>/i',
            function ($matches) {
                $attrs = $matches[1];

                // 提取 href 值（支持双引号、单引号、无引号）
                $href = '';
                if (preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/i', $attrs, $m)) {
                    $href = $m[2];
                } elseif (preg_match('/\bhref\s*=\s*([^\s>]+)/i', $attrs, $m)) {
                    $href = $m[1];
                }

                // 无 href 或是纯锚点链接，保持原样
                if ($href === '' || str_starts_with(trim($href), '#')) {
                    return $matches[0];
                }

                // 已有 target，不再覆盖
                if (preg_match('/\btarget\s*=/i', $attrs)) {
                    return $matches[0];
                }

                // 已有 rel 则追加 noopener noreferrer，否则新建
                if (preg_match('/\brel\s*=\s*(["\'])(.*?)\1/i', $attrs, $relMatch)) {
                    $oldRel = $relMatch[2];
                    $newRel = $oldRel . ' noopener noreferrer';
                    $attrs  = str_replace($relMatch[0], 'rel="' . $newRel . '"', $attrs);
                } elseif (preg_match('/\brel\s*=\s*([^\s>]+)/i', $attrs, $relMatch)) {
                    $oldRel = $relMatch[1];
                    $newRel = $oldRel . ' noopener noreferrer';
                    $attrs  = str_replace($relMatch[0], 'rel="' . $newRel . '"', $attrs);
                } else {
                    $attrs .= ' rel="noopener noreferrer"';
                }

                return '<a' . $attrs . ' target="_blank">';
            },
            $html
        );
    }

    /* ============================================================
     *  辅助方法
     * ============================================================ */

    /**
     * 单次安全替换：将 $html 中第一次出现的 $search 替换为 $replace
     * 避免 str_replace 全局替换导致嵌套/重复块错乱
     */
    private static function replaceOnce(string $html, string $search, string $replace): string
    {
        $pos = strpos($html, $search);
        if ($pos === false) {
            return $html;
        }
        return substr_replace($html, $replace, $pos, strlen($search));
    }

    private static function wrapContent(string $content): string
    {
        if ($content === '') {
            return '';
        }
        if (preg_match('/^<(p|div|h[1-6]|ul|ol|li|blockquote|pre|table|section|article|figure|details)/i', $content)) {
            return $content;
        }
        if (preg_match('/<br\s*\/?>|\n/', $content)) {
            $parts = preg_split('/<br\s*\/?>|\n/', $content);
            $wrapped = [];
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $wrapped[] = '<p>' . $part . '</p>';
                }
            }
            return implode("\n", $wrapped);
        }
        return '<p>' . $content . '</p>';
    }

    private static function buildRefCardHtml(array $data): string
    {
        $title   = htmlspecialchars($data['title'] ?? '文章标题', ENT_QUOTES, 'UTF-8');
        $url     = htmlspecialchars($data['url'] ?? '#', ENT_QUOTES, 'UTF-8');
        $thumb   = !empty($data['thumb']) ? htmlspecialchars($data['thumb'], ENT_QUOTES, 'UTF-8') : '';
        $label   = htmlspecialchars($data['label'] ?? '相关阅读', ENT_QUOTES, 'UTF-8');
        $excerpt = !empty($data['excerpt']) ? htmlspecialchars($data['excerpt'], ENT_QUOTES, 'UTF-8') : '';
        $time    = !empty($data['time']) ? htmlspecialchars($data['time'], ENT_QUOTES, 'UTF-8') : '';
        $views   = !empty($data['views']) ? htmlspecialchars($data['views'], ENT_QUOTES, 'UTF-8') : '';

        $thumbHtml = '';
        if ($thumb !== '') {
            $thumbHtml = '<div class="ref-article-thumb">'
                . '<a href="' . $thumb . '" data-fancybox="gallery" data-type="image" data-caption="' . $title . '">'
                . '<img alt="' . $title . '" loading="lazy" src="' . $thumb . '"></a>'
                . '<span class="ref-article-thumb-overlay">Featured</span>'
                . '</div>';
        }

        $metaHtml = '';
        if ($time !== '' || $views !== '') {
            $metaParts = [];
            if ($time !== '') {
                $metaParts[] = '<span><span class="material-icons" aria-hidden="true">schedule</span> ' . $time . '</span>';
            }
            if ($views !== '') {
                $metaParts[] = '<span><span class="material-icons" aria-hidden="true">visibility</span> ' . $views . '</span>';
            }
            $metaParts[] = '<span class="ref-article-readmore">阅读全文 <span class="material-icons" aria-hidden="true">arrow_forward</span></span>';
            $metaHtml = '<div class="ref-article-meta">' . implode('', $metaParts) . '</div>';
        }

        return '<div class="ref-article-card">'
            . $thumbHtml
            . '<a href="' . $url . '" target="_self" class="ref-article-body">'
            . '<span class="ref-article-label">📎 ' . $label . '</span>'
            . '<h4 class="ref-article-title">' . $title . '</h4>'
            . ($excerpt ? '<p class="ref-article-excerpt">' . $excerpt . '</p>' : '')
            . $metaHtml
            . '</a>'
            . '<span class="ref-article-corner"><span class="material-icons" aria-hidden="true">open_in_new</span></span>'
            . '</div>';
    }

    private static function buildContentCard(int $cid, string $type): string
    {
        $cacheKey = $type . ':' . $cid;
        if (isset(self::$contentCache[$cacheKey])) {
            return self::buildRefCardHtml(self::$contentCache[$cacheKey]);
        }

        try {
            $widget = \Helper::widgetById('Contents', $cid);
            if (!$widget || empty($widget->title)) {
                throw new \Exception('Content not found');
            }
            if ($widget->type !== $type) {
                throw new \Exception('Type mismatch');
            }

            $thumb = '';
            if (!empty($widget->fields->thumb)) {
                $thumb = $widget->fields->thumb;
            } else {
                $thumb = ThumbnailHelper::showThumbnail($widget, true, false, null, false, true) ?? '';
            }

            $label = '';
            if ($type === 'post') {
                $cat = AstroPro::getPostCategory($cid, $widget);
                $label = $cat['name'] ?? '';
            }
            if ($label === '') {
                $label = $type === 'post' ? '文章' : '页面';
            }

            $excerpt = AstroPro::excerpt((string) $widget->content, 120);

            $db = Db::get();
            $viewsRow = $db->fetchRow($db->select('views')->from('table.contents')->where('cid = ?', $cid));
            $views = $viewsRow ? (int) $viewsRow['views'] : 0;
            $viewsStr = $views > 0 ? self::formatNumber($views) : '';

            $time = '';
            if ($type === 'post') {
                $time = date('Y-m-d', (int) $widget->created);
            }

            $data = [
                'title'   => $widget->title,
                'url'     => $widget->permalink,
                'thumb'   => $thumb,
                'label'   => $label,
                'excerpt' => $excerpt,
                'time'    => $time,
                'views'   => $viewsStr,
            ];

            self::$contentCache[$cacheKey] = $data;
            return self::buildRefCardHtml($data);

        } catch (\Throwable $e) {
            return '<div class="ref-article-card" style="border-color:var(--error);">'
                . '<div class="ref-article-body">'
                . '<span class="ref-article-label" style="color:var(--error);">⚠️ 引用失败</span>'
                . '<h4 class="ref-article-title">' . ($type === 'post' ? '文章' : '页面') . '不存在或已被删除</h4>'
                . '<p class="ref-article-excerpt">CID: ' . $cid . '</p>'
                . '</div>'
                . '</div>';
        }
    }

    private static function formatNumber(int $num): string
    {
        if ($num >= 1000000) {
            return round($num / 1000000, 1) . 'M';
        }
        if ($num >= 1000) {
            return round($num / 1000, 1) . 'k';
        }
        return (string) $num;
    }

    private static function parseAttributes(string $attrStr): array
    {
        $attrs = [];
        if (preg_match_all('/(\w+)\s*=\s*(["\'])(.*?)\2/s', $attrStr, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $attrs[$m[1]] = $m[3];
            }
        }
        $cleaned = preg_replace('/\w+\s*=\s*["\'][^"\']*["\']/s', '', $attrStr);
        if ($cleaned !== null && $cleaned !== '') {
            if (preg_match_all('/(\w+)/s', $cleaned, $flagMatches)) {
                foreach ($flagMatches[1] as $flag) {
                    if (!isset($attrs[$flag])) {
                        $attrs[$flag] = '';
                    }
                }
            }
        }
        return $attrs;
    }

    private static function addHeadingIds($html)
    {
        $html = preg_replace_callback(
            '/<h([1-6])([^>]*)>(.*?)<\/h\1>/is',
            function ($matches) {
                $level = $matches[1];
                $attrs = $matches[2];
                $inner = $matches[3];
                $text = strip_tags($inner);
                if (empty($text)) {
                    $id = 'heading-' . uniqid();
                } else {
                    $id = self::slugify($text);
                }
                if (preg_match('/\bid\s*=\s*(["\'])/i', $attrs)) {
                    return "<h{$level}{$attrs}>{$inner}</h{$level}>";
                }
                return "<h{$level} id=\"{$id}\"{$attrs}>{$inner}</h{$level}>";
            },
            $html
        );
        return $html;
    }

    private static function slugify($text)
    {
        if (empty($text)) {
            return 'heading';
        }
        return Typecho_Common::slugName($text);
    }
}