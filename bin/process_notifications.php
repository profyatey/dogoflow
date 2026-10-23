<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
require_once dirname(__DIR__) . '/core/bootstrap.php';
$pdo = db();
$from = trim((string)(getenv('DOGOFLOW_MAIL_FROM') ?: ''));
$limit = max(1, min(100, (int)($argv[1] ?? 25)));
if (!filter_var($from, FILTER_VALIDATE_EMAIL)) { fwrite(STDERR,"Set DOGOFLOW_MAIL_FROM to a valid sender email.\n"); exit(2); }
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("SELECT n.id,n.subject,n.message,u.email FROM notifications n JOIN users u ON u.id=n.user_id WHERE n.channel='email' AND n.status='queued' AND n.attempts < 5 ORDER BY n.id ASC LIMIT {$limit} FOR UPDATE");
    $stmt->execute(); $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $ids = array_map(static fn(array $r): int => (int)$r['id'], $rows);
    if ($ids) { $marks=implode(',',array_fill(0,count($ids),'?')); $claim=$pdo->prepare("UPDATE notifications SET status='failed',attempts=attempts+1 WHERE id IN ({$marks}) AND status='queued'"); $claim->execute($ids); }
    $pdo->commit();
} catch (Throwable $e) { if($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
$sent=0;
foreach($rows as $row) {
    $email=trim((string)$row['email']); $subject=trim((string)($row['subject']??'DogoFlow update')); $ok=false;
    if(filter_var($email,FILTER_VALIDATE_EMAIL)) {
        try { SmtpMailer::send($email, $subject, (string)$row['message']); $ok=true; }
        catch (Throwable $e) { error_log('DogoFlow SMTP queue send failed: '.$e->getMessage()); }
    }
    $state=$ok?'sent':'queued';
    $up=$pdo->prepare("UPDATE notifications SET status=?,sent_at=CASE WHEN ?='sent' THEN NOW() ELSE sent_at END WHERE id=? AND status='failed'"); $up->execute([$state,$state,(int)$row['id']]); if($ok)$sent++;
}
printf("Processed %d queued email(s); sent %d.\n",count($rows),$sent);
