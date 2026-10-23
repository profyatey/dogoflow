<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
if (!dogoflow_cart_enabled()) {
    http_response_code(404);
    exit('Cart ordering is currently disabled. Please contact the seller directly from the product page.');
}
Auth::requireLogin(); if(Auth::role()!=='customer') redirect(Auth::dashboardPath());
if($_SERVER['REQUEST_METHOD']!=='POST') redirect('/cart.php'); verify_csrf((string)($_POST['csrf_token']??''));
$itemId=(int)($_POST['cart_item_id']??0); $quantity=(int)($_POST['quantity']??0);
$stmt=db()->prepare("SELECT ci.id,p.stock FROM cart_items ci INNER JOIN carts c ON c.id=ci.cart_id AND c.customer_id=? INNER JOIN products p ON p.id=ci.product_id WHERE ci.id=? LIMIT 1"); $stmt->execute([(int)Auth::userId(),$itemId]); $row=$stmt->fetch();
if(!$row){flash('error','Cart item not found.');redirect('/cart.php');}
if($quantity<=0){db()->prepare('DELETE FROM cart_items WHERE id=?')->execute([$itemId]);}else{ $quantity=min($quantity,(int)$row['stock']); if($quantity<1){db()->prepare('DELETE FROM cart_items WHERE id=?')->execute([$itemId]);}else{db()->prepare('UPDATE cart_items SET quantity=?,updated_at=NOW() WHERE id=?')->execute([$quantity,$itemId]);}}
redirect('/cart.php');
