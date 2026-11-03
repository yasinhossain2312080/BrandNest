
<?php
session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

require_once "../config/database.php";

$message = "";

// Get product ID
$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    die("Invalid Product ID.");
}

// Find product
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    die("Product not found.");
}

// Update product
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $category_id = filter_input(INPUT_POST, "category_id", FILTER_VALIDATE_INT);
    $name = trim($_POST["name"]);
    $description = trim($_POST["description"]);
    $price = (float) $_POST["price"];

    $discount_price = $_POST["discount_price"] !== ""
        ? (float) $_POST["discount_price"]
        : null;

    $stock = (int) $_POST["stock"];
    $image_name = $product["image"];

    if (!$category_id || $name === "" || $price <= 0 || $stock < 0) {
        $message = "Please enter valid information.";

    } elseif (
        $discount_price !== null &&
        ($discount_price <= 0 || $discount_price > $price)
    ) {
        $message = "Discount price must be less than regular price.";

    } else {

        // Check Category
        $category_check = $conn->prepare(
            "SELECT id FROM categories WHERE id = ? AND status = 1"
        );

        $category_check->bind_param("i", $category_id);
        $category_check->execute();

        if ($category_check->get_result()->num_rows === 0) {
            $message = "Please select a valid category.";
        }

        // Upload new image if selected
        if (
            $message === "" &&
            isset($_FILES["image"]) &&
            $_FILES["image"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {

            if ($_FILES["image"]["error"] !== UPLOAD_ERR_OK) {
                $message = "Image upload failed.";

            } else {

                $allowed_types = [
                    "image/jpeg",
                    "image/png",
                    "image/webp"
                ];

                $file_type = mime_content_type($_FILES["image"]["tmp_name"]);

                if (!in_array($file_type, $allowed_types)) {
                    $message = "Only JPG, PNG and WEBP images are allowed.";

                } else {

                    $extension = strtolower(
                        pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION)
                    );

                    $new_image = uniqid("product_", true) . "." . $extension;

                    if (
                        move_uploaded_file(
                            $_FILES["image"]["tmp_name"],
                            "../uploads/" . $new_image
                        )
                    ) {
                        $image_name = $new_image;

                    } else {
                        $message = "Could not save the new image.";
                    }
                }
            }
        }

        // Save updated product
        if ($message === "") {

            $update = $conn->prepare(
                "UPDATE products
                 SET category_id = ?,
                     name = ?,
                     description = ?,
                     price = ?,
                     discount_price = ?,
                     image = ?,
                     stock = ?
                 WHERE id = ?"
            );

            $update->bind_param(
                "issddsii",
                $category_id,
                $name,
                $description,
                $price,
                $discount_price,
                $image_name,
                $stock,
                $id
            );

            if ($update->execute()) {

                // Delete old image after successful replacement
                if (
                    $image_name !== $product["image"] &&
                    !empty($product["image"])
                ) {
                    $old_image = "../uploads/" . basename($product["image"]);

                    if (is_file($old_image)) {
                        unlink($old_image);
                    }
                }

                header("Location: products.php");
                exit;

            } else {
                $message = "Product update failed.";
            }
        }
    }

    // Preserve entered values
    $product["category_id"] = $category_id;
    $product["name"] = $name;
    $product["description"] = $description;
    $product["price"] = $price;
    $product["discount_price"] = $discount_price;
    $product["stock"] = $stock;
    $product["image"] = $image_name;
}

// Fetch Categories
$categories = $conn->query(
    "SELECT id, name FROM categories WHERE status = 1 ORDER BY name ASC"
);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Product | BrandNest</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .edit-content {
            padding: 40px 0;
            max-width: 650px;
        }

        .edit-form {
            background: #191919;
            padding: 25px;
            margin-top: 25px;
            border: 1px solid #393126;
            border-radius: 8px;
        }

        .edit-form label {
            display: block;
            margin: 15px 0 8px;
        }

        .edit-form input,
        .edit-form textarea,
        .edit-form select {
            width: 100%;
            padding: 12px;
            background: #292929;
            border: 1px solid #444;
            border-radius: 5px;
            color: white;
        }

        .edit-form button {
            margin-top: 20px;
            padding: 12px 25px;
            background: #c9a45c;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
        }

        .current-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            margin-top: 10px;
        }

        .message {
            color: #ff7777;
            margin-top: 15px;
        }
    </style>
</head>

<body>

<div class="container edit-content">

    <h1>Edit Product</h1>

    <p style="margin-top:15px;">
        <a href="products.php" style="color:#c9a45c;">
            ← Back to Products
        </a>
    </p>

    <?php if ($message !== ""): ?>
        <p class="message">
            <?= htmlspecialchars($message) ?>
        </p>
    <?php endif; ?>

    <form class="edit-form" method="POST" enctype="multipart/form-data">

        <label>Product Category</label>

        <select name="category_id" required>
            <option value="">Select Category</option>

            <?php while ($category = $categories->fetch_assoc()): ?>
                <option
                    value="<?= (int) $category["id"] ?>"
                    <?= ((int) $product["category_id"] === (int) $category["id"]) ? "selected" : "" ?>
                >
                    <?= htmlspecialchars($category["name"]) ?>
                </option>
            <?php endwhile; ?>

        </select>

        <label>Product Name</label>
        <input
            type="text"
            name="name"
            value="<?= htmlspecialchars($product["name"]) ?>"
            required
        >

        <label>Description</label>
        <textarea name="description" rows="4"><?= htmlspecialchars($product["description"] ?? "") ?></textarea>

        <label>Regular Price (৳)</label>
        <input
            type="number"
            name="price"
            step="0.01"
            min="0.01"
            value="<?= htmlspecialchars($product["price"]) ?>"
            required
        >

        <label>Discount Price (৳)</label>
        <input
            type="number"
            name="discount_price"
            step="0.01"
            min="0"
            value="<?= htmlspecialchars($product["discount_price"] ?? "") ?>"
        >

        <label>Stock Quantity</label>
        <input
            type="number"
            name="stock"
            min="0"
            value="<?= (int) $product["stock"] ?>"
            required
        >

        <label>Current Product Image</label>

        <?php if ($product["image"]): ?>
            <img
                class="current-image"
                src="../uploads/<?= htmlspecialchars($product["image"]) ?>"
                alt="Product Image"
            >
        <?php else: ?>
            <p>No image uploaded.</p>
        <?php endif; ?>

        <label>Choose New Image (Optional)</label>
        <input
            type="file"
            name="image"
            accept=".jpg,.jpeg,.png,.webp"
        >

        <button type="submit">Update Product</button>

    </form>

</div>

</body>
</html>
