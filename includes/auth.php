<?php
declare(strict_types=1);

function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $uid = (int) ($_SESSION['uid'] ?? 0);
    if ($uid <= 0) {
        return $user = null;
    }
    $st = db()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([$uid]);
    $row = $st->fetch();
    if (!$row) {
        unset($_SESSION['uid']);
        return $user = null;
    }
    if ($row['last_seen_at'] === null || strtotime($row['last_seen_at']) < time() - 300) {
        db()->prepare('UPDATE users SET last_seen_at = NOW() WHERE id = ?')->execute([$row['id']]);
        $row['last_seen_at'] = date('Y-m-d H:i:s');
    }
    return $user = $row;
}

function login_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = $userId;
}

function logout_user(): void
{
    $_SESSION = [];
    if (session_id() !== '') {
        session_destroy();
    }
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        redirect('login.php');
    }
    return $u;
}

function require_verified(): array
{
    $u = require_login();
    if (!$u['email_verified_at']) {
        redirect('resend.php');
    }
    return $u;
}

/** API guard: logged in + verified, JSON errors instead of redirects. */
function api_user(): array
{
    $u = current_user();
    if (!$u) {
        json_err('Not logged in.', 401);
    }
    if (!$u['email_verified_at']) {
        json_err('Email not verified.', 403);
    }
    return $u;
}

function api_require_csrf(): void
{
    if (!csrf_ok()) {
        json_err('Invalid CSRF token.', 403);
    }
}

function find_user_by_login(string $usernameOrEmail): ?array
{
    $st = db()->prepare('SELECT * FROM users WHERE username = ? OR email = ?');
    $st->execute([$usernameOrEmail, $usernameOrEmail]);
    return $st->fetch() ?: null;
}

function find_user_by_username(string $username): ?array
{
    $st = db()->prepare('SELECT * FROM users WHERE username = ?');
    $st->execute([$username]);
    return $st->fetch() ?: null;
}

function find_user_by_id(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

// ---------------------------------------------------------------- tokens

function create_token(int $userId, string $type, int $ttlSeconds): string
{
    $raw = bin2hex(random_bytes(32));
    db()->prepare('DELETE FROM user_tokens WHERE user_id = ? AND type = ?')->execute([$userId, $type]);
    db()->prepare(
        'INSERT INTO user_tokens (user_id, type, token_hash, expires_at)
         VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))'
    )->execute([$userId, $type, hash('sha256', $raw), $ttlSeconds]);
    return $raw;
}

function peek_token(string $raw, string $type): ?array
{
    $st = db()->prepare(
        'SELECT * FROM user_tokens
         WHERE token_hash = ? AND type = ? AND used_at IS NULL AND expires_at > NOW()'
    );
    $st->execute([hash('sha256', $raw), $type]);
    return $st->fetch() ?: null;
}

function consume_token(string $raw, string $type): ?int
{
    $t = peek_token($raw, $type);
    if (!$t) {
        return null;
    }
    db()->prepare('UPDATE user_tokens SET used_at = NOW() WHERE id = ?')->execute([$t['id']]);
    return (int) $t['user_id'];
}

/** Seconds since the latest token of this type was issued, or null if none. */
function token_age(int $userId, string $type): ?int
{
    $st = db()->prepare(
        'SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) AS age
         FROM user_tokens WHERE user_id = ? AND type = ? ORDER BY id DESC LIMIT 1'
    );
    $st->execute([$userId, $type]);
    $row = $st->fetch();
    return $row ? (int) $row['age'] : null;
}

// ---------------------------------------------------------------- avatars

function avatar_html(array $user, string $sizeClasses = 'w-10 h-10 text-base'): string
{
    if (!empty($user['avatar'])) {
        return '<img src="' . e($user['avatar']) . '" alt="" class="' . e($sizeClasses)
            . ' rounded-full object-cover shrink-0">';
    }
    $palette = ['bg-violet-600', 'bg-purple-600', 'bg-fuchsia-600', 'bg-indigo-600', 'bg-pink-600'];
    $color   = $palette[abs(crc32(strtolower((string) ($user['username'] ?? '')))) % count($palette)];
    $name    = (string) (($user['display_name'] ?? '') !== '' ? $user['display_name'] : ($user['username'] ?? '?'));
    $letter  = mb_strtoupper(mb_substr($name, 0, 1));
    return '<span class="' . $color . ' ' . e($sizeClasses)
        . ' rounded-full inline-flex items-center justify-center text-white font-semibold select-none shrink-0">'
        . e($letter) . '</span>';
}

function is_online(?string $lastSeen): bool
{
    return $lastSeen !== null && strtotime($lastSeen) > time() - 300;
}
