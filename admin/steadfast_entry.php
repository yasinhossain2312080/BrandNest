
<?php
session_start();

require_once "../config/database.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Search
$search = trim($_GET['search'] ?? '');

// Fetch Successfully Booked Orders
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
    WHERE
        orders.steadfast_booking_status = 'booked'
        AND orders.steadfast_consignment_id IS NOT NULL
        AND orders.steadfast_consignment_id != ''
";

$params = [];
$types = "";

if ($search !== '') {
    $sql .= "
        AND (
            orders.order_number LIKE ?
            OR orders.customer_name LIKE ?
            OR orders.phone LIKE ?
            OR orders.steadfast_consignment_id LIKE ?
            OR orders.steadfast_tracking_code LIKE ?
        )
    ";

    $keyword = "%{$search}%";

    $params = [
        $keyword,
        $keyword,
        $keyword,
        $keyword,
        $keyword
    ];

    $types = "sssss";
}

$sql .= "
    GROUP BY orders.id
    ORDER BY orders.steadfast_booked_at DESC, orders.id DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database Query Failed.");
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$totalEntries = $result->num_rows;

?>

<!DOCTYPE html>
<html lang="bn">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Steadfast Entry | BrandNest</title>

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    background: #f4f6fa;
    color: #263449;
    font-family: Arial, "Noto Sans Bengali", sans-serif;
}

.container {
    max-width: 1500px;
    margin: auto;
    padding: 30px 22px;
}

/* HEADER */

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 25px;
}

.page-header h1 {
    color: #172b4d;
    font-size: 25px;
}

.page-header p {
    color: #8793a5;
    font-size: 12px;
    margin-top: 7px;
}

.back-btn {
    background: #172b4d;
    color: white;
    padding: 11px 17px;
    border-radius: 7px;
    text-decoration: none;
    font-size: 12px;
    font-weight: bold;
}

/* SUMMARY */

.summary-card {
    background: linear-gradient(110deg, #172b4d, #29466f);
    color: white;
    padding: 22px;
    border-radius: 12px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.summary-card p {
    color: #d3dbea;
    font-size: 12px;
}

.summary-card h2 {
    font-size: 30px;
    margin-top: 8px;
}

.summary-icon {
    color: #c9a45c;
    font-size: 36px;
}

/* SEARCH */

.search-box {
    background: white;
    border: 1px solid #e5e9f0;
    padding: 18px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.search-box form {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.search-box input {
    flex: 1;
    min-width: 200px;
    padding: 12px;
    border: 1px solid #dce2eb;
    border-radius: 6px;
    outline: none;
}

.search-box button {
    padding: 12px 22px;
    background: #c9a45c;
    color: #172b4d;
    border: none;
    border-radius: 6px;
    font-weight: bold;
    cursor: pointer;
}

.reset-btn {
    display: inline-block;
    padding: 12px 17px;
    background: #edf1f7;
    color: #172b4d;
    text-decoration: none;
    border-radius: 6px;
    font-size: 12px;
}

/* TABLE */

.table-card {
    background: white;
    border: 1px solid #e5e9f0;
    border-radius: 12px;
    overflow: hidden;
}

.table-heading {
    padding: 19px;
    border-bottom: 1px solid #e9edf3;
}

.table-heading h2 {
    color: #172b4d;
    font-size: 16px;
}

.table-heading p {
    color: #8993a4;
    font-size: 11px;
    margin-top: 5px;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 1150px;
    border-collapse: collapse;
}

th {
    background: #172b4d;
    color: white;
    padding: 14px 12px;
    text-align: left;
    font-size: 11px;
    white-space: nowrap;
}

td {
    padding: 15px 12px;
    border-bottom: 1px solid #edf0f5;
    font-size: 11px;
    vertical-align: top;
}

tr:hover td {
    background: #fafbfe;
}

.sl-number {
    font-weight: bold;
    color: #172b4d;
}

.invoice-number {
    font-weight: bold;
    color: #172b4d;
    margin-bottom: 8px;
}

.tracking-info {
    line-height: 1.8;
    overflow-wrap: anywhere;
}

.tracking-info strong {
    color: #526078;
}

.copy-btn {
    background: #edf1f7;
    border: none;
    padding: 4px 7px;
    border-radius: 4px;
    color: #172b4d;
    cursor: pointer;
    font-size: 10px;
    margin-left: 3px;
}

.customer-name {
    font-weight: bold;
    color: #172b4d;
    margin-bottom: 6px;
}

.customer-phone {
    color: #66758c;
}

.product-info {
    max-width: 210px;
    line-height: 1.7;
    overflow-wrap: anywhere;
}

.amount {
    font-weight: bold;
    color: #172b4d;
    white-space: nowrap;
}

.entry-status {
    display: inline-block;
    background: #e1f5eb;
    color: #16834d;
    padding: 6px 9px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: bold;
    white-space: nowrap;
}

.action-links {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.action-links a {
    display: inline-block;
    padding: 8px 10px;
    text-align: center;
    border-radius: 5px;
    font-size: 10px;
    font-weight: bold;
    text-decoration: none;
    white-space: nowrap;
}

.details-btn {
    background: #172b4d;
    color: white;
}

.print-btn {
    background: #c9a45c;
    color: #172b4d;
}

.track-btn {
    background: #e9f0ff;
    color: #315d9c;
}

.empty-state {
    text-align: center;
    padding: 45px 15px;
    color: #8993a4;
}

.footer {
    text-align: center;
    padding: 25px;
    color: #9ba5b4;
    font-size: 11px;
}

@media(max-width: 600px) {
    .container {
        padding: 18px 10px;
    }

    .page-header h1 {
        font-size: 21px;
    }

    .summary-card {
        padding: 18px;
    }
}

</style>

</head>

<body>

<div class="container">

    <!-- HEADER -->

    <div class="page-header">

        <div>
            <h1>Steadfast Entry</h1>
            <p>Successfully booked parcels and courier information</p>
        </div>

        <a href="dashboard.php" class="back-btn">
            ← Dashboard
        </a>

    </div>


    <!-- SUMMARY -->

    <div class="summary-card">

        <div>
            <p>Total Successful Steadfast Entries</p>
            <h2><?php echo number_format($totalEntries); ?></h2>
        </div>

        <div class="summary-icon">🚚</div>

    </div>


    <!-- SEARCH -->

    <div class="search-box">

        <form method="GET">

            <input
                type="text"
                name="search"
                placeholder="Invoice ID, Customer, Phone, Tracking Code বা Consignment ID"
                value="<?php echo e($search); ?>"
            >

            <button type="submit">Search</button>

            <a href="steadfast_entry.php" class="reset-btn">
                Reset
            </a>

        </form>

    </div>


    <!-- ENTRIES TABLE -->

    <div class="table-card">

        <div class="table-heading">

            <h2>Successfully Booked Orders</h2>

            <p>
                Steadfast-এ সফলভাবে Entry হওয়া অর্ডারগুলো এখানে দেখা যাবে।
            </p>

        </div>


        <div class="table-wrap">

            <table>

                <thead>

                    <tr>
                        <th>SL</th>
                        <th>Invoice / Courier Information</th>
                        <th>Customer</th>
                        <th>Product</th>
                        <th>Payable</th>
                        <th>Entry Status</th>
                        <th>Actions</th>
                    </tr>

                </thead>

                <tbody>

                <?php if ($result->num_rows > 0): ?>

                    <?php $sl = 1; ?>

                    <?php while ($order = $result->fetch_assoc()): ?>

                        <tr>

                            <td class="sl-number">
                                <?php echo $sl++; ?>
                            </td>


                            <!-- INVOICE AND TRACKING -->

                            <td>

                                <div class="invoice-number">
                                    Invoice ID:
                                    <?php echo e($order['order_number']); ?>
                                </div>

                                <div class="tracking-info">

                                    <strong>Tracking Code:</strong><br>

                                    <?php if (!empty($order['steadfast_tracking_code'])): ?>

                                        <?php echo e($order['steadfast_tracking_code']); ?>

                                        <button
                                            type="button"
                                            class="copy-btn"
                                            onclick="copyText('<?php echo e($order['steadfast_tracking_code']); ?>')"
                                        >Copy</button>

                                    <?php else: ?>

                                        N/A

                                    <?php endif; ?>

                                    <br>

                                    <strong>Consignment ID:</strong><br>

                                    <?php echo e($order['steadfast_consignment_id']); ?>

                                    <button
                                        type="button"
                                        class="copy-btn"
                                        onclick="copyText('<?php echo e($order['steadfast_consignment_id']); ?>')"
                                    >Copy</button>

                                </div>

                            </td>


                            <!-- CUSTOMER -->

                            <td>

                                <div class="customer-name">
                                    <?php echo e($order['customer_name']); ?>
                                </div>

                                <div class="customer-phone">
                                    <?php echo e($order['phone']); ?>
                                </div>

                                <div style="margin-top:7px;color:#8993a4;line-height:1.5;">
                                    <?php echo e($order['area']); ?>
                                </div>

                            </td>


                            <!-- PRODUCTS -->

                            <td>

                                <div class="product-info">
                                    <?php echo e($order['products'] ?? ''); ?>
                                </div>

                            </td>


                            <!-- PAYABLE -->

                            <td>

                                <div class="amount">
                                    ৳<?php echo number_format((float)$order['total_amount'], 2); ?>
                                </div>

                                <small style="color:#8993a4;">
                                    <?php echo e($order['payment_method'] ?? 'COD'); ?>
                                </small>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span class="entry-status">
                                    Entry Complete
                                </span>

                                <div style="margin-top:7px;color:#8993a4;font-size:10px;">
                                    <?php echo e($order['steadfast_status'] ?? ''); ?>
                                </div>

                            </td>


                            <!-- ACTIONS -->

                            <td>

                                <div class="action-links">

                                    <a
                                        href="view_order.php?id=<?php echo (int)$order['id']; ?>"
                                        class="details-btn"
                                    >
                                        Details
                                    </a>

                                    <a
                                        href="print_invoice.php?id=<?php echo (int)$order['id']; ?>"
                                        class="print-btn"
                                    >
                                        Print Invoice
                                    </a>

                                    <?php if (!empty($order['steadfast_tracking_url'])): ?>

                                        <a
                                            href="<?php echo e($order['steadfast_tracking_url']); ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="track-btn"
                                        >
                                            Track Parcel
                                        </a>

                                    <?php endif; ?>

                                </div>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="7" class="empty-state">
                            এখনো কোনো সফল Steadfast Entry পাওয়া যায়নি।
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


    <div class="footer">
        © <?php echo date('Y'); ?> BrandNest | Steadfast Entry Management
    </div>

</div>


<script>

function copyText(value) {

    if (navigator.clipboard && window.isSecureContext) {

        navigator.clipboard.writeText(value)
            .then(() => alert("Copied: " + value))
            .catch(() => alert("Copy করা যায়নি।"));

    } else {

        const input = document.createElement("textarea");

        input.value = value;
        document.body.appendChild(input);

        input.select();
        document.execCommand("copy");

        input.remove();

        alert("Copied: " + value);
    }

}

</script>

</body>
</html>
