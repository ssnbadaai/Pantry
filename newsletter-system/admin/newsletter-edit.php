<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    $slug = 'issue-' . date('Y-m-d-His');
    db()->prepare(
        'insert into newsletters (internal_name,title,subject,preview_text,slug,issue_number,sender_name,sender_email,reply_to,direction,status,created_at,updated_at)
         values (?,?,?,?,?,?,?,?,?,"rtl","draft",now(),now())'
    )->execute([
        'New newsletter',
        'New Newsletter',
        'New Newsletter',
        '',
        $slug,
        '',
        (string) setting('sender_name', 'Newsletter'),
        (string) setting('sender_email', ''),
        (string) setting('reply_to', ''),
    ]);
    $id = (int) db()->lastInsertId();
    db()->prepare('insert into newsletter_sections (newsletter_id,section_type,title,sort_order,settings_json,created_at,updated_at) values (?,"content","نشرة البانتري",0,"{}",now(),now())')
        ->execute([$id]);
    redirect('admin/newsletter-edit.php?id=' . $id);
}

$newsletter = db_row('select * from newsletters where id = ?', [$id]);
if (!$newsletter) {
    http_response_code(404);
    exit('Newsletter not found');
}

require_once __DIR__ . '/../templates/render.php';
$payload = newsletter_payload($id);
$media = db_all('select * from media order by created_at desc limit 40');
$theme = setting('theme', []);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= h(csrf_token()) ?>">
    <title>Edit Newsletter</title>
    <link rel="stylesheet" href="<?= h(app_url('assets/vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= h(app_url('assets/vendor/cropper/cropper.min.css')) ?>">
    <link rel="stylesheet" href="<?= h(app_url('assets/css/admin.css')) ?>">
</head>
<body>
<div class="builder-shell p-2">
    <div class="builder-toolbar">
        <a class="btn btn-outline-secondary btn-sm" href="<?= h(app_url('admin/newsletters.php')) ?>">Back</a>
        <input class="builder-title" id="newsletterTitle" value="<?= h($newsletter['title']) ?>" aria-label="Newsletter title">
        <span class="save-state" id="saveState">Saved</span>
        <div class="ms-auto d-flex gap-2">
            <button class="btn btn-outline-secondary btn-sm" id="desktopPreview" type="button">Desktop</button>
            <button class="btn btn-outline-secondary btn-sm" id="mobilePreview" type="button">Mobile</button>
            <a class="btn btn-outline-secondary btn-sm" target="_blank" href="<?= h(app_url($newsletter['slug'])) ?>">Web Preview</a>
            <button class="btn btn-outline-primary btn-sm" id="testEmailBtn" type="button">Send Test</button>
            <button class="btn btn-primary btn-sm" id="manualSaveBtn" type="button">Save</button>
            <button class="btn btn-success btn-sm" id="queueSendBtn" type="button">Publish / Send</button>
        </div>
    </div>

    <div class="builder-grid">
        <aside class="builder-side">
            <h2 class="h6">Blocks</h2>
            <div class="block-picker mb-3">
                <button class="btn btn-outline-secondary" data-add-block="headline">Headline</button>
                <button class="btn btn-outline-secondary" data-add-block="text">Text</button>
                <button class="btn btn-outline-secondary" data-add-block="article">Article</button>
                <button class="btn btn-outline-secondary" data-add-block="image">Image</button>
                <button class="btn btn-outline-secondary" data-add-block="button">Button</button>
                <button class="btn btn-outline-secondary" data-add-block="divider">Divider</button>
            </div>
            <button class="btn btn-primary w-100 mb-4" id="addSectionBtn" type="button">Add Section</button>
            <h2 class="h6">Newsletter</h2>
            <div class="field-stack">
                <label>Internal name <input class="form-control form-control-sm meta-field" data-meta="internal_name" value="<?= h($newsletter['internal_name']) ?>"></label>
                <label>Subject <input class="form-control form-control-sm meta-field" data-meta="subject" value="<?= h($newsletter['subject']) ?>"></label>
                <label>Preview text <textarea class="form-control form-control-sm meta-field" data-meta="preview_text"><?= h($newsletter['preview_text']) ?></textarea></label>
                <label>URL slug <input class="form-control form-control-sm meta-field" data-meta="slug" value="<?= h($newsletter['slug']) ?>"></label>
                <label>Direction
                    <select class="form-select form-select-sm meta-field" data-meta="direction">
                        <?php foreach (['rtl' => 'Arabic / RTL', 'auto' => 'Auto', 'ltr' => 'English / LTR'] as $value => $label): ?>
                            <option value="<?= h($value) ?>" <?= ($newsletter['direction'] ?? 'rtl') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Status
                    <select class="form-select form-select-sm meta-field" data-meta="status">
                        <?php foreach (['draft','ready','scheduled','sent','archived'] as $status): ?>
                            <option value="<?= h($status) ?>" <?= $newsletter['status'] === $status ? 'selected' : '' ?>><?= h(ucfirst($status)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
        </aside>

        <section class="canvas-wrap">
            <div class="newsletter-canvas" id="newsletterCanvas"></div>
        </section>

        <aside class="settings-panel">
            <h2 class="h6">Settings</h2>
            <div id="settingsEmpty" class="text-muted">Select a block in the canvas.</div>
            <div id="settingsForm" class="field-stack d-none"></div>
        </aside>
    </div>
</div>

<div class="modal fade" id="mediaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title h5">Choose image</h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="uploadForm" class="d-flex gap-2 mb-3">
                    <input class="form-control" type="file" name="image" accept="image/jpeg,image/png,image/webp" required>
                    <button class="btn btn-primary">Upload</button>
                </form>
                <div class="media-grid" id="mediaGrid">
                    <?php foreach ($media as $item): ?>
                        <button class="media-card text-start p-0" type="button" data-media-id="<?= (int) $item['id'] ?>" data-url="<?= h(app_url($item['file_path'])) ?>" data-alt="<?= h($item['alt_text']) ?>" data-width="<?= (int) $item['width'] ?>" data-height="<?= (int) $item['height'] ?>">
                            <img src="<?= h(app_url($item['file_path'])) ?>" alt="<?= h($item['alt_text']) ?>">
                            <span class="meta d-block text-truncate"><?= h($item['file_name']) ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="cropModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title h5">Crop image</h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="btn-group btn-group-sm mb-3" role="group" aria-label="Crop ratios">
                    <button class="btn btn-outline-secondary" data-ratio="NaN" type="button">Original</button>
                    <button class="btn btn-outline-secondary" data-ratio="1" type="button">1:1</button>
                    <button class="btn btn-outline-secondary" data-ratio="1.3333" type="button">4:3</button>
                    <button class="btn btn-outline-secondary" data-ratio="1.5" type="button">3:2</button>
                    <button class="btn btn-outline-secondary" data-ratio="1.7777" type="button">16:9</button>
                    <button class="btn btn-outline-secondary" data-ratio="0.8" type="button">4:5</button>
                </div>
                <label class="d-flex align-items-center gap-3 mb-3">Zoom / Scale
                    <input class="form-range" id="zoomSlider" type="range" min="0.2" max="3" value="1" step="0.05">
                </label>
                <div class="crop-stage"><img id="cropImage" alt=""></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" id="rotateLeftBtn" type="button">Rotate Left</button>
                <button class="btn btn-outline-secondary" id="rotateRightBtn" type="button">Rotate Right</button>
                <button class="btn btn-outline-secondary" id="resetCropBtn" type="button">Reset</button>
                <button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
                <button class="btn btn-primary" id="applyCropBtn" type="button">Apply</button>
            </div>
        </div>
    </div>
</div>

<script>
window.NEWSLETTER_APP = {
    newsletterId: <?= (int) $id ?>,
    csrf: <?= json_encode(csrf_token()) ?>,
    appUrl: <?= json_encode(rtrim(app_url(), '/')) ?>,
    payload: <?= json_encode($payload, JSON_UNESCAPED_SLASHES) ?>,
    defaultSectionTitle: <?= json_encode('نشرة البانتري', JSON_UNESCAPED_UNICODE) ?>,
    footerHtml: <?= json_encode(render_newsletter_footer_html($theme, $newsletter, null), JSON_UNESCAPED_SLASHES) ?>
};
</script>
<script src="<?= h(app_url('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= h(app_url('assets/vendor/sortable/Sortable.min.js')) ?>"></script>
<script src="<?= h(app_url('assets/vendor/cropper/cropper.min.js')) ?>"></script>
<script src="<?= h(app_url('assets/js/builder.js')) ?>"></script>
</body>
</html>
