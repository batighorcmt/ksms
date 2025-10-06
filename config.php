<?php
// Start output buffering early to prevent 'headers already sent' issues (e.g., BOM / stray spaces)
if (!ob_get_level()) {
    ob_start();
}
// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuration - পোর্ট 3307 যোগ করুন
define('DB_HOST', 'localhost');
define('DB_PORT', '3307'); // আপনার MySQL পোর্ট
define('DB_NAME', 'kindergarten_management');
define('DB_USER', 'root');
define('DB_PASS', '');

// Base URL
define('BASE_URL', 'http://localhost/ksms/');
define('ADMIN_URL', BASE_URL . 'admin/');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create connection with port specification
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("set names utf8"); 
} catch(PDOException $e) {
   die("Database connection failed: " . $e->getMessage());
    $pdo = null;
}

// Authentication check function
function isAuthenticated() {
    return isset($_SESSION['user_id']);
}

// Role-based access control
function hasRole($allowedRoles) {
    if (!isAuthenticated()) return false;
    if (in_array($_SESSION['role'], $allowedRoles)) return true;
    return false;
}

// Redirect function
function redirect($url) {
    // Normalize target URL
    $url = trim($url);
    if (preg_match('#^https?://#i', $url)) {
        $target = $url; // absolute provided
    } elseif (str_starts_with($url, '/')) {
        $target = rtrim(BASE_URL, '/') . $url; // leading slash
    } elseif (str_starts_with($url, '../')) {
        // Collapse ../ to base (kept for backward compatibility with existing calls like '../login.php')
        $target = BASE_URL . ltrim(preg_replace('#^\.\./+#', '', $url), '/');
    } else {
        $target = BASE_URL . ltrim($url, '/');
    }

    if (!headers_sent()) {
        header('Location: ' . $target, true, 302);
        exit;
    }
    // Fallback if headers already sent
    echo '<script>window.location.href=' . json_encode($target) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
}

// Flush any buffered output at the end of main script (optional) – caller pages can call ob_end_flush();
?>