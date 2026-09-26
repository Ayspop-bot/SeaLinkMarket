<?php
/**
 * SeaLink Web Application
 * File: /admin/actions/package_delete.php
 * Purpose: Delete a promotion package.
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

if ($package_id <= 0) {
    set_message('error', 'Invalid package.');
    redirect_path('/admin/settings.php?tab=promote');
}

$stmt = mysqli_prepare($conn, "SELECT package_name FROM promotion_packages_tbl WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $package_id);
mysqli_stmt_execute($stmt);
$pkg = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$pkg) {
    set_message('error', 'Package not found.');
    redirect_path('/admin/settings.php?tab=promote');
}

$del = mysqli_prepare($conn, "DELETE FROM promotion_packages_tbl WHERE id = ?");
mysqli_stmt_bind_param($del, "i", $package_id);

if (mysqli_stmt_execute($del)) {
    mysqli_stmt_close($del);
    mysqli_close($conn);
    set_message('success', "Package '{$pkg['package_name']}' has been deleted.");
} else {
    mysqli_stmt_close($del);
    mysqli_close($conn);
    set_message('error', 'Failed to delete package.');
}

redirect_path('/admin/settings.php?tab=promote');
