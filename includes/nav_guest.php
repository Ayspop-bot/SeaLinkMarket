<?php
/**
 * SeaLink Web Application
 * File: /includes/nav_guest.php
 * Purpose: Public/Guest navigation tabs matching wireframe (Home, Market, Cart, Orders, Messages, Support, Information Hub).
 */

$active_tab = $active_tab ?? 'home';

$is_active = function ($key) use ($active_tab) {
    return ($key === $active_tab) ? 'active' : '';
};

$aria_current = function ($key) use ($active_tab) {
    return ($key === $active_tab) ? 'aria-current="page"' : '';
};
?>
<nav class="main-nav" aria-label="Guest navigation">
    <a class="nav-tab <?php echo $is_active('home'); ?>" <?php echo $aria_current('home'); ?>
       href="<?php echo BASE_URL; ?>/index.php">Home</a>

    <a class="nav-tab <?php echo $is_active('market'); ?>" <?php echo $aria_current('market'); ?>
       href="<?php echo BASE_URL; ?>/market.php">Market</a>

    <a class="nav-tab <?php echo $is_active('cart'); ?>" href="javascript:void(0)"
       onclick="openGuestPromptModal('Cart')">Cart</a>

    <a class="nav-tab <?php echo $is_active('orders'); ?>" href="javascript:void(0)"
       onclick="openGuestPromptModal('Orders')">Orders</a>

    <a class="nav-tab <?php echo $is_active('messages'); ?>" href="javascript:void(0)"
       onclick="openGuestPromptModal('Messages')">Messages</a>

    <a class="nav-tab <?php echo $is_active('infohub'); ?>" <?php echo $aria_current('infohub'); ?>
       href="<?php echo BASE_URL; ?>/infohub/index.php">Information Hub</a>

    <a class="nav-tab <?php echo $is_active('support'); ?>" <?php echo $aria_current('support'); ?>
       href="<?php echo BASE_URL; ?>/support/index.php">Support</a>
</nav>

<?php require_once __DIR__ . '/modal_guest.php'; ?>

