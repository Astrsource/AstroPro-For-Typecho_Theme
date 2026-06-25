<?php
/**
 * comments_ajax.php
 *
 * Ajax 评论后端处理（适配扁平化嵌套）
 *  - ajaxComment()       POST  themeAction=comment           提交 / 回复评论
 *  - loadMoreComments()  GET   themeAction=loadMoreComments  翻页加载更多（返回扁平化后代）
 *
 * 集成方式：在主题 functions.php 的 themeInit() 中 require 本文件，并注册路由
 */

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

if (!function_exists('ajaxComment')) {
    /**
     * Ajax 评论提交 / 回复（与原逻辑一致，无需改动）
     */
    function ajaxComment($archive): void {
        $options = \Typecho\Widget::widget('Widget_Options');
        $user    = \Typecho\Widget::widget('Widget_User');
        $db      = \Typecho\Db::get();

        header('Content-Type: application/json; charset=utf-8');

        if (!$archive->allow('comment')) {
            echo json_encode(['status' => 0, 'msg' => '评论已关闭'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $cid = (int) $archive->request->get('cid', 0);
        if ($cid <= 0) {
            echo json_encode(['status' => 0, 'msg' => '无效的文章'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $post = $db->fetchRow(
            $db->select('cid', 'type', 'status', 'authorId')
                ->from('table.contents')
                ->where('cid = ?', $cid)
        );
        if (!$post || (string) $post['status'] !== 'publish') {
            echo json_encode(['status' => 0, 'msg' => '文章不存在'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ((bool) $options->commentsPostIntervalEnable && !$user->pass('editor', true)) {
            $latest = $db->fetchRow(
                $db->select('created')->from('table.comments')
                    ->where('cid = ?', $cid)
                    ->where('ip = ?', $archive->request->getIp())
                    ->order('created', \Typecho\Db::SORT_DESC)
                    ->limit(1)
            );
            if ($latest && (time() - (int) $latest['created']) < (int) $options->commentsPostInterval) {
                echo json_encode(['status' => 0, 'msg' => '您的发言过于频繁，请稍后再试'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        $author = trim((string) $archive->request->get('author', ''));
        $mail   = trim((string) $archive->request->get('mail', ''));
        $url    = trim((string) $archive->request->get('url', ''));
        $text   = (string) $archive->request->get('text', '');
        $parent = (int) $archive->request->get('parent', 0);

        $errors = [];
        if ($author === '') {
            $errors[] = '必须填写用户名';
        } elseif (mb_strlen($author) > 200) {
            $errors[] = '用户名最多 200 字符';
        }
        if ((bool) $options->commentsRequireMail && !$user->hasLogin() && $mail === '') {
            $errors[] = '必须填写邮箱';
        }
        if ($mail !== '' && !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = '邮箱格式错误';
        }
        if ((bool) ($options->commentsRequireURL ?? $options->commentsRequireUrl) && !$user->hasLogin() && $url === '') {
            $errors[] = '必须填写网站';
        }
        if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
            $errors[] = '网站格式错误';
        }
        if ($text === '') {
            $errors[] = '必须填写评论内容';
        }
        if (!empty($errors)) {
            echo json_encode(['status' => 0, 'msg' => implode('；', $errors)], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            $url = 'http://' . $url;
        }

        $comment = [
            'cid'     => $cid,
            'created' => (int) $options->gmtTime,
            'agent'   => (string) $archive->request->getAgent(),
            'ip'      => (string) $archive->request->getIp(),
            'ownerId' => (int) $post['authorId'],
            'type'    => 'comment',
            'text'    => $text,
            'status'  => (bool) $options->commentsRequireModeration ? 'waiting' : 'approved',
        ];

        if ($parent > 0) {
            if (!(bool) $options->commentsThreaded) {
                echo json_encode(['status' => 0, 'msg' => '不支持嵌套回复'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $parentRow = $db->fetchRow(
                $db->select('coid', 'cid')->from('table.comments')->where('coid = ?', $parent)
            );
            if (!$parentRow || (int) $parentRow['cid'] !== $cid) {
                echo json_encode(['status' => 0, 'msg' => '父级评论不存在'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $comment['parent'] = $parent;
        }

        if ((bool) $options->commentsWhitelist && !(bool) $options->commentsRequireModeration) {
            $approved = $db->fetchObject(
                $db->select(['COUNT(coid)' => 'c'])->from('table.comments')
                    ->where('author = ?', $author)
                    ->where('mail = ?', $mail)
                    ->where('status = ?', 'approved')
            );
            $comment['status'] = ((int) ($approved->c ?? 0) > 0) ? 'approved' : 'waiting';
        }

        if ($user->hasLogin()) {
            $comment['author']   = (string) $user->screenName;
            $comment['mail']     = (string) $user->mail;
            $comment['url']      = (string) $user->url;
            $comment['authorId'] = (int) $user->uid;
        } else {
            $comment['author']   = $author;
            $comment['mail']     = $mail;
            $comment['url']      = $url;
            $comment['authorId'] = 0;

            $expire = (int) $options->gmtTime + (int) $options->timezone + 30 * 24 * 3600;
            \Typecho\Cookie::set('__typecho_remember_author', $author, $expire);
            \Typecho\Cookie::set('__typecho_remember_mail', $mail, $expire);
            \Typecho\Cookie::set('__typecho_remember_url', $url, $expire);
        }

        $feedback = \Typecho\Widget::widget('Widget_Feedback');
        try {
            $comment = $feedback->pluginHandle()->comment($comment, $feedback->_content) ?? $comment;
        } catch (\Typecho\Exception $e) {
            \Typecho\Cookie::set('__typecho_remember_text', $text);
            echo json_encode(['status' => 0, 'msg' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $insertId = $feedback->insert($comment);
        } catch (\Throwable $e) {
            echo json_encode(['status' => 0, 'msg' => '评论提交失败：' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $feedback->pluginHandle()->finishComment($feedback);
        } catch (\Throwable) {
            // ignore
        }

        \Typecho\Cookie::delete('__typecho_remember_text');

        $newRow = $db->fetchRow($feedback->select()->where('coid = ?', $insertId)->limit(1));
        $feedback->push($newRow);

        ob_start();
        $feedback->content();
        $contentHtml = ob_get_clean();

        $status = (string) ($comment['status'] ?? 'approved');
        $msg    = $status === 'waiting' ? '您的评论需管理员审核后才能显示' : '';
        $isAuthor = (int) ($comment['authorId'] ?? 0) === (int) $post['authorId'];

        $parentAuthor = '';
        $parentCoid   = (int) ($comment['parent'] ?? 0);
        if ($parentCoid > 0) {
            $pRow = $db->fetchRow(
                $db->select('author')->from('table.comments')
                    ->where('coid = ?', $parentCoid)->limit(1)
            );
            $parentAuthor = (string) ($pRow['author'] ?? '');
        }

        echo json_encode([
            'status'  => 1,
            'msg'     => $msg,
            'comment' => [
                'coid'         => (int) $insertId,
                'cid'          => (int) $cid,
                'parent'       => $parentCoid,
                'parentAuthor' => $parentAuthor,
                'author'       => (string) $comment['author'],
                'mail'         => (string) $comment['mail'],
                'url'          => (string) $comment['url'],
                'avatar'       => AstroPro::avatar((string) $comment['mail'], 80, true),
                'content'      => $contentHtml,
                'datetime'     => date('Y-m-d H:i:s', (int) $comment['created']),
                'status'       => $status,
                'isAuthor'     => $isAuthor,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('loadMoreComments')) {
    /**
     * Ajax 加载更多评论（扁平化版本）
     * 返回每个顶级评论及其平铺的所有后代（descendants）
     */
    function loadMoreComments($archive): void {
        header('Content-Type: application/json; charset=utf-8');

        $cid      = (int) $archive->request->get('cid', 0);
        $page     = max(1, (int) $archive->request->get('page', 1));
        $pageSize = max(1, min(50, (int) $archive->request->get('pageSize', 10)));
        $order    = ((string) $archive->request->get('order', 'ASC') === 'DESC')
                     ? 'DESC' : 'ASC';

        if ($cid <= 0) {
            echo json_encode(['status' => 0, 'msg' => '无效参数'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $db = \Typecho\Db::get();

        // 顶层评论总数
        $totalTop = (int) $db->fetchObject(
            $db->select(['COUNT(coid)' => 'c'])->from('table.comments')
                ->where('cid = ?', $cid)
                ->where('status = ?', 'approved')
                ->where('parent = ?', 0)
        )->c;

        // 当页顶层评论
        $topRows = $db->fetchAll(
            $db->select()->from('table.comments')
                ->where('cid = ?', $cid)
                ->where('status = ?', 'approved')
                ->where('parent = ?', 0)
                ->order('created', $order === 'DESC' ? \Typecho\Db::SORT_DESC : \Typecho\Db::SORT_ASC)
                ->offset(($page - 1) * $pageSize)
                ->limit($pageSize)
        );

        $hasMore = $totalTop > $page * $pageSize;

        // 获取该文章所有已通过评论（用于构建后代关系）
        $allApproved = $db->fetchAll(
            $db->select()->from('table.comments')
                ->where('cid = ?', $cid)
                ->where('status = ?', 'approved')
                ->order('created', \Typecho\Db::SORT_ASC)
        );

        // 构建父子映射
        $childrenMap = [];
        $commentMap = [];
        foreach ($allApproved as $row) {
            $coid = (int) $row['coid'];
            $parent = (int) $row['parent'];
            $commentMap[$coid] = $row;
            if ($parent !== 0) {
                $childrenMap[$parent][] = $row;
            }
        }

        /**
         * 获取某个顶级评论的所有后代（平铺，按 created 升序）
         */
        function getFlatDescendantsAjax(int $topCoid, array $childrenMap, array $commentMap): array {
            $descendants = [];
            $queue = $childrenMap[$topCoid] ?? [];
            while (!empty($queue)) {
                $current = array_shift($queue);
                $descendants[] = $current;
                $coid = (int) $current['coid'];
                if (!empty($childrenMap[$coid])) {
                    $queue = array_merge($queue, $childrenMap[$coid]);
                }
            }
            usort($descendants, function($a, $b) {
                return ($a['created'] ?? 0) - ($b['created'] ?? 0);
            });
            return $descendants;
        }

        $render = static function (array $c, array $childrenMap, array $commentMap) use ($db): array {
            $coid   = (int) $c['coid'];
            $isAuthor = (int) ($c['authorId'] ?? 0) === (int) ($c['ownerId'] ?? 0);
            $parentCoid = (int) $c['parent'];
            $parentAuthor = '';
            if ($parentCoid > 0) {
                $pRow = $db->fetchRow(
                    $db->select('author')->from('table.comments')
                        ->where('coid = ?', $parentCoid)->limit(1)
                );
                $parentAuthor = (string) ($pRow['author'] ?? '');
            }
            return [
                'coid'         => $coid,
                'cid'          => (int) $c['cid'],
                'parent'       => $parentCoid,
                'parentAuthor' => $parentAuthor,
                'author'       => (string) $c['author'],
                'mail'         => (string) $c['mail'],
                'url'          => (string) $c['url'],
                'avatar'       => AstroPro::avatar((string) $c['mail'], 80, true),
                'content'      => (string) $c['text'],
                'datetime'     => date('Y-m-d H:i:s', (int) $c['created']),
                'status'       => (string) $c['status'],
                'isAuthor'     => $isAuthor,
            ];
        };

        $list = [];
        foreach ($topRows as $top) {
            $topItem = $render($top, $childrenMap, $commentMap);
            $descendants = getFlatDescendantsAjax((int)$top['coid'], $childrenMap, $commentMap);
            $topItem['descendants'] = array_map(function($child) use ($render, $childrenMap, $commentMap) {
                return $render($child, $childrenMap, $commentMap);
            }, $descendants);
            $list[] = $topItem;
        }

        echo json_encode([
            'status'   => 1,
            'comments' => $list,
            'hasMore'  => $hasMore,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}