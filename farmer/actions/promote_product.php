<?php
/**
 * SeaLink Web Application
 * File: /farmer/actions/promote_product.php
 * Purpose: Process farmer's request to promote/boost a product with GCash payment screenshot.
 * Connected To: /farmer/manage_products.php (promoteProductModal)
 * Uses: product_tbl, product_boost_tbl
 */

require_once __DIR__ . '/../../includes/auth_check.php';
check_access('farmer');

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (($_SESSION['verification_status'] ?? '') !== 'Verified') {
    set_message('error', 'Product promotion is available for verified farmers only.');
    redirect_path('/farmer/manage_products.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_path('/farmer/manage_products.php');
}

$farmer_id   = (int)($_SESSION['user_id'] ?? 0);
$product_id  = (int)($_POST['product_id'] ?? 0);
$package_id  = (int)($_POST['package_id'] ?? 0);
$package_name = trim((string)($_POST['package'] ?? ''));
$gcash_ref   = trim((string)($_POST['gcash_reference_number'] ?? $_POST['gcash_reference'] ?? ''));

// Fetch chosen package dynamically from promotion_packages_tbl
$pkg = null;
if ($package_id > 0) {
    $pkg = get_promotion_package_by_id($conn, $package_id);
}

// Fallback search if package_id wasn't passed or package was submitted by name/slug
if (!$pkg && !empty($package_name)) {
    $stmt_p = mysqli_prepare($conn, "SELECT * FROM promotion_packages_tbl WHERE package_name = ? AND is_active = 1 LIMIT 1");
    if ($stmt_p) {
        mysqli_stmt_bind_param($stmt_p, "s", $package_name);
        mysqli_stmt_execute($stmt_p);
        $res_p = mysqli_stmt_get_result($stmt_p);
        if ($res_p && $row_p = mysqli_fetch_assoc($res_p)) {
            $pkg = $row_p;
        }
        mysqli_stmt_close($stmt_p);
    }
}

// Fallback for legacy '3_days' or '7_days'
if (!$pkg) {
    if ($package_name === '3_days') {
        $stmt_p = mysqli_prepare($conn, "SELECT * FROM promotion_packages_tbl WHERE duration_days = 3 AND is_active = 1 LIMIT 1");
    } elseif ($package_name === '7_days') {
        $stmt_p = mysqli_prepare($conn, "SELECT * FROM promotion_packages_tbl WHERE duration_days = 7 AND is_active = 1 LIMIT 1");
    }
    if (isset($stmt_p) && $stmt_p) {
        mysqli_stmt_execute($stmt_p);
        $res_p = mysqli_stmt_get_result($stmt_p);
        if ($res_p && $row_p = mysqli_fetch_assoc($res_p)) {
            $pkg = $row_p;
        }
        mysqli_stmt_close($stmt_p);
    }
}

if (!$pkg || (int)$pkg['is_active'] !== 1 || $product_id <= 0) {
    set_message('error', 'Please select a valid active promotion package.');
    redirect_path('/farmer/manage_products.php');
}

$duration_days = max(1, (int)$pkg['duration_days']);
$amount_paid   = (float)$pkg['price'];
$plan_label    = $pkg['package_name'] . ' (₱' . number_format($pkg['price'], 2) . ')';

// Verify farmer owns this product
$stmt = mysqli_prepare($conn, "SELECT product_id, name, status, promotion_status FROM product_tbl WHERE product_id = ? AND farmer_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "ii", $product_id, $farmer_id);
mysqli_stmt_execute($stmt);
$product = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$product) {
    set_message('error', 'Product not found in your inventory.');
    redirect_path('/farmer/manage_products.php');
}

if ($product['promotion_status'] === 'Pending') {
    set_message('error', 'This product already has a promotion request awaiting Admin approval.');
    redirect_path('/farmer/manage_products.php');
}

// Handle GCash receipt screenshot upload
if (empty($_FILES['receipt_image']['name']) || $_FILES['receipt_image']['error'] !== UPLOAD_ERR_OK) {
    set_message('error', 'Please upload a clear screenshot of your GCash payment receipt.');
    redirect_path('/farmer/manage_products.php');
}

$file = $_FILES['receipt_image'];
$allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
$max_size = 5 * 1024 * 1024; // 5MB

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowed_types, true)) {
    set_message('error', 'Invalid file type. Please upload a JPG, PNG, or WebP screenshot.');
    redirect_path('/farmer/manage_products.php');
}

if ($file['size'] > $max_size) {
    set_message('error', 'The receipt image file size exceeds the 5MB limit.');
    redirect_path('/farmer/manage_products.php');
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
if (empty($ext)) $ext = 'jpg';
$new_filename = 'promo_' . $farmer_id . '_' . $product_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);

$upload_dir = __DIR__ . '/../../uploads/gcash_screenshots';
if (!is_dir($upload_dir)) {
    @mkdir($upload_dir, 0777, true);
}

$target_path = $upload_dir . '/' . $new_filename;
$relative_db_path = 'uploads/gcash_screenshots/' . $new_filename;

if (!move_uploaded_file($file['tmp_name'], $target_path)) {
    set_message('error', 'Failed to save receipt image. Please try again.');
    redirect_path('/farmer/manage_products.php');
}

// Transaction: Insert into product_boost_tbl & update product_tbl
mysqli_autocommit($conn, false);

$boost_stmt = mysqli_prepare($conn, "
    INSERT INTO product_boost_tbl (product_id, farmer_id, amount_paid, gcash_reference, receipt_image, status, duration_days, promotion_plan, created_at)
    VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?, NOW())
");
mysqli_stmt_bind_param($boost_stmt, "iidssis", $product_id, $farmer_id, $amount_paid, $gcash_ref, $relative_db_path, $duration_days, $plan_label);
$ok1 = mysqli_stmt_execute($boost_stmt);
mysqli_stmt_close($boost_stmt);

$prod_stmt = mysqli_prepare($conn, "
    UPDATE product_tbl
    SET receipt_image_path = ?,
        gcash_reference_number = ?,
        promotion_status = 'Pending',
        promotion_plan = ?,
        updated_at = NOW()
    WHERE product_id = ? AND farmer_id = ?
");
mysqli_stmt_bind_param($prod_stmt, "sssii", $relative_db_path, $gcash_ref, $plan_label, $product_id, $farmer_id);
$ok2 = mysqli_stmt_execute($prod_stmt);
mysqli_stmt_close($prod_stmt);

if ($ok1 && $ok2) {
    mysqli_commit($conn);
    mysqli_autocommit($conn, true);
    mysqli_close($conn);
    set_message('success', 'Promotion request submitted! Your payment receipt has been forwarded to the Administrator for verification.');
} else {
    mysqli_rollback($conn);
    mysqli_autocommit($conn, true);
    mysqli_close($conn);
    @unlink($target_path);
    set_message('error', 'Failed to process promotion request. Please try again.');
}

redirect_path('/farmer/manage_products.php');
