<?php
/**
 * Tech265 – Security Utility
 * Centralises CSRF protection, HTTP security headers, and input validation.
 */
class Security
{
    // ── Security Headers ─────────────────────────────────────

    /**
     * Emit HTTP security headers for HTML pages.
     * Must be called before any output.
     */
    public static function sendSecurityHeaders(): void
    {
        // Prevent clickjacking
        header('X-Frame-Options: DENY');
        // Prevent MIME-type sniffing
        header('X-Content-Type-Options: nosniff');
        // Legacy XSS filter (belt-and-suspenders for older browsers)
        header('X-XSS-Protection: 1; mode=block');
        // Limit referrer information leakage
        header('Referrer-Policy: strict-origin-when-cross-origin');
        // Restrict browser feature access
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=self');
        // Enforce HTTPS for 1 year (only meaningful when served over HTTPS)
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        // Content Security Policy
        header(
            "Content-Security-Policy: " .
            "default-src 'self'; " .
            "script-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; " .
            "style-src 'self' 'unsafe-inline'; " .
            "img-src 'self' data: https:; " .
            "font-src 'self'; " .
            "connect-src 'self'; " .
            "frame-ancestors 'none';"
        );
    }

    // ── CSRF ─────────────────────────────────────────────────

    /**
     * Return (and lazily generate) the current session CSRF token.
     * Requires an active session.
     */
    public static function csrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate the CSRF token submitted with a POST request.
     * Terminates with HTTP 403 on failure.
     */
    public static function validateCsrf(): void
    {
        $submitted = $_POST['csrf_token'] ?? '';
        $expected  = $_SESSION['csrf_token'] ?? '';
        if (!$expected || !hash_equals($expected, $submitted)) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            exit('CSRF validation failed.');
        }
    }

    // ── Input Validation ─────────────────────────────────────

    /**
     * Validate a transaction / charge reference string.
     * Accepts alphanumeric characters, hyphens, and underscores — max 100 chars.
     */
    public static function isValidRef(?string $ref): bool
    {
        return $ref !== null
            && strlen($ref) >= 1
            && strlen($ref) <= 100
            && preg_match('/^[A-Za-z0-9\-_]+$/', $ref) === 1;
    }

    // ── Output Escaping ───────────────────────────────────────

    /**
     * HTML-encode a value for safe output in an HTML context.
     */
    public static function h(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
