<?php
/**
 * Database configuration.
 * Edit these values to match your MySQL server.
 */
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'pharmacy_db');
define('DB_USER', 'root');
define('DB_PASS', '');          // <-- set your MySQL password here
define('DB_CHARSET', 'utf8mb4');

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        die('Database connection failed: ' . htmlspecialchars($e->getMessage())
          . '<br>Check your credentials in config/database.php and that you imported database.sql.');
    }
    return $pdo;
}
