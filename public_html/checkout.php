<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
if (!dogoflow_cart_enabled()) { redirect('/products.php'); }
require_once DOGOFLOW_ROOT . '/core/payments/Paystack.php';

Auth::requireLogin();
if (Auth::role() !== 'customer') redirect(Auth::dashboardPath());

$customerId = (int)Auth::userId();
$pdo = db();
$error = flash('error');

$stmt = $pdo->prepare(
    "SELECT ci.id AS cart_item_id, ci.quantity, p.id AS product_id, p.name, p.price, p.stock, p.seller_id,
            p.image, sp.business_name
     FROM carts c
     INNER JOIN cart_items ci ON ci.cart_id = c.id
     INNER JOIN products p ON p.id = ci.product_id
     INNER JOIN users u ON u.id = p.seller_id AND u.role='seller' AND u.status='active'
     INNER JOIN seller_profiles sp ON sp.user_id=p.seller_id
         AND sp.status='active' AND sp.approval_status='approved'
     WHERE c.customer_id=? AND p.status='active'
     ORDER BY sp.business_name, p.name"
);
$stmt->execute([$customerId]);
$items = $stmt->fetchAll();

$subtotal = 0.0;
$invalid = [];
foreach ($items as $item) {
    if ((int)$item['quantity'] < 1 || (int)$item['quantity'] > (int)$item['stock']) {
        $invalid[] = (string)$item['name'];
    }
    $subtotal += (float)$item['price'] * (int)$item['quantity'];
}

if (!$items) redirect('/cart.php');

$userStmt = $pdo->prepare('SELECT name,email,phone FROM users WHERE id=? LIMIT 1');
$userStmt->execute([$customerId]);
$user = $userStmt->fetch() ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['_csrf'] ?? null);

    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));
    $city = trim((string)($_POST['city'] ?? ''));
    $region = trim((string)($_POST['region'] ?? ''));
    $note = trim((string)($_POST['note'] ?? ''));

    if ($fullName === '' || $phone === '' || $address === '' || $city === '' || $region === '') {
        $error = 'Please complete all required delivery details.';
    } elseif ($invalid) {
        $error = 'One or more cart quantities are no longer available. Please update your cart.';
    } elseif ((float)$subtotal <= 0) {
        $error = 'Your order total must be greater than zero.';
    } else {
        $reference = 'DF-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(4)));
        $orderNumber = 'DFO-' . date('ymdHis') . strtoupper(bin2hex(random_bytes(3)));
        $shipping = json_encode([
            'full_name' => $fullName,
            'phone' => $phone,
            'address' => $address,
            'city' => $city,
            'region' => $region,
        ], JSON_UNESCAPED_SLASHES);

        try {
            $pdo->beginTransaction();

            // Lock the cart's product rows and re-check stock before reserving it.
            $lockStmt = $pdo->prepare(
                "SELECT ci.product_id, ci.quantity, p.name, p.price, p.stock, p.seller_id
                 FROM carts c
                 INNER JOIN cart_items ci ON ci.cart_id=c.id
                 INNER JOIN products p ON p.id=ci.product_id
                 INNER JOIN users u ON u.id=p.seller_id AND u.role='seller' AND u.status='active'
                 INNER JOIN seller_profiles sp ON sp.user_id=p.seller_id AND sp.status='active' AND sp.approval_status='approved'
                 WHERE c.customer_id=? AND p.status='active'
                 FOR UPDATE"
            );
            $lockStmt->execute([$customerId]);
            $lockedItems = $lockStmt->fetchAll();
            if (!$lockedItems) throw new RuntimeException('Your cart is empty.');

            $lockedSubtotal = 0.0;
            foreach ($lockedItems as $item) {
                $qty = (int)$item['quantity'];
                if ($qty < 1 || $qty > (int)$item['stock']) {
                    throw new RuntimeException('Stock changed for ' . (string)$item['name'] . '. Please review your cart.');
                }
                $lockedSubtotal += (float)$item['price'] * $qty;
            }

            $pdo->prepare(
                'INSERT INTO orders (customer_id,order_number,subtotal,delivery_fee,total,shipping_address,customer_note,status) VALUES (?,?,?,?,?,?,?,"pending_payment")'
            )->execute([$customerId, $orderNumber, $lockedSubtotal, 0.00, $lockedSubtotal, $shipping, $note !== '' ? $note : null]);
            $orderId = (int)$pdo->lastInsertId();

            $itemInsert = $pdo->prepare('INSERT INTO order_items (order_id,product_id,seller_id,quantity,unit_price,subtotal) VALUES (?,?,?,?,?,?)');
            $stockUpdate = $pdo->prepare('UPDATE products SET stock=stock-? WHERE id=? AND stock>=?');
            foreach ($lockedItems as $item) {
                $qty = (int)$item['quantity'];
                $line = (float)$item['price'] * $qty;
                $itemInsert->execute([$orderId, (int)$item['product_id'], (int)$item['seller_id'], $qty, $item['price'], $line]);
                $stockUpdate->execute([$qty, (int)$item['product_id'], $qty]);
                if ($stockUpdate->rowCount() !== 1) throw new RuntimeException('Stock changed during checkout.');
            }

            $pdo->prepare('INSERT INTO payments (order_id,reference,amount,currency,gateway,status) VALUES (?,?,?,?,"paystack","pending")')
                ->execute([$orderId, $reference, $lockedSubtotal, 'GHS']);

            // Empty the cart only after the order and stock reservation are safely created.
            $pdo->prepare('DELETE ci FROM cart_items ci INNER JOIN carts c ON c.id=ci.cart_id WHERE c.customer_id=?')->execute([$customerId]);
            $pdo->commit();

            $paystack = new Paystack();
            $callback = url('/payment/callback.php');
            $response = $paystack->initialize(
                (string)$user['email'],
                (int)round($lockedSubtotal * 100),
                $reference,
                $callback,
                ['order_id' => $orderId, 'order_number' => $orderNumber]
            );

            $paymentData = $response['data'] ?? [];
            $authorizationUrl = (string)($paymentData['authorization_url'] ?? '');
            $gatewayReference = (string)($paymentData['reference'] ?? $reference);
            $accessCode = (string)($paymentData['access_code'] ?? '');
            if ($authorizationUrl === '') throw new RuntimeException('Paystack did not return a checkout URL.');

            $pdo->prepare('UPDATE payments SET reference=?,access_code=? WHERE order_id=? AND reference=?')
                ->execute([$gatewayReference, $accessCode !== '' ? $accessCode : null, $orderId, $reference]);

            header('Location: ' . $authorizationUrl, true, 303);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            // If Paystack initialization failed after the local transaction committed,
            // restore the reserved stock and mark the order/payment as failed.
            if (isset($orderId) && $orderId > 0 && !($pdo->inTransaction())) {
                try {
                    $pdo->beginTransaction();
                    $restore = $pdo->prepare('SELECT product_id,quantity FROM order_items WHERE order_id=?');
                    $restore->execute([$orderId]);
                    $rows = $restore->fetchAll();
                    foreach ($rows as $row) {
                        $pdo->prepare('UPDATE products SET stock=stock+? WHERE id=?')->execute([(int)$row['quantity'], (int)$row['product_id']]);
                    }
                    $pdo->prepare('UPDATE payments SET status="failed" WHERE order_id=? AND status="pending"')->execute([$orderId]);
                    $pdo->prepare('UPDATE orders SET status="cancelled" WHERE id=? AND status="pending_payment"')->execute([$orderId]);
                    $pdo->commit();
                } catch (Throwable $cleanup) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log('Checkout cleanup failed: ' . $cleanup->getMessage());
                }
            }
            $error = APP_ENV === 'development' ? $e->getMessage() : 'We could not start your payment. Please try again.';
        }
    }
}
?>

<style>
:root{--df:#0088EE;--navy:#0A1628;--muted:#64748B;--line:#E2E8F0;--soft:#F8FAFC;--green:#059669;--red:#E11D48}*{box-sizing:border-box}body{margin:0;background:#F6F9FC;color:var(--navy);font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.df-wrap{max-width:1180px;margin:auto;padding:36px 20px 80px}.df-top{display:flex;justify-content:space-between;align-items:center;gap:18px;margin-bottom:28px}.df-top h1{margin:3px 0 0;font-size:clamp(1.8rem,4vw,2.65rem);letter-spacing:-.04em}.df-eyebrow{font-size:.72rem;font-weight:900;letter-spacing:.14em;color:var(--df)}.df-sub{color:var(--muted);margin:8px 0 0;line-height:1.65}.df-card{background:#fff;border:1px solid var(--line);border-radius:22px;box-shadow:0 10px 35px rgba(15,23,42,.05)}.df-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border-radius:12px;padding:11px 16px;text-decoration:none;font-weight:850;border:1px solid transparent;cursor:pointer}.df-primary{background:var(--df);color:#fff}.df-outline{background:#fff;color:var(--navy);border-color:#CBD5E1}.df-danger{color:var(--red)}.df-muted{color:var(--muted)}.df-badge{display:inline-flex;align-items:center;padding:6px 9px;border-radius:999px;font-size:.72rem;font-weight:900}.df-badge.success{background:#ECFDF5;color:#047857}.df-badge.warning{background:#FFFBEB;color:#B45309}.df-badge.info{background:#EFF6FF;color:#1D4ED8}.df-badge.danger{background:#FFF1F2;color:#BE123C}.df-nav{height:72px;background:#fff;border-bottom:1px solid var(--line);display:flex;align-items:center}.df-nav-inner{width:min(1180px,100%);padding:0 20px;margin:auto;display:flex;align-items:center;justify-content:space-between;gap:18px}.df-nav img{height:40px;width:auto}.df-navlinks{display:flex;gap:20px;align-items:center}.df-navlinks a{color:#475569;text-decoration:none;font-weight:750;font-size:.92rem}.df-navlinks a:hover{color:var(--df)}.df-section{padding:22px}.df-section-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:18px}.df-section-head h2{margin:3px 0 0;font-size:1.15rem;letter-spacing:-.02em}.df-grid{display:grid;grid-template-columns:1fr 360px;gap:20px}.df-field{display:flex;flex-direction:column;gap:7px}.df-field label{font-size:.82rem;font-weight:850}.df-field input,.df-field textarea,.df-field select{width:100%;border:1px solid #CBD5E1;border-radius:12px;padding:12px 13px;background:#fff;color:var(--navy);outline:none;font:inherit}.df-field input:focus,.df-field textarea:focus{border-color:var(--df);box-shadow:0 0 0 3px rgba(0,136,238,.1)}.df-table{width:100%;border-collapse:collapse}.df-table th,.df-table td{text-align:left;padding:16px 18px;border-bottom:1px solid var(--line);vertical-align:middle}.df-table th{font-size:.7rem;letter-spacing:.08em;text-transform:uppercase;color:#64748B;background:#FAFCFE}.df-table tr:last-child td{border-bottom:0}.df-empty{text-align:center;padding:58px 20px}.df-empty h3{margin:0 0 7px}.df-empty p{color:var(--muted);margin:0 0 18px}.df-item{display:grid;grid-template-columns:78px 1fr auto;gap:16px;align-items:center;padding:18px 22px;border-bottom:1px solid var(--line)}.df-item:last-child{border-bottom:0}.df-thumb{width:78px;height:78px;border-radius:15px;background:#F1F5F9;display:flex;align-items:center;justify-content:center;overflow:hidden}.df-thumb img{width:100%;height:100%;object-fit:cover}.df-item-title{font-weight:900;text-decoration:none;color:var(--navy)}.df-item-meta{font-size:.78rem;color:var(--muted);margin-top:5px}.df-price{font-weight:950;color:var(--df);margin-top:7px}.df-item-right{text-align:right}.df-qty{display:flex;align-items:center;justify-content:flex-end;margin-bottom:7px}.df-qty button{height:32px;width:32px;border:1px solid #CBD5E1;background:#fff;font-weight:900;cursor:pointer}.df-qty button:first-child{border-radius:9px 0 0 9px}.df-qty button:last-child{border-radius:0 9px 9px 0}.df-qty input{height:32px;width:42px;border:1px solid #CBD5E1;border-left:0;border-right:0;text-align:center}.df-summary{padding:22px;position:sticky;top:18px}.df-summary-row{display:flex;justify-content:space-between;gap:14px;padding:11px 0;color:#475569}.df-total{display:flex;justify-content:space-between;border-top:1px solid var(--line);padding-top:16px;font-size:1.15rem}.df-total strong:last-child{color:var(--df)}.df-full{width:100%}.df-alert{padding:13px 15px;border-radius:12px;margin-bottom:18px;background:#FFF1F2;color:#BE123C;font-weight:700}.df-note{padding:13px 15px;background:#EFF6FF;border:1px solid #BFDBFE;border-radius:13px;color:#1E40AF;font-size:.82rem;line-height:1.55}.df-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px}.df-stat{padding:20px}.df-stat span{display:block;font-size:.7rem;font-weight:900;letter-spacing:.09em;color:var(--muted)}.df-stat strong{display:block;font-size:1.55rem;margin:8px 0 3px;letter-spacing:-.03em}.df-stat small{color:var(--muted)}.df-two{display:grid;grid-template-columns:1.3fr .7fr;gap:20px;margin-bottom:20px}.df-order-row{display:flex;justify-content:space-between;align-items:center;gap:15px;padding:15px 22px;border-top:1px solid var(--line);text-decoration:none;color:inherit}.df-order-row:first-child{border-top:0}.df-order-row small{display:block;color:var(--muted);margin-top:4px}.df-order-right{display:flex;align-items:center;gap:12px}.df-links{display:grid;grid-template-columns:1fr 1fr;gap:10px}.df-links a{padding:15px;border:1px solid var(--line);border-radius:14px;text-decoration:none;color:var(--navy)}.df-links strong,.df-links span{display:block}.df-links span{font-size:.78rem;color:var(--muted);margin-top:4px;line-height:1.4}.df-notice{display:flex;gap:12px;padding:15px 22px;border-top:1px solid var(--line)}.df-dot{width:9px;height:9px;border-radius:50%;background:var(--df);margin-top:7px;flex:none}.df-notice strong{font-size:.9rem}.df-notice p{margin:4px 0;color:#475569;font-size:.84rem;line-height:1.5}.df-notice small{color:var(--muted)}.df-detail-grid{display:grid;grid-template-columns:1fr 340px;gap:20px}.df-order-item{display:flex;justify-content:space-between;gap:20px;padding:17px 0;border-bottom:1px solid var(--line)}.df-order-item:last-child{border-bottom:0}.df-order-item strong{display:block}.df-order-item small{display:block;color:var(--muted);margin-top:5px}.df-history{padding:12px 0;border-bottom:1px solid var(--line)}.df-history:last-child{border-bottom:0}.df-history small{display:block;color:var(--muted);margin-top:4px}.df-address{line-height:1.75;color:#475569}.df-kicker{font-size:.75rem;color:var(--muted);font-weight:800}.df-mobile{display:none}@media(max-width:850px){.df-grid,.df-two,.df-detail-grid{grid-template-columns:1fr}.df-summary{position:static}.df-stats{grid-template-columns:1fr 1fr}.df-navlinks a:nth-child(1),.df-navlinks a:nth-child(2){display:none}}@media(max-width:600px){.df-nav{height:64px}.df-nav img{height:34px}.df-navlinks{gap:10px}.df-navlinks a{font-size:.78rem}.df-wrap{padding:25px 14px 60px}.df-top{align-items:flex-start}.df-top .df-btn{display:none}.df-section{padding:17px}.df-item{grid-template-columns:58px 1fr;gap:12px;padding:15px}.df-thumb{width:58px;height:58px}.df-item-right{grid-column:2;text-align:left}.df-qty{justify-content:flex-start}.df-stats{gap:9px}.df-stat{padding:14px}.df-stat strong{font-size:1.15rem}.df-stat small{font-size:.72rem}.df-links{grid-template-columns:1fr}.df-table{min-width:700px}.df-table-wrap{overflow:auto}.df-order-row{padding:14px 17px}.df-order-right{flex-direction:column;align-items:flex-end;gap:5px}.df-mobile{display:block}}</style>
<header class="df-nav"><div class="df-nav-inner"><a href="<?=e(url('/'))?>"><img src="<?=e(url('/assets/images/df-logo.png'))?>" alt="DogoFlow"></a><nav class="df-navlinks"><a href="<?=e(url('/products.php'))?>">Products</a><a href="<?=e(url('/stores.php'))?>">Stores</a><?php if (dogoflow_cart_enabled()): ?><a href="<?=e(url('/cart.php'))?>">Cart</a><?php endif; ?><a href="<?=e(url('/customer/dashboard.php'))?>">Account</a></nav></div></header>
<main class="df-wrap"><div class="df-top"><div><span class="df-eyebrow">SECURE CHECKOUT</span><h1>Complete your order</h1><p class="df-sub">Enter your delivery details, then continue to Paystack to pay securely.</p></div><?php if (dogoflow_cart_enabled()): ?><a class="df-btn df-outline" href="<?=e(url('/cart.php'))?>">← Back to cart</a><?php endif; ?></div><?php if($error): ?><div class="df-alert"><?=e($error)?></div><?php endif; ?><?php if($invalid): ?><div class="df-alert">Some quantities are no longer available. Please return to your cart and update them.</div><?php endif; ?><div class="df-grid"><section class="df-card df-section"><div class="df-section-head"><div><span class="df-eyebrow">DELIVERY DETAILS</span><h2>Where should we deliver?</h2></div></div><form method="post"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><div style="display:grid;grid-template-columns:1fr 1fr;gap:16px"><div class="df-field"><label for="full_name">Full name *</label><input id="full_name" name="full_name" required value="<?=e((string)($_POST['full_name']??$user['name']??''))?>"></div><div class="df-field"><label for="phone">Phone number *</label><input id="phone" name="phone" required value="<?=e((string)($_POST['phone']??$user['phone']??''))?>"></div><div class="df-field" style="grid-column:1/-1"><label for="address">Delivery address *</label><input id="address" name="address" required placeholder="House number, street, landmark" value="<?=e((string)($_POST['address']??''))?>"></div><div class="df-field"><label for="city">City / Town *</label><input id="city" name="city" required value="<?=e((string)($_POST['city']??''))?>"></div><div class="df-field"><label for="region">Region *</label><input id="region" name="region" required placeholder="e.g. Greater Accra" value="<?=e((string)($_POST['region']??''))?>"></div><div class="df-field" style="grid-column:1/-1"><label for="note">Delivery note <span class="df-muted" style="font-weight:500">(optional)</span></label><textarea id="note" name="note" rows="3" placeholder="Any useful delivery instruction"><?=e((string)($_POST['note']??''))?></textarea></div></div><div class="df-note" style="margin-top:18px">🔒 <strong>Secure payment.</strong> You will be redirected to Paystack to complete payment. DogoFlow does not collect your card PIN or payment credentials.</div><button class="df-btn df-primary" style="margin-top:18px;padding:13px 20px" type="submit" <?=($invalid?'disabled':'')?>>Continue to secure payment →</button></form></section><aside class="df-card df-summary"><span class="df-eyebrow">YOUR ORDER</span><h2 style="margin:5px 0 16px">Order summary</h2><?php foreach($items as $i): ?><div class="df-summary-row"><span style="max-width:65%"> <?=e((string)$i['name'])?> × <?= (int)$i['quantity']?></span><strong>GH₵ <?=number_format((float)$i['price']*(int)$i['quantity'],2)?></strong></div><?php endforeach; ?><div class="df-summary-row" style="border-top:1px solid var(--line);margin-top:8px"><span>Delivery</span><strong>GH₵ 0.00</strong></div><div class="df-total"><strong>Total</strong><strong>GH₵ <?=number_format($subtotal,2)?></strong></div><p class="df-sub" style="font-size:.78rem">Prices and stock are rechecked server-side when your order is created.</p></aside></div></main></body></html>