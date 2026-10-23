<?php
declare(strict_types=1);

final class Paystack {
    private string $secretKey;

    public function __construct() {
        $this->secretKey = trim((string)(getenv('DOGOFLOW_PAYSTACK_SECRET') ?: ''));
    }

    public static function isConfigured(): bool {
        return trim((string)(getenv('DOGOFLOW_PAYSTACK_SECRET') ?: '')) !== '';
    }

    public static function validSignature(string $payload, string $signature): bool {
        $secret = trim((string)(getenv('DOGOFLOW_PAYSTACK_SECRET') ?: ''));
        return $secret !== '' && $signature !== '' && hash_equals(hash_hmac('sha512', $payload, $secret), $signature);
    }

    private function request(string $method, string $endpoint, ?array $payload = null): array {
        if ($this->secretKey === '') {
            throw new RuntimeException('Paystack secret key is not configured.');
        }

        $ch = curl_init('https://api.paystack.co/' . ltrim($endpoint, '/'));
        $headers = [
            'Authorization: Bearer ' . $this->secretKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
        ];
        if ($payload !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_SLASHES);
        }
        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $error !== '') {
            throw new RuntimeException('Payment gateway request failed.');
        }
        $data = json_decode($body, true);
        if (!is_array($data) || $status >= 400 || empty($data['status'])) {
            $message = is_array($data) ? (string)($data['message'] ?? 'Payment gateway rejected the request.') : 'Invalid payment gateway response.';
            throw new RuntimeException($message);
        }
        return $data;
    }

    public function initialize(string $email, int $amountMinor, string $reference, string $callbackUrl, array $metadata = []): array {
        if ($amountMinor < 100) {
            throw new RuntimeException('Payment amount is below the minimum supported amount.');
        }
        return $this->request('POST', 'transaction/initialize', [
            'email' => $email,
            'amount' => (string)$amountMinor,
            'currency' => 'GHS',
            'reference' => $reference,
            'callback_url' => $callbackUrl,
            'metadata' => $metadata,
        ]);
    }

    public function verify(string $reference): array {
        return $this->request('GET', 'transaction/verify/' . rawurlencode($reference));
    }
}
