<?php

declare(strict_types=1);

function current_admin(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    return db_row('select id, name, email from admins where id = ?', [(int) $_SESSION['admin_id']]);
}

function require_admin(): array
{
    $admin = current_admin();
    if (!$admin) {
        header('Location: ' . app_url('admin/login.php'));
        exit;
    }
    return $admin;
}

function login_admin(string $email, string $password): bool
{
    $admin = db_row('select * from admins where email = ?', [$email]);
    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $admin['id'];
    return true;
}

function logout_admin(): void
{
    unset($_SESSION['admin_id']);
    session_regenerate_id(true);
}

