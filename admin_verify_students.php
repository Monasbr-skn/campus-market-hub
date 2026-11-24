<?php
require_once 'config/db.php';

// Protect route: Admins only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Handle Approve or Reject actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $student_id = (int)$_GET['id'];
    if ($_GET['action'] === 'approve') {
        $stmt = $pdo->prepare("UPDATE users SET is_verified = 1 WHERE user_id = ? AND role = 'student'");
        $stmt->execute([$student_id]);
        
        // Trigger verification approval notification
        sendNotification($pdo, $student_id, "Your student account has been verified by the Admin! You can now buy and post items.", "student_dashboard.php");
    } elseif ($_GET['action'] === 'reject') {
        $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ? AND role = 'student'");
        $stmt->execute([$student_id]);
    }
    header("Location: admin_verify_students.php");
    exit;
}

// Fetch pending students
$stmt = $pdo->query("SELECT * FROM users WHERE role = 'student' AND is_verified = 0 ORDER BY created_at ASC");
$pending_students = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Students - Admin</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/auto_refresh.js" defer></script>
</head>
<body>
<div class="navbar">
    <h1>Campus Market Hub | Admin Panel</h1>
    <div>
        <a href="index.php">Home</a>
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="admin_verify_students.php">Verify Students (<?= count($pending_students) ?>)</a>
        <a href="admin_manage_products.php">Listings & Reports</a>
        <a href="notifications.php">Notifications</a>
        <a href="profile.php">Profile</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container wide">
    <h2>Pending Student Verifications</h2>

    <?php if (empty($pending_students)): ?>
        <p>No students awaiting verification.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Full Name</th>
                    <th>Reg No.</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Student ID Proof</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_students as $student): ?>
                <tr>
                    <td><?= htmlspecialchars($student['full_name']) ?></td>
                    <td><?= htmlspecialchars($student['reg_or_staff_no']) ?></td>
                    <td><?= htmlspecialchars($student['email']) ?></td>
                    <td><?= htmlspecialchars($student['phone_number']) ?></td>
                    <td>
                        <a href="<?= htmlspecialchars($student['student_id_card']) ?>" target="_blank">View ID Card</a>
                    </td>
                    <td>
                        <a href="admin_verify_students.php?action=approve&id=<?= $student['user_id'] ?>" class="btn-approve">Approve</a>
                        <a href="admin_verify_students.php?action=reject&id=<?= $student['user_id'] ?>" class="btn-reject" onclick="return confirm('Reject and delete this registration?')">Reject</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>