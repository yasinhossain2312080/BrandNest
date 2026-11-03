
<?php
session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

require_once "../config/database.php";

// Only allow POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: products.php");
    exit;
}

$id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header("Location: products.php?error=invalid");
    exit;
}

// Find product image before deleting
$stmt = $conn->prepare("SELECT image FROM products WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    header("Location: products.php?error=notfound");
    exit;
}

// Delete product from database
$delete = $conn->prepare("DELETE FROM products WHERE id = ?");
$delete->bind_param("i", $id);

if ($delete->execute()) {

    // Delete image file if it exists
    if (!empty($product["image"])) {
        $image_path = "../uploads/" . basename($product["image"]);

        if (is_file($image_path)) {
            unlink($image_path);
        }
    }

    header("Location: products.php?deleted=1");
    exit;

} else {
    header("Location: products.php?error=deletefailed");
    exit;
}
