<?php
/**
 * Shared layout header. Pages set $pageTitle and $active before including.
 */
$me      = current_user();
$flashes = take_flashes();
$active  = $active ?? '';
$unread  = $me ? unread_notif_count((int) $me['id']) : 0;

$navLink = function (string $href, string $key, string $icon, string $label) use ($active): string {
    $cls = $active === $key
        ? 'text-violet-700 bg-violet-50'
        : 'text-gray-600 hover:text-violet-700 hover:bg-violet-50';
    return '<a href="' . e($href) . '" class="flex items-center gap-2 px-3 py-2 rounded-lg font-medium text-sm ' . $cls . '">'
        . '<i class="' . e($icon) . '"></i><span class="hidden sm:inline">' . e($label) . '</span></a>';
};
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Gruubuya') ?> · <?= e((string) cfg('app.name', 'Gruubuya')) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<script>
window.GB = {
    csrf:   <?= json_encode(csrf_token()) ?>,
    uid:    <?= (int) ($me['id'] ?? 0) ?>,
    wsUrl:  <?= json_encode(cfg('ws.url')) ?>,
    unread: <?= $unread ?>
};
</script>
<script defer src="assets/js/app.js"></script>
<script defer src="assets/js/realtime.js"></script>
</head>
<body class="bg-white text-gray-900 min-h-screen flex flex-col">
<nav class="border-b border-gray-200 bg-white sticky top-0 z-40">
    <div class="max-w-5xl mx-auto px-4 h-14 flex items-center gap-2">
        <?php if ($me): ?>
            <?= $navLink('feed.php', 'feed', 'fa-solid fa-house', 'Feed') ?>
            <?= $navLink('lobbies.php', 'lobbies', 'fa-solid fa-people-group', 'Lobbies') ?>
            <?= $navLink('friends.php', 'friends', 'fa-solid fa-user-group', 'Friends') ?>
            <div class="ml-auto flex items-center gap-1">
                <a href="notifications.php" id="notifBell" title="Notifications"
                   class="relative w-10 h-10 flex items-center justify-center rounded-lg text-gray-600 hover:text-violet-700 hover:bg-violet-50">
                    <i class="fa-regular fa-bell text-lg"></i>
                    <span id="notifBadge"
                          class="absolute top-1 right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-violet-600 text-white text-[11px] font-bold flex items-center justify-center<?= $unread > 0 ? '' : ' hidden' ?>"><?= $unread > 99 ? '99+' : $unread ?></span>
                </a>
                <div class="relative">
                    <button type="button" data-dropdown="userMenu" class="flex items-center gap-1 p-1 rounded-lg hover:bg-violet-50">
                        <?= avatar_html($me, 'w-8 h-8 text-sm') ?>
                        <i class="fa-solid fa-chevron-down text-xs text-gray-400"></i>
                    </button>
                    <div id="userMenu" class="hidden absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-xl shadow-lg py-1 z-50">
                        <div class="px-4 py-2 border-b border-gray-100">
                            <p class="font-semibold text-sm truncate"><?= e($me['display_name'] !== '' ? $me['display_name'] : $me['username']) ?></p>
                            <p class="text-xs text-gray-400 truncate">@<?= e($me['username']) ?></p>
                        </div>
                        <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-violet-50"><i class="fa-regular fa-user w-5"></i> Profile</a>
                        <a href="settings.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-violet-50"><i class="fa-solid fa-gear w-5"></i> Settings</a>
                        <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50"><i class="fa-solid fa-arrow-right-from-bracket w-5"></i> Log out</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="ml-auto flex items-center gap-2">
                <a href="login.php" class="px-4 py-2 rounded-lg text-sm font-medium text-gray-700 hover:text-violet-700 hover:bg-violet-50">Log in</a>
                <a href="register.php" class="px-4 py-2 rounded-lg text-sm font-medium bg-violet-600 hover:bg-violet-700 text-white">Sign up</a>
            </div>
        <?php endif; ?>
    </div>
</nav>
<main class="flex-1 w-full max-w-5xl mx-auto px-4 py-6">
<?php foreach ($flashes as $f): ?>
    <div class="mb-4 px-4 py-3 rounded-xl text-sm border <?= $f['type'] === 'error'
        ? 'bg-red-50 border-red-200 text-red-700'
        : ($f['type'] === 'success' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-violet-50 border-violet-200 text-violet-700') ?>">
        <?= e($f['message']) ?>
    </div>
<?php endforeach; ?>
