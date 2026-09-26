<?php
/**
 * SeaLink Web Application
 * File: /buyer/actions/address_default.php
 * Purpose: Sets a specific address as default for the buyer.
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
    // Reset all to non-default
    $stmt = mysqli_prepare($conn, "UPDATE buyer_address_tbl SET is_default = 0 WHERE buyer_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $buyer_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Set selected to default
    $stmt = mysqli_prepare($conn, "UPDATE buyer_address_tbl SET is_default = 1 WHERE address_id = ? AND buyer_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $address_id, $buyer_id);
    if (mysqli_stmt_execute($stmt)) {
        set_message('success', 'Default address updated.');
    }
    mysqli_stmt_close($stmt);
}

mysqli_close($conn);
redirect_path('/buyer/addresses.php');
