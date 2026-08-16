<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/media_utils.php';
require_admin();
require_csrf();

try {
    if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The cropped image could not be saved.');
    }

    $tmp = $_FILES['image']['tmp_name'];
    $info = image_info_or_fail($tmp);
    $sourceMediaId = (int) ($_POST['source_media_id'] ?? 0);
    $source = $sourceMediaId ? db_row('select * from media where id = ?', [$sourceMediaId]) : null;

    $ext = 'jpg';
    if ($info['mime'] === 'image/png') {
        $ext = 'png';
    }
    if ($info['mime'] === 'image/webp') {
        $ext = 'webp';
    }

    $base = date('YmdHis') . '-' . random_token(8) . '.' . $ext;
    $targetRel = 'uploads/optimized/' . $base;
    $targetPath = public_path($targetRel);

    if (!move_uploaded_file($tmp, $targetPath)) {
        throw new RuntimeException('The cropped image could not be saved.');
    }

    $originalRel = $source['original_file_path'] ?? $source['file_path'] ?? $targetRel;
    $fileName = $source['file_name'] ?? 'crop.' . $ext;
    $altText = $source['alt_text'] ?? '';

    db()->prepare('insert into media (file_name,file_path,original_file_path,mime_type,width,height,file_size,alt_text,created_at,updated_at) values (?,?,?,?,?,?,?,?,now(),now())')
        ->execute([$fileName, $targetRel, $originalRel, $info['mime'], $info['width'], $info['height'], filesize($targetPath), $altText]);

    $media = db_row('select * from media where id = ?', [(int) db()->lastInsertId()]);
    json_response(['ok' => true, 'media' => $media]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => $e->getMessage()], 422);
}
