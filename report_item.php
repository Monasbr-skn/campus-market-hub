<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit;
}

$product_id = (int)($_GET['product_id'] ?? 0);
$message    = '';

$stmt = $pdo->prepare("SELECT product_name FROM products WHERE product_id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    die("Product not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = trim($_POST['reason']);
    if ($reason !== '') {
        $ins = $pdo->prepare("INSERT INTO reports (product_id, reporter_id, reason) VALUES (?, ?, ?)");
        $ins->execute([$product_id, $_SESSION['user_id'], $reason]);
        $message = "Report submitted. An administrator will investigate this listing.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Report Listing - Campus Market Hub</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="navbar">
    <h1>Campus Market Hub</h1>
    <div>
        <a href="student_dashboard.php">Marketplace</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <h2>Report Suspicious Listing</h2>
    <p><strong>Item:</strong> <?= htmlspecialchars($product['product_name']) ?></p>

    <?php if ($message): ?>
        <p class="alert"><?= htmlspecialchars($message) ?></p>
        <a href="student_dashboard.php" class="btn">Return to Marketplace</a>
    <?php else: ?>
        <form method="POST">
            <label>Reason for Reporting (e.g., Fake item, misleading price, already sold, scam)</label>
            <textarea name="reason" rows="4" required></textarea>
            <button type="submit" style="background-color:#c1121f;">Submit Report</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>