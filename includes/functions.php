<?php
/**
 * SeaLink Web Application
 * File: /includes/functions.php
 * Purpose: Shared helper functions (escape, redirect, flash messages, validators).
 * Connected To: Used by pages, layouts, and actions.
 * Uses: Session (flash + login state)
 * Notes: Sanitize input lightly, but ALWAYS escape output using e().
 */

require_once __DIR__ . '/../config/app.php';

/* ========== SESSION START ========== */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ========== OUTPUT SAFETY ========== */
if (!function_exists('e')) {
    function e($text)
    {
        return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
    }
}

/* ========== REDIRECT HELPERS ========== */
function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

function redirect_path($path)
{
    if ($path === '' || $path[0] !== '/') {
        $path = '/' . $path;
    }
    redirect(BASE_URL . $path);
}

/* ========== LOGIN STATE ========== */
function is_logged_in()
{
    return isset($_SESSION['user_id'], $_SESSION['user_role']);
}

/* ========== FLASH MESSAGES ========== */
function set_message($type, $message)
{
    $_SESSION['flash'] = [
        'type' => $type,   // success | error | warning
        'text' => $message
    ];
}

function display_message($autohide = false)
{
    if (!isset($_SESSION['flash'])) {
        return;
    }

    $type = $_SESSION['flash']['type'] ?? 'success';
    $text = $_SESSION['flash']['text'] ?? '';

    $class = "alert alert-{$type}";

    if ($autohide) {
        $class .= " toast autohide";
        echo "<div id='toastMessage' class='{$class}' role='alert'>" . e($text) . "</div>";
    } else {
        echo "<div class='{$class}' role='alert'>" . e($text) . "</div>";
    }

    unset($_SESSION['flash']);
}

/* ========== INPUT HELPERS (BASIC) ========== */
function sanitize_input($data)
{
    return trim((string)$data);
}

function normalize_spaces($text)
{
    $text = sanitize_input($text);
    return preg_replace('/\s+/', ' ', $text);
}

function normalize_email($email)
{
    return strtolower(sanitize_input($email));
}

function normalize_phone($phone)
{
    $phone = sanitize_input($phone);
    return preg_replace('/\D+/', '', $phone);
}

/* ========== VALIDATORS ========== */
function validate_email($email)
{
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validate_phone($phone)
{
    return (bool)preg_match('/^09[0-9]{9}$/', $phone);
}

function validate_username($username, $maxLen = 100)
{
    $username = normalize_spaces($username);

    if ($username === '' || strlen($username) > $maxLen) {
        return false;
    }

    return (bool)preg_match('/^[A-Za-z0-9][A-Za-z0-9 ._-]*[A-Za-z0-9]$/', $username);
}

/* ========== DUPLICATE CHECKS (Admin + Farmer + Buyer) ========== */
    function username_exists_any($conn, $username)
    {
        $sql = "
            SELECT 1 FROM admin_tbl WHERE username = ?
            UNION ALL
            SELECT 1 FROM farmer_tbl WHERE username = ?
            UNION ALL
            SELECT 1 FROM buyer_tbl WHERE username = ?
            LIMIT 1
        ";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sss", $username, $username, $username);
        mysqli_stmt_execute($stmt);

        $res = mysqli_stmt_get_result($stmt);
        $exists = (mysqli_fetch_row($res) !== null);

        mysqli_stmt_close($stmt);
        return $exists;
    }

    function email_exists_any($conn, $email)
    {
        $sql = "
            SELECT 1 FROM admin_tbl WHERE email = ?
            UNION ALL
            SELECT 1 FROM farmer_tbl WHERE email = ?
            UNION ALL
            SELECT 1 FROM buyer_tbl WHERE email = ?
            LIMIT 1
        ";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sss", $email, $email, $email);
        mysqli_stmt_execute($stmt);

        $res = mysqli_stmt_get_result($stmt);
        $exists = (mysqli_fetch_row($res) !== null);

        mysqli_stmt_close($stmt);
        return $exists;
    }

/* ========== STRICT PHONE VALIDATION (REGISTER) ========== */
/**
 * validate_phone_strict_input()
 * Purpose: Strictly validates phone input (numbers only, exactly 11 digits, starts with 09).
 * Notes: Use this for registration forms to reject letters/symbols completely.
 */
function validate_phone_strict_input($raw_phone)
{
    $raw_phone = trim((string)$raw_phone);
    return (bool)preg_match('/^09[0-9]{9}$/', $raw_phone);
}

/**
 * phone_has_only_digits()
 * Purpose: Checks if input contains digits only (no letters, spaces, or symbols).
 */
function phone_has_only_digits($raw_phone)
{
    $raw_phone = trim((string)$raw_phone);
    return (bool)preg_match('/^[0-9]+$/', $raw_phone);
}

/**
 * check_and_expire_sales()
 * Purpose: Cleans up expired sales/discounts whose duration has elapsed.
 */
function check_and_expire_sales($conn)
{
    if (!$conn) return;

    $sale_exp_sql = "
        UPDATE product_tbl
        SET discounted_price = NULL,
            selling_deadline = NULL,
            updated_at = NOW()
        WHERE discounted_price IS NOT NULL
          AND selling_deadline IS NOT NULL
          AND selling_deadline <= NOW()
    ";
    @mysqli_query($conn, $sale_exp_sql);
}

/**
 * is_product_discounted()
 * Purpose: Returns true if product has an active valid discounted price.
 */
function is_product_discounted($price, $discounted_price, $selling_deadline = null)
{
    if ($discounted_price === null || $discounted_price === '' || (float)$discounted_price <= 0) {
        return false;
    }
    if ((float)$discounted_price >= (float)$price) {
        return false;
    }
    if (!empty($selling_deadline) && strtotime($selling_deadline) <= time()) {
        return false;
    }
    return true;
}

/**
 * get_product_effective_price()
 * Purpose: Returns discounted_price if active, otherwise original price.
 */
function get_product_effective_price($price, $discounted_price, $selling_deadline = null)
{
    if (is_product_discounted($price, $discounted_price, $selling_deadline)) {
        return (float)$discounted_price;
    }
    return (float)$price;
}

/**
 * check_and_expire_promotions()
 * Purpose: Automatically syncs and expires promotions whose duration has elapsed.
 * Turns off is_promoted and is_boosted and updates promotion_status to 'Expired'.
 */
function check_and_expire_promotions($conn)
{
    if (!$conn) return;

    // Expire ended flash sales / product discounts
    check_and_expire_sales($conn);

    // 1. Expire in product_tbl
    $exp_sql = "
        UPDATE product_tbl
        SET is_promoted = 0,
            is_boosted = 0,
            promotion_status = 'Expired',
            updated_at = NOW()
        WHERE (is_promoted = 1 OR is_boosted = 1 OR promotion_status = 'Active')
          AND (
            (promotion_end_date IS NOT NULL AND promotion_end_date <= NOW())
            OR (boost_expires_at IS NOT NULL AND boost_expires_at <= NOW())
          )
    ";
    @mysqli_query($conn, $exp_sql);

    // 2. Expire in product_boost_tbl
    $boost_exp_sql = "
        UPDATE product_boost_tbl
        SET status = 'Expired'
        WHERE status = 'Active'
          AND expires_at IS NOT NULL
          AND expires_at <= NOW()
    ";
    @mysqli_query($conn, $boost_exp_sql);
}

/**
 * get_promote_settings()
 * Purpose: Retrieves platform promotion & payment configuration (GCash details, QR code, 3-day and 7-day rates).
 * Automatically initializes promote_settings_tbl if it does not already exist.
 */
function get_promote_settings($conn)
{
    $defaults = [
        'id' => 1,
        'gcash_name' => 'SeaLink Admin',
        'gcash_number' => '09123456789',
        'gcash_qr_image' => null,
        'promo_price_3_days' => 50.00,
        'promo_price_7_days' => 100.00
    ];

    if (!$conn) {
        return $defaults;
    }

    // Auto-create table if not exists
    @mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS promote_settings_tbl (
            id INT PRIMARY KEY DEFAULT 1,
            gcash_name VARCHAR(100) NOT NULL DEFAULT 'SeaLink Admin',
            gcash_number VARCHAR(50) NOT NULL DEFAULT '09123456789',
            gcash_qr_image VARCHAR(255) NULL DEFAULT NULL,
            promo_price_3_days DECIMAL(10,2) NOT NULL DEFAULT 50.00,
            promo_price_7_days DECIMAL(10,2) NOT NULL DEFAULT 100.00,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    @mysqli_query($conn, "
        INSERT IGNORE INTO promote_settings_tbl (id, gcash_name, gcash_number, promo_price_3_days, promo_price_7_days)
        VALUES (1, 'SeaLink Admin', '09123456789', 50.00, 100.00)
    ");

    $res = @mysqli_query($conn, "SELECT * FROM promote_settings_tbl WHERE id = 1 LIMIT 1");
    if ($res && $row = mysqli_fetch_assoc($res)) {
        return [
            'id' => (int)$row['id'],
            'gcash_name' => $row['gcash_name'] ?? $defaults['gcash_name'],
            'gcash_number' => $row['gcash_number'] ?? $defaults['gcash_number'],
            'gcash_qr_image' => !empty($row['gcash_qr_image']) ? $row['gcash_qr_image'] : null,
            'promo_price_3_days' => (float)($row['promo_price_3_days'] ?? $defaults['promo_price_3_days']),
            'promo_price_7_days' => (float)($row['promo_price_7_days'] ?? $defaults['promo_price_7_days']),
            'updated_at' => $row['updated_at'] ?? null
        ];
    }

    return $defaults;
}

/**
 * get_promotion_packages()
 * Purpose: Retrieves dynamic promotion packages from promotion_packages_tbl.
 * Auto-creates the table and seeds default 3-day and 7-day packages if empty.
 */
function get_promotion_packages($conn, $active_only = false)
{
    $packages = [];
    if (!$conn) return $packages;

    // Auto-create promotion_packages_tbl if not exists
    @mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS promotion_packages_tbl (
            id INT AUTO_INCREMENT PRIMARY KEY,
            package_name VARCHAR(150) NOT NULL,
            duration_days INT NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Check if table is empty; if so, seed standard defaults
    $chk = @mysqli_query($conn, "SELECT COUNT(*) AS c FROM promotion_packages_tbl");
    $count = 0;
    if ($chk && $r = mysqli_fetch_assoc($chk)) {
        $count = (int)$r['c'];
    }
    if ($count === 0) {
        @mysqli_query($conn, "
            INSERT INTO promotion_packages_tbl (package_name, duration_days, price, is_active)
            VALUES 
            ('3-Day Featured Catch', 3, 50.00, 1),
            ('7-Day Featured Catch', 7, 100.00, 1)
        ");
    }

    $where = $active_only ? "WHERE is_active = 1" : "";
    $sql = "SELECT id, package_name, duration_days, price, is_active, created_at, updated_at 
            FROM promotion_packages_tbl 
            {$where} 
            ORDER BY duration_days ASC, price ASC";
    $res = @mysqli_query($conn, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $packages[] = [
                'id' => (int)$row['id'],
                'package_name' => $row['package_name'],
                'duration_days' => (int)$row['duration_days'],
                'price' => (float)$row['price'],
                'is_active' => (int)$row['is_active'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];
        }
    }

    return $packages;
}

/**
 * get_promotion_package_by_id()
 * Purpose: Retrieves a specific promotion package by its primary key ID.
 */
function get_promotion_package_by_id($conn, $package_id)
{
    $package_id = (int)$package_id;
    if (!$conn || $package_id <= 0) return null;

    $stmt = mysqli_prepare($conn, "SELECT id, package_name, duration_days, price, is_active FROM promotion_packages_tbl WHERE id = ? LIMIT 1");
    if (!$stmt) return null;

    mysqli_stmt_bind_param($stmt, "i", $package_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $pkg = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if ($pkg) {
        return [
            'id' => (int)$pkg['id'],
            'package_name' => $pkg['package_name'],
            'duration_days' => (int)$pkg['duration_days'],
            'price' => (float)$pkg['price'],
            'is_active' => (int)$pkg['is_active']
        ];
    }

    return null;
}
