<?php
/**
 * SeaLink Web Application
 * File: /support/actions/submit_ticket.php
 * Purpose: Insert a new support ticket into admin_support_tbl.
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

$user_id = (int)$_SESSION['user_id'];
$message = sanitize_input($_POST['message'] ?? '');

if ($message === '') {
    set_message('error', 'Please enter a message for your support request.');
    redirect_path('/support/index.php');
}

$farmer_id = ($role === 'farmer') ? $user_id : null;
$buyer_id  = ($role === 'buyer') ? $user_id : null;

$stmt = mysqli_prepare($conn, "
    INSERT INTO admin_support_tbl (farmer_id, buyer_id, message, status, created_at, updated_at)
    VALUES (?, ?, ?, 'Open', NOW(), NOW())
");
mysqli_stmt_bind_param($stmt, "iis", $farmer_id, $buyer_id, $message);

if (mysqli_stmt_execute($stmt)) {
    set_message('success', 'Your support ticket has been submitted. Our admin will review and reply shortly.');
} else {
    set_message('error', 'Failed to submit ticket. Please try again.');
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

redirect_path('/support/index.php');
