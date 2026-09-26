<?php
/**
 * SeaLink Web Application
 * File: /buyer/actions/address_save.php
 * Purpose: Insert or update buyer address in buyer_address_tbl.
 */

require_once __DIR__ . '/../../includes/auth_check.php';
check_access('buyer');

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_path('/buyer/addresses.php');
}

$buyer_id     = (int)$_SESSION['user_id'];
$address_id   = (int)($_POST['address_id'] ?? 0);
$street       = normalize_spaces($_POST['street'] ?? '');
$municipality = normalize_spaces($_POST['municipality'] ?? 'Santa Fe');
$province     = normalize_spaces($_POST['province'] ?? 'Romblon');
$zip_code     = sanitize_input($_POST['zip_code'] ?? '5505');
$is_default   = isset($_POST['is_default']) ? 1 : 0;

if ($street === '') {
    set_message('error', 'Street address is required.');
    redirect_path('/buyer/addresses.php');
}

if ($is_default) {
    // Reset other addresses to non-default
    $stmt = mysqli_prepare($conn, "UPDATE buyer_address_tbl SET is_default = 0 WHERE buyer_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $buyer_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

if ($address_id > 0) {
    // Update existing
    $stmt = mysqli_prepare($conn, "
        UPDATE buyer_address_tbl 
        SET street = ?, municipality = ?, province = ?, zip_code = ?, is_default = ?
        WHERE address_id = ? AND buyer_id = ?
    ");
    mysqli_stmt_bind_param($stmt, "ssssiii", $street, $municipality, $province, $zip_code, $is_default, $address_id, $buyer_id);
    if (mysqli_stmt_execute($stmt)) {
        set_message('success', 'Address updated successfully.');
    } else {
        set_message('error', 'Failed to update address.');
    }
    mysqli_stmt_close($stmt);
} else {
    // Insert new
    // Check if this is the user's first address, make it default automatically if so
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as c FROM buyer_address_tbl WHERE buyer_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $buyer_id);
    mysqli_stmt_execute($stmt);
    $cnt = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'] ?? 0);
    mysqli_stmt_close($stmt);

    if ($cnt === 0) {
        $is_default = 1;
    }

    $stmt = mysqli_prepare($conn, "
        INSERT INTO buyer_address_tbl (buyer_id, street, municipality, province, zip_code, is_default)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    mysqli_stmt_bind_param($stmt, "issssi", $buyer_id, $street, $municipality, $province, $zip_code, $is_default);
    if (mysqli_stmt_execute($stmt)) {
        set_message('success', 'Address added successfully.');
    } else {
        set_message('error', 'Failed to add address.');
    }
    mysqli_stmt_close($stmt);
}

mysqli_close($conn);
redirect_path('/buyer/addresses.php');
