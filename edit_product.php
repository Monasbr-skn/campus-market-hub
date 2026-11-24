<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit;
}

$user_id    = (int)$_SESSION['user_id'];
$product_id = (int)($_GET['id'] ?? 0);
$error      = '';

// Fetch product & verify ownership
$stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = ? AND user_id = ?");
$stmt->execute([$product_id, $user_id]);
$product = $stmt->fetch();

if (!$product) {
    die("Listing not found or access denied.");
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id  = (int)$_POST['category_id'];
    $product_name = trim($_POST['product_name']);
    $description  = trim($_POST['description']);
    $listing_type = $_POST['listing_type'];
    $price        = ($listing_type === 'Sell') ? (float)$_POST['price'] : 0.00;
    $image_path   = $product['product_image'];

    // Replace photo only if a new file was uploaded
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "uploads/products/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $clean_name  = preg_replace("/[^a-zA-Z0-9._-]/", "_", basename($_FILES["product_image"]["name"]));
        $target_file = $target_dir . time() . "_" . $clean_name;

        if (move_uploaded_file($_FILES["product_image"]["tmp_name"], $target_file)) {
            $image_path = $target_file;
        } else {
            $error = "Failed to upload new image.";
        }
    }

    if ($error === '') {
        $upd = $pdo->prepare("
            UPDATE products 
            SET category_id = ?, product_name = ?, description = ?, listing_type = ?, price = ?, product_image = ?
            WHERE product_id = ? AND user_id = ?
        ");
        $upd->execute([$category_id, $product_name, $description, $listing_type, $price, $image_path, $product_id, $user_id]);

        header("Location: my_listings.php?msg=updated");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Listing - Campus Market Hub</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/auto_refresh.js" defer></script>
    <script>
        function togglePrice() {
            const type = document.getElementById('listing_type').value;
            const priceGroup = document.getElementById('price-group');
            priceGroup.style.display = (type === 'Sell') ? 'block' : 'none';
        }
    </script>
</head>
<body onload="togglePrice()">
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

<div class="container">
    <p><a href="my_listings.php">&larr; Back to My Listings</a></p><br>
    <h2>Edit Listing</h2>
    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <label>Item Name</label>
        <input type="text" name="product_name" value="<?= htmlspecialchars($product['product_name']) ?>" required>

        <label>Category</label>
        <select name="category_id" required>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['category_id'] ?>" <?= $product['category_id'] == $cat['category_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['category_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Listing Type</label>
        <select name="listing_type" id="listing_type" onchange="togglePrice()" required>
            <option value="Sell" <?= $product['listing_type'] === 'Sell' ? 'selected' : '' ?>>For Sale</option>
            <option value="Exchange" <?= $product['listing_type'] === 'Exchange' ? 'selected' : '' ?>>For Exchange / Trade-In</option>
            <option value="Donate" <?= $product['listing_type'] === 'Donate' ? 'selected' : '' ?>>For Donation (Free)</option>
        </select>

        <div id="price-group">
            <label>Price (KES)</label>
            <input type="number" name="price" step="0.01" min="0" value="<?= htmlspecialchars($product['price']) ?>">
        </div>

        <label>Description & Condition</label>
        <textarea name="description" rows="4" required><?= htmlspecialchars($product['description']) ?></textarea>

        <label>Current Photo</label>
        <img src="<?= htmlspecialchars($product['product_image']) ?>" width="100" style="border-radius:6px; margin:6px 0; display:block;">

        <label>Replace Photo (Optional)</label>
        <input type="file" name="product_image" accept="image/*">

        <button type="submit">Save Changes</button>
    </form>
</div>
</body>
</html>