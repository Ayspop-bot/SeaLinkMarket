<?php
/**
* SeaLink Web Application
* File: /includes/layout_top.php
* Purpose: Opens HTML + shows locked header + toast + role nav tabs.
* Connected To: Included by protected pages.
* Uses: Session (user_role, username)
* Notes:
* - Header contains ONLY: app name + About us + profile icon
* - Search is Market-only (not in header)
* - Role CSS must load conditionally (prevents style leaks)
*/
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/app.php';

/* ========== PAGE DEFAULTS ========== */
if (!isset($page_title)) $page_title = $app_name;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo e($page_title); ?></title>

  <!-- ========== GOOGLE FONTS (Poppins & Nunito) ========== -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,600&family=Poppins:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,400;1,600&display=swap" rel="stylesheet">

  <!-- ========== BASE + SHARED CSS ========== -->
  <link rel="stylesheet" href="<?php echo $base_path; ?>/assets/css/base.css">
  <link rel="stylesheet" href="<?php echo $base_path; ?>/assets/css/components.css">
  <link rel="stylesheet" href="<?php echo $base_path; ?>/assets/css/pages.css">

  <!-- ========== ROLE CSS (ONLY ONE) ========== -->
  <?php if (is_logged_in() && ($_SESSION['user_role'] ?? '') === 'farmer' && file_exists(__DIR__ . '/../assets/css/farmer.css')) { ?>
    <link rel="stylesheet" href="<?php echo $base_path; ?>/assets/css/farmer.css">
  <?php } ?>
</head>

<body>
<div class="container">

  <!-- ========== HEADER (LOCKED: NO SEARCH, NO LOGOUT) ========== -->
  <header class="dashboard-header">
    <div class="logo">
      <h2><?php echo e($app_name); ?></h2>
    </div>

    <div class="header-right">
      <a href="<?php echo $base_path; ?>/about.php" class="btn-link">About us</a>

      <?php if (is_logged_in()) { ?>
        <span class="user-name"><?php echo e($_SESSION['username'] ?? ''); ?></span>

        <!-- Profile Dropdown Trigger with Color Reversal -->
        <div class="profile-dropdown-container" id="profileDropdownContainer">
          <button type="button" class="icon-btn" id="profileIconBtn" aria-label="Account Menu" onclick="toggleProfileDropdown(event)">
            <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <path fill="currentColor"
                d="M12 12a4 4 0 1 0-4-4a4 4 0 0 0 4 4m0 2c-4.42 0-8 2-8 4.5V21h16v-2.5c0-2.5-3.58-4.5-8-4.5"/>
            </svg>
          </button>

          <div class="profile-dropdown-menu" id="profileDropdownMenu">
            <a href="<?php echo $base_path; ?>/profile/index.php" class="profile-dropdown-item">
              Profile
            </a>
            <a href="<?php echo $base_path; ?>/notifications/index.php" class="profile-dropdown-item">
              Notification
            </a>
            <div class="profile-dropdown-divider"></div>
            <button type="button" class="profile-dropdown-item" style="color:#b42318;" onclick="openGlobalLogoutModal()">
              Logout
            </button>
          </div>
        </div>
      <?php } else { ?>
        <a href="<?php echo $base_path; ?>/login.php" class="btn btn-secondary btn-sm" style="padding:6px 14px; font-weight:700;">Sign in</a>
        <a href="<?php echo $base_path; ?>/register.php" class="btn btn-primary btn-sm" style="padding:6px 14px; font-weight:700; background:#fff; color:var(--primary); border-color:#fff;">Register</a>
      <?php } ?>
    </div>
  </header>

  <!-- ========== TOAST MESSAGE ========== -->
  <?php display_message(true); ?>

  <!-- ========== ROLE / GUEST NAV TABS ========== -->
  <?php
  if (empty($hide_nav)) {
    echo '<div class="nav-container">';
    if (is_logged_in()) {
      $role = $_SESSION['user_role'] ?? '';
      if ($role === 'farmer') require __DIR__ . '/nav_farmer.php';
      if ($role === 'buyer') require __DIR__ . '/nav_buyer.php';
      if ($role === 'Content Admin') require __DIR__ . '/nav_admin_content.php';
      if ($role === 'User Admin') require __DIR__ . '/nav_admin_user.php';
    } else {
      require __DIR__ . '/nav_guest.php';
    }
    echo '</div>';
  }
  ?>