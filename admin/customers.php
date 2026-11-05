
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
$filter = trim($_GET['filter'] ?? '');

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Customer Summary
$summarySql = "
    SELECT
        COUNT(*) AS total_customers,
        SUM(
            CASE WHEN order_summary.total_orders > 0
            THEN 1 ELSE 0 END
        ) AS ordered_customers,
        SUM(
            CASE WHEN order_summary.total_orders = 0
            THEN 1 ELSE 0 END
        ) AS inactive_customers
    FROM customers c
    LEFT JOIN (
        SELECT customer_id, COUNT(*) AS total_orders
        FROM orders
        WHERE customer_id IS NOT NULL
        GROUP BY customer_id
    ) AS order_summary
    ON c.id = order_summary.customer_id
";

$summaryResult = $conn->query($summarySql);
$summary = $summaryResult->fetch_assoc();

$totalCustomers = (int)($summary['total_customers'] ?? 0);
$orderedCustomers = (int)($summary['ordered_customers'] ?? 0);
$inactiveCustomers = (int)($summary['inactive_customers'] ?? 0);

// Customer List
$sql = "
    SELECT
        c.id,
        c.name,
        c.email,
        c.phone,
        c.created_at,
        COALESCE(o.total_orders, 0) AS total_orders,
        COALESCE(o.total_spent, 0) AS total_spent,
        o.last_order_date
    FROM customers c
    LEFT JOIN (
        SELECT
            customer_id,
            COUNT(*) AS total_orders,
            SUM(total_amount) AS total_spent,
            MAX(created_at) AS last_order_date
        FROM orders
        WHERE customer_id IS NOT NULL
        GROUP BY customer_id
    ) AS o
    ON c.id = o.customer_id
    WHERE 1=1
";

$params = [];
$types = "";

if ($search !== '') {
    $sql .= "
        AND (
            c.name LIKE ?
            OR c.email LIKE ?
            OR c.phone LIKE ?
        )
    ";

    $keyword = "%" . $search . "%";

    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;

    $types .= "sss";
}

if ($filter === 'ordered') {
    $sql .= " AND COALESCE(o.total_orders, 0) > 0";
} elseif ($filter === 'not_ordered') {
    $sql .= " AND COALESCE(o.total_orders, 0) = 0";
}

$sql .= " ORDER BY c.id DESC";

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Customer Management | BrandNest</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .admin-customers {
            max-width: 1400px;
            margin: auto;
            padding: 35px 20px;
            color: #eee;
        }

        .admin-customers h1 {
            color: #c9a45c;
            margin-bottom: 25px;
        }

        .customer-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 30px;
        }

        .summary-card {
            background: #191919;
            border: 1px solid #393126;
            border-radius: 9px;
            padding: 22px;
        }

        .summary-card h3 {
            color: #aaa;
            font-size: 15px;
            margin-bottom: 12px;
        }

        .summary-card p {
            color: #c9a45c;
            font-size: 28px;
            font-weight: bold;
        }

        .customer-filters {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .customer-filters input,
        .customer-filters select {
            padding: 12px;
            border-radius: 6px;
            border: 1px solid #555;
            background: #222;
            color: white;
            min-width: 180px;
            box-sizing: border-box;
        }

        .customer-filters button {
            padding: 12px 22px;
            background: #c9a45c;
            color: #111;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }

        .customer-filters button:hover {
            background: #e0bd75;
        }

        .reset-btn {
            display: inline-flex;
            align-items: center;
            padding: 12px 18px;
            background: #333;
            color: white;
            border-radius: 6px;
            text-decoration: none;
        }

        .reset-btn:hover {
            background: #444;
        }

        .customers-table-wrap {
            overflow-x: auto;
            background: #171717;
            border-radius: 10px;
            border: 1px solid #383838;
        }

        .customers-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1050px;
        }

        .customers-table th,
        .customers-table td {
            padding: 15px;
            border-bottom: 1px solid #383838;
            text-align: left;
            vertical-align: middle;
        }

        .customers-table th {
            color: #c9a45c;
            background: #222;
            white-space: nowrap;
        }

        .customers-table td {
            color: #ddd;
        }

        .customer-name {
            color: white;
            font-weight: bold;
        }

        .customer-status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-active {
            background: #203a29;
            color: #8de0a5;
        }

        .status-inactive {
            background: #3b2b1d;
            color: #e0bd75;
        }

        .order-count {
            color: #c9a45c;
            font-weight: bold;
        }

        .customer-total {
            color: #e0bd75;
            font-weight: bold;
            white-space: nowrap;
        }

        .no-customers {
            padding: 30px;
            text-align: center;
            color: #aaa;
        }

        @media(max-width: 800px) {
            .customer-summary {
                grid-template-columns: 1fr;
            }
        }

        @media(max-width: 480px) {
            .admin-customers {
                padding: 25px 12px;
            }

            .admin-customers h1 {
                font-size: 23px;
            }

            .customer-filters {
                flex-direction: column;
            }

            .customer-filters input,
            .customer-filters select,
            .customer-filters button,
            .reset-btn {
                width: 100%;
                box-sizing: border-box;
                justify-content: center;
            }

            .summary-card {
                padding: 18px;
            }
        }
    </style>
</head>

<body>

<div class="admin-customers">

    <h1>Customer Management</h1>

    <!-- Customer Summary -->
    <div class="customer-summary">

        <div class="summary-card">
            <h3>মোট Customer</h3>
            <p><?= number_format($totalCustomers); ?></p>
        </div>

        <div class="summary-card">
            <h3>অর্ডার করেছে</h3>
            <p><?= number_format($orderedCustomers); ?></p>
        </div>

        <div class="summary-card">
            <h3>অর্ডার করেনি</h3>
            <p><?= number_format($inactiveCustomers); ?></p>
        </div>

    </div>

    <!-- Search and Filter -->
    <form method="GET" class="customer-filters">

        <input
            type="text"
            name="search"
            placeholder="নাম, ফোন বা ইমেইল দিয়ে খুঁজুন"
            value="<?= e($search); ?>"
        >

        <select name="filter">

            <option value="">সব Customer</option>

            <option
                value="ordered"
                <?= $filter === 'ordered' ? 'selected' : ''; ?>
            >
                অর্ডার করেছে
            </option>

            <option
                value="not_ordered"
                <?= $filter === 'not_ordered' ? 'selected' : ''; ?>
            >
                অর্ডার করেনি
            </option>

        </select>

        <button type="submit">Search</button>

        <a href="customers.php" class="reset-btn">
            Reset
        </a>

    </form>

    <!-- Customers Table -->
    <div class="customers-table-wrap">

        <table class="customers-table">

            <thead>
                <tr>
                    <th>Customer ID</th>
                    <th>নাম</th>
                    <th>মোবাইল</th>
                    <th>ইমেইল</th>
                    <th>মোট অর্ডার</th>
                    <th>মোট অর্ডারের মূল্য</th>
                    <th>সর্বশেষ অর্ডার</th>
                    <th>অবস্থা</th>
                </tr>
            </thead>

            <tbody>

                <?php if ($result->num_rows > 0): ?>

                    <?php while ($customer = $result->fetch_assoc()): ?>

                        <?php
                        $hasOrders = (int)$customer['total_orders'] > 0;
                        ?>

                        <tr>

                            <td>
                                #<?= (int)$customer['id']; ?>
                            </td>

                            <td class="customer-name">
                                <?= e($customer['name']); ?>
                            </td>

                            <td>
                                <?= e($customer['phone']); ?>
                            </td>

                            <td>
                                <?= e($customer['email']); ?>
                            </td>

                            <td class="order-count">
                                <?= number_format((int)$customer['total_orders']); ?>
                            </td>

                            <td class="customer-total">
                                ৳ <?= number_format((float)$customer['total_spent'], 2); ?>
                            </td>

                            <td>
                                <?php if (!empty($customer['last_order_date'])): ?>

                                    <?= e(date(
                                        'd-m-Y h:i A',
                                        strtotime($customer['last_order_date'])
                                    )); ?>

                                <?php else: ?>

                                    এখনো অর্ডার করেনি

                                <?php endif; ?>
                            </td>

                            <td>

                                <?php if ($hasOrders): ?>

                                    <span class="customer-status status-active">
                                        অর্ডার করেছে
                                    </span>

                                <?php else: ?>

                                    <span class="customer-status status-inactive">
                                        অর্ডার করেনি
                                    </span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="8" class="no-customers">
                            কোনো Customer পাওয়া যায়নি।
                        </td>
                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</body>
</html>

<?php
$stmt->close();
?>
