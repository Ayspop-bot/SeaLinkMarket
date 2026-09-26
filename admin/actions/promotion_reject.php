<?php
/**
 * SeaLink Web Application
 * File: /admin/actions/promotion_reject.php
 * Purpose: Reject a farmer's product promotion request (e.g. invalid receipt).
 * Connected To: /admin/user_dashboard.php (Pending Promotions module)
 * Uses: product_tbl, product_boost_tbl
 */

require_once __DIR__ . '/../../includes/auth_check.php';
check_access(['User Admin', 'Content Admin']);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_path('/admin/user_dashboard.php');
}

$boost_id = (int)($_POST['boost_id'] ?? 0);
$product_id = (int)($_POST['product_id'] ?? 0);

if ($boost_id <= 0 && $product_id <= 0) {
    set_message('error', 'Invalid request.');
    redirect_path('/admin/user_dashboard.php');
}

// Fetch pending boost
$query = "
    SELECT b.boost_id, b.product_id, p.name AS product_name
    FROM product_boost_tbl b
    JOIN product_tbl p ON p.product_id = b.product_id
    WHERE b.status = 'Pending' " . ($boost_id > 0 ? "AND b.boost_id = ?" : "AND b.product_id = ?") . "
    ORDER BY b.boost_id DESC LIMIT 1
";
$stmt = mysqli_prepare($conn, $query);
$param_val = $boost_id > 0 ? $boost_id : $product_id;
mysqli_stmt_bind_param($stmt, "i", $param_val);
mysqli_stmt_execute($stmt);
$boost = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$boost) {
    set_message('error', 'Pending promotion request not found.');
    redirect_path('/admin/user_dashboard.php');
}

$target_boost_id = (int)$boost['boost_id'];
$target_product_id = (int)$boost['product_id'];

mysqli_autocommit($conn, false);

$u_prod = mysqli_prepare($conn, "
    UPDATE product_tbl
    SET promotion_status = 'Rejected',
        is_promoted = 0,
        updated_at = NOW()
    WHERE product_id = ?
");
mysqli_stmt_bind_param($u_prod, "i", $target_product_id);
$ok1 = mysqli_stmt_execute($u_prod);
mysqli_stmt_close($u_prod);

$u_boost = mysqli_prepare($conn, "
    UPDATE product_boost_tbl
    SET status = 'Rejected'
    WHERE boost_id = ?
");
mysqli_stmt_bind_param($u_boost, "i", $target_boost_id);
$ok2 = mysqli_stmt_execute($u_boost);
mysqli_stmt_close($u_boost);

if ($ok1 && $ok2) {
    mysqli_commit($conn);
    mysqli_autocommit($conn, true);
    mysqli_close($conn);
    set_message('warning', "Promotion request for '{$boost['product_name']}' has been rejected.");
} else {
    mysqli_rollback($conn);
    mysqli_autocommit($conn, true);
    mysqli_close($conn);
    set_message('error', 'Failed to reject promotion request.');
}

redirect_path('/admin/user_dashboard.php#pending_promotions');
