
<?php
session_start();
require_once "../config/database.php";

// Admin Login Check
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Create CSRF token
if (empty($_SESSION['orders_csrf_token'])) {
    $_SESSION['orders_csrf_token'] = bin2hex(random_bytes(32));
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

$allowedStatuses = [
    'pending',
    'confirmed',
    'processing',
    'shipped',
    'delivered',
    'cancelled'
];

$successMessage = '';
$errorMessage = '';

// Delete Order (POST Only)
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'delete_order'
) {
    $token = $_POST['csrf_token'] ?? '';
    $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);

    if (
        !$token ||
        !hash_equals($_SESSION['orders_csrf_token'], $token)
    ) {
        $errorMessage = 'নিরাপত্তা যাচাই করা যায়নি। পেজটি রিফ্রেশ করে আবার চেষ্টা করুন।';

    } elseif (!$orderId || $orderId < 1) {
        $errorMessage = 'সঠিক অর্ডার নির্বাচন করা হয়নি।';

    } else {

        try {
            $conn->begin_transaction();

            // Lock order before deletion
            $checkStmt = $conn->prepare("
                SELECT id, steadfast_booking_status
                FROM orders
                WHERE id = ?
                LIMIT 1
                FOR UPDATE
            ");

            $checkStmt->bind_param("i", $orderId);
            $checkStmt->execute();

            $orderToDelete = $checkStmt
                ->get_result()
                ->fetch_assoc();

            $checkStmt->close();

            if (!$orderToDelete) {
                throw new Exception('অর্ডারটি পাওয়া যায়নি।');
            }

            $bookingStatus =
                $orderToDelete['steadfast_booking_status']
                ?? 'not_booked';

            // Do not delete booked or uncertain Steadfast orders
            if (
                in_array(
                    $bookingStatus,
                    ['booking', 'booked', 'unknown'],
                    true
                )
            ) {
                throw new Exception(
                    'এই অর্ডারটি Steadfast-এ বুকিং করা হয়েছে অথবা বুকিংয়ের অবস্থা নিশ্চিত নয়। নিরাপত্তার জন্য এটি ডিলিট করা যাচ্ছে না।'
                );
            }

            // Restore product stock
            $restoreStmt = $conn->prepare("
                UPDATE products
                INNER JOIN order_items
                    ON products.id = order_items.product_id
                SET products.stock =
                    products.stock + order_items.quantity
                WHERE order_items.order_id = ?
            ");

            $restoreStmt->bind_param("i", $orderId);
            $restoreStmt->execute();
            $restoreStmt->close();

            // Delete order items first
            $itemsStmt = $conn->prepare("
                DELETE FROM order_items
                WHERE order_id = ?
            ");

            $itemsStmt->bind_param("i", $orderId);
            $itemsStmt->execute();
            $itemsStmt->close();

            // Delete order
            $deleteStmt = $conn->prepare("
                DELETE FROM orders
                WHERE id = ?
            ");

            $deleteStmt->bind_param("i", $orderId);
            $deleteStmt->execute();

            if ($deleteStmt->affected_rows !== 1) {
                $deleteStmt->close();
                throw new Exception('অর্ডারটি ডিলিট করা যায়নি।');
            }

            $deleteStmt->close();

            $conn->commit();

            $_SESSION['orders_csrf_token'] =
                bin2hex(random_bytes(32));

            $_SESSION['orders_success'] =
                'অর্ডারটি সফলভাবে ডিলিট করা হয়েছে এবং পণ্যের স্টক ফিরিয়ে দেওয়া হয়েছে।';

        } catch (Throwable $e) {

            $conn->rollback();

            $errorMessage = $e instanceof mysqli_sql_exception
                ? 'অর্ডার ডিলিট করা যায়নি। ডেটাবেজে সমস্যা হয়েছে।'
                : $e->getMessage();
        }
    }

    // Preserve search and filter after deletion
    $redirectParams = [];

    if (
        isset($_GET['search']) &&
        trim($_GET['search']) !== ''
    ) {
        $redirectParams['search'] = trim($_GET['search']);
    }

    if (
        isset($_GET['status']) &&
        in_array($_GET['status'], $allowedStatuses, true)
    ) {
        $redirectParams['status'] = $_GET['status'];
    }

    if ($errorMessage !== '') {
        $_SESSION['orders_error'] = $errorMessage;
    }

    $redirectUrl = 'orders.php';

    if (!empty($redirectParams)) {
        $redirectUrl .= '?' . http_build_query($redirectParams);
    }

    header("Location: " . $redirectUrl);
    exit;
}

// Read one-time messages
$successMessage = $_SESSION['orders_success'] ?? '';
$errorMessage = $_SESSION['orders_error'] ?? '';

unset(
    $_SESSION['orders_success'],
    $_SESSION['orders_error']
);

// Search and Filter
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$sql = "
    SELECT
        orders.*,
        GROUP_CONCAT(
            CONCAT(
                order_items.product_name,
                ' (',
                order_items.quantity,
                ' টি)'
            )
            SEPARATOR ', '
        ) AS products

    FROM orders

    LEFT JOIN order_items
        ON orders.id = order_items.order_id

    WHERE 1=1
";

$params = [];
$types = "";

if ($search !== '') {

    $sql .= " AND (
        orders.order_number LIKE ?
        OR orders.customer_name LIKE ?
        OR orders.phone LIKE ?
        OR orders.address LIKE ?
    )";

    $keyword = "%$search%";

    $params = array_merge(
        $params,
        [
            $keyword,
            $keyword,
            $keyword,
            $keyword
        ]
    );

    $types .= "ssss";
}

if (in_array($status, $allowedStatuses, true)) {

    $sql .= " AND orders.order_status = ?";

    $params[] = $status;
    $types .= "s";
}

$sql .= "
    GROUP BY orders.id
    ORDER BY orders.id DESC
";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="bn">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>অর্ডার ম্যানেজমেন্ট | BrandNest</title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

    <style>

        .admin-orders {
            padding: 35px 20px;
            color: #eee;
            max-width: 1400px;
            margin: auto;
        }

        .admin-orders h1 {
            color: #c9a45c;
            margin-bottom: 25px;
        }

        .order-filters {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .order-filters input,
        .order-filters select {
            padding: 12px;
            border-radius: 6px;
            border: 1px solid #555;
            background: #222;
            color: white;
        }

        .order-filters button {
            padding: 12px 22px;
            background: #c9a45c;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }

        .orders-table-wrap {
            overflow-x: auto;
            background: #171717;
            border-radius: 10px;
            border: 1px solid #383838;
        }

        .orders-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1150px;
        }

        .orders-table th,
        .orders-table td {
            padding: 14px;
            border-bottom: 1px solid #383838;
            text-align: left;
            vertical-align: top;
        }

        .orders-table th {
            color: #c9a45c;
            background: #222;
        }

        .orders-table td {
            color: #ddd;
        }

        .order-status {
            color: #c9a45c;
            font-weight: bold;
        }

        .view-order-btn {
            display: inline-block;
            padding: 8px 13px;
            background: #c9a45c;
            color: #111;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            border: none;
            cursor: pointer;
        }

        .delete-order-btn {
            display: inline-block;
            padding: 8px 13px;
            background: #a83232;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
        }

        .delete-order-btn:hover {
            background: #c43d3d;
        }

        .delete-form {
            display: inline-block;
            margin-left: 6px;
        }

        .message {
            padding: 13px 16px;
            border-radius: 6px;
            margin-bottom: 18px;
        }

        .success-message {
            background: #183b28;
            color: #b8f0c9;
            border-left: 4px solid #38a169;
        }

        .error-message {
            background: #401d1d;
            color: #ffaaaa;
            border-left: 4px solid #ff7777;
        }

        .no-orders {
            padding: 30px;
            text-align: center;
            color: #aaa;
        }

    </style>

</head>

<body>

<div class="admin-orders">

    <h1>অর্ডার ম্যানেজমেন্ট</h1>

    <?php if ($successMessage): ?>

        <div class="message success-message">
            <?php echo e($successMessage); ?>
        </div>

    <?php endif; ?>

    <?php if ($errorMessage): ?>

        <div class="message error-message">
            <?php echo e($errorMessage); ?>
        </div>

    <?php endif; ?>

    <form method="GET" class="order-filters">

        <input
            type="text"
            name="search"
            placeholder="অর্ডার নম্বর, নাম বা মোবাইল দিয়ে খুঁজুন"
            value="<?php echo e($search); ?>"
        >

        <select name="status">

            <option value="">সব অর্ডার</option>

            <?php foreach ($allowedStatuses as $item): ?>

                <option
                    value="<?php echo e($item); ?>"
                    <?php echo $status === $item ? 'selected' : ''; ?>
                >
                    <?php echo e(statusBangla($item)); ?>
                </option>

            <?php endforeach; ?>

        </select>

        <button type="submit">
            খুঁজুন
        </button>

        <a href="orders.php" class="view-order-btn">
            রিসেট
        </a>

    </form>

    <div class="orders-table-wrap">

        <table class="orders-table">

            <thead>

                <tr>
                    <th>অর্ডার নম্বর</th>
                    <th>ক্রেতার নাম</th>
                    <th>মোবাইল</th>
                    <th>পণ্য</th>
                    <th>সর্বমোট</th>
                    <th>অর্ডারের অবস্থা</th>
                    <th>তারিখ</th>
                    <th>বিস্তারিত</th>
                    <th>ডিলিট</th>
                </tr>

            </thead>

            <tbody>

                <?php if ($result->num_rows > 0): ?>

                    <?php while ($order = $result->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <?php echo e($order['order_number']); ?>
                            </td>

                            <td>
                                <?php echo e($order['customer_name']); ?>
                            </td>

                            <td>
                                <?php echo e($order['phone']); ?>
                            </td>

                            <td>
                                <?php echo e($order['products'] ?? ''); ?>
                            </td>

                            <td>
                                ৳ <?php
                                    echo number_format(
                                        (float)$order['total_amount'],
                                        2
                                    );
                                ?>
                            </td>

                            <td class="order-status">
                                <?php
                                    echo e(
                                        statusBangla(
                                            $order['order_status']
                                        )
                                    );
                                ?>
                            </td>

                            <td>
                                <?php
                                    echo date(
                                        'd-m-Y h:i A',
                                        strtotime($order['created_at'])
                                    );
                                ?>
                            </td>

                            <td>

                                <a
                                    href="view_order.php?id=<?php echo (int)$order['id']; ?>"
                                    class="view-order-btn"
                                >
                                    বিস্তারিত
                                </a>

                            </td>

                            <td>

                                <?php

                                    $bookingStatus =
                                        $order['steadfast_booking_status']
                                        ?? 'not_booked';

                                    $canDelete = !in_array(
                                        $bookingStatus,
                                        ['booking', 'booked', 'unknown'],
                                        true
                                    );

                                ?>

                                <?php if ($canDelete): ?>

                                    <form
                                        method="POST"
                                        action="orders.php<?php

                                            $query = [];

                                            if ($search !== '') {
                                                $query['search'] = $search;
                                            }

                                            if (
                                                in_array(
                                                    $status,
                                                    $allowedStatuses,
                                                    true
                                                )
                                            ) {
                                                $query['status'] = $status;
                                            }

                                            echo $query
                                                ? '?' . e(
                                                    http_build_query($query)
                                                )
                                                : '';

                                        ?>"
                                        class="delete-form"
                                        onsubmit="return confirm('আপনি কি নিশ্চিত যে এই অর্ডারটি ডিলিট করতে চান? ডিলিট করলে অর্ডারের পণ্যগুলোর স্টক ফিরিয়ে দেওয়া হবে।');"
                                    >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete_order"
                                        >

                                        <input
                                            type="hidden"
                                            name="order_id"
                                            value="<?php echo (int)$order['id']; ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?php
                                                echo e(
                                                    $_SESSION['orders_csrf_token']
                                                );
                                            ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="delete-order-btn"
                                        >
                                            Delete
                                        </button>

                                    </form>

                                <?php else: ?>

                                    <span title="Steadfast booking exists or is uncertain">
                                        বুকিং করা
                                    </span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="9" class="no-orders">
                            এখনো কোনো অর্ডার পাওয়া যায়নি।
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</body>
</html>
