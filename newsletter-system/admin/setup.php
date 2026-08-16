<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$hasAdmin = (int) db_value('select count(*) from admins') > 0;
if ($hasAdmin) {
    redirect('admin/login.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
        $error = 'Enter a name, valid email, and password of at least 10 characters.';
    } else {
        db()->prepare('insert into admins (name, email, password_hash, created_at, updated_at) values (?, ?, ?, now(), now())')
            ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
        login_admin($email, $password);
        redirect('admin/index.php');
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Administrator</title>
    <link rel="stylesheet" href="<?= h(app_url('assets/vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= h(app_url('assets/css/admin.css')) ?>">
</head>
<body>
<main class="container py-5" style="max-width:520px">
    <div class="panel">
        <h1 class="h4 mb-3">Create administrator</h1>
        <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
        <form method="post" class="field-stack">
            <?= csrf_field() ?>
            <label>Name <input class="form-control" name="name" required></label>
            <label>Email <input class="form-control" type="email" name="email" required></label>
            <label>Password <input class="form-control" type="password" name="password" minlength="10" required></label>
            <button class="btn btn-primary">Create account</button>
        </form>
    </div>
</main>
</body>
</html>
