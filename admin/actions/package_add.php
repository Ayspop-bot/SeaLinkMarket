<?php
/**
 * SeaLink Web Application
 * File: /admin/actions/package_add.php
 * Purpose: Add a new dynamic promotion package.
 * Connected To: /admin/settings.php?tab=promote
 * Uses: promotion_packages_tbl
 */

require_once __DIR__ . '/../../includes/auth_check.php';
check_access(['User Admin']);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_path('/admin/settings.php?tab=promote');
}

$package_name = trim((string)($_POST['package_name'] ?? ''));
$duration_days = (int)($_POST['duration_days'] ?? 0);
$price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);

if (empty($package_name)) {
    set_message('error', 'Package Name cannot be empty.');
    redirect_path('/admin/settings.php?tab=promote');
}

if ($duration_days < 1) {
    set_message('error', 'Duration must be at least 1 day.');
    redirect_path('/admin/settings.php?tab=promote');
}

if ($price === false || $price === null || $price < 0) {
    set_message('error', 'Please enter a valid price for the promotion package.');
    redirect_path('/admin/settings.php?tab=promote');
}

// Ensure table exists
get_promotion_packages($conn);

$stmt = mysqli_prepare($conn, "
    INSERT INTO promotion_packages_tbl (package_name, duration_days, price, is_active, created_at, updated_at)
    VALUES (?, ?, ?, 1, NOW(), NOW())
");
mysqli_stmt_bind_param($stmt, "sid", $package_name, $duration_days, $price);

if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    set_message('success', " Promotion package '{$package_name}' added successfully!");
} else {
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    set_message('error', 'Failed to add promotion package. Please try again.');
}

redirect_path('/admin/settings.php?tab=promote');
