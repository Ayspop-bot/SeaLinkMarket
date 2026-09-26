<?php
require_once __DIR__ . '/../../includes/auth_check.php';
check_access('farmer');

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (($_SESSION['verification_status'] ?? '') !== 'Verified') {
  set_message('error', 'Product actions are disabled until your account is verified.');
  redirect($base_path . '/farmer/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  redirect($base_path . '/farmer/dashboard.php');
}

$farmer_id = (int)($_SESSION['user_id'] ?? 0);
$product_id = (int)($_POST['product_id'] ?? 0);

if ($product_id <= 0) {
  redirect($base_path . '/farmer/dashboard.php');
}

/* get image + ensure ownership */
$stmt = mysqli_prepare($conn, "SELECT image_url FROM product_tbl WHERE product_id=? AND farmer_id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, "ii", $product_id, $farmer_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$row) {
  set_message('error', 'Product not found.');
  mysqli_close($conn);
  redirect($base_path . '/farmer/dashboard.php');
}

/* delete row */
$stmt = mysqli_prepare($conn, "DELETE FROM product_tbl WHERE product_id=? AND farmer_id=?");
mysqli_stmt_bind_param($stmt, "ii", $product_id, $farmer_id);

if (mysqli_stmt_execute($stmt)) {
  // delete file (optional)
  $img = $row['image_url'] ?? '';
  if (!empty($img)) {
    $path = __DIR__ . '/../../' . $img;
    if (is_file($path)) @unlink($path);
  }
  set_message('success', 'Product deleted.');
} else {
  set_message('error', 'Failed to delete product.');
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
redirect($base_path . '/farmer/dashboard.php');