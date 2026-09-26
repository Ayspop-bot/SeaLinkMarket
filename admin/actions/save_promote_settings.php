<?php
/**
 * SeaLink Web Application
 * File: /admin/actions/save_promote_settings.php
 * Purpose: Process and save User Admin GCash payment details and promotion rates.
 * Connected To: /admin/settings.php?tab=promote
 * Uses: promote_settings_tbl
 */

require_once __DIR__ . '/../../includes/auth_check.php';
check_access(['User Admin']);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_path('/admin/settings.php?tab=promote');
}

// Retrieve and validate inputs
$gcash_name = trim((string)($_POST['gcash_name'] ?? ''));
$gcash_number = trim((string)($_POST['gcash_number'] ?? ''));

if (empty($gcash_name)) {
    set_message('error', 'Platform GCash Account Name cannot be empty.');
    redirect_path('/admin/settings.php?tab=promote');
}

if (empty($gcash_number)) {
    set_message('error', 'Platform GCash Number cannot be empty.');
    redirect_path('/admin/settings.php?tab=promote');
}

// Ensure table exists
get_promote_settings($conn);

// Check if a new QR Code image is uploaded
$new_qr_path = null;
if (isset($_FILES['gcash_qr_image']) && $_FILES['gcash_qr_image']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['gcash_qr_image'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed_types, true)) {
        set_message('error', 'Invalid file type for QR Code. Please upload a JPG, PNG, or WebP image.');
        redirect_path('/admin/settings.php?tab=promote');
    }

    if ($file['size'] > $max_size) {
        set_message('error', 'The QR Code image file size exceeds the 5MB limit.');
        redirect_path('/admin/settings.php?tab=promote');
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (empty($ext)) $ext = 'png';
    $new_filename = 'gcash_qr_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);

    $upload_dir = __DIR__ . '/../../uploads/qr_codes';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0777, true);
    }

    $target_path = $upload_dir . '/' . $new_filename;
    $relative_db_path = 'uploads/qr_codes/' . $new_filename;

    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        $new_qr_path = $relative_db_path;
    } else {
        set_message('error', 'Failed to save uploaded QR Code image.');
        redirect_path('/admin/settings.php?tab=promote');
    }
}

// Update settings row
if ($new_qr_path !== null) {
    $stmt = mysqli_prepare($conn, "
        UPDATE promote_settings_tbl 
        SET gcash_name = ?,
            gcash_number = ?,
            gcash_qr_image = ?,
            updated_at = NOW()
        WHERE id = 1
    ");
    mysqli_stmt_bind_param($stmt, "sss", $gcash_name, $gcash_number, $new_qr_path);
} else {
    $stmt = mysqli_prepare($conn, "
        UPDATE promote_settings_tbl 
        SET gcash_name = ?,
            gcash_number = ?,
            updated_at = NOW()
        WHERE id = 1
    ");
    mysqli_stmt_bind_param($stmt, "ss", $gcash_name, $gcash_number);
}

if ($stmt && mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    set_message('success', ' Platform GCash details updated successfully! Farmers will now see these updated payment details.');
} else {
    if ($stmt) mysqli_stmt_close($stmt);
    mysqli_close($conn);
    set_message('error', 'An error occurred while saving payment details. Please try again.');
}

redirect_path('/admin/settings.php?tab=promote');
