<?php
/**
 * SeaLink Web Application
 * File: /buyer/order_details.php
 * Purpose: Full Order Details page for buyers aligned with checkout.php and cart.php UI.
 * Connected To:
 * - /buyer/orders.php
 * - /buyer/actions/submit_feedback.php
 * - /buyer/actions/cancel_order.php
 * Uses: order_tbl, order_item_tbl, product_tbl, farmer_tbl, buyer_tbl, buyer_address_tbl, feedback_tbl
 */

require_once __DIR__ . '/../includes/auth_check.php';
check_access('buyer');

$hide_nav = true;
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$active_tab = 'orders';
$page_title = "Order Details - SeaLink";
require_once __DIR__ . '/../includes/layout_top.php';

$buyer_id = (int)$_SESSION['user_id'];
$order_id = (int)($_GET['order_id'] ?? 0);
$return_url = trim((string)($_GET['return'] ?? '/buyer/orders.php'));

if ($order_id <= 0) {
    redirect_path('/buyer/orders.php');
}

// Fetch order details with buyer, farmer, and address info
$stmt = mysqli_prepare($conn, "
    SELECT o.*, 
           b.username AS buyer_username, b.full_name AS buyer_fullname, b.contact_number AS buyer_contact,
           f.username AS farmer_name, f.full_name AS farmer_fullname, f.contact_number AS farmer_contact, f.address AS farmer_address,
           addr.street, addr.municipality, addr.province, addr.zip_code, addr.is_default
    FROM order_tbl o 
    JOIN buyer_tbl b ON b.buyer_id = o.buyer_id
    JOIN farmer_tbl f ON f.farmer_id = o.farmer_id 
    LEFT JOIN buyer_address_tbl addr ON addr.address_id = o.address_id
    WHERE o.order_id = ? AND o.buyer_id = ?
    LIMIT 1
");
mysqli_stmt_bind_param($stmt, "ii", $order_id, $buyer_id);
mysqli_stmt_execute($stmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$order) {
    mysqli_close($conn);
    redirect_path('/buyer/orders.php');
}

// Auto-sync: If order status is Completed, ensure Payment Status is Paid
if ($order['order_status'] === 'Completed' && ($order['payment_status'] ?? '') !== 'Paid') {
    mysqli_query($conn, "UPDATE order_tbl SET payment_status = 'Paid' WHERE order_id = " . (int)$order_id);
    $order['payment_status'] = 'Paid';
}

// Auto-sync: If order status is Cancelled, ensure Payment Status is Cancelled
if ($order['order_status'] === 'Cancelled' && ($order['payment_status'] ?? '') !== 'Cancelled') {
    mysqli_query($conn, "UPDATE order_tbl SET payment_status = 'Cancelled' WHERE order_id = " . (int)$order_id);
    $order['payment_status'] = 'Cancelled';
}

// Fetch order items with review details
$items = [];
$stmt = mysqli_prepare($conn, "
    SELECT oi.order_item_id, oi.product_id, oi.quantity, oi.price_at_purchase, p.name, p.image_url,
           fb.feedback_id, fb.rating, fb.comment
    FROM order_item_tbl oi 
    JOIN product_tbl p ON p.product_id = oi.product_id 
    LEFT JOIN feedback_tbl fb ON fb.product_id = oi.product_id AND fb.buyer_id = ?
    WHERE oi.order_id = ?
");
mysqli_stmt_bind_param($stmt, "ii", $buyer_id, $order_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $items[] = $row;
}
mysqli_stmt_close($stmt);
mysqli_close($conn);

// Delivery and recipient info
$delivery_addr = trim(($order['street'] ?? '') . ', ' . ($order['municipality'] ?? '') . ', ' . ($order['province'] ?? '') . ' ' . ($order['zip_code'] ?? ''));
if (empty($delivery_addr)) {
    $delivery_addr = 'Santa Fe, Romblon';
}
$recipient_name = !empty($order['buyer_fullname']) ? $order['buyer_fullname'] : ($order['buyer_username'] ?? 'Valued Buyer');
$recipient_contact = !empty($order['buyer_contact']) ? $order['buyer_contact'] : 'No contact number provided';

// Status styling & message
$status_text = $order['order_status'];
$badge_bg = '#fef3c7';
$badge_color = '#b45309';
$status_msg = "Your order has been placed. Waiting for seller confirmation.";
$status_icon = "";

if ($status_text === 'Confirmed') {
    $badge_bg = '#e0f2fe';
    $badge_color = '#0369a1';
    $status_msg = "Seller confirmed your order. Catch is being prepared & packaged.";
    $status_icon = "";
} elseif ($status_text === 'Ready for Delivery') {
    $badge_bg = '#dcfce7';
    $badge_color = '#15803d';
    $status_msg = "Parcel is out for delivery with local Santa Fe dispatch.";
    $status_icon = "";
} elseif ($status_text === 'Ready for Pickup') {
    $badge_bg = '#e0e7ff';
    $badge_color = '#3730a3';
    $status_msg = "Ready for collection at the seller's designated selling port / dock.";
    $status_icon = "";
} elseif ($status_text === 'Ready for Drop-off' || $status_text === 'Ready for fulfillment') {
    $badge_bg = '#dcfce7';
    $badge_color = '#15803d';
    $status_msg = "Catch is on the way to the designated community drop-off point.";
    $status_icon = "";
} elseif ($status_text === 'Completed') {
    $badge_bg = '#d1fae5';
    $badge_color = '#065f46';
    $status_msg = "Order successfully delivered & completed. Thank you for supporting local Santa Fe fisherfolk!";
    $status_icon = "";
} elseif ($status_text === 'Cancelled') {
    $badge_bg = '#fee2e2';
    $badge_color = '#991b1b';
    $cancelled_by_lower = strtolower(trim((string)($order['cancelled_by'] ?? '')));
    if ($cancelled_by_lower === 'buyer') {
        $cancel_title = "You cancelled this order.";
    } elseif ($cancelled_by_lower === 'farmer' || $cancelled_by_lower === 'seller') {
        $cancel_title = "Seller cancelled the order.";
    } else {
        $cancel_title = "This order was cancelled.";
    }
    $status_msg = $cancel_title . (!empty($order['cancellation_reason']) ? " Reason: " . e($order['cancellation_reason']) : "");
    $status_icon = "❌";
}

// Financial calculations
$calculated_subtotal = 0;
foreach ($items as $it) {
    $calculated_subtotal += ((float)$it['price_at_purchase'] * (int)$it['quantity']);
}
$shipping_fee = (float)($order['shipping_fee'] ?? 50.00);
$platform_fee = (float)($order['platform_fee'] ?? round($calculated_subtotal * 0.02, 2));
$total_amount = (float)($order['total_amount'] ?? ($calculated_subtotal + $shipping_fee + $platform_fee));
?>

<main class="dashboard-content buyer-dashboard">
    <!-- Header with Back Arrow aligned with checkout.php -->
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
        <div style="display:flex; align-items:center; gap:12px;">
            <a class="back-arrow" href="<?php echo BASE_URL . e($return_url); ?>" onclick="if (document.referrer && document.referrer.indexOf(window.location.host) !== -1) { history.back(); return false; }" aria-label="Back">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="currentColor" d="M15.5 19 8.5 12l7-7 1.5 1.5L11.5 12l5.5 5.5z"/>
                </svg>
            </a>
            <div>
                <h1 style="margin:0; font-size:24px;">Order Details</h1>
                <div class="small-muted" style="margin-top:2px;">View purchase breakdown, status, and tracking information</div>
            </div>
        </div>
        <div>
            <span style="font-size:12px; font-weight:800; background:<?php echo $badge_bg; ?>; color:<?php echo $badge_color; ?>; padding:5px 14px; border-radius:999px; text-transform:uppercase; letter-spacing:0.3px;">
                <?php echo e($order['order_status']); ?>
            </span>
        </div>
    </div>

    <?php
    $is_cancelled = ($order['order_status'] === 'Cancelled');
    $is_completed = ($order['order_status'] === 'Completed');
    $is_out_for_delivery = in_array($order['order_status'], ['Ready for Delivery', 'Ready for fulfillment', 'Ready for Drop-off', 'Ready for Pickup', 'Completed'], true);
    ?>
    <!-- Horizontal Order Timeline Tracker: [Placed] ----- [Out for Delivery] ----- [Completed] -->
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

            <!-- Step 2: Out for Delivery -->
            <div style="display:flex; flex-direction:column; align-items:center; z-index:2; position:relative;">
                <div style="width:34px; height:34px; border-radius:50%; background:<?php echo $is_out_for_delivery ? 'var(--primary)' : '#f1f5f9'; ?>; color:<?php echo $is_out_for_delivery ? '#fff' : 'var(--muted)'; ?>; border:<?php echo $is_out_for_delivery ? 'none' : '2px solid #cbd5e1'; ?>; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:13px;">
                    <?php echo ($is_completed ? '✓' : ''); ?>
                </div>
                <span style="font-size:12px; font-weight:800; color:<?php echo $is_out_for_delivery ? 'var(--text)' : 'var(--muted)'; ?>; margin-top:6px;">Out for Delivery</span>
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
                    <span style="font-size:12px; font-weight:800; color:<?php echo $is_completed ? 'var(--text)' : 'var(--muted)'; ?>; margin-top:6px;">Completed</span>
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
        @media (max-width: 480px) {
            .checkout-step-panel {
                padding: 14px 12px !important;
            }
            .checkout-step-panel h2 {
                font-size: 15.5px !important;
            }
            .seller-summary-box {
                padding: 10px 12px !important;
            }
        }
    </style>

    <div class="order-details-grid">
        <!-- Left Column: Purchased Items Summary (1.3fr) -->
        <div>
            <div class="checkout-step-panel">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; border-bottom:1px solid var(--border); padding-bottom:10px;">
                    <h2><span></span> Order Summary</h2>
                    <span class="badge" style="font-size:12px; font-weight:700;">
                        <?php echo count($items); ?> item<?php echo count($items) !== 1 ? 's' : ''; ?>
                    </span>
                </div>

                <!-- Seller Info Box & Products -->
                <div class="seller-summary-box">
                    <!-- Seller Header -->
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; border-bottom:1px dashed var(--border); padding-bottom:8px; flex-wrap:wrap; gap:8px;">
                        <div style="font-weight:800; font-size:14.5px; color:var(--text); display:flex; align-items:center; gap:6px;">
                            <a href="<?php echo BASE_URL; ?>/farmer_store.php?farmer_id=<?php echo (int)$order['farmer_id']; ?>" style="color:inherit; text-decoration:none;" onmouseover="this.style.color='var(--primary)';" onmouseout="this.style.color='inherit';">
                                <?php echo e($order['farmer_name']); ?>
                            </a>
                            <span class="small-muted" style="font-size:12px; font-weight:normal;">(<?php echo e(!empty(trim($order['farmer_address'] ?? '')) ? $order['farmer_address'] : 'Santa Fe, Romblon'); ?>)</span>
                        </div>
                        <a href="<?php echo BASE_URL; ?>/farmer_store.php?farmer_id=<?php echo (int)$order['farmer_id']; ?>" class="btn-link" style="font-size:12px; font-weight:700;">
                            Visit Store
                        </a>
                    </div>

                    <!-- Products List -->
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <?php foreach ($items as $item) { 
                            $item_subtotal = (float)$item['price_at_purchase'] * (int)$item['quantity'];
                        ?>
                            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; padding-bottom:10px; border-bottom:1px dashed #e2e8f0;">
                                <div style="display:flex; align-items:center; gap:12px; min-width:0; flex:1;">
                                    <!-- Image Thumbnail -->
                                    <div style="width:52px; height:52px; border-radius:10px; overflow:hidden; background:#f1f5f9; border:1px solid #e2e8f0; flex-shrink:0;">
                                        <?php if (!empty($item['image_url'])) { ?>
                                            <img src="<?php echo BASE_URL . '/' . e($item['image_url']); ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                                        <?php } else { ?>
                                            <div style="display:flex; align-items:center; justify-content:center; height:100%; font-size:18px;"></div>
                                        <?php } ?>
                                    </div>

                                    <div style="min-width:0; flex:1;">
                                        <a href="<?php echo BASE_URL; ?>/product_detail.php?product_id=<?php echo (int)$item['product_id']; ?>" style="font-size:13.5px; font-weight:700; color:var(--text); text-decoration:none; display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                            <?php echo e($item['name']); ?>
                                        </a>
                                        <div class="small-muted" style="font-size:12px; margin-top:2px;">
                                            Fresh Catch &bull; <?php echo (int)$item['quantity']; ?> kg &times; ₱<?php echo number_format((float)$item['price_at_purchase'], 2); ?>
                                        </div>
                                    </div>
                                </div>

                                <div style="display:flex; flex-direction:column; align-items:flex-end; gap:6px; flex-shrink:0;">
                                    <div style="font-size:14px; font-weight:800; color:var(--text);">
                                        ₱<?php echo number_format($item_subtotal, 2); ?>
                                    </div>
                                    <?php if ($order['order_status'] === 'Completed') { ?>
                                        <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap; justify-content:flex-end;">
                                            <?php if (!empty($item['feedback_id'])) { ?>
                                                <span class="small-muted" style="display:inline-flex; align-items:center; gap:3px; font-weight:700; font-size:11px; background:#fef3c7; color:#b45309; padding:2px 8px; border-radius:6px;">
                                                    <?php echo (int)$item['rating']; ?>/5 Reviewed
                                                </span>
                                            <?php } else { ?>
                                                <button type="button" class="btn btn-sm btn-primary" style="padding:3px 10px; font-size:11.5px; font-weight:700;"
                                                        onclick="openFeedbackModal(<?php echo (int)$item['product_id']; ?>, '<?php echo e(addslashes($item['name'])); ?>', <?php echo (int)$order['farmer_id']; ?>)">
                                                    Review
                                                </button>
                                            <?php } ?>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>

                <!-- Financial Breakdown matching checkout.php -->
                <div style="border-top:2px solid var(--border); padding-top:14px; display:flex; flex-direction:column; gap:8px;">
                    <div style="display:flex; justify-content:space-between; font-size:14px;">
                        <span class="small-muted">Items Subtotal:</span>
                        <strong>₱<?php echo number_format($calculated_subtotal, 2); ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:14px;">
                        <span class="small-muted">Delivery Fee:</span>
                        <strong>₱<?php echo number_format($shipping_fee, 2); ?></strong>
                    </div>
                    <?php if ($platform_fee > 0) { ?>
                        <div style="display:flex; justify-content:space-between; font-size:13px; color:#92400e;">
                            <span style="display:flex; align-items:center; gap:4px;">
                                <span>Platform Sustainability Fee</span>
                                <span class="small-muted" style="font-size:11px;">(2%)</span>
                            </span>
                            <strong>₱<?php echo number_format($platform_fee, 2); ?></strong>
                        </div>
                    <?php } ?>
                    <div style="display:flex; justify-content:space-between; font-size:18px; font-weight:900; color:var(--primary); margin-top:6px; border-top:1px dashed var(--border); padding-top:8px;">
                        <span>Overall Total:</span>
                        <span>₱<?php echo number_format($total_amount, 2); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Delivery, Fulfillment, Payment & Actions (1fr) -->
        <div style="display:flex; flex-direction:column; gap:16px;">
            
            <!-- 1. Summarized Delivery, Fulfillment & Payment Container -->
            <div class="checkout-step-panel">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; border-bottom:1px solid var(--border); padding-bottom:10px;">
                    <h2><span></span> Delivery &amp; Payment Details</h2>
                </div>

                <div style="display:flex; flex-direction:column; gap:16px;">
                    <!-- A. Delivery Address -->
                    <div>
                        <div style="font-weight:700; font-size:13px; color:var(--text); margin-bottom:6px; display:flex; align-items:center; gap:6px;">
                            <span></span> Delivery Address
                        </div>
                        <div style="background:#f8fafc; border:1px solid var(--border); border-radius:10px; padding:12px 14px;">
                            <div style="font-weight:800; font-size:14px; color:var(--text);">
                                <?php echo e($recipient_name); ?>
                            </div>
                            <div style="font-size:12.5px; color:var(--muted); margin-top:2px;">
                                <?php echo e($recipient_contact); ?>
                            </div>
                            <div style="font-size:13px; line-height:1.4; color:var(--text); padding-top:8px; margin-top:8px; border-top:1px dashed #e2e8f0;">
                                <?php echo e($delivery_addr); ?>
                            </div>
                        </div>
                    </div>

                    <!-- B. Fulfillment Method -->
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                            <div style="font-weight:700; font-size:13px; color:var(--text); display:flex; align-items:center; gap:6px;">
                                <span></span> Fulfillment Method
                            </div>
                            <span class="badge" style="font-size:11px; font-weight:700; background:#eff6ff; color:#1e40af;">
                                <?php echo e($order['fulfillment_type'] ?? 'Delivery'); ?>
                            </span>
                        </div>
                        <?php 
                            $ftype = $order['fulfillment_type'] ?? 'Delivery';
                            if ($ftype === 'Pickup') {
                        ?>
                            <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; padding:10px 12px; font-size:12.5px; color:#1e293b;">
                                <div style="font-weight:700; color:#1e40af; margin-bottom:2px; display:flex; align-items:center; gap:6px;">
                                    <span></span> Seller Pickup Location:
                                </div>
                                <div><?php echo !empty(trim($order['farmer_address'] ?? '')) ? e($order['farmer_address']) : 'Santa Fe Municipal Fish Port / Local Dock'; ?></div>
                                <div class="small-muted" style="margin-top:4px; font-size:11px;">
                                    Order will be collected directly from the seller's designated dock or farm location.
                                </div>
                            </div>
                        <?php } elseif ($ftype === 'Drop-off') { ?>
                            <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:10px 12px; font-size:12.5px; color:#14532d;">
                                <div style="font-weight:700; color:#166534; margin-bottom:2px; display:flex; align-items:center; gap:6px;">
                                    <span></span> Community Drop-off Point:
                                </div>
                                <div>Designated Santa Fe community pickup hub</div>
                                <div class="small-muted" style="margin-top:4px; font-size:11px;">
                                    The seller will dispatch your catch to the designated Santa Fe drop-off point.
                                </div>
                            </div>
                        <?php } else { ?>
                            <div style="background:#f8fafc; border:1px solid var(--border); border-radius:10px; padding:10px 12px; font-size:12.5px; color:var(--text);">
                                <div style="font-weight:700; color:var(--text); margin-bottom:2px;">Direct Door-to-Door Delivery</div>
                                <div class="small-muted" style="font-size:11.5px;">
                                    Delivery dispatch will deliver fresh catch straight to your specified address.
                                </div>
                            </div>
                        <?php } ?>
                    </div>

                    <!-- C. Payment Details -->
                    <div>
                        <div style="font-weight:700; font-size:13px; color:var(--text); display:flex; align-items:center; gap:6px; margin-bottom:6px;">
                            <span></span> Payment Information
                        </div>
                        <div style="background:#f8fafc; border:1px solid var(--border); border-radius:10px; padding:10px 12px; font-size:13px; display:flex; flex-direction:column; gap:6px;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <span class="small-muted">Payment Method:</span>
                                <strong><?php echo e($order['payment_method']); ?></strong>
                            </div>
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <span class="small-muted">Payment Status:</span>
                                <?php
                                    $is_order_cancelled = ($order['order_status'] === 'Cancelled' || ($order['payment_status'] ?? '') === 'Cancelled');
                                    $is_order_paid = ($order['order_status'] === 'Completed' || ($order['payment_status'] ?? '') === 'Paid') && !$is_order_cancelled;

                                    if ($is_order_cancelled) {
                                        $display_pay_text = 'Cancelled';
                                        $display_pay_color = '#dc2626'; // red
                                    } elseif ($is_order_paid) {
                                        $display_pay_text = 'Paid';
                                        $display_pay_color = '#16a34a'; // green
                                    } else {
                                        $display_pay_text = $order['payment_status'] ?? 'Pending';
                                        $display_pay_color = '#b45309'; // amber
                                    }
                                ?>
                                <strong style="color:<?php echo $display_pay_color; ?>; font-weight:700;">
                                    <?php echo e($display_pay_text); ?>
                                </strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <?php if ($order['order_status'] === 'Order Placed' || in_array($order['order_status'], ['Completed', 'Cancelled'], true)) { ?>
        <!-- Bottom Full-Width Action Buttons Card -->
        <div class="checkout-step-panel" style="margin-top:20px; display:flex; align-items:center; justify-content:flex-end; flex-wrap:wrap; gap:12px;">
            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; justify-content:flex-end; width:100%;">
                <?php if ($order['order_status'] === 'Order Placed') { ?>
                    <button type="button" class="btn btn-outline-danger" onclick="openBuyerCancelModal(<?php echo (int)$order['order_id']; ?>)" style="font-weight:700; font-size:13.5px; padding:10px 24px;">
                        Cancel Order
                    </button>
                <?php } ?>

                <?php if (in_array($order['order_status'], ['Completed', 'Cancelled'], true)) { 
                    $first_pid = !empty($items) ? (int)$items[0]['product_id'] : 0;
                    $unreviewed = ($order['order_status'] === 'Completed') ? array_values(array_filter($items, function($it) { return empty($it['feedback_id']); })) : [];
                    if (!empty($unreviewed)) {
                        $first_un = $unreviewed[0];
                ?>
                    <button type="button" class="btn btn-primary" style="font-weight:800; font-size:13.5px; padding:10px 24px;"
                            onclick="openFeedbackModal(<?php echo (int)$first_un['product_id']; ?>, '<?php echo e(addslashes($first_un['name'])); ?>', <?php echo (int)$order['farmer_id']; ?>)">
                        Rate Items
                    </button>
                <?php } 
                    $buy_again_url = BASE_URL . '/buyer/checkout.php?mode=reorder&order_id=' . (int)$order['order_id'];
                ?>
                    <a href="<?php echo $buy_again_url; ?>" class="<?php echo !empty($unreviewed) ? 'btn btn-outline-primary' : 'btn btn-primary'; ?>" style="font-weight:800; font-size:13.5px; text-decoration:none; padding:10px 24px;">
                        Buy Again
                    </a>
                <?php } ?>
            </div>
        </div>
    <?php } ?>
</main>

<!-- Leave Review Modal -->
<div class="modal" id="feedbackModal" aria-hidden="true">
    <div class="modal-backdrop" onclick="closeModal('feedbackModal')"></div>
    <div class="modal-content" style="max-width:480px;">
        <div class="modal-header">
            <h2 style="font-size:18px; font-weight:800; margin:0;">Leave Product Review</h2>
            <button type="button" class="modal-close-x" onclick="closeModal('feedbackModal')" aria-label="Close">&times;</button>
        </div>
        <form method="POST" action="<?php echo BASE_URL; ?>/buyer/actions/submit_feedback.php">
            <input type="hidden" name="product_id" id="feedback_product_id" required>
            <input type="hidden" name="farmer_id" id="feedback_farmer_id" required>
            <input type="hidden" name="order_id" value="<?php echo (int)$order['order_id']; ?>">
            <input type="hidden" name="return" value="/buyer/order_details.php?order_id=<?php echo (int)$order['order_id']; ?>">

            <div style="margin:12px 0; background:#f8fafc; padding:10px 12px; border-radius:8px; border:1px solid #e2e8f0;">
                <span class="small-muted" style="font-size:12px;">Product:</span>
                <strong id="feedback_product_name" style="display:block; font-size:14px; margin-top:2px;"></strong>
            </div>

            <div class="form-group">
                <label style="font-weight:700; font-size:13px; margin-bottom:6px; display:block;">Rating (1 to 5 Stars) *</label>
                <select name="rating" required style="width:100%; padding:8px 12px; border:1px solid var(--border); border-radius:8px; font-size:13.5px;">
                    <option value="5">⭐⭐⭐⭐⭐ 5 Stars - Excellent Quality</option>
                    <option value="4">⭐⭐⭐⭐ 4 Stars - Very Good</option>
                    <option value="3">⭐⭐⭐ 3 Stars - Average</option>
                    <option value="2">⭐⭐ 2 Stars - Fair</option>
                    <option value="1">⭐ 1 Star - Poor</option>
                </select>
            </div>

            <div class="form-group" style="margin-top:12px;">
                <label style="font-weight:700; font-size:13px; margin-bottom:6px; display:block;">Your Comment / Feedback *</label>
                <textarea name="comment" rows="4" required placeholder="Share your experience regarding freshness, packaging, and delivery..." style="width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; font-size:13px;"></textarea>
            </div>

            <div class="form-actions" style="display:flex; justify-content:flex-end; gap:10px; margin-top:16px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('feedbackModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Review</button>
            </div>
        </form>
    </div>
</div>

<!-- Cancel Order Confirmation Modal -->
<div class="modal" id="buyerCancelModal" aria-hidden="true" onclick="if (event.target === this) closeModal('buyerCancelModal')">
    <div class="modal-backdrop" onclick="closeModal('buyerCancelModal')"></div>
    <div class="modal-content" style="max-width:480px;">
        <div class="modal-header">
            <h2 style="font-size:18px; font-weight:800; margin:0; color:#b42318;">Cancel Order no. <?php echo (int)$order['order_id']; ?></h2>
            <button type="button" class="modal-close-x" onclick="closeModal('buyerCancelModal')" aria-label="Close">&times;</button>
        </div>
        <div style="font-size:13px; color:#b42318; background:#fef2f2; border:1px solid #fee2e2; border-radius:8px; padding:8px 12px; margin-top:10px; margin-bottom:10px; font-weight:600;">
            Are you sure you want to cancel this order? This action cannot be undone.
        </div>
        <p style="font-size:13.5px; color:var(--muted); margin:0 0 14px 0;">
            You can cancel before the farmer confirms your order. Please let the farmer understand why you are cancelling:
        </p>

        <form method="POST" action="<?php echo BASE_URL; ?>/buyer/actions/cancel_order.php">
            <input type="hidden" name="order_id" id="cancel_order_id_input" value="<?php echo (int)$order['order_id']; ?>">
            <input type="hidden" name="return" value="/buyer/order_details.php?order_id=<?php echo (int)$order['order_id']; ?>">

            <div class="form-group" style="display:flex; flex-direction:column; gap:8px; font-size:14px; margin-bottom:14px;">
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="radio" name="reason_preset" value="Accidental order / duplicate submission" checked>
                    Accidental order / duplicate submission
                </label>
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="radio" name="reason_preset" value="Need to change delivery location or schedule">
                    Need to change delivery location or schedule
                </label>
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="radio" name="reason_preset" value="Found alternative seller in Santa Fe">
                    Found alternative seller in Santa Fe
                </label>
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="radio" name="reason_preset" value="Contacted farmer and mutually agreed to cancel">
                    Contacted farmer and mutually agreed to cancel
                </label>
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="radio" name="reason_preset" value="Other">
                    Other reason
                </label>
            </div>

            <div class="form-group">
                <label style="font-weight:700; font-size:13px; margin-bottom:6px; display:block;">Additional explanation (optional):</label>
                <textarea name="reason_detail" rows="2" placeholder="Provide any details to help the farmer..." 
                          style="width:100%; padding:8px 12px; border:1px solid var(--border); border-radius:8px; font-size:13px;"></textarea>
            </div>

            <div class="form-actions" style="display:flex; justify-content:flex-end; gap:10px; margin-top:16px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('buyerCancelModal')">Keep Order</button>
                <button type="submit" class="btn btn-danger" style="background:#dc2626; color:#fff; border-color:#dc2626;">Confirm Cancellation</button>
            </div>
        </form>
    </div>
</div>

<script>
function openFeedbackModal(productId, productName, farmerId) {
    document.getElementById('feedback_product_id').value = productId;
    document.getElementById('feedback_product_name').textContent = productName;
    document.getElementById('feedback_farmer_id').value = farmerId;
    openModal('feedbackModal');
}

function openBuyerCancelModal(orderId) {
    var input = document.getElementById('cancel_order_id_input');
    if (input) input.value = orderId;
    openModal('buyerCancelModal');
}
</script>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
