
<?php

// This script must run through Windows CLI only.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Access Denied");
}

require_once __DIR__ . "/../config/database.php";

$config = require __DIR__ . "/../config/steadfast.php";

// Validate Configuration
if (
    !is_array($config) ||
    empty($config['base_url']) ||
    empty($config['api_key']) ||
    empty($config['secret_key'])
) {
    exit("Steadfast configuration is invalid.\n");
}

// Steadfast to Website Status Mapping
$statusMapping = [
    'in_review' => 'processing',
    'pending' => 'processing',
    'hold' => 'processing',

    'in_transit' => 'shipped',
    'partial_delivered' => 'shipped',

    'delivered' => 'delivered',
    'cancelled' => 'cancelled'
];

// Fetch Successfully Booked Parcels
$stmt = $conn->prepare("
    SELECT id, steadfast_consignment_id
    FROM orders
    WHERE steadfast_booking_status = 'booked'
      AND steadfast_consignment_id IS NOT NULL
      AND steadfast_consignment_id != ''
");

if (!$stmt) {
    exit("Database query failed.\n");
}

$stmt->execute();

$result = $stmt->get_result();

$orders = [];

while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

$stmt->close();

// Counters
$total = count($orders);
$updated = 0;
$failed = 0;
$unknown = 0;

echo "====================================\n";
echo "BrandNest Steadfast Automatic Sync\n";
echo "====================================\n";

echo "Total Booked Parcels: {$total}\n\n";

// Process Each Order
foreach ($orders as $order) {

    $orderId = (int)$order['id'];
    $consignmentId = (string)$order['steadfast_consignment_id'];

    echo "Processing Order #{$orderId}...\n";

    // Prepare API URL
    $url = rtrim($config['base_url'], '/') .
        '/status_by_cid/' . rawurlencode($consignmentId);

    $ch = curl_init($url);

    if ($ch === false) {
        $failed++;
        echo "Order #{$orderId}: cURL initialization failed\n\n";
        continue;
    }

    // Send API Request
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

    $httpCode = (int)curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    $curlError = curl_error($ch);

    curl_close($ch);

    // Connection Error
    if ($response === false) {
        $failed++;

        echo "Order #{$orderId}: Connection failed - {$curlError}\n\n";
        continue;
    }

    // Decode API Response
    $data = json_decode($response, true);

    // Validate API Response
    if (
        $httpCode !== 200 ||
        !is_array($data) ||
        (int)($data['status'] ?? 0) !== 200 ||
        !isset($data['delivery_status']) ||
        !is_string($data['delivery_status']) ||
        trim($data['delivery_status']) === ''
    ) {
        $failed++;

        echo "Order #{$orderId}: Invalid API response\n\n";
        continue;
    }

    // Get Delivery Status
    $deliveryStatus = strtolower(
        trim($data['delivery_status'])
    );

    // Find Website Status
    $newOrderStatus = $statusMapping[$deliveryStatus] ?? null;

    try {

        // Start Transaction
        $conn->begin_transaction();

        // Lock Order Before Updating
        $lockStmt = $conn->prepare("
            SELECT steadfast_booking_status,
                   steadfast_consignment_id
            FROM orders
            WHERE id = ?
            FOR UPDATE
        ");

        if (!$lockStmt) {
            throw new Exception("Order lock query failed.");
        }

        $lockStmt->bind_param("i", $orderId);
        $lockStmt->execute();

        $lockedOrder = $lockStmt->get_result()->fetch_assoc();

        $lockStmt->close();

        // Verify Current Booking
        if (
            !$lockedOrder ||
            $lockedOrder['steadfast_booking_status'] !== 'booked' ||
            $lockedOrder['steadfast_consignment_id'] !== $consignmentId
        ) {
            throw new Exception(
                "Booking information changed or parcel is no longer booked."
            );
        }

        if ($newOrderStatus !== null) {

            // Update Both Courier and Website Status
            $update = $conn->prepare("
                UPDATE orders
                SET steadfast_status = ?,
                    order_status = ?
                WHERE id = ?
                  AND steadfast_booking_status = 'booked'
                  AND steadfast_consignment_id = ?
            ");

            if (!$update) {
                throw new Exception("Database update preparation failed.");
            }

            $update->bind_param(
                "ssis",
                $deliveryStatus,
                $newOrderStatus,
                $orderId,
                $consignmentId
            );

        } else {

            // Unknown Status:
            // Save Courier Status Only
            $update = $conn->prepare("
                UPDATE orders
                SET steadfast_status = ?
                WHERE id = ?
                  AND steadfast_booking_status = 'booked'
                  AND steadfast_consignment_id = ?
            ");

            if (!$update) {
                throw new Exception("Database update preparation failed.");
            }

            $update->bind_param(
                "sis",
                $deliveryStatus,
                $orderId,
                $consignmentId
            );
        }

        if (!$update->execute()) {
            throw new Exception("Database update failed.");
        }

        $update->close();

        // Commit Transaction
        $conn->commit();

        $updated++;

        echo "Courier Status: {$deliveryStatus}\n";

        if ($newOrderStatus !== null) {

            echo "Website Order Status: {$newOrderStatus}\n";

        } else {

            $unknown++;

            echo "Website Status: Unchanged (Unknown Mapping)\n";
        }

        echo "Order #{$orderId}: Sync Successful\n\n";

    } catch (Throwable $e) {

        // Rollback Failed Transaction
        try {
            $conn->rollback();
        } catch (Throwable $ignored) {
        }

        $failed++;

        echo "Order #{$orderId}: Sync Failed - ";
        echo $e->getMessage() . "\n\n";
    }
}

// Final Report
echo "====================================\n";
echo "SYNC COMPLETED\n";
echo "====================================\n";

echo "Total Parcels: {$total}\n";
echo "Successfully Processed: {$updated}\n";
echo "Unknown Status Mapping: {$unknown}\n";
echo "Failed: {$failed}\n";

echo "====================================\n";

$conn->close();

