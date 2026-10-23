<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
Auth::requireApprovedSeller();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

verify_csrf($_POST['_csrf'] ?? null);

$pdo = db();
$userId = (int) Auth::userId();
$productId = (int) ($_POST['product_id'] ?? 0);

if ($productId <= 0) {
    flash('error', 'Invalid product.');
    redirect('/seller/products.php');
}

$stmt = $pdo->prepare(
    'SELECT id, image
     FROM products
     WHERE id = ? AND seller_id = ?
     LIMIT 1'
);
$stmt->execute([$productId, $userId]);
$product = $stmt->fetch();

if (!$product) {
    flash('error', 'Product not found or you do not have permission to delete it.');
    redirect('/seller/products.php');
}

$imagesStmt = $pdo->prepare('SELECT image_path FROM product_images WHERE product_id = ?');
$imagesStmt->execute([$productId]);
$imagePaths = $imagesStmt->fetchAll(PDO::FETCH_COLUMN);

if (!empty($product['image'])) {
    $imagePaths[] = $product['image'];
}

try {
    $pdo->beginTransaction();

    $delete = $pdo->prepare(
        'DELETE FROM products WHERE id = ? AND seller_id = ?'
    );
    $delete->execute([$productId, $userId]);

    if ($delete->rowCount() !== 1) {
        throw new RuntimeException('The product could not be deleted.');
    }

    $pdo->commit();

    foreach (array_unique($imagePaths) as $imagePath) {
        $path = UPLOAD_DIR . '/' . basename((string) $imagePath);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    flash('success', 'Product deleted successfully.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());
    flash('error', 'The product could not be deleted.');
}

redirect('/seller/products.php');
