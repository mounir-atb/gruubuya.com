<?php
require __DIR__ . '/../includes/bootstrap.php';

$me     = api_user();
$myId   = (int) $me['id'];
$action = (string) req('action');

function lobby_or_404(int $id): array
{
    $lobby = fetch_lobby($id);
    if (!$lobby) {
        json_err('Lobby not found.', 404);
    }
    return $lobby;
}

function join_lobby(array $lobby, array $me): void
{
    $lobbyId = (int) $lobby['id'];
    $myId    = (int) $me['id'];
    if (is_lobby_member($lobbyId, $myId)) {
        json_out(['ok' => true, 'lobby_id' => $lobbyId]); // already in — just open it
    }
    if ((int) $lobby['member_count'] >= (int) $lobby['max_members']) {
        json_err('This lobby is full.');
    }
    db()->prepare('INSERT INTO lobby_members (lobby_id, user_id) VALUES (?, ?)')->execute([$lobbyId, $myId]);
    bus_emit('lobby:' . $lobbyId, 'member.join', ['user' => user_public($me)]);
    json_out(['ok' => true, 'lobby_id' => $lobbyId]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($action === 'members') {
        $lobby = lobby_or_404((int) req('lobby_id'));
        if (!is_lobby_member((int) $lobby['id'], $myId) && $lobby['privacy'] !== 'public') {
            json_err('Members only.', 403);
        }
        $members = [];
        foreach (lobby_members((int) $lobby['id']) as $u) {
            $members[] = user_public($u) + ['role' => $u['role'], 'online' => is_online($u['last_seen_at'])];
        }
        json_out(['ok' => true, 'members' => $members]);
    }
    json_err('Unknown action.');
}

api_require_csrf();

switch ($action) {
    case 'create': {
        $name    = trim((string) req('name'));
        $desc    = trim((string) req('description'));
        $privacy = (string) req('privacy', 'public');
        $max     = (int) req('max_members', 20);

        if (mb_strlen($name) < 3 || mb_strlen($name) > 60) {
            json_err('Lobby name must be 3-60 characters.');
        }
        if (mb_strlen($desc) > 300) {
            json_err('Description must be 300 characters or fewer.');
        }
        if (!in_array($privacy, ['public', 'private'], true)) {
            json_err('Invalid privacy.');
        }
        if ($max < 2 || $max > 100) {
            json_err('Max members must be between 2 and 100.');
        }

        do {
            $code = substr(strtoupper(bin2hex(random_bytes(6))), 0, 8);
            $st   = db()->prepare('SELECT 1 FROM lobbies WHERE code = ?');
            $st->execute([$code]);
        } while ($st->fetch());

        db()->prepare(
            'INSERT INTO lobbies (owner_id, name, description, privacy, code, max_members) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$myId, $name, $desc, $privacy, $code, $max]);
        $lobbyId = (int) db()->lastInsertId();
        db()->prepare('INSERT INTO lobby_members (lobby_id, user_id, role) VALUES (?, ?, \'owner\')')
            ->execute([$lobbyId, $myId]);
        json_out(['ok' => true, 'lobby_id' => $lobbyId]);
    }

    case 'join': {
        $lobby = lobby_or_404((int) req('lobby_id'));
        if ($lobby['privacy'] !== 'public') {
            json_err('This lobby is private — join with its invite code.');
        }
        join_lobby($lobby, $me);
    }

    // join_lobby exits via json_out

    case 'join_code': {
        $code = strtoupper(trim((string) req('code')));
        $st   = db()->prepare('SELECT id FROM lobbies WHERE code = ?');
        $st->execute([$code]);
        $row = $st->fetch();
        if (!$row) {
            json_err('No lobby with that code.');
        }
        join_lobby(lobby_or_404((int) $row['id']), $me);
    }

    case 'leave': {
        $lobby = lobby_or_404((int) req('lobby_id'));
        $role  = lobby_member_role((int) $lobby['id'], $myId);
        if ($role === null) {
            json_err('You are not in this lobby.');
        }
        if ($role === 'owner') {
            json_err('Owners cannot leave — delete the lobby instead.');
        }
        db()->prepare('DELETE FROM lobby_members WHERE lobby_id = ? AND user_id = ?')
            ->execute([$lobby['id'], $myId]);
        bus_emit('lobby:' . $lobby['id'], 'member.leave', ['user_id' => $myId, 'kicked' => false]);
        json_out(['ok' => true]);
    }

    case 'kick': {
        $lobby = lobby_or_404((int) req('lobby_id'));
        if (lobby_member_role((int) $lobby['id'], $myId) !== 'owner') {
            json_err('Only the owner can kick members.', 403);
        }
        $targetId = (int) req('user_id');
        if ($targetId === $myId) {
            json_err('You cannot kick yourself.');
        }
        $st = db()->prepare('DELETE FROM lobby_members WHERE lobby_id = ? AND user_id = ? AND role != \'owner\'');
        $st->execute([$lobby['id'], $targetId]);
        if ($st->rowCount() === 0) {
            json_err('That user is not a kickable member.');
        }
        bus_emit('lobby:' . $lobby['id'], 'member.leave', ['user_id' => $targetId, 'kicked' => true]);
        notify($targetId, null, 'lobby_kick', ['lobby_name' => $lobby['name']]);
        json_out(['ok' => true]);
    }

    case 'delete': {
        $lobby = lobby_or_404((int) req('lobby_id'));
        if ((int) $lobby['owner_id'] !== $myId) {
            json_err('Only the owner can delete a lobby.', 403);
        }
        db()->prepare('DELETE FROM lobbies WHERE id = ?')->execute([$lobby['id']]);
        bus_emit('lobby:' . $lobby['id'], 'lobby.deleted', []);
        json_out(['ok' => true]);
    }
}
json_err('Unknown action.');
