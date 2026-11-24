<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$alert   = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'requested') $alert = 'Transaction request sent to the seller!';
    if ($_GET['msg'] === 'reviewed')  $alert = 'Thank you! Your seller review has been published.';
}

// Handle Seller completing or cancelling a transaction
if (isset($_GET['action']) && isset($_GET['tx_id'])) {
    $tx_id  = (int)$_GET['tx_id'];
    $action = $_GET['action'];

    $tx_stmt = $pdo->prepare("
        SELECT t.*, p.listing_type, p.product_name 
        FROM transactions t 
        JOIN products p ON t.product_id = p.product_id 
        WHERE t.transaction_id = ? AND t.seller_id = ?
    ");
    $tx_stmt->execute([$tx_id, $user_id]);
    $tx = $tx_stmt->fetch();

    if ($tx && $tx['transaction_status'] === 'Pending') {
        if ($action === 'complete') {
            $pdo->prepare("UPDATE transactions SET transaction_status = 'Completed' WHERE transaction_id = ?")->execute([$tx_id]);

            $new_status = ($tx['listing_type'] === 'Exchange') ? 'Exchanged' : (($tx['listing_type'] === 'Donate') ? 'Donated' : 'Sold');
            $pdo->prepare("UPDATE products SET status = ? WHERE product_id = ?")->execute([$new_status, $tx['product_id']]);

            $pdo->prepare("UPDATE transactions SET transaction_status = 'Cancelled' WHERE product_id = ? AND transaction_id != ? AND transaction_status = 'Pending'")
                ->execute([$tx['product_id'], $tx_id]);

            sendNotification(
                $pdo,
                $tx['buyer_id'],
                "Your transaction for '" . $tx['product_name'] . "' was marked Completed! You can now rate the seller.",
                "transactions.php"
            );

            $alert = "Transaction marked as Completed! Item status updated to $new_status.";
        } elseif ($action === 'cancel') {
            $pdo->prepare("UPDATE transactions SET transaction_status = 'Cancelled' WHERE transaction_id = ?")->execute([$tx_id]);

            sendNotification(
                $pdo,
                $tx['buyer_id'],
                "Your request for '" . $tx['product_name'] . "' was cancelled by the seller.",
                "transactions.php"
            );

            $alert = "Transaction request cancelled.";
        }
    }
}

// Fetch Incoming Requests (User is Seller)
$incoming_stmt = $pdo->prepare("
    SELECT t.*, p.product_name, p.listing_type, p.price, u.full_name AS buyer_name, u.phone_number AS buyer_phone
    FROM transactions t
    JOIN products p ON t.product_id = p.product_id
    JOIN users u ON t.buyer_id = u.user_id
    WHERE t.seller_id = ?
    ORDER BY t.transaction_date DESC
");
$incoming_stmt->execute([$user_id]);
$incoming = $incoming_stmt->fetchAll();

// Fetch Outgoing Requests (User is Buyer) + check if already reviewed
$outgoing_stmt = $pdo->prepare("
    SELECT t.*, p.product_name, p.listing_type, p.price, u.full_name AS seller_name, u.phone_number AS seller_phone,
           r.review_id
    FROM transactions t
    JOIN products p ON t.product_id = p.product_id
    JOIN users u ON t.seller_id = u.user_id
    LEFT JOIN reviews r ON t.transaction_id = r.transaction_id
    WHERE t.buyer_id = ?
    ORDER BY t.transaction_date DESC
");
$outgoing_stmt->execute([$user_id]);
$outgoing = $outgoing_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transactions - Campus Market Hub</title>
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
    <h2>Transaction Tracking</h2>
    <?php if ($alert): ?><p class="alert"><?= htmlspecialchars($alert) ?></p><?php endif; ?>

    <!-- Incoming Requests -->
    <h3>Incoming Requests (Your Listings)</h3>
    <?php if (empty($incoming)): ?>
        <p>No buyers have requested your items yet.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Type</th>
                    <th>Buyer</th>
                    <th>Buyer Phone</th>
                    <th>Meetup Spot</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($incoming as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['product_name']) ?></td>
                    <td><?= htmlspecialchars($row['listing_type']) ?></td>
                    <td><?= htmlspecialchars($row['buyer_name']) ?></td>
                    <td><?= htmlspecialchars($row['buyer_phone']) ?></td>
                    <td><strong><?= htmlspecialchars($row['meetup_spot']) ?></strong></td>
                    <td><?= $row['transaction_date'] ?></td>
                    <td><strong><?= htmlspecialchars($row['transaction_status']) ?></strong></td>
                    <td>
                        <?php if ($row['transaction_status'] === 'Pending'): ?>
                            <a href="transactions.php?action=complete&tx_id=<?= $row['transaction_id'] ?>" class="btn-approve">Mark Completed</a>
                            <a href="transactions.php?action=cancel&tx_id=<?= $row['transaction_id'] ?>" class="btn-reject">Cancel</a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <br><br>

    <!-- Outgoing Requests -->
    <h3>Your Purchase / Exchange / Donation Requests</h3>
    <?php if (empty($outgoing)): ?>
        <p>You have not requested any items yet.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Type</th>
                    <th>Seller</th>
                    <th>Seller Phone</th>
                    <th>Meetup Spot</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Feedback</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($outgoing as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['product_name']) ?></td>
                    <td><?= htmlspecialchars($row['listing_type']) ?></td>
                    <td><?= htmlspecialchars($row['seller_name']) ?></td>
                    <td><?= htmlspecialchars($row['seller_phone']) ?></td>
                    <td><strong><?= htmlspecialchars($row['meetup_spot']) ?></strong></td>
                    <td><?= $row['transaction_date'] ?></td>
                    <td><strong><?= htmlspecialchars($row['transaction_status']) ?></strong></td>
                    <td>
                        <?php if ($row['transaction_status'] === 'Completed'): ?>
                            <?php if ($row['review_id']): ?>
                                <span style="color:#2d6a4f; font-weight:bold;">&#10003; Reviewed</span>
                            <?php else: ?>
                                <a href="reviews.php?tx_id=<?= $row['transaction_id'] ?>" class="btn-approve">Rate Seller</a>
                            <?php endif; ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>