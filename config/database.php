<?php
/**
 * config/database.php
 * The one and only database connection. Every page loads it with require_once,
 * so the settings below live in exactly one place.
 *
 * XAMPP default MySQL login: user "root", empty password.
 */
$host = 'localhost';
$db   = 'library_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // errors become exceptions we can catch
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // rows come back as ['column' => value]
        PDO::ATTR_EMULATE_PREPARES   => false,                    // real prepared statements
    ]);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());   // details go to the log only
    die('Database connection failed. Please contact the administrator.');
}
