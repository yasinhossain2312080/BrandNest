
<?php
session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit;
}

require_once "../config/database.php";

$message = "";
$error = "";

// Add Category
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_category"])) {

    $name = trim($_POST["name"]);
    $description = trim($_POST["description"]);

    if ($name === "") {
        $error = "Category name is required.";
    } else {

        // Create slug
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        // Check duplicate slug
        $check = $conn->prepare("SELECT id FROM categories WHERE slug = ?");
        $check->bind_param("s", $slug);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $error = "This category already exists.";
        } else {

            $stmt = $conn->prepare(
                "INSERT INTO categories (name, slug, description) VALUES (?, ?, ?)"
            );

            $stmt->bind_param("sss", $name, $slug, $description);

            if ($stmt->execute()) {
                $message = "Category added successfully!";
            } else {
                $error = "Something went wrong.";
            }
        }
    }
}

// Fetch Categories
$result = $conn->query("SELECT * FROM categories ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Management - BrandNest</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            background: #111;
            color: #fff;
            font-family: Arial, sans-serif;
            padding: 30px;
        }

        .container {
            max-width: 1000px;
            margin: auto;
        }

        h1 {
            color: #c9a45c;
            margin-bottom: 25px;
        }

        .form-box, .table-box {
            background: #1b1b1b;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 25px;
        }

        input, textarea {
            width: 100%;
            padding: 12px;
            margin: 10px 0 18px;
            background: #292929;
            border: 1px solid #444;
            color: white;
            border-radius: 5px;
        }

        textarea {
            resize: vertical;
        }

        button {
            background: #c9a45c;
            color: #111;
            border: none;
            padding: 12px 22px;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 14px;
            border-bottom: 1px solid #333;
            text-align: left;
        }

        th {
            color: #c9a45c;
        }

        .success {
            color: #5cdb85;
            margin-bottom: 15px;
        }

        .error {
            color: #ff7777;
            margin-bottom: 15px;
        }

        .back-link {
            display: inline-block;
            color: #c9a45c;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>

<div class="container">

    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

    <h1>Category Management</h1>

    <div class="form-box">

        <h2>Add New Category</h2>

        <?php if ($message): ?>
            <p class="success">
                <?php echo htmlspecialchars($message); ?>
            </p>
        <?php endif; ?>

        <?php if ($error): ?>
            <p class="error">
                <?php echo htmlspecialchars($error); ?>
            </p>
        <?php endif; ?>

        <form method="POST">

            <label>Category Name</label>
            <input type="text" name="name" placeholder="Example: Bedding" required>

            <label>Description</label>
            <textarea name="description" rows="4"
                placeholder="Category description"></textarea>

            <button type="submit" name="add_category">
                Add Category
            </button>

        </form>

    </div>

    <div class="table-box">

        <h2>All Categories</h2>

        <table>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Slug</th>
                <th>Description</th>
            </tr>

            <?php if ($result->num_rows > 0): ?>

                <?php while ($category = $result->fetch_assoc()): ?>

                    <tr>
                        <td><?php echo $category["id"]; ?></td>
                        <td><?php echo htmlspecialchars($category["name"]); ?></td>
                        <td><?php echo htmlspecialchars($category["slug"]); ?></td>
                        <td><?php echo htmlspecialchars($category["description"] ?? ""); ?></td>
                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="4">No categories found.</td>
                </tr>

            <?php endif; ?>

        </table>

    </div>

</div>

</body>
</html>
