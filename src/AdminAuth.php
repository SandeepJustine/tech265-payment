<?php
/**
 * Tech265 - Admin Auth Helper
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Logger.php';

class AdminAuth
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(ADMIN_SESSION_NAME);
            session_set_cookie_params([
                'lifetime' => ADMIN_SESSION_LIFETIME,
                'path'     => '/',
                'secure'   => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            session_start();
        }
    }

    public static function isLoggedIn(): bool
    {
        self::startSession();
        if (empty($_SESSION['admin_id'])) return false;
        // Check session expiry
        if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > ADMIN_SESSION_LIFETIME) {
            self::logout();
            return false;
        }
        $_SESSION['last_activity'] = time();
        return true;
    }

    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            header('Location: ' . APP_URL . '/admin/login.php');
            exit;
        }
    }

    public static function login(string $username, string $password): bool
    {
        self::startSession();
        try {
            $user = Database::query(
                "SELECT * FROM admin_users WHERE username = ? AND is_active = 1 LIMIT 1",
                [trim($username)]
            )->fetch();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id']       = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_fullname'] = $user['full_name'];
                $_SESSION['admin_role']     = $user['role'];
                $_SESSION['last_activity']  = time();

                Database::update('admin_users', ['last_login' => date('Y-m-d H:i:s')], ['id' => $user['id']]);
                Logger::info('Admin login', ['username' => $username, 'ip' => Logger::getIp()]);
                return true;
            }
        } catch (Throwable $e) {
            Logger::error('Admin login error: ' . $e->getMessage());
        }
        Logger::warning('Failed login attempt', ['username' => $username, 'ip' => Logger::getIp()]);
        return false;
    }

    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        session_destroy();
    }

    public static function currentUser(): array
    {
        return [
            'id'       => $_SESSION['admin_id']       ?? 0,
            'username' => $_SESSION['admin_username'] ?? '',
            'fullname' => $_SESSION['admin_fullname'] ?? '',
            'role'     => $_SESSION['admin_role']     ?? '',
        ];
    }
}
