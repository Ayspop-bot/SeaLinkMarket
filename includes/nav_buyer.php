<?php
/**
 * SeaLink Web Application
 * File: /includes/nav_buyer.php
 * Purpose: Buyer navigation tabs (Home, Market, Cart, Orders, Messages, Support, Information Hub).
 */

$active_tab = $active_tab ?? '';

$is_active = function ($key) use ($active_tab) {
    return ($key === $active_tab) ? 'active' : '';
};

$aria_current = function ($key) use ($active_tab) {
    return ($key === $active_tab) ? 'aria-current="page"' : '';
};
?>
<nav class="main-nav" aria-label="Buyer navigation">
    <a class="nav-tab <?php echo $is_active('home'); ?>" <?php echo $aria_current('home'); ?>
       href="<?php echo BASE_URL; ?>/buyer/dashboard.php">Home</a>

    <a class="nav-tab <?php echo $is_active('market'); ?>" <?php echo $aria_current('market'); ?>
       href="<?php echo BASE_URL; ?>/market.php">Market</a>

    <a class="nav-tab <?php echo $is_active('cart'); ?>" <?php echo $aria_current('cart'); ?>
       href="<?php echo BASE_URL; ?>/buyer/cart.php">Cart</a>

    <a class="nav-tab <?php echo $is_active('orders'); ?>" <?php echo $aria_current('orders'); ?>
       href="<?php echo BASE_URL; ?>/buyer/orders.php">Orders</a>

    <a class="nav-tab <?php echo $is_active('messages'); ?>" <?php echo $aria_current('messages'); ?>
       href="<?php echo BASE_URL; ?>/buyer/messages.php">Messages</a>

    <a class="nav-tab <?php echo $is_active('infohub'); ?>" <?php echo $aria_current('infohub'); ?>
       href="<?php echo BASE_URL; ?>/infohub/index.php">Information Hub</a>

    <a class="nav-tab <?php echo $is_active('support'); ?>" <?php echo $aria_current('support'); ?>
       href="<?php echo BASE_URL; ?>/support/index.php">Support</a>
</nav>