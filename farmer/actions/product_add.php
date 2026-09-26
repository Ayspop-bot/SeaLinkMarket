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

$name = normalize_spaces($_POST['name'] ?? '');
$category_id = (int)($_POST['category_id'] ?? 0);
$description = sanitize_input($_POST['description'] ?? '');
$price = (float)($_POST['price'] ?? 0);
$stock = (int)($_POST['stock_quantity'] ?? 0);
$status = ($_POST['status'] ?? 'Active');

$allowed_status = ['Active','Inactive'];

if ($name === '' || $category_id <= 0 || !in_array($status, $allowed_status, true)) {
  set_message('error', 'Please fill all required fields.');
  redirect($base_path . '/farmer/dashboard.php');
}

/* image required */
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
  set_message('error', 'Product image is required.');
  redirect($base_path . '/farmer/dashboard.php');
}

$tmp = $_FILES['image']['tmp_name'];
$size = (int)$_FILES['image']['size'];
$max = 2 * 1024 * 1024;

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = $finfo ? finfo_file($finfo, $tmp) : '';
if ($finfo) finfo_close($finfo);

if (!in_array($mime, ['image/jpeg','image/png'], true)) {
  set_message('error', 'Image must be JPG or PNG.');
  redirect($base_path . '/farmer/dashboard.php');
}
if ($size > $max) {
  set_message('error', 'Image must not exceed 2MB.');
  redirect($base_path . '/farmer/dashboard.php');
}

$ext = ($mime === 'image/png') ? 'png' : 'jpg';
$filename = 'product_' . $farmer_id . '_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;

$dir = __DIR__ . '/../../uploads/products/';
if (!is_dir($dir)) mkdir($dir, 0777, true);

if (!move_uploaded_file($tmp, $dir . $filename)) {
  set_message('error', 'Failed to upload image.');
  redirect($base_path . '/farmer/dashboard.php');
}

$image_url = 'uploads/products/' . $filename;

// Discounted price: only set if lower than regular price
$discounted_price_raw = trim($_POST['discounted_price'] ?? '');
$discounted_price = null;
if ($discounted_price_raw !== '' && is_numeric($discounted_price_raw)) {
    $dp = (float)$discounted_price_raw;
    if ($dp > 0 && $dp < $price) $discounted_price = $dp;
}

// Duration of Sale: max 24 hours, or blank if not on sale
$sale_duration_raw = trim($_POST['sale_duration_hours'] ?? '');
$shelf_life_hours = 24; // Default fallback to prevent NOT NULL database errors
$selling_deadline = null;
$harvested_at = date('Y-m-d H:i:s');

if ($sale_duration_raw !== '' && is_numeric($sale_duration_raw)) {
    $dur = (int)$sale_duration_raw;
    if ($dur >= 1 && $dur <= 24) {
        $shelf_life_hours = $dur;
        $selling_deadline = date('Y-m-d H:i:s', strtotime("+{$dur} hours"));
    }
}

$sql = "INSERT INTO product_tbl
(farmer_id, category_id, name, description, price, discounted_price, stock_quantity, image_url, status, harvested_at, shelf_life_hours, selling_deadline, created_at, updated_at)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "iissddississ", $farmer_id, $category_id, $name, $description, $price, $discounted_price, $stock, $image_url, $status, $harvested_at, $shelf_life_hours, $selling_deadline);

if (mysqli_stmt_execute($stmt)) {
  set_message('success', 'Product added successfully.');
} else {
  set_message('error', 'Failed to add product.');
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
redirect($base_path . '/farmer/manage_products.php');