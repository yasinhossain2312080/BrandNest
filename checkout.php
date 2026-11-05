<?php

session_start();



require_once "config/database.php";



function e($value) {

    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

}



// Product image path

function checkoutImage($image) {

    if (empty($image)) {

        return '';

    }



    return "uploads/" . rawurlencode(basename($image));

}



// Cart must not be empty
if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    header("Location: cart.php");
    exit;
}

// Guest checkout: no customer account is required.
$customerName = "";
$phone = "";
$area = "";
$address = "";
$deliveryZone = "";
$paymentMethod = "COD";

// CSRF token

if (empty($_SESSION['checkout_token'])) {

    $_SESSION['checkout_token'] = bin2hex(random_bytes(32));

}



$error = "";

$success = false;

$orderNumber = "";



$customerName = ""; 

$phone = "";

$area = "";

$address = "";

$deliveryZone = "";

$paymentMethod = "COD";



$totalAmount = 0;



// Process order

if ($_SERVER['REQUEST_METHOD'] === 'POST') {



    $token = $_POST['checkout_token'] ?? '';



    if (!hash_equals($_SESSION['checkout_token'], $token)) {



        $error = "অনুরোধটি সঠিক নয়। পেজটি রিফ্রেশ করে আবার চেষ্টা করুন।";



    } else {



        $customerName = trim($_POST['customer_name'] ?? '');

        $phone = trim($_POST['phone'] ?? '');

        $area = trim($_POST['area'] ?? '');

        $address = trim($_POST['address'] ?? '');

        $deliveryZone = $_POST['delivery_zone'] ?? '';



        // Only COD is accepted

        $paymentMethod = 'COD';



        $phoneDigits = preg_replace('/\D/', '', $phone);



        if (

            $customerName === '' ||

            $phone === '' ||

            $area === '' ||

            $address === ''

        ) {

            $error = "অনুগ্রহ করে প্রয়োজনীয় সব তথ্য পূরণ করুন।";



        } elseif (strlen($customerName) > 150 || strlen($area) > 255) {

            $error = "আপনার দেওয়া কিছু তথ্য অনেক বড়।";



        } elseif (strlen($address) > 1000) {

            $error = "ঠিকানাটি অনেক বড়।";



        } elseif (!preg_match('/^01[0-9]{9}$/', $phoneDigits)) {

            $error = "সঠিক বাংলাদেশি মোবাইল নম্বর লিখুন।";



        } elseif (!in_array($deliveryZone, ['inside', 'outside'], true)) {

            $error = "আপনার ডেলিভারি এলাকা নির্বাচন করুন।";



        } else {



            try {



                $conn->begin_transaction();



                $cartProducts = [];

                $subtotal = 0;

                $cartIds = array_keys($_SESSION['cart']);



                // Check product stock and information

                $productStmt = $conn->prepare("

                    SELECT id, name, price, discount_price, stock, status

                    FROM products

                    WHERE id = ?

                    LIMIT 1

                    FOR UPDATE

                ");



                foreach ($cartIds as $id) {



                    $productId = (int)$id;

                    $quantity = (int)($_SESSION['cart'][$id]['quantity'] ?? 0);



                    if ($productId < 1 || $quantity < 1) {

                        throw new Exception("কার্টের পণ্যের তথ্য সঠিক নয়।");

                    }



                    $productStmt->bind_param("i", $productId);

                    $productStmt->execute();



                    $product = $productStmt->get_result()->fetch_assoc();



                    if (!$product || (int)$product['status'] !== 1) {

                        throw new Exception("কার্টের একটি পণ্য বর্তমানে পাওয়া যাচ্ছে না।");

                    }



                    if ($quantity > (int)$product['stock']) {

                        throw new Exception(

                            $product['name'] . " পণ্যটির পর্যাপ্ত স্টক নেই। বর্তমানে স্টক: " .

                            (int)$product['stock']

                        );

                    }



                    $price = (

                        $product['discount_price'] !== null &&

                        (float)$product['discount_price'] > 0

                    )

                        ? (float)$product['discount_price']

                        : (float)$product['price'];



                    $itemSubtotal = $price * $quantity;

                    $subtotal += $itemSubtotal;



                    $cartProducts[] = [

                        'id' => (int)$product['id'],

                        'name' => $product['name'],

                        'price' => $price,

                        'quantity' => $quantity,

                        'subtotal' => $itemSubtotal

                    ];

                }



                $productStmt->close();



                if (empty($cartProducts)) {

                    throw new Exception("আপনার কার্ট খালি।");

                }



                // Delivery charge

                $deliveryCharge = ($deliveryZone === 'inside') ? 80 : 150;

                $totalAmount = $subtotal + $deliveryCharge;



                // Generate order number

                $orderNumber = "BN" . date("ymdHis") . random_int(100, 999);



                // Save order

                $stmt = $conn->prepare("

                    INSERT INTO orders (

                        customer_id,

                        order_number,

                        customer_name,

                        phone,

                        address,

                        area,

                        subtotal,

                        delivery_charge,

                        total_amount,

                        payment_method,

                        payment_status

                    )

                    VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')

                ");



                $stmt->bind_param(

                    "sssssddds",

                    $orderNumber,

                    $customerName,

                    $phoneDigits,

                    $address,

                    $area,

                    $subtotal,

                    $deliveryCharge,

                    $totalAmount,

                    $paymentMethod

                );



                $stmt->execute();

                $orderId = $conn->insert_id;

                $stmt->close();



                // Save order items

                $itemStmt = $conn->prepare("

                    INSERT INTO order_items (

                        order_id,

                        product_id,

                        product_name,

                        quantity,

                        price,

                        subtotal

                    )

                    VALUES (?, ?, ?, ?, ?, ?)

                ");



                // Update stock

                $stockStmt = $conn->prepare("

                    UPDATE products

                    SET stock = stock - ?

                    WHERE id = ? AND stock >= ?

                ");



                foreach ($cartProducts as $item) {



                    $itemStmt->bind_param(

                        "iisidd",

                        $orderId,

                        $item['id'],

                        $item['name'],

                        $item['quantity'],

                        $item['price'],

                        $item['subtotal']

                    );



                    $itemStmt->execute();



                    $stockStmt->bind_param(

                        "iii",

                        $item['quantity'],

                        $item['id'],

                        $item['quantity']

                    );



                    $stockStmt->execute();



                    if ($stockStmt->affected_rows !== 1) {

                        throw new Exception("স্টক পরিবর্তিত হয়েছে। আবার চেষ্টা করুন।");

                    }

                }



                $itemStmt->close();

                $stockStmt->close();



                $conn->commit();



                // Clear cart after successful order

                $_SESSION['cart'] = [];

                $_SESSION['checkout_token'] = bin2hex(random_bytes(32));



                $success = true;



            } catch (Throwable $e) {



                $conn->rollback();



                $error = $e instanceof mysqli_sql_exception

                    ? "অর্ডার সম্পন্ন করা যায়নি। আবার চেষ্টা করুন।"

                    : $e->getMessage();

            }

        }

    }

}



// Cart summary

$cartItems = [];

$subtotalPreview = 0;

$totalItems = 0;



if (!$success) {



    $stmt = $conn->prepare("

        SELECT id, name, price, discount_price, image, stock

        FROM products

        WHERE id = ? AND status = 1

        LIMIT 1

    ");



    foreach ($_SESSION['cart'] as $id => $item) {



        $productId = (int)$id;

        $quantity = (int)($item['quantity'] ?? 0);



        if ($productId < 1 || $quantity < 1) {

            continue;

        }



        $stmt->bind_param("i", $productId);

        $stmt->execute();



        $product = $stmt->get_result()->fetch_assoc();



        if (!$product) {

            continue;

        }



        $price = (

            $product['discount_price'] !== null &&

            (float)$product['discount_price'] > 0

        )

            ? (float)$product['discount_price']

            : (float)$product['price'];



        $itemTotal = $price * $quantity;



        $cartItems[] = [

            'name' => $product['name'],

            'image' => $product['image'],

            'price' => $price,

            'quantity' => $quantity,

            'total' => $itemTotal

        ];



        $subtotalPreview += $itemTotal;

        $totalItems += $quantity;

    }



    $stmt->close();

}



$deliveryPreview = ($deliveryZone === 'inside')

    ? 80

    : (($deliveryZone === 'outside') ? 150 : 0);



?>



<!DOCTYPE html>

<html lang="bn">



<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">



    <title>অর্ডার সম্পন্ন করুন | BrandNest</title>



    <link rel="stylesheet" href="assets/css/style.css">



    <style>

        .checkout-page {

            min-height: 80vh;

            padding: 45px 15px;

            background: #101010;

        }



        .checkout-container {

            max-width: 1100px;

            margin: auto;

        }



        .checkout-heading {

            text-align: center;

            margin-bottom: 35px;

        }



        .checkout-heading h1 {

            color: white;

            font-size: 34px;

        }



        .checkout-heading p {

            color: #aaa;

            margin-top: 10px;

        }



        .checkout-layout {

            display: grid;

            grid-template-columns: 1fr 360px;

            gap: 25px;

            align-items: start;

        }



        .checkout-card {

            background: #191919;

            border: 1px solid #393126;

            border-radius: 9px;

            padding: 25px;

            margin-bottom: 20px;

        }



        .checkout-card h2 {

            color: #c9a45c;

            font-size: 21px;

            margin-bottom: 20px;

        }



        .checkout-form label {

            display: block;

            color: #ddd;

            margin: 17px 0 8px;

        }



        .checkout-form input,

        .checkout-form select,

        .checkout-form textarea {

            width: 100%;

            box-sizing: border-box;

            padding: 13px;

            background: #252525;

            color: white;

            border: 1px solid #444;

            border-radius: 5px;

            font-size: 15px;

        }



        .checkout-form input:focus,

        .checkout-form select:focus,

        .checkout-form textarea:focus {

            outline: none;

            border-color: #c9a45c;

        }



        .checkout-form input[readonly] {

            opacity: .85;

        }



        .checkout-hint {

            color: #999;

            font-size: 12px;

            margin-top: 6px;

        }



        .payment-choice {

            display: flex;

            align-items: center;

            gap: 10px;

            padding: 14px;

            margin: 10px 0;

            border: 1px solid #444;

            border-radius: 6px;

            color: #ddd;

            cursor: pointer;

        }



        .payment-choice input {

            width: auto;

            accent-color: #c9a45c;

        }



        .checkout-summary-row {

            display: flex;

            justify-content: space-between;

            gap: 10px;

            padding: 13px 0;

            border-bottom: 1px solid #333;

            color: #ccc;

        }



        /* Product image and information */

        .checkout-product-row {

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 12px;

            padding: 13px 0;

            border-bottom: 1px solid #333;

        }



        .checkout-product-info {

            display: flex;

            align-items: center;

            gap: 12px;

            min-width: 0;

        }



        .checkout-product-image {

            width: 65px;

            height: 65px;

            object-fit: cover;

            border-radius: 6px;

            border: 1px solid #393126;

            background: #252525;

            flex-shrink: 0;

        }



        .checkout-product-name {

            color: #ccc;

            overflow-wrap: anywhere;

        }



        .checkout-product-price {

            color: white;

            white-space: nowrap;

        }



        .checkout-grand-total {

            color: #c9a45c;

            font-size: 21px;

            font-weight: bold;

        }



        .checkout-submit {

            width: 100%;

            padding: 15px;

            margin-top: 22px;

            background: #c9a45c;

            color: #111;

            border: none;

            border-radius: 5px;

            font-size: 16px;

            font-weight: bold;

            cursor: pointer;

        }



        .checkout-submit:hover {

            background: #e0bd75;

        }



        .checkout-error {

            background: #401d1d;

            color: #ffaaaa;

            border-left: 4px solid #ff7777;

            padding: 14px;

            margin-bottom: 20px;

            border-radius: 5px;

        }



        .checkout-note {

            background: #29251d;

            color: #e0bd75;

            padding: 13px;

            margin-top: 15px;

            border-left: 4px solid #c9a45c;

            font-size: 13px;

            line-height: 1.8;

        }



        .checkout-success {

            max-width: 650px;

            margin: 40px auto;

            text-align: center;

            background: #191919;

            border: 1px solid #393126;

            border-radius: 10px;

            padding: 40px 25px;

        }



        .checkout-success h1 {

            color: #c9a45c;

            margin-bottom: 15px;

        }



        .checkout-success p {

            color: #ccc;

            margin: 12px 0;

        }



        .checkout-success h2 {

            color: white;

            margin: 15px 0;

        }



        .checkout-success a {

            display: inline-block;

            background: #c9a45c;

            color: #111;

            padding: 13px 25px;

            border-radius: 5px;

            margin-top: 20px;

            font-weight: bold;

        }



        .checkout-back {

            display: inline-block;

            color: #c9a45c;

            margin-top: 20px;

        }



        @media(max-width: 800px) {

            .checkout-layout {

                grid-template-columns: 1fr;

            }

        }



        @media(max-width: 480px) {

            .checkout-card {

                padding: 18px;

            }



            .checkout-heading h1 {

                font-size: 27px;

            }



            .checkout-product-image {

                width: 55px;

                height: 55px;

            }

        }

    </style>

</head>



<body>



<header class="brand-header">

    <a href="index.php" class="brand-logo">

        Brand<span>Nest</span>

    </a>



    <p>নিরাপদ অর্ডার ব্যবস্থা</p>

</header>



<main class="checkout-page">



    <div class="checkout-container">



        <?php if ($success): ?>



            <div class="checkout-success">



                <h1>ধন্যবাদ!</h1>



                <p>আপনার অর্ডারটি সফলভাবে গ্রহণ করা হয়েছে।</p>



                <p>আপনার অর্ডার নম্বর:</p>



                <h2><?php echo e($orderNumber); ?></h2>



                <p>

                    পেমেন্ট পদ্ধতি:

                    <?php echo e('ক্যাশ অন ডেলিভারি'); ?>

                </p>



                <p>

                    অর্ডারের সর্বমোট:

                    ৳<?php echo number_format($totalAmount, 2); ?>

                </p>



                <p>পেমেন্টের অবস্থা: অপেক্ষমাণ</p>



                <a href="index.php">কেনাকাটা চালিয়ে যান</a>



            </div>



        <?php else: ?>



            <div class="checkout-heading">

                <h1>অর্ডার সম্পন্ন করুন</h1>

                <p>আপনার ডেলিভারির তথ্য প্রদান করুন</p>

            </div>



            <?php if ($error): ?>

                <div class="checkout-error">

                    <?php echo e($error); ?>

                </div>

            <?php endif; ?>



            <?php if (empty($cartItems)): ?>



                <div class="checkout-card">

                    <h2>আপনার কার্টে কোনো পণ্য নেই।</h2>



                    <a href="cart.php" class="checkout-back">

                        কার্টে ফিরে যান

                    </a>

                </div>



            <?php else: ?>



                <form method="POST" action="checkout.php" class="checkout-form">



                    <input

                        type="hidden"

                        name="checkout_token"

                        value="<?php echo e($_SESSION['checkout_token']); ?>"

                    >



                    <div class="checkout-layout">



                        <div>



                            <!-- Customer information -->

                            <div class="checkout-card">



                                <h2>গ্রাহকের তথ্য</h2>



                                <label>পূর্ণ নাম *</label>



                                <input

                                    type="text"

                                    name="customer_name"

                                    value="<?php echo e($customerName); ?>"

                                    maxlength="150"

                                    required

                                >



                                <p class="checkout-hint">

                                    অর্ডার করতে কোনো অ্যাকাউন্ট বা লগইন প্রয়োজন নেই।

                                </p>



                                <label>মোবাইল নম্বর *</label>



                                <input

                                    type="tel"

                                    name="phone"

                                    value="<?php echo e($phone); ?>"

                                    placeholder="01XXXXXXXXX"

                                    required

                                >



                                <p class="checkout-hint">

                                    উদাহরণ: 01XXXXXXXXX

                                </p>



                                <label>ডেলিভারি এলাকা *</label>



                                <select name="delivery_zone" required>



                                    <option value="">এলাকা নির্বাচন করুন</option>



                                    <option

                                        value="inside"

                                        <?php echo $deliveryZone === 'inside' ? 'selected' : ''; ?>

                                    >

                                        ঢাকার ভিতরে — ৳80

                                    </option>



                                    <option

                                        value="outside"

                                        <?php echo $deliveryZone === 'outside' ? 'selected' : ''; ?>

                                    >

                                        ঢাকার বাইরে — ৳150

                                    </option>



                                </select>



                                <label>এলাকা / লোকেশন *</label>



                                <input

                                    type="text"

                                    name="area"

                                    value="<?php echo e($area); ?>"

                                    placeholder="আপনার এলাকার নাম লিখুন"

                                    maxlength="255"

                                    required

                                >



                                <label>সম্পূর্ণ ডেলিভারি ঠিকানা *</label>



                                <textarea

                                    name="address"

                                    rows="4"

                                    maxlength="1000"

                                    placeholder="বাড়ি, রোড, এলাকা এবং অন্যান্য বিস্তারিত লিখুন"

                                    required

                                ><?php echo e($address); ?></textarea>



                            </div>



                            <!-- Payment method -->

                            <div class="checkout-card">



                                <h2>পেমেন্ট পদ্ধতি</h2>



                                <label class="payment-choice">



                                    <input

                                        type="radio"

                                        name="payment_method"

                                        value="COD"

                                        checked

                                        required

                                    >



                                    <span>

                                        <strong>ক্যাশ অন ডেলিভারি</strong><br>

                                        <small>পণ্য হাতে পেয়ে টাকা প্রদান করুন</small>

                                    </span>



                                </label>



                                <label class="payment-choice" style="opacity: 0.6; cursor: not-allowed;">



                                    <input type="radio" disabled>



                                    <span>

                                        <strong>বিকাশ</strong><br>

                                        <small>Coming Soon — শীঘ্রই আসছে</small>

                                    </span>



                                </label>



                                <label class="payment-choice" style="opacity: 0.6; cursor: not-allowed;">



                                    <input type="radio" disabled>



                                    <span>

                                        <strong>নগদ</strong><br>

                                        <small>Coming Soon — শীঘ্রই আসছে</small>

                                    </span>



                                </label>



                                <div class="checkout-note">

                                    বর্তমানে শুধুমাত্র Cash on Delivery (COD) সুবিধা চালু রয়েছে।

                                    বিকাশ ও নগদ পেমেন্ট সুবিধা খুব শীঘ্রই চালু হবে।

                                    পণ্য হাতে পেয়ে মূল্য পরিশোধ করুন।

                                </div>



                            </div>



                        </div>



                        <!-- Order summary -->

                        <aside class="checkout-card">



                            <h2>অর্ডারের সারসংক্ষেপ</h2>



                            <?php foreach ($cartItems as $item): ?>



                                <div class="checkout-product-row">



                                    <div class="checkout-product-info">



                                        <?php if (!empty($item['image'])): ?>



                                            <img

                                                src="<?php echo e(checkoutImage($item['image'])); ?>"

                                                alt="<?php echo e($item['name']); ?>"

                                                class="checkout-product-image"

                                                onerror="this.style.display='none';"

                                            >



                                        <?php endif; ?>



                                        <div class="checkout-product-name">

                                            <?php echo e($item['name']); ?>

                                            × <?php echo $item['quantity']; ?>

                                        </div>



                                    </div>



                                    <strong class="checkout-product-price">

                                        ৳<?php echo number_format($item['total'], 2); ?>

                                    </strong>



                                </div>



                            <?php endforeach; ?>



                            <div class="checkout-summary-row">



                                <span>মোট পণ্য</span>



                                <strong>

                                    <?php echo $totalItems; ?>

                                </strong>



                            </div>



                            <div class="checkout-summary-row">



                                <span>পণ্যের মোট দাম</span>



                                <strong>

                                    ৳<?php echo number_format($subtotalPreview, 2); ?>

                                </strong>



                            </div>



                            <div class="checkout-summary-row">



                                <span>ডেলিভারি চার্জ</span>



                                <strong id="deliveryCharge">

                                    ৳<?php echo number_format($deliveryPreview, 2); ?>

                                </strong>



                            </div>



                            <div class="checkout-summary-row checkout-grand-total">



                                <span>সর্বমোট</span>



                                <strong id="grandTotal">

                                    ৳<?php echo number_format($subtotalPreview + $deliveryPreview, 2); ?>

                                </strong>



                            </div>



                            <button

                                type="submit"

                                class="checkout-submit"

                            >

                                অর্ডার নিশ্চিত করুন

                            </button>



                            <a href="cart.php" class="checkout-back">

                                &larr; কার্টে ফিরে যান

                            </a>



                        </aside>



                    </div>



                </form>



            <?php endif; ?>



        <?php endif; ?>



    </div>



</main>



<script>

const subtotal = <?php echo json_encode($subtotalPreview); ?>;



const deliverySelect = document.querySelector('[name="delivery_zone"]');



function updateCheckoutTotal() {



    if (!deliverySelect) return;



    let delivery = 0;



    if (deliverySelect.value === 'inside') {

        delivery = 80;

    } else if (deliverySelect.value === 'outside') {

        delivery = 150;

    }



    const total = subtotal + delivery;



    document.getElementById('deliveryCharge').textContent =

        '৳' + delivery.toFixed(2);



    document.getElementById('grandTotal').textContent =

        '৳' + total.toFixed(2);

}



if (deliverySelect) {

    deliverySelect.addEventListener('change', updateCheckoutTotal);

    updateCheckoutTotal();

}

</script>



</body>

</html>