<?php
/**
 * Sample configuration. Copy to includes/config.php, fill in real values,
 * and upload manually via cPanel File Manager. The real config.php is
 * gitignored and must never be committed.
 */
return [
    'db' => [
        'host'    => 'localhost',
        'name'    => 'database_name',
        'user'    => 'database_user',
        'pass'    => 'database_password',
        'charset' => 'utf8mb4',
    ],
    'mail' => [
        'host'      => 'mail.example.com',
        'port'      => 465,
        'secure'    => 'ssl',
        'user'      => 'core@example.com',
        'pass'      => 'mail_password',
        'from'      => 'core@example.com',
        'from_name' => 'Gruubuya',
    ],
    'app' => [
        'name' => 'Gruubuya',
        'url'  => 'https://gruubuya.com',
    ],
    // Optional: realtime WebSocket server (ws/server.php). Omit this block
    // entirely to use AJAX polling only — everything still works.
    // 'ws' => [
    //     'port' => 8090,                          // port server.php binds to
    //     'url'  => 'wss://gruubuya.com:8090',     // URL browsers connect to
    // ],
];
