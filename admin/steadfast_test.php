
<?php
session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

$config = require "../config/steadfast.php";

$url = rtrim($config["base_url"], "/") . "/get_balance";

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Api-Key: " . $config["api_key"],
        "Secret-Key: " . $config["secret_key"],
        "Content-Type: application/json"
    ],
    CURLOPT_TIMEOUT => 20
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Steadfast Connection Test</title>
</head>
<body>

<h2>Steadfast API Connection Test</h2>

<?php if ($response === false): ?>

    <p>Connection Error: <?php echo htmlspecialchars($error); ?></p>

<?php else: ?>

    <p>HTTP Status: <?php echo (int)$httpCode; ?></p>

    <h3>API Response:</h3>

    <pre><?php
        $data = json_decode($response, true);

        echo htmlspecialchars(
            json_encode(
                $data ?? $response,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            )
        );
    ?></pre>

<?php endif; ?>

</body>
</html>
