<?php
/**
 * SeaLink Web Application
 * File: /admin/actions/save_settings.php
 * Purpose: Processes site settings form POST and updates site_settings_tbl.
 * Connected To: /admin/settings.php
 * Uses: site_settings_tbl
 */

require_once __DIR__ . '/../../includes/auth_check.php';
check_access(['Content Admin', 'User Admin']);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_path('/admin/settings.php');
}

$fields = ['site_name', 'support_email', 'office_address', 'contact_number', 'description', 'drop_off_points'];

foreach ($fields as $key) {
    $val = trim($_POST[$key] ?? '');
    $stmt = mysqli_prepare($conn, "
        INSERT INTO site_settings_tbl (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = ?
    ");
    mysqli_stmt_bind_param($stmt, "sss", $key, $val, $val);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/* ========== HANDLE LOGO UPLOAD ========== */
if (!empty($_FILES['site_logo']['tmp_name'])) {
    $upload_dir = __DIR__ . '/../../uploads/settings/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0775, true);

    $ext = strtolower(pathinfo($_FILES['site_logo']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (in_array($ext, $allowed)) {
        $filename = 'site_logo_' . time() . '.' . $ext;
        $dest = $upload_dir . $filename;
        if (move_uploaded_file($_FILES['site_logo']['tmp_name'], $dest)) {
            $logo_path = 'uploads/settings/' . $filename;
            $stmt = mysqli_prepare($conn, "
                INSERT INTO site_settings_tbl (setting_key, setting_value)
                VALUES ('site_logo', ?)
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            mysqli_stmt_bind_param($stmt, "ss", $logo_path, $logo_path);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
}

mysqli_close($conn);
$_SESSION['msg'] = 'Settings saved successfully.';
$_SESSION['msg_type'] = 'success';
redirect_path('/admin/settings.php');
