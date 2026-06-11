<?php
require __DIR__ . '/includes/bootstrap.php';

$me = require_verified();

$st = db()->prepare(
    'SELECT n.*, u.username AS actor_username, u.display_name AS actor_display_name, u.avatar AS actor_avatar
     FROM notifications n LEFT JOIN users u ON u.id = n.actor_id
     WHERE n.user_id = ? ORDER BY n.id DESC LIMIT 50'
);
$st->execute([$me['id']]);
$notifs = $st->fetchAll();

// Visiting the page marks everything read (badge resets; rows fetched above
// keep their unread flag so we can still highlight them once).
db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')->execute([$me['id']]);

$pageTitle = 'Notifications';
require __DIR__ . '/includes/layout/header.php';
?>
<div class="max-w-xl mx-auto space-y-4">
    <h1 class="text-2xl font-bold">Notifications</h1>
    <script>document.addEventListener('DOMContentLoaded', function () { setBell(0); });</script>

    <?php if (!$notifs): ?>
        <div class="text-center py-16 text-gray-400">
            <i class="fa-regular fa-bell text-4xl mb-3"></i>
            <p>No notifications yet.</p>
        </div>
    <?php else: ?>
        <div class="space-y-2">
            <?php foreach ($notifs as $n):
                $data  = json_decode((string) ($n['data'] ?? ''), true) ?: [];
                $actor = $n['actor_id']
                    ? ['id' => $n['actor_id'], 'username' => $n['actor_username'], 'display_name' => $n['actor_display_name'], 'avatar' => $n['actor_avatar']]
                    : null;
                $text = notif_text($n['type'], $actor, $data);
                $url  = notif_url($n['type'], $actor, $data);
            ?>
                <a href="<?= e($url) ?>"
                   class="flex items-center gap-3 border rounded-2xl p-4 hover:border-violet-300 <?= $n['is_read'] ? 'bg-white border-gray-200' : 'bg-violet-50 border-violet-200' ?>">
                    <?php if ($actor): ?>
                        <?= avatar_html($actor, 'w-10 h-10 text-base') ?>
                    <?php else: ?>
                        <span class="w-10 h-10 rounded-full bg-violet-100 text-violet-600 flex items-center justify-center shrink-0">
                            <i class="<?= e(notif_icon($n['type'])) ?>"></i>
                        </span>
                    <?php endif; ?>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-gray-800"><?= e($text) ?></p>
                        <p class="text-xs text-gray-400 mt-0.5"><?= e(time_ago($n['created_at'])) ?></p>
                    </div>
                    <i class="<?= e(notif_icon($n['type'])) ?> text-violet-300"></i>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
