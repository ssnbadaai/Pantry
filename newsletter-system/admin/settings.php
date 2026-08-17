<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    save_setting('sender_name', trim((string) $_POST['sender_name']));
    save_setting('sender_email', trim((string) $_POST['sender_email']));
    save_setting('reply_to', trim((string) $_POST['reply_to']));
    save_setting('smtp', [
        'host' => trim((string) $_POST['smtp_host']),
        'port' => (int) $_POST['smtp_port'],
        'username' => trim((string) $_POST['smtp_username']),
        'password' => (string) ($_POST['smtp_password'] ?: (setting('smtp', [])['password'] ?? '')),
        'encryption' => trim((string) $_POST['smtp_encryption']),
        'batch_size' => (int) $_POST['batch_size'],
        'batch_delay_seconds' => (int) $_POST['batch_delay_seconds'],
    ]);
    save_setting('theme', [
        'primary' => $_POST['primary'],
        'secondary' => $_POST['secondary'],
        'background' => $_POST['background'],
        'text' => $_POST['text'],
        'link' => $_POST['link'],
        'button' => $_POST['button'],
        'radius' => (int) $_POST['radius'],
        'email_width' => (int) $_POST['email_width'],
    ]);
    save_setting('footer_html', clean_html((string) $_POST['footer_html']));
    redirect('admin/settings.php?saved=1');
}

$smtp = setting('smtp', []);
$theme = setting('theme', []);
$senderName = trim((string) setting('sender_name', '')) ?: 'OMQ';
$senderEmail = trim((string) setting('sender_email', '')) ?: 'hello@omqpro.com';
$replyTo = trim((string) setting('reply_to', '')) ?: $senderEmail;
$smtpHost = trim((string) ($smtp['host'] ?? '')) ?: 'smtp.gmail.com';
$smtpPort = (int) ($smtp['port'] ?? 587) ?: 587;
$smtpUsername = trim((string) ($smtp['username'] ?? '')) ?: $senderEmail;
$smtpEncryption = trim((string) ($smtp['encryption'] ?? '')) ?: 'tls';
$smtpBatchSize = (int) ($smtp['batch_size'] ?? 25) ?: 25;
$smtpDelaySeconds = (int) ($smtp['batch_delay_seconds'] ?? 60) ?: 60;
admin_header('Settings', 'settings');
?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Settings saved.</div><?php endif; ?>
<form method="post" class="field-stack">
    <?= csrf_field() ?>
    <div class="panel">
        <h2 class="h5">Sender and SMTP</h2>
        <p class="text-muted mb-3">Google SMTP is prefilled for the OMQ domain. You only need to confirm the sender account and paste a Google App Password.</p>
        <div class="row g-3">
            <div class="col-md-4"><label>Sender name <input class="form-control" name="sender_name" value="<?= h($senderName) ?>"></label></div>
            <div class="col-md-4"><label>Sender email <input class="form-control" type="email" name="sender_email" placeholder="hello@omqpro.com" value="<?= h($senderEmail) ?>"></label></div>
            <div class="col-md-4"><label>Reply-to email <input class="form-control" type="email" name="reply_to" placeholder="hello@omqpro.com" value="<?= h($replyTo) ?>"></label></div>
            <div class="col-md-4"><label>SMTP host <input class="form-control" name="smtp_host" value="<?= h($smtpHost) ?>"></label></div>
            <div class="col-md-2"><label>Port <input class="form-control" type="number" name="smtp_port" value="<?= h($smtpPort) ?>"></label></div>
            <div class="col-md-3"><label>Google account email <input class="form-control" name="smtp_username" placeholder="hello@omqpro.com" value="<?= h($smtpUsername) ?>"></label></div>
            <div class="col-md-3"><label>Google App Password <input class="form-control" type="password" name="smtp_password" placeholder="Paste app password, or leave blank to keep saved"></label></div>
            <div class="col-md-3"><label>Encryption <select class="form-select" name="smtp_encryption"><option value="tls">TLS</option><option value="ssl" <?= $smtpEncryption === 'ssl' ? 'selected' : '' ?>>SSL</option><option value="">None</option></select></label></div>
            <div class="col-md-3"><label>Batch size <input class="form-control" type="number" name="batch_size" value="<?= h($smtpBatchSize) ?>"></label></div>
            <div class="col-md-3"><label>Delay seconds <input class="form-control" type="number" name="batch_delay_seconds" value="<?= h($smtpDelaySeconds) ?>"></label></div>
        </div>
        <p class="text-muted small mt-3 mb-0">Needed from you: the real Google account address if it is not <code>hello@omqpro.com</code>, and its Google App Password. Do not use the normal Google login password.</p>
    </div>
    <div class="panel">
        <h2 class="h5">Theme placeholder</h2>
        <div class="row g-3">
            <?php foreach (['primary'=>'#2563eb','secondary'=>'#0f172a','background'=>'#f7f8fb','text'=>'#1f2937','link'=>'#2563eb','button'=>'#2563eb'] as $key => $default): ?>
                <div class="col-md-2"><label><?= h(ucfirst($key)) ?><input class="form-control form-control-color" type="color" name="<?= h($key) ?>" value="<?= h($theme[$key] ?? $default) ?>"></label></div>
            <?php endforeach; ?>
            <div class="col-md-3"><label>Border radius <input class="form-control" type="number" name="radius" value="<?= h($theme['radius'] ?? 8) ?>"></label></div>
            <div class="col-md-3"><label>Email width <input class="form-control" type="number" name="email_width" value="<?= h($theme['email_width'] ?? 680) ?>"></label></div>
        </div>
    </div>
    <div class="panel">
        <h2 class="h5">Newsletter footer</h2>
        <textarea class="form-control" rows="5" name="footer_html"><?= h(setting('footer_html', '<p style="margin:0 0 10px;font-weight:bold;color:#0f172a;">Follow @omqpro</p><p style="margin:0;"><a href="https://www.instagram.com/omqpro">Instagram</a> | <a href="https://www.facebook.com/omqpro">Facebook</a> | <a href="https://x.com/omqpro">X</a> | <a href="https://www.linkedin.com/company/omqpro">LinkedIn</a></p>')) ?></textarea>
    </div>
    <button class="btn btn-primary align-self-start">Save settings</button>
</form>
<?php admin_footer(); ?>
