<?php
/**
 * Tech265 – API Middleware
 * Handles: CORS, JSON response helpers, API key auth, rate limiting
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/Database.php';

class ApiMiddleware
{
    // ── CORS ────────────────────────────────────────────────

    public static function cors()
    {
        // Build an explicit origin whitelist; never reflect an arbitrary HTTP_ORIGIN.
        $allowed = defined('CORS_ALLOWED_ORIGINS') ? (array) CORS_ALLOWED_ORIGINS : [];
        if (defined('APP_URL') && APP_URL) {
            $appOrigin = preg_replace('#(https?://[^/]+).*#', '$1', APP_URL);
            if ($appOrigin && !in_array($appOrigin, $allowed, true)) {
                $allowed[] = $appOrigin;
            }
        }

        $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($requestOrigin && in_array($requestOrigin, $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . $requestOrigin);
            header('Access-Control-Allow-Credentials: true');
            header('Vary: Origin');
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Requested-With');
        header('Access-Control-Max-Age: 86400');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }

    // ── JSON Response ───────────────────────────────────────

    public static function json(array $data, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-App: ' . APP_NAME . ' v' . APP_VERSION);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    public static function success($data = null, $message = 'Success', $status = 200)
    {
        $body = ['status' => 'success', 'message' => $message];
        if ($data !== null) $body['data'] = $data;
        self::json($body, $status);
    }

    public static function error($message, $status = 400, array $errors = [], $code = '')
    {
        $body = ['status' => 'error', 'message' => $message];
        if ($code)   $body['error_code'] = $code;
        if ($errors) $body['errors']     = $errors;
        self::json($body, $status);
    }

    // ── Request Body ────────────────────────────────────────

    public static function body()
    {
        $raw = file_get_contents('php://input');
        if (!$raw) return $_POST ?: [];
        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            self::error('Invalid JSON body.', 400, [], 'INVALID_JSON');
        }
        return $decoded ?? [];
    }

    // ── API Key Authentication ───────────────────────────────

    public static function authenticate($requiredRole = 'any')
    {
        // Accept key only via X-API-Key header or Authorization: Bearer header.
        // Query-parameter transport is intentionally unsupported to prevent key
        // leakage through server logs, browser history, and Referer headers.
        $key = '';
        if (!empty($_SERVER['HTTP_X_API_KEY'])) {
            $key = $_SERVER['HTTP_X_API_KEY'];
        } elseif (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            if (stripos($authHeader, 'Bearer ') === 0) {
                $key = substr($authHeader, 7);
            } else {
                $key = $authHeader;
            }
        }

        $key = trim($key);

        if (!$key) {
            self::error('API key is required. Pass it via X-API-Key header or Authorization: Bearer.', 401, [], 'MISSING_API_KEY');
        }

        $keys = API_KEYS;
        if (!array_key_exists($key, $keys)) {
            Logger::warning('Invalid API key attempt', ['ip' => Logger::getIp(), 'key_prefix' => substr($key, 0, 8) . '...']);
            self::error('Invalid API key.', 401, [], 'INVALID_API_KEY');
        }

        $meta = $keys[$key];
        $role = $meta['role'];

        // Role check
        if ($requiredRole !== 'any') {
            $allowed = false;
            if ($requiredRole === 'readonly') {
                $allowed = in_array($role, ['readonly', 'full']);
            } elseif ($requiredRole === 'webhook') {
                $allowed = in_array($role, ['webhook', 'full']);
            } elseif ($requiredRole === 'full') {
                $allowed = ($role === 'full');
            } else {
                $allowed = true;
            }

            if (!$allowed) {
                self::error('Your API key does not have permission for this action.', 403, [], 'FORBIDDEN');
            }
        }

        // Block write operations for readonly keys
        if ($role === 'readonly' && in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            self::error('Read-only key cannot perform write operations.', 403, [], 'READ_ONLY');
        }

        return $meta;
    }

    // ── Rate Limiting ────────────────────────────────────────

    public static function rateLimit($apiKeyName)
    {
        if (!RATE_LIMIT_ENABLED) return;

        try {
            $count = (int) Database::query(
                "SELECT COUNT(*) FROM api_logs
                 WHERE JSON_EXTRACT(request_data, '$.api_key_name') = ?
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
                [$apiKeyName]
            )->fetchColumn();

            if ($count >= RATE_LIMIT_RPM) {
                header('Retry-After: 60');
                header('X-RateLimit-Limit: ' . RATE_LIMIT_RPM);
                header('X-RateLimit-Remaining: 0');
                self::error(
                    'Rate limit exceeded. Max ' . RATE_LIMIT_RPM . ' requests/min.',
                    429, [], 'RATE_LIMIT_EXCEEDED'
                );
            }

            header('X-RateLimit-Limit: '     . RATE_LIMIT_RPM);
            header('X-RateLimit-Remaining: ' . max(0, RATE_LIMIT_RPM - $count - 1));
        } catch (Throwable $e) {
            // Non-fatal — log and continue
            Logger::warning('Rate limit check failed: ' . $e->getMessage());
        }
    }

    // ── Method Enforcement ───────────────────────────────────

    public static function requireMethod()
    {
        $methods = func_get_args();
        if (!in_array(strtoupper($_SERVER['REQUEST_METHOD']), array_map('strtoupper', $methods))) {
            header('Allow: ' . implode(', ', $methods));
            self::error(
                'Method ' . $_SERVER['REQUEST_METHOD'] . ' not allowed.',
                405, [], 'METHOD_NOT_ALLOWED'
            );
        }
    }

    // ── Standard Validation ──────────────────────────────────

    public static function validate(array $data, array $rules)
    {
        $errors = [];
        foreach ($rules as $field => $ruleStr) {
            $ruleList = explode('|', $ruleStr);
            $value    = isset($data[$field]) ? $data[$field] : null;

            foreach ($ruleList as $rule) {
                $parts     = explode(':', $rule, 2);
                $ruleName  = $parts[0];
                $ruleParam = isset($parts[1]) ? $parts[1] : null;

                switch ($ruleName) {
                    case 'required':
                        if ($value === null || $value === '') $errors[$field][] = "{$field} is required.";
                        break;
                    case 'numeric':
                        if ($value !== null && !is_numeric($value)) $errors[$field][] = "{$field} must be numeric.";
                        break;
                    case 'min':
                        if (is_numeric($value) && (float)$value < (float)$ruleParam) $errors[$field][] = "{$field} must be at least {$ruleParam}.";
                        break;
                    case 'email':
                        if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) $errors[$field][] = "{$field} must be a valid email.";
                        break;
                    case 'in':
                        $allowed = explode(',', $ruleParam);
                        if ($value && !in_array(strtoupper($value), array_map('strtoupper', $allowed))) {
                            $errors[$field][] = "{$field} must be one of: " . implode(', ', $allowed) . ".";
                        }
                        break;
                    case 'max_len':
                        if ($value && strlen($value) > (int)$ruleParam) $errors[$field][] = "{$field} must be {$ruleParam} characters or fewer.";
                        break;
                }
            }
        }

        if ($errors) {
            self::error('Validation failed.', 422, $errors, 'VALIDATION_ERROR');
        }

        return $data;
    }
}
