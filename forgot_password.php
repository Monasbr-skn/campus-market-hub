<?php
require_once 'config/db.php';

$message = '';
$error   = '';
$reset_link_fallback = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email  = trim($_POST['email']);
    $reg_no = trim($_POST['reg_or_staff_no']);

    // Verify both email and registration/staff number match
    $stmt = $pdo->prepare("SELECT user_id, full_name, email FROM users WHERE email = ? AND reg_or_staff_no = ?");
    $stmt->execute([$email, $reg_no]);
    $user = $stmt->fetch();

    if ($user) {
        // Generate cryptographically secure 64-character token expiring in 30 minutes
        $token      = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));

        // Invalidate any previous unused tokens for this user
        $pdo->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ?")->execute([$user['user_id']]);

        $ins = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
        $ins->execute([$user['user_id'], $token, $expires_at]);

        $reset_url = BASE_URL . "/reset_password.php?token=" . $token;

        // Attempt sending email on live deployment
        $subject = "Password Reset - Campus Market Hub";
        $body    = "Hello " . $user['full_name'] . ",\n\nClick the link below to reset your Campus Market Hub password (valid for 30 minutes):\n" . $reset_url;
        $headers = "From: no-reply@" . ($_SERVER['HTTP_HOST'] ?? 'campusmarkethub.ac.ke');

        $mail_sent = false;
        if (!$is_local) {
            $mail_sent = @mail($user['email'], $subject, $body, $headers);
        }

        if ($mail_sent) {
            $message = "A password reset link has been sent to your email address. Please check your inbox (and spam folder).";
        } else {
            $message = "Identity verified! Use the secure link below within 30 minutes to set a new password:";
            $reset_link_fallback = $reset_url;
        }
    } else {
        $error = "No account found matching both that email and registration/staff number.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password - Campus Market Hub</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="navbar">
    <h1>Campus Market Hub</h1>
    <div>
        <a href="index.php">Home</a>
        <a href="login.php">Login</a>
        <a href="register.php">Student Register</a>
    </div>
</div>

<div class="container">
    <h2>Password Recovery</h2>
    <p style="font-size:0.9rem; color:#555; margin-bottom:15px;">
        Enter your registered university email and registration/staff number to verify your identity.
    </p>

    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <?php if ($message): ?><p class="alert"><?= htmlspecialchars($message) ?></p><?php endif; ?>

    <?php if ($reset_link_fallback): ?>
        <div style="background:#edf6f9; padding:12px; border-radius:6px; margin-bottom:15px; word-break:break-all;">
            <a href="<?= htmlspecialchars($reset_link_fallback) ?>" class="btn" style="margin-top:0; width:100%;">Proceed to Reset Password</a>
        </div>
    <?php else: ?>
        <form method="POST">
            <label>Registered Email Address</label>
            <input type="email" name="email" required>

            <label>Registration or Staff Number</label>
            <input type="text" name="reg_or_staff_no" placeholder="C027-00-0000/2000" required>

            <button type="submit">Verify & Reset Password</button>
        </form>
    <?php endif; ?>

    <p style="margin-top:15px;"><a href="login.php">&larr; Back to Login</a></p>
</div>
</body>
</html>