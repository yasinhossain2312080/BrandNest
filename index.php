
<?php
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

// Facebook link থেকে Product ID
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

// সব Active Product
$products = $conn->query("
    SELECT products.*, categories.name AS category_name
    FROM products
    LEFT JOIN categories ON products.category_id = categories.id
    WHERE products.status = 1
    ORDER BY products.id DESC
");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BrandNest | Premium Collection</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>

<header class="brand-header">
    <a href="index.php" class="brand-logo">
        Brand<span>Nest</span>
    </a>
    <p>Premium Collection</p>
</header>

<main>

<?php if ($selectedProduct): ?>

    <!-- MAIN PRODUCT SECTION -->
    <section class="main-product">

        <div class="container">

            <div class="main-product-image">
                <img
                    src="<?php echo productImage($selectedProduct['image']); ?>"
                    alt="<?php echo e($selectedProduct['name']); ?>"
                >
            </div>

            <div class="main-product-info">

                <p class="product-category">
                    <?php echo e($selectedProduct['category_name'] ?? 'Collection'); ?>
                </p>

                <h1>
                    <?php echo e($selectedProduct['name']); ?>
                </h1>

                <!-- PRODUCT DESCRIPTION BOX -->
                <div class="product-description-box">
                    <h3>Product Description</h3>

                    <p>
                        <?php echo nl2br(e($selectedProduct['description'])); ?>
                    </p>
                </div>

                <!-- PRODUCT PRICE -->
                <?php if (
                    $selectedProduct['discount_price'] !== null &&
                    $selectedProduct['discount_price'] > 0
                ): ?>

                    <del>
                        ৳ <?php echo e($selectedProduct['price']); ?>
                    </del>

                    <h2>
                        ৳ <?php echo e($selectedProduct['discount_price']); ?>
                    </h2>

                <?php else: ?>

                    <h2>
                        ৳ <?php echo e($selectedProduct['price']); ?>
                    </h2>

                <?php endif; ?>

                <!-- ORDER NOW BUTTON -->
                <a
                    href="place_order.php?product=<?php echo (int)$selectedProduct['id']; ?>"
                    class="order-btn"
                >
                    Order Now
                </a>

            </div>

        </div>

    </section>

<?php else: ?>

    <!-- HOMEPAGE HERO -->
    <section class="hero">

        <div class="container">

            <p class="hero-tag">WELCOME TO BRANDNEST</p>

            <h1>
                Discover Your
                <span>Premium Lifestyle</span>
            </h1>

            <p>
                Explore our exclusive collection of premium products.
            </p>

            <a href="#products" class="order-btn">
                Explore Collection
            </a>

        </div>

    </section>

<?php endif; ?>


<!-- ALL / RELATED PRODUCTS -->
<section class="featured-section" id="products">

    <div class="container">

        <div class="section-heading">

            <p>EXPLORE OUR COLLECTION</p>

            <h2>
                <?php echo $selectedProduct ? 'You May Also Like' : 'Our Products'; ?>
            </h2>

        </div>

        <div class="product-grid">

            <?php if ($products && $products->num_rows > 0): ?>

                <?php while ($product = $products->fetch_assoc()): ?>

                    <?php
                    if (
                        $selectedProduct &&
                        $product['id'] == $selectedProduct['id']
                    ) {
                        continue;
                    }
                    ?>

                    <div class="product-card">

                        <a href="index.php?product=<?php echo (int)$product['id']; ?>">

                            <div class="product-image">

                                <img
                                    src="<?php echo productImage($product['image']); ?>"
                                    alt="<?php echo e($product['name']); ?>"
                                >

                            </div>

                        </a>

                        <div class="product-info">

                            <p>
                                <?php echo e($product['category_name'] ?? 'Collection'); ?>
                            </p>

                            <h3>
                                <?php echo e($product['name']); ?>
                            </h3>

                            <?php if (
                                $product['discount_price'] !== null &&
                                $product['discount_price'] > 0
                            ): ?>

                                <del>
                                    ৳ <?php echo e($product['price']); ?>
                                </del>

                                <h4>
                                    ৳ <?php echo e($product['discount_price']); ?>
                                </h4>

                            <?php else: ?>

                                <h4>
                                    ৳ <?php echo e($product['price']); ?>
                                </h4>

                            <?php endif; ?>

                            <a
                                href="index.php?product=<?php echo (int)$product['id']; ?>"
                                class="product-btn"
                            >
                                View Details
                            </a>

                        </div>

                    </div>

                <?php endwhile; ?>

            <?php else: ?>

                <p>No products available right now.</p>

            <?php endif; ?>

        </div>

    </div>

</section>

</main>

</body>
</html>
