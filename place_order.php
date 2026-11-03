
<?php
require_once "config/database.php";

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$productId = isset($_GET['product'])
    ? (int)$_GET['product']
    : (int)($_POST['product_id'] ?? 0);

// পণ্যের তথ্য সংগ্রহ
$stmt = $conn->prepare("
    SELECT * FROM products
    WHERE id = ? AND status = 1
    LIMIT 1
");

$stmt->bind_param("i", $productId);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    die("দুঃখিত! পণ্যটি পাওয়া যায়নি।");
}

$price = (
    $product['discount_price'] !== null &&
    $product['discount_price'] > 0
) ? (float)$product['discount_price'] : (float)$product['price'];

$error = "";
$success = false;
$orderNumber = "";

// অর্ডার জমা
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $customerName = trim($_POST['customer_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $area = trim($_POST['area'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 1);
    $deliveryZone = $_POST['delivery_zone'] ?? '';

    $phoneDigits = preg_replace('/\D/', '', $phone);

    if (
        $customerName === '' ||
        $phone === '' ||
        $area === '' ||
        $address === ''
    ) {
        $error = "অনুগ্রহ করে সব তথ্য পূরণ করুন।";

    } elseif (strlen($phoneDigits) < 10 || strlen($phoneDigits) > 15) {
        $error = "সঠিক মোবাইল নম্বর লিখুন।";

    } elseif ($quantity < 1) {
        $error = "সঠিক পরিমাণ নির্বাচন করুন।";

    } elseif ($quantity > (int)$product['stock']) {
        $error = "দুঃখিত! পর্যাপ্ত পণ্য স্টকে নেই।";

    } elseif (!in_array($deliveryZone, ['inside', 'outside'], true)) {
        $error = "অনুগ্রহ করে ডেলিভারির এলাকা নির্বাচন করুন।";

    } else {

        $subtotal = $price * $quantity;

        // ডেলিভারি চার্জ
        $deliveryCharge = ($deliveryZone === 'inside') ? 80 : 150;

        $totalAmount = $subtotal + $deliveryCharge;

        $orderNumber = "BN" . date("ymdHis") . random_int(100, 999);

        try {

            $conn->begin_transaction();

            // অর্ডারের তথ্য সংরক্ষণ
            $stmt = $conn->prepare("
                INSERT INTO orders
                (
                    order_number,
                    customer_name,
                    phone,
                    address,
                    area,
                    subtotal,
                    delivery_charge,
                    total_amount,
                    payment_method
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'COD')
            ");

            $stmt->bind_param(
                "sssssddd",
                $orderNumber,
                $customerName,
                $phone,
                $address,
                $area,
                $subtotal,
                $deliveryCharge,
                $totalAmount
            );

            $stmt->execute();
            $orderId = $conn->insert_id;
            $stmt->close();

            // অর্ডারের পণ্যের তথ্য সংরক্ষণ
            $stmt = $conn->prepare("
                INSERT INTO order_items
                (
                    order_id,
                    product_id,
                    product_name,
                    quantity,
                    price,
                    subtotal
                )
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "iisidd",
                $orderId,
                $productId,
                $product['name'],
                $quantity,
                $price,
                $subtotal
            );

            $stmt->execute();
            $stmt->close();

            $conn->commit();

            $success = true;

        } catch (Throwable $e) {

            $conn->rollback();
            $error = "দুঃখিত! অর্ডারটি সম্পন্ন করা যায়নি। আবার চেষ্টা করুন।";
        }
    }
}

$availableStock = (int)$product['stock'];
?>

<!DOCTYPE html>
<html lang="bn">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>অর্ডার করুন | BrandNest</title>

    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .order-form input,
        .order-form select,
        .order-form textarea {
            width: 100%;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        .order-form select {
            cursor: pointer;
        }

        .order-form input,
        .order-form textarea {
            direction: auto;
        }

        .order-form select:focus,
        .order-form input:focus,
        .order-form textarea:focus {
            outline: none;
            border-color: #c9a45c;
        }

        .stock-info {
            color: #c9a45c;
            margin-top: 8px;
        }

        .no-stock {
            color: #ff7777;
            margin: 12px 0;
        }
    </style>
</head>

<body>

<header class="brand-header">
    <a href="index.php" class="brand-logo">
        Brand<span>Nest</span>
    </a>

    <p>আপনার অর্ডার সম্পন্ন করুন</p>
</header>

<main class="order-page">

    <div class="order-container">

        <?php if ($success): ?>

            <div class="order-success">

                <h1>ধন্যবাদ!</h1>

                <p>আপনার অর্ডারটি সফলভাবে গ্রহণ করা হয়েছে।</p>

                <h3>আপনার অর্ডার নম্বর</h3>

                <h2><?php echo e($orderNumber); ?></h2>

                <p>পেমেন্ট পদ্ধতি: পণ্য হাতে পেয়ে মূল্য পরিশোধ</p>

                <a href="index.php" class="order-btn">
                    কেনাকাটা চালিয়ে যান
                </a>

            </div>

        <?php else: ?>

            <div class="order-heading">
                <p>BRANDNEST</p>
                <h1>অর্ডার সম্পন্ন করুন</h1>
                <span>আপনার ডেলিভারির তথ্যগুলো পূরণ করুন।</span>
            </div>

            <?php if ($error): ?>
                <div class="order-error">
                    <?php echo e($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="order-form">

                <input
                    type="hidden"
                    name="product_id"
                    value="<?php echo $productId; ?>"
                >

                <!-- পণ্যের তথ্য -->
                <div class="order-product">

                    <div>
                        <h3><?php echo e($product['name']); ?></h3>
                        <p>BrandNest-এর প্রিমিয়াম কালেকশন</p>
                    </div>

                    <strong>
                        ৳ <?php echo number_format($price, 2); ?>
                    </strong>

                </div>

                <!-- ক্রেতার তথ্য -->
                <h2>ক্রেতার তথ্য</h2>

                <label>আপনার সম্পূর্ণ নাম</label>

                <input
                    type="text"
                    name="customer_name"
                    placeholder="আপনার নাম লিখুন"
                    value="<?php echo e($_POST['customer_name'] ?? ''); ?>"
                    required
                >

                <label>মোবাইল নম্বর</label>

                <input
                    type="tel"
                    name="phone"
                    placeholder="01XXXXXXXXX"
                    value="<?php echo e($_POST['phone'] ?? ''); ?>"
                    required
                >

                <label>ডেলিভারির এলাকা</label>

                <select name="delivery_zone" id="deliveryZone" required>

                    <option value="">ডেলিভারির এলাকা নির্বাচন করুন</option>

                    <option
                        value="inside"
                        <?php echo (($_POST['delivery_zone'] ?? '') === 'inside') ? 'selected' : ''; ?>
                    >
                        ঢাকার ভিতরে — ৳৮০
                    </option>

                    <option
                        value="outside"
                        <?php echo (($_POST['delivery_zone'] ?? '') === 'outside') ? 'selected' : ''; ?>
                    >
                        ঢাকার বাইরে — ৳১৫০
                    </option>

                </select>

                <label>এলাকার নাম</label>

                <input
                    type="text"
                    name="area"
                    placeholder="আপনার এলাকার নাম লিখুন"
                    value="<?php echo e($_POST['area'] ?? ''); ?>"
                    required
                >

                <label>সম্পূর্ণ ডেলিভারি ঠিকানা</label>

                <textarea
                    name="address"
                    rows="4"
                    placeholder="বাসা নম্বর, রোড নম্বর, এলাকা ও অন্যান্য বিস্তারিত ঠিকানা লিখুন"
                    required
                ><?php echo e($_POST['address'] ?? ''); ?></textarea>

                <!-- পণ্যের পরিমাণ -->
                <label>পণ্যের পরিমাণ নির্বাচন করুন</label>

                <?php if ($availableStock > 0): ?>

                    <select name="quantity" id="quantity" required>

                        <?php
                        $selectedQuantity = (int)($_POST['quantity'] ?? 1);

                        for ($i = 1; $i <= $availableStock; $i++):
                        ?>

                            <option
                                value="<?php echo $i; ?>"
                                <?php echo ($selectedQuantity === $i) ? 'selected' : ''; ?>
                            >
                                <?php echo $i; ?> টি
                            </option>

                        <?php endfor; ?>

                    </select>

                    <p class="stock-info">
                        বর্তমানে স্টকে রয়েছে: <?php echo $availableStock; ?> টি
                    </p>

                <?php else: ?>

                    <p class="no-stock">দুঃখিত! পণ্যটি বর্তমানে স্টকে নেই।</p>

                <?php endif; ?>

                <!-- অর্ডারের হিসাব -->
                <div class="order-summary">

                    <h2>অর্ডারের বিবরণ</h2>

                    <div>
                        <span>পণ্যের মোট মূল্য</span>
                        <strong id="subtotal">
                            ৳ <?php echo number_format($price, 2); ?>
                        </strong>
                    </div>

                    <div>
                        <span>ডেলিভারি চার্জ</span>
                        <strong id="deliveryCharge">৳ ০.০০</strong>
                    </div>

                    <div class="order-total">
                        <span>সর্বমোট</span>
                        <strong id="totalAmount">
                            ৳ <?php echo number_format($price, 2); ?>
                        </strong>
                    </div>

                </div>

                <!-- পেমেন্ট পদ্ধতি -->
                <h2>পেমেন্ট পদ্ধতি</h2>

                <div class="payment-option">
                    <input type="radio" checked disabled>
                    <span>পণ্য হাতে পেয়ে মূল্য পরিশোধ (ক্যাশ অন ডেলিভারি)</span>
                </div>

                <button
                    type="submit"
                    class="place-order-btn"
                    <?php echo $availableStock < 1 ? 'disabled' : ''; ?>
                >
                    অর্ডার নিশ্চিত করুন
                </button>

            </form>

        <?php endif; ?>

    </div>

</main>

<script>
const price = <?php echo json_encode($price); ?>;

const quantityInput = document.getElementById("quantity");
const deliverySelect = document.getElementById("deliveryZone");

function updateOrderTotal() {

    if (!quantityInput || !deliverySelect) return;

    const quantity = parseInt(quantityInput.value) || 1;

    let delivery = 0;

    if (deliverySelect.value === "inside") {
        delivery = 80;
    } else if (deliverySelect.value === "outside") {
        delivery = 150;
    }

    const subtotal = price * quantity;
    const total = subtotal + delivery;

    document.getElementById("subtotal").textContent =
        "৳ " + subtotal.toFixed(2);

    document.getElementById("deliveryCharge").textContent =
        "৳ " + delivery.toFixed(2);

    document.getElementById("totalAmount").textContent =
        "৳ " + total.toFixed(2);
}

if (quantityInput && deliverySelect) {
    quantityInput.addEventListener("change", updateOrderTotal);
    deliverySelect.addEventListener("change", updateOrderTotal);

    updateOrderTotal();
}
</script>

</body>
</html>
