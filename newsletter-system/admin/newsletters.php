<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'delete') {
        db()->prepare('delete from newsletters where id = ?')->execute([$id]);
    }
    if ($action === 'duplicate') {
        $source = db_row('select * from newsletters where id = ?', [$id]);
        if ($source) {
            db()->prepare('insert into newsletters (internal_name,title,subject,preview_text,slug,issue_number,sender_name,sender_email,reply_to,direction,status,created_at,updated_at) values (?,?,?,?,?,?,?,?,?,?,"draft",now(),now())')
                ->execute([$source['internal_name'] . ' copy', $source['title'] . ' Copy', $source['subject'], $source['preview_text'], slugify($source['slug'] . '-copy-' . time()), $source['issue_number'], $source['sender_name'], $source['sender_email'], $source['reply_to'], $source['direction'] ?? 'auto']);
            $newId = (int) db()->lastInsertId();
            $sections = db_all('select * from newsletter_sections where newsletter_id = ? order by sort_order', [$id]);
            foreach ($sections as $section) {
                db()->prepare('insert into newsletter_sections (newsletter_id,section_type,title,sort_order,settings_json,created_at,updated_at) values (?,?,?,?,?,now(),now())')
                    ->execute([$newId, $section['section_type'], $section['title'], $section['sort_order'], $section['settings_json']]);
                $newSectionId = (int) db()->lastInsertId();
                foreach (db_all('select * from newsletter_blocks where section_id = ? order by sort_order', [$section['id']]) as $block) {
                    db()->prepare('insert into newsletter_blocks (newsletter_id,section_id,block_type,sort_order,content_json,settings_json,created_at,updated_at) values (?,?,?,?,?,?,now(),now())')
                        ->execute([$newId, $newSectionId, $block['block_type'], $block['sort_order'], $block['content_json'], $block['settings_json']]);
                }
            }
        }
    }
    redirect('admin/newsletters.php');
}

$items = db_all('select * from newsletters order by updated_at desc');
admin_header('Newsletters', 'newsletters');
?>
<div class="d-flex justify-content-end mb-3">
    <a class="btn btn-primary" href="<?= h(app_url('admin/newsletter-edit.php')) ?>">Create Newsletter</a>
</div>
<div class="table-wrap">
    <table class="table align-middle">
        <thead><tr><th>Title</th><th>Subject</th><th>Status</th><th>Updated</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?= h($item['title']) ?></td>
                <td><?= h($item['subject']) ?></td>
                <td><span class="status-pill status-<?= h($item['status']) ?>"><?= h(ucfirst($item['status'])) ?></span></td>
                <td><?= h($item['updated_at']) ?></td>
                <td class="text-end">
                    <a class="btn btn-sm btn-outline-primary" href="<?= h(app_url('admin/newsletter-edit.php?id=' . $item['id'])) ?>">Edit</a>
                    <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= h(app_url($item['slug'])) ?>">Preview</a>
                    <form class="d-inline" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><button class="btn btn-sm btn-outline-secondary" name="action" value="duplicate">Duplicate</button></form>
                    <form class="d-inline" method="post" onsubmit="return confirm('Delete this newsletter?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><button class="btn btn-sm btn-outline-danger" name="action" value="delete">Delete</button></form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="5" class="text-muted">No newsletters yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php admin_footer(); ?>
