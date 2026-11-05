
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = !empty($_SESSION['customer_id']);
?>

<header class="site-header">
    <div class="container nav-wrapper">

        <!-- Brand Logo -->
        <a href="/brandnest/" class="logo">
            Brand<span>Nest</span>
        </a>

        <!-- Navigation -->
        <nav class="main-nav">

            <a href="/brandnest/">Home</a>

            <a href="/brandnest/?category=bedding">
                Bedding
            </a>

            <a href="/brandnest/?category=clothing">
                Clothing
            </a>

            <?php if ($isLoggedIn): ?>
                <a href="/brandnest/my_orders.php">
                    My Orders
                </a>
            <?php endif; ?>

        </nav>

        <!-- Header Actions -->
        <div class="header-actions">

            <a href="/brandnest/cart.php" class="cart-link">
                Cart
            </a>

        </div>

    </div>
</header>

<style>

/* Header */
.site-header {
    width: 100%;
    background: #151515;
    border-bottom: 1px solid #292929;
    position: relative;
    z-index: 100;
}

/* Main Navigation Wrapper */
.site-header .nav-wrapper {
    width: 100%;
    max-width: 1500px;
    min-height: 75px;
    margin: 0 auto;
    padding: 12px 25px;
    box-sizing: border-box;

    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 25px;
}

/* Logo */
.site-header .logo {
    color: #ffffff;
    font-size: 32px;
    font-weight: 800;
    text-decoration: none;
    white-space: nowrap;
}

.site-header .logo span {
    color: #c9a45c;
}

/* Navigation */
.site-header .main-nav {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 28px;
    flex-wrap: wrap;
}

.site-header .main-nav a {
    color: #eeeeee;
    text-decoration: none;
    font-size: 16px;
    font-weight: 500;
    transition: color .3s ease;
}

.site-header .main-nav a:hover {
    color: #c9a45c;
}

/* Header Actions */
.site-header .header-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
}

.site-header .cart-link {
    display: inline-flex;
    justify-content: center;
    align-items: center;

    background: #c9a45c;
    color: #111111;

    padding: 12px 25px;
    border-radius: 6px;

    text-decoration: none;
    font-weight: 700;
    transition: .3s ease;
    white-space: nowrap;
}

.site-header .cart-link:hover {
    background: #e0bd75;
    transform: translateY(-2px);
}

/* Tablet */
@media (max-width: 900px) {

    .site-header .nav-wrapper {
        gap: 15px;
    }

    .site-header .main-nav {
        gap: 16px;
    }

    .site-header .main-nav a {
        font-size: 14px;
    }

    .site-header .logo {
        font-size: 27px;
    }

}

/* Mobile */
@media (max-width: 600px) {

    .site-header .nav-wrapper {
        display: flex;
        flex-direction: row;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: 12px;

        padding: 12px 15px;
    }

    .site-header .logo {
        font-size: 26px;
    }

    .site-header .header-actions {
        margin-left: auto;
    }

    .site-header .cart-link {
        padding: 10px 19px;
        font-size: 14px;
    }

    .site-header .main-nav {
        width: 100%;
        display: flex;
        justify-content: center;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px 20px;
        order: 3;
    }

    .site-header .main-nav a {
        font-size: 14px;
    }

}

/* Small Mobile */
@media (max-width: 360px) {

    .site-header .logo {
        font-size: 23px;
    }

    .site-header .main-nav {
        gap: 10px 14px;
    }

    .site-header .main-nav a {
        font-size: 13px;
    }

}

</style>
