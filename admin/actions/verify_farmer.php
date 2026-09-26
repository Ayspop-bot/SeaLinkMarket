<?php
/**
 * SeaLink Web Application
 * File: /admin/actions/verify_farmer.php
 * Purpose: Verifies a farmer's business permit and account privileges.
 */

require_once __DIR__ . '/../../includes/auth_check.php';
check_access(['User Admin', 'Content Admin']);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$admin_role = $_SESSION['user_role'] ?? '';
if ($admin_role !== 'User Admin') {
    set_message('error', 'Unauthorized action.');
    redirect_path('/admin/users.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $farmer_id = (int)($_POST['farmer_id'] ?? 0);
    if ($farmer_id > 0) {
        $stmt = mysqli_prepare($conn, "UPDATE farmer_tbl SET verification_status = 'Verified' WHERE farmer_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $farmer_id);
        if (mysqli_stmt_execute($stmt)) {
            set_message('success', 'Farmer account verified successfully. Product posting is now enabled.');
        } else {
            set_message('error', 'Failed to verify farmer.');
        }
        mysqli_stmt_close($stmt);
    }
}
mysqli_close($conn);
redirect_path('/admin/users.php');
