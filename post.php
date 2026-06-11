<?php
require __DIR__ . '/includes/bootstrap.php';

$me   = require_verified();
$post = fetch_post($me, (int) ($_GET['id'] ?? 0));

$pageTitle = 'Post';
require __DIR__ . '/includes/layout/header.php';
?>
<div class="max-w-xl mx-auto">
    <a href="feed.php" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-violet-700 mb-4">
        <i class="fa-solid fa-arrow-left"></i> Back to feed
    </a>
    <?php if (!$post): ?>
        <div class="text-center py-16 text-gray-400">
            <i class="fa-regular fa-face-frown text-4xl mb-3"></i>
            <p>This post does not exist or was deleted.</p>
        </div>
    <?php else: ?>
        <?= post_card_html($post, $me, true) ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
