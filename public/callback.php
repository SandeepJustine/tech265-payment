<?php
/**
 * Tech265 - Payment Callback / Webhook Handler
 * GET|POST /public/callback.php
 * PayChangu redirects here with ?tx_ref=... after payment
 */

require_once __DIR__ . '/../src/PayChangu.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../src/Security.php';

// ── Capture incoming data ──────────────────────────────────────
$txRef  = $_GET['tx_ref']  ?? $_POST['tx_ref']  ?? null;
$trxRef = $_GET['trx_ref'] ?? $_POST['trx_ref'] ?? null;   // PayChangu's internal ref
$status = $_GET['status']  ?? $_POST['status']  ?? null;

// Could also be a raw webhook POST
$rawBody = file_get_contents('php://input');
$webhook = $rawBody ? json_decode($rawBody, true) : null;

if ($webhook && isset($webhook['tx_ref'])) {
    $txRef  = $txRef  ?: $webhook['tx_ref'];
    $trxRef = $trxRef ?: ($webhook['trx_ref'] ?? null);
    $status = $status ?: ($webhook['status'] ?? null);
}

// Validate transaction reference format before any further processing
if ($txRef !== null && !Security::isValidRef($txRef)) {
    http_response_code(400);
    die('Invalid transaction reference format.');
}

// Log the webhook payload
Logger::logWebhook([
    'tx_ref'     => $txRef,
    'event_type' => $webhook['event'] ?? 'checkout.payment',
    'payload'    => $webhook ?: array_merge($_GET, $_POST),
    'processed'  => 0,
]);

Logger::info('Callback received', ['tx_ref' => $txRef, 'status' => $status]);

if (!$txRef) {
    http_response_code(400);
    die('Missing transaction reference.');
}

// ── Verify with PayChangu ──────────────────────────────────────
try {
    // Persist PayChangu's trx_ref early so verifyTransaction can resolve it
    if ($trxRef && $txRef) {
        try {
            Database::update('transactions', ['transaction_id' => $trxRef], ['tx_ref' => $txRef]);
        } catch (Throwable $e) { /* non-fatal */ }
    }

    $paychangu = new PayChangu();
    $result    = $paychangu->verifyTransaction($txRef);

    // Mark webhook as processed
    Database::query(
        "UPDATE webhook_logs SET processed = 1 WHERE tx_ref = ? ORDER BY id DESC LIMIT 1",
        [$txRef]
    );

    // Redirect user to a result page
    $verified  = $result['success'] && (($result['data']['status'] ?? '') === 'success');
    $returnUrl = APP_URL . '/public/result.php?tx_ref=' . urlencode($txRef) . '&verified=' . ($verified ? '1' : '0');
    header('Location: ' . $returnUrl);
    exit;

} catch (Throwable $e) {
    Logger::error('Callback exception: ' . $e->getMessage());
    Logger::logError(Logger::fromThrowable($e, ['tx_ref' => $txRef, 'error_type' => 'CALLBACK_ERROR']));
    http_response_code(500);
    die('Verification error. Contact support with ref: ' . htmlspecialchars($txRef));
}
