<?php
/**
 * SeaLink Web Application
 * File: /login.php
 * Purpose: Login entry page. Redirects logged-in users to correct dashboard, or displays login form.
 * Connected To: /login_process.php
 * Uses: Session (user_role)
 */

require_once __DIR__ . '/includes/functions.php';

/* ========== REDIRECT IF ALREADY LOGGED IN ========== */
if (is_logged_in()) {
    $role = $_SESSION['user_role'] ?? '';

    if ($role === 'farmer')        redirect_path('/farmer/dashboard.php');
    if ($role === 'buyer')         redirect_path('/buyer/dashboard.php');
    if ($role === 'Content Admin') redirect_path('/admin/content_dashboard.php');
    if ($role === 'User Admin')    redirect_path('/admin/user_dashboard.php');
}

$redirect_url = trim((string)($_GET['redirect'] ?? ''));
if ($redirect_url !== '' && ($redirect_url[0] !== '/' || str_starts_with($redirect_url, '//') || preg_match('/^\s*https?:/i', $redirect_url))) {
    $redirect_url = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In Account - SeaLink</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,600&family=Poppins:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,400;1,600&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/base.css">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/components.css">
</head>
<body>
<div class="container">

  <div class="form-container login-container" style="max-width: 440px; margin: 40px auto; padding: 36px 32px; border-radius: 16px; box-shadow: var(--card-shadow); border: 1px solid var(--border); background: var(--surface);">
    
    <!-- Back button inside top of container (smaller and closer to edge) -->
    <div style="display:flex; justify-content:flex-start; margin-top:-24px; margin-left:-20px; margin-bottom:12px;">
      <a href="<?php echo BASE_URL; ?>/index.php" onclick="if (window.history.length > 1) { window.history.back(); return false; }" style="display:inline-flex; align-items:center; gap:5px; text-decoration:none; color:var(--text); font-size:13px; font-weight:700;">
        <span class="back-arrow" style="width:26px; height:26px; border-radius:7px; margin-bottom:0; display:inline-flex; align-items:center; justify-content:center;">
          <svg width="13" height="13" viewBox="0 0 24 24" aria-hidden="true">
            <path fill="currentColor" d="M15.5 19 8.5 12l7-7 1.5 1.5L11.5 12l5.5 5.5z"/>
          </svg>
        </span>
        <span>Back</span>
      </a>
    </div>

    <div class="auth-top">
      <a href="<?php echo BASE_URL; ?>/index.php" class="auth-brand" style="text-decoration:none;">SeaLink</a>
      <a href="<?php echo BASE_URL; ?>/about.php" class="btn-link">About us</a>
    </div>

    <div style="text-align:center; margin-bottom: 20px;">
      <h1 style="font-family:var(--font-primary); font-size:24px; font-weight:800; margin:8px 0 4px 0; color:var(--text);">Sign In Account</h1>
      <p class="subtitle" style="color:var(--muted); font-size:14px; margin:0;">Enter your credentials to access your account</p>
    </div>

    <?php display_message(true); ?>

    <form action="<?php echo BASE_URL; ?>/login_process.php" method="POST" style="margin-top:16px;">
      <?php if (!empty($redirect_url)) { ?>
        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect_url); ?>">
      <?php } ?>

      <div class="form-group" style="margin-bottom:16px;">
        <label style="display:block; font-weight:700; font-size:13.5px; margin-bottom:6px; color:var(--text);">Username or Email</label>
        <div style="position:relative;">
          <input type="text" name="username" required autofocus placeholder="Enter your username or email"
                 style="width:100%; padding:11px 14px; border:1px solid var(--border); border-radius:10px; font-size:14px; background:#fbfcfe; transition:border-color .15s, box-shadow .15s;">
        </div>
      </div>

      <div class="form-group" style="margin-bottom:22px;">
        <label style="display:block; font-weight:700; font-size:13.5px; margin-bottom:6px; color:var(--text);">Password</label>
        <div style="position:relative;">
          <input type="password" name="password" required placeholder="Enter your password"
                 style="width:100%; padding:11px 14px; border:1px solid var(--border); border-radius:10px; font-size:14px; background:#fbfcfe; transition:border-color .15s, box-shadow .15s;">
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-block" style="padding:12px; font-size:15px; font-weight:800; border-radius:10px; letter-spacing:0.2px;">
        Continue
      </button>
    </form>

    <div style="margin-top:24px; padding-top:18px; border-top:1px solid var(--border); text-align:center;">
      <p style="margin:0; font-size:14px; color:var(--muted);">
        Don't have an account? <a href="<?php echo BASE_URL; ?>/register.php" style="font-weight:700; color:var(--primary); text-decoration:none;">Register here</a>
      </p>
    </div>
  </div>

</div>
<script src="<?php echo BASE_URL; ?>/assets/js/base.js"></script>
</body>
</html>
