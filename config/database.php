<?php
// config/database.php

define('DB_HOST', 'localhost');
<<<<<<< HEAD
define('DB_NAME', 'swimming_academy_migrated');
define('DB_USER', 'root');        // ← change to your DB user
define('DB_PASS', '');            // ← change to your DB password
=======
define('DB_NAME', 'swimming_academy');
define('DB_USER', 'adults');        // ← change to your DB user
define('DB_PASS', 'ThisisMyVeryStrongPassword');            // ← change to your DB password
>>>>>>> 3bee976 (adding my data)
define('DB_CHARSET', 'utf8mb4');
define('DB_PORT', 3306);

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            $pdo->exec("SET time_zone = '+02:00'");
        } catch (PDOException $e) {
            error_log('DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            die('Database connection failed. Please try again later.');
        }
    }
    return $pdo;
}
