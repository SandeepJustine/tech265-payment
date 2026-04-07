<?php
/**
 * Tech265 - Return URL Handler
 * PayChangu redirects here when customer cancels or payment repeatedly fails
 * GET /public/return.php?tx_ref=...&status=failed
 */

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Logger.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';

$txRef  = $_GET['tx_ref'] ?? null;
$status = $_GET['status'] ?? 'failed';

if ($txRef) {
    Logger::info('Return URL hit', ['tx_ref' => $txRef, 'status' => $status]);
    Logger::logApiActivity([
        'tx_ref'  => $txRef,
        'action'  => 'RETURN_URL',
        'endpoint'=> APP_URL . '/public/return.php',
        'method'  => 'GET',
        'request_data' => ['status' => $status],
    ]);

    try {
        Database::update('transactions',
            ['status' => 'cancelled'],
            ['tx_ref' => $txRef, 'status' => 'pending']
        );
    } catch (Throwable $e) {
        Logger::error('Return URL DB update failed: ' . $e->getMessage());
    }
}

// Redirect to result page
$url = APP_URL . '/public/result.php?verified=0';
if ($txRef) $url .= '&tx_ref=' . urlencode($txRef);
header('Location: ' . $url);
exit;
