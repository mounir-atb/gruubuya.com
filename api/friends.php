<?php
require __DIR__ . '/../includes/bootstrap.php';

$me     = api_user();
$action = (string) req('action');
$myId   = (int) $me['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($action === 'search') {
        $q = trim((string) req('q'));
        if (mb_strlen($q) < 2) {
            json_out(['ok' => true, 'users' => []]);
        }
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        $st   = db()->prepare(
            'SELECT id, username, display_name, avatar, last_seen_at
             FROM users
             WHERE id != ? AND email_verified_at IS NOT NULL AND (username LIKE ? OR display_name LIKE ?)
             ORDER BY username LIMIT 20'
        );
        $st->execute([$myId, $like, $like]);
        $users = [];
        foreach ($st as $u) {
            $users[] = [
                'id'           => (int) $u['id'],
                'username'     => $u['username'],
                'display_name' => $u['display_name'],
                'avatar'       => $u['avatar'],
                'online'       => is_online($u['last_seen_at']),
                'state'        => friend_state($myId, (int) $u['id']),
            ];
        }
        json_out(['ok' => true, 'users' => $users]);
    }
    json_err('Unknown action.');
}

api_require_csrf();

$targetId = (int) req('user_id');
if ($targetId === $myId) {
    json_err('That is you.');
}
$target = find_user_by_id($targetId);
if (!$target) {
    json_err('User not found.', 404);
}

$existing = friendship_between($myId, $targetId);

switch ($action) {
    case 'request': {
        if ($existing) {
            if ($existing['status'] === 'accepted') {
                json_err('You are already friends.');
            }
            if ((int) $existing['requester_id'] === $myId) {
                json_err('Request already sent.');
            }
            // They already asked us — treat as accept.
            db()->prepare('UPDATE friendships SET status = \'accepted\', responded_at = NOW() WHERE id = ?')
                ->execute([$existing['id']]);
            notify($targetId, $myId, 'friend_accept');
            json_out(['ok' => true, 'state' => 'friends']);
        }
        db()->prepare('INSERT INTO friendships (requester_id, addressee_id) VALUES (?, ?)')
            ->execute([$myId, $targetId]);
        notify($targetId, $myId, 'friend_request');
        json_out(['ok' => true, 'state' => 'pending_out']);
    }

    case 'accept': {
        if (!$existing || $existing['status'] !== 'pending' || (int) $existing['addressee_id'] !== $myId) {
            json_err('No pending request from that user.');
        }
        db()->prepare('UPDATE friendships SET status = \'accepted\', responded_at = NOW() WHERE id = ?')
            ->execute([$existing['id']]);
        notify($targetId, $myId, 'friend_accept');
        json_out(['ok' => true, 'state' => 'friends']);
    }

    case 'decline': {
        if (!$existing || $existing['status'] !== 'pending' || (int) $existing['addressee_id'] !== $myId) {
            json_err('No pending request from that user.');
        }
        db()->prepare('DELETE FROM friendships WHERE id = ?')->execute([$existing['id']]);
        json_out(['ok' => true, 'state' => 'none']);
    }

    case 'cancel': {
        if (!$existing || $existing['status'] !== 'pending' || (int) $existing['requester_id'] !== $myId) {
            json_err('No outgoing request to that user.');
        }
        db()->prepare('DELETE FROM friendships WHERE id = ?')->execute([$existing['id']]);
        json_out(['ok' => true, 'state' => 'none']);
    }

    case 'unfriend': {
        if (!$existing || $existing['status'] !== 'accepted') {
            json_err('You are not friends.');
        }
        db()->prepare('DELETE FROM friendships WHERE id = ?')->execute([$existing['id']]);
        json_out(['ok' => true, 'state' => 'none']);
    }
}
json_err('Unknown action.');
