<?php

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    global $config;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db = $config['database'];
    $charset = $db['charset'] ?? 'utf8mb4';
    $dsn = "mysql:host={$db['host']};dbname={$db['name']};charset={$charset}";
    $pdo = new PDO($dsn, $db['user'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function db_value(string $sql, array $params = [])
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function db_row(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

function db_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function setting(string $key, $default = '')
{
    $value = db_value('select setting_value from settings where setting_key = ?', [$key]);
    if ($value === false || $value === null) {
        return $default;
    }

    $decoded = json_decode((string) $value, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
}

function save_setting(string $key, $value): void
{
    $encoded = is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_SLASHES);
    db()->prepare(
        'insert into settings (setting_key, setting_value, updated_at) values (?, ?, now())
         on duplicate key update setting_value = values(setting_value), updated_at = now()'
    )->execute([$key, $encoded]);
}
