<?php
require __DIR__ . '/../includes/bootstrap.php';

$me      = api_user();
$myId    = (int) $me['id'];
$action  = (string) req('action');
$lobbyId = (int) req('lobby_id');

$lobby = fetch_lobby($lobbyId);
if (!$lobby) {
    json_err('Lobby not found.', 404);
}
$isMember = is_lobby_member($lobbyId, $myId);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($action === 'history') {
        // Members always; public lobby history is viewable by anyone verified.
        if (!$isMember && $lobby['privacy'] !== 'public') {
            json_err('Members only.', 403);
        }
        $beforeId = (int) req('before_id');
        $sql      = 'SELECT m.id, m.body, m.created_at, u.id AS uid, u.username, u.display_name, u.avatar
                     FROM lobby_messages m JOIN users u ON u.id = m.user_id
                     WHERE m.lobby_id = ?';
        $params   = [$lobbyId];
        if ($beforeId > 0) {
            $sql      .= ' AND m.id < ?';
            $params[] = $beforeId;
        }
        $sql .= ' ORDER BY m.id DESC LIMIT 50';
        $st  = db()->prepare($sql);
        $st->execute($params);
        $rows = array_reverse($st->fetchAll());

        $messages = [];
        foreach ($rows as $r) {
            $messages[] = [
                'id'   => (int) $r['id'],
                'user' => [
                    'id'           => (int) $r['uid'],
                    'username'     => $r['username'],
                    'display_name' => $r['display_name'],
                    'avatar'       => $r['avatar'],
                ],
                'body' => $r['body'],
                'ts'   => (int) strtotime($r['created_at']),
            ];
        }
        json_out(['ok' => true, 'messages' => $messages]);
    }
    json_err('Unknown action.');
}

api_require_csrf();

if ($action === 'send') {
    if (!$isMember) {
        json_err('Join the lobby to chat.', 403);
    }
    $body = trim((string) req('body'));
    if ($body === '' || mb_strlen($body) > 1000) {
        json_err('Message must be 1-1000 characters.');
    }
    db()->prepare('INSERT INTO lobby_messages (lobby_id, user_id, body) VALUES (?, ?, ?)')
        ->execute([$lobbyId, $myId, $body]);

    $msg = [
        'id'   => (int) db()->lastInsertId(),
        'user' => user_public($me),
        'body' => $body,
        'ts'   => time(),
    ];
    bus_emit('lobby:' . $lobbyId, 'chat.message', $msg);
    json_out(['ok' => true, 'msg' => $msg]);
}
json_err('Unknown action.');
