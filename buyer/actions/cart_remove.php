<?php
/**
 * SeaLink Web Application
 * File: /buyer/actions/cart_remove.php
 * Purpose: Removes item from cart_item_tbl.
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
$farmer_id = (int)($_POST['farmer_id'] ?? 0);
$cart_item_ids = $_POST['cart_item_ids'] ?? [];

if (!empty($cart_item_ids)) {
    if (!is_array($cart_item_ids)) {
        $cart_item_ids = [$cart_item_ids];
    }
    $clean_ids = [];
    foreach ($cart_item_ids as $cid) {
        $cid = (int)$cid;
        if ($cid > 0) $clean_ids[] = $cid;
    }
    if (!empty($clean_ids)) {
        $in_clause = implode(',', $clean_ids);
        $res = mysqli_query($conn, "DELETE FROM cart_item_tbl WHERE buyer_id = {$buyer_id} AND cart_item_id IN ({$in_clause})");
        if ($res && mysqli_affected_rows($conn) > 0) {
            set_message('success', 'Selected product(s) removed from your cart.');
        } else {
            set_message('warning', 'Selected item(s) were not found in your cart.');
        }
    }
} elseif ($farmer_id > 0) {
    $stmt = mysqli_prepare($conn, "
        DELETE c FROM cart_item_tbl c
        JOIN product_tbl p ON p.product_id = c.product_id
        WHERE c.buyer_id = ? AND p.farmer_id = ?
    ");
    mysqli_stmt_bind_param($stmt, "ii", $buyer_id, $farmer_id);
    if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
        set_message('success', 'All items from this seller have been removed from your cart.');
    } else {
        set_message('warning', 'No items from this seller were found in your cart.');
    }
    mysqli_stmt_close($stmt);
} elseif ($product_id > 0) {
    $stmt = mysqli_prepare($conn, "DELETE FROM cart_item_tbl WHERE buyer_id = ? AND product_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $buyer_id, $product_id);
    if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
        set_message('success', 'Item removed from your cart.');
    } else {
        set_message('warning', 'Item was not found in your cart.');
    }
    mysqli_stmt_close($stmt);
}

mysqli_close($conn);
redirect_path('/buyer/cart.php');
