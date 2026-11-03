
<?php
session_start();

require_once "../config/database.php";

// Admin Login Check
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Get Order ID
$orderId = (int)($_GET['id'] ?? 0);

if ($orderId <= 0) {
    die("Invalid Order ID.");
}

// Fetch Order
$stmt = $conn->prepare("
    SELECT order_number, steadfast_consignment_id
    FROM orders
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $orderId);
$stmt->execute();

$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    die("অর্ডার পাওয়া যায়নি।");
}

// Check Booking
if (empty($order['steadfast_consignment_id'])) {
    die("এই অর্ডারের কোনো Steadfast Parcel বুক করা হয়নি।");
}

// Load Configuration
$config = require "../config/steadfast.php";

// Prepare Tracking History API URL
$invoice = $order['order_number'];

$url = rtrim($config['base_url'], '/') .
    '/trackings_by_invoice/' . rawurlencode($invoice);

// Send GET Request
$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPGET => true,
    CURLOPT_HTTPHEADER => [
        'Api-Key: ' . $config['api_key'],
        'Secret-Key: ' . $config['secret_key'],
        'Content-Type: application/json'
    ],
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

curl_close($ch);

// Connection Error
if ($response === false) {
    die("Steadfast Connection Error: " . e($curlError));
}

// Decode Response
$result = json_decode($response, true);

// Check Response
if (
    $httpCode !== 200 ||
    !is_array($result) ||
    (int)($result['status'] ?? 0) !== 200
) {
    $message = is_array($result)
        ? ($result['message'] ?? 'Unknown API Error')
        : 'Invalid API Response';

    die(
        "Tracking History পাওয়া যায়নি। HTTP: " .
        (int)$httpCode . " | " . e($message)
    );
}

// Get Tracking History
$history = $result['tracking'] ?? [];

if (!is_array($history)) {
    $history = [];
}

?>

<!DOCTYPE html>
<html lang="bn">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tracking History | BrandNest</title>

    <style>

        body {
            background: #101010;
            color: #eee;
            font-family: Arial, sans-serif;
            padding: 20px;
        }

        .container {
            max-width: 850px;
            margin: 30px auto;
        }

        h2 {
            color: #c9a45c;
        }

        .card {
            background: #191919;
            border: 1px solid #51442f;
            border-radius: 10px;
            padding: 22px;
            margin-bottom: 20px;
        }

        .history-item {
            border-left: 3px solid #c9a45c;
            padding: 10px 15px;
            margin: 20px 0;
            background: #242424;
            border-radius: 5px;
        }

        .history-item strong {
            color: #d9b66e;
        }

        .history-date {
            color: #aaa;
            font-size: 13px;
            margin-top: 8px;
        }

        .back-btn {
            display: inline-block;
            background: #c9a45c;
            color: #111;
            padding: 12px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
        }

        .empty-message {
            color: #e5bd66;
        }

    </style>

</head>

<body>

<div class="container">

    <h2>Steadfast Tracking History</h2>

    <div class="card">

        <p>
            <strong>Order Number:</strong>
            <?php echo e($order['order_number']); ?>
        </p>

        <p>
            <strong>Consignment ID:</strong>
            <?php echo e($order['steadfast_consignment_id']); ?>
        </p>

    </div>

    <div class="card">

        <h3>Courier Updates</h3>

        <?php if (empty($history)): ?>

            <p class="empty-message">
                এখনো কোনো Tracking History পাওয়া যায়নি।
            </p>

        <?php else: ?>

            <?php foreach ($history as $event): ?>

                <div class="history-item">

                    <p>
                        <strong>Update:</strong><br>
                        <?php echo e($event['text'] ?? 'No description'); ?>
                    </p>

                    <p>
                        <strong>Tracking Type:</strong>
                        <?php echo e($event['tracking_type'] ?? 'N/A'); ?>
                    </p>

                    <p class="history-date">

                        <?php

                        if (!empty($event['created_at'])) {
                            $timestamp = strtotime($event['created_at']);

                            echo $timestamp
                                ? date('d-m-Y h:i A', $timestamp)
                                : e($event['created_at']);
                        } else {
                            echo 'Date unavailable';
                        }

                        ?>

                    </p>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

    <a
        href="view_order.php?id=<?php echo $orderId; ?>"
        class="back-btn"
    >
        অর্ডারে ফিরে যান
    </a>

</div>

</body>
</html>
