<?php
/**
 * SeaLink Web Application
 * File: /profile/actions/update_profile.php
 * Purpose: Process Edit Profile form — updates farmer_tbl, buyer_tbl, or admin_tbl depending on role.
 * Connected To: /profile/index.php (editProfileForm)
 * Uses: farmer_tbl, buyer_tbl, admin_tbl
 */

require_once __DIR__ . '/../../includes/auth_check.php';
check_access(['farmer', 'buyer', 'Content Admin', 'User Admin']);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_path('/profile/index.php');
}

$role    = $_SESSION['user_role'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);

$full_name      = trim($_POST['full_name']      ?? '');
$username       = trim($_POST['username']       ?? '');
$email          = trim($_POST['email']          ?? '');
$contact_number = trim($_POST['contact_number'] ?? '');
$address        = trim($_POST['address']        ?? '');
$new_password   = trim($_POST['new_password']   ?? '');
$confirm_pass   = trim($_POST['confirm_password'] ?? '');

/* ========== VALIDATION ========== */
if (empty($full_name) || empty($username) || empty($email)) {
    $_SESSION['msg']      = 'Full name, username, and email are required.';
    $_SESSION['msg_type'] = 'error';
    redirect_path('/profile/index.php');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['msg']      = 'Please enter a valid email address.';
    $_SESSION['msg_type'] = 'error';
    redirect_path('/profile/index.php');
}

$password_hash = null;
if ($new_password !== '') {
    if ($new_password !== $confirm_pass) {
        $_SESSION['msg']      = 'Passwords do not match.';
        $_SESSION['msg_type'] = 'error';
        redirect_path('/profile/index.php');
    }
    if (strlen($new_password) < 6) {
        $_SESSION['msg']      = 'Password must be at least 6 characters.';
        $_SESSION['msg_type'] = 'error';
        redirect_path('/profile/index.php');
    }
    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
}

/* ========== PROFILE IMAGE UPLOAD ========== */
$profile_image_path = null;
if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
    $tmp  = $_FILES['profile_image']['tmp_name'];
    $size = (int)$_FILES['profile_image']['size'];
    $max  = 2 * 1024 * 1024; // 2MB

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = $finfo ? finfo_file($finfo, $tmp) : '';
    if ($finfo) finfo_close($finfo);

    if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
        $_SESSION['msg']      = 'Profile image must be JPG or PNG.';
        $_SESSION['msg_type'] = 'error';
        redirect_path('/profile/index.php');
    }

    if ($size > $max) {
        $_SESSION['msg']      = 'Profile image must not exceed 2MB.';
        $_SESSION['msg_type'] = 'error';
        redirect_path('/profile/index.php');
    }

    $ext = ($mime === 'image/png') ? 'png' : 'jpg';
    $filename = 'profile_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $upload_dir = __DIR__ . '/../../uploads/profiles/';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0777, true);
    }

    if (move_uploaded_file($tmp, $upload_dir . $filename)) {
        $profile_image_path = 'uploads/profiles/' . $filename;
    }
}

/* ========== UPDATE BASED ON ROLE ========== */
if ($role === 'farmer') {
    if ($password_hash && $profile_image_path) {
        $stmt = mysqli_prepare($conn, "
            UPDATE farmer_tbl
            SET full_name=?, username=?, email=?, contact_number=?, address=?, password_hash=?, profile_image=?
            WHERE farmer_id=?
        ");
        mysqli_stmt_bind_param($stmt, "sssssssi", $full_name, $username, $email, $contact_number, $address, $password_hash, $profile_image_path, $user_id);
    } elseif ($password_hash) {
        $stmt = mysqli_prepare($conn, "
            UPDATE farmer_tbl
            SET full_name=?, username=?, email=?, contact_number=?, address=?, password_hash=?
            WHERE farmer_id=?
        ");
        mysqli_stmt_bind_param($stmt, "ssssssi", $full_name, $username, $email, $contact_number, $address, $password_hash, $user_id);
    } elseif ($profile_image_path) {
        $stmt = mysqli_prepare($conn, "
            UPDATE farmer_tbl
            SET full_name=?, username=?, email=?, contact_number=?, address=?, profile_image=?
            WHERE farmer_id=?
        ");
        mysqli_stmt_bind_param($stmt, "ssssssi", $full_name, $username, $email, $contact_number, $address, $profile_image_path, $user_id);
    } else {
        $stmt = mysqli_prepare($conn, "
            UPDATE farmer_tbl
            SET full_name=?, username=?, email=?, contact_number=?, address=?
            WHERE farmer_id=?
        ");
        mysqli_stmt_bind_param($stmt, "sssssi", $full_name, $username, $email, $contact_number, $address, $user_id);
    }
}

if ($role === 'buyer') {
    if ($password_hash && $profile_image_path) {
        $stmt = mysqli_prepare($conn, "
            UPDATE buyer_tbl
            SET full_name=?, username=?, email=?, contact_number=?, password_hash=?, profile_image=?
            WHERE buyer_id=?
        ");
        mysqli_stmt_bind_param($stmt, "ssssssi", $full_name, $username, $email, $contact_number, $password_hash, $profile_image_path, $user_id);
    } elseif ($password_hash) {
        $stmt = mysqli_prepare($conn, "
            UPDATE buyer_tbl
            SET full_name=?, username=?, email=?, contact_number=?, password_hash=?
            WHERE buyer_id=?
        ");
        mysqli_stmt_bind_param($stmt, "sssssi", $full_name, $username, $email, $contact_number, $password_hash, $user_id);
    } elseif ($profile_image_path) {
        $stmt = mysqli_prepare($conn, "
            UPDATE buyer_tbl
            SET full_name=?, username=?, email=?, contact_number=?, profile_image=?
            WHERE buyer_id=?
        ");
        mysqli_stmt_bind_param($stmt, "sssssi", $full_name, $username, $email, $contact_number, $profile_image_path, $user_id);
    } else {
        $stmt = mysqli_prepare($conn, "
            UPDATE buyer_tbl
            SET full_name=?, username=?, email=?, contact_number=?
            WHERE buyer_id=?
        ");
        mysqli_stmt_bind_param($stmt, "ssssi", $full_name, $username, $email, $contact_number, $user_id);
    }
}

if ($role === 'Content Admin' || $role === 'User Admin') {
    if ($password_hash && $profile_image_path) {
        $stmt = mysqli_prepare($conn, "
            UPDATE admin_tbl
            SET full_name=?, username=?, email=?, contact_number=?, address=?, password_hash=?, profile_image=?
            WHERE admin_id=?
        ");
        mysqli_stmt_bind_param($stmt, "sssssssi", $full_name, $username, $email, $contact_number, $address, $password_hash, $profile_image_path, $user_id);
    } elseif ($password_hash) {
        $stmt = mysqli_prepare($conn, "
            UPDATE admin_tbl
            SET full_name=?, username=?, email=?, contact_number=?, address=?, password_hash=?
            WHERE admin_id=?
        ");
        mysqli_stmt_bind_param($stmt, "ssssssi", $full_name, $username, $email, $contact_number, $address, $password_hash, $user_id);
    } elseif ($profile_image_path) {
        $stmt = mysqli_prepare($conn, "
            UPDATE admin_tbl
            SET full_name=?, username=?, email=?, contact_number=?, address=?, profile_image=?
            WHERE admin_id=?
        ");
        mysqli_stmt_bind_param($stmt, "ssssssi", $full_name, $username, $email, $contact_number, $address, $profile_image_path, $user_id);
    } else {
        $stmt = mysqli_prepare($conn, "
            UPDATE admin_tbl
            SET full_name=?, username=?, email=?, contact_number=?, address=?
            WHERE admin_id=?
        ");
        mysqli_stmt_bind_param($stmt, "sssssi", $full_name, $username, $email, $contact_number, $address, $user_id);
    }
}

mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
mysqli_close($conn);

// Update session name
$_SESSION['full_name'] = $full_name;
$_SESSION['username']  = $username;

$_SESSION['msg']      = 'Profile updated successfully.';
$_SESSION['msg_type'] = 'success';
redirect_path('/profile/index.php');
