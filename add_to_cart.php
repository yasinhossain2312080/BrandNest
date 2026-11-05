
<?php
session_start();

require_once "config/database.php";

// Only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

// Product ID
$productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);

if (!$productId || $productId < 1) {
    header("Location: index.php");
    exit;
}

// Requested action
$action = $_POST['action'] ?? 'add_to_cart';
$isBuyNow = ($action === 'buy_now');

// Fetch active product
$stmt = $conn->prepare("
    SELECT id, name, price, discount_price, stock, image
    FROM products
    WHERE id = ? AND status = 1
    LIMIT 1
");

$stmt->bind_param("i", $productId);
$stmt->execute();

$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    $_SESSION['cart_message'] = "Product not found.";
    header("Location: index.php");
    exit;
}

// Check stock
if ((int)$product['stock'] < 1) {
    $_SESSION['cart_message'] = "This product is out of stock.";
    header("Location: index.php");
    exit;
}

// Initialize cart
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Current quantity
$currentQuantity = (int)(
    $_SESSION['cart'][$productId]['quantity'] ?? 0
);

// Prevent exceeding available stock
if (
    !$isBuyNow &&
    $currentQuantity >= (int)$product['stock']
) {
    $_SESSION['cart_message'] = "Maximum available stock reached.";
    header("Location: index.php");
    exit;
}

// Calculate selling price
$price = (
    $product['discount_price'] !== null &&
    (float)$product['discount_price'] > 0
)
    ? (float)$product['discount_price']
    : (float)$product['price'];

// Add product to cart
$_SESSION['cart'][$productId] = [
    'product_id' => (int)$product['id'],
    'name'       => $product['name'],
    'price'      => $price,
    'image'      => $product['image'],
    'quantity'   => $isBuyNow
        ? max(1, $currentQuantity)
        : $currentQuantity + 1
];

// Buy Now: Go directly to checkout
if ($isBuyNow) {

    $_SESSION['cart_message'] = "Product ready for checkout.";

    header("Location: checkout.php");
    exit;
}

// Normal Add to Cart
$_SESSION['cart_message'] = "Product added to cart successfully!";

// Return to previous page
$redirect = $_SERVER['HTTP_REFERER'] ?? 'index.php';

$redirectHost = parse_url($redirect, PHP_URL_HOST);

if (
    $redirectHost !== null &&
    strcasecmp($redirectHost, $_SERVER['HTTP_HOST']) !== 0
) {
    $redirect = 'index.php';
}

header("Location: " . $redirect);
exit;
?>
