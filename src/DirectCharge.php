<?php
/**
 * Tech265 – PayChangu Direct Charge & Payout Service
 *
 * Unified payment dispatch — one charge() call handles both
 * Mobile Money and Bank Transfer; one payout() call handles
 * both MoMo and Bank payouts. The 'operator' field decides
 * which PayChangu endpoint is used behind the scenes.
 *
 * Operator values:
 *   'momo'          → Mobile Money (POST /mobile-money/payments/initialize)
 *   'bank_transfer' → Bank Transfer (POST /direct-charge/payments/initialize)
 *
 * Payout operator values:
 *   'momo'          → MoMo Payout  (POST /mobile-money/payouts/initialize)
 *   'bank_transfer' → Bank Payout  (POST /direct-charge/payouts/initialize)
 *
 * Every outbound call → api_logs
 * Every error         → error_logs
 * Every charge/payout → direct_charges / payouts tables
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../config/constants.php';

class DirectCharge
{
    private string $secretKey;
    private string $baseUrl;
    private int    $timeout;

    // Recognised operator slugs
    const OP_MOMO          = 'momo';
    const OP_BANK_TRANSFER = 'bank_transfer';

    public function __construct()
    {
        $this->secretKey = PAYCHANGU_SECRET_KEY;
        $this->baseUrl   = rtrim(PAYCHANGU_API_BASE, '/');
        $this->timeout   = PAYCHANGU_TIMEOUT;
    }

    // ════════════════════════════════════════════════════════
    // ── OPERATORS / BANKS  (discovery endpoints) ─────────────
    // ════════════════════════════════════════════════════════

    /**
     * GET /mobile-money
     * Returns all MoMo operators (used for both charges and payouts).
     */
    public function getOperators(): array
    {
        return $this->request('GET', '/mobile-money', [], null, 'GET_OPERATORS');
    }

    /**
     * GET /direct-charge/payouts/supported-banks?currency={MWK|USD}
     * Returns all banks available for bank-transfer payouts.
     */
    public function getSupportedBanks(string $currency = 'MWK'): array
    {
        return $this->request(
            'GET',
            '/direct-charge/payouts/supported-banks?currency=' . strtoupper($currency),
            [], null, 'GET_SUPPORTED_BANKS'
        );
    }

    // ════════════════════════════════════════════════════════
    // ── UNIFIED CHARGE  (collection) ────────────────────────
    // ════════════════════════════════════════════════════════

    /**
     * Initiate a direct charge using either MoMo or Bank Transfer.
     *
     * The 'operator' field controls routing:
     *   'momo'          → POST /mobile-money/payments/initialize
     *   'bank_transfer' → POST /direct-charge/payments/initialize
     *
     * Common required fields:
     *   charge_id   string   Your unique reference (e.g. PC-T265-XXXXXXXX)
     *   operator    string   'momo' | 'bank_transfer'
     *   amount      numeric  Amount to collect
     *   currency    string   'MWK'
     *
     * MoMo-specific (operator = 'momo'):
     *   mobile_money_operator_ref_id  UUID from getOperators()
     *   mobile                        Customer phone number
     *   first_name, last_name, email  (optional)
     *
     * Bank-specific (operator = 'bank_transfer'):
     *   create_permanent_account  'true'|'false'  (optional, default 'false')
     *   — amount is optional for permanent accounts
     *
     * @return array  ['success'=>bool, 'data'=>..., 'message'=>..., 'operator'=>..., 'charge_id'=>...]
     */
    public function charge(array $params): array
    {
        $operator = strtolower(trim($params['operator'] ?? ''));
        $chargeId = $params['charge_id'] ?? null;

        if ($operator === self::OP_MOMO) {
            // Strip our internal 'operator' key before forwarding
            $payload  = $this->stripInternalKeys($params);
            $result   = $this->request(
                'POST', '/mobile-money/payments/initialize',
                $payload, $chargeId, 'DIRECT_CHARGE_MOMO'
            );

        } elseif ($operator === self::OP_BANK_TRANSFER) {
            $payload                     = $this->stripInternalKeys($params);
            $payload['payment_method']   = 'mobile_bank_transfer';
            $payload['currency']         = strtoupper($payload['currency'] ?? 'MWK');
            // Cast to a real PHP boolean so json_encode produces true/false, not "true"/"false"
            $isPerm = $payload['create_permanent_account'] ?? false;
            $payload['create_permanent_account'] = filter_var($isPerm, FILTER_VALIDATE_BOOLEAN);
            $result = $this->request(
                'POST', '/direct-charge/payments/initialize',
                $payload, $chargeId, 'DIRECT_CHARGE_BANK'
            );

        } else {
            return [
                'success'   => false,
                'message'   => "Invalid operator '{$operator}'. Use 'momo' or 'bank_transfer'.",
                'charge_id' => $chargeId,
            ];
        }

        // Persist record with resolved type
        if ($result['success']) {
            $this->saveCharge($chargeId, $operator, $params, $result);
        }

        $result['operator']   = $operator;
        $result['charge_id']  = $chargeId;
        return $result;
    }

    // ════════════════════════════════════════════════════════
    // ── CHARGE STATUS / DETAILS ──────────────────────────────
    // ════════════════════════════════════════════════════════

    /**
     * Verify a charge. The 'operator' field determines which
     * PayChangu endpoint to hit.
     *
     * MoMo   → GET /mobile-money/payments/{chargeId}/verify
     * Bank   → GET /direct-charge/transactions/{chargeId}/details
     */
    public function verifyCharge(string $chargeId, string $operator): array
    {
        $operator = strtolower(trim($operator));

        if ($operator === self::OP_MOMO) {
            $result = $this->request(
                'GET', "/mobile-money/payments/{$chargeId}/verify",
                [], $chargeId, 'VERIFY_CHARGE_MOMO'
            );
        } elseif ($operator === self::OP_BANK_TRANSFER) {
            $result = $this->request(
                'GET', "/direct-charge/transactions/{$chargeId}/details",
                [], $chargeId, 'VERIFY_CHARGE_BANK'
            );
        } else {
            return ['success' => false, 'message' => "Invalid operator '{$operator}'."];
        }

        // Sync status in our DB
        if ($result['success']) {
            $status = $this->extractStatus($result['data'], $operator);
            if ($status) {
                $this->syncChargeStatus($chargeId, $status);
            }
        }

        $result['operator']  = $operator;
        $result['charge_id'] = $chargeId;
        return $result;
    }

    /**
     * Get full details of a charge.
     *
     * MoMo   → GET /mobile-money/payments/{chargeId}/details
     * Bank   → GET /direct-charge/transactions/{chargeId}/details
     */
    public function getChargeDetails(string $chargeId, string $operator): array
    {
        $operator = strtolower(trim($operator));

        if ($operator === self::OP_MOMO) {
            $result = $this->request(
                'GET', "/mobile-money/payments/{$chargeId}/details",
                [], $chargeId, 'CHARGE_DETAILS_MOMO'
            );
        } elseif ($operator === self::OP_BANK_TRANSFER) {
            $result = $this->request(
                'GET', "/direct-charge/transactions/{$chargeId}/details",
                [], $chargeId, 'CHARGE_DETAILS_BANK'
            );
        } else {
            return ['success' => false, 'message' => "Invalid operator '{$operator}'."];
        }

        if ($result['success']) {
            $status = $this->extractStatus($result['data'], $operator);
            if ($status) {
                $this->syncChargeStatus($chargeId, $status);
            }
        }

        $result['operator']  = $operator;
        $result['charge_id'] = $chargeId;
        return $result;
    }

    // ════════════════════════════════════════════════════════
    // ── UNIFIED PAYOUT  (disbursement) ──────────────────────
    // ════════════════════════════════════════════════════════

    /**
     * Initiate a payout using either MoMo or Bank Transfer.
     *
     * The 'operator' field controls routing:
     *   'momo'          → POST /mobile-money/payouts/initialize
     *   'bank_transfer' → POST /direct-charge/payouts/initialize
     *
     * Common required fields:
     *   charge_id   string   Your unique payout reference
     *   operator    string   'momo' | 'bank_transfer'
     *   amount      numeric  Amount to disburse
     *
     * MoMo-specific (operator = 'momo'):
     *   mobile_money_operator_ref_id  UUID from getOperators()
     *   mobile                        Recipient phone number
     *
     * Bank-specific (operator = 'bank_transfer'):
     *   bank_uuid            UUID from getSupportedBanks()
     *   bank_account_name    Recipient full name
     *   bank_account_number  Recipient account number
     *
     * @return array  ['success'=>bool, 'data'=>..., 'message'=>..., 'operator'=>..., 'charge_id'=>...]
     */
    public function payout(array $params): array
    {
        $operator = strtolower(trim($params['operator'] ?? ''));
        $chargeId = $params['charge_id'] ?? null;

        if ($operator === self::OP_MOMO) {
            $payload = $this->stripInternalKeys($params);
            $result  = $this->request(
                'POST', '/mobile-money/payouts/initialize',
                $payload, $chargeId, 'PAYOUT_MOMO'
            );

        } elseif ($operator === self::OP_BANK_TRANSFER) {
            $payload                   = $this->stripInternalKeys($params);
            $payload['payout_method']  = 'bank_transfer';
            $result = $this->request(
                'POST', '/direct-charge/payouts/initialize',
                $payload, $chargeId, 'PAYOUT_BANK'
            );

        } else {
            return [
                'success'   => false,
                'message'   => "Invalid operator '{$operator}'. Use 'momo' or 'bank_transfer'.",
                'charge_id' => $chargeId,
            ];
        }

        if ($result['success']) {
            $this->savePayout($chargeId, $operator, $params, $result);
        }

        $result['operator']  = $operator;
        $result['charge_id'] = $chargeId;
        return $result;
    }

    // ════════════════════════════════════════════════════════
    // ── PAYOUT STATUS / DETAILS ──────────────────────────────
    // ════════════════════════════════════════════════════════

    /**
     * Get details of a payout by charge_id.
     *
     * MoMo   → GET /mobile-money/payments/{chargeId}/details
     * Bank   → GET /direct-charge/payouts/{chargeId}/details
     */
    public function getPayoutDetails(string $chargeId, string $operator): array
    {
        $operator = strtolower(trim($operator));

        if ($operator === self::OP_MOMO) {
            $result = $this->request(
                'GET', "/mobile-money/payments/{$chargeId}/details",
                [], $chargeId, 'PAYOUT_DETAILS_MOMO'
            );
        } elseif ($operator === self::OP_BANK_TRANSFER) {
            $result = $this->request(
                'GET', "/direct-charge/payouts/{$chargeId}/details",
                [], $chargeId, 'PAYOUT_DETAILS_BANK'
            );
        } else {
            return ['success' => false, 'message' => "Invalid operator '{$operator}'."];
        }

        if ($result['success']) {
            $status      = $this->extractStatus($result['data'], $operator);
            $completedAt = $this->extractCompletedAt($result['data']);
            $extra       = $completedAt ? ['completed_at' => $completedAt] : [];
            if ($status) {
                $this->syncPayoutStatus($chargeId, $status, $extra);
            }
        }

        $result['operator']  = $operator;
        $result['charge_id'] = $chargeId;
        return $result;
    }

    /**
     * GET /direct-charge/payouts
     * List all bank payouts on the account.
     * (No operator-specific equivalent for MoMo list.)
     */
    public function listPayouts(): array
    {
        return $this->request('GET', '/direct-charge/payouts', [], null, 'PAYOUT_LIST_ALL');
    }

    // ════════════════════════════════════════════════════════
    // ── HELPERS ─────────────────────────────────────────────
    // ════════════════════════════════════════════════════════

    /**
     * Generate a unique charge_id.
     * e.g.  PC-T265-A3F9B2C1-1700000000
     */
    public static function generateChargeId(): string
    {
        $prefix = defined('CHARGE_ID_PREFIX') ? CHARGE_ID_PREFIX : 'PC-T265';
        return $prefix . '-' . strtoupper(bin2hex(random_bytes(4))) . '-' . time();
    }

    /** Strip keys that are internal to our API and should not be forwarded to PayChangu */
    private function stripInternalKeys(array $params): array
    {
        unset($params['operator']);   // routing key — not a PayChangu field
        return $params;
    }

    /** Extract a status string from a PayChangu response regardless of structure */
    private function extractStatus(array $data, string $operator): ?string
    {
        // MoMo response: data.status
        if (isset($data['status'])) {
            return $data['status'];
        }
        // Bank response: data.transaction.status
        if (isset($data['transaction']['status'])) {
            return $data['transaction']['status'];
        }
        return null;
    }

    /** Extract completed_at from various response structures */
    private function extractCompletedAt(array $data): ?string
    {
        $raw = $data['completed_at']
            ?? $data['transaction']['completed_at']
            ?? null;
        return $raw ? date('Y-m-d H:i:s', strtotime($raw)) : null;
    }

    // ── DB persistence ──────────────────────────────────────

    private function saveCharge(string $chargeId, string $type, array $params, array $result): void
    {
        try {
            $data = $result['data'] ?? [];
            $txn  = $data['transaction'] ?? $data;
            $acct = $data['payment_account_details'] ?? [];   // bank-only
            $momo = $txn['mobile_money'] ?? [];               // momo-only

            $expiresAt = null;
            if (!empty($acct['account_expiration_timestamp'])) {
                $expiresAt = date('Y-m-d H:i:s', (int)$acct['account_expiration_timestamp']);
            }

            Database::insert('direct_charges', [
                'charge_id'            => $chargeId,
                'charge_type'          => $type,
                'status'               => $txn['status']   ?? 'pending',
                'amount'               => $txn['amount']   ?? ($params['amount'] ?? null),
                'currency'             => $txn['currency'] ?? ($params['currency'] ?? 'MWK'),
                'mobile'               => $params['mobile'] ?? null,
                'operator_ref_id'      => $params['mobile_money_operator_ref_id'] ?? null,
                'operator_name'        => $momo['name'] ?? null,
                'first_name'           => $params['first_name'] ?? null,
                'last_name'            => $params['last_name']  ?? null,
                'email'                => $params['email']      ?? null,
                'bank_name'            => $acct['bank_name']      ?? null,
                'account_number'       => $acct['account_number'] ?? null,
                'account_name'         => $acct['account_name']   ?? null,
                'account_expires_at'   => $expiresAt,
                'is_permanent_account' => !empty($params['create_permanent_account']) && filter_var($params['create_permanent_account'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
                'ref_id'               => $txn['ref_id']   ?? null,
                'trans_id'             => $txn['trans_id'] ?? null,
                'trace_id'             => $txn['trace_id'] ?? null,
                'charges_amount'       => $txn['transaction_charges']['amount'] ?? 0,
                'mode'                 => $txn['mode'] ?? null,
                'raw_response'         => json_encode($data),
                'ip_address'           => Logger::getIp(),
            ]);
        } catch (Throwable $e) {
            Logger::error('DirectCharge::saveCharge failed: ' . $e->getMessage());
        }
    }

    private function savePayout(string $chargeId, string $type, array $params, array $result): void
    {
        try {
            $data = $result['data'] ?? [];
            $txn  = $data['transaction'] ?? $data;
            $acct = $txn['recipient_account_details'] ?? [];
            $momo = $txn['mobile_money'] ?? [];

            $completedAt = $this->extractCompletedAt($data);

            Database::insert('payouts', [
                'charge_id'           => $chargeId,
                'payout_type'         => $type,
                'status'              => $txn['status']   ?? 'pending',
                'amount'              => $txn['amount']   ?? ($params['amount'] ?? 0),
                'currency'            => $txn['currency'] ?? 'MWK',
                'mobile'              => $params['mobile'] ?? null,
                'operator_ref_id'     => $params['mobile_money_operator_ref_id'] ?? null,
                'operator_name'       => $momo['name'] ?? null,
                'bank_uuid'           => $acct['bank_uuid']     ?? ($params['bank_uuid'] ?? null),
                'bank_name'           => $acct['bank_name']     ?? null,
                'bank_account_name'   => $acct['account_name']  ?? ($params['bank_account_name']   ?? null),
                'bank_account_number' => $acct['account_number']?? ($params['bank_account_number'] ?? null),
                'ref_id'              => $txn['ref_id']   ?? null,
                'trans_id'            => $txn['trans_id'] ?? null,
                'trace_id'            => $txn['trace_id'] ?? null,
                'charges_amount'      => $txn['transaction_charges']['amount'] ?? 0,
                'mode'                => $txn['mode'] ?? null,
                'raw_response'        => json_encode($data),
                'ip_address'          => Logger::getIp(),
                'completed_at'        => $completedAt,
            ]);
        } catch (Throwable $e) {
            Logger::error('DirectCharge::savePayout failed: ' . $e->getMessage());
        }
    }

    private function syncChargeStatus(string $chargeId, string $status): void
    {
        try {
            Database::update('direct_charges', [
                'status'      => $status,
                'verified_at' => date('Y-m-d H:i:s'),
            ], ['charge_id' => $chargeId]);
        } catch (Throwable $e) { /* non-fatal */ }
    }

    private function syncPayoutStatus(string $chargeId, string $status, array $extra = []): void
    {
        try {
            Database::update('payouts',
                array_merge(['status' => $status], $extra),
                ['charge_id' => $chargeId]
            );
        } catch (Throwable $e) { /* non-fatal */ }
    }

    // ════════════════════════════════════════════════════════
    // ── HTTP ENGINE ─────────────────────────────────────────
    // ════════════════════════════════════════════════════════

    private function request(string $method, string $endpoint, array $payload, ?string $chargeId, string $action): array
    {
        $url   = $this->baseUrl . $endpoint;
        $start = microtime(true);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->secretKey,
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_CAINFO         => __DIR__ . '/../config/cacert.pem',
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
        $response   = ($raw && $raw !== '') ? json_decode($raw, true) : null;
        $isError    = 0;
        $result     = ['success' => false];

        if ($curlError) {
            $isError = 1;
            $msg     = "cURL error [{$action}]: {$curlError}";
            Logger::error($msg, ['charge_id' => $chargeId, 'endpoint' => $endpoint]);
            Logger::logError(['tx_ref' => $chargeId, 'error_type' => 'CURL_ERROR', 'message' => $msg,
                'context' => ['endpoint' => $endpoint, 'method' => $method]]);
            $result['message'] = 'Network error. Please try again.';

        } elseif ($httpStatus < 200 || $httpStatus >= 300) {
            $isError = 1;
            $rawMsg  = $response['message'] ?? "HTTP {$httpStatus} error";
            $apiMsg  = is_array($rawMsg) ? json_encode($rawMsg) : (string) $rawMsg;
            Logger::error("{$action} HTTP {$httpStatus}: {$apiMsg}", ['charge_id' => $chargeId]);
            Logger::logError(['tx_ref' => $chargeId, 'error_type' => 'API_HTTP_ERROR',
                'error_code' => (string) $httpStatus, 'message' => $apiMsg,
                'context' => ['endpoint' => $endpoint, 'response' => $response]]);
            $result['message']     = $apiMsg;
            $result['http_status'] = $httpStatus;

        } else {
            $apiStatus = $response['status'] ?? '';
            if (in_array($apiStatus, ['success', 'successful'], true)) {
                $result = [
                    'success' => true,
                    'data'    => $response['data']    ?? $response,
                    'message' => $response['message'] ?? 'OK',
                ];
            } else {
                $isError = 1;
                $apiMsg  = $response['message'] ?? 'Request failed.';
                Logger::warning("{$action} non-success: {$apiMsg}", ['charge_id' => $chargeId]);
                Logger::logError(['tx_ref' => $chargeId, 'error_type' => 'API_LOGIC_ERROR',
                    'message' => $apiMsg, 'context' => ['response' => $response]]);
                $result['message'] = $apiMsg;
            }
        }

        Logger::logApiActivity([
            'tx_ref'        => $chargeId,
            'action'        => $action,
            'endpoint'      => $url,
            'method'        => $method,
            'request_data'  => ($method === 'POST' && $payload) ? $payload : null,
            'response_data' => $response,
            'http_status'   => $httpStatus ?: null,
            'duration_ms'   => $durationMs,
            'is_error'      => $isError,
        ]);

        return $result;
    }
}
