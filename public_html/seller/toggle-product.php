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

$stmt = $pdo->prepare('SELECT status FROM products WHERE id = ? AND seller_id = ? LIMIT 1');
$stmt->execute([$productId, $userId]);
$status = $stmt->fetchColumn();

if ($status === false) {
    flash('error', 'Product not found.');
    redirect('/seller/products.php');
}

// Only live and hidden products can be toggled. Drafts and rejected items are managed by approval.
if (!in_array($status, ['active', 'inactive'], true)) {
    flash('error', 'This product cannot be shown or hidden right now.');
    redirect('/seller/products.php');
}

$newStatus = $status === 'active' ? 'inactive' : 'active';
$update = $pdo->prepare('UPDATE products SET status = ? WHERE id = ? AND seller_id = ?');
$update->execute([$newStatus, $productId, $userId]);

flash('success', $newStatus === 'inactive' ? 'Product hidden from customers.' : 'Product is visible to customers again.');
redirect('/seller/products.php');
