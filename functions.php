<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

// ==================== 注册自动加载器 ====================//
spl_autoload_register(function ($className) {
    // 映射类名到文件路径（libs/ 目录，无命名空间）
    $map = [
        'AstroPro'            => __DIR__ . '/libs/AstroPro.php',
        'AdjacentPostRouter'  => __DIR__ . '/libs/AdjacentPosts.php',
        'AdjacentPosts'       => __DIR__ . '/libs/AdjacentPosts.php',
        'ThumbnailHelper'     => __DIR__ . '/libs/ThumbnailHelper.php',
        'SeoHelper'           => __DIR__ . '/libs/SeoHelper.php',
        'HotSearch'           => __DIR__ . '/libs/HotSearch.php',
        'FileCache'           => __DIR__ . '/libs/MusicParser.php',
        'HttpClient'          => __DIR__ . '/libs/MusicParser.php',
        'MusicParserFactory'  => __DIR__ . '/libs/MusicParser.php',
        'ContentFilter'       => __DIR__ . '/libs/ContentFilter.php',
        'Backup'              => __DIR__ . '/libs/Backup.php',
    ];

    if (isset($map[$className])) {
        require_once $map[$className];
        return;
    }

    $file = __DIR__ . '/libs/' . str_replace('\\', '/', $className) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

//==================== 主题配置 ====================//
require_once __DIR__ . '/libs/FunctionsConfig.php';

//==================== Ajax 评论后端处理（注册到 themeInit）====================//
require_once __DIR__ . '/comments_ajax.php';

//==================== 初始化全局音乐缓存/HTTP 客户端 ====================//
$GLOBALS['music_cache'] = new FileCache();
$GLOBALS['music_http']  = new HttpClient();

//==================== 注册内容过滤器 ====================//

// 挂载继承类钩子
// \Typecho\Plugin::factory('Widget_Archive')->contentEx = ['ContentFilter', 'parseContent'];
// \Typecho\Plugin::factory('Widget_Archive')->excerptEx = ['ContentFilter', 'parseContent'];

// 挂载基础类钩子
// \Typecho\Plugin::factory('Widget_Base_Contents')->contentEx = ['ContentFilter', 'parseContent'];
// \Typecho\Plugin::factory('Widget_Base_Contents')->excerptEx = ['ContentFilter', 'parseContent'];

// 测试用：打印已注册的处理函数
// var_dump(\Typecho\Plugin::export()['handles']['Widget_Base_Contents:contentEx'] ?? 'NOT FOUND');

//显示成功注册内容过滤器，但是无法生效，问题未知！
//以上多种钩子尝试挂载，均显示注册函数，但实际处理未生效。
//钩子名称在1.3版本中使用下划线，如使用反斜杠，会导致挂载失败。

// ==================== 主题初始化 ====================//
function themeInit($self) {
    // ==================== Ajax 评论提交 / 回复 ====================
    if ($self->is('single') && $self->request->isPost() && $self->request->get('themeAction') === 'comment') {
        ajaxComment($self);
    }

    // ==================== Ajax 加载更多评论 ====================
    if ($self->is('single') && $self->request->get('themeAction') === 'loadMoreComments') {
        loadMoreComments($self);
    }

    // 钩子挂载不生效，改用初始化函数处理文章和页面内容过滤
    if ($self->is('post') || $self->is('page')) {
        $existing = $self->content;
        $self->content = ContentFilter::parseContent($existing, $self);
    }

    // 搜索接口（只返回结果，不记录热搜）
    if ($self->request->isGet() && $self->request->get('action') == 'search') {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');

        $keyword = trim($self->request->get('keyword', ''));
        if (empty($keyword) || mb_strlen($keyword, 'UTF-8') < 1) {
            echo json_encode(['error' => '请输入搜索关键词']);
            exit;
        }
        $keyword = addslashes($keyword);

        try {
            $db = Typecho_Db::get();
            $options = Helper::options();

            $articles = $db->fetchAll(
                $db->select()->from('table.contents')
                    ->where('status = ?', 'publish')
                    ->where('password IS NULL')
                    ->where('type = ?', 'post')
                    ->where('(title LIKE ? OR text LIKE ?)', "%{$keyword}%", "%{$keyword}%")
                    ->order('created', Typecho_Db::SORT_DESC)
            );

            $results = [];
            foreach ($articles as $article) {
                $content = strip_tags($article['text']);
                $pos = mb_stripos($content, $keyword, 0, 'UTF-8');
                if ($pos !== false) {
                    $start = max(0, $pos - 60);
                    $preview = ($start > 0 ? '...' : '') . mb_substr($content, $start, 120, 'UTF-8') . '...';
                } else {
                    $preview = mb_substr($content, 0, 120, 'UTF-8') . '...';
                }

                $cat = $db->fetchRow(
                    $db->select('name')->from('table.metas')
                        ->join('table.relationships', 'table.metas.mid = table.relationships.mid')
                        ->where('table.relationships.cid = ?', $article['cid'])
                        ->where('table.metas.type = ?', 'category')
                        ->limit(1)
                );

                $results[] = [
                    'title'    => $article['title'],
                    'url'      => Typecho_Common::url(Typecho_Router::url('post', $article), $options->index),
                    'excerpt'  => $preview,
                    'created'  => date('Y-m-d', $article['created']),
                    'type'     => 'post',
                    'category' => $cat ? $cat['name'] : '',
                ];
            }

            echo json_encode(['success' => true, 'count' => count($results), 'data' => $results]);
        } catch (Exception $e) {
            echo json_encode(['error' => '搜索失败']);
        }
        exit;
    }

    // 记录热搜接口（仅明确提交时调用，不返回搜索结果）
    if ($self->request->get('action') == 'record') {
        $keyword = trim($self->request->get('keyword', ''));
        if (!empty($keyword)) {
            HotSearch::log($keyword);
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true]);
        exit;
    }

    // 热门搜索 HTML 接口（供 PJAX 后前端刷新）
    if ($self->request->get('action') == 'hotSearchHTML') {
        header('Content-Type: text/html; charset=utf-8');
        HotSearch::render(5, [
            'wrapper' => '<div class="search-hot-tags">{items}</div>',
            'item'    => '<span class="search-hot-tag" role="button" tabindex="0" data-keyword="{keyword}">{keyword}</span>',
            'empty'   => '<span style="color:var(--text-muted);font-size:0.75rem;">暂无热门搜索</span>',
        ]);
        exit;
    }
    
    // ==================== 音乐链接刷新接口 ====================
    if ($self->request->isGet() && $self->request->get('action') == 'refresh-music-url') {
        header('Content-Type: application/json; charset=utf-8');

        $source = $self->request->get('source', '');
        $rawId  = $self->request->get('raw_id', '');

        if (empty($source) || empty($rawId)) {
            echo json_encode(['error' => '参数不完整']);
            exit;
        }

        // 兼容前端传入简称
        $sourceMap = [
            'ne'      => 'Netease',
            'tx'      => 'QQMusic',
            'kg'      => 'Kugou',
            'Netease' => 'Netease',
            'QQMusic' => 'QQMusic',
            'Kugou'   => 'Kugou',
        ];
        $source = $sourceMap[$source] ?? $source;

        global $music_cache, $music_http;
        if (!isset($music_cache) || !($music_cache instanceof FileCache)) {
            $music_cache = new FileCache();
        }
        if (!isset($music_http) || !($music_http instanceof HttpClient)) {
            $music_http = new HttpClient();
        }
        MusicParserFactory::init($music_cache, $music_http);

        $data = MusicParserFactory::refreshMusicUrl($source, $rawId);

        if ($data) {
            echo json_encode([
                'success' => true,
                'url'     => $data['url'],
                'name'    => $data['name'],
                'artist'  => $data['artist'],
            ]);
        } else {
            echo json_encode(['error' => '刷新失败，请稍后再试']);
        }
        exit;
    }
    // 点赞接口
    if ($self->request->get('action') == 'like') {
        header('Content-Type: application/json; charset=utf-8');
        $cid = (int) $self->request->get('cid', 0);
        if ($cid <= 0) {
            echo json_encode(['error' => '无效文章']);
            exit;
        }
        $result = AstroPro::toggleLike($cid);
        echo json_encode(['success' => true, 'likes' => $result['likes'], 'liked' => $result['liked']]);
        exit;
    }
}

// ==================== 音乐列表解析（带批量刷新） ====================//
function getThemeMusicList(bool $forceRefresh = false): array {
    $rawText = Typecho\Widget::widget('Widget_Options')->musicList ?? '';
    $lines = explode(PHP_EOL, $rawText);

    global $music_cache, $music_http;
    // 确保全局对象已初始化（若被意外清空则重建）
    if (!isset($music_cache) || !($music_cache instanceof FileCache)) {
        $music_cache = new FileCache();
    }
    if (!isset($music_http) || !($music_http instanceof HttpClient)) {
        $music_http = new HttpClient();
    }
    MusicParserFactory::init($music_cache, $music_http);

    $list = MusicParserFactory::batchParseFromText($lines);

    if ($forceRefresh && !empty($list)) {
        $checkMap = [];
        $indexMap = [];
        foreach ($list as $i => $item) {
            if ($item['source'] !== 'manual' && !empty($item['url']) && !empty($item['raw_id'])) {
                $checkMap[$i] = $item['url'];
                $indexMap[$i] = ['source' => $item['source'], 'raw_id' => $item['raw_id']];
            }
        }

        if (!empty($checkMap)) {
            $status = $music_http->batchHeadCheck($checkMap);
            foreach ($status as $i => $ok) {
                if (!$ok) {
                    $parser = MusicParserFactory::getParser($indexMap[$i]['source']);
                    if ($parser) {
                        $fresh = $parser->parse($indexMap[$i]['raw_id']);
                        if (empty($fresh['error'])) {
                            $list[$i] = $fresh;
                        }
                    }
                }
            }
        }
    }

    return $list;
}

/**
 * 获取作者头像
 *
 * 优先级：自定义头像 > 邮箱头像
 *
 * @param \Widget_Abstract_Contents|\Widget_Abstract_Users|string $author 作者对象或邮箱
 * @param int                                                     $size   头像尺寸（默认 64）
 * @param bool                                                    $default 是否使用默认头像（默认 true）
 * @return string 头像 URL
 */
function getAuthorAvatar($author = null, int $size = 64, bool $default = true): string
{
    $options = \Typecho\Widget::widget('Widget_Options');
    $customAvatar = (string) ($options->isAvatar);

    if (!empty($customAvatar)) {
        return $customAvatar;
    }

    $mail = '';
    if (is_object($author) && isset($author->mail)) {
        $mail = (string) $author->mail;
    } elseif (is_string($author)) {
        $mail = $author;
    } elseif (isset($GLOBALS['author']) && is_object($GLOBALS['author'])) {
        $mail = (string) $GLOBALS['author']->mail;
    }

    return AstroPro::avatar($mail, $size, $default);
}

/**
 *
 * 随机文章 Widget
 *
 */
class Widget_Random_Posts extends \Widget_Abstract_Contents
{
    public function execute(): void {
        $adapterClass = get_class($this->db->getAdapter());
        $randOrder = (str_contains($adapterClass, 'SQLite') || str_contains($adapterClass, 'Pgsql'))
            ? 'RANDOM()'
            : 'RAND()';

        $select = $this->select()
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.type = ?', 'post')
            ->where('table.contents.created < ?', time())
            ->order($randOrder)
            ->limit(3);

        $this->db->fetchAll($select, [$this, 'push']);
    }
}

/**
 * 热门文章 Widget（按浏览量 views 降序）
 * 
 * 调用方式：
 *   Widget::widget('Widget_Post_hot@hot', 'pageSize=5')->to($hot);
 */
class Widget_Post_hot extends \Widget_Abstract_Contents
{
    public function execute(): void
    {
        $select = $this->select()
            ->where("table.contents.password IS NULL OR table.contents.password = ''")
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.created <= ?', time())
            ->where('table.contents.type = ?', 'post')
            ->limit((int) ($this->parameter->pageSize ?? 5))
            ->order('table.contents.views', \Typecho\Db::SORT_DESC);

        $this->db->fetchAll($select, [$this, 'push']);
    }
}