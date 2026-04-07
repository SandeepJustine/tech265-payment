<?php
/**
 * Tech265 – .env File Loader
 *
 * Parses the .env file and makes all variables available via:
 *   getenv('KEY')       — standard PHP
 *   $_ENV['KEY']        — superglobal
 *   $_SERVER['KEY']     — Apache-style
 *
 * Features:
 *   - Ignores blank lines and # comments
 *   - Strips inline comments (value=foo # comment → foo)
 *   - Handles quoted values: KEY="hello world" or KEY='hello world'
 *   - Supports multi-word values without quotes: KEY=hello world
 *   - Does NOT override variables already set in the environment
 *     (so Apache SetEnv / system env always wins over .env)
 *   - Safe to call multiple times (loads only once)
 */
class EnvLoader
{
    private static bool $loaded = false;

    /**
     * Load the given .env file.
     *
     * @param string $filePath  Absolute path to the .env file.
     * @param bool   $override  If true, overwrite existing env vars.
     */
    public static function load(string $filePath, bool $override = false): void
    {
        if (self::$loaded && !$override) {
            return;
        }

        if (!file_exists($filePath)) {
            // Graceful degradation — system env vars / getenv() fallbacks still work
            error_log('[EnvLoader] .env file not found: ' . $filePath);
            return;
        }

        if (!is_readable($filePath)) {
            error_log('[EnvLoader] .env file is not readable: ' . $filePath);
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            error_log('[EnvLoader] Failed to read .env file: ' . $filePath);
            return;
        }

        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);

            // Skip blank lines and full-line comments
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            // Must contain an = sign
            if (strpos($line, '=') === false) {
                continue;
            }

            [$key, $value] = self::parseLine($line);

            if ($key === '') {
                continue;
            }

            // Do not override variables already set in the real environment
            // (e.g. set via Apache SetEnv or the OS shell), unless $override = true
            if (!$override && getenv($key) !== false) {
                continue;
            }

            // Inject into all three PHP superglobals
            putenv("{$key}={$value}");
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
        }

        self::$loaded = true;
    }

    /**
     * Parse a single KEY=VALUE line.
     * Returns [trimmed_key, trimmed_value].
     */
    private static function parseLine(string $line): array
    {
        // Split only on the first = sign
        $eqPos = strpos($line, '=');
        $key   = trim(substr($line, 0, $eqPos));
        $value = trim(substr($line, $eqPos + 1));

        // Remove export prefix (e.g. "export KEY=value")
        if (strpos($key, 'export ') === 0) {
            $key = trim(substr($key, 7));
        }

        // Strip inline comments (only outside quoted values)
        // e.g.  VALUE=hello  # this is a comment  →  hello
        $value = self::stripInlineComment($value);

        // Unwrap surrounding quotes (single or double)
        $value = self::unquote($value);

        return [$key, $value];
    }

    /**
     * Remove trailing inline comment from a value string.
     * Handles quoted strings so  KEY="foo # not a comment"  works correctly.
     */
    private static function stripInlineComment(string $value): string
    {
        // If wrapped in quotes, the comment is outside the quotes
        if (strlen($value) >= 2) {
            $first = $value[0];
            if ($first === '"' || $first === "'") {
                // Find the closing matching quote
                $close = strpos($value, $first, 1);
                if ($close !== false) {
                    // Everything after the closing quote is a comment
                    return substr($value, 0, $close + 1);
                }
                return $value;
            }
        }

        // Unquoted value — strip from first # that has whitespace before it
        $commentPos = strpos($value, ' #');
        if ($commentPos !== false) {
            $value = trim(substr($value, 0, $commentPos));
        }

        // Also handle tab before comment
        $commentPos = strpos($value, "\t#");
        if ($commentPos !== false) {
            $value = trim(substr($value, 0, $commentPos));
        }

        return $value;
    }

    /**
     * Remove surrounding single or double quotes from a value.
     */
    private static function unquote(string $value): string
    {
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last  = $value[strlen($value) - 1];
            if (($first === '"'  && $last === '"')  ||
                ($first === "'"  && $last === "'")) {
                return substr($value, 1, -1);
            }
        }
        return $value;
    }

    /**
     * Get a value with an optional default.
     * Checks $_ENV first, then getenv(), then returns $default.
     */
    public static function get(string $key, string $default = ''): string
    {
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        $val = getenv($key);
        return $val !== false ? $val : $default;
    }

    /**
     * Get a boolean value. Truthy strings: "true","1","yes","on"
     */
    public static function bool(string $key, bool $default = false): bool
    {
        $val = self::get($key);
        if ($val === '') return $default;
        return in_array(strtolower($val), ['true', '1', 'yes', 'on'], true);
    }

    /**
     * Get an integer value.
     */
    public static function int(string $key, int $default = 0): int
    {
        $val = self::get($key);
        return $val !== '' ? (int)$val : $default;
    }

    /**
     * Get a comma-separated value as an array.
     * e.g.  ALLOWED_CURRENCIES=MWK,USD  →  ['MWK', 'USD']
     */
    public static function arr(string $key, array $default = []): array
    {
        $val = self::get($key);
        if ($val === '') return $default;
        return array_map('trim', explode(',', $val));
    }

    /**
     * Assert that required keys are present and non-empty.
     * Throws a RuntimeException listing any missing keys.
     *
     * @param string[] $keys
     */
    public static function require(array $keys): void
    {
        $missing = [];
        foreach ($keys as $key) {
            $val = self::get($key);
            if ($val === '' || $val === 'YOUR_SECRET_KEY_HERE' || $val === 'YOUR_PUBLIC_KEY_HERE') {
                $missing[] = $key;
            }
        }
        if ($missing) {
            throw new RuntimeException(
                '[EnvLoader] Missing or unconfigured .env keys: ' . implode(', ', $missing) .
                "\nPlease update your .env file."
            );
        }
    }
}
