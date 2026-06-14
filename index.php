<?php
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('profile.php');
}

$pageTitle = 'Welcome';
require __DIR__ . '/includes/layout/header.php';
?>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
