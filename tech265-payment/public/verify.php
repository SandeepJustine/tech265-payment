<?php
/**
 * Tech265 - Verify Transaction Endpoint
 * GET /public/verify.php?tx_ref=...
 */

require_once __DIR__ . '/../src/PayChangu.php';

header('Content-Type: application/json');

$txRef = $_GET['tx_ref'] ?? null;

if (!$txRef) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'tx_ref is required.']);
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
