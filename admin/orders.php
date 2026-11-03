
<?php
session_start();
require_once "../config/database.php";

// Admin Login Check
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Search and Filter
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$sql = "
    SELECT
        orders.*,
        GROUP_CONCAT(
            CONCAT(order_items.product_name, ' (', order_items.quantity, ' টি)')
            SEPARATOR ', '
        ) AS products
    FROM orders
    LEFT JOIN order_items ON orders.id = order_items.order_id
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

    $params = array_merge($params, [
        $keyword,
        $keyword,
        $keyword,
        $keyword
    ]);

    $types .= "ssss";
}

$allowedStatuses = [
    'pending',
    'confirmed',
    'processing',
    'shipped',
    'delivered',
    'cancelled'
];

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
?>

<!DOCTYPE html>
<html lang="bn">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>অর্ডার ম্যানেজমেন্ট | BrandNest</title>

    <link rel="stylesheet" href="../assets/css/style.css">

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
            min-width: 1050px;
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

        <button type="submit">খুঁজুন</button>

        <a href="orders.php" class="view-order-btn">রিসেট</a>

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
                </tr>
            </thead>

            <tbody>

                <?php if ($result->num_rows > 0): ?>

                    <?php while ($order = $result->fetch_assoc()): ?>

                        <tr>

                            <td><?php echo e($order['order_number']); ?></td>

                            <td><?php echo e($order['customer_name']); ?></td>

                            <td><?php echo e($order['phone']); ?></td>

                            <td><?php echo e($order['products'] ?? ''); ?></td>

                            <td>
                                ৳ <?php echo number_format((float)$order['total_amount'], 2); ?>
                            </td>

                            <td class="order-status">
                                <?php echo e(statusBangla($order['order_status'])); ?>
                            </td>

                            <td>
                                <?php echo date('d-m-Y h:i A', strtotime($order['created_at'])); ?>
                            </td>

                            <td>
                                <a
                                    href="view_order.php?id=<?php echo (int)$order['id']; ?>"
                                    class="view-order-btn"
                                >
                                    বিস্তারিত
                                </a>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="8" class="no-orders">
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
