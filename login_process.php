<?php
/**
 * SeaLink Web Application
 * File: /login_process.php
 * Purpose: Handles login POST; sets session; redirects by role.
 * Connected To: /index.php
 * Uses: admin_tbl, farmer_tbl, buyer_tbl
 * Notes: POST-only; session regenerated on successful login.
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

/* ========== POST ONLY ========== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_path('/login.php');
}

/* ========== INPUTS ========== */
$login_input = sanitize_input($_POST['username'] ?? '');
$password = (string)($_POST['password'] ?? '');
$redirect_url = trim((string)($_POST['redirect'] ?? ''));
if ($redirect_url !== '' && ($redirect_url[0] !== '/' || str_starts_with($redirect_url, '//') || preg_match('/^\s*https?:/i', $redirect_url))) {
    $redirect_url = '';
}

$fail_redirect = '/login.php' . (!empty($redirect_url) ? ('?redirect=' . urlencode($redirect_url)) : '');

if ($login_input === '' || $password === '') {
    set_message('error', 'Please enter username/email and password.');
    redirect_path($fail_redirect);
}

$invalid_msg = 'Invalid username/email or password.';

/* ========== HELPER: SET SESSION ========== */
function login_set_session($id, $role, $username, $full_name, $verification_status = null): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$id;
    $_SESSION['user_role'] = (string)$role;
    $_SESSION['username'] = (string)$username;
    $_SESSION['full_name'] = (string)$full_name;
    if ($verification_status !== null) {
        $_SESSION['verification_status'] = (string)$verification_status;
    }
}

/* ========== 1) ADMIN LOGIN ========== */
$sql = "SELECT admin_id, role, username, email, password_hash, full_name
        FROM admin_tbl
        WHERE (username = ? OR email = ?)
          AND status = 'Active'
        LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $login_input, $login_input);
mysqli_stmt_execute($stmt);
$admin = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if ($admin) {
    if (!password_verify($password, $admin['password_hash'])) {
        mysqli_close($conn);
        set_message('error', $invalid_msg);
        redirect_path($fail_redirect);
    }

    login_set_session($admin['admin_id'], $admin['role'], $admin['username'], $admin['full_name']);
    mysqli_close($conn);

    if ($_SESSION['user_role'] === 'Content Admin') {
        redirect_path('/admin/content_dashboard.php');
    }
    redirect_path('/admin/user_dashboard.php');
}

/* ========== 2) FARMER LOGIN ========== */
$sql = "SELECT farmer_id, username, email, password_hash, full_name, verification_status
        FROM farmer_tbl
        WHERE (username = ? OR email = ?)
          AND status = 'Active'
        LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $login_input, $login_input);
mysqli_stmt_execute($stmt);
$farmer = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if ($farmer) {
    if (!password_verify($password, $farmer['password_hash'])) {
        mysqli_close($conn);
        set_message('error', $invalid_msg);
        redirect_path($fail_redirect);
    }

    if (($farmer['verification_status'] ?? '') === 'Rejected') {
        mysqli_close($conn);
        set_message('error', 'Your farmer account was rejected. Contact User Admin.');
        redirect_path($fail_redirect);
    }

    login_set_session(
        $farmer['farmer_id'],
        'farmer',
        $farmer['username'],
        $farmer['full_name'],
        $farmer['verification_status']
    );

    /* ========== FARMER LOGIN: VERIFICATION TOAST RULES ========== */
    if (($farmer['verification_status'] ?? '') === 'Pending') {
        set_message('warning', 'Your account is pending verification. Product posting is disabled until verified.');
    }
    if (($farmer['verification_status'] ?? '') === 'Verified') {
        $ck = 'sealink_verified_notice_' . (int)$farmer['farmer_id'];
        if (empty($_COOKIE[$ck])) {
            set_message('success', 'Your account is verified. You can now post products.');
            setcookie($ck, '1', [
                'expires' => time() + (365 * 24 * 60 * 60),
                'path' => BASE_URL . '/',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
    }

    mysqli_close($conn);
    redirect_path('/farmer/dashboard.php');
}

/* ========== 3) BUYER LOGIN ========== */
$sql = "SELECT buyer_id, username, email, password_hash, full_name
        FROM buyer_tbl
        WHERE (username = ? OR email = ?)
          AND status = 'Active'
        LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $login_input, $login_input);
mysqli_stmt_execute($stmt);
$buyer = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if ($buyer) {
    if (!password_verify($password, $buyer['password_hash'])) {
        mysqli_close($conn);
        set_message('error', $invalid_msg);
        redirect_path($fail_redirect);
    }

    login_set_session($buyer['buyer_id'], 'buyer', $buyer['username'], $buyer['full_name']);
    mysqli_close($conn);
    
    // Redirect to specified url if provided, otherwise default buyer dashboard
    if (!empty($redirect_url)) {
        redirect_path($redirect_url);
    }
    redirect_path('/buyer/dashboard.php');
}

/* ========== FAIL ========== */
mysqli_close($conn);
set_message('error', $invalid_msg);
redirect_path($fail_redirect);