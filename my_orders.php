
<?php
session_start();
require_once "config/database.php";

// Customer login check
if (empty($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit;
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$customerId = (int) $_SESSION['customer_id'];

// Get customer's orders only
$stmt = $conn->prepare("
    SELECT
        id,
        order_number,
        customer_name,
        phone,
        address,
        area,
        subtotal,
        delivery_charge,
        total_amount,
        payment_method,
        payment_status,
        order_status,
        created_at
    FROM orders
    WHERE customer_id = ?
    ORDER BY created_at DESC
");

$stmt->bind_param("i", $customerId);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];

while ($order = $result->fetch_assoc()) {
    $orders[] = $order;
}

$stmt->close();

function orderStatus($status) {
    $statuses = [
        'pending' => 'অপেক্ষমাণ',
        'confirmed' => 'নিশ্চিত',
        'processing' => 'প্রস্তুত করা হচ্ছে',
        'shipped' => 'ডেলিভারির পথে',
        'delivered' => 'ডেলিভারি সম্পন্ন',
        'cancelled' => 'বাতিল'
    ];

    return $statuses[$status] ?? $status;
}

function paymentMethodName($method) {
    $methods = [
        'COD' => 'ক্যাশ অন ডেলিভারি',
        'bKash' => 'বিকাশ',
        'Nagad' => 'নগদ'
    ];

    return $methods[$method] ?? $method;
}

function paymentStatusName($status) {
    $statuses = [
        'pending' => 'অপেক্ষমাণ',
        'paid' => 'পরিশোধিত',
        'unpaid' => 'পরিশোধিত নয়',
        'failed' => 'ব্যর্থ'
    ];

    return $statuses[$status] ?? $status;
}
?>

<!DOCTYPE html>
<html lang="bn">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>আমার অর্ডার | BrandNest</title>

    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .my-orders-page {
            min-height: 75vh;
            background: #101010;
            padding: 45px 15px;
        }

        .orders-container {
            max-width: 1100px;
            margin: auto;
        }

        .orders-heading {
            text-align: center;
            margin-bottom: 35px;
        }

        .orders-heading h1 {
            color: #c9a45c;
            font-size: 32px;
        }

        .orders-heading p {
            color: #aaa;
            margin-top: 10px;
        }

        .order-card {
            background: #191919;
            border: 1px solid #393126;
            border-radius: 9px;
            padding: 24px;
            margin-bottom: 22px;
        }

        .order-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            padding-bottom: 17px;
            border-bottom: 1px solid #393126;
        }

        .order-number {
            color: #c9a45c;
            font-size: 18px;
            font-weight: bold;
        }

        .order-date {
            color: #999;
            font-size: 13px;
            margin-top: 6px;
        }

        .order-status {
            background: #30291d;
            color: #e0bd75;
            padding: 8px 14px;
            border-radius: 5px;
            font-size: 13px;
        }

        .order-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            padding: 20px 0;
        }

        .detail-item {
            color: #aaa;
            font-size: 14px;
            line-height: 1.8;
            overflow-wrap: anywhere;
        }

        .detail-item strong {
            color: #fff;
            display: block;
        }

        .order-items {
            border-top: 1px solid #393126;
            border-bottom: 1px solid #393126;
            padding: 15px 0;
        }

        .order-items h3 {
            color: #c9a45c;
            font-size: 17px;
            margin-bottom: 12px;
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            color: #ccc;
            font-size: 14px;
            padding: 8px 0;
        }

        .order-item span:last-child {
            color: white;
            white-space: nowrap;
        }

        .order-total {
            text-align: right;
            color: #c9a45c;
            font-size: 20px;
            font-weight: bold;
            padding-top: 18px;
        }

        .empty-orders {
            background: #191919;
            border: 1px solid #393126;
            border-radius: 9px;
            text-align: center;
            padding: 50px 20px;
        }

        .empty-orders h2 {
            color: white;
            margin-bottom: 12px;
        }

        .empty-orders p {
            color: #aaa;
            margin-bottom: 22px;
        }

        .shop-button {
            display: inline-block;
            background: #c9a45c;
            color: #111;
            padding: 12px 24px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
        }

        .shop-button:hover {
            background: #e0bd75;
        }

        @media(max-width: 600px) {
            .my-orders-page {
                padding: 30px 12px;
            }

            .orders-heading h1 {
                font-size: 26px;
            }

            .order-card {
                padding: 17px;
            }

            .order-details {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .order-total {
                font-size: 18px;
            }
        }
    </style>
</head>

<body>

<?php include "includes/header.php"; ?>

<main class="my-orders-page">

    <div class="orders-container">

        <div class="orders-heading">
            <h1>আমার অর্ডার</h1>
            <p>আপনার সকল অর্ডারের তথ্য এখানে দেখতে পারবেন।</p>
        </div>

        <?php if (empty($orders)): ?>

            <div class="empty-orders">
                <h2>এখনো কোনো অর্ডার করেননি!</h2>

                <p>
                    আপনার পছন্দের পণ্যগুলো দেখে কেনাকাটা শুরু করুন।
                </p>

                <a href="index.php" class="shop-button">
                    কেনাকাটা শুরু করুন
                </a>
            </div>

        <?php else: ?>

            <?php foreach ($orders as $order): ?>

                <div class="order-card">

                    <div class="order-top">

                        <div>
                            <div class="order-number">
                                অর্ডার #<?= e($order['order_number']); ?>
                            </div>

                            <div class="order-date">
                                <?= e(date('d M Y, h:i A', strtotime($order['created_at']))); ?>
                            </div>
                        </div>

                        <span class="order-status">
                            <?= e(orderStatus($order['order_status'])); ?>
                        </span>

                    </div>

                    <div class="order-details">

                        <div class="detail-item">
                            গ্রাহকের নাম
                            <strong><?= e($order['customer_name']); ?></strong>
                        </div>

                        <div class="detail-item">
                            মোবাইল নম্বর
                            <strong><?= e($order['phone']); ?></strong>
                        </div>

                        <div class="detail-item">
                            ডেলিভারি এলাকা
                            <strong><?= e($order['area']); ?></strong>
                        </div>

                        <div class="detail-item">
                            ঠিকানা
                            <strong><?= e($order['address']); ?></strong>
                        </div>

                        <div class="detail-item">
                            পেমেন্ট পদ্ধতি
                            <strong><?= e(paymentMethodName($order['payment_method'])); ?></strong>
                        </div>

                        <div class="detail-item">
                            পেমেন্টের অবস্থা
                            <strong><?= e(paymentStatusName($order['payment_status'])); ?></strong>
                        </div>

                    </div>

                    <div class="order-items">

                        <h3>অর্ডারের পণ্যসমূহ</h3>

                        <?php
                        $itemStmt = $conn->prepare("
                            SELECT product_name, quantity, price, subtotal
                            FROM order_items
                            WHERE order_id = ?
                        ");

                        $itemStmt->bind_param("i", $order['id']);
                        $itemStmt->execute();

                        $items = $itemStmt->get_result();

                        while ($item = $items->fetch_assoc()):
                        ?>

                            <div class="order-item">

                                <span>
                                    <?= e($item['product_name']); ?>
                                    × <?= (int)$item['quantity']; ?>
                                </span>

                                <span>
                                    ৳<?= number_format((float)$item['subtotal'], 2); ?>
                                </span>

                            </div>

                        <?php endwhile;

                        $itemStmt->close();
                        ?>

                    </div>

                    <div class="order-total">
                        সর্বমোট: ৳<?= number_format((float)$order['total_amount'], 2); ?>
                    </div>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</main>

</body>
</html>
