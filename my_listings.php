<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle Delete Listing
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM products WHERE product_id = ? AND user_id = ?");
    $stmt->execute([$del_id, $user_id]);
    header("Location: my_listings.php?msg=deleted");
    exit;
}

$stmt = $pdo->prepare("
    SELECT p.*, c.category_name 
    FROM products p 
    JOIN categories c ON p.category_id = c.category_id 
    WHERE p.user_id = ? 
    ORDER BY p.date_posted DESC
");
$stmt->execute([$user_id]);
$my_items = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Listings - Campus Market Hub</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/auto_refresh.js" defer></script>
</head>
<body>
<div class="navbar">
    <h1>Campus Market Hub</h1>
    <div>
        <a href="index.php">Home</a>
        <a href="student_dashboard.php">Marketplace</a>
        <a href="add_product.php">+ Post Item</a>
        <a href="my_listings.php">My Listings</a>
        <a href="messages.php">Messages</a>
        <a href="transactions.php">Transactions</a>
        <a href="notifications.php">Notifications</a>
        <a href="profile.php">Profile</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container wide">
    <h2>My Listed Items</h2>
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
        <p class="alert">Listing removed successfully.</p>
    <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'updated'): ?>
        <p class="alert">Listing updated successfully.</p>
    <?php endif; ?>

    <?php if (empty($my_items)): ?>
        <p>You haven't posted any items yet. <a href="add_product.php">Post your first item</a>.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Type</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Views</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($my_items as $item): ?>
                <tr>
                    <td><img src="<?= htmlspecialchars($item['product_image']) ?>" width="55" height="55" style="object-fit:cover; border-radius:4px;"></td>
                    <td><?= htmlspecialchars($item['product_name']) ?></td>
                    <td><?= htmlspecialchars($item['category_name']) ?></td>
                    <td><?= htmlspecialchars($item['listing_type']) ?></td>
                    <td><?= $item['listing_type'] === 'Sell' ? 'KES ' . number_format($item['price'], 2) : '-' ?></td>
                    <td><strong><?= htmlspecialchars($item['status']) ?></strong></td>
                    <td><?= $item['views_count'] ?></td>
                    <td>
                        <?php if ($item['status'] === 'Available'): ?>
                            <a href="edit_product.php?id=<?= $item['product_id'] ?>" class="btn-approve">Edit</a>
                        <?php endif; ?>
                        <a href="my_listings.php?delete_id=<?= $item['product_id'] ?>" class="btn-reject" onclick="return confirm('Delete this listing permanently?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>