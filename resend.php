<?php
require __DIR__ . '/includes/bootstrap.php';

$me = require_login();
if ($me['email_verified_at']) {
    redirect('profile.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        flash('error', 'Session expired, please try again.');
    } else {
        $age = token_age((int) $me['id'], 'verify');
        if ($age !== null && $age < 60) {
            flash('error', 'Please wait ' . (60 - $age) . 's before requesting another email.');
        } else {
            $token = create_token((int) $me['id'], 'verify', 60 * 60 * 24);
            if (send_verification_email($me, $token)) {
                flash('success', 'Verification email sent — check your inbox.');
            } else {
                flash('error', 'Could not send the email right now. Try again in a minute.');
            }
        }
    }
    redirect('resend.php');
}

$pageTitle = 'Verify your email';
require __DIR__ . '/includes/layout/header.php';
?>
<div class="max-w-md mx-auto mt-10 text-center">
    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-8">
        <i class="fa-regular fa-envelope-open text-4xl text-violet-600 mb-4"></i>
        <h1 class="text-xl font-bold mb-2">Check your inbox</h1>
        <p class="text-sm text-gray-500 mb-6">
            We sent a verification link to<br>
            <span class="font-semibold text-gray-800"><?= e($me['email']) ?></span><br>
            Click it to activate your account.
        </p>
        <form method="post" class="space-y-3">
            <?= csrf_field() ?>
            <button class="w-full bg-violet-600 hover:bg-violet-700 text-white font-semibold rounded-xl py-2.5">
                <i class="fa-solid fa-paper-plane mr-1"></i> Resend email
            </button>
        </form>
        <p class="text-xs text-gray-400 mt-5">
            Wrong account? <a href="logout.php" class="text-violet-600 hover:text-violet-700">Log out</a>
        </p>
    </div>
</div>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
