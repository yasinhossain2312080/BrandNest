
<?php
session_start();
require_once "config/database.php";

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Already logged in
if (!empty($_SESSION['customer_id'])) {
    header("Location: index.php");
    exit;
}

// CSRF token
if (empty($_SESSION['login_token'])) {
    $_SESSION['login_token'] = bin2hex(random_bytes(32));
}

$error = "";
$identifier = "";

// Login Processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['login_token'] ?? '';

    if (!hash_equals($_SESSION['login_token'], $token)) {

        $error = "Invalid request. Please refresh the page.";

    } else {

        $identifier = trim($_POST['identifier'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($identifier === "" || $password === "") {

            $error = "Please enter your email or phone number and password.";

        } else {

            // Find customer using Email OR Phone
            $stmt = $conn->prepare("
                SELECT id, name, email, phone, password
                FROM customers
                WHERE email = ? OR phone = ?
                LIMIT 1
            ");

            $stmt->bind_param("ss", $identifier, $identifier);
            $stmt->execute();

            $customer = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($customer && password_verify($password, $customer['password'])) {

                // Regenerate session ID
                session_regenerate_id(true);

                // Customer Session
                $_SESSION['customer_id'] = (int)$customer['id'];
                $_SESSION['customer_name'] = $customer['name'];
                $_SESSION['customer_email'] = $customer['email'];
                $_SESSION['customer_phone'] = $customer['phone'];

                unset($_SESSION['login_token']);

                // Redirect after login
                $next = $_POST['next'] ?? '';

                if ($next === 'checkout') {
                    header("Location: checkout.php");
                } else {
                    header("Location: index.php");
                }

                exit;

            } else {

                $error = "Incorrect email/phone number or password.";

            }
        }
    }
}

$registered = isset($_GET['registered']);

$next = ($_GET['next'] ?? '') === 'checkout'
    ? 'checkout'
    : '';
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Customer Login | BrandNest</title>

    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .customer-auth-page {
            min-height: 100vh;
            padding: 50px 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #101010;
        }

        .customer-auth-box {
            width: 100%;
            max-width: 450px;
            padding: 35px;
            background: #191919;
            border: 1px solid #393126;
            border-radius: 10px;
        }

        .customer-auth-logo {
            text-align: center;
            margin-bottom: 25px;
        }

        .customer-auth-logo a {
            font-size: 29px;
            font-weight: bold;
            color: white;
        }

        .customer-auth-logo span {
            color: #c9a45c;
        }

        .customer-auth-box h1 {
            text-align: center;
            margin-bottom: 10px;
            color: white;
        }

        .customer-auth-subtitle {
            text-align: center;
            color: #aaa;
            margin-bottom: 25px;
        }

        .customer-auth-box label {
            display: block;
            color: #ddd;
            margin: 16px 0 8px;
            font-size: 14px;
        }

        .customer-auth-box input {
            width: 100%;
            padding: 13px;
            background: #252525;
            color: white;
            border: 1px solid #444;
            border-radius: 5px;
            outline: none;
        }

        .customer-auth-box input:focus {
            border-color: #c9a45c;
        }

        .customer-auth-submit {
            width: 100%;
            padding: 14px;
            margin-top: 25px;
            background: #c9a45c;
            color: #111;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
        }

        .customer-auth-submit:hover {
            background: #e0bd75;
        }

        .customer-auth-error,
        .customer-auth-success {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .customer-auth-error {
            background: #401d1d;
            color: #ffaaaa;
        }

        .customer-auth-success {
            background: #203323;
            color: #a5e6ad;
        }

        .customer-auth-footer {
            text-align: center;
            color: #aaa;
            margin-top: 22px;
            font-size: 14px;
        }

        .customer-auth-footer a {
            color: #c9a45c;
            font-weight: bold;
        }

        @media(max-width: 480px) {
            .customer-auth-box {
                padding: 25px 20px;
            }
        }
    </style>

</head>

<body>

<div class="customer-auth-page">

    <div class="customer-auth-box">

        <div class="customer-auth-logo">
            <a href="index.php">
                Brand<span>Nest</span>
            </a>
        </div>

        <h1>Welcome Back</h1>

        <p class="customer-auth-subtitle">
            Login to your BrandNest account.
        </p>

        <?php if ($registered): ?>

            <div class="customer-auth-success">
                Account created successfully! Please login.
            </div>

        <?php endif; ?>

        <?php if ($error): ?>

            <div class="customer-auth-error">
                <?php echo e($error); ?>
            </div>

        <?php endif; ?>

        <form method="POST" action="login.php">

            <input
                type="hidden"
                name="login_token"
                value="<?php echo e($_SESSION['login_token']); ?>"
            >

            <input
                type="hidden"
                name="next"
                value="<?php echo e($next); ?>"
            >

            <label>Email Address or Phone Number</label>

            <input
                type="text"
                name="identifier"
                placeholder="Enter email or phone number"
                value="<?php echo e($identifier); ?>"
                autocomplete="username"
                required
            >

            <label>Password</label>

            <input
                type="password"
                name="password"
                placeholder="Enter your password"
                autocomplete="current-password"
                required
            >

            <button type="submit" class="customer-auth-submit">
                Login
            </button>

        </form>

        <div class="customer-auth-footer">
            Don't have an account?
            <a href="register.php">Create Account</a>
        </div>

        <div class="customer-auth-footer">
            <a href="index.php">Back to Shopping</a>
        </div>

    </div>

</div>

</body>
</html>
