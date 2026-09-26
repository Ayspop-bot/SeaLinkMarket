<?php
/**
 * SeaLink Web Application
 * File: /admin/actions/user_status.php
 * Purpose: Allows User Admin to toggle a user's status between Active and Suspended.
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
    $type    = trim($_POST['type'] ?? '');

    if ($user_id > 0 && ($type === 'farmer' || $type === 'buyer')) {
        $table = ($type === 'farmer') ? 'farmer_tbl' : 'buyer_tbl';
        $pk    = ($type === 'farmer') ? 'farmer_id' : 'buyer_id';

        $stmt = mysqli_prepare($conn, "SELECT status FROM {$table} WHERE {$pk} = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $u = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if ($u) {
            $new_status = ($u['status'] === 'Active') ? 'Suspended' : 'Active';
            $up = mysqli_prepare($conn, "UPDATE {$table} SET status = ? WHERE {$pk} = ?");
            mysqli_stmt_bind_param($up, "si", $new_status, $user_id);
            if (mysqli_stmt_execute($up)) {
                set_message('success', 'User account status changed to ' . $new_status . '.');
            } else {
                set_message('error', 'Failed to update user status.');
            }
            mysqli_stmt_close($up);
        }
    }
}

mysqli_close($conn);
redirect_path('/admin/users.php');
