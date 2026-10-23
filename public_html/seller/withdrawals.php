<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
Auth::requireApprovedSeller();
$pdo=db(); $sellerId=(int)Auth::userId();
$stmt=$pdo->prepare('SELECT amount,method,status,reference,created_at,processed_at,payout_note FROM withdrawals WHERE user_id=? ORDER BY id DESC LIMIT 100');
$stmt->execute([$sellerId]); $rows=$stmt->fetchAll();
function wb(string $s):string{return match($s){'paid'=>'success','approved','processing'=>'info','rejected','failed'=>'danger',default=>'warning'};}
?>
<!doctype html><html lang="en"><head><link rel="icon" href="<?= e(url('/assets/images/favicon-32.png')) ?>"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Withdrawals · DogoFlow Seller</title><?php require __DIR__.'/../partials/tailwind.php'; ?></head>
<body class="seller-page"><main class="container-wide seller-shell-df"><?php $sellerActive = 'withdrawals'; require __DIR__ . '/../partials/seller_header.php'; ?>
<section class="seller-page-heading-df" style="padding-top:32px;margin-bottom:22px"><div><span class="eyebrow-df">Payouts</span><h1>Withdrawals</h1><p>Review your payout requests and their processing status.</p></div></section>
<div class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"><strong>Seller payout workflow:</strong> withdrawal requests are reviewed and processed by DogoFlow administration. This page does not initiate a payout or collect payment details.</div>
<section class="card overflow-hidden"><div class="table-wrap-df"><table class="table-df"><thead><tr><th>Reference</th><th>Amount</th><th>Method</th><th>Status</th><th>Requested</th><th>Processed</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><strong><?=e($r['reference'])?></strong><?php if(!empty($r['payout_note'])):?><div class="muted mt-1"><?=e($r['payout_note'])?></div><?php endif;?></td><td>GH₵ <?=number_format((float)$r['amount'],2)?></td><td><?=e($r['method'])?></td><td><span class="badge-df <?=e(wb((string)$r['status']))?>"><?=e(ucfirst($r['status']))?></span></td><td><?=e(date('d M Y, H:i',strtotime($r['created_at'])))?></td><td><?=!empty($r['processed_at'])?e(date('d M Y, H:i',strtotime($r['processed_at']))):'—'?></td></tr><?php endforeach;if(!$rows):?><tr><td colspan="6"><div class="empty-df">No withdrawal requests yet.</div></td></tr><?php endif;?></tbody></table></div></section>
</main></body></html>