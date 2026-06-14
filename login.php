<?php
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('profile.php');
}

$errors = [];
$old    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $errors[] = 'Session expired, please try again.';
    } else {
        $login = trim((string) ($_POST['login'] ?? ''));
        $pass  = (string) ($_POST['password'] ?? '');
        $old   = $login;

        $user = $login !== '' ? find_user_by_login($login) : null;
        if (!$user || !password_verify($pass, $user['pass_hash'])) {
            usleep(300000); // soften brute-force attempts
            $errors[] = 'Invalid username/email or password.';
        } else {
            login_user((int) $user['id']);
            redirect('profile.php');
        }
    }
}

$pageTitle = 'Log in';
require __DIR__ . '/includes/layout/header.php';
?>
<div class="max-w-md mx-auto mt-6">
    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-8">
        <h1 class="text-2xl font-bold text-center mb-1">Welcome back</h1>
        <p class="text-sm text-gray-500 text-center mb-6">Log in to your Gruubuya account.</p>
        <?php if ($errors): ?>
            <div class="mb-4 px-4 py-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm">
                <?php foreach ($errors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
            </div>
        <?php endif; ?>
        <form method="post" class="space-y-4">
            <?= csrf_field() ?>
            <div>
                <label class="block text-sm font-medium mb-1" for="login">Username or email</label>
                <input id="login" name="login" value="<?= e($old) ?>" required autocomplete="username"
                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-violet-500">
            </div>
            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="block text-sm font-medium" for="password">Password</label>
                    <a href="forgot.php" class="text-xs text-violet-600 hover:text-violet-700">Forgot password?</a>
                </div>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-violet-500">
            </div>
            <button class="w-full bg-violet-600 hover:bg-violet-700 text-white font-semibold rounded-xl py-2.5">
                <i class="fa-solid fa-arrow-right-to-bracket mr-1"></i> Log in
            </button>
        </form>
        <p class="text-sm text-gray-500 text-center mt-5">
            New here?
            <a href="register.php" class="text-violet-600 hover:text-violet-700 font-medium">Create an account</a>
        </p>
    </div>
</div>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
