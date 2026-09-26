<?php
/**
 * SeaLink Web Application
 * File: /admin/actions/admin_add.php
 * Purpose: Allows User Admin to register a new administrator (User Admin or Content Admin).
 */

require_once __DIR__ . '/../../includes/auth_check.php';
check_access(['User Admin']);

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_path('/admin/users.php?role=Admin');
}

$full_name      = trim($_POST['full_name'] ?? '');
$username       = trim($_POST['username'] ?? '');
$email          = trim($_POST['email'] ?? '');
$contact_number = trim($_POST['contact_number'] ?? '');
$address        = trim($_POST['address'] ?? '');
$admin_role     = trim($_POST['admin_role'] ?? 'Content Admin');
$password       = trim($_POST['password'] ?? '');
$confirm_pass   = trim($_POST['confirm_password'] ?? '');

if (empty($full_name) || empty($username) || empty($email) || empty($password)) {
    set_message('error', 'Please fill in all required fields.');
    redirect_path('/admin/users.php?role=Admin');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    set_message('error', 'Please enter a valid email address.');
    redirect_path('/admin/users.php?role=Admin');
}

if ($password !== $confirm_pass) {
    set_message('error', 'Passwords do not match.');
    redirect_path('/admin/users.php?role=Admin');
}

if (strlen($password) < 6) {
    set_message('error', 'Password must be at least 6 characters.');
    redirect_path('/admin/users.php?role=Admin');
}

if (!in_array($admin_role, ['User Admin', 'Content Admin'], true)) {
    $admin_role = 'Content Admin';
}

// Check uniqueness
$stmt = mysqli_prepare($conn, "SELECT admin_id FROM admin_tbl WHERE username = ? OR email = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "ss", $username, $email);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
if (mysqli_num_rows($res) > 0) {
    set_message('error', 'Username or Email is already registered.');
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    redirect_path('/admin/users.php?role=Admin');
}
mysqli_stmt_close($stmt);

$password_hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = mysqli_prepare($conn, "
    INSERT INTO admin_tbl (role, username, full_name, email, contact_number, address, password_hash, status, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'Active', NOW())
");
mysqli_stmt_bind_param($stmt, "sssssss", $admin_role, $username, $full_name, $email, $contact_number, $address, $password_hash);

if (mysqli_stmt_execute($stmt)) {
    set_message('success', 'New ' . $admin_role . ' (' . e($full_name) . ') registered successfully.');
} else {
    set_message('error', 'Failed to register admin account.');
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
redirect_path('/admin/users.php?role=Admin');
