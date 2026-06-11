<?php
require __DIR__ . '/includes/bootstrap.php';

$me    = require_verified();
$posts = fetch_posts($me, null);

$pageTitle = 'Feed';
$active    = 'feed';
require __DIR__ . '/includes/layout/header.php';
?>
<div class="max-w-xl mx-auto space-y-4">
    <div class="bg-white border border-gray-200 rounded-2xl p-5">
        <form id="composerForm" class="flex items-start gap-3">
            <?= avatar_html($me, 'w-10 h-10 text-base') ?>
            <div class="flex-1 min-w-0">
                <textarea name="body" rows="3" maxlength="2000" required
                          placeholder="What's on your mind, <?= e($me['display_name'] !== '' ? $me['display_name'] : $me['username']) ?>?"
                          class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-violet-500"></textarea>
                <div class="flex justify-end mt-2">
                    <button class="bg-violet-600 hover:bg-violet-700 text-white font-semibold text-sm rounded-xl px-5 py-2">
                        <i class="fa-solid fa-paper-plane mr-1"></i> Post
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div id="postsList" class="space-y-4">
        <?php if (!$posts): ?>
            <div id="emptyFeed" class="text-center py-12 text-gray-400">
                <i class="fa-regular fa-comments text-4xl mb-3"></i>
                <p>Nothing here yet. Post something or <a href="friends.php?tab=find" class="text-violet-600 hover:underline">find friends</a>!</p>
            </div>
        <?php else: ?>
            <?php foreach ($posts as $p) echo post_card_html($p, $me); ?>
        <?php endif; ?>
    </div>

    <?php if (count($posts) === POSTS_PAGE_SIZE): ?>
        <div class="text-center">
            <button data-load-more data-mode="feed" data-before="<?= (int) end($posts)['id'] ?>"
                    class="border border-gray-200 hover:border-violet-300 hover:text-violet-700 text-sm font-medium rounded-xl px-6 py-2.5">
                Load more
            </button>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
