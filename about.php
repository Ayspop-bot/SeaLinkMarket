<?php
/**
 * SeaLink Web Application
 * File: /about.php
 * Purpose: About SeaLink page — Image 4 wireframe with banner, description, and 3 info boxes.
 *          Accessible to logged-in users AND unauthenticated visitors (login/register links).
 *          Shows a simple "Back" button instead of the full nav header.
 * Connected To: Header "About Us" link, /index.php, /register.php
 * Uses: Layout templates (no nav tabs when not logged in)
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/app.php';

$page_title  = "About Us - SeaLink";
$hide_search = true;

// Check if user came from login or register (not logged in)
$is_guest = !is_logged_in();

// For guests: standalone page with no header/tabs
if ($is_guest) {
    $back_url = isset($_SERVER['HTTP_REFERER']) ? htmlspecialchars($_SERVER['HTTP_REFERER']) : BASE_URL . '/index.php';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $page_title; ?></title>
  <meta name="description" content="Learn about SeaLink — the integrated web-based marketplace and information hub connecting aquatic farmers and buyers in Santa Fe, Romblon.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,600&family=Poppins:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,400;1,600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/base.css">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/components.css">
</head>
<body>
<div style="max-width:860px; margin:0 auto; padding:40px 24px 60px 24px;">

  <!-- Back Button -->
  <div style="margin-bottom:24px; display:inline-flex; align-items:center; gap:8px;">
    <a class="back-arrow" href="<?php echo BASE_URL; ?>/login.php" onclick="if (document.referrer && document.referrer.indexOf(window.location.host) !== -1) { history.back(); return false; }" aria-label="Back" style="margin-bottom:0;">
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path fill="currentColor" d="M15.5 19 8.5 12l7-7 1.5 1.5L11.5 12l5.5 5.5z"/>
      </svg>
    </a>
    <a href="<?php echo BASE_URL; ?>/login.php" onclick="if (document.referrer && document.referrer.indexOf(window.location.host) !== -1) { history.back(); return false; }" style="font-weight:700; font-size:15px; color:var(--text); text-decoration:none;">Back</a>
  </div>

  <!-- About Us Content (Image 4 Wireframe) -->
  <?php include __DIR__ . '/about_content.php'; ?>
</div>
<script src="<?php echo BASE_URL; ?>/assets/js/base.js"></script>
</body>
</html>
    <?php
    exit;
}

// Logged-in users: use standard layout
require_once __DIR__ . '/includes/layout_top.php';
?>

<main class="dashboard-content">
  <div style="margin-bottom:20px; display:inline-flex; align-items:center; gap:8px;">
    <a class="back-arrow" href="<?php echo BASE_URL; ?>/index.php" onclick="if (document.referrer && document.referrer.indexOf(window.location.host) !== -1) { history.back(); return false; }" aria-label="Back" style="margin-bottom:0;">
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path fill="currentColor" d="M15.5 19 8.5 12l7-7 1.5 1.5L11.5 12l5.5 5.5z"/>
      </svg>
    </a>
    <a href="<?php echo BASE_URL; ?>/index.php" onclick="if (document.referrer && document.referrer.indexOf(window.location.host) !== -1) { history.back(); return false; }" style="font-weight:700; font-size:15px; color:var(--text); text-decoration:none;">Back</a>
  </div>
  <?php include __DIR__ . '/about_content.php'; ?>
</main>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>