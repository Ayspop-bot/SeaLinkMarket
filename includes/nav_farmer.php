<?php
/**
 * SeaLink Web Application
 * File: /includes/nav_farmer.php
 * Purpose: Farmer navigation tabs (Home, Manage Products, Orders, Messages, Market, Support, Information Hub).
 */

if (!isset($active_tab)) {
    $active_tab = '';
}

function nav_active($key, $active_tab) { return ($key === $active_tab) ? 'active' : ''; }
function nav_aria_current($key, $active_tab) { return ($key === $active_tab) ? 'aria-current="page"' : ''; }
?>
<nav class="main-nav" aria-label="Farmer navigation">
    <a class="nav-tab <?php echo nav_active('home', $active_tab); ?>"
       <?php echo nav_aria_current('home', $active_tab); ?>
       href="<?php echo BASE_URL; ?>/farmer/dashboard.php">Home</a>

    <a class="nav-tab <?php echo nav_active('market', $active_tab); ?>"
       <?php echo nav_aria_current('market', $active_tab); ?>
       href="<?php echo BASE_URL; ?>/market.php">Market</a>

    <a class="nav-tab <?php echo nav_active('orders', $active_tab); ?>"
       <?php echo nav_aria_current('orders', $active_tab); ?>
       href="<?php echo BASE_URL; ?>/farmer/orders.php">Orders</a>

    <a class="nav-tab <?php echo nav_active('messages', $active_tab); ?>"
       <?php echo nav_aria_current('messages', $active_tab); ?>
       href="<?php echo BASE_URL; ?>/farmer/messages.php">Messages</a>

    <a class="nav-tab <?php echo nav_active('manage_products', $active_tab); ?>"
       <?php echo nav_aria_current('manage_products', $active_tab); ?>
       href="<?php echo BASE_URL; ?>/farmer/manage_products.php">Manage Products</a>

    <a class="nav-tab <?php echo nav_active('infohub', $active_tab); ?>"
       <?php echo nav_aria_current('infohub', $active_tab); ?>
       href="<?php echo BASE_URL; ?>/infohub/index.php">Information Hub</a>

    <a class="nav-tab <?php echo nav_active('support', $active_tab); ?>"
       <?php echo nav_aria_current('support', $active_tab); ?>
       href="<?php echo BASE_URL; ?>/support/index.php">Support</a>
</nav>