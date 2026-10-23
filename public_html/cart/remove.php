<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
if (!dogoflow_cart_enabled()) {
    http_response_code(404);
    exit('Cart ordering is currently disabled. Please contact the seller directly from the product page.');
}
Auth::requireLogin(); if(Auth::role()!=='customer') redirect(Auth::dashboardPath());
if(!hash_equals((string)($_SESSION['_csrf']??''),(string)($_GET['csrf']??''))){flash('error','Your security token expired. Please try again.');redirect('/cart.php');}
$id=(int)($_GET['id']??0); $stmt=db()->prepare('DELETE ci FROM cart_items ci INNER JOIN carts c ON c.id=ci.cart_id WHERE ci.id=? AND c.customer_id=?');$stmt->execute([$id,(int)Auth::userId()]);flash('success','Item removed from your cart.');redirect('/cart.php');
