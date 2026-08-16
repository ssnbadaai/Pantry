<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/media_utils.php';
require_admin();
require_csrf();

try {
    if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The image could not be uploaded.');
    }
    $maxBytes = (int) (($config['uploads']['max_size_mb'] ?? 10) * 1024 * 1024);
    if ($_FILES['image']['size'] > $maxBytes) {
        throw new RuntimeException('The image could not be uploaded. Maximum file size is ' . (int) $config['uploads']['max_size_mb'] . ' MB.');
    }
    $tmp = $_FILES['image']['tmp_name'];
    $info = image_info_or_fail($tmp);
    $ext = 'jpg';
    if ($info['mime'] === 'image/png') {
        $ext = 'png';
    }
    if ($info['mime'] === 'image/webp') {
        $ext = 'webp';
    }
    $base = date('YmdHis') . '-' . random_token(8) . '.' . $ext;
    $originalRel = 'uploads/original/' . $base;
    $optimizedRel = 'uploads/optimized/' . $base;
    $originalPath = public_path($originalRel);
    $optimizedPath = public_path($optimizedRel);
    if (!move_uploaded_file($tmp, $originalPath)) {
        throw new RuntimeException('The image could not be saved.');
    }
    $optimized = create_optimized_copy($originalPath, $optimizedPath, $info['mime'], (int) ($config['uploads']['max_width'] ?? 2400), (int) ($config['uploads']['quality'] ?? 82));
    $fileName = safe_filename((string) $_FILES['image']['name']);
    db()->prepare('insert into media (file_name,file_path,original_file_path,mime_type,width,height,file_size,alt_text,created_at,updated_at) values (?,?,?,?,?,?,?,?,now(),now())')
        ->execute([$fileName, $optimizedRel, $originalRel, $info['mime'], $optimized['width'], $optimized['height'], $optimized['size'], pathinfo($fileName, PATHINFO_FILENAME)]);
    $media = db_row('select * from media where id = ?', [(int) db()->lastInsertId()]);
    json_response(['ok' => true, 'media' => $media]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => $e->getMessage()], 422);
}
