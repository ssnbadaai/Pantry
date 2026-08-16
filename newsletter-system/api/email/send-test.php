<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_once __DIR__ . '/../../templates/render.php';
require_admin();
require_csrf();

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$newsletterId = (int) ($input['newsletter_id'] ?? 0);
$to = trim((string) ($input['to'] ?? ''));
if (!$newsletterId || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    json_response(['ok' => false, 'message' => 'Enter a valid test email address.'], 422);
}
$newsletter = db_row('select * from newsletters where id=?', [$newsletterId]);
if (!$newsletter) {
    json_response(['ok' => false, 'message' => 'Newsletter not found.'], 404);
}
$html = '<p><strong>TEST EMAIL</strong></p>' . render_newsletter_html(newsletter_payload($newsletterId), 'email');
$result = send_newsletter_mail($to, $newsletter['subject'], $html, '', true);
json_response($result, $result['ok'] ? 200 : 500);
