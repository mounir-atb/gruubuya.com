<?php
declare(strict_types=1);

// ---------------------------------------------------------------- friendships

function friendship_between(int $a, int $b): ?array
{
    $st = db()->prepare(
        'SELECT * FROM friendships
         WHERE (requester_id = ? AND addressee_id = ?) OR (requester_id = ? AND addressee_id = ?)'
    );
    $st->execute([$a, $b, $b, $a]);
    return $st->fetch() ?: null;
}

function are_friends(int $a, int $b): bool
{
    $f = friendship_between($a, $b);
    return $f !== null && $f['status'] === 'accepted';
}

/**
 * Relationship of $me to $other: none | friends | pending_out | pending_in.
 */
function friend_state(int $me, int $other): string
{
    $f = friendship_between($me, $other);
    if ($f === null) {
        return 'none';
    }
    if ($f['status'] === 'accepted') {
        return 'friends';
    }
    return (int) $f['requester_id'] === $me ? 'pending_out' : 'pending_in';
}

function friends_of(int $userId): array
{
    $st = db()->prepare(
        'SELECT u.id, u.username, u.display_name, u.avatar, u.last_seen_at
         FROM friendships f
         JOIN users u ON u.id = IF(f.requester_id = ?, f.addressee_id, f.requester_id)
         WHERE f.status = \'accepted\' AND (f.requester_id = ? OR f.addressee_id = ?)
         ORDER BY u.display_name, u.username'
    );
    $st->execute([$userId, $userId, $userId]);
    return $st->fetchAll();
}

function friend_count(int $userId): int
{
    $st = db()->prepare(
        'SELECT COUNT(*) FROM friendships
         WHERE status = \'accepted\' AND (requester_id = ? OR addressee_id = ?)'
    );
    $st->execute([$userId, $userId]);
    return (int) $st->fetchColumn();
}

// ---------------------------------------------------------------- posts

const POSTS_PAGE_SIZE = 20;

/**
 * Fetch posts with author + like/comment counts. $authorId null = friends
 * feed for the viewer; otherwise that author's posts.
 */
function fetch_posts(array $viewer, ?int $authorId, int $beforeId = 0, int $limit = POSTS_PAGE_SIZE): array
{
    $me     = (int) $viewer['id'];
    $params = [$me];
    $sql    = 'SELECT p.id, p.user_id, p.body, p.created_at,
                      u.username, u.display_name, u.avatar,
                      (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id)    AS like_count,
                      (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id = p.id) AS comment_count,
                      EXISTS(SELECT 1 FROM post_likes pl2 WHERE pl2.post_id = p.id AND pl2.user_id = ?) AS liked
               FROM posts p
               JOIN users u ON u.id = p.user_id
               WHERE ';
    if ($authorId !== null) {
        $sql      .= 'p.user_id = ?';
        $params[] = $authorId;
    } else {
        $sql .= '(p.user_id = ? OR p.user_id IN (
                    SELECT IF(f.requester_id = ?, f.addressee_id, f.requester_id)
                    FROM friendships f
                    WHERE f.status = \'accepted\' AND (f.requester_id = ? OR f.addressee_id = ?)))';
        array_push($params, $me, $me, $me, $me);
    }
    if ($beforeId > 0) {
        $sql      .= ' AND p.id < ?';
        $params[] = $beforeId;
    }
    $sql .= ' ORDER BY p.id DESC LIMIT ' . (int) $limit;

    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function fetch_post(array $viewer, int $postId): ?array
{
    $st = db()->prepare(
        'SELECT p.id, p.user_id, p.body, p.created_at,
                u.username, u.display_name, u.avatar,
                (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id)    AS like_count,
                (SELECT COUNT(*) FROM post_comments pc WHERE pc.post_id = p.id) AS comment_count,
                EXISTS(SELECT 1 FROM post_likes pl2 WHERE pl2.post_id = p.id AND pl2.user_id = ?) AS liked
         FROM posts p JOIN users u ON u.id = p.user_id WHERE p.id = ?'
    );
    $st->execute([(int) $viewer['id'], $postId]);
    return $st->fetch() ?: null;
}

function fetch_comments(int $postId): array
{
    $st = db()->prepare(
        'SELECT c.id, c.post_id, c.user_id, c.body, c.created_at,
                u.username, u.display_name, u.avatar
         FROM post_comments c JOIN users u ON u.id = c.user_id
         WHERE c.post_id = ? ORDER BY c.id'
    );
    $st->execute([$postId]);
    return $st->fetchAll();
}

function comment_html(array $c): string
{
    $author = ['username' => $c['username'], 'display_name' => $c['display_name'], 'avatar' => $c['avatar']];
    $profile = 'profile.php?u=' . rawurlencode($c['username']);
    return '<div class="flex items-start gap-2">'
        . '<a href="' . e($profile) . '">' . avatar_html($author, 'w-7 h-7 text-xs') . '</a>'
        . '<div class="bg-gray-50 border border-gray-100 rounded-2xl px-3 py-2 min-w-0">'
        . '<a href="' . e($profile) . '" class="font-medium text-sm hover:text-violet-700">'
        . e($c['display_name'] !== '' ? $c['display_name'] : $c['username']) . '</a>'
        . ' <span class="text-gray-400 text-xs">' . e(time_ago($c['created_at'])) . '</span>'
        . '<p class="text-sm text-gray-800 break-words">' . nl2br(e($c['body'])) . '</p>'
        . '</div></div>';
}

function post_card_html(array $p, array $viewer, bool $expandComments = false): string
{
    $pid     = (int) $p['id'];
    $author  = ['username' => $p['username'], 'display_name' => $p['display_name'], 'avatar' => $p['avatar']];
    $profile = 'profile.php?u=' . rawurlencode($p['username']);
    $name    = e($p['display_name'] !== '' ? $p['display_name'] : $p['username']);
    $liked   = !empty($p['liked']);
    $own     = (int) $p['user_id'] === (int) $viewer['id'];

    $html = '<article class="bg-white border border-gray-200 rounded-2xl p-5" data-pid="' . $pid . '">'
        . '<div class="flex items-start gap-3">'
        . '<a href="' . e($profile) . '">' . avatar_html($author, 'w-10 h-10 text-base') . '</a>'
        . '<div class="min-w-0 flex-1">'
        . '<div class="flex items-center gap-2 flex-wrap">'
        . '<a href="' . e($profile) . '" class="font-semibold hover:text-violet-700">' . $name . '</a>'
        . '<span class="text-gray-400 text-sm">@' . e($p['username']) . '</span>'
        . '<span class="text-gray-300">&middot;</span>'
        . '<a href="post.php?id=' . $pid . '" class="text-gray-400 text-sm hover:text-violet-600">'
        . e(time_ago($p['created_at'])) . '</a>'
        . '</div>'
        . '<p class="mt-1 text-gray-800 break-words">' . nl2br(e($p['body'])) . '</p>'
        . '<div class="mt-3 flex items-center gap-5 text-sm">'
        . '<button type="button" data-like="' . $pid . '" class="flex items-center gap-1.5 '
        . ($liked ? 'text-violet-600' : 'text-gray-500 hover:text-violet-600') . '">'
        . '<i class="' . ($liked ? 'fa-solid' : 'fa-regular') . ' fa-heart"></i> '
        . '<span data-like-count>' . (int) $p['like_count'] . '</span></button>'
        . '<button type="button" data-comments="' . $pid . '" class="flex items-center gap-1.5 text-gray-500 hover:text-violet-600">'
        . '<i class="fa-regular fa-comment"></i> <span data-comment-count>' . (int) $p['comment_count'] . '</span></button>';
    if ($own) {
        $html .= '<button type="button" data-del-post="' . $pid . '" class="ml-auto text-gray-400 hover:text-red-600" title="Delete post">'
            . '<i class="fa-regular fa-trash-can"></i></button>';
    }
    $html .= '</div>'
        . '<div data-comments-box class="mt-3' . ($expandComments ? '" data-loaded="1' : ' hidden') . '">'
        . '<div data-comments-list class="space-y-3">';
    if ($expandComments) {
        foreach (fetch_comments($pid) as $c) {
            $html .= comment_html($c);
        }
    }
    $html .= '</div>'
        . '<form data-comment-form data-pid="' . $pid . '" class="mt-3 flex items-center gap-2">'
        . '<input name="body" maxlength="500" placeholder="Write a comment&hellip;" autocomplete="off" required '
        . 'class="flex-1 min-w-0 border border-gray-200 rounded-full px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-500">'
        . '<button class="bg-violet-600 hover:bg-violet-700 text-white rounded-full w-9 h-9 flex items-center justify-center shrink-0">'
        . '<i class="fa-solid fa-paper-plane text-sm"></i></button>'
        . '</form></div>'
        . '</div></div></article>';
    return $html;
}

// ---------------------------------------------------------------- lobbies

function fetch_lobby(int $id): ?array
{
    $st = db()->prepare(
        'SELECT l.*, (SELECT COUNT(*) FROM lobby_members m WHERE m.lobby_id = l.id) AS member_count
         FROM lobbies l WHERE l.id = ?'
    );
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function is_lobby_member(int $lobbyId, int $userId): bool
{
    return lobby_member_role($lobbyId, $userId) !== null;
}

function lobby_member_role(int $lobbyId, int $userId): ?string
{
    $st = db()->prepare('SELECT role FROM lobby_members WHERE lobby_id = ? AND user_id = ?');
    $st->execute([$lobbyId, $userId]);
    $row = $st->fetch();
    return $row ? $row['role'] : null;
}

function lobby_members(int $lobbyId): array
{
    $st = db()->prepare(
        'SELECT u.id, u.username, u.display_name, u.avatar, u.last_seen_at, m.role
         FROM lobby_members m JOIN users u ON u.id = m.user_id
         WHERE m.lobby_id = ? ORDER BY m.role = \'owner\' DESC, u.display_name, u.username'
    );
    $st->execute([$lobbyId]);
    return $st->fetchAll();
}

/** Compact user payload embedded in realtime events and chat responses. */
function user_public(array $u): array
{
    return [
        'id'           => (int) $u['id'],
        'username'     => $u['username'],
        'display_name' => $u['display_name'],
        'avatar'       => $u['avatar'],
    ];
}
