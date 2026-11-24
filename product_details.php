<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit;
}

$user_id    = $_SESSION['user_id'];
$product_id = (int)($_GET['id'] ?? 0);
$alert      = '';

// Fetch product + seller info
$stmt = $pdo->prepare("
    SELECT p.*, c.category_name, u.full_name AS seller_name, u.reg_or_staff_no, u.phone_number 
    FROM products p
    JOIN categories c ON p.category_id = c.category_id
    JOIN users u ON p.user_id = u.user_id
    WHERE p.product_id = ?
");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    die("Product not found.");
}

// Fetch seller's average rating & review count
$rev_stmt = $pdo->prepare("SELECT COUNT(*) as total_reviews, AVG(rating) as avg_rating FROM reviews WHERE seller_id = ?");
$rev_stmt->execute([$product['user_id']]);
$rev_stats = $rev_stmt->fetch();

// Log view activity for Recommendation Engine (only if viewer is not the seller)
if ($product['user_id'] != $user_id) {
    $pdo->prepare("UPDATE products SET views_count = views_count + 1 WHERE product_id = ?")->execute([$product_id]);
    $pdo->prepare("INSERT INTO user_activity (user_id, product_id, category_id, action_type) VALUES (?, ?, ?, 'view')")
        ->execute([$user_id, $product_id, $product['category_id']]);
}

// Handle "Request Transaction" with Meetup Spot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_transaction'])) {
    $dropdown_spot = trim($_POST['meetup_dropdown'] ?? '');
    $custom_spot   = trim($_POST['meetup_custom'] ?? '');
    $meetup_spot   = ($custom_spot !== '') ? $custom_spot : $dropdown_spot;

    if ($product['user_id'] == $user_id) {
        $alert = "You cannot request your own listing.";
    } elseif ($product['status'] !== 'Available') {
        $alert = "This item is no longer available.";
    } elseif ($meetup_spot === '') {
        $alert = "Please select or enter a campus meetup spot.";
    } else {
        $check = $pdo->prepare("SELECT * FROM transactions WHERE product_id = ? AND buyer_id = ? AND transaction_status = 'Pending'");
        $check->execute([$product_id, $user_id]);

        if ($check->fetch()) {
            $alert = "You already have a pending request for this item. Check your Transactions page.";
        } else {
            $tx = $pdo->prepare("INSERT INTO transactions (product_id, buyer_id, seller_id, meetup_spot, transaction_status) VALUES (?, ?, ?, ?, 'Pending')");
            $tx->execute([$product_id, $user_id, $product['user_id'], $meetup_spot]);

            // Notify the seller including the proposed meetup spot
            sendNotification(
                $pdo,
                $product['user_id'],
                $_SESSION['full_name'] . " requested '" . $product['product_name'] . "' (Meetup: " . $meetup_spot . ")",
                "transactions.php"
            );

            $pdo->prepare("INSERT INTO user_activity (user_id, product_id, category_id, action_type) VALUES (?, ?, ?, 'purchase')")
                ->execute([$user_id, $product_id, $product['category_id']]);

            header("Location: transactions.php?msg=requested");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($product['product_name']) ?> - Campus Market Hub</title>
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
    <p><a href="student_dashboard.php">&larr; Back to Marketplace</a></p><br>
    <?php if ($alert): ?><p class="error"><?= htmlspecialchars($alert) ?></p><?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; align-items: start;">
        <div>
            <img src="<?= htmlspecialchars($product['product_image']) ?>" alt="Product" style="width:100%; border-radius:8px; max-height:400px; object-fit:cover;">
        </div>
        <div>
            <span class="badge"><?= htmlspecialchars($product['listing_type']) ?> &bull; <?= htmlspecialchars($product['category_name']) ?></span>
            <h2><?= htmlspecialchars($product['product_name']) ?></h2>
            <p class="price">
                <?= $product['listing_type'] === 'Sell' ? 'KES ' . number_format($product['price'], 2) : htmlspecialchars($product['listing_type']) ?>
            </p>
            <p><strong>Status:</strong> <?= htmlspecialchars($product['status']) ?></p>
            <p style="margin: 15px 0;"><?= nl2br(htmlspecialchars($product['description'])) ?></p>

            <hr style="margin: 20px 0; border: 0; border-top: 1px solid #ddd;">
            <h4>Verified Seller Information</h4>
            <p><strong>Name:</strong> <?= htmlspecialchars($product['seller_name']) ?> (<?= htmlspecialchars($product['reg_or_staff_no']) ?>)</p>
            <p><strong>Phone:</strong> <?= htmlspecialchars($product['phone_number']) ?></p>
            <p>
                <strong>Seller Trust Rating:</strong> 
                <?php if ($rev_stats['total_reviews'] > 0): ?>
                    <?= round($rev_stats['avg_rating'], 1) ?> / 5.0 &#9733; 
                    (<a href="reviews.php?seller_id=<?= $product['user_id'] ?>">Read <?= $rev_stats['total_reviews'] ?> reviews</a>)
                <?php else: ?>
                    New Seller (No ratings yet)
                <?php endif; ?>
            </p>

            <?php if ($product['user_id'] != $user_id && $product['status'] === 'Available'): ?>
                <hr style="margin: 20px 0; border: 0; border-top: 1px solid #ddd;">

                <form method="POST" style="background:#f8f9fa; padding:15px; border-radius:6px; border:1px solid #e0e0e0;">
                    <h4 style="margin-bottom:8px; color:#1b4332;">Arrange Campus Handover</h4>

                    <label>Select a Campus Meetup Spot</label>
                    <select name="meetup_dropdown">
                        <option value="Resource Center">Resource Center</option>
                        <option value="School of Engineering">School of Engineering</option>
                        <option value="School of Business">School of Business</option>
                        <option value="Freedom Hall">Freedom Hall</option>
                        <option value="Heroes Garden">Heroes Garden</option>
                        <option value="Mess">Mess</option>
                        <option value="Gazebos (outside the Mess)">Gazebos (outside the Mess)</option>
                        <option value="Student's Center">Student's Center</option>
                        <option value="Basketball Court">Basketball Court</option>
                        <option value="Football field">Football field</option>
                    </select>

                    <label>Or Type a Custom Meetup Location (Optional)</label>
                    <input type="text" name="meetup_custom" placeholder="e.g., Main Gate, Hostel B Entrance...">

                    <div style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
                        <button type="submit" name="request_transaction" style="margin-top:0; background-color:#1b4332;">
                            <?= $product['listing_type'] === 'Sell' ? 'Request to Buy' : ($product['listing_type'] === 'Exchange' ? 'Request Exchange' : 'Claim Donation') ?>
                        </button>
                        <a href="messages.php?product_id=<?= $product['product_id'] ?>&receiver_id=<?= $product['user_id'] ?>" class="btn" style="margin-top:0;">Message Seller</a>
                    </div>
                </form>

                <p style="margin-top:15px;">
                    <a href="report_item.php?product_id=<?= $product['product_id'] ?>" style="color:#c1121f; font-size:0.9rem;">&#9873; Report this listing as suspicious</a>
                </p>
            <?php elseif ($product['user_id'] == $user_id): ?>
                <p style="margin-top: 15px; color: #555;"><em>This is your listing.</em></p>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>