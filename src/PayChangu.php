<?php
/**
 * Tech265 - PayChangu API Client
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../config/constants.php';

class PayChangu
{
    private string $secretKey;
    private string $baseUrl;
    private int    $timeout;

    public function __construct()
    {
        $this->secretKey = PAYCHANGU_SECRET_KEY;
        $this->baseUrl   = PAYCHANGU_API_BASE;
        $this->timeout   = PAYCHANGU_TIMEOUT;
    }

    // ═══════════════════════════════════════════════════════
    // PUBLIC METHODS
    // ═══════════════════════════════════════════════════════

    /**
     * Initiate a payment and return checkout URL + tx_ref.
     */
    public function initiatePayment(array $params): array
    {
        $txRef = $this->generateTxRef();

        $payload = [
            'amount'       => (string) $params['amount'],
            'currency'     => $params['currency']  ?? 'MWK',
            'email'        => $params['email'],
            'first_name'   => $params['first_name'],
            'last_name'    => $params['last_name'],
            'tx_ref'       => $txRef,
            'callback_url' => PAYCHANGU_CALLBACK_URL,
            'return_url'   => PAYCHANGU_RETURN_URL,
            'customization' => [
                'title'       => $params['title']       ?? APP_NAME,
                'description' => $params['description'] ?? 'Payment',
            ],
            'meta' => $params['meta'] ?? [],
        ];

        // Persist transaction as pending
        $this->saveTransaction([
            'tx_ref'        => $txRef,
            'first_name'    => $params['first_name'],
            'last_name'     => $params['last_name'],
            'email'         => $params['email'],
            'amount'        => $params['amount'],
            'currency'      => $params['currency'] ?? 'MWK',
            'status'        => 'pending',
            'payment_title' => $params['title']       ?? APP_NAME,
            'payment_desc'  => $params['description'] ?? 'Payment',
            'meta_data'     => json_encode($params['meta'] ?? []),
            'ip_address'    => Logger::getIp(),
            'user_agent'    => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);

        $result = $this->post('/payment', $payload, $txRef, 'INITIATE_PAYMENT');

        if ($result['success']) {
            $checkoutUrl = $result['data']['checkout_url'] ?? null;
            Database::update('transactions', ['checkout_url' => $checkoutUrl], ['tx_ref' => $txRef]);
        }

        return array_merge($result, ['tx_ref' => $txRef]);
    }

    /**
     * Verify a transaction by tx_ref.
     *
     * Also accepts PayChangu's own trx_ref (stored as transaction_id in our DB)
     * so callers can verify using either reference.
     */
    public function verifyTransaction(string $txRef): array
    {
        // Resolve to our tx_ref: support lookup by PayChangu's trx_ref (transaction_id)
        $resolvedRef = $txRef;
        try {
            $row = Database::query(
                "SELECT tx_ref FROM transactions WHERE tx_ref = ? OR transaction_id = ? LIMIT 1",
                [$txRef, $txRef]
            )->fetch();
            if ($row) {
                $resolvedRef = $row['tx_ref'];
            }
        } catch (Throwable $e) { /* silent — fall back to the passed value */ }

        $result = $this->get("/verify-payment/{$resolvedRef}", $resolvedRef, 'VERIFY_PAYMENT');

        if ($result['success'] && isset($result['data'])) {
            $d      = $result['data'];
            $status = $d['status'] ?? 'failed';
            $auth   = $d['authorization'] ?? [];

            $cols = [
                'status'          => $status,
                'payment_channel' => $auth['channel']       ?? null,
                'card_number'     => $auth['card_number']   ?? null,
                'card_brand'      => $auth['brand']         ?? null,
                'mobile_number'   => $auth['mobile_number'] ?? null,
                'charges'         => $d['charges']          ?? 0,
                'verified_at'     => date('Y-m-d H:i:s'),
            ];

            // Persist PayChangu's internal reference so future lookups by trx_ref work
            $trxRef = $d['trx_ref'] ?? $d['transaction_id'] ?? null;
            if ($trxRef) {
                $cols['transaction_id'] = $trxRef;
            }

            Database::update('transactions', $cols, ['tx_ref' => $resolvedRef]);
        }

        return $result;
    }

    // ═══════════════════════════════════════════════════════
    // HTTP HELPERS
    // ═══════════════════════════════════════════════════════

    private function post(string $endpoint, array $payload, string $txRef, string $action): array
    {
        return $this->request('POST', $endpoint, $payload, $txRef, $action);
    }

    private function get(string $endpoint, string $txRef, string $action): array
    {
        return $this->request('GET', $endpoint, [], $txRef, $action);
    }

    private function request(string $method, string $endpoint, array $payload, string $txRef, string $action): array
    {
        $url   = $this->baseUrl . $endpoint;
        $start = microtime(true);

        $ch = curl_init($url);
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];
        if (!empty($this->secretKey)) {
            $headers[] = 'Authorization: Bearer ' . $this->secretKey;
        }
        // Set CA certificate for SSL verification
        $caPath = __DIR__ . '/../config/cacert.pem';
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CAINFO         => $caPath,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $raw        = curl_exec($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError  = curl_error($ch);
        curl_close($ch);

        $durationMs = (int) round((microtime(true) - $start) * 1000);
        $response   = $raw ? json_decode($raw, true) : null;
        $isError    = 0;
        $result     = ['success' => false];

        // ── cURL level error ───────────────────────────────
        if ($curlError) {
            $isError = 1;
            $msg = "cURL error on {$action}: {$curlError}";
            Logger::error($msg, ['tx_ref' => $txRef, 'endpoint' => $endpoint]);
            Logger::logError([
                'tx_ref'     => $txRef,
                'error_type' => 'CURL_ERROR',
                'message'    => $msg,
                'context'    => ['endpoint' => $endpoint, 'method' => $method],
            ]);
            $result['message'] = 'Network error. Please try again.';

        // ── HTTP error ─────────────────────────────────────
        } elseif ($httpStatus < 200 || $httpStatus >= 300) {
            $isError = 1;
            $rawMsg  = $response['message'] ?? 'Unknown API error';
            $apiMsg  = is_array($rawMsg) ? json_encode($rawMsg) : (string) $rawMsg;
            Logger::error("{$action} HTTP {$httpStatus}: {$apiMsg}", ['tx_ref' => $txRef]);
            Logger::logError([
                'tx_ref'     => $txRef,
                'error_type' => 'API_HTTP_ERROR',
                'error_code' => (string) $httpStatus,
                'message'    => $apiMsg,
                'context'    => ['endpoint' => $endpoint, 'response' => $response],
            ]);
            $result['message'] = $apiMsg;

        // ── Success ────────────────────────────────────────
        } else {
            $apiStatus = $response['status'] ?? '';
            if ($apiStatus === 'success') {
                $result = ['success' => true, 'data' => $response['data'], 'message' => $response['message'] ?? 'OK'];
            } else {
                $isError = 1;
                $apiMsg  = $response['message'] ?? 'Payment failed.';
                Logger::warning("{$action} non-success: {$apiMsg}", ['tx_ref' => $txRef]);
                Logger::logError([
                    'tx_ref'     => $txRef,
                    'error_type' => 'API_LOGIC_ERROR',
                    'message'    => $apiMsg,
                    'context'    => ['response' => $response],
                ]);
                $result['message'] = $apiMsg;
            }
        }

        // ── Always write API log ───────────────────────────
        Logger::logApiActivity([
            'tx_ref'        => $txRef,
            'action'        => $action,
            'endpoint'      => $url,
            'method'        => $method,
            'request_data'  => $payload ?: null,
            'response_data' => $response,
            'http_status'   => $httpStatus ?: null,
            'duration_ms'   => $durationMs,
            'is_error'      => $isError,
        ]);

        return $result;
    }

    // ═══════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════

    private function generateTxRef(): string
    {
        return 'T265-' . strtoupper(bin2hex(random_bytes(6))) . '-' . time();
    }

    private function saveTransaction(array $data): void
    {
        try {
            Database::insert('transactions', $data);
        } catch (Throwable $e) {
            Logger::error('Failed to save transaction: ' . $e->getMessage());
            Logger::logError(array_merge(Logger::fromThrowable($e), ['error_type' => 'DB_SAVE_ERROR']));
        }
    }
}
