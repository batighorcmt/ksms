<?php
session_start();

// Database configuration - পোর্ট 3307 যোগ করুন
define('DB_HOST', 'localhost');
define('DB_PORT', '3307'); // আপনার MySQL পোর্ট
define('DB_NAME', 'kindergarten_management');
define('DB_USER', 'root');
define('DB_PASS', '');

// Base URL
define('BASE_URL', 'http://localhost/ksms/');
define('BASE_URL', 'http://localhost/ksms/admin/');

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
    header("Location: " . BASE_URL . $url);
    exit();
}
?>