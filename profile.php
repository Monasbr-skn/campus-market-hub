<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$role    = $_SESSION['role'];
$alert   = '';
$error   = '';

// Handle profile details update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $phone     = trim($_POST['phone_number']);

    if ($full_name !== '' && $phone !== '') {
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone_number = ? WHERE user_id = ?");
        $stmt->execute([$full_name, $phone, $user_id]);
        $_SESSION['full_name'] = $full_name;
        $alert = "Profile updated successfully.";
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_pw = $_POST['current_password'];
    $new_pw     = $_POST['new_password'];

    $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user_row = $stmt->fetch();

    if ($user_row && password_verify($current_pw, $user_row['password'])) {
        $hashed = password_hash($new_pw, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?")->execute([$hashed, $user_id]);
        $alert = "Password changed successfully.";
    } else {
        $error = "Current password is incorrect.";
    }
}

// Fetch latest user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// If student, fetch their seller rating summary
$rev_stats = ['total_reviews' => 0, 'avg_rating' => 0];
if ($role === 'student') {
    $rev_stmt = $pdo->prepare("SELECT COUNT(*) as total_reviews, AVG(rating) as avg_rating FROM reviews WHERE seller_id = ?");
    $rev_stmt->execute([$user_id]);
    $rev_stats = $rev_stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile - Campus Market Hub</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/auto_refresh.js" defer></script>
</head>
<body>
<div class="navbar">
    <h1>Campus Market Hub <?= $role === 'admin' ? '| Admin Panel' : '' ?></h1>
    <div>
        <?php if ($role === 'admin'): ?>
            <a href="index.php">Home</a>
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

<div class="container">
    <h2>My Profile</h2>
    <?php if ($alert): ?><p class="alert"><?= htmlspecialchars($alert) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

    <div style="background:#edf6f9; padding:15px; border-radius:6px; margin-bottom:20px;">
        <p><strong>Role:</strong> <?= ucfirst(htmlspecialchars($user['role'])) ?></p>
        <p><strong><?= $role === 'admin' ? 'Staff Number:' : 'Registration Number:' ?></strong> <?= htmlspecialchars($user['reg_or_staff_no']) ?></p>
        <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
        <p><strong>Account Status:</strong> <?= $user['is_verified'] ? 'Verified' : 'Pending Verification' ?></p>
        <?php if ($role === 'student'): ?>
            <p style="margin-top:8px;">
                <strong>My Seller Rating:</strong>
                <?php if ($rev_stats['total_reviews'] > 0): ?>
                    <?= round($rev_stats['avg_rating'], 1) ?> / 5.0 &#9733; 
                    (<a href="reviews.php?seller_id=<?= $user_id ?>">View my <?= $rev_stats['total_reviews'] ?> reviews</a>)
                <?php else: ?>
                    No ratings yet (<a href="reviews.php?seller_id=<?= $user_id ?>">View page</a>)
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>

    <form method="POST">
        <h3>Edit Contact Details</h3>
        <label>Full Name</label>
        <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>

        <label>Phone Number</label>
        <input type="text" name="phone_number" value="<?= htmlspecialchars($user['phone_number']) ?>" required>

        <button type="submit" name="update_profile">Save Changes</button>
    </form>

    <hr style="margin: 25px 0; border: 0; border-top: 1px solid #ddd;">

    <form method="POST">
        <h3>Change Password</h3>
        <label>Current Password</label>
        <input type="password" name="current_password" required>

        <label>New Password</label>
        <input type="password" name="new_password" required>

        <button type="submit" name="change_password" style="background-color:#1b4332;">Update Password</button>
    </form>
</div>
</body>
</html>