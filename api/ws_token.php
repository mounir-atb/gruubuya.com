<?php
require __DIR__ . '/../includes/bootstrap.php';

/**
 * Issues a short-lived single-use token the browser presents to the
 * WebSocket server (ws/server.php) to authenticate its connection.
 */
$me = api_user();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_err('POST only.', 405);
}
api_require_csrf();

$raw = bin2hex(random_bytes(32));
db()->prepare('DELETE FROM ws_tokens WHERE user_id = ? OR expires_at < NOW()')->execute([$me['id']]);
db()->prepare(
    'INSERT INTO ws_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 120 SECOND))'
)->execute([$me['id'], hash('sha256', $raw)]);

json_out(['ok' => true, 'token' => $raw]);
