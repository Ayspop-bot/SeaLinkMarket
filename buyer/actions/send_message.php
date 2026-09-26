<?php
/**
 * SeaLink Web Application
 * File: /buyer/actions/send_message.php
 */
require_once __DIR__ . '/../../includes/auth_check.php';
check_access('buyer');
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $buyer_id = (int)$_SESSION['user_id'];
    $farmer_id = (int)($_POST['farmer_id'] ?? 0);
    $content = sanitize_input($_POST['content'] ?? '');

    if ($farmer_id > 0 && $content !== '') {
        $stmt = mysqli_prepare($conn, "INSERT INTO message_tbl (farmer_id, buyer_id, sender_type, content) VALUES (?, ?, 'Buyer', ?)");
        mysqli_stmt_bind_param($stmt, "iis", $farmer_id, $buyer_id, $content);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    mysqli_close($conn);
    redirect_path('/buyer/messages.php?farmer_id=' . $farmer_id);
}
redirect_path('/buyer/messages.php');
