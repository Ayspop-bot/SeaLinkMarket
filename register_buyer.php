<?php
/**
 * SeaLink Web Application
 * File: /register_buyer.php
 * Purpose: Buyer registration (status = Active).
 * Connected To: /register.php -> /register_buyer.php -> redirects to /index.php
 * Uses: DB buyer_tbl + duplicate checks across admin/buyer/farmer
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/db.php';

$field_errors = [];
$form = [
    'username'         => '',
    'full_name'        => '',
    'email'            => '',
    'contact_number'   => '',
    'facebook_account' => '',
];

$invalid = function (string $key) use (&$field_errors) {
    return isset($field_errors[$key]) ? 'is-invalid' : '';
};

/* ========== HANDLE POST ========== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $form['username']         = normalize_spaces($_POST['username'] ?? '');
    $form['full_name']        = normalize_spaces($_POST['full_name'] ?? '');
    $form['email']            = normalize_email($_POST['email'] ?? '');
    $raw_phone                = sanitize_input($_POST['contact_number'] ?? '');
    $form['contact_number']   = $raw_phone;
    $form['facebook_account'] = sanitize_input($_POST['facebook_account'] ?? '');

    $password         = (string)($_POST['password'] ?? '');
    $confirm_password = (string)($_POST['confirm_password'] ?? '');

    /* ========== REQUIRED ========== */
    if ($form['username'] === '')         $field_errors['username'] = 'Username is required.';
    if ($form['full_name'] === '')        $field_errors['full_name'] = 'Full name is required.';
    if ($form['email'] === '')            $field_errors['email'] = 'Email is required.';
    if ($raw_phone === '')                $field_errors['contact_number'] = 'Contact number is required.';
    if ($form['facebook_account'] === '') $field_errors['facebook_account'] = 'Facebook account is required.';
    if ($password === '')                 $field_errors['password'] = 'Password is required.';
    if ($confirm_password === '')         $field_errors['confirm_password'] = 'Confirm password is required.';

    /* ========== FORMAT CHECKS ========== */
    if ($form['email'] !== '' && !validate_email($form['email'])) {
        $field_errors['email'] = 'Please enter a valid email address.';
    }

    if ($form['username'] !== '' && !validate_username($form['username'], 50)) {
        $field_errors['username'] = 'Username format is invalid.';
    }

    if ($raw_phone !== '') {
        if (!phone_has_only_digits($raw_phone)) {
            $field_errors['contact_number'] = 'Contact number must contain numbers only.';
        } elseif (!validate_phone_strict_input($raw_phone)) {
            $field_errors['contact_number'] = 'Contact number must be 11 digits and start with 09.';
        }
    }

    if ($password !== '' && strlen($password) < 8) {
        $field_errors['password'] = 'Password must be at least 8 characters.';
    }

    if ($password !== '' && $confirm_password !== '' && $password !== $confirm_password) {
        $field_errors['confirm_password'] = 'Passwords do not match.';
    }

    /* ========== LENGTH LIMITS ========== */
    if (strlen($form['username']) > 50)          $field_errors['username'] = 'Username must be 50 characters or less.';
    if (strlen($form['full_name']) > 100)        $field_errors['full_name'] = 'Full name must be 100 characters or less.';
    if (strlen($form['email']) > 50)             $field_errors['email'] = 'Email must be 50 characters or less.';
    if (strlen($form['facebook_account']) > 255) $field_errors['facebook_account'] = 'Facebook account must be 255 characters or less.';

    /* ========== DUPLICATE CHECKS ========== */
    if (count($field_errors) === 0) {

        if (email_exists_any($conn, $form['email'])) {
            $field_errors['email'] = 'Email is already registered.';
        }

        if (username_exists_any($conn, $form['username'])) {
            $field_errors['username'] = 'Username is already taken.';
        }
    }

    /* ========== OPTIONAL PROFILE IMAGE UPLOAD ========== */
    $profile_image_path = null;

    if (count($field_errors) === 0 && isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {

        $tmp  = $_FILES['profile_image']['tmp_name'];
        $size = (int)$_FILES['profile_image']['size'];

        if ($size > (2 * 1024 * 1024)) {
            $field_errors['profile_image'] = 'Profile image must not exceed 2MB.';
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = $finfo ? finfo_file($finfo, $tmp) : '';
            if ($finfo) finfo_close($finfo);

            if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
                $field_errors['profile_image'] = 'Profile image must be JPG or PNG.';
            } else {
                $ext = ($mime === 'image/png') ? 'png' : 'jpg';
                $filename = 'buyer_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;

                $dir = __DIR__ . '/uploads/profiles/';
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }

                if (move_uploaded_file($tmp, $dir . $filename)) {
                    $profile_image_path = 'uploads/profiles/' . $filename;
                } else {
                    $field_errors['profile_image'] = 'Failed to upload profile image.';
                }
            }
        }
    }

    /* ========== INSERT BUYER ========== */
    if (count($field_errors) === 0) {

        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO buyer_tbl
            (username, full_name, email, password_hash, contact_number, facebook_account,
             profile_image, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Active', NOW())";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param(
            $stmt,
            "sssssss",
            $form['username'],
            $form['full_name'],
            $form['email'],
            $password_hash,
            $form['contact_number'],
            $form['facebook_account'],
            $profile_image_path
        );

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            mysqli_close($conn);

            set_message('success', 'Registration successful! You can now log in.');
            redirect_path('/index.php');
        }

        mysqli_stmt_close($stmt);
        $field_errors['form'] = 'Registration failed. Please try again.';
    }
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buyer Registration - SeaLink</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,600&family=Poppins:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,400;1,600&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/base.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/components.css">
</head>
<body>
<div class="container">
    <div class="form-container">

        <!-- Back button inside top of container (smaller and closer to edge) -->
        <div style="display:flex; justify-content:flex-start; margin-top:-18px; margin-left:-18px; margin-bottom:12px;">
            <a href="<?php echo BASE_URL; ?>/register.php" onclick="if (window.history.length > 1) { window.history.back(); return false; }" style="display:inline-flex; align-items:center; gap:5px; text-decoration:none; color:var(--text); font-size:13px; font-weight:700;">
                <span class="back-arrow" style="width:26px; height:26px; border-radius:7px; margin-bottom:0; display:inline-flex; align-items:center; justify-content:center;">
                    <svg width="13" height="13" viewBox="0 0 24 24" aria-hidden="true">
                        <path fill="currentColor" d="M15.5 19 8.5 12l7-7 1.5 1.5L11.5 12l5.5 5.5z"/>
                    </svg>
                </span>
                <span>Back</span>
            </a>
        </div>

        <div style="text-align:center; margin-bottom:24px;">
            <h1 style="font-family:var(--font-primary); font-size:24px; font-weight:800; margin:8px 0 4px 0; color:var(--text);">Buyer Registration</h1>
            <p class="subtitle" style="color:var(--muted); font-size:14px; margin:0;">Create your buyer account.</p>
        </div>

        <form action="<?php echo BASE_URL; ?>/register_buyer.php" method="POST" enctype="multipart/form-data">

            <div class="form-group">
                <label>Username *</label>
                <input type="text" name="username" required maxlength="50"
                       class="<?php echo $invalid('username'); ?>"
                       value="<?php echo e($form['username']); ?>"
                       placeholder="Enter username">
                <?php if (isset($field_errors['username'])) { ?>
                    <div class="field-error"><?php echo e($field_errors['username']); ?></div>
                <?php } ?>
            </div>

            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="full_name" required maxlength="100"
                       class="<?php echo $invalid('full_name'); ?>"
                       value="<?php echo e($form['full_name']); ?>"
                       placeholder="Enter full name">
                <?php if (isset($field_errors['full_name'])) { ?>
                    <div class="field-error"><?php echo e($field_errors['full_name']); ?></div>
                <?php } ?>
            </div>

            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" required maxlength="50"
                       class="<?php echo $invalid('email'); ?>"
                       value="<?php echo e($form['email']); ?>"
                       placeholder="name@gmail.com"
                       autocomplete="email">
                <?php if (isset($field_errors['email'])) { ?>
                    <div class="field-error"><?php echo e($field_errors['email']); ?></div>
                <?php } ?>
            </div>

            <div class="form-group">
                <label>Contact Number *</label>
                <input type="text" name="contact_number" required
                       maxlength="11"
                       inputmode="numeric"
                       pattern="^09[0-9]{9}$"
                       title="Format: 09XXXXXXXXX (11 digits, numbers only)"
                       class="<?php echo $invalid('contact_number'); ?>"
                       value="<?php echo e($form['contact_number']); ?>"
                       placeholder="09XXXXXXXXX"
                       autocomplete="tel">
                <?php if (isset($field_errors['contact_number'])) { ?>
                    <div class="field-error"><?php echo e($field_errors['contact_number']); ?></div>
                <?php } ?>
            </div>

            <div class="form-group">
                <label>Facebook Account *</label>
                <input type="text" name="facebook_account" required maxlength="255"
                       class="<?php echo $invalid('facebook_account'); ?>"
                       value="<?php echo e($form['facebook_account']); ?>"
                       placeholder="Enter Facebook profile link">
                <?php if (isset($field_errors['facebook_account'])) { ?>
                    <div class="field-error"><?php echo e($field_errors['facebook_account']); ?></div>
                <?php } ?>
            </div>

            <div class="form-group">
                <label>Password *</label>
                <input type="password" name="password" required minlength="8"
                       class="<?php echo $invalid('password'); ?>"
                       placeholder="Minimum 8 characters">
                <?php if (isset($field_errors['password'])) { ?>
                    <div class="field-error"><?php echo e($field_errors['password']); ?></div>
                <?php } ?>
            </div>

            <div class="form-group">
                <label>Confirm Password *</label>
                <input type="password" name="confirm_password" required minlength="8"
                       class="<?php echo $invalid('confirm_password'); ?>"
                       placeholder="Re-enter password">
                <?php if (isset($field_errors['confirm_password'])) { ?>
                    <div class="field-error"><?php echo e($field_errors['confirm_password']); ?></div>
                <?php } ?>
            </div>

            <div class="form-group">
                <label>Profile Image (Optional)</label>
                <input type="file" name="profile_image" accept="image/jpeg,image/png"
                       class="<?php echo $invalid('profile_image'); ?>">
                <?php if (isset($field_errors['profile_image'])) { ?>
                    <div class="field-error"><?php echo e($field_errors['profile_image']); ?></div>
                <?php } ?>
            </div>

            <?php if (isset($field_errors['form'])) { ?>
                <div class="field-error" style="margin-top:10px;"><?php echo e($field_errors['form']); ?></div>
            <?php } ?>

            <div style="display:flex; justify-content:center; margin-top:24px;">
                <button type="submit" class="btn btn-primary" style="padding:12px 36px; font-size:15px; font-weight:800; border-radius:10px;">Register</button>
            </div>

            <div style="margin-top:24px; padding-top:18px; border-top:1px solid var(--border); text-align:center;">
                <p style="margin:0; font-size:14px; color:var(--muted);">
                    Already have an account? <a href="<?php echo BASE_URL; ?>/login.php" style="font-weight:700; color:var(--primary); text-decoration:none;">Sign in here</a>
                </p>
            </div>

        </form>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/assets/js/base.js"></script>
</body>
</html>