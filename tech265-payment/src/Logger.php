<?php
/**
 * Tech265 - Logger (file + database)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';

class Logger
{
    private static array $levels = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];

    // ── File Logging ────────────────────────────────────────

    public static function write(string $level, string $message, array $context = []): void
    {
        if (!is_dir(LOG_DIR)) {
            @mkdir(LOG_DIR, 0755, true);
        }

        $threshold = self::$levels[LOG_LEVEL] ?? 0;
        if ((self::$levels[$level] ?? 0) < $threshold) {
            return;
        }

        $line = sprintf(
            "[%s] [%s] %s %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context ? json_encode($context) : ''
        );

        $file = LOG_DIR . '/' . date('Y-m-d') . '_app.log';
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    public static function debug(string $msg, array $ctx = []): void   { self::write('debug',   $msg, $ctx); }
    public static function info(string $msg, array $ctx = []): void    { self::write('info',    $msg, $ctx); }
    public static function warning(string $msg, array $ctx = []): void { self::write('warning', $msg, $ctx); }
    public static function error(string $msg, array $ctx = []): void   { self::write('error',   $msg, $ctx); }

    // ── DB: API Activity Log ────────────────────────────────

    public static function logApiActivity(array $data): int
    {
        try {
            return Database::insert('api_logs', [
                'tx_ref'        => $data['tx_ref']        ?? null,
                'action'        => $data['action']        ?? 'unknown',
                'endpoint'      => $data['endpoint']      ?? '',
                'method'        => $data['method']        ?? 'GET',
                'request_data'  => isset($data['request_data'])  ? json_encode($data['request_data'])  : null,
                'response_data' => isset($data['response_data']) ? json_encode($data['response_data']) : null,
                'http_status'   => $data['http_status']   ?? null,
                'duration_ms'   => $data['duration_ms']   ?? null,
                'ip_address'    => $data['ip_address']    ?? self::getIp(),
                'user_agent'    => $data['user_agent']    ?? ($_SERVER['HTTP_USER_AGENT'] ?? null),
                'is_error'      => (int)($data['is_error'] ?? 0),
            ]);
        } catch (Throwable $e) {
            self::error('Failed to write api_log: ' . $e->getMessage());
            return 0;
        }
    }

    // ── DB: Error Log ───────────────────────────────────────

    public static function logError(array $data): int
    {
        try {
            return Database::insert('error_logs', [
                'tx_ref'      => $data['tx_ref']      ?? null,
                'error_type'  => $data['error_type']  ?? 'GENERAL_ERROR',
                'error_code'  => $data['error_code']  ?? null,
                'message'     => $data['message']     ?? '',
                'stack_trace' => $data['stack_trace'] ?? null,
                'context'     => isset($data['context']) ? json_encode($data['context']) : null,
                'file'        => $data['file']        ?? null,
                'line_number' => $data['line_number'] ?? null,
                'ip_address'  => $data['ip_address']  ?? self::getIp(),
            ]);
        } catch (Throwable $e) {
            self::error('Failed to write error_log: ' . $e->getMessage());
            return 0;
        }
    }

    // ── DB: Webhook Log ─────────────────────────────────────

    public static function logWebhook(array $data): int
    {
        try {
            return Database::insert('webhook_logs', [
                'tx_ref'     => $data['tx_ref']     ?? null,
                'event_type' => $data['event_type'] ?? null,
                'payload'    => isset($data['payload'])   ? json_encode($data['payload'])   : null,
                'signature'  => $data['signature']  ?? null,
                'processed'  => (int)($data['processed'] ?? 0),
                'ip_address' => $data['ip_address'] ?? self::getIp(),
            ]);
        } catch (Throwable $e) {
            self::error('Failed to write webhook_log: ' . $e->getMessage());
            return 0;
        }
    }

    // ── Helpers ─────────────────────────────────────────────

    public static function getIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_CLIENT_IP','REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                return trim(explode(',', $_SERVER[$key])[0]);
            }
        }
        return '0.0.0.0';
    }

    /** Convert a Throwable into a loggable array */
    public static function fromThrowable(Throwable $e, array $extra = []): array
    {
        return array_merge([
            'error_type'  => get_class($e),
            'error_code'  => (string) $e->getCode(),
            'message'     => $e->getMessage(),
            'stack_trace' => $e->getTraceAsString(),
            'file'        => $e->getFile(),
            'line_number' => $e->getLine(),
        ], $extra);
    }
}
