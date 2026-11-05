
<?php
session_start();
require_once "config/database.php";

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['register_token'])) {
    $_SESSION['register_token'] = bin2hex(random_bytes(32));
}

$error = "";
$success = "";

$name = "";
$email = "";
$phone = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['register_token'] ?? '';

    if (!hash_equals($_SESSION['register_token'], $token)) {
        $error = "Invalid request. Please refresh the page.";
    } else {

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($name === "" || $email === "" || $phone === "" ||
            $password === "" || $confirmPassword === "") {

            $error = "Please fill in all fields.";

        } elseif (strlen($name) > 150) {

            $error = "Name is too long.";

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $error = "Please enter a valid email address.";

        } elseif (strlen($email) > 150) {

            $error = "Email address is too long.";

        } elseif (!preg_match('/^[0-9+\-\s]{10,20}$/', $phone)) {

            $error = "Please enter a valid phone number.";

        } elseif (strlen($password) < 8) {

            $error = "Password must contain at least 8 characters.";

        } elseif ($password !== $confirmPassword) {

            $error = "Passwords do not match.";

        } else {

            // Check existing email
            $stmt = $conn->prepare("
                SELECT id FROM customers
                WHERE email = ?
                LIMIT 1
            ");

            $stmt->bind_param("s", $email);
            $stmt->execute();

            $existingCustomer = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($existingCustomer) {

                $error = "This email is already registered.";

            } else {

                // Hash password
                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                // Insert customer
                $stmt = $conn->prepare("
                    INSERT INTO customers
                    (name, email, phone, password)
                    VALUES (?, ?, ?, ?)
                ");

                $stmt->bind_param(
                    "ssss",
                    $name,
                    $email,
                    $phone,
                    $hashedPassword
                );

                if ($stmt->execute()) {

                    $stmt->close();

                    $_SESSION['register_token'] = bin2hex(random_bytes(32));

                    header("Location: login.php?registered=1");
                    exit;

                } else {

                    $error = "Registration failed. Please try again.";
                    $stmt->close();
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Customer Registration | BrandNest</title>

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

        .customer-auth-error {
            padding: 12px;
            background: #401d1d;
            color: #ffaaaa;
            border-radius: 5px;
            margin-bottom: 15px;
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

        <h1>Create Account</h1>

        <p class="customer-auth-subtitle">
            Join BrandNest and enjoy shopping.
        </p>

        <?php if ($error): ?>
            <div class="customer-auth-error">
                <?php echo e($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php">

            <input
                type="hidden"
                name="register_token"
                value="<?php echo e($_SESSION['register_token']); ?>"
            >

            <label>Full Name</label>

            <input
                type="text"
                name="name"
                placeholder="Enter your full name"
                value="<?php echo e($name); ?>"
                maxlength="150"
                autocomplete="name"
                required
            >

            <label>Email Address</label>

            <input
                type="email"
                name="email"
                placeholder="Enter your email"
                value="<?php echo e($email); ?>"
                maxlength="150"
                autocomplete="email"
                required
            >

            <label>Phone Number</label>

            <input
                type="tel"
                name="phone"
                placeholder="Enter your phone number"
                value="<?php echo e($phone); ?>"
                maxlength="20"
                autocomplete="tel"
                required
            >

            <label>Password</label>

            <input
                type="password"
                name="password"
                placeholder="Minimum 8 characters"
                minlength="8"
                autocomplete="new-password"
                required
            >

            <label>Confirm Password</label>

            <input
                type="password"
                name="confirm_password"
                placeholder="Enter password again"
                minlength="8"
                autocomplete="new-password"
                required
            >

            <button type="submit" class="customer-auth-submit">
                Create Account
            </button>

        </form>

        <div class="customer-auth-footer">
            Already have an account?
            <a href="login.php">Login</a>
        </div>

        <div class="customer-auth-footer">
            <a href="index.php">Back to Shopping</a>
        </div>

    </div>

</div>

</body>
</html>
