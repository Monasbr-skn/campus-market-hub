<?php
require_once 'config/db.php';
$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $reg_no    = trim($_POST['reg_or_staff_no']);
    $email     = trim($_POST['email']);
    $phone     = trim($_POST['phone_number']);
    $password  = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $target_dir = "uploads/ids/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $clean_name  = preg_replace("/[^a-zA-Z0-9._-]/", "_", basename($_FILES["student_id_card"]["name"]));
    $target_file = $target_dir . time() . "_" . $clean_name;

    if (move_uploaded_file($_FILES["student_id_card"]["tmp_name"], $target_file)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (full_name, reg_or_staff_no, email, phone_number, password, role, student_id_card, is_verified) VALUES (?, ?, ?, ?, ?, 'student', ?, 0)");
            $stmt->execute([$full_name, $reg_no, $email, $phone, $password, $target_file]);
            $message = "Registration successful! Your account is pending Admin verification.";
        } catch (PDOException $e) {
            $error = "Error: Registration number or email already exists.";
        }
    } else {
        $error = "Failed to upload Student ID image.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - Campus Market Hub</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/auto_refresh.js" defer></script>
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
    <h2>Student Registration</h2>
    <?php if ($message): ?><p class="alert"><?= htmlspecialchars($message) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    
    <form method="POST" enctype="multipart/form-data">
        <label>Full Name</label>
        <input type="text" name="full_name" required>

        <label>Registration Number</label>
        <input type="text" name="reg_or_staff_no" placeholder="C027-00-0000/2000" required>

        <label>University Email</label>
        <input type="email" name="email" required>

        <label>Phone Number</label>
        <input type="text" name="phone_number" required>

        <label>Password</label>
        <input type="password" name="password" required>

        <label>Upload Student ID Card (For Admin Verification)</label>
        <input type="file" name="student_id_card" accept="image/*" required>

        <button type="submit">Register</button>
    </form>
    <p style="margin-top:15px;">Already have an account? <a href="login.php">Login here</a></p>
</div>
</body>
</html>