<?php
require __DIR__ . '/includes/bootstrap.php';

$me  = require_verified();
$tab = (string) ($_GET['tab'] ?? 'friends');
if (!in_array($tab, ['friends', 'requests', 'sent', 'find'], true)) {
    $tab = 'friends';
}

$friends = friends_of((int) $me['id']);

$st = db()->prepare(
    'SELECT u.id, u.username, u.display_name, u.avatar, u.last_seen_at, f.created_at AS requested_at
     FROM friendships f JOIN users u ON u.id = f.requester_id
     WHERE f.addressee_id = ? AND f.status = \'pending\' ORDER BY f.id DESC'
);
$st->execute([$me['id']]);
$incoming = $st->fetchAll();

$st = db()->prepare(
    'SELECT u.id, u.username, u.display_name, u.avatar, u.last_seen_at, f.created_at AS requested_at
     FROM friendships f JOIN users u ON u.id = f.addressee_id
     WHERE f.requester_id = ? AND f.status = \'pending\' ORDER BY f.id DESC'
);
$st->execute([$me['id']]);
$outgoing = $st->fetchAll();

$pageTitle = 'Friends';
$active    = 'friends';
require __DIR__ . '/includes/layout/header.php';

$tabLink = function (string $key, string $label, int $count) use ($tab): string {
    $cls = $tab === $key
        ? 'bg-violet-600 text-white'
        : 'text-gray-600 hover:text-violet-700 hover:bg-violet-50';
    $badge = $count > 0
        ? ' <span class="text-xs ' . ($tab === $key ? 'text-violet-200' : 'text-gray-400') . '">(' . $count . ')</span>'
        : '';
    return '<a href="friends.php?tab=' . $key . '" class="px-4 py-2 rounded-xl text-sm font-medium ' . $cls . '">'
        . e($label) . $badge . '</a>';
};

$userRow = function (array $u, string $actionsHtml): string {
    $profile = 'profile.php?u=' . rawurlencode($u['username']);
    return '<div class="flex items-center gap-3 bg-white border border-gray-200 rounded-2xl p-4">'
        . '<a href="' . e($profile) . '" class="relative">' . avatar_html($u, 'w-11 h-11 text-base')
        . (is_online($u['last_seen_at'] ?? null) ? '<span class="absolute bottom-0 right-0 w-3 h-3 rounded-full bg-green-500 border-2 border-white"></span>' : '')
        . '</a>'
        . '<div class="min-w-0 flex-1">'
        . '<a href="' . e($profile) . '" class="font-semibold hover:text-violet-700 truncate block">'
        . e($u['display_name'] !== '' ? $u['display_name'] : $u['username']) . '</a>'
        . '<p class="text-xs text-gray-400 truncate">@' . e($u['username']) . '</p>'
        . '</div>'
        . '<div class="flex gap-2 shrink-0" data-uid="' . (int) $u['id'] . '">' . $actionsHtml . '</div>'
        . '</div>';
};
?>
<div class="max-w-xl mx-auto space-y-4">
    <h1 class="text-2xl font-bold">Friends</h1>
    <div class="flex gap-2 flex-wrap">
        <?= $tabLink('friends', 'Friends', count($friends)) ?>
        <?= $tabLink('requests', 'Requests', count($incoming)) ?>
        <?= $tabLink('sent', 'Sent', count($outgoing)) ?>
        <?= $tabLink('find', 'Find people', 0) ?>
    </div>

    <?php if ($tab === 'friends'): ?>
        <div class="space-y-3">
            <?php if (!$friends): ?>
                <div class="text-center py-12 text-gray-400">
                    <i class="fa-solid fa-user-group text-4xl mb-3"></i>
                    <p>No friends yet. <a href="friends.php?tab=find" class="text-violet-600 hover:underline">Find people</a> to connect with!</p>
                </div>
            <?php else: foreach ($friends as $u): ?>
                <?= $userRow($u, '<button data-friend-act="unfriend" class="border border-gray-200 hover:border-red-300 hover:text-red-600 text-xs font-medium rounded-lg px-3 py-1.5">Remove</button>') ?>
            <?php endforeach; endif; ?>
        </div>

    <?php elseif ($tab === 'requests'): ?>
        <div class="space-y-3">
            <?php if (!$incoming): ?>
                <div class="text-center py-12 text-gray-400">
                    <i class="fa-regular fa-envelope text-4xl mb-3"></i>
                    <p>No pending requests.</p>
                </div>
            <?php else: foreach ($incoming as $u): ?>
                <?= $userRow(
                    $u,
                    '<button data-friend-act="accept" class="bg-violet-600 hover:bg-violet-700 text-white text-xs font-semibold rounded-lg px-3 py-1.5">Accept</button>'
                    . '<button data-friend-act="decline" class="border border-gray-200 hover:border-red-300 hover:text-red-600 text-xs font-medium rounded-lg px-3 py-1.5">Decline</button>'
                ) ?>
            <?php endforeach; endif; ?>
        </div>

    <?php elseif ($tab === 'sent'): ?>
        <div class="space-y-3">
            <?php if (!$outgoing): ?>
                <div class="text-center py-12 text-gray-400">
                    <i class="fa-regular fa-paper-plane text-4xl mb-3"></i>
                    <p>No outgoing requests.</p>
                </div>
            <?php else: foreach ($outgoing as $u): ?>
                <?= $userRow($u, '<button data-friend-act="cancel" class="border border-gray-200 hover:border-red-300 hover:text-red-600 text-xs font-medium rounded-lg px-3 py-1.5">Cancel</button>') ?>
            <?php endforeach; endif; ?>
        </div>

    <?php else: ?>
        <div class="relative">
            <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
            <input id="friendSearch" placeholder="Search by username or name&hellip;" autocomplete="off"
                   class="w-full border border-gray-200 rounded-xl pl-10 pr-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-violet-500">
        </div>
        <div id="searchResults" class="space-y-3">
            <p class="text-center py-10 text-gray-400 text-sm">Type at least 2 characters to search.</p>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
