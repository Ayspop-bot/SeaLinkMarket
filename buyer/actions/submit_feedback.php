<?php
/**
 * SeaLink Web Application
 * File: /buyer/actions/submit_feedback.php
 * Purpose: Allows verified buyers to submit feedback/reviews for completed products.
 */

require_once __DIR__ . '/../../includes/auth_check.php';
check_access('buyer');

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_path('/buyer/orders.php');
}

$buyer_id   = (int)$_SESSION['user_id'];
$product_id = (int)($_POST['product_id'] ?? 0);
$farmer_id  = (int)($_POST['farmer_id'] ?? 0);
$order_id   = (int)($_POST['order_id'] ?? 0);
$return_url = trim((string)($_POST['return'] ?? ''));
$rating     = max(1, min(5, (int)($_POST['rating'] ?? 5)));
$comment    = sanitize_input($_POST['comment'] ?? '');

$redirect_dest = $return_url !== '' ? $return_url : ($order_id > 0 ? ('/buyer/order_details.php?order_id=' . $order_id) : '/buyer/orders.php');

if ($product_id <= 0 || $farmer_id <= 0 || $comment === '') {
    set_message('error', 'Please complete the rating and comment fields.');
    redirect_path($redirect_dest);
}

// Ensure buyer actually completed an order for this product
$stmt = mysqli_prepare($conn, "
    SELECT oi.order_item_id 
    FROM order_item_tbl oi
    JOIN order_tbl o ON o.order_id = oi.order_id
    WHERE o.buyer_id = ? AND oi.product_id = ? AND o.order_status = 'Completed'
    LIMIT 1
");
mysqli_stmt_bind_param($stmt, "ii", $buyer_id, $product_id);
mysqli_stmt_execute($stmt);
$valid = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$valid) {
    set_message('error', 'You can only review products from completed orders.');
    redirect_path($redirect_dest);
}

// Check if feedback already exists to update, else insert
$stmt = mysqli_prepare($conn, "SELECT feedback_id FROM feedback_tbl WHERE buyer_id = ? AND product_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "ii", $buyer_id, $product_id);
mysqli_stmt_execute($stmt);
$existing = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if ($existing) {
    $stmt = mysqli_prepare($conn, "UPDATE feedback_tbl SET rating = ?, comment = ?, created_at = NOW() WHERE feedback_id = ?");
    mysqli_stmt_bind_param($stmt, "isi", $rating, $comment, $existing['feedback_id']);
} else {
    $stmt = mysqli_prepare($conn, "
        INSERT INTO feedback_tbl (buyer_id, product_id, farmer_id, rating, comment, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    mysqli_stmt_bind_param($stmt, "iiiis", $buyer_id, $product_id, $farmer_id, $rating, $comment);
}

if (mysqli_stmt_execute($stmt)) {
    set_message('success', 'Thank you for your feedback! Your review has been recorded.');
} else {
    set_message('error', 'Failed to submit review.');
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

redirect_path($redirect_dest);
