<?php
/**
 * SeaLink Web Application
 * File: /admin/actions/support_reply.php
 * Purpose: Admin submits reply to support message or marks concern as resolved.
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
$admin_role = (string)($_SESSION['user_role'] ?? 'User Admin');
$admin_name = (string)($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Administrator');
$support_id = (int)($_POST['support_id'] ?? 0);
$action     = trim((string)($_POST['action'] ?? 'reply'));

if ($support_id > 0) {
    if ($action === 'reply') {
        $reply = sanitize_input($_POST['admin_reply'] ?? '');
        if ($reply !== '') {
            // Insert into admin_support_reply_tbl
            $ins = mysqli_prepare($conn, "
                INSERT INTO admin_support_reply_tbl (support_id, sender_type, sender_id, sender_role, sender_name, message, created_at)
                VALUES (?, 'admin', ?, ?, ?, ?, NOW())
            ");
            mysqli_stmt_bind_param($ins, "iisss", $support_id, $admin_id, $admin_role, $admin_name, $reply);
            mysqli_stmt_execute($ins);
            mysqli_stmt_close($ins);

            $stmt = mysqli_prepare($conn, "
                UPDATE admin_support_tbl 
                SET admin_id = ?, admin_reply = ?, status = 'Replied', updated_at = NOW() 
                WHERE support_id = ?
            ");
            mysqli_stmt_bind_param($stmt, "isi", $admin_id, $reply, $support_id);
            if (mysqli_stmt_execute($stmt)) {
                set_message('success', 'Reply sent to user.');
            } else {
                set_message('error', 'Failed to send reply.');
            }
            mysqli_stmt_close($stmt);
        } else {
            set_message('error', 'Please enter a reply message.');
        }
    } elseif ($action === 'resolve' || $action === 'cancel') {
        $reply = sanitize_input($_POST['admin_reply'] ?? '');
        if ($reply !== '') {
            $ins = mysqli_prepare($conn, "
                INSERT INTO admin_support_reply_tbl (support_id, sender_type, sender_id, sender_role, sender_name, message, created_at)
                VALUES (?, 'admin', ?, ?, ?, ?, NOW())
            ");
            mysqli_stmt_bind_param($ins, "iisss", $support_id, $admin_id, $admin_role, $admin_name, $reply);
            mysqli_stmt_execute($ins);
            mysqli_stmt_close($ins);

            $stmt = mysqli_prepare($conn, "
                UPDATE admin_support_tbl 
                SET admin_id = ?, admin_reply = ?, status = 'Closed', updated_at = NOW() 
                WHERE support_id = ?
            ");
            mysqli_stmt_bind_param($stmt, "isi", $admin_id, $reply, $support_id);
        } else {
            $stmt = mysqli_prepare($conn, "
                UPDATE admin_support_tbl 
                SET admin_id = ?, status = 'Closed', updated_at = NOW() 
                WHERE support_id = ?
            ");
            mysqli_stmt_bind_param($stmt, "ii", $admin_id, $support_id);
        }
        if (mysqli_stmt_execute($stmt)) {
            set_message('success', 'Support message marked as Concern Resolved.');
        } else {
            set_message('error', 'Failed to update support status.');
        }
        mysqli_stmt_close($stmt);
    }
}

mysqli_close($conn);
redirect_path('/admin/support.php?support_id=' . $support_id);
