<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit;
}

$user_id     = (int)$_SESSION['user_id'];
$product_id  = (int)($_GET['product_id'] ?? 0);
$receiver_id = (int)($_GET['receiver_id'] ?? 0);

// Handle sending a message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $msg_text = trim($_POST['message_text']);
    $p_id     = (int)$_POST['product_id'];
    $r_id     = (int)$_POST['receiver_id'];

    if ($msg_text !== '' && $p_id > 0 && $r_id > 0 && $r_id !== $user_id) {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, product_id, message_text) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $r_id, $p_id, $msg_text]);

        // Notify the recipient
        sendNotification(
            $pdo,
            $r_id,
            "New message from " . $_SESSION['full_name'],
            "messages.php?product_id=$p_id&receiver_id=$user_id"
        );

        header("Location: messages.php?product_id=$p_id&receiver_id=$r_id");
        exit;
    }
}

// Fetch all messages involving this user and group into unique threads in PHP
$all_msgs_stmt = $pdo->prepare("
    SELECT m.*, p.product_name, 
           s.full_name AS sender_name, 
           r.full_name AS receiver_name
    FROM messages m
    JOIN products p ON m.product_id = p.product_id
    JOIN users s ON m.sender_id = s.user_id
    JOIN users r ON m.receiver_id = r.user_id
    WHERE m.sender_id = ? OR m.receiver_id = ?
    ORDER BY m.date_sent DESC
");
$all_msgs_stmt->execute([$user_id, $user_id]);
$all_user_msgs = $all_msgs_stmt->fetchAll();

$threads = [];
foreach ($all_user_msgs as $m) {
    $other_id   = ($m['sender_id'] == $user_id) ? $m['receiver_id'] : $m['sender_id'];
    $other_name = ($m['sender_id'] == $user_id) ? $m['receiver_name'] : $m['sender_name'];
    $key        = $m['product_id'] . '_' . $other_id;

    if (!isset($threads[$key])) {
        $threads[$key] = [
            'product_id'      => $m['product_id'],
            'product_name'    => $m['product_name'],
            'other_user_id'   => $other_id,
            'other_user_name' => $other_name,
            'last_message'    => $m['message_text']
        ];
    }
}

// Fetch active conversation if selected
$chat_messages  = [];
$active_product = null;
$active_partner = null;

if ($product_id > 0 && $receiver_id > 0) {
    $p_stmt = $pdo->prepare("SELECT product_name FROM products WHERE product_id = ?");
    $p_stmt->execute([$product_id]);
    $active_product = $p_stmt->fetch();

    $u_stmt = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ?");
    $u_stmt->execute([$receiver_id]);
    $active_partner = $u_stmt->fetch();

    $chat_stmt = $pdo->prepare("
        SELECT m.*, u.full_name AS sender_name 
        FROM messages m
        JOIN users u ON m.sender_id = u.user_id
        WHERE m.product_id = ? 
          AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
        ORDER BY m.date_sent ASC
    ");
    $chat_stmt->execute([$product_id, $user_id, $receiver_id, $receiver_id, $user_id]);
    $chat_messages = $chat_stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Messages - Campus Market Hub</title>
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
    <h2>Campus Messages</h2>
    <div style="display: grid; grid-template-columns: 300px 1fr; gap: 20px; min-height: 430px;">
        <!-- Left Sidebar: Conversation Threads -->
        <div style="border-right: 1px solid #ddd; padding-right: 15px;">
            <h4>Conversations</h4>
            <?php if (empty($threads) && !$active_product): ?>
                <p style="font-size:0.9rem; color:#666;">No messages yet. Click "Message Seller" on any item to start chatting.</p>
            <?php endif; ?>
            <?php foreach ($threads as $t): ?>
                <div style="padding: 12px; border-bottom: 1px solid #eee; border-radius: 5px; background: <?= ($t['product_id'] == $product_id && $t['other_user_id'] == $receiver_id) ? '#d8f3dc' : '#fff' ?>;">
                    <a href="messages.php?product_id=<?= $t['product_id'] ?>&receiver_id=<?= $t['other_user_id'] ?>" style="text-decoration:none; color:#1b4332; font-weight:bold; display:block;">
                        <?= htmlspecialchars($t['other_user_name']) ?>
                    </a>
                    <small style="color:#2d6a4f; font-weight:600;">Item: <?= htmlspecialchars($t['product_name']) ?></small><br>
                    <small style="color:#666;"><?= htmlspecialchars(substr($t['last_message'], 0, 35)) ?>...</small>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Right Pane: Opposite-Side Chat Window -->
        <div>
            <?php if ($active_product && $active_partner): ?>
                <div style="padding-bottom:10px; margin-bottom:10px; border-bottom:1px solid #ddd;">
                    <strong>Chatting with:</strong> <?= htmlspecialchars($active_partner['full_name']) ?> &nbsp;|&nbsp;
                    <strong>Item:</strong> <?= htmlspecialchars($active_product['product_name']) ?>
                </div>

                <div class="chat-box" id="chat-box">
                    <?php if (empty($chat_messages)): ?>
                        <p style="color:#777; text-align:center; margin-top:40px;">No messages yet. Send a message below to start the conversation.</p>
                    <?php else: ?>
                        <?php foreach ($chat_messages as $msg): ?>
                            <?php $is_me = ((int)$msg['sender_id'] === $user_id); ?>
                            <div class="msg-row <?= $is_me ? 'sent' : 'received' ?>">
                                <div class="msg-bubble">
                                    <span class="msg-meta">
                                        <?= $is_me ? 'You (' . htmlspecialchars($_SESSION['full_name']) . ')' : htmlspecialchars($msg['sender_name']) ?>
                                        &bull; <?= date('H:i', strtotime($msg['date_sent'])) ?>
                                    </span>
                                    <?= nl2br(htmlspecialchars($msg['message_text'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <form method="POST" style="display:flex; gap:10px;">
                    <input type="hidden" name="product_id" value="<?= $product_id ?>">
                    <input type="hidden" name="receiver_id" value="<?= $receiver_id ?>">
                    <input type="text" name="message_text" placeholder="Write a message..." required style="margin-top:0;">
                    <button type="submit" name="send_message" style="margin-top:0;">Send</button>
                </form>
            <?php else: ?>
                <p style="color:#666; margin-top:60px; text-align:center;">Select a conversation on the left to view or reply to messages.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Auto-scroll chat box to the newest message at the bottom
    const chatBox = document.getElementById('chat-box');
    if (chatBox) {
        chatBox.scrollTop = chatBox.scrollHeight;
    }
</script>
</body>
</html>