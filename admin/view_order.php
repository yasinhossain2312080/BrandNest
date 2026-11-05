
<?php
session_start();
require_once "../config/database.php";

// Admin Login Check
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// HTML Escape
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Order Status Translation
function statusBangla($status) {
    $statuses = [
        'pending' => 'নতুন অর্ডার',
        'confirmed' => 'নিশ্চিত',
        'processing' => 'প্রস্তুত হচ্ছে',
        'shipped' => 'কুরিয়ারে পাঠানো হয়েছে',
        'delivered' => 'ডেলিভারি সম্পন্ন',
        'cancelled' => 'বাতিল'
    ];

    return $statuses[$status] ?? $status;
}

// Courier Status Translation
function courierStatusBangla($status) {
    $statuses = [
        'in_review' => 'পর্যালোচনায় রয়েছে',
        'pending' => 'অপেক্ষমাণ',
        'delivered' => 'ডেলিভারি সম্পন্ন',
        'partial_delivered' => 'আংশিক ডেলিভারি',
        'cancelled' => 'বাতিল',
        'hold' => 'স্থগিত',
        'in_transit' => 'পথে রয়েছে'
    ];

    return $statuses[$status] ?? ($status ?: 'এখনো পাওয়া যায়নি');
}

// Order ID
$orderId = (int)($_GET['id'] ?? 0);

if ($orderId <= 0) {
    die("সঠিক Order ID পাওয়া যায়নি।");
}

$error = "";
$success = "";

// Courier Message
$courierMessage = trim($_GET['courier_message'] ?? '');

if ($courierMessage !== '') {
    $success = $courierMessage;
}

// Fetch Order
$stmt = $conn->prepare("
    SELECT *
    FROM orders
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Database Query তৈরি করা যায়নি।");
}

$stmt->bind_param("i", $orderId);
$stmt->execute();

$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    die("অর্ডারটি পাওয়া যায়নি!");
}

// Fetch Order Items
$stmt = $conn->prepare("
    SELECT *
    FROM order_items
    WHERE order_id = ?
");

if (!$stmt) {
    die("Order Items Query তৈরি করা যায়নি।");
}

$stmt->bind_param("i", $orderId);
$stmt->execute();

$items = $stmt->get_result();

// Booking Status
$bookingStatus = $order['steadfast_booking_status'] ?? 'not_booked';

$isBooked = (
    $bookingStatus === 'booked' &&
    !empty($order['steadfast_consignment_id'])
);

// Tracking URL Validation
$trackingUrl = trim($order['steadfast_tracking_url'] ?? '');

$trackingUrlIsValid = (
    filter_var($trackingUrl, FILTER_VALIDATE_URL) &&
    in_array(
        strtolower((string)parse_url($trackingUrl, PHP_URL_SCHEME)),
        ['http', 'https'],
        true
    )
);

?>

<!DOCTYPE html>
<html lang="bn">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>অর্ডারের বিস্তারিত | BrandNest</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>

    <style>

        .order-details-page {
            max-width: 1100px;
            margin: 35px auto;
            padding: 20px;
            color: #eee;
        }

        .details-card {
            background: #191919;
            border: 1px solid #40372a;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
        }

        .details-card h2 {
            color: #c9a45c;
            margin-bottom: 20px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        .details-grid p {
            line-height: 1.8;
            overflow-wrap: anywhere;
        }

        .details-grid strong {
            color: #c9a45c;
        }

        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .details-table th,
        .details-table td {
            padding: 13px;
            border-bottom: 1px solid #444;
            text-align: left;
        }

        .details-table th {
            color: #c9a45c;
        }

        .action-btn,
        .steadfast-btn,
        .tracking-refresh-btn,
        .history-btn {
            display: inline-block;
            padding: 12px 22px;
            margin-top: 20px;
            background: #c9a45c;
            color: #111;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            cursor: pointer;
        }

        .steadfast-btn {
            background: #d8a94d;
        }

        .tracking-refresh-btn {
            background: #75b99a;
        }

        .history-btn {
            background: #8e83c9;
        }

        .success-message {
            color: #8ee0a0;
            margin-bottom: 15px;
            line-height: 1.7;
        }

        .error-message {
            color: #ff8585;
            margin-bottom: 15px;
            line-height: 1.7;
        }

        .total-row {
            color: #c9a45c;
            font-weight: bold;
            font-size: 20px;
            text-align: right;
            margin-top: 20px;
        }

        .tracking-info {
            background: #242424;
            border: 1px solid #51442f;
            border-radius: 8px;
            padding: 18px;
            margin-top: 18px;
            line-height: 2;
            overflow-wrap: anywhere;
        }

        .tracking-info strong {
            color: #c9a45c;
        }

        .tracking-link {
            color: #d9b66e;
            text-decoration: underline;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 5px;
            background: #343434;
            border: 1px solid #c9a45c;
            color: #e5c47e;
            font-weight: bold;
        }

        .barcode-box {
            margin-top: 15px;
            padding: 15px;
            background: #fff;
            border-radius: 6px;
            text-align: center;
            max-width: 350px;
        }

        .barcode-box svg {
            width: 100%;
            max-width: 300px;
            height: 75px;
        }

        .no-print {
            margin-bottom: 20px;
        }

        @media (max-width: 600px) {

            .details-grid {
                grid-template-columns: 1fr;
            }

            .details-card {
                padding: 17px;
            }

            .total-row {
                font-size: 16px;
            }

        }

        @media print {

            body {
                background: white !important;
                color: black !important;
            }

            .details-card {
                background: white !important;
                color: black !important;
                border: 1px solid #ccc;
            }

            .details-card h2,
            .details-grid strong,
            .details-table th,
            .total-row {
                color: black !important;
            }

            .no-print {
                display: none !important;
            }

        }

    </style>

</head>

<body>

<main class="order-details-page">

    <h1 style="color:#c9a45c; margin-bottom:25px;">
        অর্ডারের বিস্তারিত
    </h1>

    <?php if ($success): ?>
        <p class="success-message">
            <?php echo e($success); ?>
        </p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="error-message">
            <?php echo e($error); ?>
        </p>
    <?php endif; ?>


    <!-- CUSTOMER INFORMATION -->

    <section class="details-card">

        <h2>ক্রেতার তথ্য</h2>

        <div class="details-grid">

            <p>
                <strong>অর্ডার নম্বর:</strong><br>
                <?php echo e($order['order_number']); ?>
            </p>

            <p>
                <strong>ক্রেতার নাম:</strong><br>
                <?php echo e($order['customer_name']); ?>
            </p>

            <p>
                <strong>মোবাইল নম্বর:</strong><br>
                <?php echo e($order['phone']); ?>
            </p>

            <p>
                <strong>এলাকার নাম:</strong><br>
                <?php echo e($order['area']); ?>
            </p>

            <p>
                <strong>সম্পূর্ণ ঠিকানা:</strong><br>
                <?php echo nl2br(e($order['address'])); ?>
            </p>

            <p>
                <strong>অর্ডারের তারিখ:</strong><br>
                <?php
                echo !empty($order['created_at'])
                    ? date('d-m-Y h:i A', strtotime($order['created_at']))
                    : 'N/A';
                ?>
            </p>

        </div>

    </section>


    <!-- PRODUCT INFORMATION -->

    <section class="details-card">

        <h2>অর্ডার করা পণ্য</h2>

        <div style="overflow-x:auto;">

            <table class="details-table">

                <thead>

                    <tr>
                        <th>পণ্যের নাম</th>
                        <th>পরিমাণ</th>
                        <th>একক মূল্য</th>
                        <th>মোট মূল্য</th>
                    </tr>

                </thead>

                <tbody>

                    <?php if ($items->num_rows > 0): ?>

                        <?php while ($item = $items->fetch_assoc()): ?>

                            <tr>

                                <td>
                                    <?php echo e($item['product_name']); ?>
                                </td>

                                <td>
                                    <?php echo (int)$item['quantity']; ?> টি
                                </td>

                                <td>
                                    ৳ <?php echo number_format((float)$item['price'], 2); ?>
                                </td>

                                <td>
                                    ৳ <?php echo number_format((float)$item['subtotal'], 2); ?>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="4">কোনো পণ্য পাওয়া যায়নি।</td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

        <div class="total-row">

            পণ্যের মোট মূল্য:
            ৳ <?php echo number_format((float)$order['subtotal'], 2); ?>

            <br><br>

            ডেলিভারি চার্জ:
            ৳ <?php echo number_format((float)$order['delivery_charge'], 2); ?>

            <br><br>

            সর্বমোট:
            ৳ <?php echo number_format((float)$order['total_amount'], 2); ?>

        </div>

        <p style="margin-top:20px;">
            <strong>পেমেন্ট পদ্ধতি:</strong>
            <?php echo e($order['payment_method']); ?>
        </p>

    </section>


    <!-- ORDER STATUS: READ ONLY -->

    <section class="details-card">

        <h2>অর্ডারের বর্তমান অবস্থা</h2>

        <p>
            <strong>Website Order Status:</strong>
            <span class="status-badge">
                <?php echo e(statusBangla($order['order_status'])); ?>
            </span>
        </p>

        <p style="color:#aaa; line-height:1.8;">
            অর্ডারের অবস্থা এখন আর এই পেজ থেকে হাতে পরিবর্তন করা যাবে না।
            Steadfast থেকে পাওয়া তথ্য অনুযায়ী Status Sync করা হবে।
        </p>

    </section>


    <!-- STEADFAST TRACKING -->

    <section class="details-card no-print">

        <h2>Steadfast Courier Booking & Tracking</h2>

        <?php if ($isBooked): ?>

            <p class="success-message">
                এই অর্ডারের পার্সেল Steadfast-এ বুক করা হয়েছে।
            </p>

            <div class="tracking-info">

                <p>
                    <strong>Booking Status:</strong><br>
                    <span class="status-badge">Booked</span>
                </p>

                <p>
                    <strong>Consignment ID:</strong><br>
                    <?php echo e($order['steadfast_consignment_id']); ?>
                </p>

                <p>
                    <strong>Tracking Code:</strong><br>
                    <?php echo e($order['steadfast_tracking_code'] ?? 'তথ্য পাওয়া যায়নি'); ?>
                </p>

                <p>
                    <strong>Courier Status:</strong><br>

                    <?php
                    echo e(
                        courierStatusBangla(
                            $order['steadfast_status'] ?? ''
                        )
                    );
                    ?>

                </p>

                <p>
                    <strong>Booking Date:</strong><br>

                    <?php
                    if (!empty($order['steadfast_booked_at'])) {
                        echo date(
                            'd-m-Y h:i A',
                            strtotime($order['steadfast_booked_at'])
                        );
                    } else {
                        echo 'তথ্য পাওয়া যায়নি';
                    }
                    ?>

                </p>

                <?php if ($trackingUrlIsValid): ?>

                    <p>
                        <strong>Tracking Link:</strong><br>

                        <a
                            class="tracking-link"
                            href="<?php echo e($trackingUrl); ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            পার্সেল ট্র্যাক করুন
                        </a>
                    </p>

                <?php endif; ?>

                <?php if (!empty($order['steadfast_tracking_code'])): ?>

                    <div class="barcode-box">

                        <svg id="trackingBarcode"></svg>

                    </div>

                <?php endif; ?>

            </div>


            <a
                href="steadfast_status.php?id=<?php echo (int)$order['id']; ?>"
                class="tracking-refresh-btn"
                onclick="return confirm('Steadfast থেকে সর্বশেষ Tracking Status আনতে চান?');"
            >
                Tracking Status Refresh করুন
            </a>

            <a
                href="steadfast_history.php?id=<?php echo (int)$order['id']; ?>"
                class="history-btn"
            >
                Tracking History দেখুন
            </a>


        <?php elseif ($order['order_status'] === 'cancelled'): ?>

            <p class="error-message">
                বাতিল অর্ডার Steadfast-এ বুক করা যাবে না।
            </p>


        <?php elseif ($bookingStatus === 'booking'): ?>

            <p class="success-message">
                এই অর্ডারের Booking Request প্রক্রিয়াধীন।
                অনুগ্রহ করে অপেক্ষা করুন।
            </p>

            <p class="error-message">
                এই অবস্থায় পুনরায় Booking করবেন না।
            </p>


        <?php elseif ($bookingStatus === 'unknown'): ?>

            <p class="error-message">
                Booking-এর ফলাফল নিশ্চিত নয়।
            </p>

            <div class="tracking-info">

                <strong>সতর্কতা:</strong><br>

                Steadfast Merchant Portal-এ এই Invoice যাচাই করুন।
                Parcel তৈরি হয়ে থাকলে নতুন করে Booking করবেন না।
                যাচাই সম্পন্ন না হওয়া পর্যন্ত Retry বন্ধ থাকবে।

            </div>


        <?php elseif ($bookingStatus === 'failed'): ?>

            <p class="error-message">
                আগের Booking প্রচেষ্টা ব্যর্থ হিসেবে চিহ্নিত হয়েছে।
            </p>

            <div class="tracking-info">

                Retry করার আগে Steadfast Merchant Portal-এ Invoice যাচাই করুন।
                নিশ্চিত হতে হবে যে আগের Request থেকে কোনো Parcel তৈরি হয়নি।

            </div>

            <form
                method="POST"
                action="book_steadfast.php"
                onsubmit="return confirm('Merchant Portal-এ যাচাই করে নিশ্চিত হয়েছেন যে Parcel তৈরি হয়নি? তাহলে পুনরায় Booking করবেন?');"
            >

                <input
                    type="hidden"
                    name="order_id"
                    value="<?php echo (int)$order['id']; ?>"
                >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo e($_SESSION['csrf_token'] ?? ''); ?>"
                >

                <button type="submit" class="steadfast-btn">
                    যাচাই শেষে পুনরায় Booking করুন
                </button>

            </form>


        <?php elseif ($bookingStatus === 'not_booked'): ?>

            <p>
                এই অর্ডারটি এখনো Steadfast-এ বুক করা হয়নি।
            </p>

            <form
                method="POST"
                action="book_steadfast.php"
                onsubmit="return confirm('আপনি কি এই অর্ডারটি Steadfast-এ বুক করতে চান?');"
            >

                <input
                    type="hidden"
                    name="order_id"
                    value="<?php echo (int)$order['id']; ?>"
                >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo e($_SESSION['csrf_token'] ?? ''); ?>"
                >

                <button type="submit" class="steadfast-btn">
                    Steadfast-এ পার্সেল বুক করুন
                </button>

            </form>


        <?php else: ?>

            <p class="error-message">
                Booking Status অজানা বা সঠিক নয়।
                Database যাচাই না করা পর্যন্ত Booking বন্ধ থাকবে।
            </p>

        <?php endif; ?>

    </section>


    <!-- PRINT AND BACK -->

    <div class="no-print">

        <a
            href="print_invoice.php?id=<?php echo (int)$order['id']; ?>"
            class="action-btn"
        >
            অর্ডার ইনভয়েস প্রিন্ট করুন
        </a>

        <a
            href="orders.php"
            class="action-btn"
        >
            অর্ডার তালিকায় ফিরে যান
        </a>

        <a
            href="dashboard.php"
            class="action-btn"
        >
            Dashboard
        </a>

    </div>

</main>


<?php if ($isBooked && !empty($order['steadfast_tracking_code'])): ?>

<script>

document.addEventListener("DOMContentLoaded", function () {

    if (typeof JsBarcode !== "undefined") {

        JsBarcode(
            "#trackingBarcode",
            <?php
            echo json_encode(
                $order['steadfast_tracking_code'],
                JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
            );
            ?>,
            {
                format: "CODE128",
                displayValue: true,
                height: 55,
                width: 1.5,
                margin: 5,
                background: "#ffffff",
                lineColor: "#000000"
            }
        );

    }

});

</script>

<?php endif; ?>

</body>
</html>
