<?php
declare(strict_types=1);
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * Typecho 主题专用 - 音乐解析工具库
 * 特性：
 * 1. 按平台批量并发解析（curl_multi），大幅减少网络等待
 * 2. 单条 parse() 自动复用 batchParse() 代码路径
 * 3. 支持批量 HEAD 检查 URL 有效性
 * 4. URL 自动刷新机制（refreshIfExpired）
 */

class MusicConfig
{
    public const CACHE_DIR = __DIR__ . '/cache/';
    public const CACHE_TTL = 600; // 10 分钟
}

class FileCache
{
    public function __construct()
    {
        if (!is_dir(MusicConfig::CACHE_DIR)) {
            mkdir(MusicConfig::CACHE_DIR, 0755, true);
        }
    }

    public function get(string $key): ?array
    {
        $file = MusicConfig::CACHE_DIR . md5($key) . '.json';
        if (file_exists($file)) {
            if (time() - filemtime($file) > MusicConfig::CACHE_TTL) {
                unlink($file);
                return null;
            }
            $data = json_decode(file_get_contents($file), true);
            return is_array($data) ? $data : null;
        }
        return null;
    }

    public function set(string $key, array $data): void
    {
        $file = MusicConfig::CACHE_DIR . md5($key) . '.json';
        file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    public function delete(string $key): void
    {
        $file = MusicConfig::CACHE_DIR . md5($key) . '.json';
        if (file_exists($file)) unlink($file);
    }
}

class HttpClient
{
    public function request(string $url, array $opts = []): string
    {
        $ch = curl_init($url);
        $cfg = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING       => '',
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ];
        if (!empty($opts['post'])) {
            $cfg[CURLOPT_POST] = true;
            $cfg[CURLOPT_POSTFIELDS] = is_array($opts['post'])
                ? http_build_query($opts['post'])
                : $opts['post'];
        }
        if (!empty($opts['headers'])) {
            $cfg[CURLOPT_HTTPHEADER] = $opts['headers'];
        }
        curl_setopt_array($ch, $cfg);
        $result = curl_exec($ch);
        // curl_close($ch); php8.5 版本已删除
        return $result === false ? '' : $result;
    }

    public function getFinalUrl(string $url, array $headers = []): string
    {
        $ch = curl_init($url);
        $cfg = [
            CURLOPT_NOBODY          => true,
            CURLOPT_FOLLOWLOCATION  => true,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_TIMEOUT         => 10,
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_HEADER          => true,
            CURLOPT_USERAGENT       => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ];
        if (!empty($headers)) {
            $cfg[CURLOPT_HTTPHEADER] = $headers;
        }
        curl_setopt_array($ch, $cfg);
        curl_exec($ch);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        // curl_close($ch); php8.5 版本已删除
        return $finalUrl ?: $url;
    }

    public function isUrlAccessible(string $url): bool
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY          => true,
            CURLOPT_FOLLOWLOCATION  => true,
            CURLOPT_TIMEOUT         => 5,
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_RETURNTRANSFER  => true,
        ]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // curl_close($ch); php8.5 版本已删除
        return $httpCode === 200 || $httpCode === 302;
    }

    public function multiRequest(array $pool): array
    {
        if (count($pool) === 1) {
            $r = reset($pool);
            $k = key($pool);
            $r['body'] = $this->request($r['url'], $r['opts'] ?? []);
            return [$k => $r];
        }

        $mh = curl_multi_init();
        $handles = [];
        $defaultCfg = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING       => '',
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ];

        foreach ($pool as $k => $r) {
            $ch = curl_init();
            $cfg = $defaultCfg + [CURLOPT_URL => $r['url']];

            $h = ['Accept: */*'];
            if (!empty($r['opts']['post'])) {
                $cfg[CURLOPT_POST] = true;
                $cfg[CURLOPT_POSTFIELDS] = is_array($r['opts']['post'])
                    ? http_build_query($r['opts']['post'])
                    : $r['opts']['post'];
                if (is_array($r['opts']['post'])) {
                    $h[] = 'Content-Type: application/x-www-form-urlencoded';
                }
            }
            if (!empty($r['opts']['headers'])) {
                $h = array_merge($h, $r['opts']['headers']);
            }
            $cfg[CURLOPT_HTTPHEADER] = $h;

            curl_setopt_array($ch, $cfg);
            curl_multi_add_handle($mh, $ch);
            $handles[$k] = $ch;
        }

        $running = null;
        do {
            curl_multi_exec($mh, $running);
            if ($running > 0) {
                curl_multi_select($mh, 0.05);
            }
        } while ($running > 0);

        $res = [];
        foreach ($handles as $k => $ch) {
            $res[$k] = $pool[$k];
            $res[$k]['body'] = curl_multi_getcontent($ch);
            curl_multi_remove_handle($mh, $ch);
            // curl_close($ch); php8.5 版本已删除
        }
        curl_multi_close($mh);

        return $res;
    }

    public function batchHeadCheck(array $urls): array
    {
        if (empty($urls)) {
            return [];
        }
        if (count($urls) === 1) {
            $k = key($urls);
            return [$k => $this->isUrlAccessible($urls[$k])];
        }

        $mh = curl_multi_init();
        $handles = [];
        $results = [];

        foreach ($urls as $id => $url) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_NOBODY         => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_RETURNTRANSFER => true,
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$id] = $ch;
            $results[$id] = false;
        }

        $running = null;
        do {
            curl_multi_exec($mh, $running);
            if ($running > 0) {
                curl_multi_select($mh, 0.05);
            }
        } while ($running > 0);

        foreach ($handles as $id => $ch) {
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $results[$id] = ($httpCode === 200 || $httpCode === 302);
            curl_multi_remove_handle($mh, $ch);
            // curl_close($ch); php8.5 版本已删除
        }
        curl_multi_close($mh);

        return $results;
    }
    
    /**
     * 批量并发获取最终重定向 URL
     * @param array $urls ['id1' => 'url1', ...]
     * @param array $headers 额外请求头
     * @return array ['id1' => 'finalUrl1', ...]
     */
    public function batchGetFinalUrl(array $urls, array $headers = []): array
    {
        if (empty($urls)) return [];
        if (count($urls) === 1) {
            $k = key($urls);
            return [$k => $this->getFinalUrl($urls[$k], $headers)];
        }

        $mh = curl_multi_init();
        $handles = [];
        $results = [];

        foreach ($urls as $id => $url) {
            $ch = curl_init();
            $cfg = [
                CURLOPT_URL            => $url,
                CURLOPT_NOBODY         => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HEADER         => true,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ];
            if (!empty($headers)) {
                $cfg[CURLOPT_HTTPHEADER] = $headers;
            }
            curl_setopt_array($ch, $cfg);
            curl_multi_add_handle($mh, $ch);
            $handles[$id] = $ch;
        }

        $running = null;
        do {
            curl_multi_exec($mh, $running);
            if ($running > 0) curl_multi_select($mh, 0.05);
        } while ($running > 0);

        foreach ($handles as $id => $ch) {
            $results[$id] = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $urls[$id];
            curl_multi_remove_handle($mh, $ch);
        }
        curl_multi_close($mh);

        return $results;
    }
}

class MusicHelper
{
    public static function slim(array $data): array
    {
        $keep = ['name', 'artist', 'pic', 'url', 'source', 'raw_id'];
        return array_intersect_key($data, array_flip($keep));
    }
}

abstract class AbstractMusicParser
{
    protected FileCache $cache;
    protected HttpClient $http;

    public function __construct(FileCache $cache, HttpClient $http)
    {
        $this->cache = $cache;
        $this->http  = $http;
    }

    /**
     * 单条解析（自动复用 batchParse 代码路径）
     */
    public function parse(string $id): array
    {
        $results = $this->batchParse([$id]);
        return $results[$id] ?? ['error' => 'not found'];
    }

    /**
     * 批量解析（核心新增）
     * @param array $ids 原始 ID 数组
     * @return array ['id1' => data, 'id2' => data, ...]
     */
    abstract public function batchParse(array $ids): array;

    public function refreshIfExpired(string $id, array $cachedData): array
    {
        $url = $cachedData['url'] ?? '';
        if (empty($url) || !$this->http->isUrlAccessible($url)) {
            $newData = $this->parse($id);
            if (!isset($newData['error'])) {
                $cacheKey = $this->getCacheKey($id);
                $this->cache->set($cacheKey, MusicHelper::slim($newData));
                return MusicHelper::slim($newData);
            }
        }
        return $cachedData;
    }

    public function getCacheKey(string $id): string
    {
        return $this->getSourcePrefix() . '_' . $id;
    }

    abstract protected function getSourcePrefix(): string;
}

class NeteaseParser extends AbstractMusicParser
{
    protected function getSourcePrefix(): string { return 'ne'; }

    public function batchParse(array $ids): array
    {
        $ids = array_unique($ids);
        $result = [];
        $needIds = [];

        foreach ($ids as $id) {
            $cached = $this->cache->get($this->getCacheKey($id));
            if ($cached !== null) {
                $result[$id] = $cached;
            } else {
                $needIds[] = $id;
            }
        }

        if (empty($needIds)) {
            return $result;
        }

        // 1. 批量获取歌曲详情
        $headers = ['Referer: https://music.163.com'];
        $cBody = '[' . implode(',', array_map(fn($id) => '{"id":' . $id . ',"v":0}', $needIds)) . ']';
        $res = json_decode($this->http->request(
            'https://music.163.com/api/v3/song/detail',
            ['post' => ['c' => $cBody], 'headers' => $headers]
        ), true);

        // 2. 批量获取真实播放地址（跟随重定向）
        $outerUrls = [];
        foreach ($needIds as $id) {
            $outerUrls[$id] = 'https://music.163.com/song/media/outer/url?id=' . $id;
        }
        $realUrls = $this->http->batchGetFinalUrl($outerUrls, ['Referer: https://music.163.com']);

        // 3. 组装结果
        foreach ($res['songs'] ?? [] as $song) {
            $id = (string) $song['id'];
            $artists = array_column($song['ar'] ?? [], 'name');
            $outerUrl = $outerUrls[$id] ?? 'https://music.163.com/song/media/outer/url?id=' . $id;
            $finalUrl = !empty($realUrls[$id]) ? $realUrls[$id] : $outerUrl;

            $data = [
                'name'   => $song['name'] ?? '未知歌曲',
                'artist' => implode(' / ', $artists) ?: '未知歌手',
                'pic'    => $song['al']['picUrl'] ?? '',
                'url'    => $finalUrl,
                'source' => 'Netease',
                'raw_id' => $id,
            ];
            $slim = MusicHelper::slim($data);
            $this->cache->set($this->getCacheKey($id), $slim);
            $result[$id] = $slim;
        }

        return $result;
    }
}

class TencentParser extends AbstractMusicParser
{
    protected function getSourcePrefix(): string { return 'tx'; }

    public function batchParse(array $ids): array
    {
        $ids = array_unique($ids);
        $result = [];
        $needIds = [];

        foreach ($ids as $id) {
            $cached = $this->cache->get($this->getCacheKey($id));
            if ($cached !== null) {
                $result[$id] = $cached;
            } else {
                $needIds[] = $id;
            }
        }

        if (empty($needIds)) {
            return $result;
        }

        // 1. 批量获取 song detail
        $pool = [];
        foreach ($needIds as $id) {
            $pool[$id] = [
                'url'  => 'https://c.y.qq.com/v8/fcg-bin/fcg_play_single_song.fcg?songmid=' . urlencode($id) . '&platform=yqq&format=json',
                'opts' => ['headers' => ['Referer: https://y.qq.com']]
            ];
        }
        $details = $this->http->multiRequest($pool);

        // 2. 准备 vkey 请求
        $guid = (string) rand(1000000000, 9999999999);
        $mids = [];
        $files = [];
        $types = [];
        $meta = [];

        foreach ($details as $id => $res) {
            $d = json_decode($res['body'], true)['data'][0] ?? [];
            if (empty($d)) {
                continue;
            }
            $artists = array_column($d['singer'] ?? [], 'name');
            $meta[$id] = [
                'name'     => $d['name'] ?? '未知歌曲',
                'artists'  => $artists,
                'albumMid' => $d['album']['mid'] ?? '',
                'mediaMid' => $d['file']['media_mid'] ?? '',
                'type'     => $d['type'] ?? 0
            ];
            if (!empty($meta[$id]['mediaMid'])) {
                $mids[] = $id;
                $files[] = 'C400' . $meta[$id]['mediaMid'] . '.m4a';
                $types[] = $d['type'] ?? 0;
            }
        }

        // 3. 批量获取 vkey（一次请求）
        $urlMap = [];
        if (!empty($mids)) {
            $payload = [
                'req_0' => [
                    'module' => 'vkey.GetVkeyServer',
                    'method' => 'CgiGetVkey',
                    'param'  => [
                        'guid'      => $guid,
                        'songmid'   => $mids,
                        'filename'  => $files,
                        'songtype'  => $types,
                        'uin'       => '0',
                        'loginflag' => 1,
                        'platform'  => '20'
                    ]
                ]
            ];
            $vkeyUrl = 'https://u.y.qq.com/cgi-bin/musicu.fcg?format=json&platform=yqq.json&needNewCode=0&data=' . urlencode(json_encode($payload));
            $vRes = json_decode($this->http->request($vkeyUrl, ['headers' => ['Referer: https://y.qq.com']]), true);
            $infos = $vRes['req_0']['data']['midurlinfo'] ?? [];
            $sip = $vRes['req_0']['data']['sip'][0] ?? 'https://aqqmusic.tc.qq.com/';
            foreach ($infos as $i => $info) {
                if (!empty($info['purl'])) {
                    $urlMap[$mids[$i]] = $sip . $info['purl'];
                }
            }
        }

        // 4. 组装结果
        foreach ($meta as $id => $m) {
            $url = $urlMap[$id] ?? '';
            if (empty($url) && !empty($m['mediaMid'])) {
                $url = 'https://isure.stream.qqmusic.qq.com/C100' . $m['mediaMid'] . '.m4a?fromtag=66';
            }
            $data = [
                'name'   => $m['name'],
                'artist' => implode(' / ', $m['artists']) ?: '未知歌手',
                'pic'    => $m['albumMid'] ? 'https://y.gtimg.cn/music/photo_new/T002R300x300M000' . $m['albumMid'] . '.jpg?max_age=2592000' : '',
                'url'    => $url,
                'source' => 'QQMusic',
                'raw_id' => $id,
            ];
            $slim = MusicHelper::slim($data);
            $this->cache->set($this->getCacheKey($id), $slim);
            $result[$id] = $slim;
        }

        return $result;
    }
}

class KugouParser extends AbstractMusicParser
{
    protected function getSourcePrefix(): string { return 'kg'; }

    public function batchParse(array $ids): array
    {
        $ids = array_unique($ids);
        $result = [];
        $needIds = [];

        foreach ($ids as $id) {
            $cached = $this->cache->get($this->getCacheKey($id));
            if ($cached !== null) {
                $result[$id] = $cached;
            } else {
                $needIds[] = $id;
            }
        }

        if (empty($needIds)) {
            return $result;
        }

        // 1. 批量获取 privilege
        $pool = [];
        foreach ($needIds as $hash) {
            $pool[$hash] = [
                'url'  => 'http://media.store.kugou.com/v1/get_res_privilege',
                'opts' => [
                    'post'    => json_encode([
                        'relate'    => 1,
                        'userid'    => '0',
                        'vip'       => 0,
                        'appid'     => 1000,
                        'token'     => '',
                        'behavior'  => 'download',
                        'area_code' => '1',
                        'clientver' => '8990',
                        'resource'  => [['id' => 0, 'type' => 'audio', 'hash' => $hash]]
                    ]),
                    'headers' => ['Content-Type: application/json']
                ]
            ];
        }
        $privs = $this->http->multiRequest($pool);

        // 2. 批量获取 track URL
        $trackPool = [];
        $meta = [];
        foreach ($privs as $hash => $res) {
            foreach (json_decode($res['body'], true)['data'][0]['relate_goods'] ?? [] as $item) {
                if (empty($item['hash'])) {
                    continue;
                }
                $name = $item['name'] ?? '未知歌曲';
                $artists = [];
                if (!empty($item['singername'])) {
                    $artists = explode('、', $item['singername']);
                } elseif (strpos($name, ' - ') !== false) {
                    $p = explode(' - ', $name, 2);
                    $artists = explode('、', $p[0]);
                    $name = $p[1];
                }
                $pic = !empty($item['info']['image']) ? str_replace('{size}', '400', $item['info']['image']) : '';
                $meta[$hash] = ['name' => $name, 'artists' => $artists, 'pic' => $pic];
                $trackPool[$hash] = [
                    'url' => 'http://trackercdn.kugou.com/i/v2/?hash=' . $item['hash'] . '&key=' . md5($item['hash'] . 'kgcloudv2') . '&pid=3&behavior=play&cmd=25&version=8990'
                ];
                break; // 每个 hash 只取第一个 relate_goods
            }
        }

        $tracks = !empty($trackPool) ? $this->http->multiRequest($trackPool) : [];

        foreach ($needIds as $hash) {
            if (empty($tracks[$hash])) {
                continue;
            }
            $j = json_decode($tracks[$hash]['body'], true);
            if (empty($j['url'])) {
                continue;
            }
            $url = is_array($j['url']) ? $j['url'][0] : $j['url'];
            $url = preg_replace('#(?<!:)/{2,}#', '/', $url);
            $m = $meta[$hash] ?? ['name' => '未知歌曲', 'artists' => [], 'pic' => ''];
            $data = [
                'name'   => $m['name'],
                'artist' => implode(' / ', $m['artists']) ?: '未知歌手',
                'pic'    => $m['pic'],
                'url'    => $url,
                'source' => 'Kugou',
                'raw_id' => $hash,
            ];
            $slim = MusicHelper::slim($data);
            $this->cache->set($this->getCacheKey($hash), $slim);
            $result[$hash] = $slim;
        }

        return $result;
    }
}

class MusicParserFactory
{
    private static ?FileCache $cache = null;
    private static ?HttpClient $http = null;

    public static function init(FileCache $cache, HttpClient $http): void
    {
        self::$cache = $cache;
        self::$http = $http;
    }

    public static function getParser(string $source): ?AbstractMusicParser
    {
        if (!self::$cache || !self::$http) {
            return null;
        }
        return match ($source) {
            'Netease' => new NeteaseParser(self::$cache, self::$http),
            'QQMusic'    => new TencentParser(self::$cache, self::$http),
            'Kugou'  => new KugouParser(self::$cache, self::$http),
            default     => null,
        };
    }

    /**
     * 解析单行文本，返回指令数组（不触发网络请求）
     */
    public static function parseLine(string $line): ?array
    {
        $line = trim($line);
        if (empty($line) || !str_starts_with($line, '[') || !str_ends_with($line, ']')) {
            return null;
        }
        $content = trim(mb_substr($line, 1, -1));
        if (empty($content)) {
            return null;
        }

        $pattern = '/(\w+)\s*=\s*["\']([^"\']+)["\']/';
        preg_match_all($pattern, $content, $matches);
        $attrs = [];
        if (!empty($matches[1]) && !empty($matches[2])) {
            $attrs = array_combine($matches[1], $matches[2]);
        }
        if (empty($attrs)) {
            return null;
        }

        // 获取 autoplay 布尔值
        $autoplay = isset($attrs['autoplay']) && filter_var($attrs['autoplay'], FILTER_VALIDATE_BOOLEAN);

        if (isset($attrs['wy']) && !empty($attrs['wy'])) {
            return [
                'source'   => 'Netease',
                'raw_id'   => $attrs['wy'],
                'autoplay' => $autoplay
            ];
        }
        if (isset($attrs['tx']) && !empty($attrs['tx'])) {
            return [
                'source'   => 'QQMusic',
                'raw_id'   => $attrs['tx'],
                'autoplay' => $autoplay
            ];
        }
        if (isset($attrs['kg']) && !empty($attrs['kg'])) {
            return [
                'source'   => 'Kugou',
                'raw_id'   => $attrs['kg'],
                'autoplay' => $autoplay
            ];
        }

        $manual = [
            'name'     => $attrs['title'] ?? '',
            'artist'   => $attrs['artist'] ?? '未知歌手',
            'url'      => $attrs['url'] ?? '',
            'pic'      => $attrs['pic'] ?? '',
            'source'   => 'manual',
            'raw_id'   => '',
            'autoplay' => $autoplay
        ];
        return (empty($manual['url']) || empty($manual['name'])) ? null : $manual;
    }

    /**
     * 从文本行批量解析（核心批量入口）
     * @param array $lines 原始文本行数组
     * @return array 按原始顺序排列的歌曲列表
     */
    public static function batchParseFromText(array $lines): array
    {
        $custom = [];
        $groups = ['网易云音乐' => [], 'QQ音乐' => [], '酷狗音乐' => []];
        $order = [];
        $autoplayMap = []; // ← 新增：保存每个索引的 autoplay 状态
        $idx = 0;

        foreach ($lines as $line) {
            $item = self::parseLine($line);
            if ($item === null) {
                continue;
            }

            $autoplayMap[$idx] = $item['autoplay'] ?? false; // ← 新增

            if ($item['source'] === 'manual') {
                $custom[$idx] = $item;
                $order[$idx] = ['type' => 'manual', 'key' => $idx];
            } else {
                $rawId = $item['raw_id'] ?? '';
                if (!empty($rawId)) {
                    $groups[$item['source']][$idx] = $rawId;
                    $order[$idx] = ['type' => $item['source'], 'key' => $rawId];
                }
            }
            $idx++;
        }

        if (empty($order)) {
            return [];
        }

        // 批量解析各平台
        $parsed = [];
        foreach ($groups as $source => $ids) {
            if (empty($ids)) {
                continue;
            }
            $parser = self::getParser($source);
            if ($parser) {
                $results = $parser->batchParse(array_values($ids));
                foreach ($results as $rawId => $data) {
                    $parsed[$source][$rawId] = $data;
                }
            }
        }

        // 按原始顺序组装
        $list = [];
        ksort($order);
        foreach ($order as $idx => $info) {
            $item = null;
            if ($info['type'] === 'manual') {
                if (!empty($custom[$idx])) {
                    $item = $custom[$idx];
                }
            } else {
                $key = $info['key'];
                if (!empty($parsed[$info['type']][$key])) {
                    $item = $parsed[$info['type']][$key];
                }
            }
            
            if ($item !== null) {
                // ← 新增：合并 autoplay 状态（平台解析结果被 slim 过滤掉了 autoplay，需从 map 还原）
                $item['autoplay'] = $autoplayMap[$idx] ?? false;
                $list[] = $item;
            }
        }

        return $list;
    }
    
    /**
     * 强制刷新某首歌曲的链接（删除旧缓存 → 重新解析 → 写入新缓存）
     * @param string $source 平台标识：Netease / QQMusic / Kugou
     * @param string $rawId  歌曲原始 ID
     * @return array|null 成功返回歌曲数据，失败返回 null
     */
    public static function refreshMusicUrl(string $source, string $rawId): ?array
    {
        $parser = self::getParser($source);
        if (!$parser || !self::$cache) {
            return null;
        }

        // 删除旧缓存
        $cacheKey = $parser->getCacheKey($rawId);
        self::$cache->delete($cacheKey);

        // 重新解析（会自动写入缓存）
        $data = $parser->parse($rawId);
        if (isset($data['error'])) {
            return null;
        }

        return MusicHelper::slim($data);
    }
}