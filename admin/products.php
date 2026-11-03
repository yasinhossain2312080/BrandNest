
<?php
session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

require_once "../config/database.php";

$message = "";

// Fetch Categories
$categories = $conn->query(
    "SELECT id, name FROM categories WHERE status = 1 ORDER BY name ASC"
);

// Add New Product
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_product"])) {

    $category_id = filter_input(INPUT_POST, "category_id", FILTER_VALIDATE_INT);
    $name = trim($_POST["name"]);
    $description = trim($_POST["description"]);
    $price = (float) $_POST["price"];

    $discount_price = $_POST["discount_price"] !== ""
        ? (float) $_POST["discount_price"]
        : null;

    $stock = (int) $_POST["stock"];
    $image_name = null;

    if (!$category_id || $name === "" || $price <= 0 || $stock < 0) {
        $message = "Please enter valid product information.";

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

        // Upload Product Image
        if ($message === "" &&
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

                    $image_name = uniqid("product_", true) . "." . $extension;

                    if (!move_uploaded_file(
                        $_FILES["image"]["tmp_name"],
                        "../uploads/" . $image_name
                    )) {
                        $message = "Image upload failed.";
                    }
                }
            }
        }

        // Save Product
        if ($message === "") {

            $slug = strtolower(
                trim(preg_replace("/[^A-Za-z0-9-]+/", "-", $name), "-")
            );

            $slug .= "-" . time();

            $stmt = $conn->prepare(
                "INSERT INTO products
                (category_id, name, slug, description, price, discount_price, image, stock)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "isssddsi",
                $category_id,
                $name,
                $slug,
                $description,
                $price,
                $discount_price,
                $image_name,
                $stock
            );

            if ($stmt->execute()) {
                header("Location: products.php?added=1");
                exit;
            } else {
                $message = "Something went wrong. Please try again.";
            }
        }
    }
}

// Success and error messages
if (isset($_GET["added"])) {
    $message = "Product added successfully!";
}

if (isset($_GET["deleted"])) {
    $message = "Product deleted successfully!";
}

if (isset($_GET["error"])) {
    $message = "Unable to delete product. Please try again.";
}

// Get All Products with Category
$result = $conn->query(
    "SELECT products.*, categories.name AS category_name
     FROM products
     LEFT JOIN categories ON products.category_id = categories.id
     ORDER BY products.id DESC"
);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Products | BrandNest</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .admin-content {
            padding: 40px 0;
        }

        .admin-form {
            background: #191919;
            padding: 25px;
            border: 1px solid #393126;
            border-radius: 8px;
            max-width: 650px;
            margin: 25px 0;
        }

        .admin-form label {
            display: block;
            margin: 15px 0 8px;
        }

        .admin-form input,
        .admin-form textarea,
        .admin-form select {
            width: 100%;
            padding: 12px;
            background: #292929;
            border: 1px solid #444;
            border-radius: 5px;
            color: white;
        }

        .admin-form button {
            margin-top: 20px;
            padding: 12px 25px;
            background: #c9a45c;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
        }

        .message {
            margin: 15px 0;
            color: #c9a45c;
        }

        .product-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
            background: #191919;
        }

        .product-table th,
        .product-table td {
            padding: 12px;
            border: 1px solid #393939;
            text-align: left;
        }

        .product-table th {
            color: #c9a45c;
        }

        .product-table img {
            width: 60px;
            height: 60px;
            object-fit: cover;
        }

        .edit-btn,
        .delete-btn {
            display: inline-block;
            padding: 8px 13px;
            border: none;
            border-radius: 4px;
            font-weight: bold;
            text-decoration: none;
            cursor: pointer;
        }

        .edit-btn {
            background: #c9a45c;
            color: #111;
        }

        .delete-btn {
            background: #d9534f;
            color: white;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .table-wrapper {
            overflow-x: auto;
        }
    </style>
</head>

<body>

<div class="container admin-content">

    <h1>Product Management</h1>

    <p style="margin-top:10px;">
        <a href="dashboard.php" style="color:#c9a45c;">
            ← Back to Dashboard
        </a>
        |
        <a href="categories.php" style="color:#c9a45c;">
            Manage Categories
        </a>
    </p>

    <?php if ($message !== ""): ?>
        <p class="message">
            <?= htmlspecialchars($message) ?>
        </p>
    <?php endif; ?>

    <h2 style="margin-top:30px;">Add New Product</h2>

    <form class="admin-form" method="POST" enctype="multipart/form-data">

        <label>Product Category</label>

        <select name="category_id" required>
            <option value="">Select Category</option>

            <?php while ($category = $categories->fetch_assoc()): ?>
                <option value="<?= (int) $category["id"] ?>">
                    <?= htmlspecialchars($category["name"]) ?>
                </option>
            <?php endwhile; ?>

        </select>

        <label>Product Name</label>
        <input type="text" name="name" required>

        <label>Description</label>
        <textarea name="description" rows="4"></textarea>

        <label>Regular Price (৳)</label>
        <input type="number" name="price" step="0.01" min="0.01" required>

        <label>Discount Price (৳) - Optional</label>
        <input type="number" name="discount_price" step="0.01" min="0">

        <label>Stock Quantity</label>
        <input type="number" name="stock" min="0" value="0" required>

        <label>Product Image</label>
        <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp">

        <button type="submit" name="add_product">
            Add Product
        </button>

    </form>

    <h2>All Products</h2>

    <div class="table-wrapper">

        <table class="product-table">

            <tr>
                <th>Image</th>
                <th>Name</th>
                <th>Category</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Action</th>
            </tr>

            <?php if ($result && $result->num_rows > 0): ?>

                <?php while ($product = $result->fetch_assoc()): ?>

                    <tr>

                        <td>
                            <?php if (!empty($product["image"])): ?>
                                <img
                                    src="../uploads/<?= htmlspecialchars($product["image"]) ?>"
                                    alt="Product"
                                >
                            <?php else: ?>
                                No Image
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($product["name"]) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($product["category_name"] ?? "Uncategorized") ?>
                        </td>

                        <td>
                            ৳ <?= number_format($product["price"], 2) ?>
                        </td>

                        <td>
                            <?= (int) $product["stock"] ?>
                        </td>

                        <td>
                            <div class="action-buttons">

                                <a
                                    href="edit_product.php?id=<?= (int) $product["id"] ?>"
                                    class="edit-btn"
                                >
                                    Edit
                                </a>

                                <form
                                    action="delete_product.php"
                                    method="POST"
                                    onsubmit="return confirm('Are you sure you want to delete this product?');"
                                >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int) $product["id"] ?>"
                                    >

                                    <button type="submit" class="delete-btn">
                                        Delete
                                    </button>

                                </form>

                            </div>
                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="6">No products found.</td>
                </tr>

            <?php endif; ?>

        </table>

    </div>

</div>

</body>
</html>
