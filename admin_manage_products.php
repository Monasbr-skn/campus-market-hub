<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Handle removing a flagged product
if (isset($_GET['remove_product'])) {
    $pid = (int)$_GET['remove_product'];
    $pdo->prepare("DELETE FROM products WHERE product_id = ?")->execute([$pid]);
    header("Location: admin_manage_products.php?msg=removed");
    exit;
}

// Handle dismissing/resolving a report without deleting the item
if (isset($_GET['resolve_report'])) {
    $rid = (int)$_GET['resolve_report'];
    $pdo->prepare("UPDATE reports SET status = 'Resolved' WHERE report_id = ?")->execute([$rid]);
    header("Location: admin_manage_products.php?msg=resolved");
    exit;
}

// Fetch open fraud reports
$reports = $pdo->query("
    SELECT r.*, p.product_name, p.product_image, u_rep.full_name AS reporter_name, u_sel.full_name AS seller_name, u_sel.reg_or_staff_no AS seller_reg
    FROM reports r
    JOIN products p ON r.product_id = p.product_id
    JOIN users u_rep ON r.reporter_id = u_rep.user_id
    JOIN users u_sel ON p.user_id = u_sel.user_id
    WHERE r.status = 'Open'
    ORDER BY r.created_at DESC
")->fetchAll();

// Fetch all active listings for general monitoring
$all_products = $pdo->query("
    SELECT p.*, c.category_name, u.full_name AS seller_name 
    FROM products p
    JOIN categories c ON p.category_id = c.category_id
    JOIN users u ON p.user_id = u.user_id
    ORDER BY p.date_posted DESC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Moderate Products & Reports - Admin</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="navbar">
    <h1>Campus Market Hub | Admin Panel</h1>
    <div>
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="admin_verify_students.php">Verify Students</a>
        <a href="admin_manage_products.php">Listings & Reports (<?= count($reports) ?>)</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container wide">
    <h2>Fraud & Suspicious Listing Reports</h2>
    <?php if (isset($_GET['msg'])): ?>
        <p class="alert">Action completed successfully.</p>
    <?php endif; ?>

    <?php if (empty($reports)): ?>
        <p>No open fraud reports at the moment.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Reported Item</th>
                    <th>Seller (Reg No.)</th>
                    <th>Reported By</th>
                    <th>Reason</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $rep): ?>
                <tr>
                    <td><?= htmlspecialchars($rep['product_name']) ?></td>
                    <td><?= htmlspecialchars($rep['seller_name']) ?> (<?= htmlspecialchars($rep['seller_reg']) ?>)</td>
                    <td><?= htmlspecialchars($rep['reporter_name']) ?></td>
                    <td><?= htmlspecialchars($rep['reason']) ?></td>
                    <td>
                        <a href="admin_manage_products.php?remove_product=<?= $rep['product_id'] ?>" class="btn-reject" onclick="return confirm('Delete this fraudulent listing?')">Remove Listing</a>
                        <a href="admin_manage_products.php?resolve_report=<?= $rep['report_id'] ?>" class="btn-approve">Dismiss Report</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <br><br>
    <h2>All Platform Listings</h2>
    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>Category</th>
                <th>Seller</th>
                <th>Type</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($all_products as $p): ?>
            <tr>
                <td><?= htmlspecialchars($p['product_name']) ?></td>
                <td><?= htmlspecialchars($p['category_name']) ?></td>
                <td><?= htmlspecialchars($p['seller_name']) ?></td>
                <td><?= htmlspecialchars($p['listing_type']) ?></td>
                <td><?= htmlspecialchars($p['status']) ?></td>
                <td>
                    <a href="admin_manage_products.php?remove_product=<?= $p['product_id'] ?>" class="btn-reject" onclick="return confirm('Remove this item?')">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>