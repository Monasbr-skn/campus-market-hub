<?php
// Harden session cookies before starting session
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', 1);
}
session_start();

// Detect environment (Localhost vs Live Deployed Server)
$is_local = in_array($_SERVER['HTTP_HOST'] ?? 'localhost', ['localhost', '127.0.0.1']);

if ($is_local) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    $host     = 'localhost';
    $dbname   = 'campus_market_hub';
    $username = 'root';
    $password = '';
    define('BASE_URL', 'http://localhost/campus_market_hub');
} else {
    // Production error handling (log errors instead of exposing DB details to users)
    error_reporting(0);
    ini_set('display_errors', 0);
    
    // Update these 4 values with your live hosting credentials upon deployment
    $host     = getenv('DB_HOST') ?: 'localhost';
    $dbname   = getenv('DB_NAME') ?: 'campus_market_hub';
    $username = getenv('DB_USER') ?: 'your_live_db_user';
    $password = getenv('DB_PASS') ?: 'your_live_db_password';
    
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    define('BASE_URL', $protocol . $_SERVER['HTTP_HOST']);
}

// Special secret key required for Admins to log in
define('ADMIN_SECRET_KEY', 'ADMIN2026');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("Service temporarily unavailable. Please try again later.");
}

// Helper: Send an in-app notification to any user
function sendNotification($pdo, $user_id, $message, $link = 'notifications.php') {
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $message, $link]);
}
?>