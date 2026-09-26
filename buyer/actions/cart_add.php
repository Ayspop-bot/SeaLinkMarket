<?php
/**
 * SeaLink Web Application
 * File: /buyer/actions/cart_add.php
 * Purpose: Adds a product to cart (upsert using cart_item_tbl).
 */

require_once __DIR__ . '/../../includes/auth_check.php';
check_access('buyer');

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_path('/buyer/market.php');
}

$buyer_id = (int)$_SESSION['user_id'];
$product_id = (int)($_POST['product_id'] ?? 0);
$quantity = (int)($_POST['quantity'] ?? 1);
$return_url = trim((string)($_POST['return'] ?? ''));

/* Safety check return */
if ($return_url !== '' && ($return_url[0] !== '/' || preg_match('/^\s*https?:/i', $return_url))) {
    $return_url = '';
}
$target_redirect = $return_url ? (BASE_URL . $return_url) : (BASE_URL . '/buyer/market.php');

if ($product_id <= 0 || $quantity <= 0) {
    set_message('error', 'Invalid product or quantity.');
    redirect($target_redirect);
}

// Check stock and status
$stmt = mysqli_prepare($conn, "SELECT name, stock_quantity, status FROM product_tbl WHERE product_id = ?");
mysqli_stmt_bind_param($stmt, "i", $product_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$product || $product['status'] !== 'Active') {
    set_message('error', 'Product is currently unavailable.');
    redirect($target_redirect);
}

if ($product['stock_quantity'] <= 0) {
    set_message('error', 'Product is out of stock.');
    redirect($target_redirect);
}

// Check current item in cart_item_tbl
$stmt = mysqli_prepare($conn, "SELECT cart_item_id, quantity FROM cart_item_tbl WHERE buyer_id = ? AND product_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "ii", $buyer_id, $product_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$existing = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

$new_qty = $existing ? ($existing['quantity'] + $quantity) : $quantity;

if ($new_qty > $product['stock_quantity']) {
    set_message('error', 'Cannot add more. Exceeds available stock of ' . (int)$product['stock_quantity'] . ' kg.');
    redirect($target_redirect);
}

if ($existing) {
    $stmt = mysqli_prepare($conn, "UPDATE cart_item_tbl SET quantity = ? WHERE cart_item_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $new_qty, $existing['cart_item_id']);
} else {
    $stmt = mysqli_prepare($conn, "INSERT INTO cart_item_tbl (buyer_id, product_id, quantity, added_at) VALUES (?, ?, ?, NOW())");
    mysqli_stmt_bind_param($stmt, "iii", $buyer_id, $product_id, $quantity);
}

if (mysqli_stmt_execute($stmt)) {
    set_message('success', 'Added ' . $quantity . ' kg of "' . $product['name'] . '" to your cart.');
} else {
    set_message('error', 'Failed to add item to cart.');
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

redirect($target_redirect);