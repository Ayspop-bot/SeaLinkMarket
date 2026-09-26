<?php
/**
 * SeaLink Web Application
 * File: /buyer/checkout.php
 * Purpose: Checkout page for selected cart items or Buy Now with order confirmation modal.
 * Connected To:
 * - /buyer/cart.php
 * - /buyer/product_detail.php
 * - /buyer/actions/place_order.php
 * Uses: cart_item_tbl, product_tbl, farmer_tbl, buyer_address_tbl
 */

require_once __DIR__ . '/../includes/auth_check.php';
check_access('buyer');

$hide_nav = true;
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$active_tab = 'cart';
$page_title = "Checkout - SeaLink";
require_once __DIR__ . '/../includes/layout_top.php';

$buyer_id = (int)$_SESSION['user_id'];
$mode = trim((string)($_GET['mode'] ?? 'cart'));
if (!in_array($mode, ['cart', 'buynow', 'reorder'], true)) {
    $mode = 'cart';
}
$reorder_order_id = (int)($_GET['order_id'] ?? 0);

$product_id = (int)($_GET['product_id'] ?? 0);
$qty = max(1, (int)($_GET['qty'] ?? 1));
$return_param = trim((string)($_GET['return'] ?? ''));

/* Safety check */
if ($return_param !== '' && ($return_param[0] !== '/' || preg_match('/^\s*https?:/i', $return_param))) {
    $return_param = '';
}
$back_url = $return_param ? (BASE_URL . $return_param) : ($mode === 'buynow' ? BASE_URL . '/buyer/market.php' : ($mode === 'reorder' ? BASE_URL . '/buyer/orders.php' : BASE_URL . '/buyer/cart.php'));

/* Fetch buyer details (Name, Contact Number) */
$buyer_info = [];
$b_stmt = mysqli_prepare($conn, "SELECT username, full_name, contact_number, email FROM buyer_tbl WHERE buyer_id = ? LIMIT 1");
if ($b_stmt) {
    mysqli_stmt_bind_param($b_stmt, "i", $buyer_id);
    mysqli_stmt_execute($b_stmt);
    $buyer_info = mysqli_fetch_assoc(mysqli_stmt_get_result($b_stmt)) ?? [];
    mysqli_stmt_close($b_stmt);
}

/* Fetch buyer's default address or first address */
$saved_addresses = [];
$default_address = null;

$stmt = mysqli_prepare($conn, "SELECT address_id, street, municipality, province, zip_code, is_default FROM buyer_address_tbl WHERE buyer_id = ? ORDER BY is_default DESC, address_id DESC");
mysqli_stmt_bind_param($stmt, "i", $buyer_id);
mysqli_stmt_execute($stmt);
$addr_res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($addr_res)) {
    $saved_addresses[] = $row;
    if ($row['is_default'] && !$default_address) {
        $default_address = $row;
    }
}
mysqli_stmt_close($stmt);

if (!$default_address && !empty($saved_addresses)) {
    $default_address = $saved_addresses[0];
}

// Check if specific address was chosen via query param
$chosen_addr_id = (int)($_GET['address_id'] ?? 0);
if ($chosen_addr_id > 0) {
    foreach ($saved_addresses as $addr) {
        if ((int)$addr['address_id'] === $chosen_addr_id) {
            $default_address = $addr;
            break;
        }
    }
}

/* Fetch available drop-off points configured by Admin */
$drop_off_points_raw = "Marketplace, Poblacion Santa Fe\nSanta Fe Municipal Port\nBarangay Taboboan Drop Point";
$set_res = mysqli_query($conn, "SELECT setting_value FROM site_settings_tbl WHERE setting_key = 'drop_off_points' LIMIT 1");
if ($set_res && $set_row = mysqli_fetch_assoc($set_res)) {
    if (!empty(trim($set_row['setting_value']))) {
        $drop_off_points_raw = $set_row['setting_value'];
    }
}
$drop_off_locations = array_filter(array_map('trim', explode("\n", str_replace("\r", "", $drop_off_points_raw))));
if (empty($drop_off_locations)) {
    $drop_off_locations = ['Marketplace, Poblacion Santa Fe'];
}

/* Retrieve checkout items grouped by farmer */
$checkout_groups = [];
$total_product_price = 0;
$delivery_fee_per_seller = 50.00; // Flat ₱50 per seller delivery fee

// Selected item IDs if coming from Cart checklist
$selected_items_filter = [];
if (!empty($_GET['selected_items']) && is_array($_GET['selected_items'])) {
    foreach ($_GET['selected_items'] as $sid) {
        $sid = (int)$sid;
        if ($sid > 0) $selected_items_filter[] = $sid;
    }
}

if ($mode === 'buynow') {
    if ($product_id <= 0) {
        set_message('error', 'Invalid product selected.');
        redirect_path('/buyer/market.php');
    }

    $stmt = mysqli_prepare($conn, "
        SELECT p.product_id, p.name, p.price, p.discounted_price, p.selling_deadline, p.image_url, p.stock_quantity, p.status,
               f.farmer_id, f.username AS farmer_name, f.address AS farmer_address
        FROM product_tbl p
        JOIN farmer_tbl f ON f.farmer_id = p.farmer_id
        WHERE p.product_id = ? AND p.status = 'Active'
    ");
    mysqli_stmt_bind_param($stmt, "i", $product_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $product = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$product || (int)$product['stock_quantity'] <= 0) {
        set_message('error', 'Product is currently unavailable or out of stock.');
        redirect_path('/buyer/market.php');
    }

    $qty = min($qty, (int)$product['stock_quantity']);
    $product['quantity'] = $qty;
    $eff_price = get_product_effective_price($product['price'], $product['discounted_price'] ?? null, $product['selling_deadline'] ?? null);
    $product['effective_price'] = $eff_price;

    $fid = (int)$product['farmer_id'];
    $checkout_groups[$fid] = [
        'farmer_name'    => $product['farmer_name'],
        'farmer_address' => $product['farmer_address'],
        'items'          => [$product]
    ];
    $total_product_price = $eff_price * $qty;

} elseif ($mode === 'reorder') {
    if ($reorder_order_id <= 0) {
        set_message('error', 'Invalid order selected for reorder.');
        redirect_path('/buyer/orders.php');
    }

    $ro_stmt = mysqli_prepare($conn, "SELECT order_id, farmer_id, address_id, fulfillment_type, payment_method FROM order_tbl WHERE order_id = ? AND buyer_id = ? LIMIT 1");
    mysqli_stmt_bind_param($ro_stmt, "ii", $reorder_order_id, $buyer_id);
    mysqli_stmt_execute($ro_stmt);
    $prev_order = mysqli_fetch_assoc(mysqli_stmt_get_result($ro_stmt));
    mysqli_stmt_close($ro_stmt);

    if (!$prev_order) {
        set_message('error', 'Order not found.');
        redirect_path('/buyer/orders.php');
    }

    // Default address from previous order if available
    if (!empty($prev_order['address_id'])) {
        foreach ($saved_addresses as $addr) {
            if ((int)$addr['address_id'] === (int)$prev_order['address_id']) {
                $default_address = $addr;
                break;
            }
        }
    }

    // Fetch items from that order
    $ro_items_sql = "
        SELECT oi.product_id, oi.quantity, p.name, p.price, p.discounted_price, p.selling_deadline, p.image_url, p.stock_quantity, p.status,
               f.farmer_id, f.username AS farmer_name, f.address AS farmer_address
        FROM order_item_tbl oi
        JOIN product_tbl p ON p.product_id = oi.product_id
        JOIN farmer_tbl f ON f.farmer_id = p.farmer_id
        WHERE oi.order_id = ?
        ORDER BY oi.order_item_id ASC
    ";
    $ro_items_stmt = mysqli_prepare($conn, $ro_items_sql);
    mysqli_stmt_bind_param($ro_items_stmt, "i", $reorder_order_id);
    mysqli_stmt_execute($ro_items_stmt);
    $ro_res = mysqli_stmt_get_result($ro_items_stmt);

    while ($row = mysqli_fetch_assoc($ro_res)) {
        $fid = (int)$row['farmer_id'];
        $item_qty = (int)$row['quantity'];
        if ((int)$row['stock_quantity'] > 0 && $row['status'] === 'Active') {
            $item_qty = min($item_qty, (int)$row['stock_quantity']);
        }
        $row['quantity'] = max(1, $item_qty);
        $eff_price = get_product_effective_price($row['price'], $row['discounted_price'] ?? null, $row['selling_deadline'] ?? null);
        $row['effective_price'] = $eff_price;

        if (!isset($checkout_groups[$fid])) {
            $checkout_groups[$fid] = [
                'farmer_name'    => $row['farmer_name'],
                'farmer_address' => $row['farmer_address'],
                'items'          => []
            ];
        }
        $checkout_groups[$fid]['items'][] = $row;
        $total_product_price += ($eff_price * $row['quantity']);
    }
    mysqli_stmt_close($ro_items_stmt);

    if (empty($checkout_groups)) {
        set_message('warning', 'The products from this order are no longer available.');
        redirect_path('/buyer/orders.php');
    }

} else {
    // Mode = cart (Filtered by selected_items if provided)
    $where_cart = "c.buyer_id = ? AND p.status = 'Active' AND p.stock_quantity > 0";
    if (!empty($selected_items_filter)) {
        $in_clause = implode(',', $selected_items_filter);
        $where_cart .= " AND c.cart_item_id IN ($in_clause)";
    }

    $sql = "
    SELECT 
        c.cart_item_id, c.product_id, c.quantity, p.name, p.price, p.discounted_price, p.selling_deadline, p.image_url, p.stock_quantity, p.status,
        f.farmer_id, f.username AS farmer_name, f.address AS farmer_address
    FROM cart_item_tbl c
    JOIN product_tbl p ON p.product_id = c.product_id
    JOIN farmer_tbl f ON f.farmer_id = p.farmer_id
    WHERE {$where_cart}
    ORDER BY f.farmer_id ASC
    ";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $buyer_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($res)) {
        $fid = (int)$row['farmer_id'];
        $item_qty = min((int)$row['quantity'], (int)$row['stock_quantity']);
        $row['quantity'] = $item_qty;
        $eff_price = get_product_effective_price($row['price'], $row['discounted_price'] ?? null, $row['selling_deadline'] ?? null);
        $row['effective_price'] = $eff_price;

        if (!isset($checkout_groups[$fid])) {
            $checkout_groups[$fid] = [
                'farmer_name'    => $row['farmer_name'],
                'farmer_address' => $row['farmer_address'],
                'items'          => []
            ];
        }
        $checkout_groups[$fid]['items'][] = $row;
        $total_product_price += ($eff_price * $item_qty);
    }
    mysqli_stmt_close($stmt);

    if (empty($checkout_groups)) {
        set_message('warning', 'Your cart has no available items selected for checkout.');
        redirect_path('/buyer/cart.php');
    }
}

mysqli_close($conn);

$total_delivery_fee  = count($checkout_groups) * $delivery_fee_per_seller;
$grand_total         = $total_product_price + $total_delivery_fee;
?>

<main class="dashboard-content buyer-dashboard">
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
        <a class="back-arrow" href="<?php echo e($back_url); ?>" onclick="if (document.referrer && document.referrer.indexOf(window.location.host) !== -1) { history.back(); return false; }" aria-label="Back">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path fill="currentColor" d="M15.5 19 8.5 12l7-7 1.5 1.5L11.5 12l5.5 5.5z"/>
            </svg>
        </a>
        <h1 style="margin:0; font-size:24px;">Order Checkout</h1>
    </div>

    <form method="POST" action="<?php echo BASE_URL; ?>/buyer/actions/place_order.php" enctype="multipart/form-data" id="placeOrderForm" onsubmit="return handlePlaceOrderSubmit(event)">
        <input type="hidden" name="mode" value="<?php echo e($mode); ?>">
        <?php if ($mode === 'buynow') { ?>
            <input type="hidden" name="product_id" value="<?php echo (int)$product_id; ?>">
            <input type="hidden" name="qty" id="form_buynow_qty_<?php echo (int)$product_id; ?>" value="<?php echo (int)$qty; ?>">
        <?php } elseif ($mode === 'reorder') { ?>
            <input type="hidden" name="reorder_order_id" value="<?php echo (int)$reorder_order_id; ?>">
            <?php foreach ($checkout_groups as $fid => $grp) { ?>
                <?php foreach ($grp['items'] as $it) { ?>
                    <input type="hidden" name="reorder_items[<?php echo (int)$it['product_id']; ?>]" id="form_reorder_qty_<?php echo (int)$it['product_id']; ?>" value="<?php echo (int)$it['quantity']; ?>">
                <?php } ?>
            <?php } ?>
        <?php } else { ?>
            <?php foreach ($selected_items_filter as $sid) { ?>
                <input type="hidden" name="selected_items[]" value="<?php echo (int)$sid; ?>">
            <?php } ?>
            <?php foreach ($checkout_groups as $fid => $grp) { ?>
                <?php foreach ($grp['items'] as $it) { ?>
                    <input type="hidden" name="cart_quantities[<?php echo (int)$it['cart_item_id']; ?>]" id="form_cart_qty_<?php echo (int)$it['product_id']; ?>" value="<?php echo (int)$it['quantity']; ?>">
                <?php } ?>
            <?php } ?>
        <?php } ?>

        <style>
            .checkout-grid {
                display: grid;
                grid-template-columns: 1.3fr 1fr;
                gap: 20px;
                align-items: start;
            }
            .checkout-step-panel {
                background: #fff;
                border: 1px solid var(--border);
                border-radius: 14px;
                padding: 20px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.02);
            }
            .checkout-step-panel h2 {
                font-size: 17px;
                font-weight: 800;
                margin: 0;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .seller-summary-box {
                background: #f8fafc;
                border: 1px solid var(--border);
                border-radius: 12px;
                padding: 12px 14px;
                margin-bottom: 12px;
            }
            @media (max-width: 900px) {
                .checkout-grid {
                    grid-template-columns: 1fr !important;
                    gap: 16px;
                }
                .checkout-step-panel {
                    padding: 16px 14px !important;
                }
            }
            @media (max-width: 480px) {
                .checkout-step-panel {
                    padding: 14px 12px !important;
                }
                .checkout-step-panel h2 {
                    font-size: 15.5px !important;
                }
                .checkout-step-panel .form-group {
                    margin-bottom: 12px !important;
                }
                .checkout-step-panel .form-group label {
                    font-size: 12.5px !important;
                }
                .checkout-step-panel input,
                .checkout-step-panel select {
                    font-size: 13.5px !important;
                    padding: 8px 10px !important;
                }
                .seller-summary-box {
                    padding: 10px 12px !important;
                }
            }
        </style>

        <div class="checkout-grid">
            <!-- Left Column: Order Summary (Bigger space: 1.3fr) -->
            <div>
                <div class="checkout-step-panel" style="border-radius:14px; padding:20px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; border-bottom:1px solid var(--border); padding-bottom:10px;">
                        <h2 style="font-size:18px; font-weight:800; margin:0; display:flex; align-items:center; gap:8px;">
                            <span></span> Order Summary
                        </h2>
                        <span class="badge" style="font-size:12px; font-weight:700;">
                            <?php 
                                $all_items_count = 0;
                                foreach ($checkout_groups as $g) { $all_items_count += count($g['items']); }
                                echo $all_items_count . ' item' . ($all_items_count !== 1 ? 's' : '');
                            ?>
                        </span>
                    </div>

                    <!-- Items Breakdown per Seller -->
                    <div style="display:flex; flex-direction:column; gap:12px; margin-bottom:16px;">
                        <?php foreach ($checkout_groups as $fid => $group) { 
                            $seller_items_subtotal = 0;
                            foreach ($group['items'] as $it) {
                                $seller_items_subtotal += ($it['effective_price'] * (int)$it['quantity']);
                            }
                            $seller_total = $seller_items_subtotal + $delivery_fee_per_seller;
                        ?>
                            <div class="seller-summary-box">
                                <!-- Seller Header -->
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; border-bottom:1px dashed var(--border); padding-bottom:6px;">
                                    <div style="font-weight:800; font-size:14px; color:var(--text); display:flex; align-items:center; gap:6px;">
                                        <span></span> <?php echo e($group['farmer_name']); ?>
                                    </div>
                                    <span class="small-muted" style="font-size:11.5px;"><?php echo count($group['items']); ?> item<?php echo count($group['items']) !== 1 ? 's' : ''; ?></span>
                                </div>

                                <!-- Items with images -->
                                <div style="display:flex; flex-direction:column; gap:8px;">
                                    <?php foreach ($group['items'] as $item) { 
                                        $item_eff_price = $item['effective_price'] ?? get_product_effective_price($item['price'], $item['discounted_price'] ?? null, $item['selling_deadline'] ?? null);
                                        $item_subtotal = $item_eff_price * (int)$item['quantity'];
                                        $is_item_discounted = is_product_discounted($item['price'], $item['discounted_price'] ?? null, $item['selling_deadline'] ?? null);
                                    ?>
                                        <div style="display:flex; align-items:center; justify-content:space-between; gap:10px;">
                                            <div style="display:flex; align-items:center; gap:10px; min-width:0; flex:1;">
                                                <!-- Product Image Thumbnail -->
                                                <div style="width:40px; height:40px; border-radius:8px; overflow:hidden; background:#f1f5f9; border:1px solid #e2e8f0; flex-shrink:0;">
                                                    <?php if (!empty($item['image_url'])) { ?>
                                                        <img src="<?php echo BASE_URL . '/' . e($item['image_url']); ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                                                    <?php } else { ?>
                                                        <div style="display:flex; align-items:center; justify-content:center; height:100%; font-size:16px;">🐟</div>
                                                    <?php } ?>
                                                </div>

                                                <div style="min-width:0; flex:1;">
                                                    <div style="font-size:13px; font-weight:700; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                                        <?php echo e($item['name']); ?>
                                                    </div>
                                                    <div class="small-muted" style="font-size:11.5px;">
                                                        ₱<?php echo number_format((float)$item_eff_price, 2); ?> / kg
                                                        <?php if ($is_item_discounted) { ?>
                                                            <span style="color:#dc2626; font-weight:800; font-size:10.5px;">(Sale)</span>
                                                        <?php } ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Qty Controls (copied from buyer/cart.php) -->
                                            <div style="display:flex; align-items:center; gap:6px; flex-shrink:0;">
                                                <button type="button" class="btn btn-sm btn-secondary" onclick="updateCheckoutQty(<?php echo (int)$item['product_id']; ?>, -1)" id="btn_minus_<?php echo (int)$item['product_id']; ?>" style="width:30px; height:30px; padding:0; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:15px;" <?php echo ((int)$item['quantity'] <= 1 ? 'disabled' : ''); ?>>-</button>

                                                <span id="qty_display_<?php echo (int)$item['product_id']; ?>" style="font-weight:800; width:44px; text-align:center; font-size:13.5px;"><?php echo (int)$item['quantity']; ?> kg</span>

                                                <button type="button" class="btn btn-sm btn-secondary" onclick="updateCheckoutQty(<?php echo (int)$item['product_id']; ?>, 1)" id="btn_plus_<?php echo (int)$item['product_id']; ?>" style="width:30px; height:30px; padding:0; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:15px;" <?php echo ((int)$item['quantity'] >= (int)$item['stock_quantity'] ? 'disabled' : ''); ?>>+</button>
                                            </div>

                                            <div style="font-size:14px; font-weight:800; color:var(--text); flex-shrink:0; min-width:70px; text-align:right;" id="item_subtotal_<?php echo (int)$item['product_id']; ?>">
                                                ₱<?php echo number_format($item_subtotal, 2); ?>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>

                                <!-- Seller Delivery Fee & Total (Changed from Seller Total) -->
                                <div style="margin-top:10px; padding-top:8px; border-top:1px solid #e2e8f0; font-size:12px; display:flex; flex-direction:column; gap:4px;">
                                    <div style="display:flex; justify-content:space-between; color:var(--muted);">
                                        <span>Delivery Fee</span>
                                        <span>₱<?php echo number_format($delivery_fee_per_seller, 2); ?></span>
                                    </div>
                                    <div style="display:flex; justify-content:space-between; font-weight:800; color:var(--text); font-size:13px; margin-top:2px;">
                                        <span>Total:</span>
                                        <span style="color:var(--primary);" id="seller_total_<?php echo $fid; ?>">₱<?php echo number_format($seller_total, 2); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>

                    <!-- Overall Cost Totals with Emojis -->
                    <div style="border-top:2px solid var(--border); padding-top:12px; display:flex; flex-direction:column; gap:8px;">
                        <div style="display:flex; justify-content:space-between; font-size:14px;">
                            <span class="small-muted">Items Subtotal:</span>
                            <strong id="summaryItemsSubtotal">₱<?php echo number_format($total_product_price, 2); ?></strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:14px;">
                            <span class="small-muted">Total Delivery Fee:</span>
                            <strong>₱<?php echo number_format($total_delivery_fee, 2); ?></strong>
                        </div>
                        <!-- Changed from Grand Total to Overall Total -->
                        <div style="display:flex; justify-content:space-between; font-size:18px; font-weight:900; color:var(--primary); margin-top:8px; border-top:1px dashed var(--border); padding-top:8px;">
                            <span>Overall Total:</span>
                            <span id="summaryGrandTotal">₱<?php echo number_format($grand_total, 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Delivery Address, Fulfillment Option, Payment Method & Button (1fr) -->
            <div style="display:flex; flex-direction:column; gap:16px;">
                
                <!-- 1. Delivery Address Card (No dropdown: Name, Phone, and Address display) -->
                <div class="checkout-step-panel">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <h2><span></span> Delivery Address</h2>
                        <a href="<?php echo BASE_URL; ?>/buyer/addresses.php?return=<?php echo urlencode('/buyer/checkout.php' . ($mode === 'buynow' ? '?mode=buynow&product_id='.$product_id.'&qty='.$qty : ($mode === 'reorder' ? '?mode=reorder&order_id='.$reorder_order_id : ''))); ?>" class="btn-link" style="font-size:12.5px; font-weight:700;">
                            Change Address
                        </a>
                    </div>

                    <?php if ($default_address) { ?>
                        <input type="hidden" name="address_id" id="address_id" value="<?php echo (int)$default_address['address_id']; ?>">
                        <div style="background:#f8fafc; border:1px solid var(--border); border-radius:12px; padding:14px;">
                            <div style="margin-bottom:6px;">
                                <div style="font-weight:800; font-size:15px; color:var(--text);">
                                    <?php echo e(!empty($buyer_info['full_name']) ? $buyer_info['full_name'] : ($buyer_info['username'] ?? 'Valued Buyer')); ?>
                                </div>
                                <div style="font-size:13px; color:var(--muted); margin-top:2px;">
                                    <?php echo e(!empty($buyer_info['contact_number']) ? $buyer_info['contact_number'] : 'No contact number provided'); ?>
                                </div>
                            </div>
                            <div style="font-size:13.5px; line-height:1.45; color:var(--text); padding-top:8px; border-top:1px dashed #e2e8f0;">
                                <span id="display_address_text"><?php echo e(trim($default_address['street'] . ', ' . $default_address['municipality'] . ', ' . $default_address['province'] . ' ' . $default_address['zip_code'])); ?></span>
                                <?php if (!empty($default_address['is_default'])) { ?>
                                    <span class="badge active" style="font-size:10px; margin-left:6px;">Default</span>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } else { ?>
                        <input type="hidden" name="address_id" value="0">
                        <div style="background:#fffbeb; border:1px solid #fef3c7; border-radius:10px; padding:12px; margin-bottom:12px; font-size:13px; color:#92400e;">
                            No delivery address saved yet. Please enter your address details below:
                        </div>
                        <div id="new_address_fields">
                            <div class="form-group">
                                <label>Street / Barangay *</label>
                                <input type="text" name="street" id="street_input" placeholder="e.g. Barangay Poblacion" required>
                            </div>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                                <div class="form-group">
                                    <label>Municipality *</label>
                                    <input type="text" name="municipality" id="muni_input" value="Santa Fe" required>
                                </div>
                                <div class="form-group">
                                    <label>Province *</label>
                                    <input type="text" name="province" id="prov_input" value="Romblon" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Zip Code</label>
                                <input type="text" name="zip_code" id="zip_input" value="5505">
                            </div>
                        </div>
                    <?php } ?>
                </div>

                <!-- 2. Fulfillment Method Card (Pickup shows seller's port/farm; Drop-off shows admin-configured drop points) -->
                <div class="checkout-step-panel">
                    <h2 style="margin-bottom:12px;"><span></span> Fulfillment Option</h2>
                    <div class="form-group" style="margin:0;">
                        <label>Fulfillment Type *</label>
                        <select name="fulfillment_type" id="fulfillment_select" required onchange="onFulfillmentChange(this.value)">
                            <option value="Delivery" <?php echo (!empty($prev_order['fulfillment_type']) && $prev_order['fulfillment_type'] === 'Delivery') ? 'selected' : (!isset($prev_order['fulfillment_type']) ? 'selected' : ''); ?>>Delivery (Direct to your address)</option>
                            <option value="Pickup" <?php echo (!empty($prev_order['fulfillment_type']) && $prev_order['fulfillment_type'] === 'Pickup') ? 'selected' : ''; ?>>Pickup (At seller's farm/port)</option>
                            <option value="Drop-off" <?php echo (!empty($prev_order['fulfillment_type']) && $prev_order['fulfillment_type'] === 'Drop-off') ? 'selected' : ''; ?>>Drop-off Point</option>
                        </select>
                    </div>

                    <!-- Delivery Notice -->
                    <div id="fulfillment_info_delivery" style="margin-top:10px; font-size:12.5px; color:var(--muted); line-height:1.4;">
                        Direct delivery will be brought to your delivery address specified above.
                    </div>

                    <!-- Pickup Notice (Displays seller's selling port / farm location) -->
                    <div id="fulfillment_info_pickup" style="display:none; margin-top:12px; padding:12px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px;">
                        <div style="font-weight:700; font-size:13px; color:#1e40af; margin-bottom:6px; display:flex; align-items:center; gap:6px;">
                            <span></span> Seller Pickup Location(s):
                        </div>
                        <div style="display:flex; flex-direction:column; gap:6px;">
                            <?php foreach ($checkout_groups as $fid => $group) { ?>
                                <div style="font-size:12.5px; color:#1e293b; background:#fff; padding:8px 10px; border-radius:6px; border:1px solid #dbeafe;">
                                    <div style="font-weight:700;"><?php echo e($group['farmer_name']); ?>:</div>
                                    <div style="color:var(--muted); margin-top:2px;">
                                        <?php echo !empty(trim($group['farmer_address'])) ? e($group['farmer_address']) : 'Santa Fe Municipal Fish Port / Local Dock'; ?>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="small-muted" style="font-size:11.5px; margin-top:8px;">
                            You will pick up your orders directly at the seller's designated selling port or farm location.
                        </div>
                    </div>

                    <!-- Drop-off Notice (Displays drop-off locations editable by Admin in settings.php) -->
                    <div id="fulfillment_info_dropoff" style="display:none; margin-top:12px; padding:12px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px;">
                        <label for="drop_off_select" style="font-weight:700; font-size:13px; color:#166534; display:block; margin-bottom:6px;">
                            <span></span> Select Drop-off Location:
                        </label>
                        <select name="drop_off_location" id="drop_off_select" style="width:100%; padding:8px 10px; border:1px solid #86efac; border-radius:8px; font-size:13px; background:#fff;">
                            <?php foreach ($drop_off_locations as $loc) { ?>
                                <option value="<?php echo e($loc); ?>"><?php echo e($loc); ?></option>
                            <?php } ?>
                        </select>
                        <div class="small-muted" style="font-size:11.5px; margin-top:8px; color:#15803d;">
                            Your order will be consolidated and delivered to this designated drop-off location for your convenient pickup.
                        </div>
                    </div>
                </div>

                <!-- 3. Payment Method Card -->
                <div class="checkout-step-panel">
                    <h2 style="margin-bottom:12px;"><span></span> Payment Method</h2>
                    
                    <div class="form-group" style="margin:0;">
                        <label>Select Payment *</label>
                        <select name="payment_method" id="payment_method" required onchange="toggleGCashField(this.value)">
                            <option value="COD" <?php echo (!empty($prev_order['payment_method']) && $prev_order['payment_method'] === 'COD') ? 'selected' : (!isset($prev_order['payment_method']) ? 'selected' : ''); ?>>Cash on Delivery (COD)</option>
                            <option value="GCash" <?php echo (!empty($prev_order['payment_method']) && $prev_order['payment_method'] === 'GCash') ? 'selected' : ''; ?>>GCash (Upload Receipt Screenshot)</option>
                        </select>
                    </div>

                    <div id="gcash_container" style="display:none; padding:14px; background:#f5fbfb; border:1px dashed var(--secondary); border-radius:10px; margin-top:10px;">
                        <div style="font-weight:700; font-size:14px; margin-bottom:6px; color:var(--primary);">GCash Payment Instructions</div>
                        <div class="small-muted" style="margin-bottom:10px;">
                            Please send the exact total amount to our verified SeaLink GCash account: <strong>0912-345-6789 (SeaLink Merchant)</strong> and upload a clear screenshot of your receipt.
                        </div>

                        <div class="form-group">
                            <label>GCash Receipt Screenshot *</label>
                            <input type="file" name="gcash_screenshot" id="gcash_screenshot_input" accept="image/jpeg,image/png">
                            <div class="small-muted" style="margin-top:4px;">JPG or PNG, max 2MB</div>
                        </div>
                    </div>
                </div>

                <!-- Place Order Button -->
                <div>
                    <button type="submit" class="btn btn-primary btn-block" style="font-size:15.5px; font-weight:800; padding:14px; border-radius:10px;">
                        Place Order Now
                    </button>
                    <div class="small-muted" style="text-align:center; margin-top:10px; font-size:11.5px;">
                        Safe and direct payment with local fishermen and farmers.
                    </div>
                </div>

            </div>
        </div>
    </form>
</main>

<!-- ORDER PLACEMENT CONFIRMATION MODAL -->
<div class="modal" id="orderConfirmModal" aria-hidden="true">
    <div class="modal-content" style="max-width:480px;">
        <div class="modal-header">
            <h2>Confirm Order</h2>
        </div>

        <div style="padding:10px 0;">
            <p style="margin:0 0 12px 0;">Review your order before proceeding:</p>

            <div style="background:#f9fbfb; border:1px solid var(--border); border-radius:10px; padding:14px; display:flex; flex-direction:column; gap:8px; font-size:14px;">
                <div>
                    <span class="small-muted">Delivery Address:</span>
                    <div id="confirm_address" style="font-weight:700; margin-top:2px;"></div>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span class="small-muted">Fulfillment:</span>
                    <strong id="confirm_fulfillment"></strong>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span class="small-muted">Payment Method:</span>
                    <strong id="confirm_payment"></strong>
                </div>
                <div style="display:flex; justify-content:space-between; border-top:1px dashed var(--border); padding-top:8px; font-size:16px; color:var(--primary);">
                    <span style="font-weight:800;">Total Amount:</span>
                    <strong id="confirm_total"></strong>
                </div>
            </div>
        </div>

        <div class="form-actions" style="justify-content:flex-end; margin-top:16px;">
            <button type="button" class="btn btn-secondary" onclick="closeModal('orderConfirmModal')">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="proceedPlaceOrder()">Place Order</button>
        </div>
    </div>
</div>

<script>
var checkoutProducts = {
    <?php 
    foreach ($checkout_groups as $fid => $grp) {
        foreach ($grp['items'] as $it) {
            $pid = (int)$it['product_id'];
            $eff = (float)$it['effective_price'];
            $qty = (int)$it['quantity'];
            $max_stock = (int)$it['stock_quantity'];
            echo "    {$pid}: { farmerId: {$fid}, price: {$eff}, qty: {$qty}, maxStock: {$max_stock} },\n";
        }
    }
    ?>
};

var deliveryFeePerSeller = <?php echo (float)$delivery_fee_per_seller; ?>;
var numSellers = <?php echo count($checkout_groups); ?>;

function updateCheckoutQty(productId, delta) {
    var p = checkoutProducts[productId];
    if (!p) return;
    var newQty = p.qty + delta;
    if (newQty < 1 || newQty > p.maxStock) return;
    p.qty = newQty;

    var disp = document.getElementById('qty_display_' + productId);
    if (disp) disp.textContent = p.qty + ' kg';

    var inpBuyNow = document.getElementById('form_buynow_qty_' + productId);
    if (inpBuyNow) inpBuyNow.value = p.qty;
    var inpReorder = document.getElementById('form_reorder_qty_' + productId);
    if (inpReorder) inpReorder.value = p.qty;
    var inpCart = document.getElementById('form_cart_qty_' + productId);
    if (inpCart) inpCart.value = p.qty;

    var btnMinus = document.getElementById('btn_minus_' + productId);
    if (btnMinus) btnMinus.disabled = (p.qty <= 1);
    var btnPlus = document.getElementById('btn_plus_' + productId);
    if (btnPlus) btnPlus.disabled = (p.qty >= p.maxStock);

    var itemTot = p.qty * p.price;
    var itemTotElem = document.getElementById('item_subtotal_' + productId);
    if (itemTotElem) itemTotElem.textContent = '₱' + itemTot.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});

    recalculateCheckoutTotals();
}

function recalculateCheckoutTotals() {
    var sellerTotals = {};
    var overallItemsSubtotal = 0;

    for (var pid in checkoutProducts) {
        var p = checkoutProducts[pid];
        var itemTot = p.qty * p.price;
        overallItemsSubtotal += itemTot;
        if (!sellerTotals[p.farmerId]) {
            sellerTotals[p.farmerId] = 0;
        }
        sellerTotals[p.farmerId] += itemTot;
    }

    for (var fid in sellerTotals) {
        var sTotal = sellerTotals[fid] + deliveryFeePerSeller;
        var sTotalElem = document.getElementById('seller_total_' + fid);
        if (sTotalElem) {
            sTotalElem.textContent = '₱' + sTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }
    }

    var subElem = document.getElementById('summaryItemsSubtotal');
    if (subElem) {
        subElem.textContent = '₱' + overallItemsSubtotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    var grandTotal = overallItemsSubtotal + (numSellers * deliveryFeePerSeller);
    var grandElem = document.getElementById('summaryGrandTotal');
    if (grandElem) {
        grandElem.textContent = '₱' + grandTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
}

function onFulfillmentChange(val) {
    var pickBox = document.getElementById('fulfillment_info_pickup');
    var dropBox = document.getElementById('fulfillment_info_dropoff');
    var delBox = document.getElementById('fulfillment_info_delivery');
    if (pickBox) pickBox.style.display = (val === 'Pickup') ? 'block' : 'none';
    if (dropBox) dropBox.style.display = (val === 'Drop-off') ? 'block' : 'none';
    if (delBox) delBox.style.display = (val === 'Delivery') ? 'block' : 'none';
}

function toggleGCashField(val) {
    var box = document.getElementById('gcash_container');
    var fileInput = document.getElementById('gcash_screenshot_input');
    if (val === 'GCash') {
        box.style.display = 'block';
        if (fileInput) fileInput.required = true;
    } else {
        box.style.display = 'none';
        if (fileInput) fileInput.required = false;
    }
}

function handlePlaceOrderSubmit(e) {
    e.preventDefault();
    
    // Populate summary in confirmation modal
    var addrDisplay = document.getElementById('display_address_text');
    var addrText = '';
    if (addrDisplay) {
        addrText = addrDisplay.textContent.trim();
    } else {
        var st = document.getElementById('street_input') ? document.getElementById('street_input').value.trim() : '';
        var mu = document.getElementById('muni_input') ? document.getElementById('muni_input').value.trim() : '';
        var pr = document.getElementById('prov_input') ? document.getElementById('prov_input').value.trim() : '';
        addrText = st + (mu ? ', ' + mu : '') + (pr ? ', ' + pr : '');
    }

    var fulSelect = document.getElementById('fulfillment_select');
    var fulVal = fulSelect ? fulSelect.value : 'Delivery';
    var fulText = fulVal;
    if (fulVal === 'Drop-off') {
        var dropLoc = document.getElementById('drop_off_select');
        if (dropLoc) {
            fulText += ' (' + dropLoc.value + ')';
        }
    } else if (fulVal === 'Pickup') {
        fulText += ' (Seller Dock/Farm)';
    }

    var paySelect = document.getElementById('payment_method');
    var pay = paySelect ? paySelect.value : 'COD';
    var totElem = document.getElementById('summaryGrandTotal');
    var tot = totElem ? totElem.textContent : '';

    document.getElementById('confirm_address').textContent = addrText;
    document.getElementById('confirm_fulfillment').textContent = fulText;
    document.getElementById('confirm_payment').textContent = pay;
    document.getElementById('confirm_total').textContent = tot;

    openModal('orderConfirmModal');
    return false;
}

function proceedPlaceOrder() {
    closeModal('orderConfirmModal');
    document.getElementById('placeOrderForm').submit();
}

document.addEventListener('DOMContentLoaded', function() {
    var fSelect = document.getElementById('fulfillment_select');
    if (fSelect && fSelect.value !== 'Delivery') {
        onFulfillmentChange(fSelect.value);
    }
    var pSelect = document.getElementById('payment_method');
    if (pSelect && pSelect.value === 'GCash') {
        toggleGCashField('GCash');
    }
});
</script>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
