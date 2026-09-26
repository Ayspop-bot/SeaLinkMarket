<?php
/**
 * SeaLink Web Application
 * File: /support/actions/cancel_ticket.php
 * Purpose: Allows farmers or buyers to close/cancel their own open tickets.
 */

require_once __DIR__ . '/../../includes/auth_check.php';
$role = $_SESSION['user_role'] ?? '';
if (!in_array($role, ['farmer', 'buyer'], true)) {
    redirect_path('/index.php');
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_path('/support/index.php');
}

$user_id    = (int)$_SESSION['user_id'];
$support_id = (int)($_POST['support_id'] ?? 0);
$is_farmer  = ($role === 'farmer');

if ($support_id > 0) {
    $sql = $is_farmer 
        ? "UPDATE admin_support_tbl SET status = 'Closed', updated_at = NOW() WHERE support_id = ? AND farmer_id = ? AND status = 'Open'"
        : "UPDATE admin_support_tbl SET status = 'Closed', updated_at = NOW() WHERE support_id = ? AND buyer_id = ? AND status = 'Open'";
        
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $support_id, $user_id);
    if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
        set_message('success', 'Your support ticket has been closed.');
    } else {
        set_message('error', 'Cannot cancel this ticket or it is already closed.');
    }
    mysqli_stmt_close($stmt);
}

mysqli_close($conn);
redirect_path('/support/index.php');
