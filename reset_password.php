<?php
require_once 'config/db.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';
$success = '';

if ($token === '') {
    die("Invalid password reset request.");
}

// Validate token exists, is unused, and has not expired
$stmt = $pdo->prepare("
    SELECT pr.*, u.email 
    FROM password_resets pr
    JOIN users u ON pr.user_id = u.user_id
    WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()
");
$stmt->execute([$token]);
$reset_row = $stmt->fetch();

if (!$reset_row) {
    $error = "This password reset link is invalid or has expired. Please request a new one.";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password     = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);

        // Update password and mark reset token as used
        $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?")->execute([$hashed, $reset_row['user_id']]);
        $pdo->prepare("UPDATE password_resets SET used = 1 WHERE reset_id = ?")->execute([$reset_row['reset_id']]);

        $success = "Your password has been reset successfully! You can now log in.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Set New Password - Campus Market Hub</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="navbar">
    <h1>Campus Market Hub</h1>
    <div>
        <a href="index.php">Home</a>
        <a href="login.php">Login</a>
    </div>
</div>

<div class="container">
    <h2>Create New Password</h2>

    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
        <p><a href="forgot_password.php">Request a new reset link</a></p>
    <?php elseif ($success): ?>
        <p class="alert"><?= htmlspecialchars($success) ?></p>
        <a href="login.php" class="btn">Go to Login</a>
    <?php else: ?>
        <p style="font-size:0.9rem; color:#555; margin-bottom:12px;">
            Resetting password for: <strong><?= htmlspecialchars($reset_row['email']) ?></strong>
        </p>
        <form method="POST">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

            <label>New Password (Min 6 characters)</label>
            <input type="password" name="new_password" minlength="6" required>

            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" minlength="6" required>

            <button type="submit">Update Password</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>