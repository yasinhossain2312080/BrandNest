
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
        "Location: view_order.php?id=" . (int)$id .
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
    SELECT
        steadfast_consignment_id,
        steadfast_booking_status,
        order_status
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

// Check Successful Booking
$consignmentId = $order['steadfast_consignment_id'];

if (
    ($order['steadfast_booking_status'] ?? '') !== 'booked' ||
    empty($consignmentId)
) {
    backToOrder(
        $orderId,
        "এই অর্ডারের সফল Steadfast Booking পাওয়া যায়নি।"
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
    !isset($result['delivery_status']) ||
    !is_string($result['delivery_status']) ||
    trim($result['delivery_status']) === ''
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
$deliveryStatus = strtolower(trim($result['delivery_status']));

// Map Steadfast Status to Website Order Status
$statusMapping = [
    'in_review' => 'processing',
    'pending' => 'processing',
    'hold' => 'processing',

    'in_transit' => 'shipped',
    'partial_delivered' => 'shipped',

    'delivered' => 'delivered',
    'cancelled' => 'cancelled'
];

// Find Matching Website Status
$newOrderStatus = $statusMapping[$deliveryStatus] ?? null;

// Update Database
try {

    $conn->begin_transaction();

    // Lock Order Before Updating
    $lockStmt = $conn->prepare("
        SELECT steadfast_booking_status, steadfast_consignment_id
        FROM orders
        WHERE id = ?
        FOR UPDATE
    ");

    if (!$lockStmt) {
        throw new Exception("Order Lock Query তৈরি করা যায়নি।");
    }

    $lockStmt->bind_param("i", $orderId);
    $lockStmt->execute();

    $lockedOrder = $lockStmt->get_result()->fetch_assoc();
    $lockStmt->close();

    // Verify Booking Again
    if (
        !$lockedOrder ||
        $lockedOrder['steadfast_booking_status'] !== 'booked' ||
        $lockedOrder['steadfast_consignment_id'] !== $consignmentId
    ) {
        throw new Exception(
            "অর্ডারের Booking তথ্য পরিবর্তিত হয়েছে। আবার চেষ্টা করুন।"
        );
    }

    if ($newOrderStatus !== null) {

        // Update Courier and Website Status Together
        $updateStmt = $conn->prepare("
            UPDATE orders
            SET steadfast_status = ?,
                order_status = ?
            WHERE id = ?
              AND steadfast_consignment_id = ?
        ");

        if (!$updateStmt) {
            throw new Exception("Database Update Query তৈরি করা যায়নি।");
        }

        $updateStmt->bind_param(
            "ssis",
            $deliveryStatus,
            $newOrderStatus,
            $orderId,
            $consignmentId
        );

    } else {

        // Unknown Courier Status:
        // Save Courier Status Only
        $updateStmt = $conn->prepare("
            UPDATE orders
            SET steadfast_status = ?
            WHERE id = ?
              AND steadfast_consignment_id = ?
        ");

        if (!$updateStmt) {
            throw new Exception("Database Update Query তৈরি করা যায়নি।");
        }

        $updateStmt->bind_param(
            "sis",
            $deliveryStatus,
            $orderId,
            $consignmentId
        );
    }

    if (!$updateStmt->execute()) {
        throw new Exception("Database-এ Status Save করা যায়নি।");
    }

    $updateStmt->close();

    $conn->commit();

    if ($newOrderStatus !== null) {

        backToOrder(
            $orderId,
            "Steadfast Status আপডেট হয়েছে: " .
            $deliveryStatus .
            " | Website Order Status: " .
            $newOrderStatus
        );

    } else {

        backToOrder(
            $orderId,
            "Courier Status Save হয়েছে: " .
            $deliveryStatus .
            " | নতুন Status Mapping পাওয়া যায়নি, তাই Website Status পরিবর্তন করা হয়নি।"
        );

    }

} catch (Throwable $e) {

    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
    }

    backToOrder(
        $orderId,
        "Status Update ব্যর্থ: " . $e->getMessage()
    );
}
