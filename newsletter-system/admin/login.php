<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if ((int) db_value('select count(*) from admins') === 0) {
    redirect('admin/setup.php');
}
if (current_admin()) {
    redirect('admin/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (login_admin(trim((string) ($_POST['email'] ?? '')), (string) ($_POST['password'] ?? ''))) {
        redirect('admin/index.php');
    }
    $error = 'Email or password is incorrect.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login</title>
    <link rel="stylesheet" href="<?= h(app_url('assets/vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= h(app_url('assets/css/admin.css')) ?>">
</head>
<body>
<main class="container py-5" style="max-width:460px">
    <div class="panel">
        <h1 class="h4 mb-3">Newsletter admin</h1>
        <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
        <form method="post" class="field-stack">
            <?= csrf_field() ?>
            <label>Email <input class="form-control" type="email" name="email" required></label>
            <label>Password <input class="form-control" type="password" name="password" required></label>
            <button class="btn btn-primary">Log in</button>
        </form>
    </div>
</main>
</body>
</html>
