<?php
/**
 * SeaLink Web Application
 * File: /admin/actions/package_edit.php
 * Purpose: Edit an existing promotion package.
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

$package_id = (int)($_POST['package_id'] ?? 0);
$package_name = trim((string)($_POST['package_name'] ?? ''));
$duration_days = (int)($_POST['duration_days'] ?? 0);
$price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);

if ($package_id <= 0) {
    set_message('error', 'Invalid package selected.');
    redirect_path('/admin/settings.php?tab=promote');
}

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

$stmt = mysqli_prepare($conn, "
    UPDATE promotion_packages_tbl 
    SET package_name = ?,
        duration_days = ?,
        price = ?,
        updated_at = NOW()
    WHERE id = ?
");
mysqli_stmt_bind_param($stmt, "sidi", $package_name, $duration_days, $price, $package_id);

if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    set_message('success', "✔ Package '{$package_name}' updated successfully!");
} else {
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    set_message('error', 'Failed to update promotion package.');
}

redirect_path('/admin/settings.php?tab=promote');
