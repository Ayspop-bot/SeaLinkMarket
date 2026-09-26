<?php
/**
 * SeaLink Web Application
 * File: /farmer/actions/update_order_status.php
 */
require_once __DIR__ . '/../../includes/auth_check.php';
check_access('farmer');
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$redirect_filter = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $farmer_id = (int)$_SESSION['user_id'];
    $order_id = (int)($_POST['order_id'] ?? 0);
    $new_status = sanitize_input($_POST['new_status'] ?? '');
    $current_filter = sanitize_input($_POST['current_filter'] ?? '');
    if ($current_filter !== '' && $current_filter !== 'All') {
        $redirect_filter = '?status=' . urlencode($current_filter);
    }
    
    $valid_statuses = ['Order Placed', 'Confirmed', 'Ready for fulfillment', 'Ready for Pickup', 'Ready for Delivery', 'Ready for Drop-off', 'Completed'];
    
    $status_levels = [
        'Order Placed'          => 1,
        'Confirmed'             => 2,
        'Ready for fulfillment' => 3,
        'Ready for Pickup'      => 3,
        'Ready for Delivery'    => 3,
        'Ready for Drop-off'    => 3,
        'Completed'             => 4
    ];
    
    if ($order_id > 0 && in_array($new_status, $valid_statuses, true)) {
        // Fetch current status to ensure forward-only progression
        $chk = mysqli_prepare($conn, "SELECT order_status FROM order_tbl WHERE order_id = ? AND farmer_id = ? LIMIT 1");
        mysqli_stmt_bind_param($chk, "ii", $order_id, $farmer_id);
        mysqli_stmt_execute($chk);
        $chk_res = mysqli_stmt_get_result($chk);
        $curr_row = mysqli_fetch_assoc($chk_res);
        mysqli_stmt_close($chk);

        if (!$curr_row) {
            set_message('error', 'Order not found.');
        } elseif ($curr_row['order_status'] === 'Cancelled') {
            set_message('error', 'Cancelled orders cannot be updated.');
        } else {
            $curr_lvl = $status_levels[$curr_row['order_status']] ?? 1;
            $new_lvl  = $status_levels[$new_status] ?? 1;
            
            if ($new_lvl < $curr_lvl) {
                set_message('error', 'Orders cannot be reverted to a previous status level.');
            } else {
                if ($new_status === 'Completed') {
                    $stmt = mysqli_prepare($conn, "UPDATE order_tbl SET order_status = ?, payment_status = 'Paid' WHERE order_id = ? AND farmer_id = ?");
                } else {
                    $stmt = mysqli_prepare($conn, "UPDATE order_tbl SET order_status = ? WHERE order_id = ? AND farmer_id = ?");
                }
                mysqli_stmt_bind_param($stmt, "sii", $new_status, $order_id, $farmer_id);
                if (mysqli_stmt_execute($stmt)) {
                    set_message('success', 'Order status updated successfully.');
                } else {
                    set_message('error', 'Failed to update order status.');
                }
                mysqli_stmt_close($stmt);
            }
        }
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
redirect_path('/farmer/orders.php' . $redirect_filter);
