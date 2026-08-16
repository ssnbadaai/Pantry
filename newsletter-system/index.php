<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/templates/render.php';

$route = trim((string) ($_GET['route'] ?? ''), '/');
if ($route === '') {
    $route = 'archive';
}

if ($route === 'subscribe') {
    handle_subscribe();
    exit;
}

if ($route === 'unsubscribe') {
    handle_unsubscribe();
    exit;
}

if ($route === 'archive') {
    public_header('Newsletter Archive');
    $items = db_all("select * from newsletters where status in ('ready','sent','archived') and published_at is not null order by published_at desc");
    echo '<div class="archive-list">';
    foreach ($items as $item) {
        echo '<div class="archive-item"><div><strong>' . h($item['title']) . '</strong><br><span>' . h($item['published_at']) . '</span></div><a href="' . h(app_url($item['slug'])) . '">View</a></div>';
    }
    if (!$items) echo '<p class="text-muted">No published newsletters yet.</p>';
    echo '</div>';
    public_footer();
    exit;
}

if (starts_with($route, 'click/')) {
    $token = substr($route, 6);
    $link = db_row('select * from newsletter_links where tracking_token=?', [$token]);
    if ($link) {
        db()->prepare('insert into email_events (newsletter_id,subscriber_id,event_type,link_id,ip_hash,user_agent,created_at) values (?,?,?,?,?,?,now())')
            ->execute([(int) $link['newsletter_id'], null, 'click', (int) $link['id'], hash('sha256', $_SERVER['REMOTE_ADDR'] ?? ''), substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
        header('Location: ' . $link['url']);
        exit;
    }
}

if (starts_with($route, 'open/')) {
    $token = str_replace('.gif', '', substr($route, 5));
    $queue = db_row('select * from email_queue where tracking_token=?', [$token]);
    if ($queue) {
        db()->prepare('insert into email_events (newsletter_id,subscriber_id,event_type,ip_hash,user_agent,created_at) values (?,?,?,?,?,now())')
            ->execute([(int) $queue['newsletter_id'], (int) $queue['subscriber_id'], 'open', hash('sha256', $_SERVER['REMOTE_ADDR'] ?? ''), substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
    }
    header('Content-Type: image/gif');
    echo base64_decode('R0lGODlhAQABAPAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw==');
    exit;
}

$newsletter = current_admin()
    ? db_row('select * from newsletters where slug=?', [$route])
    : db_row('select * from newsletters where slug=? and status in ("ready","sent","archived")', [$route]);
if (!$newsletter) {
    http_response_code(404);
    public_header('Newsletter not found');
    echo '<div class="public-card"><h1>Newsletter not found</h1><p>This issue is not available.</p></div>';
    public_footer();
    exit;
}

public_header($newsletter['title']);
echo render_newsletter_html(newsletter_payload((int) $newsletter['id']));
public_footer();

function public_header(string $title): void
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . h($title) . '</title><link rel="stylesheet" href="' . h(app_url('assets/vendor/bootstrap/bootstrap.min.css')) . '"><link rel="stylesheet" href="' . h(app_url('assets/css/public.css')) . '"></head><body><main class="public-shell">';
}

function public_footer(): void
{
    echo '</main></body></html>';
}

function starts_with(string $haystack, string $needle): bool
{
    return substr($haystack, 0, strlen($needle)) === $needle;
}

function handle_subscribe(): void
{
    $message = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $started = (int) ($_POST['started_at'] ?? 0);
        if (!empty($_POST['website']) || time() - $started < 2) {
            $message = 'Thank you for subscribing.';
        } else {
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = 'Enter a valid email address.';
            } else {
                db()->prepare(
                    'insert into subscribers (email,first_name,last_name,status,subscription_token,unsubscribe_token,source,subscribed_at,created_at,updated_at)
                     values (?,?,?,?,?,?,?,now(),now(),now())
                     on duplicate key update first_name=values(first_name), last_name=values(last_name), status="active", unsubscribed_at=null, updated_at=now()'
                )->execute([$email, trim((string) ($_POST['first_name'] ?? '')), trim((string) ($_POST['last_name'] ?? '')), 'active', random_token(16), random_token(16), 'public']);
                $message = 'Thank you for subscribing.';
            }
        }
    }
    public_header('Subscribe');
    echo '<div class="public-card"><h1>Subscribe</h1>';
    if ($message) echo '<div class="alert alert-info">' . h($message) . '</div>';
    echo '<form method="post" class="field-stack"><input type="hidden" name="started_at" value="' . time() . '"><label style="display:none">Website <input name="website" tabindex="-1" autocomplete="off"></label><label>Email <input class="form-control" type="email" name="email" required></label><label>First name <input class="form-control" name="first_name"></label><label>Last name <input class="form-control" name="last_name"></label><button class="btn btn-primary">Subscribe</button></form></div>';
    public_footer();
}

function handle_unsubscribe(): void
{
    $token = trim((string) ($_GET['token'] ?? ''));
    $message = 'The unsubscribe link is invalid.';
    if ($token !== '') {
        $subscriber = db_row('select * from subscribers where unsubscribe_token=?', [$token]);
        if ($subscriber) {
            db()->prepare('update subscribers set status="unsubscribed", unsubscribed_at=now(), updated_at=now() where id=?')->execute([(int) $subscriber['id']]);
            db()->prepare('insert into email_events (newsletter_id,subscriber_id,event_type,ip_hash,user_agent,created_at) values (?,?,?,?,?,now())')
                ->execute([null, (int) $subscriber['id'], 'unsubscribe', hash('sha256', $_SERVER['REMOTE_ADDR'] ?? ''), substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
            $message = 'You have successfully unsubscribed from our newsletter.';
        }
    }
    public_header('Unsubscribe');
    echo '<div class="public-card"><h1>Unsubscribe</h1><p>' . h($message) . '</p><p><a href="' . h(app_url('subscribe')) . '">Subscribe again</a></p></div>';
    public_footer();
}
