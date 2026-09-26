<?php
/**
 * SeaLink Web Application
 * File: /buyer/actions/cancel_order.php
 */
require_once __DIR__ . '/../../includes/auth_check.php';
check_access('buyer');
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $buyer_id = (int)$_SESSION['user_id'];
    $order_id = (int)($_POST['order_id'] ?? 0);
    
    $reason_preset = trim(sanitize_input($_POST['reason_preset'] ?? ''));
    $reason_detail = trim(sanitize_input($_POST['reason_detail'] ?? ''));
    $reason = $reason_preset;
    if ($reason_detail !== '') {
        $reason = ($reason !== '' && $reason !== 'Other') ? ($reason . ' - ' . $reason_detail) : $reason_detail;
    }
    if ($reason === '') {
        $reason = 'Buyer requested order cancellation';
    }

    if ($order_id > 0) {
        $stmt = mysqli_prepare($conn, "UPDATE order_tbl SET order_status = 'Cancelled', payment_status = 'Cancelled', cancellation_reason = ?, cancelled_by = 'Buyer', cancelled_at = NOW() WHERE order_id = ? AND buyer_id = ? AND order_status IN ('Order Placed', 'Pending')");
        mysqli_stmt_bind_param($stmt, "sii", $reason, $order_id, $buyer_id);
        if(mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
            set_message('success', 'Order cancelled successfully.');
            // Add stock back
            $st = mysqli_prepare($conn, "SELECT product_id, quantity FROM order_item_tbl WHERE order_id = ?");
            mysqli_stmt_bind_param($st, "i", $order_id);
            mysqli_stmt_execute($st);
            $res = mysqli_stmt_get_result($st);
            while($row = mysqli_fetch_assoc($res)) {
                $up = mysqli_prepare($conn, "UPDATE product_tbl SET stock_quantity = stock_quantity + ? WHERE product_id = ?");
                mysqli_stmt_bind_param($up, "ii", $row['quantity'], $row['product_id']);
                mysqli_stmt_execute($up);
                mysqli_stmt_close($up);
            }
            mysqli_stmt_close($st);
        } else {
            set_message('error', 'Cannot cancel this order or it was already confirmed by the farmer.');
        }
        mysqli_stmt_close($stmt);
    }
    $return_url = trim((string)($_POST['return'] ?? ''));
    if ($return_url !== '' && ($return_url[0] !== '/' || str_starts_with($return_url, '//') || preg_match('/^\s*https?:/i', $return_url))) {
        $return_url = '';
    }
}
mysqli_close($conn);
if (!empty($return_url)) {
    redirect_path($return_url);
}
redirect_path('/buyer/orders.php');
