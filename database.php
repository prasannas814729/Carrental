<?php
// ============================================================
//  RentX — Database Configuration
// ============================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'rentx_db');
define('DB_USER', 'root');
define('DB_PASS', '');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            die("<h2 style='color:red;font-family:sans-serif;padding:2rem'>Database Error: " . $e->getMessage() . "</h2>");
        }
    }
    return $pdo;
}
