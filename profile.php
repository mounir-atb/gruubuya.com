<?php
require __DIR__ . '/includes/bootstrap.php';

$me       = require_verified();
$username = trim((string) ($_GET['u'] ?? ''));
$user     = $username === '' ? $me : find_user_by_username($username);

if (!$user) {
    $pageTitle = 'User not found';
    require __DIR__ . '/includes/layout/header.php';
    ?>
    <div class="text-center py-16 text-gray-400">
        <i class="fa-regular fa-face-frown text-4xl mb-3"></i>
        <p>No user named "<?= e($username) ?>".</p>
    </div>
    <?php
    require __DIR__ . '/includes/layout/footer.php';
    exit;
}

$isMe    = (int) $user['id'] === (int) $me['id'];
$state   = $isMe ? '' : friend_state((int) $me['id'], (int) $user['id']);
$friends = friend_count((int) $user['id']);

$pageTitle = $user['display_name'] !== '' ? $user['display_name'] : $user['username'];
require __DIR__ . '/includes/layout/header.php';
?>
<div class="max-w-xl mx-auto space-y-4">
    <div class="bg-white border border-gray-200 rounded-2xl p-6">
        <div class="flex items-start gap-4">
            <?= avatar_html($user, 'w-20 h-20 text-3xl') ?>
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-xl font-bold truncate"><?= e($user['display_name'] !== '' ? $user['display_name'] : $user['username']) ?></h1>
                    <?php if (is_online($user['last_seen_at'])): ?>
                        <span class="w-2.5 h-2.5 rounded-full bg-green-500" title="Online"></span>
                    <?php endif; ?>
                </div>
                <p class="text-gray-400 text-sm">@<?= e($user['username']) ?></p>
                <p class="text-sm text-gray-500 mt-1">
                    <i class="fa-solid fa-user-group mr-1"></i><?= $friends ?> friend<?= $friends === 1 ? '' : 's' ?>
                    <span class="mx-2 text-gray-300">|</span>
                    <i class="fa-regular fa-calendar mr-1"></i>Joined <?= e(date('M Y', strtotime($user['created_at']))) ?>
                </p>
                <?php if ($user['bio'] !== ''): ?>
                    <p class="mt-3 text-sm text-gray-700 break-words"><?= nl2br(e($user['bio'])) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="mt-4 flex gap-2" id="friendAction" data-uid="<?= (int) $user['id'] ?>">
            <?php if ($isMe): ?>
                <a href="settings.php" class="border border-gray-200 hover:border-violet-300 hover:text-violet-700 text-sm font-medium rounded-xl px-5 py-2">
                    <i class="fa-solid fa-pen mr-1"></i> Edit profile
                </a>
            <?php elseif ($state === 'friends'): ?>
                <button data-friend-act="unfriend" class="border border-gray-200 hover:border-red-300 hover:text-red-600 text-sm font-medium rounded-xl px-5 py-2">
                    <i class="fa-solid fa-user-check mr-1"></i> Friends — remove
                </button>
            <?php elseif ($state === 'pending_out'): ?>
                <button data-friend-act="cancel" class="border border-gray-200 hover:border-red-300 hover:text-red-600 text-sm font-medium rounded-xl px-5 py-2">
                    <i class="fa-regular fa-clock mr-1"></i> Request sent — cancel
                </button>
            <?php elseif ($state === 'pending_in'): ?>
                <button data-friend-act="accept" class="bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold rounded-xl px-5 py-2">
                    <i class="fa-solid fa-check mr-1"></i> Accept request
                </button>
                <button data-friend-act="decline" class="border border-gray-200 hover:border-red-300 hover:text-red-600 text-sm font-medium rounded-xl px-5 py-2">
                    Decline
                </button>
            <?php else: ?>
                <button data-friend-act="request" class="bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold rounded-xl px-5 py-2">
                    <i class="fa-solid fa-user-plus mr-1"></i> Add friend
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
