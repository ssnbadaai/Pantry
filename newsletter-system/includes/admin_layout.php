<?php

declare(strict_types=1);

function admin_header(string $title, string $active = ''): void
{
    $admin = require_admin();
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= h(csrf_token()) ?>">
    <title><?= h($title) ?> - Newsletter Admin</title>
    <link rel="stylesheet" href="<?= h(app_url('assets/vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= h(app_url('assets/vendor/cropper/cropper.min.css')) ?>">
    <link rel="stylesheet" href="<?= h(app_url('assets/css/admin.css')) ?>">
</head>
<body class="admin-shell">
<aside class="admin-sidebar">
    <div class="brand">
        <span class="brand-mark">P</span>
        <span>Pantry Bulletin</span>
    </div>
    <nav>
        <?php
        $items = [
            'dashboard' => ['Dashboard', 'admin/index.php'],
            'newsletters' => ['Newsletters', 'admin/newsletters.php'],
            'create' => ['Create Newsletter', 'admin/newsletter-edit.php'],
            'subscribers' => ['Subscribers', 'admin/subscribers.php'],
            'media' => ['Media Library', 'admin/media.php'],
            'settings' => ['Settings', 'admin/settings.php'],
        ];
        foreach ($items as $key => [$label, $url]): ?>
            <a class="<?= $active === $key ? 'active' : '' ?>" href="<?= h(app_url($url)) ?>"><?= h($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <span><?= h($admin['name']) ?></span>
        <a href="<?= h(app_url('admin/logout.php')) ?>">Logout</a>
    </div>
</aside>
<main class="admin-main">
    <header class="page-header">
        <h1><?= h($title) ?></h1>
    </header>
    <?php
}

function admin_footer(): void
{
    ?>
</main>
<script src="<?= h(app_url('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
</body>
</html>
    <?php
}

