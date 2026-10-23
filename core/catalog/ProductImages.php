<?php
declare(strict_types=1);

/**
 * Shared product image validation and filesystem helpers.
 * Load only after the DogoFlow core bootstrap has defined UPLOAD_DIR and
 * MAX_UPLOAD_BYTES.
 */
final class ProductImages
{
    public const MAX_PER_PRODUCT = 5;

    /** Validate and normalize a PHP multiple-file upload structure. */
    public static function multipleUploads(?array $files): array
    {
        if ($files === null) {
            return [];
        }

        if (!isset($files['name'], $files['error']) || !is_array($files['name']) || !is_array($files['error'])) {
            throw new RuntimeException('The uploaded image selection is invalid. Please choose your photos again.');
        }

        $uploads = [];
        foreach (array_keys($files['name']) as $index) {
            $upload = self::fileAt($files, $index);
            if ((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $uploads[$index] = $upload;
            }
        }

        return $uploads;
    }

    /** Safely extract a nested input such as replace_images[123]. */
    public static function nestedUpload(?array $files, int|string $key): array
    {
        if ($files === null) {
            return ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0];
        }

        foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $field) {
            if (!isset($files[$field]) || !is_array($files[$field])) {
                return ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0];
            }
        }

        return self::fileAt($files, $key);
    }

    private static function fileAt(array $files, int|string $key): array
    {
        return [
            'name' => $files['name'][$key] ?? '',
            'type' => $files['type'][$key] ?? '',
            'tmp_name' => $files['tmp_name'][$key] ?? '',
            'error' => $files['error'][$key] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$key] ?? 0,
        ];
    }

    /**
     * Validate an actual HTTP-uploaded raster image and save it under a random
     * filename. Returns a project-relative path suitable for products.image and
     * product_images.image_path.
     */
    public static function saveUpload(array $file): string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException($error === UPLOAD_ERR_NO_FILE
                ? 'Choose a photo to upload.'
                : 'One of the product photos could not be uploaded. Please try again.');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('An uploaded photo was invalid. Please choose the file again.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size < 1 || $size > MAX_UPLOAD_BYTES) {
            throw new RuntimeException('Each product photo must be 5 MB or smaller.');
        }

        $allowed = [
            'image/jpeg' => ['ext' => 'jpg', 'type' => IMAGETYPE_JPEG],
            'image/png' => ['ext' => 'png', 'type' => IMAGETYPE_PNG],
            'image/webp' => ['ext' => 'webp', 'type' => IMAGETYPE_WEBP],
        ];

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!is_string($mime) || !isset($allowed[$mime])) {
            throw new RuntimeException('Only JPG, PNG and WebP product photos are allowed.');
        }

        $dimensions = @getimagesize($tmp);
        if ($dimensions === false || ($dimensions[0] ?? 0) < 100 || ($dimensions[1] ?? 0) < 100
            || ($dimensions[2] ?? null) !== $allowed[$mime]['type']) {
            throw new RuntimeException('Each photo must be a valid image at least 100 × 100 pixels.');
        }

        if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true) && !is_dir(UPLOAD_DIR)) {
            throw new RuntimeException('The product photo directory is not available.');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime]['ext'];
        $destination = rtrim(UPLOAD_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($tmp, $destination)) {
            throw new RuntimeException('A product photo could not be saved. Please try again.');
        }

        return 'uploads/products/' . $filename;
    }

    /** Return gallery rows, falling back to the legacy products.image value. */
    public static function gallery(PDO $pdo, int $productId, ?string $legacyImage = null): array
    {
        $stmt = $pdo->prepare(
            'SELECT id, product_id, image_path, is_primary, sort_order
             FROM product_images
             WHERE product_id = ?
             ORDER BY is_primary DESC, sort_order ASC, id ASC'
        );
        $stmt->execute([$productId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $legacyImage = trim((string) $legacyImage);
        if ($legacyImage !== '') {
            $represented = false;
            foreach ($rows as $row) {
                if ((string) ($row['image_path'] ?? '') === $legacyImage) {
                    $represented = true;
                    break;
                }
            }
            if (!$represented) {
                foreach ($rows as &$row) {
                    $row['is_primary'] = 0;
                }
                unset($row);
                array_unshift($rows, [
                    'id' => 0,
                    'product_id' => $productId,
                    'image_path' => $legacyImage,
                    'is_primary' => 1,
                    'sort_order' => -1,
                    'is_legacy' => true,
                ]);
            }
        }

        return $rows;
    }

    public static function publicUrl(string $relativePath): string
    {
        return url('/' . ltrim($relativePath, '/'));
    }

    /** Only generated product paths are eligible for filesystem deletion. */
    public static function isManagedPath(string $relativePath): bool
    {
        $path = str_replace('\\', '/', trim($relativePath));
        return preg_match('#^uploads/products/[A-Za-z0-9_-]+\.(?:jpe?g|png|webp)$#Di', $path) === 1
            && basename($path) !== '.'
            && basename($path) !== '..';
    }

    /**
     * Delete old files only when no product or gallery row still references
     * them. Invoke after the database transaction commits.
     */
    public static function cleanupUnreferenced(PDO $pdo, array $relativePaths): void
    {
        $check = $pdo->prepare(
            'SELECT
                (SELECT COUNT(*) FROM product_images WHERE image_path = ?) +
                (SELECT COUNT(*) FROM products WHERE image = ?)'
        );

        foreach (array_unique(array_filter($relativePaths, 'is_string')) as $path) {
            if (!self::isManagedPath($path)) {
                continue;
            }

            $check->execute([$path, $path]);
            if ((int) $check->fetchColumn() !== 0) {
                continue;
            }

            $file = rtrim(UPLOAD_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($path);
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    /** Delete just-created uploads on a failed transaction. */
    public static function cleanupNewUploads(array $relativePaths): void
    {
        foreach (array_unique(array_filter($relativePaths, 'is_string')) as $path) {
            if (!self::isManagedPath($path)) {
                continue;
            }

            $file = rtrim(UPLOAD_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($path);
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
}
