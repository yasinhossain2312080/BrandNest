
<?php
session_start();

require_once "../config/database.php";

// Admin Login Check
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Return to Order Details
function backToOrder($id, $message) {
    header(
        "Location: view_order.php?id=" . $id .
        "&courier_message=" . urlencode($message)
    );
    exit;
}

// Get Order ID
$orderId = (int)($_GET['id'] ?? 0);

if ($orderId <= 0) {
    die("Invalid Order ID.");
}

// Fetch Order
$stmt = $conn->prepare("
    SELECT steadfast_consignment_id
    FROM orders
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $orderId);
$stmt->execute();

$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    backToOrder($orderId, "অর্ডার পাওয়া যায়নি।");
}

// Check Booking
$consignmentId = $order['steadfast_consignment_id'];

if (empty($consignmentId)) {
    backToOrder(
        $orderId,
        "এই অর্ডারের কোনো Steadfast Parcel বুক করা হয়নি।"
    );
}

// Load Steadfast Configuration
$config = require "../config/steadfast.php";

// Prepare API URL
$url = rtrim($config['base_url'], '/') .
    '/status_by_cid/' . rawurlencode($consignmentId);

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
    backToOrder(
        $orderId,
        "Steadfast-এর সঙ্গে সংযোগ করা যায়নি: " . $curlError
    );
}

// Decode Response
$result = json_decode($response, true);

// Validate API Response
if (
    $httpCode !== 200 ||
    !is_array($result) ||
    (int)($result['status'] ?? 0) !== 200 ||
    empty($result['delivery_status'])
) {
    $message = is_array($result)
        ? ($result['message'] ?? 'Unknown API Error')
        : 'Invalid API Response';

    backToOrder(
        $orderId,
        "Tracking Status পাওয়া যায়নি। HTTP: " .
        $httpCode . " | " . $message
    );
}

// Get Delivery Status
$deliveryStatus = (string)$result['delivery_status'];

// Update Database
$stmt = $conn->prepare("
    UPDATE orders
    SET steadfast_status = ?
    WHERE id = ?
      AND steadfast_consignment_id = ?
");

$stmt->bind_param(
    "sis",
    $deliveryStatus,
    $orderId,
    $consignmentId
);

if ($stmt->execute()) {
    $stmt->close();

    backToOrder(
        $orderId,
        "Steadfast Tracking Status আপডেট হয়েছে: " .
        $deliveryStatus
    );
}

$stmt->close();

backToOrder(
    $orderId,
    "Tracking Status পাওয়া গেছে, কিন্তু Database-এ Save হয়নি।"
);
