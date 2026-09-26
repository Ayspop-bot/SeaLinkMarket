<?php
/**
 * SeaLink Web Application
 * File: /buyer/actions/address_delete.php
 * Purpose: Deletes an address from buyer_address_tbl.
 */

require_once __DIR__ . '/../../includes/auth_check.php';
check_access('buyer');

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_path('/buyer/addresses.php');
}

$buyer_id   = (int)$_SESSION['user_id'];
$address_id = (int)($_POST['address_id'] ?? 0);

if ($address_id > 0) {
    $stmt = mysqli_prepare($conn, "DELETE FROM buyer_address_tbl WHERE address_id = ? AND buyer_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $address_id, $buyer_id);
    if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
        set_message('success', 'Address deleted successfully.');
    } else {
        set_message('error', 'Unable to delete address.');
    }
    mysqli_stmt_close($stmt);
}

mysqli_close($conn);
redirect_path('/buyer/addresses.php');
