<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../templates/render.php';

$smtp = google_smtp_settings(setting('smtp', []), trim((string) setting('sender_email', '')) ?: 'hello@omqpro.com');
$batchSize = max(1, (int) ($smtp['batch_size'] ?? 50));
$items = db_all(
    "select q.*, s.unsubscribe_token, s.status as subscriber_status, n.subject
     from email_queue q
     join subscribers s on s.id=q.subscriber_id
     join newsletters n on n.id=q.newsletter_id
     where q.status in ('queued','failed') and q.attempts < 3 and s.status='active' and q.scheduled_at <= now()
     order by q.scheduled_at asc, q.id asc
     limit $batchSize"
);

$sent = $failed = 0;
foreach ($items as $item) {
    db()->prepare('update email_queue set status="sending", attempts=attempts+1, updated_at=now() where id=?')->execute([(int) $item['id']]);
    $subscriber = db_row('select * from subscribers where id=?', [(int) $item['subscriber_id']]);
    $payload = newsletter_payload((int) $item['newsletter_id']);
    $html = render_newsletter_html($payload, 'email', $subscriber);
    $html .= '<img src="' . h(app_url('open/' . $item['tracking_token'] . '.gif')) . '" width="1" height="1" alt="" style="display:none">';
    $result = send_newsletter_mail($item['recipient_email'], $item['subject'], $html);
    if ($result['ok']) {
        db()->prepare('update email_queue set status="sent", sent_at=now(), last_error=null, updated_at=now() where id=?')->execute([(int) $item['id']]);
        $sent++;
    } else {
        db()->prepare('update email_queue set status="failed", last_error=?, updated_at=now() where id=?')->execute([$result['message'], (int) $item['id']]);
        $failed++;
    }
}

$newsletterIds = array_unique(array_map(static function ($item) {
    return (int) $item['newsletter_id'];
}, $items));
foreach ($newsletterIds as $newsletterId) {
    $remaining = (int) db_value("select count(*) from email_queue where newsletter_id=? and status in ('queued','sending','failed')", [$newsletterId]);
    if ($remaining === 0) {
        db()->prepare('update newsletters set status="sent", sent_at=coalesce(sent_at, now()), updated_at=now() where id=?')->execute([$newsletterId]);
    }
}

echo "Sent: $sent\nFailed: $failed\n";
