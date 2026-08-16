<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'delete') {
        $media = db_row('select * from media where id = ?', [$id]);
        if ($media) {
            db()->prepare('delete from media where id = ?')->execute([$id]);
        }
    }
    if (($_POST['action'] ?? '') === 'rename') {
        db()->prepare('update media set file_name=?, alt_text=?, updated_at=now() where id=?')
            ->execute([safe_filename((string) $_POST['file_name']), trim((string) $_POST['alt_text']), $id]);
    }
    redirect('admin/media.php');
}

$q = trim((string) ($_GET['q'] ?? ''));
$items = $q
    ? db_all('select * from media where file_name like ? or alt_text like ? order by created_at desc', ["%$q%", "%$q%"])
    : db_all('select * from media order by created_at desc limit 200');
admin_header('Media Library', 'media');
?>
<form class="d-flex gap-2 mb-3">
    <input class="form-control" name="q" value="<?= h($q) ?>" placeholder="Search images">
    <button class="btn btn-outline-secondary">Search</button>
</form>
<div class="media-grid">
    <?php foreach ($items as $item): ?>
        <div class="media-card">
            <img src="<?= h(app_url($item['file_path'])) ?>" alt="<?= h($item['alt_text']) ?>">
            <div class="meta">
                <form method="post" class="field-stack">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                    <input class="form-control form-control-sm" name="file_name" value="<?= h($item['file_name']) ?>">
                    <input class="form-control form-control-sm" name="alt_text" value="<?= h($item['alt_text']) ?>" placeholder="Alt text">
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-outline-primary" name="action" value="rename">Save</button>
                        <button class="btn btn-sm btn-outline-danger" name="action" value="delete" onclick="return confirm('Delete this media record?')">Delete</button>
                    </div>
                </form>
                <div class="mt-2"><?= (int) $item['width'] ?> x <?= (int) $item['height'] ?> - <?= number_format((int) $item['file_size'] / 1024, 1) ?> KB</div>
                <input class="form-control form-control-sm mt-2" readonly value="<?= h(app_url($item['file_path'])) ?>">
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$items): ?><p class="text-muted">No images found. Upload from the newsletter builder.</p><?php endif; ?>
</div>
<?php admin_footer(); ?>
