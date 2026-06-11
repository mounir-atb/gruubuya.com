<?php
declare(strict_types=1);

/**
 * Gruubuya WebSocket server — dependency-free push proxy over the events
 * table. Browsers authenticate with a single-use token (api/ws_token.php),
 * subscribe to channels, and receive new `events` rows in realtime.
 *
 * Run from the cPanel Terminal (or a cron @reboot entry):
 *     php ws/server.php
 *
 * Requires the optional 'ws' block in includes/config.php. If this server
 * is not running, clients automatically fall back to AJAX polling.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require __DIR__ . '/../includes/bootstrap.php';

const WS_GUID          = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
const MAX_FRAME_BYTES  = 65536;
const EVENT_POLL_SECS  = 0.4;

function ws_db(): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        cfg('db.host'),
        cfg('db.name'),
        cfg('db.charset', 'utf8mb4')
    );
    return new PDO($dsn, cfg('db.user'), cfg('db.pass'), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}

function ws_encode(string $payload, int $opcode = 1): string
{
    $len  = strlen($payload);
    $head = chr(0x80 | $opcode);
    if ($len < 126) {
        $head .= chr($len);
    } elseif ($len < 65536) {
        $head .= chr(126) . pack('n', $len);
    } else {
        $head .= chr(127) . pack('J', $len);
    }
    return $head . $payload;
}

/** Pull one complete frame off the buffer; null if incomplete. */
function ws_decode_frame(string &$buf): ?array
{
    if (strlen($buf) < 2) {
        return null;
    }
    $b1     = ord($buf[0]);
    $b2     = ord($buf[1]);
    $opcode = $b1 & 0x0F;
    $masked = ($b2 >> 7) & 1;
    $len    = $b2 & 0x7F;
    $off    = 2;
    if ($len === 126) {
        if (strlen($buf) < 4) {
            return null;
        }
        $len = unpack('n', substr($buf, 2, 2))[1];
        $off = 4;
    } elseif ($len === 127) {
        if (strlen($buf) < 10) {
            return null;
        }
        $len = unpack('J', substr($buf, 2, 8))[1];
        $off = 10;
    }
    if ($len > MAX_FRAME_BYTES) {
        $buf = '';
        return [8, ''];
    }
    $maskKey = '';
    if ($masked) {
        if (strlen($buf) < $off + 4) {
            return null;
        }
        $maskKey = substr($buf, $off, 4);
        $off    += 4;
    }
    if (strlen($buf) < $off + $len) {
        return null;
    }
    $payload = substr($buf, $off, $len);
    $buf     = substr($buf, $off + $len);
    if ($masked) {
        $out = '';
        for ($i = 0; $i < $len; $i++) {
            $out .= $payload[$i] ^ $maskKey[$i % 4];
        }
        $payload = $out;
    }
    return [$opcode, $payload];
}

function ws_handshake(array &$client): bool
{
    $pos     = strpos($client['buf'], "\r\n\r\n");
    $headers = substr($client['buf'], 0, $pos);
    $client['buf'] = substr($client['buf'], $pos + 4);
    if (!preg_match('/Sec-WebSocket-Key:\s*(\S+)/i', $headers, $m)) {
        return false;
    }
    $accept = base64_encode(sha1($m[1] . WS_GUID, true));
    fwrite(
        $client['sock'],
        "HTTP/1.1 101 Switching Protocols\r\n"
        . "Upgrade: websocket\r\n"
        . "Connection: Upgrade\r\n"
        . "Sec-WebSocket-Accept: $accept\r\n\r\n"
    );
    $client['shaken'] = true;
    return true;
}

function drop(array &$clients, int $id): void
{
    if (isset($clients[$id])) {
        @fclose($clients[$id]['sock']);
        unset($clients[$id]);
    }
}

function send_json($sock, array $data): void
{
    @fwrite($sock, ws_encode(json_encode($data, JSON_UNESCAPED_UNICODE)));
}

/** Channels a user may listen on: own user channel + their lobbies. */
function user_channels(PDO $db, int $userId): array
{
    $channels = ['user:' . $userId];
    $st = $db->prepare('SELECT lobby_id FROM lobby_members WHERE user_id = ?');
    $st->execute([$userId]);
    foreach ($st as $row) {
        $channels[] = 'lobby:' . (int) $row['lobby_id'];
    }
    return $channels;
}

function handle_message(PDO $db, array &$clients, int $id, string $raw): void
{
    $msg = json_decode($raw, true);
    if (!is_array($msg)) {
        return;
    }
    $c = &$clients[$id];

    if (($msg['type'] ?? '') === 'auth') {
        $token = (string) ($msg['token'] ?? '');
        $st = $db->prepare(
            'SELECT id, user_id FROM ws_tokens
             WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()'
        );
        $st->execute([hash('sha256', $token)]);
        $row = $st->fetch();
        if (!$row) {
            send_json($c['sock'], ['type' => 'error', 'error' => 'auth_failed']);
            drop($clients, $id);
            return;
        }
        $db->prepare('UPDATE ws_tokens SET used_at = NOW() WHERE id = ?')->execute([$row['id']]);
        $c['user_id']  = (int) $row['user_id'];
        $c['channels'] = user_channels($db, $c['user_id']);
        send_json($c['sock'], ['type' => 'ready']);
        return;
    }

    if (($msg['type'] ?? '') === 'subscribe' && $c['user_id'] > 0) {
        $channel = (string) ($msg['channel'] ?? '');
        if (in_array($channel, $c['channels'], true)) {
            return; // already allowed
        }
        if (preg_match('/^lobby:(\d+)$/', $channel, $m)) {
            $st = $db->prepare('SELECT 1 FROM lobby_members WHERE lobby_id = ? AND user_id = ?');
            $st->execute([(int) $m[1], $c['user_id']]);
            if ($st->fetch()) {
                $c['channels'][] = $channel;
            }
        }
    }
}

// ---------------------------------------------------------------- main loop

$port   = (int) cfg('ws.port', 8090);
$server = @stream_socket_server('tcp://0.0.0.0:' . $port, $errno, $errstr);
if (!$server) {
    fwrite(STDERR, "Cannot bind port $port: $errstr\n");
    exit(1);
}
echo "Gruubuya WS server listening on :$port\n";

$db = ws_db();
$lastEventId = (int) $db->query('SELECT COALESCE(MAX(id), 0) FROM events')->fetchColumn();

$clients  = [];
$lastPoll = 0.0;

while (true) {
    $read = [$server];
    foreach ($clients as $c) {
        $read[] = $c['sock'];
    }
    $write = $except = null;
    @stream_select($read, $write, $except, 0, 200000);

    foreach ($read as $sock) {
        if ($sock === $server) {
            $new = @stream_socket_accept($server, 0);
            if ($new) {
                stream_set_blocking($new, false);
                $clients[(int) $new] = [
                    'sock'     => $new,
                    'buf'      => '',
                    'shaken'   => false,
                    'user_id'  => 0,
                    'channels' => [],
                ];
            }
            continue;
        }

        $id = (int) $sock;
        if (!isset($clients[$id])) {
            continue;
        }
        $data = @fread($sock, 8192);
        if ($data === '' || $data === false) {
            if (feof($sock)) {
                drop($clients, $id);
            }
            continue;
        }
        $clients[$id]['buf'] .= $data;

        if (!$clients[$id]['shaken']) {
            if (str_contains($clients[$id]['buf'], "\r\n\r\n") && !ws_handshake($clients[$id])) {
                drop($clients, $id);
            }
            continue;
        }

        while (isset($clients[$id]) && ($frame = ws_decode_frame($clients[$id]['buf'])) !== null) {
            [$opcode, $payload] = $frame;
            if ($opcode === 8) {            // close
                drop($clients, $id);
            } elseif ($opcode === 9) {      // ping -> pong
                @fwrite($sock, ws_encode($payload, 10));
            } elseif ($opcode === 1) {      // text
                handle_message($db, $clients, $id, $payload);
            }
        }
    }

    $now = microtime(true);
    if ($now - $lastPoll >= EVENT_POLL_SECS) {
        $lastPoll = $now;
        try {
            $st = $db->prepare('SELECT id, channel, type, payload FROM events WHERE id > ? ORDER BY id LIMIT 500');
            $st->execute([$lastEventId]);
            foreach ($st as $ev) {
                $lastEventId = max($lastEventId, (int) $ev['id']);
                $frame = ws_encode(json_encode([
                    'id'      => (int) $ev['id'],
                    'channel' => $ev['channel'],
                    'type'    => $ev['type'],
                    'payload' => json_decode($ev['payload'], true),
                ], JSON_UNESCAPED_UNICODE));
                foreach ($clients as $c) {
                    if ($c['user_id'] > 0 && in_array($ev['channel'], $c['channels'], true)) {
                        @fwrite($c['sock'], $frame);
                    }
                }
            }
        } catch (Throwable $e) {
            fwrite(STDERR, 'DB error: ' . $e->getMessage() . "\n");
            try {
                $db = ws_db(); // MySQL connection likely timed out — reconnect
            } catch (Throwable $e2) {
                sleep(2);
            }
        }
    }
}
