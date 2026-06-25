<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

function themeConfig($form)
{
    // ==================== 基础设置 ====================
    $logoUrl = new \Typecho\Widget\Helper\Form\Element\Text('logoUrl', null, null, _t('站点 LOGO 地址'), _t('填入图片 URL'));
    $form->addInput($logoUrl->addRule('url', _t('请填写合法 URL')));

    $logoDarkUrl = new \Typecho\Widget\Helper\Form\Element\Text('logoDarkUrl', null, null, _t('站点 LOGO 地址（暗色模式）'), _t('填入图片 URL'));
    $form->addInput($logoDarkUrl->addRule('url', _t('请填写合法 URL')));

    $logoTitle = new \Typecho\Widget\Helper\Form\Element\Radio(
        'logoTitle',
        ['1' => _t('显示'), '0' => _t('关闭')],
        '0',
        _t('站点 LOGO 显示标题'),
        _t('开启后，站点 LOGO 右边显示标题。使用方形 LOGO 时效果最佳，长方形 LOGO 时建议关闭。')
    );
    $form->addInput($logoTitle);

    $backgroundUrl = new \Typecho\Widget\Helper\Form\Element\Text('backgroundUrl', null, null, _t('背景图片'), _t('填入图片 URL'));
    $form->addInput($backgroundUrl); 

    $bgFilter = new \Typecho\Widget\Helper\Form\Element\Radio(
        'bgFilter',
        ['1' => _t('开启'), '0' => _t('关闭')],
        '0',
        _t('背景模糊滤镜'),
        _t('开启后，背景将添加模糊滤镜效果。')
    );
    $form->addInput($bgFilter);

    $gravatars = new \Typecho\Widget\Helper\Form\Element\Select('gravatars', [
        'https://www.gravatar.com/avatar/' => _t('gravatar的www源'),
        'https://cn.gravatar.com/avatar/' => _t('gravatar的cn源'),
        'https://secure.gravatar.com/avatar/' => _t('gravatar的secure源'),
        'https://cravatar.cn/avatar/' => _t('Cravatar[建议]'),
        'https://gravatar.helingqi.com/wavatar/' => _t('禾令奇源[建议]'),
        'https://gravatar.loli.net/avatar/' => _t('loli.net源[建议]'),
    ], 'https://gravatar.loli.net/avatar/',_t('gravatar头像源'), _t('替换Gravatar头像的默认地址。替换后可提升加载速度，默认使用<b>loli.net源[建议]</b>。'));
    $form->addInput($gravatars->multiMode());

    // ==================== 个人资料 ====================
    $isAvatar = new \Typecho\Widget\Helper\Form\Element\Text('isAvatar', null, null, _t('头像'), _t('填入图片 URL，留空则使用站点设置 Gravatar 头像'));
    $form->addInput($isAvatar->addRule('url', _t('请填写合法 URL')));

    $isBio = new \Typecho\Widget\Helper\Form\Element\Text('isBio', null, null, _t('个人标签'), _t('填入个人标签，留空则使用站点名称'));
    $form->addInput($isBio);
    
    $isDescription = new \Typecho\Widget\Helper\Form\Element\Text('isDescription', null, null, _t('个人描述'), _t('填入个人描述，留空则使用站点描述'));
    $form->addInput($isDescription);

    $isSocials = new \Typecho\Widget\Helper\Form\Element\Textarea('isSocials', null, null, _t('个人社交链接'),  
        _t('最多 4 个，每行一个，留空默认显示RSS和评论RSS、管理员邮箱。格式：[icon="图标" url="链接" tooltip="提示"]，如：<br>[icon="code" url="https://github.com/username"]<<br>[icon="email" url="mailto:email@example.com" tooltip="邮箱"]'));
    $form->addInput($isSocials);

    // ==================== 首页配置 ====================
    $isBanner = new \Typecho\Widget\Helper\Form\Element\Radio(
        'isBanner',
        ['1' => _t('显示'), '0' => _t('关闭')],
        '1',
        _t('首页banner区'),
        _t('开启后，首页banner区将显示。关闭后，首页banner区将不显示。')
    );
    $form->addInput($isBanner);

    $carouselBanner = new \Typecho\Widget\Helper\Form\Element\Textarea('carouselBanner', null, null, _t('banner轮播解析'),  
        _t('每行一个，格式：<br>[title="轮播标题" excerpt="这是轮播图的摘要" url="site.com" pic="banner.png" Lbadge="广告" Rbadge="2026-01-01"] <br> [post="文章cid"] <br> [page="独立页面cid"]<<br><strong style="color:#e74c3c;">注意：轮播图最多 4 个，超过 4 个将自动截取。</strong>'));
    $form->addInput($carouselBanner);

    $randomThumbnailList = new \Typecho\Widget\Helper\Form\Element\Textarea('randomThumbnailList', null, null, _t('随机图片列表'),  
        _t('每行一个，留空则使用主题默认 {n}.jpg 模板，支持主题路径(AstroPro文件夹内)和图片链接。如：<br>/images/1.jpg<br>/images/2.jpg<br>https://example.com/api.php<br>'));
    $form->addInput($randomThumbnailList);
    
    $sticky = new \Typecho\Widget\Helper\Form\Element\Text(
        'sticky', null, '',
        _t('置顶文章'),
        _t('多个cid用 , 分隔，如：1,2,3')
    );
    $form->addInput($sticky);

    // ==================== 功能组件 ====================
    $hotSearchTimeRange = new Typecho_Widget_Helper_Form_Element_Text(
        'hotSearchTimeRange',
        null,
        '0',
        _t('热门搜索统计范围（天）'),
        _t('只统计最近 N 天内的搜索记录。填写 0 表示不限。例如：7 = 最近7天，30 = 最近30天。')
    );
    $form->addInput($hotSearchTimeRange);

    $musicList = new \Typecho\Widget\Helper\Form\Element\Textarea('musicList', null, null, _t('音乐解析'),
        _t('每行一个：[title="歌名" artist="歌手" url="/歌曲.mp3" pic="封面图" autoplay="false"] 或 [wy="ID" autoplay="true"] / [tx="MID"] / [kg="Hash"]（网易云音乐、QQ音乐、酷狗音乐）<<br><strong style="color:#e74c3c;">注意：推荐使用自定义格式，音乐文件上传到CDN平台。三大平台接口随时可能失效，且部分歌曲需要VIP权限可能会导致解析失败。</strong>'));
    $form->addInput($musicList);

    $musicRandomAutoplay = new \Typecho\Widget\Helper\Form\Element\Radio(
        'musicRandomAutoplay',
        ['1' => _t('开启'), '0' => _t('关闭')],
        '0',
        _t('访问页面自动随机播放'),
        _t('开启后，访客进入页面将自动随机播放一首歌曲。<strong style="color:#e74c3c;">注意：此功能与单首`歌曲的 autoplay 设置互斥，开启后单首 autoplay 将失效。</strong>')
    );
    $form->addInput($musicRandomAutoplay);

    $footerRightLinks = new \Typecho\Widget\Helper\Form\Element\Textarea('footerRightLinks', null, null, _t('底部右侧链接解析'),
        _t('每行一个：[title="链接标题" url="example.com" icon="图标名称"]'));
    $form->addInput($footerRightLinks);

    $footerLinks = new \Typecho\Widget\Helper\Form\Element\Textarea('footerLinks', null, null, _t('底部图标链接解析'),
        _t('每行一个：[title="链接标题" url="example.com" img="图标 URL"]'));
    $form->addInput($footerLinks);
    
    // ==================== 图标导航（分类/页面图标） ====================
    $categories = Typecho_Widget::widget('Widget_Metas_Category_List')->to($categories);
    $categoryList = [];
    while ($categories->next()) {
        $categoryList[] = [
            'type' => 'category',
            'mid' => $categories->mid,
            'name' => $categories->name,
            'slug' => $categories->slug,
            'count' => $categories->count
        ];
    }
    
    $pages = Typecho_Widget::widget('Widget_Contents_Page_List')->to($pages);
    $pageList = [];
    while ($pages->next()) {
        $pageList[] = [
            'type' => 'page',
            'cid' => $pages->cid,
            'title' => $pages->title,
            'slug' => $pages->slug,
            'permalink' => $pages->permalink
        ];
    }
    
    $categoryIcons = new \Typecho\Widget\Helper\Form\Element\Hidden(
        'categoryIcons',
        NULL,
        '{"categories": {}, "pages": {}}',
        _t('分类与页面图标配置'),
        NULL
    );
    $form->addInput($categoryIcons);
    
    // ==================== 1. 数据备份（固定在最顶部，表单外） ====================
    echo '<div id="ap-backup-fixed">';
    Backup::echoBackup();
    echo '</div>';
    
    // ==================== 2. Tabs 导航（表单外，仅按钮） ====================
    echo '
    <style>
    .ap-tabs-nav-outer {
        display: flex;
        gap: 6px;
        border-bottom: 2px solid #e8e8e8;
        margin-bottom: 24px;
        padding: 12px 12px 0;
        flex-wrap: wrap;
        background: #fafafa;
        border-radius: 8px 8px 0 0;
    }
    .ap-tab-btn {
        padding: 8px 18px;
        border: none;
        background: transparent;
        color: #595959;
        font-size: 14px;
        cursor: pointer;
        border-bottom: 2px solid transparent;
        margin-bottom: -2px;
        transition: all 0.2s;
        font-weight: 500;
        border-radius: 4px 4px 0 0;
    }
    .ap-tab-btn:hover {
        color: #1890ff;
        background: rgba(24,144,255,0.06);
    }
    .ap-tab-btn.active {
        color: #1890ff;
        border-bottom-color: #1890ff;
        background: #fff;
        font-weight: 600;
    }
    .ap-tabs-content {
        padding: 4px 2px;
    }
    .ap-tab-panel {
        display: none;
        animation: apFadeIn 0.3s ease;
    }
    .ap-tab-panel.active {
        display: block;
    }
    @keyframes apFadeIn {
        from { opacity: 0; transform: translateY(6px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .ap-tab-panel .typecho-option {
        margin-bottom: 18px;
        background: #fff;
        padding: 12px 16px;
        border-radius: 6px;
        border: 1px solid #f0f0f0;
        transition: box-shadow 0.2s;
    }
    .ap-tab-panel .typecho-option:hover {
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }
    .ap-tab-panel .typecho-option label.typecho-label {
        font-weight: 600;
        color: #262626;
        margin-bottom: 8px;
        display: block;
    }
    </style>
    
    <div class="ap-tabs-nav-outer">
        <button type="button" class="ap-tab-btn active" data-target="ap-tab-basic">基础设置</button>
        <button type="button" class="ap-tab-btn" data-target="ap-tab-home">首页配置</button>
        <button type="button" class="ap-tab-btn" data-target="ap-tab-profile">个人资料</button>
        <button type="button" class="ap-tab-btn" data-target="ap-tab-feature">功能组件</button>
        <button type="button" class="ap-tab-btn" data-target="ap-tab-icons">图标导航</button>
    </div>';
    
    // ==================== 3. 图标 Picker（先隐藏，稍后由 JS 移入表单内面板） ====================
    $script = '
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    
    <style>
        .icon-picker-wrap { margin-top: 10px; }
        .icon-picker-section { margin-bottom: 20px; }
        .icon-picker-section-title {
            font-size: 14px; font-weight: 600; color: #333;
            padding: 8px 12px; background: #f5f5f5;
            border-radius: 4px; margin-bottom: 10px;
            display: flex; align-items: center; gap: 6px;
        }
        .icon-picker-section-title .material-icons { font-size: 18px; color: #666; }
        .icon-picker-item {
            display: flex; align-items: center; padding: 12px;
            border: 1px solid #d9d9d9; border-radius: 4px;
            margin-bottom: 8px; background: #fff; transition: all 0.2s;
        }
        .icon-picker-item:hover { border-color: #1890ff; box-shadow: 0 2px 8px rgba(0,0,0,0.09); }
        .icon-picker-item .item-info { flex: 1; display: flex; align-items: center; gap: 10px; }
        .icon-picker-item .item-name { font-weight: 500; color: #333; }
        .icon-picker-item .item-meta { color: #999; font-size: 12px; margin-left: 8px; }
        .icon-picker-item .icon-select { display: flex; align-items: center; gap: 8px; }
        .icon-picker-item .icon-preview {
            width: 36px; height: 36px; display: flex;
            align-items: center; justify-content: center;
            border: 1px dashed #d9d9d9; border-radius: 4px; background: #fafafa;
        }
        .icon-picker-item .icon-preview .material-icons { font-size: 20px; color: #666; }
        .icon-picker-item .icon-preview.has-icon { border-style: solid; border-color: #1890ff; background: #e6f7ff; }
        .icon-picker-item .icon-preview.has-icon .material-icons { color: #1890ff; }
        .icon-picker-item input[type="text"] {
            line-height: 1;
            width: 160px; padding: 5px 10px;
            border: 1px solid #d9d9d9; border-radius: 4px; font-size: 13px;
        }
        .icon-picker-item input[type="text"]:focus { outline: none; border-color: #1890ff; }
        .icon-picker-empty { color: #999; font-style: italic; padding: 20px; text-align: center; background: #fafafa; border-radius: 4px; }
        .icon-picker-tips {
            margin-top: 10px; padding: 10px 12px;
            background: #f6ffed; border: 1px solid #b7eb8f;
            border-radius: 4px; color: #52c41a; font-size: 13px; margin-bottom: 12px;
        }
        .icon-picker-tips code { background: #fff; padding: 2px 6px; border-radius: 3px; font-size: 12px; color: #389e0d; }
    </style>
    
    <div class="icon-picker-wrap">
        <label class="typecho-label">' . _t('分类与页面图标设置') . '</label>
        <div class="icon-picker-tips">
            ' . _t('输入 Material Icons 图标名称即可，如：') . 
            '<code>home</code> <code>folder</code> <code>star</code> <code>info</code> <code>contact_page</code> ' . 
            _t('留空则不显示图标。') . '
        </div>';
    
    if (!empty($categoryList)) {
        $script .= '
        <div class="icon-picker-section">
            <div class="icon-picker-section-title">
                <span class="material-icons">folder</span>
                ' . _t('分类') . '
            </div>
            <div id="icon-picker-categories">';
        
        foreach ($categoryList as $cat) {
            $script .= '
            <div class="icon-picker-item" data-type="category" data-id="' . $cat['mid'] . '">
                <div class="item-info">
                    <span class="item-name">' . htmlspecialchars($cat['name']) . '</span>
                    <span class="item-meta">mid:' . $cat['mid'] . ' / 文章:' . $cat['count'] . '</span>
                </div>
                <div class="icon-select">
                    <div class="icon-preview" id="preview-cat-' . $cat['mid'] . '">
                        <span class="material-icons"></span>
                    </div>
                    <input type="text" 
                           class="icon-input" 
                           data-type="category" 
                           data-id="' . $cat['mid'] . '" 
                           placeholder="' . _t('如：home') . '" 
                           value="">
                </div>
            </div>';
        }
        
        $script .= '</div></div>';
    }
    
    if (!empty($pageList)) {
        $script .= '
        <div class="icon-picker-section">
            <div class="icon-picker-section-title">
                <span class="material-icons">description</span>
                ' . _t('独立页面') . '
            </div>
            <div id="icon-picker-pages">';
        
        foreach ($pageList as $page) {
            $script .= '
            <div class="icon-picker-item" data-type="page" data-id="' . $page['cid'] . '">
                <div class="item-info">
                    <span class="item-name">' . htmlspecialchars($page['title']) . '</span>
                    <span class="item-meta">cid:' . $page['cid'] . ' / slug:' . $page['slug'] . '</span>
                </div>
                <div class="icon-select">
                    <div class="icon-preview" id="preview-page-' . $page['cid'] . '">
                        <span class="material-icons"></span>
                    </div>
                    <input type="text" 
                           class="icon-input" 
                           data-type="page" 
                           data-id="' . $page['cid'] . '" 
                           placeholder="' . _t('如：info') . '" 
                           value="">
                </div>
            </div>';
        }
        
        $script .= '</div></div>';
    }
    
    if (empty($categoryList) && empty($pageList)) {
        $script .= '<div class="icon-picker-empty">' . _t('暂无分类和独立页面') . '</div>';
    }
    
    $script .= '</div>
    
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        var hiddenInput = document.querySelector("input[name=\'categoryIcons\']");
        if (!hiddenInput) {
            console.error("Hidden input not found!");
            return;
        }
        
        var config = { categories: {}, pages: {} };
        
        try {
            var savedValue = hiddenInput.value.trim();
            if (savedValue && savedValue !== "{}") {
                var decoded = JSON.parse(savedValue);
                if (decoded && typeof decoded === "object") {
                    config.categories = decoded.categories || {};
                    config.pages = decoded.pages || {};
                }
            }
        } catch(e) {
            console.warn("Parse saved icons failed:", e);
            config = { categories: {}, pages: {} };
        }
        
        function updatePreview(type, id, iconName) {
            var prefix = type === "category" ? "cat-" : "page-";
            var preview = document.getElementById("preview-" + prefix + id);
            if (!preview) return;
            
            var iconEl = preview.querySelector(".material-icons");
            preview.classList.remove("has-icon");
            iconEl.textContent = "";
            
            if (iconName && iconName.trim()) {
                iconEl.textContent = iconName.trim();
                preview.classList.add("has-icon");
            }
        }
        
        function syncToHidden() {
            hiddenInput.value = JSON.stringify(config);
            var event = new Event("change", { bubbles: true });
            hiddenInput.dispatchEvent(event);
        }
        
        var inputs = document.querySelectorAll(".icon-input");
        inputs.forEach(function(input) {
            var type = input.getAttribute("data-type");
            var id = input.getAttribute("data-id");
            var storage = type === "category" ? config.categories : config.pages;
            
            if (storage[id]) {
                input.value = storage[id];
                updatePreview(type, id, storage[id]);
            }
            
            input.addEventListener("input", function() {
                var value = this.value.trim();
                var type = this.getAttribute("data-type");
                var id = this.getAttribute("data-id");
                var storage = type === "category" ? config.categories : config.pages;
                
                if (value) {
                    storage[id] = value;
                } else {
                    delete storage[id];
                }
                
                updatePreview(type, id, value);
                syncToHidden();
            });
        });
        
        var form = hiddenInput.closest("form");
        if (form) {
            form.addEventListener("submit", function(e) {
                syncToHidden();
            });
        }
    });
    </script>';
    
    echo '<div id="ap-icons-wrapper" style="display:none;">';
    echo $script;
    echo '</div>';
    
    // ==================== 4. JS：在表单内创建面板并安全移动选项 ====================
    echo '
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // 定位主题配置表单（通过 action 特征或从已有选项向上查找）
        var form = document.querySelector(\'form[action*="themes-edit"]\');
        if (!form) {
            var firstOption = document.querySelector(".typecho-option");
            if (firstOption) form = firstOption.closest("form");
        }
        if (!form) {
            console.error("Theme config form not found");
            return;
        }
        
        // 在表单内创建面板容器（确保所有选项仍在 form 中）
        var content = document.createElement("div");
        content.className = "ap-tabs-content";
        
        var panelIds = ["ap-tab-basic","ap-tab-home","ap-tab-profile","ap-tab-feature","ap-tab-icons"];
        var panels = {};
        panelIds.forEach(function(id) {
            var div = document.createElement("div");
            div.id = id;
            div.className = "ap-tab-panel";
            content.appendChild(div);
            panels[id] = div;
        });
        
        // 插入到表单第一个选项之前（保持保存按钮在最后）
        var firstOption = form.querySelector(".typecho-option");
        if (firstOption) {
            form.insertBefore(content, firstOption);
        } else {
            form.appendChild(content);
        }
        
        // 按字段名移动选项到对应面板
        function moveField(name, tabId) {
            var input = document.querySelector(\'input[name="\' + name + \'"], select[name="\' + name + \'"], textarea[name="\' + name + \'"]\');
            if (input) {
                var option = input.closest(".typecho-option");
                if (option && panels[tabId]) {
                    panels[tabId].appendChild(option);
                }
            }
        }
        
        var map = {
            "ap-tab-basic": ["logoUrl","logoDarkUrl","logoTitle","backgroundUrl","bgFilter","gravatars"],
            "ap-tab-home": ["isBanner","carouselBanner","sticky"],
            "ap-tab-profile": ["isAvatar","isBio","isDescription","isSocials"],
            "ap-tab-feature": ["randomThumbnailList","hotSearchTimeRange","musicList","musicRandomAutoplay","footerRightLinks","footerLinks"],
            "ap-tab-icons": ["categoryIcons"]
        };
        
        for (var tabId in map) {
            map[tabId].forEach(function(name) {
                moveField(name, tabId);
            });
        }
        
        // 将图标 Picker 界面移入图标导航面板
        var iconWrap = document.getElementById("ap-icons-wrapper");
        if (iconWrap && panels["ap-tab-icons"]) {
            panels["ap-tab-icons"].appendChild(iconWrap);
            iconWrap.style.display = "";
        }
        
        // 默认激活基础设置
        panels["ap-tab-basic"].classList.add("active");
        
        // Tab 切换事件
        var buttons = document.querySelectorAll(".ap-tab-btn");
        buttons.forEach(function(btn) {
            btn.addEventListener("click", function() {
                var target = this.getAttribute("data-target");
                buttons.forEach(function(b) { b.classList.remove("active"); });
                this.classList.add("active");
                panelIds.forEach(function(id) {
                    panels[id].classList.remove("active");
                });
                if (panels[target]) panels[target].classList.add("active");
            });
        });
    });
    </script>';
}