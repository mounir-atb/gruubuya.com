<?php
require __DIR__ . '/includes/bootstrap.php';

$me = require_verified();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        flash('error', 'Session expired, please try again.');
        redirect('settings.php');
    }
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'profile') {
        $displayName = trim((string) ($_POST['display_name'] ?? ''));
        $bio         = trim((string) ($_POST['bio'] ?? ''));
        $errors      = [];

        if ($displayName === '' || mb_strlen($displayName) > 50) {
            $errors[] = 'Display name must be 1-50 characters.';
        }
        if (mb_strlen($bio) > 500) {
            $errors[] = 'Bio must be 500 characters or fewer.';
        }

        $avatarPath = null;
        if (!empty($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
            $f = $_FILES['avatar'];
            $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
            $info   = $f['error'] === UPLOAD_ERR_OK ? @getimagesize($f['tmp_name']) : false;
            $mime   = $info['mime'] ?? '';
            if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 2 * 1024 * 1024 || !isset($extMap[$mime])) {
                $errors[] = 'Avatar must be a JPG, PNG, WebP or GIF up to 2 MB.';
            } else {
                $name = 'u' . $me['id'] . '_' . bin2hex(random_bytes(6)) . '.' . $extMap[$mime];
                $dest = __DIR__ . '/uploads/avatars/' . $name;
                if (!move_uploaded_file($f['tmp_name'], $dest)) {
                    $errors[] = 'Could not save the avatar, try again.';
                } else {
                    $avatarPath = 'uploads/avatars/' . $name;
                }
            }
        }

        if ($errors) {
            foreach ($errors as $err) {
                flash('error', $err);
            }
        } else {
            if ($avatarPath !== null) {
                $old = (string) $me['avatar'];
                if (str_starts_with($old, 'uploads/avatars/') && is_file(__DIR__ . '/' . $old)) {
                    @unlink(__DIR__ . '/' . $old);
                }
                db()->prepare('UPDATE users SET display_name = ?, bio = ?, avatar = ? WHERE id = ?')
                    ->execute([$displayName, $bio, $avatarPath, $me['id']]);
            } else {
                db()->prepare('UPDATE users SET display_name = ?, bio = ? WHERE id = ?')
                    ->execute([$displayName, $bio, $me['id']]);
            }
            flash('success', 'Profile updated.');
        }
        redirect('settings.php');
    }

    if ($action === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        $new2    = (string) ($_POST['new_password2'] ?? '');

        if (!password_verify($current, $me['pass_hash'])) {
            flash('error', 'Current password is incorrect.');
        } elseif (strlen($new) < 8) {
            flash('error', 'New password must be at least 8 characters.');
        } elseif ($new !== $new2) {
            flash('error', 'New passwords do not match.');
        } else {
            db()->prepare('UPDATE users SET pass_hash = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $me['id']]);
            flash('success', 'Password changed.');
        }
        redirect('settings.php');
    }

    redirect('settings.php');
}

$pageTitle = 'Settings';
require __DIR__ . '/includes/layout/header.php';
?>
<div class="max-w-xl mx-auto space-y-5">
    <h1 class="text-2xl font-bold">Settings</h1>

    <div class="bg-white border border-gray-200 rounded-2xl p-6">
        <h2 class="font-semibold mb-4"><i class="fa-regular fa-user mr-2 text-violet-600"></i>Profile</h2>
        <form method="post" enctype="multipart/form-data" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="profile">
            <div class="flex items-center gap-4">
                <?= avatar_html($me, 'w-16 h-16 text-2xl') ?>
                <div class="flex-1">
                    <label class="block text-sm font-medium mb-1" for="avatar">Avatar</label>
                    <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp,image/gif"
                           class="block w-full text-sm text-gray-500 file:mr-3 file:px-4 file:py-2 file:rounded-lg file:border-0 file:bg-violet-50 file:text-violet-700 file:font-medium hover:file:bg-violet-100">
                    <p class="text-xs text-gray-400 mt-1">JPG, PNG, WebP or GIF — max 2 MB.</p>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="display_name">Display name</label>
                <input id="display_name" name="display_name" value="<?= e($me['display_name']) ?>" required maxlength="50"
                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-violet-500">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="bio">Bio</label>
                <textarea id="bio" name="bio" rows="3" maxlength="500"
                          class="w-full border border-gray-200 rounded-xl px-4 py-2.5 resize-none focus:outline-none focus:ring-2 focus:ring-violet-500"><?= e($me['bio']) ?></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Username / email</label>
                <p class="text-sm text-gray-500">@<?= e($me['username']) ?> &middot; <?= e($me['email']) ?></p>
            </div>
            <button class="bg-violet-600 hover:bg-violet-700 text-white font-semibold text-sm rounded-xl px-6 py-2.5">
                <i class="fa-solid fa-floppy-disk mr-1"></i> Save profile
            </button>
        </form>
    </div>

    <div class="bg-white border border-gray-200 rounded-2xl p-6">
        <h2 class="font-semibold mb-4"><i class="fa-solid fa-lock mr-2 text-violet-600"></i>Change password</h2>
        <form method="post" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="password">
            <div>
                <label class="block text-sm font-medium mb-1" for="current_password">Current password</label>
                <input id="current_password" name="current_password" type="password" required autocomplete="current-password"
                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-violet-500">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="new_password">New password</label>
                <input id="new_password" name="new_password" type="password" required minlength="8" autocomplete="new-password"
                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-violet-500">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="new_password2">Confirm new password</label>
                <input id="new_password2" name="new_password2" type="password" required minlength="8" autocomplete="new-password"
                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-violet-500">
            </div>
            <button class="bg-violet-600 hover:bg-violet-700 text-white font-semibold text-sm rounded-xl px-6 py-2.5">
                <i class="fa-solid fa-key mr-1"></i> Change password
            </button>
        </form>
    </div>
</div>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
