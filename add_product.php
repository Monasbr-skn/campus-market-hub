<?php
require_once 'config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit;
}

$error = '';
$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id      = $_SESSION['user_id'];
    $category_id  = (int)$_POST['category_id'];
    $product_name = trim($_POST['product_name']);
    $description  = trim($_POST['description']);
    $listing_type = $_POST['listing_type'];
    $price        = ($listing_type === 'Sell') ? (float)$_POST['price'] : 0.00;

    $target_dir = "uploads/products/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    // Sanitize filename to prevent broken image URLs
    $clean_name  = preg_replace("/[^a-zA-Z0-9._-]/", "_", basename($_FILES["product_image"]["name"]));
    $target_file = $target_dir . time() . "_" . $clean_name;

    if (move_uploaded_file($_FILES["product_image"]["tmp_name"], $target_file)) {
        $stmt = $pdo->prepare("INSERT INTO products (user_id, category_id, product_name, description, listing_type, price, product_image) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $category_id, $product_name, $description, $listing_type, $price, $target_file]);
        
        // Redirect straight back to student dashboard
        header("Location: student_dashboard.php?msg=posted");
        exit;
    } else {
        $error = "Failed to upload product image. Check folder permissions.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Post Item - Campus Market Hub</title>
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
<body>
<div class="navbar">
    <h1>Campus Market Hub</h1>
    <div>
        <a href="student_dashboard.php">Marketplace</a>
        <a href="add_product.php">+ Post Item</a>
        <a href="my_listings.php">My Listings</a>
        <a href="messages.php">Messages</a>
        <a href="transactions.php">Transactions</a>
        <a href="profile.php">Profile</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="container">
    <h2>List a New Item</h2>
    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <label>Item Name</label>
        <input type="text" name="product_name" required>

        <label>Category</label>
        <select name="category_id" required>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
            <?php endforeach; ?>
        </select>

        <label>Listing Type</label>
        <select name="listing_type" id="listing_type" onchange="togglePrice()" required>
            <option value="Sell">For Sale</option>
            <option value="Exchange">For Exchange / Trade-In</option>
            <option value="Donate">For Donation (Free)</option>
        </select>

        <div id="price-group">
            <label>Price (KES)</label>
            <input type="number" name="price" step="0.01" min="0" value="0">
        </div>

        <label>Description & Condition</label>
        <textarea name="description" rows="4" required></textarea>

        <label>Product Photo</label>
        <input type="file" name="product_image" accept="image/*" required>

        <button type="submit">Publish Listing</button>
    </form>
</div>
</body>
</html>