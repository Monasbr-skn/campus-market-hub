<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit;
}

$user_id   = (int)$_SESSION['user_id'];
$tx_id     = (int)($_GET['tx_id'] ?? 0);
$seller_id = (int)($_GET['seller_id'] ?? 0);
$alert     = '';
$error     = '';

// Mode A: Reviewing a completed transaction
$tx = null;
if ($tx_id > 0) {
    $stmt = $pdo->prepare("
        SELECT t.*, p.product_name, u.full_name AS seller_name 
        FROM transactions t
        JOIN products p ON t.product_id = p.product_id
        JOIN users u ON t.seller_id = u.user_id
        WHERE t.transaction_id = ? AND t.buyer_id = ? AND t.transaction_status = 'Completed'
    ");
    $stmt->execute([$tx_id, $user_id]);
    $tx = $stmt->fetch();

    if (!$tx) {
        die("Invalid or incomplete transaction.");
    }
    $seller_id = (int)$tx['seller_id'];
}

// Handle Review Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review']) && $tx) {
    $rating  = (int)$_POST['rating'];
    $comment = trim($_POST['comment']);

    if ($rating >= 1 && $rating <= 5) {
        try {
            $ins = $pdo->prepare("INSERT INTO reviews (transaction_id, product_id, reviewer_id, seller_id, rating, comment) VALUES (?, ?, ?, ?, ?, ?)");
            $ins->execute([$tx['transaction_id'], $tx['product_id'], $user_id, $tx['seller_id'], $rating, $comment]);

            // Notify the seller of their new rating
            sendNotification(
                $pdo,
                $tx['seller_id'],
                $_SESSION['full_name'] . " rated you " . $rating . "/5 stars on '" . $tx['product_name'] . "'",
                "reviews.php?seller_id=" . $tx['seller_id']
            );

            header("Location: transactions.php?msg=reviewed");
            exit;
        } catch (PDOException $e) {
            $error = "You have already reviewed this transaction.";
        }
    } else {
        $error = "Please select a valid rating between 1 and 5.";
    }
}

// Fetch Seller Details & All Reviews
$seller_info = null;
$seller_reviews = [];
$avg_rating = 0;

if ($seller_id > 0) {
    $s_stmt = $pdo->prepare("SELECT full_name, reg_or_staff_no FROM users WHERE user_id = ?");
    $s_stmt->execute([$seller_id]);
    $seller_info = $s_stmt->fetch();

    $r_stmt = $pdo->prepare("
        SELECT r.*, u.full_name AS reviewer_name, p.product_name 
        FROM reviews r
        JOIN users u ON r.reviewer_id = u.user_id
        JOIN products p ON r.product_id = p.product_id
        WHERE r.seller_id = ?
        ORDER BY r.created_at DESC
    ");
    $r_stmt->execute([$seller_id]);
    $seller_reviews = $r_stmt->fetchAll();

    if (!empty($seller_reviews)) {
        $sum = array_sum(array_column($seller_reviews, 'rating'));
        $avg_rating = round($sum / count($seller_reviews), 1);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Seller Reviews - Campus Market Hub</title>
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
    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

    <!-- Review Submission Form (Only shown when coming from a completed transaction) -->
    <?php if ($tx): ?>
        <div style="max-width:550px; margin-bottom:35px;">
            <h2>Rate Your Experience with <?= htmlspecialchars($tx['seller_name']) ?></h2>
            <p><strong>Item:</strong> <?= htmlspecialchars($tx['product_name']) ?></p>

            <form method="POST">
                <label>Trust & Condition Rating (1 = Poor, 5 = Excellent)</label>
                <select name="rating" required>
                    <option value="5">5 &#9733; - Excellent & Trustworthy</option>
                    <option value="4">4 &#9733; - Good</option>
                    <option value="3">3 &#9733; - Average</option>
                    <option value="2">2 &#9733; - Below Expectations</option>
                    <option value="1">1 &#9733; - Poor / Issue Encountered</option>
                </select>

                <label>Review Comment</label>
                <textarea name="comment" rows="3" placeholder="Was the item as described? Was the campus meetup smooth?" required></textarea>

                <button type="submit" name="submit_review">Submit Review</button>
            </form>
        </div>
        <hr style="margin:25px 0; border:0; border-top:1px solid #ddd;">
    <?php endif; ?>

    <!-- Seller Reputation History -->
    <?php if ($seller_info): ?>
        <h2>Seller Reputation: <?= htmlspecialchars($seller_info['full_name']) ?> (<?= htmlspecialchars($seller_info['reg_or_staff_no']) ?>)</h2>
        <p style="font-size:1.1rem; margin-bottom:20px;">
            <strong>Average Trust Score:</strong> 
            <?= count($seller_reviews) > 0 ? $avg_rating . ' / 5.0 &#9733; (' . count($seller_reviews) . ' reviews)' : 'No ratings yet' ?>
        </p>

        <?php if (empty($seller_reviews)): ?>
            <p>This seller has not received any reviews yet.</p>
        <?php else: ?>
            <?php foreach ($seller_reviews as $rev): ?>
                <div style="border:1px solid #e0e0e0; padding:15px; border-radius:6px; margin-bottom:12px; background:#fafafa;">
                    <div style="display:flex; justify-content:space-between;">
                        <strong><?= htmlspecialchars($rev['reviewer_name']) ?></strong>
                        <span style="color:#d4a373; font-weight:bold;"><?= str_repeat('&#9733;', $rev['rating']) . str_repeat('&#9734;', 5 - $rev['rating']) ?></span>
                    </div>
                    <small style="color:#666;">Purchased: <?= htmlspecialchars($rev['product_name']) ?> &bull; <?= $rev['created_at'] ?></small>
                    <p style="margin-top:8px;"><?= nl2br(htmlspecialchars($rev['comment'])) ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>