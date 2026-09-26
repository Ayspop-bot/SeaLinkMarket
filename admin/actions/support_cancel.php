<?php
/**
 * SeaLink Web Application
 * File: /admin/actions/support_cancel.php
 * Purpose: Allows admin to close/cancel a support ticket.
 */

require_once __DIR__ . '/../../includes/auth_check.php';
check_access(['Content Admin', 'User Admin']);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_path('/admin/support.php');
}

$admin_id   = (int)$_SESSION['user_id'];
$support_id = (int)($_POST['support_id'] ?? 0);

if ($support_id > 0) {
    $stmt = mysqli_prepare($conn, "
        UPDATE admin_support_tbl 
        SET admin_id = ?, status = 'Closed', updated_at = NOW() 
        WHERE support_id = ?
    ");
    mysqli_stmt_bind_param($stmt, "ii", $admin_id, $support_id);
    if (mysqli_stmt_execute($stmt)) {
        set_message('success', 'Ticket closed successfully.');
    } else {
        set_message('error', 'Failed to close ticket.');
    }
    mysqli_stmt_close($stmt);
}

mysqli_close($conn);
redirect_path('/admin/support.php?support_id=' . $support_id);
