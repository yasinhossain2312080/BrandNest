
<?php
session_start();
require_once "../config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"]);
    $password = $_POST["password"];

    $stmt = $conn->prepare(
        "SELECT id, name, password FROM admins WHERE username = ?"
    );

    $stmt->bind_param("s", $username);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($admin = $result->fetch_assoc()) {

        if (password_verify($password, $admin["password"])) {

            session_regenerate_id(true);

            $_SESSION["admin_id"] = $admin["id"];
            $_SESSION["admin_name"] = $admin["name"];

            header("Location: dashboard.php");
            exit;

        }
    }

    $error = "Invalid username or password.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | BrandNest</title>

    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

    <div class="login-container">

        <form class="login-box" method="POST">

            <h1>Brand<span>Nest</span></h1>
            <p>Admin Panel Login</p>

            <?php if ($error): ?>
                <p class="login-error">
                    <?= htmlspecialchars($error) ?>
                </p>
            <?php endif; ?>

            <label>Username</label>
            <input type="text" name="username" required>

            <label>Password</label>
            <input type="password" name="password" required>

            <button type="submit">Login</button>

        </form>

    </div>

</body>
</html>
