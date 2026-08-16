<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_admin();
require_csrf();

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$newsletterId = (int) ($input['newsletter_id'] ?? 0);
$newsletter = db_row('select * from newsletters where id=?', [$newsletterId]);
if (!$newsletter) {
    json_response(['ok' => false, 'message' => 'Newsletter not found.'], 404);
}

$activeCount = (int) db_value("select count(*) from subscribers where status='active'");
db()->beginTransaction();
try {
    db()->prepare('update newsletters set status="ready", published_at=coalesce(published_at, now()), updated_at=now() where id=?')->execute([$newsletterId]);
    $subscribers = db_all("select * from subscribers where status='active'");
    $queued = 0;
    foreach ($subscribers as $subscriber) {
        $exists = db_value('select id from email_queue where newsletter_id=? and subscriber_id=?', [$newsletterId, (int) $subscriber['id']]);
        if ($exists) {
            continue;
        }
        db()->prepare('insert into email_queue (newsletter_id,subscriber_id,recipient_email,status,tracking_token,attempts,scheduled_at,created_at,updated_at) values (?,?,?,?,?,0,now(),now(),now())')
            ->execute([$newsletterId, (int) $subscriber['id'], $subscriber['email'], 'queued', random_token(16)]);
        $queued++;
    }
    db()->commit();
    json_response(['ok' => true, 'message' => "Published and queued $queued of $activeCount active subscribers. Run the cron sender to process the queue."]);
} catch (Throwable $e) {
    db()->rollBack();
    json_response(['ok' => false, 'message' => 'The newsletter could not be queued.'], 500);
}
