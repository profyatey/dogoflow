<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';
$pdo=db();
$ref=trim((string)($_GET['ref']??''));
if($ref===''){header('Location: '.url('/auth/register.php'));exit;}
$stmt=$pdo->prepare("SELECT id, referral_code, status FROM affiliate_profiles WHERE referral_code=? AND status='active' LIMIT 1");$stmt->execute([$ref]);$profile=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$profile){header('Location: '.url('/auth/register.php'));exit;}
$productId=filter_input(INPUT_GET,'product_id',FILTER_VALIDATE_INT);$productId=$productId?:null;
$ip=(string)($_SERVER['REMOTE_ADDR']??'');$ua=substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500);$salt=(string)(getenv('DOGOFLOW_APP_KEY')?:'dogoflow-affiliate');$ipHash=$ip!==''?hash('sha256',$salt.'|'.$ip):null;
$stmt=$pdo->prepare('INSERT INTO affiliate_clicks (affiliate_id,product_id,ip_hash,user_agent) VALUES (?,?,?,?)');$stmt->execute([(int)$profile['id'],$productId,$ipHash,$ua]);
$_SESSION['_affiliate_referral_code'] = $ref;
$target=url('/auth/register.php?ref='.rawurlencode($ref)); if($productId){$target=url('/product.php?id='.(int)$productId.'&ref='.rawurlencode($ref));} header('Location: '.$target, true, 302);exit;
