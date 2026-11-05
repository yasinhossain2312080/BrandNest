
<?php
session_start();
require_once "config/database.php";

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Initialize cart
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// CSRF token
if (empty($_SESSION['cart_token'])) {
    $_SESSION['cart_token'] = bin2hex(random_bytes(32));
}

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['cart_token'] ?? '';

    if (!hash_equals($_SESSION['cart_token'], $token)) {
        die("Invalid request. Please refresh the page.");
    }

    $action = $_POST['action'] ?? '';
    $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);

    if ($action === 'remove' && $productId) {

        unset($_SESSION['cart'][$productId]);
        $_SESSION['cart_message'] = "Product removed from cart.";

    } elseif ($action === 'update' && $productId) {

        $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

        if (!$quantity || $quantity < 1) {
            $_SESSION['cart_message'] = "Invalid quantity.";
        } else {

            $stmt = $conn->prepare("
                SELECT stock
                FROM products
                WHERE id = ? AND status = 1
                LIMIT 1
            ");

            $stmt->bind_param("i", $productId);
            $stmt->execute();
            $product = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$product) {
                unset($_SESSION['cart'][$productId]);
                $_SESSION['cart_message'] = "Product is no longer available.";
            } elseif ($quantity > (int)$product['stock']) {
                $_SESSION['cart_message'] = "Only " . (int)$product['stock'] . " items are available.";
            } else {
                $_SESSION['cart'][$productId]['quantity'] = $quantity;
                $_SESSION['cart_message'] = "Cart updated successfully.";
            }
        }
    }

    header("Location: cart.php");
    exit;
}

// Fetch latest product information
$cartItems = [];
$subtotal = 0;
$cartCount = 0;

foreach ($_SESSION['cart'] as $id => $item) {

    $productId = (int)$id;

    $stmt = $conn->prepare("
        SELECT id, name, price, discount_price, image, stock
        FROM products
        WHERE id = ? AND status = 1
        LIMIT 1
    ");

    $stmt->bind_param("i", $productId);
    $stmt->execute();

    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$product) {
        unset($_SESSION['cart'][$id]);
        continue;
    }

    $quantity = max(1, (int)($item['quantity'] ?? 1));

    $price = (
        $product['discount_price'] !== null &&
        (float)$product['discount_price'] > 0
    )
        ? (float)$product['discount_price']
        : (float)$product['price'];

    $cartItems[] = [
        'id' => $product['id'],
        'name' => $product['name'],
        'image' => $product['image'],
        'price' => $price,
        'quantity' => $quantity,
        'stock' => (int)$product['stock'],
        'total' => $price * $quantity
    ];

    $subtotal += $price * $quantity;
    $cartCount += $quantity;
}

function cartImage($image) {
    if (empty($image)) {
        return "assets/images/no-image.png";
    }

    return "uploads/" . rawurlencode(basename($image));
}

$message = $_SESSION['cart_message'] ?? '';
unset($_SESSION['cart_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Shopping Cart | BrandNest</title>

    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .cart-page {
            min-height: 75vh;
            padding: 50px 15px;
            background: #101010;
        }

        .cart-container {
            max-width: 1100px;
            margin: auto;
        }

        .cart-title {
            text-align: center;
            margin-bottom: 35px;
        }

        .cart-title h1 {
            color: #fff;
            font-size: 34px;
        }

        .cart-title p {
            color: #aaa;
            margin-top: 10px;
        }

        .cart-message {
            background: #29251d;
            color: #e0bd75;
            padding: 13px;
            border-left: 4px solid #c9a45c;
            margin-bottom: 20px;
        }

        .cart-layout {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 25px;
            align-items: start;
        }

        .cart-items,
        .cart-summary {
            background: #191919;
            border: 1px solid #393126;
            border-radius: 9px;
            padding: 20px;
        }

        .cart-item {
            display: grid;
            grid-template-columns: 100px 1fr auto;
            gap: 18px;
            align-items: center;
            padding: 18px 0;
            border-bottom: 1px solid #333;
        }

        .cart-item:first-child {
            padding-top: 0;
        }

        .cart-item img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            background: #252525;
            border-radius: 6px;
        }

        .cart-item h3 {
            color: #fff;
            margin-bottom: 8px;
        }

        .cart-item p {
            color: #c9a45c;
            margin-bottom: 12px;
        }

        .quantity-form {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .quantity-form input {
            width: 65px;
            padding: 8px;
            background: #252525;
            color: white;
            border: 1px solid #555;
            border-radius: 4px;
        }

        .quantity-form button {
            padding: 8px 12px;
            background: #c9a45c;
            color: #111;
            border: none;
            border-radius: 4px;
            font-weight: bold;
        }

        .remove-btn {
            background: none;
            border: none;
            color: #ff7777;
            cursor: pointer;
            margin-top: 10px;
        }

        .item-total {
            color: #c9a45c;
            font-weight: bold;
            white-space: nowrap;
        }

        .cart-summary h2 {
            color: #c9a45c;
            margin-bottom: 20px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            color: #ccc;
            padding: 13px 0;
            border-bottom: 1px solid #333;
        }

        .summary-total {
            color: #c9a45c;
            font-size: 20px;
            font-weight: bold;
            border-bottom: none;
        }

        .checkout-btn {
            display: block;
            text-align: center;
            background: #c9a45c;
            color: #111;
            padding: 14px;
            margin-top: 20px;
            border-radius: 5px;
            font-weight: bold;
        }

        .continue-shopping {
            display: inline-block;
            color: #c9a45c;
            margin-top: 25px;
        }

        .empty-cart {
            text-align: center;
            padding: 50px 15px;
            color: #aaa;
        }

        .empty-cart h2 {
            color: #fff;
            margin-bottom: 15px;
        }

        @media(max-width: 750px) {
            .cart-layout {
                grid-template-columns: 1fr;
            }
        }

        @media(max-width: 480px) {
            .cart-item {
                grid-template-columns: 75px 1fr;
                gap: 12px;
            }

            .cart-item img {
                width: 75px;
                height: 85px;
            }

            .item-total {
                grid-column: 2;
            }

            .cart-items,
            .cart-summary {
                padding: 14px;
            }

            .cart-title h1 {
                font-size: 27px;
            }
        }
    </style>
</head>

<body>

<header class="bn-header">
    <div class="bn-nav container">

        <a href="index.php" class="bn-logo">
            Brand<span>Nest</span>
        </a>

        <nav class="bn-menu">
            <a href="index.php">Home</a>
            <a href="index.php#categories">Categories</a>
            <a href="index.php#products">Shop</a>
        </nav>

        <a href="cart.php" class="bn-header-btn">
            Cart (<?php echo $cartCount; ?>)
        </a>

    </div>
</header>

<main class="cart-page">

    <div class="cart-container">

        <div class="cart-title">
            <h1>Shopping Cart</h1>
            <p>Review your selected products</p>
        </div>

        <?php if ($message): ?>
            <div class="cart-message">
                <?php echo e($message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($cartItems)): ?>

            <div class="cart-layout">

                <div class="cart-items">

                    <?php foreach ($cartItems as $item): ?>

                        <div class="cart-item">

                            <img
                                src="<?php echo e(cartImage($item['image'])); ?>"
                                alt="<?php echo e($item['name']); ?>"
                            >

                            <div>

                                <h3><?php echo e($item['name']); ?></h3>

                                <p>
                                    ৳<?php echo number_format($item['price'], 2); ?>
                                </p>

                                <form method="POST" class="quantity-form">

                                    <input type="hidden" name="cart_token"
                                        value="<?php echo e($_SESSION['cart_token']); ?>">

                                    <input type="hidden" name="action" value="update">

                                    <input type="hidden" name="product_id"
                                        value="<?php echo (int)$item['id']; ?>">

                                    <input
                                        type="number"
                                        name="quantity"
                                        min="1"
                                        max="<?php echo $item['stock']; ?>"
                                        value="<?php echo $item['quantity']; ?>"
                                        required
                                    >

                                    <button type="submit">Update</button>

                                </form>

                                <form method="POST">

                                    <input type="hidden" name="cart_token"
                                        value="<?php echo e($_SESSION['cart_token']); ?>">

                                    <input type="hidden" name="action" value="remove">

                                    <input type="hidden" name="product_id"
                                        value="<?php echo (int)$item['id']; ?>">

                                    <button type="submit" class="remove-btn">
                                        Remove
                                    </button>

                                </form>

                            </div>

                            <div class="item-total">
                                ৳<?php echo number_format($item['total'], 2); ?>
                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

                <aside class="cart-summary">

                    <h2>Order Summary</h2>

                    <div class="summary-row">
                        <span>Total Items</span>
                        <strong><?php echo $cartCount; ?></strong>
                    </div>

                    <div class="summary-row summary-total">
                        <span>Subtotal</span>
                        <strong>
                            ৳<?php echo number_format($subtotal, 2); ?>
                        </strong>
                    </div>

                    <p style="color:#999;font-size:12px;margin-top:12px;">
                        Delivery charge will be calculated at checkout.
                    </p>

                    <a href="checkout.php" class="checkout-btn">
                        Proceed to Checkout
                    </a>

                </aside>

            </div>

        <?php else: ?>

            <div class="empty-cart">

                <h2>Your Cart is Empty</h2>

                <p>You haven't added any products yet.</p>

                <a href="index.php#products" class="bn-primary-btn">
                    Continue Shopping
                </a>

            </div>

        <?php endif; ?>

        <a href="index.php#products" class="continue-shopping">
            &larr; Continue Shopping
        </a>

    </div>

</main>

</body>
</html>
