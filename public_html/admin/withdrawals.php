<?php
declare(strict_types=1);
require_once __DIR__.'/../bootstrap.php';
Auth::requireRole('admin');
$pdo=db(); $active='withdrawals'; $pageTitle='Withdrawals'; $pageDescription='Review and manually process seller and affiliate payout requests.';
$error=flash('error'); $success=flash('success');
$pendingAffiliatePayouts=(int)$pdo->query("SELECT COUNT(*) FROM withdrawals w JOIN users u ON u.id=w.user_id WHERE u.role='affiliate' AND w.status='pending'")->fetchColumn();
$pendingPayoutAmount=(float)$pdo->query("SELECT COALESCE(SUM(w.amount),0) FROM withdrawals w JOIN users u ON u.id=w.user_id WHERE u.role='affiliate' AND w.status='pending'")->fetchColumn();
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf($_POST['_csrf']??null); $id=(int)($_POST['withdrawal_id']??0); $action=(string)($_POST['action']??''); $note=trim((string)($_POST['payout_note']??''));
 $allowed=['approve','reject','processing','paid','failed'];
 if($id<1 || !in_array($action,$allowed,true)){flash('error','Invalid withdrawal action.');redirect('/admin/withdrawals.php');}
 try{
  $pdo->beginTransaction();
  $stmt=$pdo->prepare('SELECT w.*,u.name,u.email,u.role FROM withdrawals w INNER JOIN users u ON u.id=w.user_id WHERE w.id=? LIMIT 1 FOR UPDATE');$stmt->execute([$id]);$w=$stmt->fetch();
  if(!$w) throw new RuntimeException('Withdrawal request not found.');
  $current=(string)$w['status'];
  $transitions=['pending'=>['approve'=>'approved','reject'=>'rejected'],'approved'=>['processing'=>'processing','reject'=>'rejected'],'processing'=>['paid'=>'paid','failed'=>'failed']];
  if(!isset($transitions[$current][$action])) throw new RuntimeException('That withdrawal status cannot be changed with this action.');
  $next=$transitions[$current][$action];
  if($next==='paid' && $w['role']==='affiliate'){
   $profile=$pdo->prepare('SELECT id FROM affiliate_profiles WHERE user_id=? LIMIT 1');$profile->execute([(int)$w['user_id']]);$affiliateId=(int)$profile->fetchColumn();
   if($affiliateId<1) throw new RuntimeException('Affiliate profile not found.');
   $sum=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM affiliate_rewards WHERE affiliate_id=? AND status='available' FOR UPDATE");$sum->execute([$affiliateId]);
   if((float)$sum->fetchColumn() + 0.0001 < (float)$w['amount']) throw new RuntimeException('The affiliate no longer has enough available rewards for this payout.');
   $remaining=(float)$w['amount'];
   $rewards=$pdo->prepare("SELECT id,amount FROM affiliate_rewards WHERE affiliate_id=? AND status='available' ORDER BY created_at,id FOR UPDATE");$rewards->execute([$affiliateId]);
   foreach($rewards as $reward){
    if($remaining<=0.0001) break;
    $rewardAmount=(float)$reward['amount'];
    if($rewardAmount<=0 || $rewardAmount>$remaining+0.0001) continue;
    $mark=$pdo->prepare("UPDATE affiliate_rewards SET status='paid',paid_at=NOW() WHERE id=? AND status='available'");
    $mark->execute([(int)$reward['id']]);
    if($mark->rowCount()===1) $remaining-=$rewardAmount;
   }
   if($remaining>0.0001) throw new RuntimeException('Could not reconcile the affiliate reward balance for this payout. Check the available reward records before marking it paid.');
  }
  $pdo->prepare("UPDATE withdrawals SET status=?,processed_at=CASE WHEN ? IN ('paid','rejected','failed') THEN NOW() ELSE processed_at END,payout_note=? WHERE id=?")->execute([$next,$next,$note!==''?$note:null,$id]);
  $pdo->commit(); flash('success','Withdrawal '.$w['reference'].' updated to '.ucfirst($next).'.');
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();flash('error',$e instanceof RuntimeException?$e->getMessage():'Withdrawal update failed.');}
 redirect('/admin/withdrawals.php');
}
$rows=$pdo->query('SELECT w.*,u.name,u.email,u.role FROM withdrawals w INNER JOIN users u ON u.id=w.user_id ORDER BY CASE w.status WHEN "pending" THEN 0 WHEN "approved" THEN 1 WHEN "processing" THEN 2 ELSE 3 END,w.id DESC LIMIT 200')->fetchAll();
function withdrawal_badge(string $s):string{return match($s){'paid'=>'success','approved','processing'=>'info','rejected','failed'=>'danger',default=>'warning'};}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Withdrawals · DogoFlow Admin</title><link rel="icon" href="<?=url('/assets/images/favicon-32.png')?>"><?php require __DIR__.'/../partials/tailwind.php'; ?></head><body class="admin-shell"><?php require __DIR__.'/../partials/admin_sidebar.php'; ?><section class="admin-main"><?php require __DIR__.'/../partials/admin_topbar.php'; ?><main class="admin-content"><div class="page-heading"><div><h1>Withdrawals</h1><p>Review affiliate and seller payout requests, record manual Mobile Money payments and keep payout history reconciled.</p></div></div>
<section class="stats-grid-df" style="margin-bottom:18px"><article class="stat-card-df"><div class="stat-label-df">Pending affiliate requests</div><div class="stat-value-df"><?= number_format($pendingAffiliatePayouts) ?></div><small>Requests awaiting Admin review</small></article><article class="stat-card-df"><div class="stat-label-df">Pending affiliate amount</div><div class="stat-value-df">GH₵ <?= number_format($pendingPayoutAmount,2) ?></div><small>Not yet paid</small></article></section><?php if($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?><?php if($success): ?><div class="alert alert-success"><?=e($success)?></div><?php endif; ?><article class="panel-df"><div class="table-wrap-df"><table class="table-df"><thead><tr><th>Requester</th><th>Type</th><th>Amount</th><th>Method</th><th>Account</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach($rows as $w): $details=json_decode((string)$w['account_details'],true)?:[]; ?><tr><td><strong><?=e($w['name'])?></strong><small><?=e($w['email'])?></small><small><?=e($w['reference'])?></small></td><td><span class="badge-df info"><?=e(ucfirst((string)$w['role']))?></span></td><td><strong>GH₵ <?=number_format((float)$w['amount'],2)?></strong></td><td><?=e((string)$w['method'])?></td><td><?=e((string)($details['network']??''))?> <?=e((string)($details['phone']??''))?><?php if(!empty($details['account_name'])):?><small><?=e((string)$details['account_name'])?></small><?php endif;?></td><td><span class="badge-df <?=e(withdrawal_badge((string)$w['status']))?>"><?=e(ucfirst((string)$w['status']))?></span><?php if(!empty($w['payout_note'])):?><small><?=e($w['payout_note'])?></small><?php endif;?></td><td><?php $status=(string)$w['status']; ?><form method="post" class="flex flex-col gap-2 min-w-[150px]"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="withdrawal_id" value="<?= (int)$w['id'] ?>"><input name="payout_note" maxlength="500" placeholder="Note (optional)" class="rounded-lg border px-2 py-2 text-xs"><?php if($status==='pending'): ?><button name="action" value="approve" class="btn-df secondary">Approve</button><button name="action" value="reject" class="btn-df danger">Reject</button><?php elseif($status==='approved'): ?><button name="action" value="processing" class="btn-df secondary">Mark processing</button><button name="action" value="reject" class="btn-df danger">Reject</button><?php elseif($status==='processing'): ?><button name="action" value="paid" class="btn-df primary">Mark paid</button><button name="action" value="failed" class="btn-df danger">Mark failed</button><?php else: ?><span class="text-xs text-slate-400">No further actions</span><?php endif; ?></form></td></tr><?php endforeach; if(!$rows): ?><tr><td colspan="7"><div class="empty-df">No withdrawal requests yet.</div></td></tr><?php endif;?></tbody></table></div></article></main></section></body></html>