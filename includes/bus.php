<?php
declare(strict_types=1);

/**
 * Realtime event bus. Every realtime payload is written to the `events`
 * table; the optional WebSocket server (ws/server.php) pushes new rows to
 * connected browsers, and api/events.php serves them to polling clients.
 *
 * Channels: "user:{id}" (private per-user) and "lobby:{id}" (members only).
 */
function bus_emit(string $channel, string $type, array $payload): void
{
    db()->prepare('INSERT INTO events (channel, type, payload) VALUES (?, ?, ?)')
        ->execute([$channel, $type, json_encode($payload, JSON_UNESCAPED_UNICODE)]);
}

// ---------------------------------------------------------------- notifications

function notify(int $userId, ?int $actorId, string $type, array $data = []): void
{
    if ($actorId !== null && $actorId === $userId) {
        return;
    }
    db()->prepare('INSERT INTO notifications (user_id, actor_id, type, data) VALUES (?, ?, ?, ?)')
        ->execute([$userId, $actorId, $type, json_encode($data, JSON_UNESCAPED_UNICODE)]);

    $actor = null;
    if ($actorId !== null) {
        $st = db()->prepare('SELECT id, username, display_name, avatar FROM users WHERE id = ?');
        $st->execute([$actorId]);
        $actor = $st->fetch() ?: null;
    }
    bus_emit('user:' . $userId, 'notification', [
        'text' => notif_text($type, $actor, $data),
        'url'  => notif_url($type, $actor, $data),
    ]);
}

function notif_text(string $type, ?array $actor, array $data): string
{
    $name = $actor ? (($actor['display_name'] ?? '') !== '' ? $actor['display_name'] : $actor['username']) : 'Someone';
    return match ($type) {
        'friend_request' => $name . ' sent you a friend request',
        'friend_accept'  => $name . ' accepted your friend request',
        'lobby_kick'     => 'You were removed from lobby "' . ($data['lobby_name'] ?? '') . '"',
        default          => 'New notification',
    };
}

function notif_url(string $type, ?array $actor, array $data): string
{
    return match ($type) {
        'friend_request'             => 'friends.php?tab=requests',
        'friend_accept'              => $actor ? 'profile.php?u=' . rawurlencode($actor['username']) : 'friends.php',
        default                      => 'notifications.php',
    };
}

function notif_icon(string $type): string
{
    return match ($type) {
        'friend_request' => 'fa-solid fa-user-plus',
        'friend_accept'  => 'fa-solid fa-user-check',
        'lobby_kick'     => 'fa-solid fa-door-open',
        default          => 'fa-solid fa-bell',
    };
}

function unread_notif_count(int $userId): int
{
    $st = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $st->execute([$userId]);
    return (int) $st->fetchColumn();
}
