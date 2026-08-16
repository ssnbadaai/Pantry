<?php

declare(strict_types=1);

function require_gd(): void
{
    if (!extension_loaded('gd')) {
        throw new RuntimeException('PHP GD is required for image upload and cropping. Enable GD in cPanel PHP extensions.');
    }
}

function image_info_or_fail(string $path): array
{
    require_gd();
    $info = getimagesize($path);
    if (!$info) {
        throw new RuntimeException('The uploaded file is not a valid image.');
    }
    $mime = $info['mime'] ?? '';
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        throw new RuntimeException('Only JPG, PNG, and WEBP images are allowed.');
    }
    return ['width' => (int) $info[0], 'height' => (int) $info[1], 'mime' => $mime];
}

function image_resource_from(string $path, string $mime)
{
    if ($mime === 'image/jpeg') {
        return imagecreatefromjpeg($path);
    }
    if ($mime === 'image/png') {
        return imagecreatefrompng($path);
    }
    if ($mime === 'image/webp') {
        return imagecreatefromwebp($path);
    }
    throw new RuntimeException('Unsupported image type.');
}

function save_image_resource($image, string $path, string $mime, int $quality): void
{
    if ($mime === 'image/png') {
        imagepng($image, $path, 7);
        return;
    }
    if ($mime === 'image/webp' && function_exists('imagewebp')) {
        imagewebp($image, $path, $quality);
        return;
    }
    imagejpeg($image, $path, $quality);
}

function create_optimized_copy(string $source, string $target, string $mime, int $maxWidth, int $quality): array
{
    [$width, $height] = getimagesize($source);
    $scale = $width > $maxWidth ? $maxWidth / $width : 1;
    $newWidth = max(1, (int) round($width * $scale));
    $newHeight = max(1, (int) round($height * $scale));
    $src = image_resource_from($source, $mime);
    $dst = imagecreatetruecolor($newWidth, $newHeight);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    save_image_resource($dst, $target, $mime, $quality);
    imagedestroy($src);
    imagedestroy($dst);
    return ['width' => $newWidth, 'height' => $newHeight, 'size' => filesize($target)];
}

function crop_image_copy(string $source, string $target, string $mime, array $crop, int $quality): array
{
    $src = image_resource_from($source, $mime);
    $angle = (float) ($crop['rotate'] ?? 0);
    if ($angle !== 0.0) {
        $rotated = imagerotate($src, -$angle, imagecolorallocatealpha($src, 0, 0, 0, 127));
        if ($rotated) {
            imagesavealpha($rotated, true);
            imagedestroy($src);
            $src = $rotated;
        }
    }
    $sourceWidth = imagesx($src);
    $sourceHeight = imagesy($src);
    $x = max(0, min((int) ($crop['x'] ?? 0), $sourceWidth - 1));
    $y = max(0, min((int) ($crop['y'] ?? 0), $sourceHeight - 1));
    $width = max(1, min((int) ($crop['width'] ?? $sourceWidth), $sourceWidth - $x));
    $height = max(1, min((int) ($crop['height'] ?? $sourceHeight), $sourceHeight - $y));
    $dst = imagecreatetruecolor($width, $height);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagecopyresampled($dst, $src, 0, 0, $x, $y, $width, $height, $width, $height);
    save_image_resource($dst, $target, $mime, $quality);
    imagedestroy($src);
    imagedestroy($dst);
    return ['width' => $width, 'height' => $height, 'size' => filesize($target)];
}
