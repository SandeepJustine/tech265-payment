<?php
/**
 * Tech265 - Database Connection (Singleton PDO)
 */

require_once __DIR__ . '/../config/config.php';

class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone() {}

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Log to file since DB is unavailable
                $msg = '[' . date('Y-m-d H:i:s') . '] DB_CONNECT_ERROR: ' . $e->getMessage() . PHP_EOL;
                @file_put_contents(LOG_DIR . '/db_errors.log', $msg, FILE_APPEND | LOCK_EX);
                http_response_code(503);
                die(json_encode(['status' => 'error', 'message' => 'Database unavailable.']));
            }
        }
        return self::$instance;
    }

    /** Execute a query and return the PDOStatement */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Insert a row and return the last insert ID */
    public static function insert(string $table, array $data): int
    {
        $cols   = implode(', ', array_keys($data));
        $phs    = implode(', ', array_fill(0, count($data), '?'));
        $sql    = "INSERT INTO `{$table}` ({$cols}) VALUES ({$phs})";
        self::query($sql, array_values($data));
        return (int) self::getInstance()->lastInsertId();
    }

    /** Update rows matching $where and return affected row count */
    public static function update(string $table, array $data, array $where): int
    {
        $set   = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($data)));
        $cond  = implode(' AND ', array_map(fn($k) => "`{$k}` = ?", array_keys($where)));
        $sql   = "UPDATE `{$table}` SET {$set} WHERE {$cond}";
        $stmt  = self::query($sql, array_merge(array_values($data), array_values($where)));
        return $stmt->rowCount();
    }
}
