<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$role    = $_SESSION['role'];

// Mark a single notification as read & redirect to its target link
if (isset($_GET['read_id'])) {
    $nid = (int)$_GET['read_id'];
    $stmt = $pdo->prepare("SELECT link FROM notifications WHERE notification_id = ? AND user_id = ?");
    $stmt->execute([$nid, $user_id]);
    $notif = $stmt->fetch();

    if ($notif) {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ?")->execute([$nid]);
        header("Location: " . $notif['link']);
        exit;
    }
}

// Mark all as read
if (isset($_GET['mark_all'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$user_id]);
    header("Location: notifications.php");
    exit;
}

// Fetch all notifications for this user
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notifications - Campus Market Hub</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/auto_refresh.js" defer></script>
</head>
<body>
<div class="navbar">
    <h1>Campus Market Hub <?= $role === 'admin' ? '| Admin Panel' : '' ?></h1>
    <div>
        <?php if ($role === 'admin'): ?>
            <a href="admin_dashboard.php">Dashboard</a>
            <a href="admin_verify_students.php">Verify Students</a>
            <a href="admin_manage_products.php">Listings & Reports</a>
            <a href="notifications.php">Notifications</a>
            <a href="profile.php">Profile</a>
            <a href="logout.php">Logout</a>
        <?php else: ?>
            <a href="index.php">Home</a>
            <a href="student_dashboard.php">Marketplace</a>
            <a href="add_product.php">+ Post Item</a>
            <a href="my_listings.php">My Listings</a>
            <a href="messages.php">Messages</a>
            <a href="transactions.php">Transactions</a>
            <a href="notifications.php">Notifications</a>
            <a href="profile.php">Profile</a>
            <a href="logout.php">Logout</a>
        <?php endif; ?>
    </div>
</div>

<div class="container wide">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2 style="margin-bottom:0;">Notifications</h2>
        <?php if (!empty($notifications)): ?>
            <a href="notifications.php?mark_all=1" class="btn" style="margin-top:0;">Mark All as Read</a>
        <?php endif; ?>
    </div>

    <?php if (empty($notifications)): ?>
        <p>You have no notifications yet.</p>
    <?php else: ?>
        <?php foreach ($notifications as $n): ?>
            <div style="padding:14px 18px; border-radius:6px; margin-bottom:10px; border:1px solid #ddd; display:flex; justify-content:space-between; align-items:center; background: <?= $n['is_read'] ? '#fff' : '#d8f3dc' ?>;">
                <div>
                    <p style="font-weight: <?= $n['is_read'] ? 'normal' : 'bold' ?>; color:#1b4332;">
                        <?= htmlspecialchars($n['message']) ?>
                    </p>
                    <small style="color:#666;"><?= $n['created_at'] ?></small>
                </div>
                <a href="notifications.php?read_id=<?= $n['notification_id'] ?>" class="btn" style="margin-top:0; padding:6px 12px; font-size:0.85rem;">
                    View
                </a>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>