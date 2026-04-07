<?php
/**
 * Tech265 - Initiate Payment Endpoint
 * POST /public/pay.php
 */

require_once __DIR__ . '/../src/PayChangu.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

// Parse JSON or form body
$raw    = file_get_contents('php://input');
$params = json_decode($raw, true) ?: $_POST;

// ── Validate required fields ──────────────────────────────────
$errors = [];
$required = ['first_name', 'last_name', 'email', 'amount'];
foreach ($required as $field) {
    if (empty($params[$field])) {
        $errors[] = "Field '{$field}' is required.";
    }
}

if (!empty($params['email']) && !filter_var($params['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email address.';
}

if (!empty($params['amount']) && (!is_numeric($params['amount']) || $params['amount'] <= 0)) {
    $errors[] = 'Amount must be a positive number.';
}

if ($errors) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'errors' => $errors]);
    exit;
}

// ── Initiate payment ──────────────────────────────────────────
try {
    $paychangu = new PayChangu();
    $result    = $paychangu->initiatePayment([
        'first_name'  => htmlspecialchars(trim($params['first_name'])),
        'last_name'   => htmlspecialchars(trim($params['last_name'])),
        'email'       => trim($params['email']),
        'amount'      => (float) $params['amount'],
        'currency'    => strtoupper($params['currency'] ?? 'MWK'),
        'title'       => $params['title']       ?? APP_NAME . ' Payment',
        'description' => $params['description'] ?? 'Online Payment',
        'meta'        => $params['meta']        ?? [],
    ]);

    if ($result['success']) {
        echo json_encode([
            'status'       => 'success',
            'message'      => 'Payment initiated.',
            'tx_ref'       => $result['tx_ref'],
            'checkout_url' => $result['data']['data']['checkout_url'] ?? null,
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'status'  => 'error',
            'message' => $result['message'] ?? 'Could not initiate payment.',
            'tx_ref'  => $result['tx_ref'],
        ]);
    }

} catch (Throwable $e) {
    Logger::error('Unhandled exception in pay.php: ' . $e->getMessage());
    Logger::logError(Logger::fromThrowable($e, ['error_type' => 'UNHANDLED_EXCEPTION']));
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal server error.']);
}
