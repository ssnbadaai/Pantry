<?php

declare(strict_types=1);

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function app_url(string $path = ''): string
{
    global $config;
    $base = rtrim((string) ($config['app_url'] ?? ''), '/');
    return $base . ($path ? '/' . ltrim($path, '/') : '');
}

function public_path(string $path = ''): string
{
    return dirname(__DIR__) . ($path ? '/' . ltrim($path, '/') : '');
}

function redirect(string $path): void
{
    header('Location: ' . app_url($path));
    exit;
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function require_csrf(): void
{
    $token = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) $token)) {
        json_response(['ok' => false, 'message' => 'Your session expired. Refresh and try again.'], 419);
    }
}

function slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?: 'newsletter';
    $value = trim($value, '-');
    return $value ?: 'newsletter';
}

function random_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

function clean_html(string $html): string
{
    $allowed = '<p><br><strong><b><em><i><u><ul><ol><li><a><h1><h2><h3><blockquote>';
    $html = strip_tags($html, $allowed);
    $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
    $html = preg_replace('/javascript\s*:/i', '', $html) ?? $html;
    return $html;
}

function media_url(?array $media): string
{
    if (!$media) {
        return app_url('assets/img/placeholder.svg');
    }
    return app_url($media['file_path']);
}

function upload_url_from_path(string $path): string
{
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return app_url($path);
}

function safe_filename(string $name): string
{
    $name = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $name) ?: 'image';
    return trim($name, '-');
}

function visitor_hash(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return hash('sha256', $ip . '|' . $agent);
}

function record_newsletter_read(int $newsletterId): void
{
    db()->prepare('insert into email_events (newsletter_id,subscriber_id,event_type,ip_hash,user_agent,created_at) values (?,null,"view",?,?,now())')
        ->execute([$newsletterId, visitor_hash(), substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
}

function newsletter_read_count(int $newsletterId): int
{
    $emailReaders = (int) db_value(
        'select count(distinct subscriber_id) from email_events where newsletter_id=? and event_type="open" and subscriber_id is not null',
        [$newsletterId]
    );
    $webReaders = (int) db_value(
        'select count(distinct ip_hash) from email_events where newsletter_id=? and event_type="view" and subscriber_id is null and ip_hash is not null',
        [$newsletterId]
    );
    return $emailReaders + $webReaders;
}

function total_newsletter_read_count(): int
{
    return (int) db_value(
        'select count(*) from (
            select newsletter_id, subscriber_id as reader_key
            from email_events
            where event_type="open" and subscriber_id is not null
            group by newsletter_id, subscriber_id
            union all
            select newsletter_id, ip_hash as reader_key
            from email_events
            where event_type="view" and subscriber_id is null and ip_hash is not null
            group by newsletter_id, ip_hash
        ) as reader_rows'
    );
}
