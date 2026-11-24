<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$pending_count  = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND is_verified = 0")->fetchColumn();
$verified_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND is_verified = 1")->fetchColumn();
$product_count  = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'Available'")->fetchColumn();
$tx_count       = $pdo->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
$open_reports   = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'Open'")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Campus Market Hub</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/auto_refresh.js" defer></script>
</head>
<body>
<div class="navbar">
    <h1>Campus Market Hub | Admin Panel</h1>
    <div>
        <a href="index.php">Home</a>
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="admin_verify_students.php">Verify Students (<?= $pending_count ?>)</a>
        <a href="admin_manage_products.php">Listings & Reports (<?= $open_reports ?>)</a>
        <a href="profile.php">Profile</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container wide">
    <h2>Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?></h2>

    <div class="stats-grid">
        <div class="stat-card">
            <h3><?= $pending_count ?></h3>
            <p>Pending Student Verifications</p>
            <a href="admin_verify_students.php" class="btn">Review Now</a>
        </div>
        <div class="stat-card">
            <h3><?= $open_reports ?></h3>
            <p>Open Fraud Reports</p>
            <a href="admin_manage_products.php" class="btn">Moderate</a>
        </div>
        <div class="stat-card">
            <h3><?= $verified_count ?></h3>
            <p>Verified Students</p>
        </div>
        <div class="stat-card">
            <h3><?= $product_count ?></h3>
            <p>Active Marketplace Listings</p>
        </div>
        <div class="stat-card">
            <h3><?= $tx_count ?></h3>
            <p>Total Transactions</p>
        </div>
    </div>
</div>
</body>
</html>