
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

$orderId = (int)($_GET['id'] ?? 0);

if ($orderId <= 0) {
    die("Invalid Order ID.");
}

// Fetch Order
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");

if (!$stmt) {
    die("Database Query Failed.");
}

$stmt->bind_param("i", $orderId);
$stmt->execute();

$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    die("Order not found.");
}

// Fetch Order Items
$stmt = $conn->prepare("
    SELECT product_name, quantity, price, subtotal
    FROM order_items
    WHERE order_id = ?
");

if (!$stmt) {
    die("Order Items Query Failed.");
}

$stmt->bind_param("i", $orderId);
$stmt->execute();

$result = $stmt->get_result();
$items = [];

while ($item = $result->fetch_assoc()) {
    $items[] = $item;
}

$stmt->close();

$orderDate = !empty($order['created_at'])
    ? date('d-m-Y, h:i A', strtotime($order['created_at']))
    : 'N/A';

$paymentMethod = $order['payment_method'] ?? 'COD';

// Steadfast Information
$bookingSuccessful =
    ($order['steadfast_booking_status'] ?? '') === 'booked'
    && !empty($order['steadfast_consignment_id']);

$consignmentId = $bookingSuccessful
    ? $order['steadfast_consignment_id']
    : '';

$trackingCode = $bookingSuccessful
    ? ($order['steadfast_tracking_code'] ?? '')
    : '';

?>

<!DOCTYPE html>
<html lang="bn">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>ইনভয়েস - <?php echo e($order['order_number']); ?> | BrandNest</title>

<!-- Barcode Library -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 20px;
    background: #eeeeee;
    color: #222;
    font-family: Arial, "Noto Sans Bengali", sans-serif;
}

.invoice {
    width: 100%;
    max-width: 560px;
    margin: auto;
    padding: 22px;
    background: #fff;
    border: 1px solid #ddd;
}

/* HEADER */

.invoice-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding-bottom: 13px;
    border-bottom: 2px solid #c9a45c;
}

.brand-area {
    width: 48%;
    text-align: left;
}

.brand-logo {
    width: 150px;
    height: 90px;
    object-fit: contain;
    object-position: left center;
    display: block;
}

.brand-name {
    margin-top: 3px;
    font-size: 19px;
    font-weight: bold;
    color: #172b4d;
    letter-spacing: 1px;
}

.invoice-title {
    width: 52%;
    text-align: right;
}

.invoice-title h2 {
    margin: 0 0 8px;
    color: #c9a45c;
    font-size: 23px;
    letter-spacing: 1px;
}

.invoice-title p {
    margin: 4px 0;
    font-size: 10px;
    overflow-wrap: anywhere;
}

/* STEADFAST INFORMATION */

.courier-info {
    margin-top: 14px;
    padding: 10px 12px;
    border: 1px solid #e5e5e5;
    border-left: 4px solid #c9a45c;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}

.courier-id {
    font-size: 11px;
    color: #172b4d;
    line-height: 1.7;
    overflow-wrap: anywhere;
}

.courier-id strong {
    font-size: 12px;
}

.barcode-area {
    text-align: center;
    min-width: 125px;
}

.barcode-area svg {
    width: 145px;
    max-width: 100%;
    height: 48px;
    display: block;
    margin: auto;
}

/* CUSTOMER */

.section {
    margin-top: 17px;
}

.section-title {
    font-size: 13px;
    color: #172b4d;
    border-bottom: 1px solid #ddd;
    padding-bottom: 6px;
    margin: 0 0 10px;
}

.customer-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 9px 13px;
}

.info-item {
    font-size: 10px;
    line-height: 1.6;
    overflow-wrap: anywhere;
}

.info-item strong {
    color: #555;
}

/* PRODUCT TABLE */

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 9px;
    table-layout: fixed;
}

th {
    background: #172b4d;
    color: #fff;
    padding: 8px 5px;
    font-size: 9px;
    text-align: left;
}

td {
    padding: 8px 5px;
    border-bottom: 1px solid #ddd;
    font-size: 9px;
    overflow-wrap: anywhere;
    line-height: 1.5;
}

.center {
    text-align: center;
}

.right {
    text-align: right;
}

/* TOTAL */

.totals {
    width: 235px;
    max-width: 100%;
    margin-left: auto;
    margin-top: 15px;
}

.total-line {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    padding: 6px 0;
    font-size: 10px;
    border-bottom: 1px solid #eee;
}

.grand-total {
    font-size: 14px;
    font-weight: bold;
    color: #172b4d;
    border-top: 2px solid #c9a45c;
    margin-top: 6px;
    padding-top: 10px;
}

/* PAYMENT */

.payment-info {
    margin-top: 15px;
    padding: 10px 12px;
    background: #f7f5ef;
    border-left: 4px solid #c9a45c;
    font-size: 10px;
}

/* FOOTER */

.footer {
    margin-top: 22px;
    padding-top: 12px;
    border-top: 1px solid #ddd;
    text-align: center;
    color: #777;
    font-size: 9px;
    line-height: 1.8;
}

.footer strong {
    color: #172b4d;
}

.brand-contact {
    margin-top: 7px;
    padding-top: 5px;
    border-top: 1px solid #eee;
    color: #222;
    font-size: 10px;
    line-height: 1.8;
}

.brand-contact-row {
    display: flex;
    justify-content: center;
    align-items: center;
    flex-wrap: wrap;
    gap: 5px 12px;
    margin-top: 5px;
}

.contact-divider {
    color: #bbb;
}

/* BUTTONS */

.print-actions {
    max-width: 560px;
    margin: 0 auto 12px;
    text-align: right;
}

.print-btn,
.back-btn {
    display: inline-block;
    padding: 9px 15px;
    border: none;
    border-radius: 4px;
    background: #c9a45c;
    color: #111;
    font-weight: bold;
    text-decoration: none;
    cursor: pointer;
    font-size: 12px;
    margin-left: 5px;
}

.back-btn {
    background: #172b4d;
    color: white;
}

/* A5 PRINT */

@page {
    size: A5 portrait;
    margin: 7mm;
}

@media print {

    html, body {
        width: auto;
        margin: 0;
        padding: 0;
        background: #fff;
    }

    .invoice {
        width: 100%;
        max-width: none;
        margin: 0;
        padding: 0;
        border: none;
    }

    .print-actions {
        display: none !important;
    }

    .invoice-header,
    th,
    .payment-info,
    .courier-info {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    tr,
    .section,
    .totals,
    .payment-info,
    .courier-info,
    .footer {
        break-inside: avoid;
        page-break-inside: avoid;
    }
}

@media screen and (max-width: 600px) {

    body {
        padding: 8px;
    }

    .invoice {
        padding: 14px;
    }

    .brand-logo {
        width: 105px;
        height: 68px;
    }

    .customer-grid {
        grid-template-columns: 1fr 1fr;
    }

    .print-actions {
        text-align: center;
    }

    .courier-info {
        flex-wrap: wrap;
    }
}

</style>

</head>

<body>

<div class="print-actions">

    <a
        href="view_order.php?id=<?php echo (int)$orderId; ?>"
        class="back-btn"
    >
        ফিরে যান
    </a>

    <button
        onclick="window.print()"
        class="print-btn"
    >
        ইনভয়েস প্রিন্ট করুন
    </button>

</div>


<div class="invoice">

    <!-- BRANDNEST HEADER -->

    <div class="invoice-header">

        <div class="brand-area">

            <img
                src="../uploads/brandnest-logo.png"
                alt="BrandNest Logo"
                class="brand-logo"
            >

            <div class="brand-name">BrandNest</div>

        </div>

        <div class="invoice-title">

            <h2>ইনভয়েস</h2>

            <p>
                <strong>অর্ডার নং:</strong>
                <?php echo e($order['order_number']); ?>
            </p>

            <p>
                <strong>তারিখ:</strong>
                <?php echo e($orderDate); ?>
            </p>

        </div>

    </div>


    <!-- STEADFAST COURIER INFORMATION -->

    <?php if ($bookingSuccessful): ?>

        <div class="courier-info">

            <div class="courier-id">

                <strong>Steadfast Consignment ID:</strong>

                <br>

                <?php echo e($consignmentId); ?>

            </div>

            <?php if (!empty($trackingCode)): ?>

                <div class="barcode-area">

                    <svg id="trackingBarcode"></svg>

                </div>

            <?php endif; ?>

        </div>

    <?php endif; ?>


    <!-- CUSTOMER INFORMATION -->

    <div class="section">

        <h3 class="section-title">কাস্টমারের তথ্য</h3>

        <div class="customer-grid">

            <div class="info-item">
                <strong>নাম:</strong><br>
                <?php echo e($order['customer_name']); ?>
            </div>

            <div class="info-item">
                <strong>মোবাইল:</strong><br>
                <?php echo e($order['phone']); ?>
            </div>

            <div class="info-item">
                <strong>এলাকা:</strong><br>
                <?php echo e($order['area']); ?>
            </div>

            <div class="info-item">
                <strong>ঠিকানা:</strong><br>
                <?php echo nl2br(e($order['address'])); ?>
            </div>

        </div>

    </div>


    <!-- PRODUCT DETAILS -->

    <div class="section">

        <h3 class="section-title">পণ্যের বিবরণ</h3>

        <table>

            <thead>

                <tr>
                    <th style="width:8%">ক্রম</th>
                    <th style="width:34%">পণ্যের নাম</th>
                    <th style="width:14%" class="center">পরিমাণ</th>
                    <th style="width:22%" class="right">দাম</th>
                    <th style="width:22%" class="right">মোট</th>
                </tr>

            </thead>

            <tbody>

                <?php if (empty($items)): ?>

                    <tr>
                        <td colspan="5" class="center">
                            কোনো পণ্য পাওয়া যায়নি।
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($items as $index => $item): ?>

                        <tr>

                            <td><?php echo $index + 1; ?></td>

                            <td>
                                <?php echo e($item['product_name']); ?>
                            </td>

                            <td class="center">
                                <?php echo (int)$item['quantity']; ?>
                            </td>

                            <td class="right">
                                ৳<?php echo number_format((float)$item['price'], 0); ?>
                            </td>

                            <td class="right">
                                ৳<?php echo number_format((float)$item['subtotal'], 0); ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>


    <!-- TOTAL AMOUNT -->

    <div class="totals">

        <div class="total-line">

            <span>পণ্যের মূল্য</span>

            <strong>
                ৳<?php echo number_format((float)$order['subtotal'], 0); ?>
            </strong>

        </div>

        <div class="total-line">

            <span>ডেলিভারি চার্জ</span>

            <strong>
                ৳<?php echo number_format((float)$order['delivery_charge'], 0); ?>
            </strong>

        </div>

        <div class="total-line grand-total">

            <span>সর্বমোট</span>

            <span>
                ৳<?php echo number_format((float)$order['total_amount'], 0); ?>
            </span>

        </div>

    </div>


    <!-- PAYMENT -->

    <div class="payment-info">

        <strong>পেমেন্ট পদ্ধতি:</strong>
        <?php echo e($paymentMethod); ?>

    </div>


    <!-- BRANDNEST FOOTER -->

    <div class="footer">

        <strong>BrandNest-এর সাথে কেনাকাটা করার জন্য ধন্যবাদ!</strong>

        <br>

        আপনার আস্থা আমাদের অনুপ্রেরণা।

        <br>

        <strong>BrandNest</strong>

        <div class="brand-contact">

            <div class="brand-contact-row">

                <strong>01860384618</strong>

                <span class="contact-divider">|</span>

                <strong>01924206648</strong>

            </div>

            <div>
                ঠিকানা: Mirpur-13, Plot-11/D, Dhaka-1216
            </div>

        </div>

    </div>

</div>


<?php if ($bookingSuccessful && !empty($trackingCode)): ?>

<script>

document.addEventListener("DOMContentLoaded", function () {

    if (typeof JsBarcode !== "undefined") {

        JsBarcode("#trackingBarcode", <?php
            echo json_encode(
                $trackingCode,
                JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
            );
        ?>, {
            format: "CODE128",
            displayValue: false,
            height: 42,
            width: 1.5,
            margin: 2,
            background: "#ffffff",
            lineColor: "#000000"
        });

    }

});

</script>

<?php endif; ?>

</body>
</html>
