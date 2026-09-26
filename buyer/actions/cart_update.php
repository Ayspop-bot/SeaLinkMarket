<?php
/**
 * SeaLink Web Application
 * File: /buyer/actions/cart_update.php
 * Purpose: Updates quantity in cart_item_tbl.
 */

require_once __DIR__ . '/../../includes/auth_check.php';
check_access('buyer');

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_path('/buyer/cart.php');
}

$buyer_id = (int)$_SESSION['user_id'];
$product_id = (int)($_POST['product_id'] ?? 0);
$action = trim((string)($_POST['action'] ?? ''));

if ($product_id <= 0 || !in_array($action, ['increase', 'decrease'], true)) {
    redirect_path('/buyer/cart.php');
}

$stmt = mysqli_prepare($conn, "
    SELECT c.cart_item_id, c.quantity, p.stock_quantity, p.name 
    FROM cart_item_tbl c 
    JOIN product_tbl p ON p.product_id = c.product_id 
    WHERE c.buyer_id = ? AND c.product_id = ?
    LIMIT 1
");
mysqli_stmt_bind_param($stmt, "ii", $buyer_id, $product_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$cart_item = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if ($cart_item) {
    $qty = (int)$cart_item['quantity'];
    if ($action === 'increase') {
        $qty++;
        if ($qty > (int)$cart_item['stock_quantity']) {
            set_message('error', 'Cannot exceed available stock of ' . (int)$cart_item['stock_quantity'] . ' kg.');
            $qty = (int)$cart_item['stock_quantity'];
        }
    } else {
        $qty--;
    }

    if ($qty <= 0) {
        $stmt = mysqli_prepare($conn, "DELETE FROM cart_item_tbl WHERE cart_item_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $cart_item['cart_item_id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        set_message('success', 'Item removed from cart.');
    } else {
        $stmt = mysqli_prepare($conn, "UPDATE cart_item_tbl SET quantity = ? WHERE cart_item_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $qty, $cart_item['cart_item_id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

mysqli_close($conn);
redirect_path('/buyer/cart.php');
