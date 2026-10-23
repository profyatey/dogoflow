<?php
declare(strict_types=1);

/**
 * Small dependency-free SMTP client for DogoFlow shared hosting.
 * Supports authenticated SMTP with STARTTLS (port 587) and plain-text messages.
 */
final class SmtpMailer
{
    public static function send(string $to, string $subject, string $body): void
    {
        $host = trim((string) (getenv('DOGOFLOW_MAIL_HOST') ?: ''));
        $port = (int) (getenv('DOGOFLOW_MAIL_PORT') ?: 587);
        $username = trim((string) (getenv('DOGOFLOW_MAIL_USERNAME') ?: ''));
        $password = (string) (getenv('DOGOFLOW_MAIL_PASSWORD') ?: '');
        $encryption = strtolower(trim((string) (getenv('DOGOFLOW_MAIL_ENCRYPTION') ?: 'tls')));
        $from = trim((string) (getenv('DOGOFLOW_MAIL_FROM') ?: $username));
        $fromName = trim((string) (getenv('DOGOFLOW_MAIL_FROM_NAME') ?: APP_NAME));

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid recipient email address.');
        }
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('DOGOFLOW_MAIL_FROM is not configured correctly.');
        }
        if ($host === '' || $username === '' || $password === '') {
            throw new RuntimeException('SMTP credentials are not configured.');
        }
        if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
            throw new RuntimeException('Unsupported SMTP encryption setting.');
        }
        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('Invalid SMTP port.');
        }

        $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'SNI_enabled' => true,
                'peer_name' => $host,
            ],
        ]);

        $socket = @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            15,
            STREAM_CLIENT_CONNECT,
            $context
        );
        if (!is_resource($socket)) {
            throw new RuntimeException('Could not connect to SMTP server: ' . ($errstr ?: 'connection failed'));
        }

        stream_set_timeout($socket, 15);
        try {
            self::expect($socket, [220]);
            self::command($socket, 'EHLO ' . self::clientName(), [250]);

            if ($encryption === 'tls') {
                self::command($socket, 'STARTTLS', [220]);
                $crypto = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($crypto !== true) {
                    throw new RuntimeException('Could not establish SMTP TLS encryption.');
                }
                self::command($socket, 'EHLO ' . self::clientName(), [250]);
            }

            self::command($socket, 'AUTH LOGIN', [334]);
            self::command($socket, base64_encode($username), [334]);
            self::command($socket, base64_encode($password), [235]);
            self::command($socket, 'MAIL FROM:<' . $from . '>', [250]);
            self::command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            self::command($socket, 'DATA', [354]);

            $headers = [
                'From: ' . self::formatAddress($from, $fromName),
                'To: <' . $to . '>',
                'Subject: ' . mb_encode_mimeheader($subject !== '' ? $subject : 'DogoFlow notification', 'UTF-8'),
                'Date: ' . date(DATE_RFC2822),
                'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $host . '>',
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
            ];
            $cleanBody = str_replace(["\r\n", "\r"], "\n", $body);
            $cleanBody = preg_replace('/^\./m', '..', $cleanBody) ?? $cleanBody;
            $data = implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n", "\r\n", $cleanBody) . "\r\n.";
            fwrite($socket, $data . "\r\n");
            self::expect($socket, [250]);
            self::command($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }

    private static function clientName(): string
    {
        $host = preg_replace('/[^a-z0-9.-]/i', '', (string) ($_SERVER['HTTP_HOST'] ?? 'dogoflow.com'));
        return $host !== '' ? $host : 'dogoflow.com';
    }

    private static function formatAddress(string $email, string $name): string
    {
        if ($name === '') return '<' . $email . '>';
        return mb_encode_mimeheader($name, 'UTF-8') . ' <' . $email . '>';
    }

    private static function command($socket, string $command, array $expected): string
    {
        fwrite($socket, $command . "\r\n");
        return self::expect($socket, $expected);
    }

    private static function expect($socket, array $expected): string
    {
        $response = '';
        while (($line = fgets($socket, 2048)) !== false) {
            $response .= $line;
            if (preg_match('/^(\d{3})([ -])/', $line, $m)) {
                if ($m[2] === ' ') break;
            }
        }
        $code = (int) substr(trim($response), 0, 3);
        if (!in_array($code, $expected, true)) {
            throw new RuntimeException('SMTP server rejected the request (' . $code . ').');
        }
        return $response;
    }
}
