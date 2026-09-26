<?php
/**
 * SeaLink Web Application
 * File: /farmer/actions/send_message.php
 */
require_once __DIR__ . '/../../includes/auth_check.php';
check_access('farmer');
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $farmer_id = (int)$_SESSION['user_id'];
    $buyer_id = (int)($_POST['buyer_id'] ?? 0);
    $content = sanitize_input($_POST['content'] ?? '');

    if ($buyer_id > 0 && $content !== '') {
        $stmt = mysqli_prepare($conn, "INSERT INTO message_tbl (farmer_id, buyer_id, sender_type, content) VALUES (?, ?, 'Farmer', ?)");
        mysqli_stmt_bind_param($stmt, "iis", $farmer_id, $buyer_id, $content);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    mysqli_close($conn);
    redirect_path('/farmer/messages.php?buyer_id=' . $buyer_id);
}
redirect_path('/farmer/messages.php');
