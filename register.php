<?php
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('profile.php');
}

$errors = [];
$old    = ['username' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $errors[] = 'Session expired, please try again.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $email    = trim((string) ($_POST['email'] ?? ''));
        $pass     = (string) ($_POST['password'] ?? '');
        $pass2    = (string) ($_POST['password2'] ?? '');
        $old      = ['username' => $username, 'email' => $email];

        if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $username)) {
            $errors[] = 'Username must be 3-20 characters: letters, numbers, underscores.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (strlen($pass) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($pass !== $pass2) {
            $errors[] = 'Passwords do not match.';
        }
        if (!$errors) {
            $st = db()->prepare('SELECT username, email FROM users WHERE username = ? OR email = ?');
            $st->execute([$username, $email]);
            foreach ($st as $row) {
                if (strcasecmp($row['username'], $username) === 0) {
                    $errors[] = 'That username is already taken.';
                }
                if (strcasecmp($row['email'], $email) === 0) {
                    $errors[] = 'That email is already registered.';
                }
            }
        }
        if (!$errors) {
            db()->prepare(
                'INSERT INTO users (username, email, pass_hash, display_name) VALUES (?, ?, ?, ?)'
            )->execute([$username, $email, password_hash($pass, PASSWORD_DEFAULT), $username]);
            $userId = (int) db()->lastInsertId();

            $user  = find_user_by_id($userId);
            $token = create_token($userId, 'verify', 60 * 60 * 24);
            if (!send_verification_email($user, $token)) {
                flash('error', 'Account created, but the verification email could not be sent. Use the resend button.');
            }
            login_user($userId);
            redirect('resend.php');
        }
    }
}

$pageTitle = 'Sign up';
require __DIR__ . '/includes/layout/header.php';
?>
<div class="max-w-md mx-auto mt-6">
    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-8">
        <h1 class="text-2xl font-bold text-center mb-1">Create your account</h1>
        <p class="text-sm text-gray-500 text-center mb-6">Join Gruubuya — it takes a minute.</p>
        <?php if ($errors): ?>
            <div class="mb-4 px-4 py-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm">
                <ul class="list-disc list-inside space-y-1">
                    <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <form method="post" class="space-y-4">
            <?= csrf_field() ?>
            <div>
                <label class="block text-sm font-medium mb-1" for="username">Username</label>
                <input id="username" name="username" value="<?= e($old['username']) ?>" required minlength="3" maxlength="20"
                       pattern="[A-Za-z0-9_]+" autocomplete="username"
                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-violet-500">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="email">Email</label>
                <input id="email" name="email" type="email" value="<?= e($old['email']) ?>" required maxlength="190"
                       autocomplete="email"
                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-violet-500">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="password">Password</label>
                <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"
                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-violet-500">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="password2">Confirm password</label>
                <input id="password2" name="password2" type="password" required minlength="8" autocomplete="new-password"
                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-violet-500">
            </div>
            <button class="w-full bg-violet-600 hover:bg-violet-700 text-white font-semibold rounded-xl py-2.5">
                <i class="fa-solid fa-user-plus mr-1"></i> Sign up
            </button>
        </form>
        <p class="text-sm text-gray-500 text-center mt-5">
            Already have an account?
            <a href="login.php" class="text-violet-600 hover:text-violet-700 font-medium">Log in</a>
        </p>
    </div>
</div>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
