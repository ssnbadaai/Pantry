<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/media_utils.php';
require_admin();
require_csrf();

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$mediaId = (int) ($input['media_id'] ?? 0);
$crop = $input['crop'] ?? [];

try {
    $media = db_row('select * from media where id = ?', [$mediaId]);
    if (!$media) {
        throw new RuntimeException('Choose an image before cropping.');
    }
    $sourceRel = $media['original_file_path'] ?: $media['file_path'];
    $sourcePath = public_path($sourceRel);
    if (!file_exists($sourcePath)) {
        throw new RuntimeException('The original image is missing.');
    }
    $info = image_info_or_fail($sourcePath);
    $ext = pathinfo($media['file_path'], PATHINFO_EXTENSION) ?: 'jpg';
    $base = date('YmdHis') . '-' . random_token(8) . '.' . $ext;
    $targetRel = 'uploads/optimized/' . $base;
    $targetPath = public_path($targetRel);
    $result = crop_image_copy($sourcePath, $targetPath, $info['mime'], $crop, (int) ($config['uploads']['quality'] ?? 82));
    db()->prepare('insert into media (file_name,file_path,original_file_path,mime_type,width,height,file_size,alt_text,created_at,updated_at) values (?,?,?,?,?,?,?,?,now(),now())')
        ->execute([$media['file_name'], $targetRel, $sourceRel, $info['mime'], $result['width'], $result['height'], $result['size'], $media['alt_text']]);
    $newMedia = db_row('select * from media where id = ?', [(int) db()->lastInsertId()]);
    json_response(['ok' => true, 'media' => $newMedia]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => $e->getMessage()], 422);
}
