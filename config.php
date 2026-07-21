<?php
// ============================================
// CONFIGURATION FILE
// ============================================

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'water_management_system');
define('DB_USER', 'devieeh');
define('DB_PASS', 'Devieh@22');

// Application configuration
define('APP_NAME', 'Water Management System');
define('APP_URL', 'http://176.34.133.248/water_ms/');
define('TIMEZONE', 'Africa/Dar_es_Salaam');

// Encryption configuration
// NOTE: Badilisha hii na ufunguo wako halisi wa herufi 32 (angalia maelezo chini)
define('ENCRYPTION_KEY', 'ChangeThisTo32CharsRandomKey!!!!'); // Must be exactly 32 bytes for AES-256
define('ENCRYPTION_METHOD', 'AES-256-CBC');

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Weka 1 kama unatumia HTTPS

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set timezone
date_default_timezone_set(TIMEZONE);

// Database connection function
function getDB() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}
?>
