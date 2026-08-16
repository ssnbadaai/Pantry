<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_layout.php';

$metrics = [
    'Total subscribers' => (int) db_value('select count(*) from subscribers'),
    'Active subscribers' => (int) db_value("select count(*) from subscribers where status = 'active'"),
    'Unsubscribed users' => (int) db_value("select count(*) from subscribers where status = 'unsubscribed'"),
    'Total newsletters' => (int) db_value('select count(*) from newsletters'),
    'Draft newsletters' => (int) db_value("select count(*) from newsletters where status = 'draft'"),
    'Sent newsletters' => (int) db_value("select count(*) from newsletters where status = 'sent'"),
    'Total reads' => total_newsletter_read_count(),
];
$recent = db_all('select * from newsletters order by updated_at desc limit 8');
admin_header('Dashboard', 'dashboard');
?>
<div class="metric-grid mb-4">
    <?php foreach ($metrics as $label => $value): ?>
        <div class="metric-card"><span><?= h($label) ?></span><strong><?= number_format($value) ?></strong></div>
    <?php endforeach; ?>
</div>

<div class="d-flex justify-content-between align-items-center mb-2">
    <h2 class="h5 m-0">Recent newsletters</h2>
    <a class="btn btn-primary btn-sm" href="<?= h(app_url('admin/newsletter-edit.php')) ?>">Create Newsletter</a>
</div>
<div class="table-wrap">
    <table class="table align-middle">
        <thead><tr><th>Title</th><th>Status</th><th>Reads</th><th>Created</th><th>Sent</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($recent as $item): ?>
            <tr>
                <td><?= h($item['title']) ?></td>
                <td><span class="status-pill status-<?= h($item['status']) ?>"><?= h(ucfirst($item['status'])) ?></span></td>
                <td><?= number_format(newsletter_read_count((int) $item['id'])) ?></td>
                <td><?= h($item['created_at']) ?></td>
                <td><?= h($item['sent_at'] ?: '-') ?></td>
                <td class="text-end">
                    <a class="btn btn-sm btn-outline-primary" href="<?= h(app_url('admin/newsletter-edit.php?id=' . $item['id'])) ?>">Edit</a>
                    <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= h(app_url($item['slug'])) ?>">Preview</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$recent): ?><tr><td colspan="6" class="text-muted">No newsletters yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php admin_footer(); ?>
