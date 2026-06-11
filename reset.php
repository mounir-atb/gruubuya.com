<?php
require __DIR__ . '/includes/bootstrap.php';

$token = (string) ($_REQUEST['token'] ?? '');
$valid = $token !== '' && peek_token($token, 'reset') !== null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    if (!csrf_ok()) {
        $errors[] = 'Session expired, please try again.';
    } else {
        $pass  = (string) ($_POST['password'] ?? '');
        $pass2 = (string) ($_POST['password2'] ?? '');
        if (strlen($pass) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($pass !== $pass2) {
            $errors[] = 'Passwords do not match.';
        }
        if (!$errors) {
            $userId = consume_token($token, 'reset');
            if ($userId === null) {
                $valid = false;
            } else {
                db()->prepare('UPDATE users SET pass_hash = ? WHERE id = ?')
                    ->execute([password_hash($pass, PASSWORD_DEFAULT), $userId]);
                flash('success', 'Password updated — you can log in now.');
                redirect('login.php');
            }
        }
    }
}

$pageTitle = 'Reset password';
require __DIR__ . '/includes/layout/header.php';

if (!$valid): ?>
    <div class="max-w-md mx-auto mt-10 text-center">
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-8">
            <i class="fa-solid fa-circle-xmark text-4xl text-red-500 mb-4"></i>
            <h1 class="text-xl font-bold mb-2">Invalid or expired link</h1>
            <p class="text-sm text-gray-500 mb-6">This reset link is no longer valid. Request a new one.</p>
            <a href="forgot.php" class="inline-block bg-violet-600 hover:bg-violet-700 text-white font-semibold rounded-xl px-6 py-2.5">
                Request new link
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="max-w-md mx-auto mt-6">
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-8">
            <h1 class="text-2xl font-bold text-center mb-1">Choose a new password</h1>
            <p class="text-sm text-gray-500 text-center mb-6">Make it at least 8 characters.</p>
            <?php if ($errors): ?>
                <div class="mb-4 px-4 py-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm">
                    <ul class="list-disc list-inside space-y-1">
                        <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <form method="post" class="space-y-4">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <div>
                    <label class="block text-sm font-medium mb-1" for="password">New password</label>
                    <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"
                           class="w-full border border-gray-200 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-violet-500">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" for="password2">Confirm new password</label>
                    <input id="password2" name="password2" type="password" required minlength="8" autocomplete="new-password"
                           class="w-full border border-gray-200 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-violet-500">
                </div>
                <button class="w-full bg-violet-600 hover:bg-violet-700 text-white font-semibold rounded-xl py-2.5">
                    <i class="fa-solid fa-key mr-1"></i> Update password
                </button>
            </form>
        </div>
    </div>
<?php endif;
require __DIR__ . '/includes/layout/footer.php';
