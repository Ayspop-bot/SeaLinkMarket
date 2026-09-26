<?php
/**
 * SeaLink Web Application
 * File: /includes/nav_admin_user.php
 * Purpose: User Admin navigation tabs (Home, Users, Product, Market, Info Hub, Analytics, Support, Setting).
 */

$active_tab = $active_tab ?? '';

$is_active = function ($key) use ($active_tab) {
    return ($key === $active_tab) ? 'active' : '';
};

$aria_current = function ($key) use ($active_tab) {
    return ($key === $active_tab) ? 'aria-current="page"' : '';
};
?>
<nav class="main-nav" aria-label="User Admin navigation">
    <a class="nav-tab <?php echo $is_active('home'); ?>"
       <?php echo $aria_current('home'); ?>
       href="<?php echo BASE_URL; ?>/admin/user_dashboard.php">Home</a>

    <a class="nav-tab <?php echo $is_active('users'); ?>"
       <?php echo $aria_current('users'); ?>
       href="<?php echo BASE_URL; ?>/admin/users.php">Users</a>

    <a class="nav-tab <?php echo $is_active('products'); ?>"
       <?php echo $aria_current('products'); ?>
       href="<?php echo BASE_URL; ?>/admin/products.php">Product</a>

    <a class="nav-tab <?php echo $is_active('market'); ?>"
       <?php echo $aria_current('market'); ?>
       href="<?php echo BASE_URL; ?>/market.php">Market</a>

    <a class="nav-tab <?php echo $is_active('infohub'); ?>"
       <?php echo $aria_current('infohub'); ?>
       href="<?php echo BASE_URL; ?>/infohub/index.php">Info Hub</a>

    <a class="nav-tab <?php echo $is_active('analytics'); ?>"
       <?php echo $aria_current('analytics'); ?>
       href="<?php echo BASE_URL; ?>/admin/analytics.php">Analytics</a>

    <a class="nav-tab <?php echo $is_active('support'); ?>"
       <?php echo $aria_current('support'); ?>
       href="<?php echo BASE_URL; ?>/admin/support.php">Support</a>

    <a class="nav-tab <?php echo $is_active('settings'); ?>"
       <?php echo $aria_current('settings'); ?>
       href="<?php echo BASE_URL; ?>/admin/settings.php">Setting</a>
</nav>