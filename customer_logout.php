
<?php
session_start();

// Remove only customer login information.
// Keep the shopping cart in the session.
unset(
    $_SESSION['customer_id'],
    $_SESSION['customer_name'],
    $_SESSION['customer_email'],
    $_SESSION['customer_phone'],
    $_SESSION['login_token']
);

session_regenerate_id(true);

header("Location: index.php");
exit;
