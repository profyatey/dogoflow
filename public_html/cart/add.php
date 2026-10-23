<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
if (!dogoflow_cart_enabled()) {
    http_response_code(404);
    exit('Cart ordering is currently disabled. Please contact the seller directly from the product page.');
}
Auth::requireLogin();
if (Auth::role() !== 'customer') redirect(Auth::dashboardPath());
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/products.php');
verify_csrf((string)($_POST['csrf_token'] ?? ''));
$productId = (int)($_POST['product_id'] ?? 0);
$quantity = max(1, min(99, (int)($_POST['quantity'] ?? 1)));
$stmt = db()->prepare("SELECT p.id,p.stock FROM products p INNER JOIN users u ON u.id=p.seller_id AND u.role='seller' AND u.status='active' INNER JOIN seller_profiles sp ON sp.user_id=p.seller_id AND sp.status='active' AND sp.approval_status='approved' WHERE p.id=? AND p.status='active' LIMIT 1");
$stmt->execute([$productId]); $product=$stmt->fetch();
if (!$product || (int)$product['stock'] < 1) { flash('error','That product is unavailable or out of stock.'); redirect('/products.php'); }
$quantity=min($quantity,(int)$product['stock']);
$pdo=db(); $pdo->beginTransaction();
try {
    $pdo->prepare('INSERT INTO carts (customer_id) VALUES (?) ON DUPLICATE KEY UPDATE updated_at=NOW()')->execute([(int)Auth::userId()]);
    $s=$pdo->prepare('SELECT id FROM carts WHERE customer_id=? FOR UPDATE'); $s->execute([(int)Auth::userId()]); $cartId=(int)$s->fetchColumn();
    $s=$pdo->prepare('SELECT quantity FROM cart_items WHERE cart_id=? AND product_id=? FOR UPDATE'); $s->execute([$cartId,$productId]); $existing=$s->fetchColumn();
    $newQty=min((int)$product['stock'], (int)($existing ?: 0)+$quantity);
    if ($existing !== false) $pdo->prepare('UPDATE cart_items SET quantity=?,updated_at=NOW() WHERE cart_id=? AND product_id=?')->execute([$newQty,$cartId,$productId]);
    else $pdo->prepare('INSERT INTO cart_items (cart_id,product_id,quantity) VALUES (?,?,?)')->execute([$cartId,$productId,$newQty]);
    $pdo->commit(); flash('success','Product added to your cart.');
} catch(Throwable $e){ if($pdo->inTransaction())$pdo->rollBack(); flash('error','We could not add that product to your cart.'); }
$back=(string)($_POST['return_to']??'/products.php'); if($back===''||str_starts_with($back,'//'))$back='/products.php'; redirect($back);
