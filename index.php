<?php
require_once 'config/db.php';

// Fetch live platform trust stats
$verified_students = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND is_verified = 1")->fetchColumn();
$completed_deals   = $pdo->query("SELECT COUNT(*) FROM transactions WHERE transaction_status = 'Completed'")->fetchColumn();
$active_listings   = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'Available'")->fetchColumn();

// Fetch categories with live count of available items
$categories = $pdo->query("
    SELECT c.category_id, c.category_name, COUNT(p.product_id) AS item_count
    FROM categories c
    LEFT JOIN products p ON c.category_id = p.category_id AND p.status = 'Available'
    GROUP BY c.category_id, c.category_name
    ORDER BY c.category_name ASC
")->fetchAll();

// Fetch 4 latest available items for public preview
$latest = $pdo->query("
    SELECT p.*, c.category_name 
    FROM products p 
    JOIN categories c ON p.category_id = c.category_id 
    WHERE p.status = 'Available' 
    ORDER BY p.date_posted DESC LIMIT 4
")->fetchAll();

// Icon mapping for categories
$cat_icons = [
    'Textbooks & Notes'      => '&#128218;',
    'Electronics'            => '&#128187;',
    'Furniture'              => '&#129681;',
    'Clothing & Accessories' => '&#128085;',
    'Hostel Essentials'      => '&#127968;',
    'Services'               => '&#128736;'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Campus Market Hub - Trusted University Marketplace</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/auto_refresh.js" defer></script>
</head>
<body>
<div class="navbar">
    <h1>Campus Market Hub</h1>
    <div>
        <a href="index.php">Home</a>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="<?= $_SESSION['role'] === 'admin' ? 'admin_dashboard.php' : 'student_dashboard.php' ?>">Dashboard</a>
            <a href="profile.php">Profile</a>
            <a href="logout.php">Logout</a>
        <?php else: ?>
            <a href="login.php">Login</a>
            <a href="register.php">Student Register</a>
        <?php endif; ?>
    </div>
</div>

<!-- Hero Section -->
<div class="container wide" style="text-align:center; padding: 45px 30px;">
    <span class="badge" style="font-size:0.85rem; padding:5px 12px;">100% Admin-Verified Campus Community</span>
    <h1 style="color:#1b4332; font-size:2.2rem; margin:12px 0 15px 0;">Safe, Verified Campus Buying, Selling & Exchange</h1>
    <p style="max-width:660px; margin:0 auto 25px auto; color:#555; font-size:1.05rem;">
        Exclusively for verified university students. Buy affordable textbooks, electronics, and hostel essentials, trade items, or donate to fellow students without the risk of social media scams.
    </p>
    <?php if (isset($_SESSION['user_id'])): ?>
        <a href="<?= $_SESSION['role'] === 'admin' ? 'admin_dashboard.php' : 'student_dashboard.php' ?>" class="btn">Go to My Dashboard</a>
    <?php else: ?>
        <a href="register.php" class="btn">Get Started (Student Registration)</a>
        <a href="login.php" class="btn" style="background-color:#1b4332; margin-left:10px;">Login to Account</a>
    <?php endif; ?>

    <!-- Live Community Trust Counters -->
    <div class="stats-grid" style="margin-top:35px; text-align:left;">
        <div class="stat-card">
            <h3><?= $verified_students ?></h3>
            <p>Verified Campus Students</p>
        </div>
        <div class="stat-card">
            <h3><?= $active_listings ?></h3>
            <p>Active Listings Available</p>
        </div>
        <div class="stat-card">
            <h3><?= $completed_deals ?></h3>
            <p>Completed Safe Handovers</p>
        </div>
        <div class="stat-card">
            <h3>10+</h3>
            <p>Designated Campus Meetup Zones</p>
        </div>
    </div>
</div>

<!-- How It Works Section -->
<div class="container wide">
    <h2>How Campus Market Hub Works</h2>
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:20px;">
        <div style="padding:20px; border:1px solid #e0e0e0; border-radius:8px; background:#fafafa;">
            <h3 style="font-size:1.1rem; margin-bottom:8px;">1. Student ID Verification</h3>
            <p style="font-size:0.92rem; color:#555;">Every student registers with their university registration number and ID card, verified by an Administrator before trading.</p>
        </div>
        <div style="padding:20px; border:1px solid #e0e0e0; border-radius:8px; background:#fafafa;">
            <h3 style="font-size:1.1rem; margin-bottom:8px;">2. Direct Chat & Meetup Spot</h3>
            <p style="font-size:0.92rem; color:#555;">Message sellers directly and select safe, well-lit campus handover points like the Resource Center, Freedom Hall, or Student's Center.</p>
        </div>
        <div style="padding:20px; border:1px solid #e0e0e0; border-radius:8px; background:#fafafa;">
            <h3 style="font-size:1.1rem; margin-bottom:8px;">3. Handover & Trust Ratings</h3>
            <p style="font-size:0.92rem; color:#555;">Inspect the item in person before paying or exchanging, then rate the seller to build a trusted campus reputation.</p>
        </div>
    </div>
</div>

<!-- Browse Categories Grid (Always Populated) -->
<div class="container wide">
    <h2>Explore Campus Categories</h2>
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:15px;">
        <?php foreach ($categories as $cat): ?>
            <?php $icon = $cat_icons[$cat['category_name']] ?? '&#128230;'; ?>
            <a href="<?= isset($_SESSION['user_id']) && $_SESSION['role'] === 'student' ? 'student_dashboard.php?category=' . $cat['category_id'] : 'login.php' ?>" 
               style="text-decoration:none; color:inherit; border:1px solid #ddd; border-radius:8px; padding:18px 12px; text-align:center; background:#edf6f9; display:block;">
                <div style="font-size:1.8rem; margin-bottom:6px;"><?= $icon ?></div>
                <strong style="color:#1b4332; display:block; font-size:0.95rem;"><?= htmlspecialchars($cat['category_name']) ?></strong>
                <small style="color:#555;"><?= $cat['item_count'] ?> item(s)</small>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Recent Listings (With Clean Fallback if Empty) -->
<div class="container wide">
    <h2>Recent Campus Listings</h2>
    <?php if (empty($latest)): ?>
        <div style="text-align:center; padding:30px 20px; background:#fafafa; border:1px dashed #ccc; border-radius:8px;">
            <p style="font-size:1.05rem; color:#444; margin-bottom:10px;">No items are currently listed on the hub.</p>
            <p style="font-size:0.9rem; color:#666; margin-bottom:15px;">Have textbooks, electronics, or hostel items you no longer need? Be the first to post!</p>
            <a href="<?= isset($_SESSION['user_id']) ? 'add_product.php' : 'register.php' ?>" class="btn" style="margin-top:0;">Post an Item Now</a>
        </div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($latest as $item): ?>
                <div class="product-card">
                    <img src="<?= htmlspecialchars($item['product_image']) ?>" alt="<?= htmlspecialchars($item['product_name']) ?>">
                    <div class="product-info">
                        <span class="badge"><?= htmlspecialchars($item['listing_type']) ?> &bull; <?= htmlspecialchars($item['category_name']) ?></span>
                        <h4><?= htmlspecialchars($item['product_name']) ?></h4>
                        <p class="price">
                            <?= $item['listing_type'] === 'Sell' ? 'KES ' . number_format($item['price'], 2) : htmlspecialchars($item['listing_type']) ?>
                        </p>
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'student'): ?>
                            <a href="product_details.php?id=<?= $item['product_id'] ?>" class="btn">View Details</a>
                        <?php else: ?>
                            <a href="login.php" class="btn">Login to Connect</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>