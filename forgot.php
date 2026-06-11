<?php
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('feed.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        flash('error', 'Session expired, please try again.');
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        $st    = db()->prepare('SELECT * FROM users WHERE email = ?');
        $st->execute([$email]);
        $user = $st->fetch();
        if ($user) {
            $age = token_age((int) $user['id'], 'reset');
            if ($age === null || $age >= 60) {
                $token = create_token((int) $user['id'], 'reset', 60 * 60);
                send_reset_email($user, $token);
            }
        }
        // Same message either way — no account enumeration.
        flash('success', 'If that email is registered, a reset link is on its way.');
    }
    redirect('forgot.php');
}

$pageTitle = 'Forgot password';
require __DIR__ . '/includes/layout/header.php';
?>
<div class="max-w-md mx-auto mt-6">
    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-8">
        <h1 class="text-2xl font-bold text-center mb-1">Forgot your password?</h1>
        <p class="text-sm text-gray-500 text-center mb-6">Enter your email and we'll send you a reset link.</p>
        <form method="post" class="space-y-4">
            <?= csrf_field() ?>
            <div>
                <label class="block text-sm font-medium mb-1" for="email">Email</label>
                <input id="email" name="email" type="email" required maxlength="190" autocomplete="email"
                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-violet-500">
            </div>
            <button class="w-full bg-violet-600 hover:bg-violet-700 text-white font-semibold rounded-xl py-2.5">
                <i class="fa-solid fa-paper-plane mr-1"></i> Send reset link
            </button>
        </form>
        <p class="text-sm text-gray-500 text-center mt-5">
            <a href="login.php" class="text-violet-600 hover:text-violet-700 font-medium">Back to log in</a>
        </p>
    </div>
</div>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
