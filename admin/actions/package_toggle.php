<?php
/**
 * SeaLink Web Application
 * File: /admin/actions/package_toggle.php
 * Purpose: Toggle promotion package between Active and Inactive.
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

$stmt = mysqli_prepare($conn, "SELECT package_name, is_active FROM promotion_packages_tbl WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $package_id);
mysqli_stmt_execute($stmt);
$pkg = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$pkg) {
    set_message('error', 'Package not found.');
    redirect_path('/admin/settings.php?tab=promote');
}

$new_status = ($pkg['is_active'] == 1) ? 0 : 1;
$status_label = ($new_status == 1) ? 'Activated' : 'Deactivated';

$upd = mysqli_prepare($conn, "UPDATE promotion_packages_tbl SET is_active = ?, updated_at = NOW() WHERE id = ?");
mysqli_stmt_bind_param($upd, "ii", $new_status, $package_id);

if (mysqli_stmt_execute($upd)) {
    mysqli_stmt_close($upd);
    mysqli_close($conn);
    set_message('success', "Package '{$pkg['package_name']}' has been {$status_label}.");
} else {
    mysqli_stmt_close($upd);
    mysqli_close($conn);
    set_message('error', 'Failed to change package status.');
}

redirect_path('/admin/settings.php?tab=promote');
