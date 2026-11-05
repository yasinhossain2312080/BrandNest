
<?php
session_start();

require_once "../config/database.php";

// Admin Login Check
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Helper Functions
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function textLength($value) {
    return function_exists('mb_strlen')
        ? mb_strlen($value, 'UTF-8')
        : strlen($value);
}

$orderId = (int)($_GET['id'] ?? 0);

if ($orderId <= 0) {
    die("Invalid Order ID.");
}

// Fetch Order
$stmt = $conn->prepare("
    SELECT *
    FROM orders
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $orderId);
$stmt->execute();

$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    die("Order not found.");
}

// Fetch Order Items
$stmt = $conn->prepare("
    SELECT product_name, quantity
    FROM order_items
    WHERE order_id = ?
");

$stmt->bind_param("i", $orderId);
$stmt->execute();

$result = $stmt->get_result();

$itemNames = [];
$totalQuantity = 0;

while ($item = $result->fetch_assoc()) {
    $itemNames[] = $item['product_name'];
    $totalQuantity += (int)$item['quantity'];
}

$stmt->close();

// Prepare Data
$invoice = trim($order['order_number']);
$name = trim($order['customer_name']);
$phone = preg_replace('/\D/', '', $order['phone'] ?? '');

$address = trim(
    $order['address'] . ", " . $order['area']
);

$paymentMethod = strtolower($order['payment_method'] ?? 'cod');

$codAmount = ($paymentMethod === 'cod')
    ? (float)$order['total_amount']
    : 0;

$itemDescription = implode(', ', $itemNames);

// Validation Results
$checks = [];

$checks[] = [
    'Invoice Number',
    !empty($invoice)
        && preg_match('/^[A-Za-z0-9_-]+$/', $invoice)
        && textLength($invoice) <= 100
];

$checks[] = [
    'Customer Name',
    !empty($name) && textLength($name) <= 100
];

$checks[] = [
    'Phone Number',
    preg_match('/^01[0-9]{9}$/', $phone)
];

$checks[] = [
    'Delivery Address',
    !empty($address) && textLength($address) <= 490
];

$checks[] = [
    'COD Amount',
    is_finite($codAmount)
        && $codAmount >= 0
        && $codAmount <= 1000000
];

$checks[] = [
    'Order Items',
    !empty($itemNames)
        && textLength($itemDescription) <= 255
];

$checks[] = [
    'Total Quantity',
    $totalQuantity >= 1
];

$checks[] = [
    'Delivery Type',
    true
];

// Build Payload for Local Preview Only
$payload = [
    'invoice' => $invoice,
    'recipient_name' => $name,
    'recipient_phone' => $phone,
    'recipient_address' => $address,
    'cod_amount' => $codAmount,
    'note' => '',
    'delivery_type' => 0
];

$allPassed = true;

foreach ($checks as $check) {
    if (!$check[1]) {
        $allPassed = false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Steadfast Local Validation</title>

    <style>
        body {
            background: #101010;
            color: #fff;
            font-family: Arial, sans-serif;
            padding: 25px;
        }

        .container {
            max-width: 750px;
            margin: auto;
        }

        h2 {
            color: #d6ad55;
        }

        .card {
            background: #1b1b1b;
            border: 1px solid #51452d;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .pass {
            color: #48d597;
        }

        .fail {
            color: #ff7777;
        }

        pre {
            background: #111;
            padding: 15px;
            border-radius: 6px;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .warning {
            color: #e5bd66;
            font-weight: bold;
        }

        a {
            color: #e5bd66;
        }
    </style>
</head>

<body>

<div class="container">

    <h2>Steadfast Local Validation Test</h2>

    <div class="card">
        <h3>Order Information</h3>

        <p>Order Number: <?= e($invoice) ?></p>
        <p>Customer: <?= e($name) ?></p>
        <p>Phone: <?= e($phone) ?></p>
        <p>Address: <?= e($address) ?></p>
        <p>COD Amount: ৳<?= number_format($codAmount, 2) ?></p>
    </div>

    <div class="card">
        <h3>Validation Results</h3>

        <?php foreach ($checks as $check): ?>

            <p class="<?= $check[1] ? 'pass' : 'fail' ?>">
                <?= $check[1] ? 'PASS' : 'FAIL' ?>
                — <?= e($check[0]) ?>
            </p>

        <?php endforeach; ?>

        <hr>

        <?php if ($allPassed): ?>
            <h3 class="pass">All Local Checks Passed!</h3>
        <?php else: ?>
            <h3 class="fail">Some Checks Failed!</h3>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Prepared API Payload</h3>

        <pre><?= e(
            json_encode(
                $payload,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            )
        ) ?></pre>

        <p class="warning">
            LOCAL TEST ONLY — NO API REQUEST WAS SENT.
        </p>
    </div>

    <p>
        <a href="view_order.php?id=<?= $orderId ?>">
            Back to Order Details
        </a>
    </p>

</div>

</body>
</html>
