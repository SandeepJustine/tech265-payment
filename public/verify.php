<?php
/**
 * Tech265 - Verify Transaction Endpoint
 * GET /public/verify.php?tx_ref=...
 */

require_once __DIR__ . '/../src/PayChangu.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../src/Security.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

$txRef = $_GET['tx_ref'] ?? null;

if (!$txRef || !Security::isValidRef($txRef)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing tx_ref.']);
    exit;
}

try {
    $paychangu = new PayChangu();
    $result    = $paychangu->verifyTransaction(trim($txRef));

    if ($result['success']) {
        echo json_encode(['status' => 'success', 'data' => $result['data']]);
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => $result['message']]);
    }
} catch (Throwable $e) {
    Logger::error('verify.php exception: ' . $e->getMessage());
    Logger::logError(Logger::fromThrowable($e, ['error_type' => 'VERIFY_EXCEPTION', 'tx_ref' => $txRef]));
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal server error.']);
}
