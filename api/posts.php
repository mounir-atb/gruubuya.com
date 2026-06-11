<?php
require __DIR__ . '/../includes/bootstrap.php';

$me     = api_user();
$action = (string) req('action');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    api_require_csrf();

    switch ($action) {
        case 'create': {
            $body = trim((string) req('body'));
            if ($body === '' || mb_strlen($body) > 2000) {
                json_err('Post must be 1-2000 characters.');
            }
            db()->prepare('INSERT INTO posts (user_id, body) VALUES (?, ?)')->execute([$me['id'], $body]);
            $post = fetch_post($me, (int) db()->lastInsertId());
            json_out(['ok' => true, 'html' => post_card_html($post, $me)]);
        }

        case 'delete': {
            $pid = (int) req('post_id');
            $st  = db()->prepare('DELETE FROM posts WHERE id = ? AND user_id = ?');
            $st->execute([$pid, $me['id']]);
            if ($st->rowCount() === 0) {
                json_err('Post not found or not yours.', 404);
            }
            json_out(['ok' => true]);
        }

        case 'like': {
            $pid  = (int) req('post_id');
            $post = fetch_post($me, $pid);
            if (!$post) {
                json_err('Post not found.', 404);
            }
            if (!empty($post['liked'])) {
                db()->prepare('DELETE FROM post_likes WHERE post_id = ? AND user_id = ?')->execute([$pid, $me['id']]);
                $liked = false;
            } else {
                db()->prepare('INSERT IGNORE INTO post_likes (post_id, user_id) VALUES (?, ?)')->execute([$pid, $me['id']]);
                $liked = true;
                notify((int) $post['user_id'], (int) $me['id'], 'post_like', ['post_id' => $pid]);
            }
            $st = db()->prepare('SELECT COUNT(*) FROM post_likes WHERE post_id = ?');
            $st->execute([$pid]);
            json_out(['ok' => true, 'liked' => $liked, 'count' => (int) $st->fetchColumn()]);
        }

        case 'comment': {
            $pid  = (int) req('post_id');
            $body = trim((string) req('body'));
            $post = fetch_post($me, $pid);
            if (!$post) {
                json_err('Post not found.', 404);
            }
            if ($body === '' || mb_strlen($body) > 500) {
                json_err('Comment must be 1-500 characters.');
            }
            db()->prepare('INSERT INTO post_comments (post_id, user_id, body) VALUES (?, ?, ?)')
                ->execute([$pid, $me['id'], $body]);
            notify((int) $post['user_id'], (int) $me['id'], 'post_comment', ['post_id' => $pid]);

            $st = db()->prepare(
                'SELECT c.id, c.post_id, c.user_id, c.body, c.created_at,
                        u.username, u.display_name, u.avatar
                 FROM post_comments c JOIN users u ON u.id = c.user_id WHERE c.id = ?'
            );
            $st->execute([(int) db()->lastInsertId()]);
            $stc = db()->prepare('SELECT COUNT(*) FROM post_comments WHERE post_id = ?');
            $stc->execute([$pid]);
            json_out(['ok' => true, 'html' => comment_html($st->fetch()), 'count' => (int) $stc->fetchColumn()]);
        }
    }
    json_err('Unknown action.');
}

// GET actions
switch ($action) {
    case 'comments': {
        $pid = (int) req('post_id');
        if (!fetch_post($me, $pid)) {
            json_err('Post not found.', 404);
        }
        $html = '';
        foreach (fetch_comments($pid) as $c) {
            $html .= comment_html($c);
        }
        json_out(['ok' => true, 'html' => $html]);
    }

    case 'feed':
    case 'user': {
        $beforeId = (int) req('before_id');
        $authorId = $action === 'user' ? (int) req('user_id') : null;
        $posts    = fetch_posts($me, $authorId, $beforeId);
        $html     = '';
        foreach ($posts as $p) {
            $html .= post_card_html($p, $me);
        }
        json_out([
            'ok'   => true,
            'html' => $html,
            'more' => count($posts) === POSTS_PAGE_SIZE,
            'last' => $posts ? (int) end($posts)['id'] : 0,
        ]);
    }
}
json_err('Unknown action.');
