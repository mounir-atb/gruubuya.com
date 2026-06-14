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
