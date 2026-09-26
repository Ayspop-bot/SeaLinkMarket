<?php
/**
 * SeaLink Web Application
 * File: /register.php
 * Purpose: Role selection (Farmer or Buyer).
 * Connected To: /register_farmer.php and /register_buyer.php
 */

require_once __DIR__ . '/includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register - SeaLink</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,600&family=Poppins:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,400;1,600&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/base.css">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/components.css">
</head>
<body>
<div class="container">

  <div class="register-choice">
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

    <div style="text-align:center; margin-bottom:20px;">
      <h1 style="font-family:var(--font-primary); font-size:24px; font-weight:800; margin:8px 0 4px 0; color:var(--text);">Create an Account</h1>
      <p class="subtitle" style="color:var(--muted); font-size:14px; margin:0;">Choose your account type to get started:</p>
    </div>

    <?php display_message(true); ?>

    <div class="choice-cards">
      <a href="<?php echo BASE_URL; ?>/register_farmer.php" class="choice-card">
        <div style="width:68px; height:68px; border-radius:50%; background:rgba(46, 134, 171, 0.12); color:var(--ocean-blue); display:flex; align-items:center; justify-content:center; margin-bottom:12px;">
          <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M6.5 12c2-3.5 6-4.5 9.5-3 2.5 1 4.5 3 6 3-1.5 0-3.5 2-6 3-3.5 1.5-7.5.5-9.5-3z"/>
            <circle cx="18" cy="10.5" r="1" fill="currentColor"/>
            <path d="M2 17c2-1 4-1 6 0s4 1 6 0 4-1 6 0"/>
            <path d="M2 21c2-1 4-1 6 0s4 1 6 0 4-1 6 0"/>
          </svg>
        </div>
        <h2>I'm a Farmer</h2>
        <p>I want to sell and market my fresh aquatic products directly</p>
        <span class="btn btn-cart" style="width:100%; text-align:center;">Register as Farmer</span>
      </a>

      <a href="<?php echo BASE_URL; ?>/register_buyer.php" class="choice-card">
        <div style="width:68px; height:68px; border-radius:50%; background:rgba(27, 108, 168, 0.12); color:var(--deep-cerulean); display:flex; align-items:center; justify-content:center; margin-bottom:12px;">
          <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
            <line x1="3" y1="6" x2="21" y2="6"/>
            <path d="M16 10a4 4 0 0 1-8 0"/>
          </svg>
        </div>
        <h2>I'm a Buyer</h2>
        <p>I want to browse, purchase, and receive quality aquatic goods</p>
        <span class="btn btn-confirm" style="width:100%; text-align:center;">Register as Buyer</span>
      </a>
    </div>

    <div style="margin-top:24px; padding-top:18px; border-top:1px solid var(--border); text-align:center;">
      <p style="margin:0; font-size:14px; color:var(--muted);">
        Already have an account? <a href="<?php echo BASE_URL; ?>/login.php" style="font-weight:700; color:var(--primary); text-decoration:none;">Sign in here</a>
      </p>
    </div>
  </div>

</div>
<script src="<?php echo BASE_URL; ?>/assets/js/base.js"></script>
</body>
</html>