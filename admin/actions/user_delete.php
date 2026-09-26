<?php
/**
 * SeaLink Web Application
 * File: /admin/actions/user_delete.php
 * Purpose: Deletes a user account (farmer or buyer).
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
    $user_id = (int)($_POST['user_id'] ?? 0);
    $type = $_POST['type'] ?? '';
    
    if ($user_id > 0) {
        if ($type === 'farmer') {
            $stmt = mysqli_prepare($conn, "DELETE FROM farmer_tbl WHERE farmer_id = ?");
        } else {
            $stmt = mysqli_prepare($conn, "DELETE FROM buyer_tbl WHERE buyer_id = ?");
        }
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        if (mysqli_stmt_execute($stmt)) {
            set_message('success', 'User account deleted successfully.');
        } else {
            set_message('error', 'Cannot delete this user due to existing active transaction records.');
        }
        mysqli_stmt_close($stmt);
    }
}
mysqli_close($conn);
redirect_path('/admin/users.php');
