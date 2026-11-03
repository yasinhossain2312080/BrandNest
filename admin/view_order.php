
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

    return $statuses[$status] ?? $status;
}

$orderId = (int)($_GET['id'] ?? $_POST['order_id'] ?? 0);

$error = "";
$success = "";

// Validate Order ID
if ($orderId <= 0) {
    die("সঠিক Order ID পাওয়া যায়নি।");
}

// Handle Manual Order Update
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['update_order'])
) {
    $newStatus = $_POST['order_status'] ?? '';
    $deliveryPartner = trim($_POST['delivery_partner'] ?? '');
    $trackingId = trim($_POST['tracking_id'] ?? '');

    $allowedStatuses = [
        'pending',
        'confirmed',
        'processing',
        'shipped',
        'delivered',
        'cancelled'
    ];

    if (!in_array($newStatus, $allowedStatuses, true)) {
        $error = "সঠিক অর্ডার স্ট্যাটাস নির্বাচন করুন।";
    } else {
        $stmt = $conn->prepare("
            UPDATE orders
            SET order_status = ?,
                delivery_partner = ?,
                tracking_id = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "sssi",
            $newStatus,
            $deliveryPartner,
            $trackingId,
            $orderId
        );

        if ($stmt->execute()) {
            $success = "অর্ডারের তথ্য সফলভাবে আপডেট হয়েছে।";
        } else {
            $error = "তথ্য আপডেট করা যায়নি। আবার চেষ্টা করুন।";
        }

        $stmt->close();
    }
}

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

$stmt->bind_param("i", $orderId);
$stmt->execute();

$items = $stmt->get_result();

// Booking Status
$bookingStatus = $order['steadfast_booking_status'] ?? 'not_booked';

$isBooked = (
    !empty($order['steadfast_consignment_id']) ||
    $bookingStatus === 'booked'
);

?>

<!DOCTYPE html>
<html lang="bn">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>অর্ডারের বিস্তারিত | BrandNest</title>

    <link rel="stylesheet" href="../assets/css/style.css">

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

        .details-form label {
            display: block;
            margin: 15px 0 8px;
        }

        .details-form input,
        .details-form select {
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            background: #242424;
            color: white;
            border: 1px solid #555;
            border-radius: 6px;
        }

        .details-form button,
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


    <!-- Customer Information -->

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
                <?php echo date('d-m-Y h:i A', strtotime($order['created_at'])); ?>
            </p>

        </div>

    </section>


    <!-- Product Information -->

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

                    <?php while ($item = $items->fetch_assoc()): ?>

                        <tr>
                            <td><?php echo e($item['product_name']); ?></td>

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


    <!-- Steadfast Parcel Booking and Tracking -->

    <section class="details-card no-print">

        <h2>Steadfast Courier Booking & Tracking</h2>

        <?php if ($isBooked): ?>

            <p class="success-message">
                এই অর্ডারের পার্সেল Steadfast-এ বুক করা হয়েছে।
            </p>

            <div class="tracking-info">

                <p>
                    <strong>Consignment ID:</strong><br>
                    <?php echo e($order['steadfast_consignment_id']); ?>
                </p>

                <p>
                    <strong>Tracking Code:</strong><br>
                    <?php echo e($order['steadfast_tracking_code']); ?>
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

                <?php if (!empty($order['steadfast_tracking_url'])): ?>

                    <p>
                        <strong>Tracking Link:</strong><br>

                        <a
                            class="tracking-link"
                            href="<?php echo e($order['steadfast_tracking_url']); ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            পার্সেল ট্র্যাক করুন
                        </a>
                    </p>

                <?php endif; ?>

            </div>

            <!-- Refresh Tracking Status -->

            <a
                href="steadfast_status.php?id=<?php echo (int)$order['id']; ?>"
                class="tracking-refresh-btn"
                onclick="return confirm('Steadfast থেকে সর্বশেষ Tracking Status আনতে চান?');"
            >
                Tracking Status আপডেট করুন
            </a>

            <!-- Tracking History Button -->

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

        <?php else: ?>

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

                <button
                    type="submit"
                    class="steadfast-btn"
                >
                    Steadfast-এ পার্সেল বুক করুন
                </button>

            </form>

            <?php if ($bookingStatus === 'failed'): ?>

                <p class="error-message" style="margin-top:15px;">
                    আগের Booking প্রচেষ্টা ব্যর্থ হয়েছিল।
                    নতুন করে বুক করার আগে Steadfast Merchant Portal-এ
                    Invoice যাচাই করুন।
                </p>

            <?php endif; ?>

        <?php endif; ?>

    </section>


    <!-- Order Update -->

    <section class="details-card no-print">

        <h2>অর্ডার ও কুরিয়ার আপডেট</h2>

        <form method="POST" class="details-form">

            <input
                type="hidden"
                name="order_id"
                value="<?php echo (int)$order['id']; ?>"
            >

            <label>অর্ডারের বর্তমান অবস্থা</label>

            <select name="order_status" required>

                <?php
                $statuses = [
                    'pending' => 'নতুন অর্ডার',
                    'confirmed' => 'নিশ্চিত',
                    'processing' => 'প্রস্তুত হচ্ছে',
                    'shipped' => 'কুরিয়ারে পাঠানো হয়েছে',
                    'delivered' => 'ডেলিভারি সম্পন্ন',
                    'cancelled' => 'বাতিল'
                ];

                foreach ($statuses as $key => $label):
                ?>

                    <option
                        value="<?php echo e($key); ?>"
                        <?php echo $order['order_status'] === $key ? 'selected' : ''; ?>
                    >
                        <?php echo e($label); ?>
                    </option>

                <?php endforeach; ?>

            </select>

            <label>কুরিয়ার পার্টনার</label>

            <input
                type="text"
                name="delivery_partner"
                placeholder="যেমন: Steadfast"
                value="<?php echo e($order['delivery_partner'] ?? ''); ?>"
            >

            <label>ট্র্যাকিং নম্বর</label>

            <input
                type="text"
                name="tracking_id"
                placeholder="কুরিয়ারের Tracking ID"
                value="<?php echo e($order['tracking_id'] ?? ''); ?>"
            >

            <button
                type="submit"
                name="update_order"
                value="1"
            >
                পরিবর্তন সংরক্ষণ করুন
            </button>

        </form>

    </section>


    <!-- Print and Back -->

    <div class="no-print">

        <button
            onclick="window.print()"
            class="action-btn"
        >
            অর্ডার স্লিপ প্রিন্ট করুন
        </button>

        <a
            href="orders.php"
            class="action-btn"
        >
            অর্ডার তালিকায় ফিরে যান
        </a>

    </div>

</main>

</body>
</html>
