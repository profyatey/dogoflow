<?php
declare(strict_types=1);
final class NotificationService
{
    /**
     * Queues an email and, optionally, an in-app notification.
     * Verification and password-reset emails pass $inApp = false so their links
     * never appear in a user's notification list.
     */
    public static function queue(PDO $pdo, int $userId, string $subject, string $message, bool $email = true, bool $inApp = true): void
    {
        if ($userId < 1 || trim($message) === '') throw new InvalidArgumentException('A valid user and message are required.');
        $subject = trim($subject); $message = trim($message);
        $subjectValue = $subject !== '' ? mb_substr($subject, 0, 180) : null;

        // Sign-in links (verification, password reset) are never shown as in-app notifications.
        $isAuthLink = (bool) preg_match('#/auth/(verify|reset-password)\.php#', $message);
        if ($inApp && !$isAuthLink) {
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id,channel,subject,message,status) VALUES (?,'in_app',?,?, 'sent')");
            $stmt->execute([$userId, $subjectValue, $message]);
        }

        if ($email) {
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id,channel,subject,message,status) VALUES (?,'email',?,?, 'queued')");
            $stmt->execute([$userId, $subjectValue, $message]);
            $notificationId = (int) $pdo->lastInsertId();

            // Send immediately when SMTP is configured. The queue remains as a
            // fallback for shared-hosting cron/manual processing.
            try {
                $userStmt = $pdo->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
                $userStmt->execute([$userId]);
                $recipient = trim((string) $userStmt->fetchColumn());
                if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    SmtpMailer::send($recipient, $subject !== '' ? $subject : 'DogoFlow notification', $message);
                    $pdo->prepare("UPDATE notifications SET status='sent', sent_at=NOW(), attempts=attempts+1 WHERE id=? AND status='queued'")
                        ->execute([$notificationId]);
                }
            } catch (Throwable $e) {
                error_log('DogoFlow SMTP notification failed: ' . $e->getMessage());
                $pdo->prepare("UPDATE notifications SET attempts=attempts+1 WHERE id=? AND status='queued'")
                    ->execute([$notificationId]);
            }
        }
    }
}
