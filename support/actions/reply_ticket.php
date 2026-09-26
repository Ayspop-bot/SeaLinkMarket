<?php
/**
 * SeaLink Web Application
 * File: /support/actions/reply_ticket.php
 * Purpose: Allows buyer or farmer to submit a follow-up reply to an open or replied support message.
 * Connected To: /support/index.php
 * Uses: admin_support_tbl, admin_support_reply_tbl
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

$user_id    = (int)($_SESSION['user_id'] ?? 0);
$user_name  = (string)($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User');
$support_id = (int)($_POST['support_id'] ?? 0);
$reply_msg  = sanitize_input($_POST['message'] ?? '');

if ($support_id <= 0 || $reply_msg === '') {
    set_message('error', 'Please enter a reply message.');
    redirect_path('/support/index.php');
}

// Verify that the support ticket belongs to the current user and is not closed/canceled
$sql = ($role === 'farmer')
    ? "SELECT support_id, status FROM admin_support_tbl WHERE support_id = ? AND farmer_id = ? LIMIT 1"
    : "SELECT support_id, status FROM admin_support_tbl WHERE support_id = ? AND buyer_id = ? LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $support_id, $user_id);
mysqli_stmt_execute($stmt);
$ticket = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$ticket) {
    mysqli_close($conn);
    set_message('error', 'Support message not found.');
    redirect_path('/support/index.php');
}

if ($ticket['status'] === 'Closed' || $ticket['status'] === 'Cancelled' || $ticket['status'] === 'Resolved') {
    mysqli_close($conn);
    set_message('error', 'This support inquiry has already been resolved or canceled.');
    redirect_path('/support/index.php');
}

// Insert into replies table
$ins = mysqli_prepare($conn, "
    INSERT INTO admin_support_reply_tbl (support_id, sender_type, sender_id, sender_role, sender_name, message, created_at)
    VALUES (?, 'user', ?, ?, ?, ?, NOW())
");
mysqli_stmt_bind_param($ins, "iisss", $support_id, $user_id, $role, $user_name, $reply_msg);
$executed = mysqli_stmt_execute($ins);
mysqli_stmt_close($ins);

if ($executed) {
    // Re-open ticket if it was replied so admin is alerted of follow-up
    $upd = mysqli_prepare($conn, "UPDATE admin_support_tbl SET status = 'Open', updated_at = NOW() WHERE support_id = ?");
    mysqli_stmt_bind_param($upd, "i", $support_id);
    mysqli_stmt_execute($upd);
    mysqli_stmt_close($upd);

    set_message('success', 'Follow-up message sent to administrator.');
} else {
    set_message('error', 'Failed to send follow-up message.');
}

mysqli_close($conn);
redirect_path('/support/index.php');
