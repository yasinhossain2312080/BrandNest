
<?php
session_start();

require_once "../config/database.php";

// Admin Login Check
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Only POST request allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: orders.php");
    exit;
}

$orderId = (int)($_POST['order_id'] ?? 0);

// Return to Order Details
function backToOrder($id, $message) {
    header(
        "Location: view_order.php?id=" . $id .
        "&courier_message=" . urlencode($message)
    );
    exit;
}

if ($orderId <= 0) {
    die("Invalid Order ID.");
}

// Load Steadfast Configuration
$config = require "../config/steadfast.php";

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
    backToOrder($orderId, "অর্ডার পাওয়া যায়নি।");
}

// Check Previous Booking
if (
    !empty($order['steadfast_consignment_id']) ||
    $order['steadfast_booking_status'] === 'booked'
) {
    backToOrder(
        $orderId,
        "এই অর্ডারের পার্সেল আগে থেকেই বুক করা হয়েছে।"
    );
}

// Cancelled Order Check
if ($order['order_status'] === 'cancelled') {
    backToOrder($orderId, "বাতিল অর্ডার বুক করা যাবে না।");
}

// Validate Phone Number
$phone = preg_replace('/\D/', '', $order['phone']);

if (!preg_match('/^01[0-9]{9}$/', $phone)) {
    backToOrder(
        $orderId,
        "মোবাইল নম্বর ১১ সংখ্যার হতে হবে এবং 01 দিয়ে শুরু হতে হবে।"
    );
}

// Prepare Address
$address = trim($order['address'] . ", " . $order['area']);

if (strlen($address) > 490) {
    backToOrder($orderId, "ঠিকানা অনেক বড়। ঠিকানা সংক্ষিপ্ত করুন।");
}

// Fetch Order Items
$stmt = $conn->prepare("
    SELECT product_name, quantity
    FROM order_items
    WHERE order_id = ?
");

$stmt->bind_param("i", $orderId);
$stmt->execute();

$itemsResult = $stmt->get_result();

$itemNames = [];
$totalQuantity = 0;

while ($item = $itemsResult->fetch_assoc()) {
    $itemNames[] = $item['product_name'];
    $totalQuantity += (int)$item['quantity'];
}

$stmt->close();

if (empty($itemNames)) {
    backToOrder($orderId, "অর্ডারে কোনো পণ্য পাওয়া যায়নি।");
}

// Calculate COD Amount
$paymentMethod = strtolower($order['payment_method'] ?? 'cod');

$codAmount = ($paymentMethod === 'cod')
    ? (float)$order['total_amount']
    : 0;

// Use BrandNest Order Number as Invoice
$invoice = $order['order_number'];

// Prepare API Payload
$payload = [
    'invoice' => $invoice,
    'recipient_name' => $order['customer_name'],
    'recipient_phone' => $phone,
    'recipient_address' => $address,
    'cod_amount' => $codAmount,
    'item_description' => implode(', ', $itemNames),
    'total_lot' => max(1, $totalQuantity),
    'delivery_type' => 0
];

// Send Booking Request
$url = rtrim($config['base_url'], '/') . '/create_order';

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE
    ),
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
        "Steadfast-এর সঙ্গে সংযোগ করা যায়নি। Merchant Portal-এ Invoice যাচাই না করে Retry করবেন না। Error: " .
        $curlError
    );
}

// Decode API Response
$result = json_decode($response, true);

// Check Successful Booking
if (
    $httpCode === 200 &&
    isset($result['status']) &&
    (int)$result['status'] === 200 &&
    !empty($result['consignment']['consignment_id'])
) {

    $consignment = $result['consignment'];

    $consignmentId = (string)$consignment['consignment_id'];
    $trackingCode = (string)($consignment['tracking_code'] ?? '');

    // Correct API Field: tracking_link
    $trackingUrl = (string)($consignment['tracking_link'] ?? '');

    $courierStatus = (string)($consignment['status'] ?? 'in_review');

    // Save Courier Information
    // Do NOT change order_status to shipped here.
    $stmt = $conn->prepare("
        UPDATE orders
        SET delivery_partner = 'Steadfast',
            tracking_id = ?,
            steadfast_consignment_id = ?,
            steadfast_tracking_code = ?,
            steadfast_tracking_url = ?,
            steadfast_status = ?,
            steadfast_booking_status = 'booked',
            steadfast_booked_at = NOW()
        WHERE id = ?
          AND steadfast_consignment_id IS NULL
    ");

    $stmt->bind_param(
        "sssssi",
        $trackingCode,
        $consignmentId,
        $trackingCode,
        $trackingUrl,
        $courierStatus,
        $orderId
    );

    if ($stmt->execute() && $stmt->affected_rows === 1) {
        $stmt->close();

        backToOrder(
            $orderId,
            "Steadfast Parcel সফলভাবে বুক হয়েছে। Tracking Code: " .
            $trackingCode
        );
    }

    $stmt->close();

    // Booking succeeded remotely but local save failed.
    backToOrder(
        $orderId,
        "Steadfast-এ Booking সফল হয়েছে, কিন্তু Database-এ Save হয়নি। Merchant Portal-এ Invoice যাচাই করুন।"
    );
}

// API Error
$errorMessage = $result['message'] ?? 'Unknown API Error';

backToOrder(
    $orderId,
    "Parcel Booking সফল হয়নি। HTTP: " .
    $httpCode . " | " . $errorMessage
);
