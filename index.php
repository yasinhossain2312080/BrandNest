
<?php
session_start();
require_once "config/database.php";

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function productImage($image) {
    if (empty($image)) {
        return "assets/images/no-image.png";
    }

    return "uploads/" . rawurlencode(basename($image));
}

// Initialize Cart
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Cart Count
$cartCount = 0;

foreach ($_SESSION['cart'] as $item) {
    $cartCount += (int)($item['quantity'] ?? 0);
}

// Cart Message
$cartMessage = $_SESSION['cart_message'] ?? '';
unset($_SESSION['cart_message']);

// Selected Product
$selectedProduct = null;

if (isset($_GET['product']) && is_numeric($_GET['product'])) {
    $id = (int) $_GET['product'];

    $stmt = $conn->prepare("
        SELECT products.*, categories.name AS category_name
        FROM products
        LEFT JOIN categories ON products.category_id = categories.id
        WHERE products.id = ? AND products.status = 1
        LIMIT 1
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $selectedProduct = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Active Categories
$categories = $conn->query("
    SELECT id, name, slug, description
    FROM categories
    WHERE status = 1
    ORDER BY id ASC
");

// Search and Category Filter
$search = trim($_GET['search'] ?? '');

$categoryId = isset($_GET['category']) && is_numeric($_GET['category'])
    ? (int) $_GET['category']
    : 0;

// Products Query
$sql = "
    SELECT products.*, categories.name AS category_name
    FROM products
    LEFT JOIN categories ON products.category_id = categories.id
    WHERE products.status = 1
";

$params = [];
$types = "";

if ($categoryId > 0) {
    $sql .= " AND products.category_id = ?";
    $params[] = $categoryId;
    $types .= "i";
}

if ($search !== '') {
    $sql .= " AND (products.name LIKE ? OR products.description LIKE ?)";
    $keyword = "%" . $search . "%";
    $params[] = $keyword;
    $params[] = $keyword;
    $types .= "ss";
}

$sql .= " ORDER BY products.id DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$products = $stmt->get_result();

$pageTitle = $selectedProduct
    ? $selectedProduct['name'] . " | BrandNest"
    : "BrandNest | Premium Collection";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo e($pageTitle); ?></title>

    <meta name="description" content="Discover premium bedding, comforters and clothing collections at BrandNest.">

    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .bn-add-cart-btn {
            display: block;
            width: 100%;
            padding: 11px;
            margin-top: 10px;
            background: transparent;
            color: #c9a45c;
            border: 1px solid #c9a45c;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }

        .bn-add-cart-btn:hover {
            background: #c9a45c;
            color: #111;
        }

        .bn-add-cart-form {
            width: 100%;
        }

        /* Order Now Button */
        .bn-order-now-form {
            width: 100%;
            margin-top: 10px;
        }

        .bn-order-now-btn {
            display: block;
            width: 100%;
            padding: 12px;
            background: #c9a45c;
            color: #111;
            border: 1px solid #c9a45c;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }

        .bn-order-now-btn:hover {
            background: #e0bd75;
            border-color: #e0bd75;
        }

        .bn-detail-order-form {
            max-width: 300px;
            margin-top: 10px;
        }

        .bn-detail-order-form button {
            padding: 14px 25px;
        }

        .bn-cart-message {
            max-width: 1200px;
            width: 90%;
            margin: 15px auto;
            padding: 13px 18px;
            background: #29251d;
            color: #e0bd75;
            border-left: 4px solid #c9a45c;
            border-radius: 4px;
        }

        .bn-detail-cart-form {
            max-width: 300px;
            margin-top: 12px;
        }

        .bn-detail-cart-form button {
            padding: 14px 25px;
        }

        .bn-stock-warning {
            color: #ff8585;
            margin-top: 10px;
        }
    </style>
</head>

<body>

<!-- HEADER -->
<header class="bn-header">
    <div class="bn-nav container">

        <a href="index.php" class="bn-logo">
            Brand<span>Nest</span>
        </a>

        <nav class="bn-menu">
            <a href="index.php">Home</a>
            <a href="index.php#categories">Categories</a>
            <a href="index.php#products">Shop</a>

            <?php if (!empty($_SESSION['customer_id'])): ?>
                <a href="my_orders.php">My Orders</a>
            <?php endif; ?>
        </nav>

        <div class="bn-header-actions">

            <a href="cart.php" class="bn-header-btn">
                Cart (<?php echo $cartCount; ?>)
            </a>

            <?php if (!empty($_SESSION['customer_id'])): ?>

                <span class="bn-customer-name">
                    Hi, <?php echo e($_SESSION['customer_name']); ?>
                </span>

                <a href="customer_logout.php" class="bn-header-btn">
                    Logout
                </a>

            <?php else: ?>

                <a href="login.php" class="bn-header-btn">
                    Login
                </a>

            <?php endif; ?>

        </div>

    </div>
</header>

<?php if ($cartMessage): ?>
    <div class="bn-cart-message">
        <?php echo e($cartMessage); ?>
    </div>
<?php endif; ?>

<main>

<?php if ($selectedProduct): ?>

    <!-- PRODUCT DETAILS -->
    <section class="bn-product-details">
        <div class="container">

            <a href="index.php" class="bn-back-link">
                &larr; Back to Home
            </a>

            <div class="bn-detail-grid">

                <div class="bn-detail-image">
                    <img
                        src="<?php echo e(productImage($selectedProduct['image'])); ?>"
                        alt="<?php echo e($selectedProduct['name']); ?>"
                    >
                </div>

                <div class="bn-detail-info">

                    <p class="bn-category-label">
                        <?php echo e($selectedProduct['category_name'] ?? 'Collection'); ?>
                    </p>

                    <h1><?php echo e($selectedProduct['name']); ?></h1>

                    <div class="bn-description">
                        <h3>Product Description</h3>

                        <p>
                            <?php echo nl2br(e($selectedProduct['description'])); ?>
                        </p>
                    </div>

                    <div class="bn-detail-price">

                        <?php if (
                            $selectedProduct['discount_price'] !== null &&
                            $selectedProduct['discount_price'] > 0
                        ): ?>

                            <del>
                                ৳<?php echo e(number_format((float)$selectedProduct['price'], 2)); ?>
                            </del>

                            <h2>
                                ৳<?php echo e(number_format((float)$selectedProduct['discount_price'], 2)); ?>
                            </h2>

                        <?php else: ?>

                            <h2>
                                ৳<?php echo e(number_format((float)$selectedProduct['price'], 2)); ?>
                            </h2>

                        <?php endif; ?>

                    </div>

                    <?php if ((int)$selectedProduct['stock'] > 0): ?>

                        <p class="bn-stock">In Stock</p>

                        <!-- ADD TO CART -->
                        <form
                            action="add_to_cart.php"
                            method="POST"
                            class="bn-detail-cart-form"
                        >

                            <input
                                type="hidden"
                                name="product_id"
                                value="<?php echo (int)$selectedProduct['id']; ?>"
                            >

                            <button type="submit" class="bn-add-cart-btn">
                                Add to Cart
                            </button>

                        </form>

                        <!-- ORDER NOW -->
                        <form
                            action="add_to_cart.php"
                            method="POST"
                            class="bn-detail-order-form"
                        >

                            <input
                                type="hidden"
                                name="product_id"
                                value="<?php echo (int)$selectedProduct['id']; ?>"
                            >

                            <input
                                type="hidden"
                                name="action"
                                value="buy_now"
                            >

                            <button type="submit" class="bn-order-now-btn">
                                Order Now
                            </button>

                        </form>

                    <?php else: ?>

                        <p class="bn-out-stock">
                            Currently Out of Stock
                        </p>

                    <?php endif; ?>

                </div>

            </div>

        </div>
    </section>

<?php else: ?>

    <!-- HERO -->
    <section class="bn-hero">
        <div class="container bn-hero-content">

            <p class="bn-hero-tag">WELCOME TO BRANDNEST</p>

            <h1>
                Discover Your
                <span>Premium Lifestyle</span>
            </h1>

            <p>
                Explore our exclusive collection of premium bedding,
                comforters and clothing.
            </p>

            <div class="bn-hero-buttons">

                <a href="#products" class="bn-primary-btn">
                    Shop Now
                </a>

                <a href="#categories" class="bn-outline-btn">
                    Explore Categories
                </a>

            </div>

        </div>
    </section>

    <!-- CATEGORIES -->
    <section class="bn-categories" id="categories">
        <div class="container">

            <div class="bn-section-heading">
                <p>FIND YOUR STYLE</p>
                <h2>Shop By Category</h2>
                <span>Explore our collections</span>
            </div>

            <div class="bn-category-grid">

                <?php if ($categories && $categories->num_rows > 0): ?>

                    <?php while ($category = $categories->fetch_assoc()): ?>

                        <a
                            href="index.php?category=<?php echo (int)$category['id']; ?>#products"
                            class="bn-category-card"
                        >

                            <div>
                                <span>BRANDNEST COLLECTION</span>

                                <h3>
                                    <?php echo e($category['name']); ?>
                                </h3>

                                <p>
                                    <?php echo e($category['description']); ?>
                                </p>

                                <strong>Explore Collection &rarr;</strong>
                            </div>

                        </a>

                    <?php endwhile; ?>

                <?php else: ?>

                    <p>No categories available.</p>

                <?php endif; ?>

            </div>

        </div>
    </section>

<?php endif; ?>


<!-- PRODUCTS -->
<section class="bn-products" id="products">
    <div class="container">

        <div class="bn-section-heading">
            <p>OUR PREMIUM COLLECTION</p>

            <h2>
                <?php
                if ($selectedProduct) {
                    echo "You May Also Like";
                } elseif ($categoryId > 0) {
                    echo "Selected Collection";
                } else {
                    echo "Explore Our Products";
                }
                ?>
            </h2>

            <span>Discover something special at BrandNest</span>
        </div>

        <!-- SEARCH -->
        <form action="index.php#products" method="GET" class="bn-search-form">

            <input
                type="text"
                name="search"
                placeholder="Search products..."
                value="<?php echo e($search); ?>"
            >

            <button type="submit">Search</button>

        </form>

        <?php if ($categoryId > 0 || $search !== ''): ?>

            <a href="index.php#products" class="bn-clear-filter">
                View All Products
            </a>

        <?php endif; ?>

        <div class="bn-product-grid">

            <?php
            $productCount = 0;

            if ($products && $products->num_rows > 0):
            ?>

                <?php while ($product = $products->fetch_assoc()): ?>

                    <?php
                    if (
                        $selectedProduct &&
                        (int)$product['id'] === (int)$selectedProduct['id']
                    ) {
                        continue;
                    }

                    $productCount++;
                    ?>

                    <div class="bn-product-card">

                        <a
                            href="index.php?product=<?php echo (int)$product['id']; ?>"
                            class="bn-product-image"
                        >

                            <img
                                src="<?php echo e(productImage($product['image'])); ?>"
                                alt="<?php echo e($product['name']); ?>"
                                loading="lazy"
                            >

                        </a>

                        <div class="bn-product-info">

                            <p class="bn-product-category">
                                <?php echo e($product['category_name'] ?? 'Collection'); ?>
                            </p>

                            <h3>
                                <?php echo e($product['name']); ?>
                            </h3>

                            <div class="bn-card-price">

                                <?php if (
                                    $product['discount_price'] !== null &&
                                    $product['discount_price'] > 0
                                ): ?>

                                    <del>
                                        ৳<?php echo e(number_format((float)$product['price'], 2)); ?>
                                    </del>

                                    <strong>
                                        ৳<?php echo e(number_format((float)$product['discount_price'], 2)); ?>
                                    </strong>

                                <?php else: ?>

                                    <strong>
                                        ৳<?php echo e(number_format((float)$product['price'], 2)); ?>
                                    </strong>

                                <?php endif; ?>

                            </div>

                            <a
                                href="index.php?product=<?php echo (int)$product['id']; ?>"
                                class="bn-view-btn"
                            >
                                View Details
                            </a>

                            <?php if ((int)$product['stock'] > 0): ?>

                                <!-- ADD TO CART -->
                                <form
                                    action="add_to_cart.php"
                                    method="POST"
                                    class="bn-add-cart-form"
                                >

                                    <input
                                        type="hidden"
                                        name="product_id"
                                        value="<?php echo (int)$product['id']; ?>"
                                    >

                                    <button type="submit" class="bn-add-cart-btn">
                                        Add to Cart
                                    </button>

                                </form>

                                <!-- ORDER NOW -->
                                <form
                                    action="add_to_cart.php"
                                    method="POST"
                                    class="bn-order-now-form"
                                >

                                    <input
                                        type="hidden"
                                        name="product_id"
                                        value="<?php echo (int)$product['id']; ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="buy_now"
                                    >

                                    <button type="submit" class="bn-order-now-btn">
                                        Order Now
                                    </button>

                                </form>

                            <?php else: ?>

                                <p class="bn-stock-warning">
                                    Out of Stock
                                </p>

                            <?php endif; ?>

                        </div>

                    </div>

                <?php endwhile; ?>

            <?php endif; ?>

            <?php if ($productCount === 0): ?>

                <div class="bn-no-products">
                    <h3>No Products Found</h3>
                    <p>Try another search or explore all products.</p>

                    <a href="index.php#products" class="bn-primary-btn">
                        View All Products
                    </a>
                </div>

            <?php endif; ?>

        </div>

    </div>
</section>

</main>

<!-- FOOTER -->
<footer class="bn-footer">
    <div class="container">

        <a href="index.php" class="bn-logo">
            Brand<span>Nest</span>
        </a>

        <p>Discover comfort. Experience quality.</p>

        <div class="bn-footer-contact">
            <p>01860384618 | 01924206648</p>
            <p>Mirpur-13, Plot-11/D, Dhaka-1216</p>
        </div>

        <div class="bn-footer-bottom">
            &copy; <?php echo date('Y'); ?> BrandNest. All Rights Reserved.
        </div>

    </div>
</footer>

</body>
</html>

<?php
$stmt->close();
?>
