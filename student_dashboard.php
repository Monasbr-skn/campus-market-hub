<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit;
}

$user_id     = $_SESSION['user_id'];
$search      = trim($_GET['search'] ?? '');
$category_id = (int)($_GET['category'] ?? 0);
$alert       = (isset($_GET['msg']) && $_GET['msg'] === 'posted') ? 'Your item has been posted to the marketplace!' : '';

// Personal quick stats for top strip
$unread_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$unread_stmt->execute([$user_id]);
$unread_notifs = $unread_stmt->fetchColumn();

$my_active_stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE user_id = ? AND status = 'Available'");
$my_active_stmt->execute([$user_id]);
$my_active_count = $my_active_stmt->fetchColumn();

$pending_tx_stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE (buyer_id = ? OR seller_id = ?) AND transaction_status = 'Pending'");
$pending_tx_stmt->execute([$user_id, $user_id]);
$pending_tx_count = $pending_tx_stmt->fetchColumn();

// Categories with live available item counts
$categories = $pdo->query("
    SELECT c.category_id, c.category_name, COUNT(p.product_id) AS item_count
    FROM categories c
    LEFT JOIN products p ON c.category_id = p.category_id AND p.status = 'Available'
    GROUP BY c.category_id, c.category_name
    ORDER BY c.category_name ASC
")->fetchAll();

$cat_icons = [
    'Textbooks & Notes'      => '&#128218;',
    'Electronics'            => '&#128187;',
    'Furniture'              => '&#129681;',
    'Clothing & Accessories' => '&#128085;',
    'Hostel Essentials'      => '&#127968;',
    'Services'               => '&#128736;'
];

// Recommendation Engine
$pref_stmt = $pdo->prepare("
    SELECT category_id, COUNT(*) as score 
    FROM user_activity 
    WHERE user_id = ? 
    GROUP BY category_id 
    ORDER BY score DESC 
    LIMIT 2
");
$pref_stmt->execute([$user_id]);
$preferred_cats = $pref_stmt->fetchAll(PDO::FETCH_COLUMN);

if (!empty($preferred_cats)) {
    $in = str_repeat('?,', count($preferred_cats) - 1) . '?';
    $rec_sql = "SELECT p.*, c.category_name FROM products p 
                JOIN categories c ON p.category_id = c.category_id 
                WHERE p.status = 'Available' AND p.user_id != ? AND p.category_id IN ($in)
                ORDER BY p.views_count DESC, p.date_posted DESC LIMIT 4";
    $rec_stmt = $pdo->prepare($rec_sql);
    $rec_stmt->execute(array_merge([$user_id], $preferred_cats));
    $recommended = $rec_stmt->fetchAll();
} else {
    $rec_stmt = $pdo->prepare("
        SELECT p.*, c.category_name FROM products p 
        JOIN categories c ON p.category_id = c.category_id 
        WHERE p.status = 'Available' AND p.user_id != ?
        ORDER BY p.views_count DESC, p.date_posted DESC LIMIT 4
    ");
    $rec_stmt->execute([$user_id]);
    $recommended = $rec_stmt->fetchAll();
}

// Main Marketplace Query
$sql = "SELECT p.*, c.category_name, u.full_name AS seller_name 
        FROM products p 
        JOIN categories c ON p.category_id = c.category_id 
        JOIN users u ON p.user_id = u.user_id 
        WHERE p.status = 'Available'";
$params = [];

if ($search !== '') {
    $sql .= " AND (p.product_name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_id > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $category_id;
}

$sql .= " ORDER BY p.date_posted DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Campus Market Hub - Student Marketplace</title>
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
        <a href="notifications.php">Notifications (<?= $unread_notifs ?>)</a>
        <a href="profile.php">Profile</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container wide">
    <?php if ($alert): ?><p class="alert"><?= htmlspecialchars($alert) ?></p><?php endif; ?>

    <!-- Welcome & Personal Activity Strip -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:20px;">
        <div>
            <h2 style="margin-bottom:4px;">Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?></h2>
            <p style="font-size:0.9rem; color:#555;">Browse verified campus listings or list an item to sell, exchange, or donate.</p>
        </div>
        <a href="add_product.php" class="btn" style="margin-top:0;">+ Post New Item</a>
    </div>

    <div class="stats-grid" style="margin-bottom:25px;">
        <div class="stat-card">
            <h3><?= $my_active_count ?></h3>
            <p>Your Active Listings (<a href="my_listings.php">Manage</a>)</p>
        </div>
        <div class="stat-card">
            <h3><?= $pending_tx_count ?></h3>
            <p>Pending Handovers (<a href="transactions.php">Track</a>)</p>
        </div>
        <div class="stat-card">
            <h3><?= $unread_notifs ?></h3>
            <p>Unread Alerts (<a href="notifications.php">View</a>)</p>
        </div>
    </div>

    <!-- Search & Filter Form -->
    <form method="GET" class="search-bar">
        <input type="text" name="search" placeholder="Search textbooks, electronics, furniture..." value="<?= htmlspecialchars($search) ?>">
        <select name="category">
            <option value="0">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['category_id'] ?>" <?= $category_id == $cat['category_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['category_name']) ?> (<?= $cat['item_count'] ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Search</button>
        <?php if ($search !== '' || $category_id > 0): ?>
            <a href="student_dashboard.php" class="btn" style="margin-top:0; background-color:#6c757d;">Reset</a>
        <?php endif; ?>
    </form>

    <!-- Quick Category Explorer Grid -->
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap:12px; margin-bottom:30px;">
        <?php foreach ($categories as $cat): ?>
            <?php 
                $icon = $cat_icons[$cat['category_name']] ?? '&#128230;';
                $is_selected = ($category_id == $cat['category_id']);
            ?>
            <a href="student_dashboard.php?category=<?= $cat['category_id'] ?>" 
               style="text-decoration:none; color:inherit; border: <?= $is_selected ? '2px solid #1b4332' : '1px solid #ddd' ?>; border-radius:8px; padding:12px; text-align:center; background: <?= $is_selected ? '#d8f3dc' : '#f8f9fa' ?>;">
                <div style="font-size:1.4rem; margin-bottom:4px;"><?= $icon ?></div>
                <strong style="color:#1b4332; display:block; font-size:0.88rem;"><?= htmlspecialchars($cat['category_name']) ?></strong>
                <small style="color:#555;"><?= $cat['item_count'] ?> available</small>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Recommended Section -->
    <?php if (!empty($recommended) && $search === '' && $category_id === 0): ?>
        <h2>Recommended For You</h2>
        <div class="product-grid">
            <?php foreach ($recommended as $item): ?>
                <div class="product-card">
                    <img src="<?= htmlspecialchars($item['product_image']) ?>" alt="<?= htmlspecialchars($item['product_name']) ?>">
                    <div class="product-info">
                        <span class="badge"><?= htmlspecialchars($item['listing_type']) ?> &bull; <?= htmlspecialchars($item['category_name']) ?></span>
                        <h4><?= htmlspecialchars($item['product_name']) ?></h4>
                        <p class="price">
                            <?= $item['listing_type'] === 'Sell' ? 'KES ' . number_format($item['price'], 2) : htmlspecialchars($item['listing_type']) ?>
                        </p>
                        <a href="product_details.php?id=<?= $item['product_id'] ?>" class="btn">View Details</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- All Products Section -->
    <h2>Campus Listings</h2>
    <?php if (empty($products)): ?>
        <div style="text-align:center; padding:40px 20px; background:#fafafa; border:1px dashed #bbb; border-radius:8px;">
            <h3 style="margin-bottom:8px;">No Active Listings Found</h3>
            <p style="color:#666; max-width:500px; margin:0 auto 18px auto;">
                <?= ($search !== '' || $category_id > 0) 
                    ? 'No items matched your current filter. Try resetting your search or browse another category.' 
                    : 'The marketplace is ready! Post textbooks, electronics, furniture, or hostel essentials to start trading with verified students.' ?>
            </p>
            <?php if ($search !== '' || $category_id > 0): ?>
                <a href="student_dashboard.php" class="btn" style="margin-top:0; background-color:#6c757d;">Clear Filters</a>
            <?php endif; ?>
            <a href="add_product.php" class="btn" style="margin-top:0;">+ Post an Item</a>
        </div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($products as $item): ?>
                <div class="product-card">
                    <img src="<?= htmlspecialchars($item['product_image']) ?>" alt="<?= htmlspecialchars($item['product_name']) ?>">
                    <div class="product-info">
                        <span class="badge"><?= htmlspecialchars($item['listing_type']) ?> &bull; <?= htmlspecialchars($item['category_name']) ?></span>
                        <h4><?= htmlspecialchars($item['product_name']) ?></h4>
                        <p class="price">
                            <?= $item['listing_type'] === 'Sell' ? 'KES ' . number_format($item['price'], 2) : htmlspecialchars($item['listing_type']) ?>
                        </p>
                        <small>Seller: <?= htmlspecialchars($item['seller_name']) ?> (Verified)</small><br>
                        <a href="product_details.php?id=<?= $item['product_id'] ?>" class="btn">View & Connect</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>