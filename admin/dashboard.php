<?php
session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | BrandNest</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

    <div class="container" style="padding: 50px 0;">
        <h1>Welcome to BrandNest Admin Panel</h1>

        <p style="margin-top: 15px;">
            Hello, <?= htmlspecialchars($_SESSION["admin_name"]) ?>!
        </p>

        <h2 style="margin-top: 40px;">Dashboard</h2>

        <p style="margin-top: 15px;">Your admin panel is ready.</p>

        <a href="logout.php"
           style="display:inline-block; margin-top:25px; color:#c9a45c;">
            Logout
        </a>
    </div>

</body>
</html>