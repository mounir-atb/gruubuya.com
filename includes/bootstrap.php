<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$__configFile = __DIR__ . '/config.php';
if (!is_file($__configFile)) {
    http_response_code(503);
    exit('Setup required: upload includes/config.php (see includes/config.sample.php).');
}
$GLOBALS['__cfg'] = require $__configFile;

function cfg(string $path, mixed $default = null): mixed
{
    $node = $GLOBALS['__cfg'];
    foreach (explode('.', $path) as $key) {
        if (!is_array($node) || !array_key_exists($key, $node)) {
            return $default;
        }
        $node = $node[$key];
    }
    return $node;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            cfg('db.host'),
            cfg('db.name'),
            cfg('db.charset', 'utf8mb4')
        );
        try {
            $pdo = new PDO($dsn, cfg('db.user'), cfg('db.pass'), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('DB connection failed: ' . $e->getMessage());
            http_response_code(503);
            exit('Database unavailable. Check includes/config.php and run db/install.sql if this is a fresh install.');
        }
    }
    return $pdo;
}

if (PHP_SAPI !== 'cli') {
    $__https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('gbsid');
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 30,
        'path'     => '/',
        'secure'   => $__https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
}

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function csrf_token(): string
{
    return $_SESSION['csrf'] ?? '';
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_ok(): bool
{
    $sent = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
    if (!is_string($sent) || $sent === '') {
        $sent = json_body()['csrf'] ?? '';
    }
    return is_string($sent) && $sent !== '' && hash_equals(csrf_token(), $sent);
}

function json_body(): array
{
    static $body = null;
    if ($body === null) {
        $raw     = (string) file_get_contents('php://input');
        $decoded = $raw !== '' ? json_decode($raw, true) : null;
        $body    = is_array($decoded) ? $decoded : [];
    }
    return $body;
}

/** Read a request value from POST, JSON body, or GET (in that order). */
function req(string $key, mixed $default = ''): mixed
{
    return $_POST[$key] ?? json_body()[$key] ?? $_GET[$key] ?? $default;
}

function json_out(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function json_err(string $message, int $code = 400): never
{
    json_out(['ok' => false, 'error' => $message], $code);
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function time_ago(string $datetime): string
{
    $ts   = (int) strtotime($datetime);
    $diff = time() - $ts;
    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . 'm ago';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . 'h ago';
    }
    if ($diff < 604800) {
        return floor($diff / 86400) . 'd ago';
    }
    return date('M j, Y', $ts);
}

require __DIR__ . '/auth.php';
require __DIR__ . '/bus.php';
require __DIR__ . '/social.php';
require __DIR__ . '/mailer.php';
