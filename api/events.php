<?php
require __DIR__ . '/../includes/bootstrap.php';

/**
 * Polling endpoint of the realtime bus — fallback when the WebSocket server
 * is not running. Clients pass ?since=<last event id>&channels=a,b and get
 * everything newer on channels they are allowed to read.
 */
$me    = api_user();
$myId  = (int) $me['id'];
$since = (int) req('since', 0);
$chans = array_filter(array_map('trim', explode(',', (string) req('channels'))));

$allowed = [];
foreach ($chans as $c) {
    if ($c === 'user:' . $myId) {
        $allowed[] = $c;
    } elseif (preg_match('/^lobby:(\d+)$/', $c, $m) && is_lobby_member((int) $m[1], $myId)) {
        $allowed[] = $c;
    }
}

// Opportunistic cleanup: the bus only needs a short tail of history.
if (random_int(1, 50) === 1) {
    db()->exec('DELETE FROM events WHERE created_at < NOW() - INTERVAL 15 MINUTE');
}

if (!$allowed) {
    json_out(['ok' => true, 'last' => $since, 'events' => []]);
}

if ($since <= 0) {
    // First poll: hand back the current cursor without replaying history.
    $last = (int) db()->query('SELECT COALESCE(MAX(id), 0) FROM events')->fetchColumn();
    json_out(['ok' => true, 'last' => $last, 'events' => []]);
}

$in = implode(',', array_fill(0, count($allowed), '?'));
$st = db()->prepare(
    "SELECT id, channel, type, payload FROM events WHERE id > ? AND channel IN ($in) ORDER BY id LIMIT 100"
);
$st->execute(array_merge([$since], $allowed));

$events = [];
$last   = $since;
foreach ($st as $row) {
    $last     = max($last, (int) $row['id']);
    $events[] = [
        'id'      => (int) $row['id'],
        'channel' => $row['channel'],
        'type'    => $row['type'],
        'payload' => json_decode($row['payload'], true),
    ];
}
json_out(['ok' => true, 'last' => $last, 'events' => $events]);
