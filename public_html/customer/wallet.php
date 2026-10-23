<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
Auth::requireRole('customer');
$pdo = db();
$userId = (int)Auth::userId();
$stmt = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN direction='credit' AND status='completed' THEN amount ELSE 0 END),0)-COALESCE(SUM(CASE WHEN direction='debit' AND status='completed' THEN amount ELSE 0 END),0) FROM wallet_transactions WHERE user_id=?");
$stmt->execute([$userId]);
$balance = (float)$stmt->fetchColumn();
$stmt = $pdo->prepare('SELECT type,amount,direction,status,reference,created_at FROM wallet_transactions WHERE user_id=? ORDER BY id DESC LIMIT 50');
$stmt->execute([$userId]);
$transactions = $stmt->fetchAll();
?>
<!doctype html><html lang="en"><head><link rel="icon" href="<?= e(url('/assets/images/favicon-32.png')) ?>"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Wallet · DogoFlow</title><?php require __DIR__ . '/../partials/tailwind.php'; ?></head>
<body><header class="site-header"><a href="<?=e(url('/'))?>"><img src="<?=e(url('/assets/images/df-logo.png'))?>" alt="DogoFlow" style="height:40px;width:auto"></a><nav class="nav-links"><a href="<?=e(url('/products.php'))?>">Products</a><a href="<?=e(url('/customer/dashboard.php'))?>">Account</a></nav></header><main class="container customer-form-page-df"><div class="page-heading"><div><span class="eyebrow-df">MY WALLET</span><h1>Wallet</h1><p>View your DogoFlow wallet activity.</p></div></div><div class="card wallet-balance-df"><span>AVAILABLE LEDGER BALANCE</span><strong>GH₵ <?=number_format($balance,2)?></strong><small>Wallet funds are separate from Paystack checkout payments.</small></div><section class="card customer-panel-df" style="margin-top:20px"><div class="panel-head-df"><div><span class="eyebrow-df">ACTIVITY</span><h2>Transactions</h2></div></div><div class="table-wrap-df"><table class="table-df"><thead><tr><th>Type</th><th>Reference</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead><tbody><?php foreach($transactions as $tx): ?><tr><td><?=e(ucwords(str_replace('_',' ',(string)$tx['type'])))?></td><td><?=e((string)$tx['reference'])?></td><td class="<?=e($tx['direction']==='credit'?'wallet-credit-df':'wallet-debit-df')?>"><?=e($tx['direction']==='credit'?'+':'-')?> GH₵ <?=number_format((float)$tx['amount'],2)?></td><td><?=e(ucwords((string)$tx['status']))?></td><td><?=e(date('d M Y · H:i',strtotime((string)$tx['created_at'])))?></td></tr><?php endforeach; if(!$transactions): ?><tr><td colspan="5"><div class="empty-df">No wallet transactions yet.</div></td></tr><?php endif; ?></tbody></table></div></section></main></body></html>
