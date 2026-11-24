<?php
require_once 'config/db.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role      = $_POST['role'];
    $email     = trim($_POST['email']);
    $password  = $_POST['password'];
    $admin_key = $_POST['admin_key'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = ?");
    $stmt->execute([$email, $role]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        if ($role === 'admin') {
            if ($admin_key !== ADMIN_SECRET_KEY) {
                $error = "Invalid Admin Special Key. Access Denied.";
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id']   = $user['user_id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role']      = 'admin';
                header("Location: admin_dashboard.php");
                exit;
            }
        } else {
            if ($user['is_verified'] == 0) {
                $error = "Your account is still awaiting Admin verification. Please check back later.";
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id']   = $user['user_id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role']      = 'student';
                header("Location: student_dashboard.php");
                exit;
            }
        }
    } else {
        $error = "Invalid email, password, or selected role.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Campus Market Hub</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/auto_refresh.js" defer></script>
    <script>
        function toggleAdminKey() {
            const role = document.getElementById('role').value;
            const keyGroup = document.getElementById('admin-key-group');
            const keyInput = document.getElementById('admin_key');
            if (role === 'admin') {
                keyGroup.style.display = 'block';
                keyInput.required = true;
            } else {
                keyGroup.style.display = 'none';
                keyInput.required = false;
            }
        }
    </script>
</head>
<body>
<div class="navbar">
    <h1>Campus Market Hub</h1>
    <div>
        <a href="index.php">Home</a>
        <a href="register.php">Student Register</a>
    </div>
</div>

<div class="container">
    <h2>Campus Market Hub Login</h2>
    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

    <form method="POST">
        <label>Login As:</label>
        <select name="role" id="role" onchange="toggleAdminKey()" required>
            <option value="student">Student</option>
            <option value="admin">Administrator</option>
        </select>

        <label>Email Address</label>
        <input type="email" name="email" required>

        <label>Password</label>
        <input type="password" name="password" required>

        <div id="admin-key-group" style="display:none;">
            <label>Admin Special Security Key</label>
            <input type="password" name="admin_key" id="admin_key" placeholder="Enter Admin Secret Key">
        </div>

        <button type="submit">Login</button>
    </form>

    <p style="margin-top:15px;">
        <a href="forgot_password.php">Forgot your password?</a>
    </p>
    <p style="margin-top:8px;">New student? <a href="register.php">Create an account</a></p>
</div>
</body>
</html>