<?php
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('feed.php');
}

$pageTitle = 'Welcome';
require __DIR__ . '/includes/layout/header.php';
?>
<div class="text-center pt-14 pb-10">
    <h1 class="text-4xl sm:text-5xl font-extrabold tracking-tight">
        Hang out on <span class="text-violet-600">Gruubuya</span>
    </h1>
    <p class="mt-4 text-gray-500 max-w-xl mx-auto">
        Share posts with friends, chat in realtime lobbies, and never miss a thing
        with live notifications.
    </p>
    <div class="mt-8 flex items-center justify-center gap-3">
        <a href="register.php" class="bg-violet-600 hover:bg-violet-700 text-white font-semibold rounded-xl px-7 py-3">
            Get started <i class="fa-solid fa-arrow-right ml-1"></i>
        </a>
        <a href="login.php" class="border border-gray-200 hover:border-violet-300 hover:text-violet-700 font-semibold rounded-xl px-7 py-3">
            Log in
        </a>
    </div>
</div>

<div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-6 mb-10">
    <?php
    $features = [
        ['fa-solid fa-user-group', 'Friends & Feed', 'Add friends, share posts, like and comment.'],
        ['fa-solid fa-people-group', 'Lobbies', 'Create public or private rooms and chat live.'],
        ['fa-solid fa-bolt', 'Realtime', 'WebSockets with automatic polling fallback.'],
        ['fa-regular fa-bell', 'Notifications', 'Instant alerts for everything that matters.'],
    ];
    foreach ($features as [$icon, $title, $text]): ?>
        <div class="bg-white border border-gray-200 rounded-2xl p-6 text-center">
            <div class="w-12 h-12 mx-auto rounded-xl bg-violet-50 text-violet-600 flex items-center justify-center text-xl mb-3">
                <i class="<?= e($icon) ?>"></i>
            </div>
            <h3 class="font-semibold mb-1"><?= e($title) ?></h3>
            <p class="text-sm text-gray-500"><?= e($text) ?></p>
        </div>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
