<?php

declare(strict_types=1);

namespace Amare\Api\Helpers;

class ImageUploadHelper
{
    private const MAX_PIXELS = 25000000;

    /**
     * @return array{width: int, height: int, mime: string}
     */
    public static function inspectUploadedImage(
        array $file,
        array $allowedMimes,
        int $maxBytes,
        int $minWidth = 1,
        int $minHeight = 1
    ): array {
        $tmpName = (string)($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new \InvalidArgumentException('La imagen no se recibio correctamente.');
        }

        $size = (int)($file['size'] ?? 0);
        if ($size <= 0) {
            throw new \InvalidArgumentException('La imagen esta vacia.');
        }
        if ($size > $maxBytes) {
            throw new \InvalidArgumentException('La imagen no debe pesar mas de ' . self::formatBytes($maxBytes) . '.');
        }

        $imageInfo = @getimagesize($tmpName);
        if ($imageInfo === false) {
            throw new \InvalidArgumentException('El archivo debe ser una imagen valida.');
        }

        $width = (int)($imageInfo[0] ?? 0);
        $height = (int)($imageInfo[1] ?? 0);
        $mime = strtolower((string)($imageInfo['mime'] ?? ''));

        if ($width < $minWidth || $height < $minHeight || !in_array($mime, $allowedMimes, true)) {
            throw new \InvalidArgumentException('Sube una imagen clara en jpg, png o webp.');
        }

        if ($width * $height > self::MAX_PIXELS) {
            throw new \InvalidArgumentException('La imagen es demasiado grande. Sube una foto de menor resolucion.');
        }

        return [
            'width' => $width,
            'height' => $height,
            'mime' => $mime,
        ];
    }

    /**
     * Compresses the uploaded image as a flattened JPEG and returns the stored filename.
     */
    public static function saveCompressedUpload(
        array $file,
        string $uploadDir,
        string $filenameBase,
        int $maxWidth,
        int $maxHeight,
        int $quality = 78
    ): string {
        if (!extension_loaded('gd')) {
            throw new \RuntimeException('El servidor no tiene habilitada la extension GD para comprimir imagenes.');
        }

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            throw new \RuntimeException('No se pudo preparar la carpeta de imagenes.');
        }

        $tmpName = (string)($file['tmp_name'] ?? '');
        $info = self::inspectUploadedImage(
            $file,
            ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
            10 * 1024 * 1024
        );

        $source = self::createSourceImage($tmpName, $info['mime']);
        if (!$source) {
            throw new \RuntimeException('No se pudo procesar la imagen.');
        }

        if ($info['mime'] === 'image/jpeg') {
            $source = self::applyJpegOrientation($source, $tmpName);
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = min(1, $maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
        $targetWidth = max(1, (int)round($sourceWidth * $scale));
        $targetHeight = max(1, (int)round($sourceHeight * $scale));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        if (!$target) {
            imagedestroy($source);
            throw new \RuntimeException('No se pudo crear la imagen comprimida.');
        }

        $white = imagecolorallocate($target, 255, 255, 255);
        imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $white);
        imagecopyresampled(
            $target,
            $source,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight
        );

        $safeBase = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $filenameBase) ?: 'image';
        $filename = trim($safeBase, '-') . '.jpg';
        $destPath = rtrim($uploadDir, DIRECTORY_SEPARATOR . '/') . DIRECTORY_SEPARATOR . $filename;

        $saved = imagejpeg($target, $destPath, max(50, min(90, $quality)));
        imagedestroy($source);
        imagedestroy($target);

        if (!$saved) {
            throw new \RuntimeException('No se pudo guardar la imagen comprimida.');
        }

        return $filename;
    }

    public static function deleteLocalUploadFromUrl(?string $url, string $uploadsRoot, string $requiredFilenamePrefix = ''): void
    {
        if ($url === null || trim($url) === '') {
            return;
        }

        $path = (string)(parse_url($url, PHP_URL_PATH) ?: $url);
        $uploadsPosition = strpos($path, '/uploads/');
        if ($uploadsPosition === false) {
            return;
        }

        $relativePath = ltrim(substr($path, $uploadsPosition + strlen('/uploads/')), '/\\');
        $relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        $filename = basename($relativePath);

        if ($requiredFilenamePrefix !== '' && strpos($filename, $requiredFilenamePrefix) !== 0) {
            return;
        }

        $root = realpath($uploadsRoot);
        if ($root === false) {
            return;
        }

        $target = realpath(rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relativePath);
        if ($target === false || strpos($target, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($target)) {
            return;
        }

        @unlink($target);
    }

    private static function createSourceImage(string $path, string $mime): \GdImage|false
    {
        return match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png' => imagecreatefrompng($path),
            'image/gif' => imagecreatefromgif($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($path) : false,
            default => false,
        };
    }

    private static function applyJpegOrientation(\GdImage $image, string $path): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? (int)($exif['Orientation'] ?? 1) : 1;

        return match ($orientation) {
            3 => imagerotate($image, 180, 0) ?: $image,
            6 => imagerotate($image, -90, 0) ?: $image,
            8 => imagerotate($image, 90, 0) ?: $image,
            default => $image,
        };
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return (string)round($bytes / 1024 / 1024) . ' MB';
        }

        return (string)round($bytes / 1024) . ' KB';
    }
}
