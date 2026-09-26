<?php
/**
 * SeaLink Web Application
 * File: /farmer/order_details.php
 * Purpose: Full Order Details page for farmers showing complete buyer contact, delivery address, fulfillment, payment details, and status controls.
 * Connected To:
 * - /farmer/orders.php
 * - /farmer/actions/update_order_status.php
 * - /farmer/actions/cancel_order.php
 * Uses: order_tbl, order_item_tbl, product_tbl, buyer_tbl, buyer_address_tbl
 */

require_once __DIR__ . '/../includes/auth_check.php';
check_access('farmer');

$hide_nav = true;
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$active_tab = 'orders';
$page_title = "Order Details - SeaLink";
require_once __DIR__ . '/../includes/layout_top.php';

$farmer_id  = (int)$_SESSION['user_id'];
$order_id   = (int)($_GET['order_id'] ?? 0);
$return_url = trim((string)($_GET['return'] ?? '/farmer/orders.php'));

if ($order_id <= 0) {
    redirect_path('/farmer/orders.php');
}

// Fetch order details with buyer and address info
$stmt = mysqli_prepare($conn, "
    SELECT o.*,
           b.username AS buyer_username, b.full_name AS buyer_fullname, b.contact_number AS buyer_contact,
           b.facebook_account AS buyer_facebook, b.email AS buyer_email,
           addr.street, addr.municipality, addr.province, addr.zip_code
    FROM order_tbl o
    JOIN buyer_tbl b ON b.buyer_id = o.buyer_id
    LEFT JOIN buyer_address_tbl addr ON addr.address_id = o.address_id
    WHERE o.order_id = ? AND o.farmer_id = ?
    LIMIT 1
");
mysqli_stmt_bind_param($stmt, "ii", $order_id, $farmer_id);
mysqli_stmt_execute($stmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$order) {
    mysqli_close($conn);
    redirect_path('/farmer/orders.php');
}

// Fetch order items
$items = [];
$stmt = mysqli_prepare($conn, "
    SELECT oi.order_item_id, oi.product_id, oi.quantity, oi.price_at_purchase,
           p.name AS product_name, p.image_url
    FROM order_item_tbl oi
    JOIN product_tbl p ON p.product_id = oi.product_id
    WHERE oi.order_id = ?
");
mysqli_stmt_bind_param($stmt, "i", $order_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $items[] = $row;
}
mysqli_stmt_close($stmt);
mysqli_close($conn);

// Financial calculations
$calculated_subtotal = 0;
foreach ($items as $it) {
    $calculated_subtotal += ((float)$it['price_at_purchase'] * (int)$it['quantity']);
}
$shipping_fee = (float)($order['shipping_fee'] ?? 0.00);
$total_amount = (float)($order['total_amount'] ?? ($calculated_subtotal + $shipping_fee));

// Buyer & address info
$buyer_name    = !empty($order['buyer_fullname']) ? $order['buyer_fullname'] : $order['buyer_username'];
$buyer_phone   = !empty($order['buyer_contact']) ? $order['buyer_contact'] : 'Not provided';
$buyer_fb      = trim((string)($order['buyer_facebook'] ?? ''));
$buyer_email   = trim((string)($order['buyer_email'] ?? ''));

$addr_parts    = array_filter([$order['street'] ?? '', $order['municipality'] ?? '', $order['province'] ?? '', $order['zip_code'] ?? '']);
$delivery_addr = !empty($addr_parts) ? implode(', ', $addr_parts) : 'Guinbirayan, Santa Fe, Romblon';

// Status styling & message
$status_text = $order['order_status'];
$badge_bg    = '#fef3c7';
$badge_color = '#b45309';

if ($status_text === 'Confirmed') {
    $badge_bg    = '#e0f2fe';
    $badge_color = '#0369a1';
} elseif (in_array($status_text, ['Ready for Delivery', 'Ready for Pickup', 'Ready for Drop-off', 'Ready for fulfillment'], true)) {
    $badge_bg    = '#dcfce7';
    $badge_color = '#15803d';
} elseif ($status_text === 'Completed') {
    $badge_bg    = '#d1fae5';
    $badge_color = '#065f46';
} elseif ($status_text === 'Cancelled') {
    $badge_bg    = '#fee2e2';
    $badge_color = '#991b1b';
}

$is_cancelled = ($order['order_status'] === 'Cancelled');
$is_completed = ($order['order_status'] === 'Completed');
$is_out_for_delivery = in_array($order['order_status'], ['Ready for Delivery', 'Ready for fulfillment', 'Ready for Drop-off', 'Ready for Pickup', 'Completed'], true);

$status_levels = [
    'Order Placed'          => 1,
    'Confirmed'             => 2,
    'Ready for fulfillment' => 3,
    'Ready for Pickup'      => 3,
    'Ready for Delivery'    => 3,
    'Ready for Drop-off'    => 3,
    'Completed'             => 4
];

$allowed_statuses = [
    'Order Placed'          => 'Pending Order',
    'Confirmed'             => 'Processing',
    'Ready for fulfillment' => 'Ready for fulfillment',
    'Completed'             => 'Delivered'
];
$current_order_status = $order['order_status'];
$current_level = $status_levels[$current_order_status] ?? 1;
?>

<main class="dashboard-content farmer-dashboard">
    <!-- Header with Back Arrow matching buyer/order_details.php -->
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
        <div style="display:flex; align-items:center; gap:12px;">
            <a class="back-arrow" href="<?php echo BASE_URL . e($return_url); ?>" onclick="if (window.history.length > 1) { window.history.back(); return false; }" aria-label="Back" style="width:32px; height:32px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; text-decoration:none;">
                <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="currentColor" d="M15.5 19 8.5 12l7-7 1.5 1.5L11.5 12l5.5 5.5z"/>
                </svg>
            </a>
            <div>
                <h1 style="margin:0; font-size:24px;">Order Details</h1>
                <div class="small-muted" style="margin-top:2px;">
                    Order no. <?php echo (int)$order['order_id']; ?> &bull; Placed on <?php echo date('M d, Y h:i A', strtotime($order['order_date'])); ?>
                </div>
            </div>
        </div>
        <div>
            <span style="font-size:12px; font-weight:800; background:<?php echo $badge_bg; ?>; color:<?php echo $badge_color; ?>; padding:5px 14px; border-radius:999px; text-transform:uppercase; letter-spacing:0.3px;">
                <?php echo e($order['order_status']); ?>
            </span>
        </div>
    </div>

    <!-- Horizontal Order Timeline Tracker: [Placed] ----- [Ready for fulfillment] ----- [Completed / Cancelled] -->
    <div class="section-card" style="margin-top:0; margin-bottom:18px; padding:18px 24px;">
        <div style="display:flex; align-items:center; justify-content:space-between; position:relative; max-width:620px; margin:0 auto;">
            <!-- Step 1: Placed -->
            <div style="display:flex; flex-direction:column; align-items:center; z-index:2; position:relative;">
                <div style="width:34px; height:34px; border-radius:50%; background:var(--primary); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:13px; box-shadow:0 2px 6px rgba(0,0,0,0.12);">
                    ✓
                </div>
                <span style="font-size:12px; font-weight:800; color:var(--text); margin-top:6px;">Placed</span>
            </div>

            <!-- Connecting Line 1-2 -->
            <div style="flex:1; height:4px; background:<?php echo $is_out_for_delivery ? 'var(--primary)' : '#e2e8f0'; ?>; margin:0 -8px; margin-bottom:20px; z-index:1;"></div>

            <!-- Step 2: Ready for fulfillment -->
            <div style="display:flex; flex-direction:column; align-items:center; z-index:2; position:relative;">
                <div style="width:34px; height:34px; border-radius:50%; background:<?php echo $is_out_for_delivery ? 'var(--primary)' : '#f1f5f9'; ?>; color:<?php echo $is_out_for_delivery ? '#fff' : 'var(--muted)'; ?>; border:<?php echo $is_out_for_delivery ? 'none' : '2px solid #cbd5e1'; ?>; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:13px;">
                    <?php echo ($is_completed ? '✓' : '2'); ?>
                </div>
                <span style="font-size:12px; font-weight:800; color:<?php echo $is_out_for_delivery ? 'var(--text)' : 'var(--muted)'; ?>; margin-top:6px;">Fulfillment</span>
            </div>

            <!-- Connecting Line 2-3 -->
            <div style="flex:1; height:4px; background:<?php echo $is_completed ? 'var(--primary)' : ($is_cancelled ? '#fca5a5' : '#e2e8f0'); ?>; margin:0 -8px; margin-bottom:20px; z-index:1;"></div>

            <!-- Step 3: Completed or Cancelled -->
            <?php if ($is_cancelled) { ?>
                <div style="display:flex; flex-direction:column; align-items:center; z-index:2; position:relative;">
                    <div style="width:34px; height:34px; border-radius:50%; background:#dc2626; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:13px;">
                        ✕
                    </div>
                    <span style="font-size:12px; font-weight:800; color:#dc2626; margin-top:6px;">Cancelled</span>
                </div>
            <?php } else { ?>
                <div style="display:flex; flex-direction:column; align-items:center; z-index:2; position:relative;">
                    <div style="width:34px; height:34px; border-radius:50%; background:<?php echo $is_completed ? 'var(--primary)' : '#f1f5f9'; ?>; color:<?php echo $is_completed ? '#fff' : 'var(--muted)'; ?>; border:<?php echo $is_completed ? 'none' : '2px solid #cbd5e1'; ?>; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:13px;">
                        <?php echo ($is_completed ? '✓' : '3'); ?>
                    </div>
                    <span style="font-size:12px; font-weight:800; color:<?php echo $is_completed ? 'var(--text)' : 'var(--muted)'; ?>; margin-top:6px;">Delivered</span>
                </div>
            <?php } ?>
        </div>
    </div>

    <style>
        .order-details-grid {
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
            padding: 14px 16px;
            margin-bottom: 12px;
        }
        @media (max-width: 900px) {
            .order-details-grid {
                grid-template-columns: 1fr !important;
                gap: 16px;
            }
            .checkout-step-panel {
                padding: 16px 14px !important;
            }
        }
    </style>

    <div class="order-details-grid">
        <!-- Left Column: Purchased Items Summary (1.3fr) -->
        <div>
            <div class="checkout-step-panel">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; border-bottom:1px solid var(--border); padding-bottom:10px;">
                    <h2>Purchased Items</h2>
                    <span class="badge" style="font-size:12px; font-weight:700;">
                        <?php echo count($items); ?> item<?php echo count($items) !== 1 ? 's' : ''; ?>
                    </span>
                </div>

                <!-- Buyer Header Box -->
                <div class="seller-summary-box">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; border-bottom:1px dashed var(--border); padding-bottom:8px; flex-wrap:wrap; gap:8px;">
                        <div style="font-weight:800; font-size:14.5px; color:var(--text); display:flex; align-items:center; gap:6px;">
                            <span>Customer: <strong><?php echo e($buyer_name); ?></strong></span>
                            <span class="small-muted" style="font-size:12px; font-weight:normal;">(@<?php echo e($order['buyer_username']); ?>)</span>
                        </div>
                        <a href="<?php echo BASE_URL; ?>/farmer/messages.php?buyer_id=<?php echo (int)$order['buyer_id']; ?>" style="color:var(--primary); font-size:12.5px; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:4px; padding:3px 10px; border-radius:6px; background:#eff6ff;" onmouseover="this.style.background='#dbeafe';" onmouseout="this.style.background='#eff6ff';">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            <span>Chat Buyer</span>
                        </a>
                    </div>

                    <!-- Products List -->
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <?php foreach ($items as $item) { 
                            $item_subtotal = (float)$item['price_at_purchase'] * (int)$item['quantity'];
                        ?>
                            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; padding-bottom:10px; border-bottom:1px dashed #e2e8f0;">
                                <div style="display:flex; align-items:center; gap:12px; min-width:0; flex:1;">
                                    <div style="width:52px; height:52px; border-radius:10px; overflow:hidden; background:#f1f5f9; border:1px solid #e2e8f0; flex-shrink:0;">
                                        <?php if (!empty($item['image_url'])) { ?>
                                            <img src="<?php echo BASE_URL . '/' . e($item['image_url']); ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                                        <?php } else { ?>
                                            <div style="display:flex; align-items:center; justify-content:center; height:100%; font-size:18px;">🐟</div>
                                        <?php } ?>
                                    </div>

                                    <div style="min-width:0; flex:1;">
                                        <div style="font-size:14px; font-weight:800; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                            <?php echo e($item['product_name']); ?>
                                        </div>
                                        <div class="small-muted" style="font-size:12px; margin-top:2px;">
                                            Fresh Catch &bull; <?php echo (int)$item['quantity']; ?> kg &times; ₱<?php echo number_format((float)$item['price_at_purchase'], 2); ?>
                                        </div>
                                    </div>
                                </div>

                                <div style="text-align:right; flex-shrink:0;">
                                    <div style="font-size:14px; font-weight:800; color:var(--text);">
                                        ₱<?php echo number_format($item_subtotal, 2); ?>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>

                <!-- Financial Breakdown -->
                <div style="border-top:2px solid var(--border); padding-top:14px; display:flex; flex-direction:column; gap:8px;">
                    <div style="display:flex; justify-content:space-between; font-size:14px;">
                        <span class="small-muted">Items Subtotal:</span>
                        <strong>₱<?php echo number_format($calculated_subtotal, 2); ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:14px;">
                        <span class="small-muted">Delivery Fee:</span>
                        <strong>₱<?php echo number_format($shipping_fee, 2); ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:18px; font-weight:900; color:var(--primary); margin-top:6px; border-top:1px dashed var(--border); padding-top:8px;">
                        <span>Total Amount:</span>
                        <span>₱<?php echo number_format($total_amount, 2); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Buyer, Fulfillment & Payment Details (1fr) -->
        <div style="display:flex; flex-direction:column; gap:16px;">
            <div class="checkout-step-panel">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; border-bottom:1px solid var(--border); padding-bottom:10px;">
                    <h2>Customer &amp; Fulfillment Details</h2>
                </div>

                <div style="display:flex; flex-direction:column; gap:16px;">
                    <!-- A. Buyer Information -->
                    <div>
                        <div style="font-weight:700; font-size:13px; color:var(--text); margin-bottom:6px;">
                            Buyer Information
                        </div>
                        <div style="background:#f8fafc; border:1px solid var(--border); border-radius:10px; padding:12px 14px; display:flex; flex-direction:column; gap:6px; font-size:13px;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <span class="small-muted">Name:</span>
                                <strong style="color:var(--text);"><?php echo e($buyer_name); ?></strong>
                            </div>
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <span class="small-muted">Contact:</span>
                                <a href="tel:<?php echo e($buyer_phone); ?>" style="color:var(--primary); font-weight:700; text-decoration:none;">
                                    <?php echo e($buyer_phone); ?>
                                </a>
                            </div>
                            <?php if (!empty($buyer_fb)) { ?>
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <span class="small-muted">Facebook:</span>
                                    <?php if (str_starts_with($buyer_fb, 'http://') || str_starts_with($buyer_fb, 'https://')) { ?>
                                        <a href="<?php echo e($buyer_fb); ?>" target="_blank" rel="noopener" style="color:var(--primary); font-weight:700; text-decoration:none; max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                            <?php echo e($buyer_fb); ?>
                                        </a>
                                    <?php } else { ?>
                                        <span style="font-weight:600; color:var(--text);"><?php echo e($buyer_fb); ?></span>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                            <?php if (!empty($buyer_email)) { ?>
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <span class="small-muted">Email:</span>
                                    <span style="font-weight:600; color:var(--text);"><?php echo e($buyer_email); ?></span>
                                </div>
                            <?php } ?>
                        </div>
                    </div>

                    <!-- B. Fulfillment & Delivery Address -->
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                            <div style="font-weight:700; font-size:13px; color:var(--text);">
                                Fulfillment Method
                            </div>
                            <span class="badge" style="font-size:11px; font-weight:700; background:#eff6ff; color:#1e40af;">
                                <?php echo e($order['fulfillment_type'] ?? 'Delivery'); ?>
                            </span>
                        </div>
                        <div style="background:#f8fafc; border:1px solid var(--border); border-radius:10px; padding:12px 14px;">
                            <div style="font-weight:700; font-size:12px; color:var(--muted); text-transform:uppercase; letter-spacing:0.3px; margin-bottom:4px;">
                                Delivery Address
                            </div>
                            <div style="font-size:13.5px; line-height:1.45; color:var(--text); font-weight:600;">
                                <?php echo e($delivery_addr); ?>
                            </div>
                        </div>
                    </div>

                    <!-- C. Payment Information -->
                    <div>
                        <div style="font-weight:700; font-size:13px; color:var(--text); margin-bottom:6px;">
                            Payment Information
                        </div>
                        <div style="background:#f8fafc; border:1px solid var(--border); border-radius:10px; padding:12px 14px; display:flex; flex-direction:column; gap:8px; font-size:13px;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <span class="small-muted">Payment Method:</span>
                                <strong><?php echo e($order['payment_method']); ?></strong>
                            </div>
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <span class="small-muted">Payment Status:</span>
                                <?php
                                    $is_order_cancelled = ($order['order_status'] === 'Cancelled' || ($order['payment_status'] ?? '') === 'Cancelled');
                                    $is_order_paid      = ($order['order_status'] === 'Completed' || ($order['payment_status'] ?? '') === 'Paid') && !$is_order_cancelled;

                                    if ($is_order_cancelled) {
                                        $display_pay_text  = 'Cancelled';
                                        $display_pay_color = '#dc2626';
                                    } elseif ($is_order_paid) {
                                        $display_pay_text  = 'Paid';
                                        $display_pay_color = '#16a34a';
                                    } else {
                                        $display_pay_text  = $order['payment_status'] ?? 'Pending';
                                        $display_pay_color = '#b45309';
                                    }
                                ?>
                                <span style="font-weight:800; color:<?php echo $display_pay_color; ?>;">
                                    <?php echo e($display_pay_text); ?>
                                </span>
                            </div>

                            <?php if (!empty($order['gcash_screenshot_url'])) { ?>
                                <div style="margin-top:6px; padding-top:8px; border-top:1px dashed #e2e8f0; display:flex; align-items:center; justify-content:space-between;">
                                    <span class="small-muted">GCash Receipt:</span>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="openGcashModal('<?php echo BASE_URL . '/' . e($order['gcash_screenshot_url']); ?>')" style="padding:3px 10px; font-size:12px; font-weight:700;">
                                        View Screenshot
                                    </button>
                                </div>
                            <?php } ?>
                        </div>
                    </div>

                    <!-- D. Order Actions & Status Controls -->
                    <div style="border-top:1px solid var(--border); padding-top:14px;">
                        <?php if ($order['order_status'] === 'Cancelled') { ?>
                            <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:10px; padding:12px 14px; color:#991b1b; font-size:13px;">
                                <div style="font-weight:800; font-size:14px; margin-bottom:4px;">Order Cancelled</div>
                                <div>Cancelled by: <strong><?php echo e($order['cancelled_by'] ?? 'Farmer'); ?></strong></div>
                                <?php if (!empty($order['cancellation_reason'])) { ?>
                                    <div style="margin-top:4px;">Reason: <em><?php echo e($order['cancellation_reason']); ?></em></div>
                                <?php } ?>
                                <?php if (!empty($order['cancelled_at'])) { ?>
                                    <div style="font-size:11.5px; opacity:0.8; margin-top:4px;"><?php echo date('M d, Y h:i A', strtotime($order['cancelled_at'])); ?></div>
                                <?php } ?>
                            </div>
                        <?php } elseif ($current_level >= 4) { ?>
                            <div style="background:#d1fae5; border:1px solid #a7f3d0; border-radius:8px; padding:10px 14px; color:#065f46; font-size:13px; font-weight:700; display:flex; align-items:center; gap:8px;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                <span>Order Completed &bull; Status Finalized</span>
                            </div>
                        <?php } else { ?>
                            <form method="POST" action="<?php echo BASE_URL; ?>/farmer/actions/update_order_status.php" style="display:flex; flex-direction:column; gap:10px;">
                                <input type="hidden" name="order_id" value="<?php echo (int)$order['order_id']; ?>">
                                <input type="hidden" name="return" value="/farmer/order_details.php?order_id=<?php echo (int)$order['order_id']; ?>">
                                
                                <label style="font-weight:700; font-size:13px; color:var(--text); margin:0;">
                                    Update Order Status:
                                </label>
                                <div style="display:flex; gap:8px;">
                                    <select name="new_status" style="flex:1; padding:9px 12px; border:1px solid var(--border); border-radius:8px; font-weight:700; font-size:13px; background:var(--surface); cursor:pointer;">
                                        <?php 
                                        foreach ($allowed_statuses as $st_key => $st_name) { 
                                            // Only allow current or forward status levels
                                            if (($status_levels[$st_key] ?? 1) < $current_level) {
                                                continue;
                                            }
                                            $is_sel = ($order['order_status'] === $st_key 
                                                       || ($st_key === 'Ready for fulfillment' && in_array($order['order_status'], ['Ready for fulfillment', 'Ready for Pickup', 'Ready for Delivery', 'Ready for Drop-off'], true))
                                                       || ($st_key === 'Order Placed' && $order['order_status'] === 'Order Placed')
                                                       || ($st_key === 'Completed' && $order['order_status'] === 'Completed')) ? 'selected' : '';
                                        ?>
                                            <option value="<?php echo e($st_key); ?>" <?php echo $is_sel; ?>>
                                                <?php echo e($st_name); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                    <button type="submit" class="btn btn-primary" style="padding:9px 16px; font-weight:800; font-size:13px;">
                                        Save
                                    </button>
                                </div>
                            </form>

                            <?php if ($order['order_status'] === 'Order Placed') { ?>
                                <div style="margin-top:12px; text-align:right;">
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="openFarmerCancelModal(<?php echo (int)$order['order_id']; ?>)" style="padding:7px 16px; font-weight:700; font-size:12.5px;">
                                        Cancel Order
                                    </button>
                                </div>
                            <?php } ?>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- GCASH RECEIPT ZOOM MODAL -->
<div class="modal" id="gcashZoomModal" aria-hidden="true" style="display:none;" onclick="if (event.target === this) closeModal('gcashZoomModal')">
    <div class="modal-backdrop" onclick="closeModal('gcashZoomModal')"></div>
    <div class="modal-content" style="max-width:540px; padding:16px;">
        <div class="modal-header" style="padding-bottom:10px; margin-bottom:10px; border-bottom:1px solid var(--border);">
            <h2 style="font-size:17px; font-weight:800; margin:0; color:var(--text);">GCash Payment Receipt</h2>
            <button type="button" class="modal-close-x" onclick="closeModal('gcashZoomModal')" aria-label="Close">&times;</button>
        </div>
        <div style="text-align:center; max-height:75vh; overflow-y:auto; border-radius:8px;">
            <img id="gcashZoomImg" src="" alt="GCash Screenshot" style="max-width:100%; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.1);">
        </div>
        <div style="display:flex; justify-content:flex-end; margin-top:14px;">
            <button type="button" class="btn btn-secondary" onclick="closeModal('gcashZoomModal')">Close</button>
        </div>
    </div>
</div>

<!-- FARMER CANCEL ORDER MODAL WITH REQUIRED REASON -->
<div class="modal" id="farmerCancelModal" aria-hidden="true" style="display:none;" onclick="if (event.target === this) closeModal('farmerCancelModal')">
    <div class="modal-backdrop" onclick="closeModal('farmerCancelModal')"></div>
    <div class="modal-content" style="max-width:480px;">
        <div class="modal-header">
            <h2 style="font-size:18px; font-weight:800; margin:0; color:#b42318;">Cancel Order Confirmation</h2>
            <button type="button" class="modal-close-x" onclick="closeModal('farmerCancelModal')" aria-label="Close">&times;</button>
        </div>
        <div style="font-size:13px; color:#b42318; background:#fef2f2; border:1px solid #fee2e2; border-radius:8px; padding:8px 12px; margin-top:10px; margin-bottom:10px; font-weight:600;">
            Are you sure you want to cancel this order? This action cannot be undone.
        </div>
        <p style="font-size:13.5px; color:var(--muted); margin:0 0 14px 0;">
            Orders can only be cancelled before confirmation. Please indicate the reason so the buyer understands:
        </p>

        <form method="POST" action="<?php echo BASE_URL; ?>/farmer/actions/cancel_order.php">
            <input type="hidden" name="order_id" id="farmer_cancel_order_id_input" value="<?php echo (int)$order['order_id']; ?>">
            <input type="hidden" name="return" value="/farmer/order_details.php?order_id=<?php echo (int)$order['order_id']; ?>">

            <div class="form-group" style="display:flex; flex-direction:column; gap:8px; font-size:14px; margin-bottom:14px;">
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="radio" name="reason_preset" value="Catch damaged / harvest shortage" checked>
                    Catch damaged / harvest shortage
                </label>
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="radio" name="reason_preset" value="Severe coastal weather / boat delayed">
                    Severe coastal weather / boat delayed
                </label>
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="radio" name="reason_preset" value="Out of stock / overbooked batch">
                    Out of stock / overbooked batch
                </label>
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="radio" name="reason_preset" value="Buyer contact details uncontactable">
                    Buyer contact details uncontactable
                </label>
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="radio" name="reason_preset" value="Other">
                    Other reason
                </label>
            </div>

            <div class="form-group">
                <label style="font-weight:700; font-size:13px;">Additional explanation (optional):</label>
                <textarea name="reason_detail" rows="2" placeholder="Explain details to buyer..." 
                          style="width:100%; padding:8px 12px; border:1px solid var(--border); border-radius:8px; font-size:13px;"></textarea>
            </div>

            <div class="form-actions" style="display:flex; justify-content:flex-end; gap:10px; margin-top:16px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('farmerCancelModal')">Keep Order</button>
                <button type="submit" class="btn btn-danger" style="background:#dc2626; color:#fff; border-color:#dc2626;">Confirm Cancellation</button>
            </div>
        </form>
    </div>
</div>

<script>
function openFarmerCancelModal(orderId) {
    var input = document.getElementById('farmer_cancel_order_id_input');
    if (input) input.value = orderId;
    openModal('farmerCancelModal');
}

function openGcashModal(imgUrl) {
    var img = document.getElementById('gcashZoomImg');
    if (img) img.src = imgUrl;
    openModal('gcashZoomModal');
}
</script>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
