
<?php
session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

require_once "../config/database.php";

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$adminName = $_SESSION["admin_name"] ?? "Admin";

// Dashboard Statistics
$stats = [
    'total' => 0,
    'pending' => 0,
    'confirmed' => 0,
    'processing' => 0,
    'shipped' => 0,
    'delivered' => 0,
    'cancelled' => 0
];

$statsQuery = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(order_status = 'pending') AS pending,
        SUM(order_status = 'confirmed') AS confirmed,
        SUM(order_status = 'processing') AS processing,
        SUM(order_status = 'shipped') AS shipped,
        SUM(order_status = 'delivered') AS delivered,
        SUM(order_status = 'cancelled') AS cancelled
    FROM orders
");

if ($statsQuery) {
    $statsData = $statsQuery->fetch_assoc();

    foreach ($stats as $key => $value) {
        $stats[$key] = (int)($statsData[$key] ?? 0);
    }
}

// Total Sales
$salesQuery = $conn->query("
    SELECT COALESCE(SUM(total_amount), 0) AS total_sales
    FROM orders
    WHERE order_status = 'delivered'
");

$totalSales = 0;

if ($salesQuery) {
    $totalSales = (float)$salesQuery->fetch_assoc()['total_sales'];
}

// Recent Orders
$recentOrders = [];

$ordersQuery = $conn->query("
    SELECT
        id,
        order_number,
        customer_name,
        phone,
        total_amount,
        order_status,
        payment_method,
        created_at
    FROM orders
    ORDER BY created_at DESC
    LIMIT 10
");

if ($ordersQuery) {
    while ($row = $ordersQuery->fetch_assoc()) {
        $recentOrders[] = $row;
    }
}

$statusLabels = [
    'pending' => 'New Booking',
    'confirmed' => 'Confirmed',
    'processing' => 'Processing',
    'shipped' => 'Shipped',
    'delivered' => 'Delivered',
    'cancelled' => 'Cancelled'
];

$statusClasses = [
    'pending' => 'pending',
    'confirmed' => 'confirmed',
    'processing' => 'processing',
    'shipped' => 'shipped',
    'delivered' => 'delivered',
    'cancelled' => 'cancelled'
];
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Admin Dashboard | BrandNest</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>

:root {
    --navy: #172b4d;
    --navy-dark: #10213d;
    --gold: #c9a45c;
    --background: #f5f6fa;
    --white: #ffffff;
    --text: #263449;
    --muted: #8993a4;
    --border: #e9edf3;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', Arial, sans-serif;
    background: var(--background);
    color: var(--text);
}

a {
    text-decoration: none;
    color: inherit;
}

/* LAYOUT */

.dashboard-layout {
    display: flex;
    min-height: 100vh;
}

/* SIDEBAR */

.sidebar {
    width: 250px;
    background: var(--navy-dark);
    color: white;
    padding: 25px 17px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
}

.logo {
    padding: 5px 12px 28px;
    border-bottom: 1px solid rgba(255,255,255,0.12);
    margin-bottom: 25px;
}

.logo h2 {
    color: var(--gold);
    font-size: 25px;
    letter-spacing: 1px;
}

.logo p {
    font-size: 10px;
    color: #c4cbd6;
    letter-spacing: 2px;
    margin-top: 5px;
}

.menu-label {
    color: #8795ab;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 1.5px;
    padding: 0 12px;
    margin-bottom: 12px;
}

.sidebar-menu {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.sidebar-menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border-radius: 8px;
    color: #d1d8e4;
    font-size: 13px;
    transition: 0.2s;
}

.sidebar-menu a:hover,
.sidebar-menu a.active {
    background: rgba(201,164,92,0.16);
    color: var(--gold);
}

.menu-icon {
    width: 20px;
    text-align: center;
    font-size: 16px;
}

.sidebar-bottom {
    margin-top: auto;
    padding-top: 25px;
}

.admin-profile {
    border-top: 1px solid rgba(255,255,255,0.12);
    padding: 18px 8px;
    margin-bottom: 10px;
}

.admin-profile strong {
    display: block;
    font-size: 12px;
    color: white;
    overflow-wrap: anywhere;
}

.admin-profile span {
    font-size: 10px;
    color: #9ba8ba;
    display: block;
    margin-top: 5px;
}

.logout-link {
    display: block;
    padding: 12px;
    background: rgba(255,255,255,0.08);
    border-radius: 8px;
    color: #ffb4b4;
    font-size: 13px;
}

/* MAIN */

.main-content {
    flex: 1;
    min-width: 0;
    padding: 30px;
}

/* TOPBAR */

.topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 30px;
}

.welcome h1 {
    color: var(--navy);
    font-size: 24px;
    font-weight: 800;
}

.welcome p {
    color: var(--muted);
    font-size: 12px;
    margin-top: 7px;
}

.topbar-right {
    display: flex;
    align-items: center;
    gap: 12px;
}

.admin-badge {
    background: white;
    border: 1px solid var(--border);
    padding: 10px 15px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    color: var(--navy);
}

/* SUMMARY */

.section-heading {
    font-size: 16px;
    font-weight: 700;
    color: var(--navy);
    margin-bottom: 16px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 28px;
}

.stat-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 19px;
    transition: 0.2s;
    position: relative;
    overflow: hidden;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 22px rgba(23,43,77,0.07);
}

.stat-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
}

.stat-name {
    font-size: 11px;
    color: var(--muted);
    font-weight: 600;
}

.stat-icon {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
}

.stat-number {
    font-size: 27px;
    font-weight: 800;
    color: var(--navy);
    margin-top: 13px;
}

.stat-description {
    font-size: 10px;
    color: var(--muted);
    margin-top: 5px;
}

.icon-blue {
    background: #eaf1ff;
    color: #3974d9;
}

.icon-green {
    background: #e5f7ee;
    color: #169b59;
}

.icon-orange {
    background: #fff3df;
    color: #d99525;
}

.icon-red {
    background: #ffeded;
    color: #dc5252;
}

.icon-purple {
    background: #f0eaff;
    color: #8659d7;
}

.icon-gold {
    background: #f8f0df;
    color: #b58a35;
}

/* SALES CARD */

.sales-card {
    background: linear-gradient(115deg, #172b4d, #243e67);
    color: white;
    padding: 23px;
    border-radius: 12px;
    margin-bottom: 28px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

.sales-card p {
    font-size: 12px;
    color: #cbd5e4;
}

.sales-card h2 {
    font-size: 27px;
    margin-top: 9px;
}

.sales-icon {
    width: 55px;
    height: 55px;
    border-radius: 12px;
    background: rgba(201,164,92,0.18);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 27px;
    color: var(--gold);
}

/* ORDERS TABLE */

.orders-section {
    background: white;
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
}

.orders-header {
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    border-bottom: 1px solid var(--border);
}

.orders-header h2 {
    font-size: 16px;
    color: var(--navy);
}

.orders-header p {
    color: var(--muted);
    font-size: 11px;
    margin-top: 5px;
}

.view-all {
    color: var(--gold);
    font-size: 12px;
    font-weight: 700;
}

.table-wrapper {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 750px;
}

thead {
    background: #f8f9fc;
}

th {
    text-align: left;
    padding: 14px 15px;
    color: #8993a4;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    white-space: nowrap;
}

td {
    padding: 15px;
    border-top: 1px solid var(--border);
    font-size: 11px;
    color: var(--text);
}

.customer-name {
    font-weight: 700;
    color: var(--navy);
}

.customer-phone {
    font-size: 10px;
    color: var(--muted);
    margin-top: 5px;
}

.order-number {
    color: var(--navy);
    font-weight: 700;
}

.status {
    display: inline-block;
    padding: 6px 9px;
    border-radius: 20px;
    font-size: 9px;
    font-weight: 700;
    white-space: nowrap;
}

.status-pending {
    background: #fff3df;
    color: #bb811c;
}

.status-confirmed {
    background: #e5f7ee;
    color: #16834d;
}

.status-processing {
    background: #eaf1ff;
    color: #3974d9;
}

.status-shipped {
    background: #f0eaff;
    color: #8659d7;
}

.status-delivered {
    background: #e1f5f3;
    color: #168d83;
}

.status-cancelled {
    background: #ffeded;
    color: #dc5252;
}

.action-btn {
    display: inline-block;
    padding: 7px 10px;
    border-radius: 6px;
    background: #f0f3f9;
    color: var(--navy);
    font-size: 10px;
    font-weight: 700;
}

.action-btn:hover {
    background: var(--navy);
    color: white;
}

.empty-state {
    padding: 40px 15px;
    text-align: center;
    color: var(--muted);
    font-size: 12px;
}

/* FOOTER */

.footer {
    text-align: center;
    color: #a0a8b5;
    font-size: 10px;
    padding: 25px 0 5px;
}

/* RESPONSIVE */

@media (max-width: 1100px) {
    .stats-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 800px) {
    .sidebar {
        width: 200px;
    }

    .main-content {
        padding: 22px 15px;
    }

    .stats-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}


/* MOBILE SIDEBAR BUTTON */
.mobile-menu-btn {
    display: none;
    border: none;
    background: var(--navy-dark);
    color: white;
    padding: 11px 16px;
    border-radius: 7px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    margin-bottom: 20px;
}

/* SIDEBAR OVERLAY */
.sidebar-overlay {
    display: none;
}

@media (max-width: 600px) {

    .dashboard-layout {
        display: block;
    }

    /* Slide Sidebar */
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: min(280px, 85vw);
        height: 100vh;
        height: 100dvh;
        overflow-y: auto;
        z-index: 1001;

        padding: 22px 17px;

        transform: translateX(-105%);
        transition: transform 0.3s ease;
    }

    .sidebar.mobile-open {
        transform: translateX(0);
    }

    /* Overlay */
    .sidebar-overlay {
        display: block;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.55);
        z-index: 1000;

        opacity: 0;
        visibility: hidden;
        transition: 0.3s ease;
    }

    .sidebar-overlay.show {
        opacity: 1;
        visibility: visible;
    }

    /* Restore Original Vertical Menu */
    .sidebar-menu {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .sidebar-menu a {
        padding: 13px 12px;
        font-size: 14px;
    }

    .sidebar-bottom {
        margin-top: 25px;
        padding-top: 15px;
    }

    .admin-profile {
        padding: 15px 8px;
    }

    /* Main Content */
    .main-content {
        padding: 20px 12px;
        min-width: 0;
    }

    /* Menu Button */
    .mobile-menu-btn {
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }

    .topbar {
        align-items: flex-start;
        flex-direction: column;
        margin-bottom: 25px;
    }

    .welcome h1 {
        font-size: 20px;
    }

    .stats-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .stat-card {
        padding: 13px;
    }

    .stat-number {
        font-size: 23px;
    }

    .sales-card {
        padding: 17px;
    }

    .orders-header {
        padding: 15px;
    }

    /* Prevent Background Scrolling */
    body.sidebar-open {
        overflow: hidden;
    }
}


</style>
</head>

<body>

<div class="dashboard-layout">

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <div class="logo">
            <h2>BrandNest</h2>
            <p>ADMIN CONTROL PANEL</p>
        </div>

        <div class="menu-label">MAIN MENU</div>

        
<nav class="sidebar-menu">

    <a href="dashboard.php" class="active">
        <span class="menu-icon">⌂</span>
        Dashboard
    </a>

    <a href="orders.php">
        <span class="menu-icon">▤</span>
        All Orders
    </a>

    <a href="customers.php">
        <span class="menu-icon">👥</span>
        Customers
    </a>

    <a href="steadfast_entry.php">
        <span class="menu-icon">🚚</span>
        Steadfast Entry
    </a>

    <a href="products.php">
        <span class="menu-icon">▣</span>
        Products
    </a>

    <a href="categories.php">
        <span class="menu-icon">▦</span>
        Categories
    </a>

    <a href="../index.php" target="_blank" rel="noopener">
        <span class="menu-icon">🛍</span>
        Shopping Page
    </a>

</nav>


        <div class="sidebar-bottom">

            <div class="admin-profile">
                <strong><?php echo e($adminName); ?></strong>
                <span>Administrator</span>
            </div>

            <a href="logout.php" class="logout-link">
                ↪ &nbsp; Logout
            </a>

        </div>

    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- MAIN CONTENT -->

    <main class="main-content">

    <button type="button" class="mobile-menu-btn"
        id="mobileMenuBtn"
        aria-label="Open navigation menu"
        aria-expanded="false">
    ☰ <span>Menu</span>
</button>

        <!-- TOPBAR -->

        <div class="topbar">

            <div class="welcome">
                <h1>Dashboard Overview</h1>
                <p>Welcome back, <?php echo e($adminName); ?>! Here's your store overview.</p>
            </div>

            <div class="topbar-right">
                <div class="admin-badge">
                    ● &nbsp; Admin Account
                </div>
            </div>

        </div>

        <!-- ORDER STATISTICS -->

        <h2 class="section-heading">Order Statistics</h2>

        <div class="stats-grid">

            <div class="stat-card">
                <div class="stat-top">
                    <span class="stat-name">Total Orders</span>
                    <span class="stat-icon icon-blue">▤</span>
                </div>
                <div class="stat-number">
                    <?php echo number_format($stats['total']); ?>
                </div>
                <div class="stat-description">All received orders</div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <span class="stat-name">New Booking</span>
                    <span class="stat-icon icon-orange">🛒</span>
                </div>
                <div class="stat-number">
                    <?php echo number_format($stats['pending']); ?>
                </div>
                <div class="stat-description">Pending orders</div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <span class="stat-name">Confirmed</span>
                    <span class="stat-icon icon-green">✓</span>
                </div>
                <div class="stat-number">
                    <?php echo number_format($stats['confirmed']); ?>
                </div>
                <div class="stat-description">Confirmed orders</div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <span class="stat-name">Processing</span>
                    <span class="stat-icon icon-purple">⚙</span>
                </div>
                <div class="stat-number">
                    <?php echo number_format($stats['processing']); ?>
                </div>
                <div class="stat-description">Orders being prepared</div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <span class="stat-name">Shipped</span>
                    <span class="stat-icon icon-blue">🚚</span>
                </div>
                <div class="stat-number">
                    <?php echo number_format($stats['shipped']); ?>
                </div>
                <div class="stat-description">Orders on the way</div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <span class="stat-name">Delivered</span>
                    <span class="stat-icon icon-green">✓</span>
                </div>
                <div class="stat-number">
                    <?php echo number_format($stats['delivered']); ?>
                </div>
                <div class="stat-description">Successfully delivered</div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <span class="stat-name">Cancelled</span>
                    <span class="stat-icon icon-red">✕</span>
                </div>
                <div class="stat-number">
                    <?php echo number_format($stats['cancelled']); ?>
                </div>
                <div class="stat-description">Cancelled orders</div>
            </div>

        </div>

        <!-- SALES -->

        <div class="sales-card">

            <div>
                <p>Total Delivered Sales</p>

                <h2>
                    ৳ <?php echo number_format($totalSales, 2); ?>
                </h2>

                <p style="margin-top:7px;">
                    Total amount of delivered orders
                </p>
            </div>

            <div class="sales-icon">
                ৳
            </div>

        </div>

        <!-- RECENT ORDERS -->

        <div class="orders-section">

            <div class="orders-header">

                <div>
                    <h2>Recent Orders</h2>
                    <p>Latest 10 orders received by your store</p>
                </div>

                <a href="orders.php" class="view-all">
                    View All Orders →
                </a>

            </div>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (empty($recentOrders)): ?>

                        <tr>
                            <td colspan="7" class="empty-state">
                                No orders found yet.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($recentOrders as $order): ?>

                            <?php
                            $status = $order['order_status'] ?? 'pending';
                            $statusLabel = $statusLabels[$status] ?? ucfirst($status);
                            $statusClass = $statusClasses[$status] ?? 'pending';
                            ?>

                            <tr>

                                <td>
                                    <span class="order-number">
                                        <?php echo e($order['order_number']); ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="customer-name">
                                        <?php echo e($order['customer_name']); ?>
                                    </div>

                                    <div class="customer-phone">
                                        <?php echo e($order['phone']); ?>
                                    </div>
                                </td>

                                <td>
                                    <?php
                                    echo !empty($order['created_at'])
                                        ? date('d M Y', strtotime($order['created_at']))
                                        : 'N/A';
                                    ?>
                                </td>

                                <td>
                                    <strong>
                                        ৳<?php echo number_format((float)$order['total_amount'], 2); ?>
                                    </strong>
                                </td>

                                <td>
                                    <?php echo e($order['payment_method'] ?? 'COD'); ?>
                                </td>

                                <td>
                                    <span class="status status-<?php echo e($statusClass); ?>">
                                        <?php echo e($statusLabel); ?>
                                    </span>
                                </td>

                                <td>
                                    <a
                                        href="view_order.php?id=<?php echo (int)$order['id']; ?>"
                                        class="action-btn"
                                    >
                                        View Details
                                    </a>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

        <div class="footer">
            © <?php echo date('Y'); ?> BrandNest. All Rights Reserved.
        </div>

    </main>

</div>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const sidebar = document.querySelector(".sidebar");
    const menuButton = document.getElementById("mobileMenuBtn");
    const overlay = document.getElementById("sidebarOverlay");

    // প্রয়োজনীয় HTML element না থাকলে বন্ধ হবে
    if (!sidebar || !menuButton || !overlay) {
        return;
    }

    function openSidebar() {
        sidebar.classList.add("mobile-open");
        overlay.classList.add("show");
        document.body.classList.add("sidebar-open");
        menuButton.setAttribute("aria-expanded", "true");
    }

    function closeSidebar() {
        sidebar.classList.remove("mobile-open");
        overlay.classList.remove("show");
        document.body.classList.remove("sidebar-open");
        menuButton.setAttribute("aria-expanded", "false");
    }

    menuButton.addEventListener("click", function () {
        if (sidebar.classList.contains("mobile-open")) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    overlay.addEventListener("click", closeSidebar);

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            closeSidebar();
        }
    });

    sidebar.querySelectorAll(".sidebar-menu a, .logout-link")
        .forEach(function (link) {
            link.addEventListener("click", function () {
                if (window.innerWidth <= 600) {
                    closeSidebar();
                }
            });
        });

    window.addEventListener("resize", function () {
        if (window.innerWidth > 600) {
            closeSidebar();
        }
    });

});
</script>

</body>
</html>