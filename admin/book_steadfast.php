
<?php

session_start();

require_once "../config/database.php";

// Admin Login Check
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Only POST Request Allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: orders.php");
    exit;
}

$orderId = (int)($_POST['order_id'] ?? 0);

// Return to Order Details
function backToOrder($id, $message)
{
    header(
        "Location: view_order.php?id=" . (int)$id .
        "&courier_message=" . urlencode($message)
    );
    exit;
}

// Validate Order ID
if ($orderId <= 0) {
    die("Invalid Order ID.");
}

// CSRF Token Validation
$submittedToken = $_POST['csrf_token'] ?? '';

if (
    !is_string($submittedToken) ||
    empty($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $submittedToken)
) {
    backToOrder(
        $orderId,
        "Security Token সঠিক নয়। পেজ Refresh করে আবার চেষ্টা করুন।"
    );
}

// Update Booking Status Safely
function updateBookingStatus($conn, $orderId, $status)
{
    $allowedStatuses = ['unknown', 'failed'];

    if (!in_array($status, $allowedStatuses, true)) {
        return false;
    }

    $stmt = $conn->prepare("
        UPDATE orders
        SET steadfast_booking_status = ?
        WHERE id = ?
          AND steadfast_booking_status = 'booking'
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("si", $status, $orderId);
    $stmt->execute();

    $updated = ($stmt->affected_rows === 1);

    $stmt->close();

    return $updated;
}

// Fetch Order
$stmt = $conn->prepare("
    SELECT *
    FROM orders
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    backToOrder($orderId, "Database Query তৈরি করা যায়নি।");
}

$stmt->bind_param("i", $orderId);
$stmt->execute();

$order = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$order) {
    backToOrder($orderId, "অর্ডার পাওয়া যায়নি।");
}

// Already Booked Check
if (
    !empty($order['steadfast_consignment_id']) ||
    ($order['steadfast_booking_status'] ?? '') === 'booked'
) {
    backToOrder(
        $orderId,
        "এই অর্ডারের পার্সেল আগে থেকেই বুক করা হয়েছে।"
    );
}

// Booking in Progress
if (($order['steadfast_booking_status'] ?? '') === 'booking') {
    backToOrder(
        $orderId,
        "এই অর্ডারের Booking Request ইতিমধ্যে চলছে।"
    );
}

// Unknown Result - Never Retry Automatically
if (($order['steadfast_booking_status'] ?? '') === 'unknown') {
    backToOrder(
        $orderId,
        "Booking-এর ফলাফল নিশ্চিত নয়। Merchant Portal যাচাই না করে আবার Booking করবেন না।"
    );
}

// Cancelled Order Check
if (($order['order_status'] ?? '') === 'cancelled') {
    backToOrder(
        $orderId,
        "বাতিল অর্ডার বুক করা যাবে না।"
    );
}

// Validate Phone Number
$phone = preg_replace('/\D/', '', $order['phone'] ?? '');

if (!preg_match('/^01[0-9]{9}$/', $phone)) {
    backToOrder(
        $orderId,
        "মোবাইল নম্বর ১১ সংখ্যার হতে হবে এবং 01 দিয়ে শুরু হতে হবে।"
    );
}

// Prepare Address
$address = trim(
    ($order['address'] ?? '') . ", " .
    ($order['area'] ?? '')
);

if ($address === '' || strlen($address) > 490) {
    backToOrder(
        $orderId,
        "ঠিকানা খালি অথবা অনেক বড়। ঠিকানা যাচাই করুন।"
    );
}

// Validate Customer Name
$customerName = trim($order['customer_name'] ?? '');

if ($customerName === '') {
    backToOrder(
        $orderId,
        "কাস্টমারের নাম পাওয়া যায়নি।"
    );
}

// Validate Invoice Number
$invoice = trim($order['order_number'] ?? '');

if ($invoice === '') {
    backToOrder(
        $orderId,
        "অর্ডার নম্বর পাওয়া যায়নি।"
    );
}

// Fetch Order Items
$stmt = $conn->prepare("
    SELECT product_name, quantity
    FROM order_items
    WHERE order_id = ?
");

if (!$stmt) {
    backToOrder(
        $orderId,
        "Order Items Query তৈরি করা যায়নি।"
    );
}

$stmt->bind_param("i", $orderId);
$stmt->execute();

$itemsResult = $stmt->get_result();

$itemNames = [];
$totalQuantity = 0;

while ($item = $itemsResult->fetch_assoc()) {

    $quantity = (int)$item['quantity'];

    if ($quantity <= 0) {
        continue;
    }

    $productName = trim($item['product_name'] ?? '');

    if ($productName === '') {
        continue;
    }

    $itemNames[] = $productName;
    $totalQuantity += $quantity;
}

$stmt->close();

if (empty($itemNames) || $totalQuantity <= 0) {
    backToOrder(
        $orderId,
        "অর্ডারে বৈধ কোনো পণ্য পাওয়া যায়নি।"
    );
}

// Calculate COD Amount
$paymentMethod = strtolower(
    trim($order['payment_method'] ?? 'cod')
);

$totalAmount = (float)($order['total_amount'] ?? 0);

if (!is_finite($totalAmount) || $totalAmount < 0) {
    backToOrder(
        $orderId,
        "অর্ডারের Total Amount সঠিক নয়।"
    );
}

$codAmount = ($paymentMethod === 'cod')
    ? $totalAmount
    : 0;

// Prepare API Payload
$payload = [
    'invoice' => $invoice,
    'recipient_name' => $customerName,
    'recipient_phone' => $phone,
    'recipient_address' => $address,
    'cod_amount' => $codAmount,
    'note' => '',
    'delivery_type' => 0
];

// Encode JSON Before Booking Lock
$jsonPayload = json_encode(
    $payload,
    JSON_UNESCAPED_UNICODE
);

if ($jsonPayload === false) {
    backToOrder(
        $orderId,
        "Booking Data তৈরি করা যায়নি।"
    );
}

// Load Steadfast Configuration Before Lock
$configFile = "../config/steadfast.php";

if (!file_exists($configFile)) {
    backToOrder(
        $orderId,
        "Steadfast Configuration File পাওয়া যায়নি।"
    );
}

$config = require $configFile;

if (
    !is_array($config) ||
    empty($config['base_url']) ||
    empty($config['api_key']) ||
    empty($config['secret_key'])
) {
    backToOrder(
        $orderId,
        "Steadfast Configuration সঠিক নয়।"
    );
}

$url = rtrim($config['base_url'], '/') . '/create_order';

// Initialize cURL Before Lock
$ch = curl_init($url);

if ($ch === false) {
    backToOrder(
        $orderId,
        "cURL চালু করা যায়নি। Booking Request পাঠানো হয়নি।"
    );
}

// Atomic Booking Lock
try {

    $conn->begin_transaction();

    $stmt = $conn->prepare("
        SELECT
            steadfast_consignment_id,
            steadfast_booking_status,
            order_status
        FROM orders
        WHERE id = ?
        FOR UPDATE
    ");

    if (!$stmt) {
        throw new Exception("Database Lock Query Failed");
    }

    $stmt->bind_param("i", $orderId);
    $stmt->execute();

    $lockedOrder = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (!$lockedOrder) {
        throw new Exception("Order Not Found");
    }

    $currentStatus = $lockedOrder['steadfast_booking_status']
        ?? 'not_booked';

    // Already Booked
    if (
        !empty($lockedOrder['steadfast_consignment_id']) ||
        $currentStatus === 'booked'
    ) {
        throw new Exception("Already Booked");
    }

    // Only not_booked and manually verified failed can proceed
    if (
        $currentStatus !== 'not_booked' &&
        $currentStatus !== 'failed'
    ) {
        throw new Exception(
            "Order Not Ready: " . $currentStatus
        );
    }

    // Cancelled Order
    if ($lockedOrder['order_status'] === 'cancelled') {
        throw new Exception("Cancelled Order");
    }

    // Claim Booking Lock
    $stmt = $conn->prepare("
        UPDATE orders
        SET steadfast_booking_status = 'booking'
        WHERE id = ?
          AND steadfast_consignment_id IS NULL
          AND steadfast_booking_status IN ('not_booked', 'failed')
    ");

    if (!$stmt) {
        throw new Exception("Booking Lock Query Failed");
    }

    $stmt->bind_param("i", $orderId);
    $stmt->execute();

    $claimed = ($stmt->affected_rows === 1);

    $stmt->close();

    if (!$claimed) {
        throw new Exception("Booking Lock Not Acquired");
    }

    $conn->commit();

} catch (Throwable $e) {

    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
    }

    curl_close($ch);

    $error = $e->getMessage();

    if ($error === "Already Booked") {
        backToOrder(
            $orderId,
            "এই অর্ডার ইতিমধ্যে বুক করা হয়েছে।"
        );
    }

    if ($error === "Cancelled Order") {
        backToOrder(
            $orderId,
            "বাতিল অর্ডার বুক করা যাবে না।"
        );
    }

    if (strpos($error, "Order Not Ready:") === 0) {
        backToOrder(
            $orderId,
            "এই অর্ডার এখন Booking-এর জন্য প্রস্তুত নয়। Status: " .
            substr($error, strlen("Order Not Ready:"))
        );
    }

    backToOrder(
        $orderId,
        "Database Booking Lock নেওয়া যায়নি। Booking Request পাঠানো হয়নি।"
    );
}

// Send Booking Request
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $jsonPayload,
    CURLOPT_HTTPHEADER => [
        'Api-Key: ' . $config['api_key'],
        'Secret-Key: ' . $config['secret_key'],
        'Content-Type: application/json'
    ],
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);

$httpCode = (int)curl_getinfo(
    $ch,
    CURLINFO_HTTP_CODE
);

$curlError = curl_error($ch);

curl_close($ch);

// Connection Error - Result Unknown
if ($response === false) {

    updateBookingStatus(
        $conn,
        $orderId,
        'unknown'
    );

    backToOrder(
        $orderId,
        "Steadfast-এর উত্তর পাওয়া যায়নি। Booking হয়েছে কি না Merchant Portal-এ যাচাই করুন। Error: " .
        $curlError
    );
}

// Decode API Response
$result = json_decode($response, true);

// Invalid JSON - Result Unknown
if (!is_array($result)) {

    updateBookingStatus(
        $conn,
        $orderId,
        'unknown'
    );

    backToOrder(
        $orderId,
        "Steadfast-এর Response বোঝা যায়নি। Merchant Portal যাচাই করুন। HTTP: " .
        $httpCode
    );
}

// Read Consignment Data
$consignment = $result['consignment'] ?? [];

if (!is_array($consignment)) {
    $consignment = [];
}

// Confirm Successful Booking
$bookingSuccessful = (
    $httpCode === 200 &&
    isset($result['status']) &&
    (int)$result['status'] === 200 &&
    !empty($consignment['consignment_id'])
);

// Successful Booking
if ($bookingSuccessful) {

    $consignmentId = (string)$consignment['consignment_id'];

    $trackingCode = (string)(
        $consignment['tracking_code'] ?? ''
    );

    $trackingUrl = (string)(
        $consignment['tracking_link'] ?? ''
    );

    $courierStatus = (string)(
        $consignment['status'] ?? 'in_review'
    );

    try {

        $conn->begin_transaction();

        $stmt = $conn->prepare("
            UPDATE orders
            SET
                delivery_partner = 'Steadfast',
                tracking_id = ?,
                steadfast_consignment_id = ?,
                steadfast_tracking_code = ?,
                steadfast_tracking_url = ?,
                steadfast_status = ?,
                steadfast_booking_status = 'booked',
                steadfast_booked_at = NOW()
            WHERE id = ?
              AND steadfast_booking_status = 'booking'
              AND steadfast_consignment_id IS NULL
        ");

        if (!$stmt) {
            throw new Exception("Booking Save Query Failed");
        }

        $stmt->bind_param(
            "sssssi",
            $trackingCode,
            $consignmentId,
            $trackingCode,
            $trackingUrl,
            $courierStatus,
            $orderId
        );

        $stmt->execute();

        $saved = ($stmt->affected_rows === 1);

        $stmt->close();

        if (!$saved) {
            throw new Exception("Booking Save Failed");
        }

        $conn->commit();

        backToOrder(
            $orderId,
            "Steadfast Parcel সফলভাবে বুক হয়েছে। Tracking Code: " .
            $trackingCode
        );

    } catch (Throwable $e) {

        try {
            $conn->rollback();
        } catch (Throwable $ignored) {
        }

        // Remote Booking Succeeded But Local Save Failed
        updateBookingStatus(
            $conn,
            $orderId,
            'unknown'
        );

        backToOrder(
            $orderId,
            "Steadfast-এ Booking সফল হয়েছে, কিন্তু Database-এ Save হয়নি। Merchant Portal যাচাই করুন। আবার Booking করবেন না।"
        );
    }
}

// API Response Not Confirmed - Mark Unknown
updateBookingStatus(
    $conn,
    $orderId,
    'unknown'
);

$errorMessage = $result['message']
    ?? 'Unknown API Error';

backToOrder(
    $orderId,
    "Booking-এর ফলাফল নিশ্চিত নয়। HTTP: " .
    $httpCode . " | " . $errorMessage .
    " | Merchant Portal যাচাই না করে Retry করবেন না।"
);
