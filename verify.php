<?php
require __DIR__ . '/includes/bootstrap.php';

$token  = (string) ($_GET['token'] ?? '');
$userId = $token !== '' ? consume_token($token, 'verify') : null;

if ($userId === null) {
    $pageTitle = 'Verification failed';
    require __DIR__ . '/includes/layout/header.php';
    ?>
    <div class="max-w-md mx-auto mt-10 text-center">
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-8">
            <i class="fa-solid fa-circle-xmark text-4xl text-red-500 mb-4"></i>
            <h1 class="text-xl font-bold mb-2">Invalid or expired link</h1>
            <p class="text-sm text-gray-500 mb-6">This verification link is no longer valid. Request a new one and try again.</p>
            <a href="<?= current_user() ? 'resend.php' : 'login.php' ?>"
               class="inline-block bg-violet-600 hover:bg-violet-700 text-white font-semibold rounded-xl px-6 py-2.5">
                <?= current_user() ? 'Resend email' : 'Log in' ?>
            </a>
        </div>
    </div>
    <?php
    require __DIR__ . '/includes/layout/footer.php';
    exit;
}

db()->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = ? AND email_verified_at IS NULL')
    ->execute([$userId]);

flash('success', 'Email verified — welcome to Gruubuya!');
$me = current_user();
redirect($me && (int) $me['id'] === $userId ? 'profile.php' : 'login.php');
