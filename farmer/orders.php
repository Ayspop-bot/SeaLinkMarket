<?php
/**
 * SeaLink Web Application
 * File: /farmer/orders.php
 * Purpose: Farmer Order Management with product images in item rows, cost summary bar, and unified modern card UI matching buyer/orders.php.
 * Connected To:
 * - /farmer/actions/update_order_status.php
 * - /farmer/actions/cancel_order.php
 * Uses: order_tbl, order_item_tbl, buyer_tbl, product_tbl, buyer_address_tbl
 */

require_once __DIR__ . '/../includes/auth_check.php';
check_access('farmer');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$active_tab = 'orders';
$page_title = "Order Management - SeaLink";
require_once __DIR__ . '/../includes/layout_top.php';

$farmer_id     = (int)$_SESSION['user_id'];
$status_filter = trim((string)($_GET['status'] ?? 'All'));

$where  = "o.farmer_id = ?";
$params = [$farmer_id];
$types  = "i";

if ($status_filter === 'Pending') {
    $where .= " AND o.order_status = 'Order Placed'";
} elseif ($status_filter === 'Processing') {
    $where .= " AND o.order_status = 'Confirmed'";
} elseif ($status_filter === 'Ready') {
    $where .= " AND o.order_status IN ('Ready for Pickup', 'Ready for Delivery', 'Ready for Drop-off', 'Ready for fulfillment')";
} elseif ($status_filter === 'Delivered' || $status_filter === 'Completed') {
    $where .= " AND o.order_status = 'Completed'";
} elseif ($status_filter === 'Cancelled') {
    $where .= " AND o.order_status = 'Cancelled'";
}

$sql = "
SELECT 
    o.order_id, o.order_date, o.order_status, o.total_amount, o.shipping_fee,
    o.payment_method, o.payment_status, o.gcash_screenshot_url, o.fulfillment_type,
    o.cancellation_reason, o.cancelled_by, o.cancelled_at,
    b.buyer_id, b.full_name AS buyer_name, b.username AS buyer_username,
    b.contact_number AS buyer_contact, b.facebook_account AS buyer_facebook,
    addr.street, addr.municipality, addr.province, addr.zip_code
FROM order_tbl o
JOIN buyer_tbl b ON b.buyer_id = o.buyer_id
LEFT JOIN buyer_address_tbl addr ON addr.address_id = o.address_id
WHERE {$where}
ORDER BY o.order_date DESC
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$orders = [];
while ($row = mysqli_fetch_assoc($res)) {
    $oid = (int)$row['order_id'];
    
    $item_stmt = mysqli_prepare($conn, "
        SELECT oi.quantity, oi.price_at_purchase, p.product_id, p.name AS product_name, p.image_url
        FROM order_item_tbl oi
        JOIN product_tbl p ON p.product_id = oi.product_id
        WHERE oi.order_id = ?
    ");
    mysqli_stmt_bind_param($item_stmt, "i", $oid);
    mysqli_stmt_execute($item_stmt);
    $item_res = mysqli_stmt_get_result($item_stmt);
    $items = [];
    while ($i_row = mysqli_fetch_assoc($item_res)) {
        $items[] = $i_row;
    }
    mysqli_stmt_close($item_stmt);

    $row['items'] = $items;
    $orders[] = $row;
}
mysqli_stmt_close($stmt);

$tot_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM order_tbl WHERE farmer_id = ?");
mysqli_stmt_bind_param($tot_stmt, "i", $farmer_id);
mysqli_stmt_execute($tot_stmt);
$tot_res = mysqli_stmt_get_result($tot_stmt);
$tot_row = mysqli_fetch_assoc($tot_res);
$total_orders_count = (int)($tot_row['total'] ?? count($orders));
mysqli_stmt_close($tot_stmt);

mysqli_close($conn);

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
?>

<main class="dashboard-content farmer-dashboard">
    <!-- Header Inside Container -->
    <div class="section-card" style="margin-bottom:16px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div>
                <h1 style="margin:0; font-size:24px;">Order Management</h1>
                <div class="small-muted" style="margin-top:4px;">Manage incoming buyer purchases, prepare packages, and update fulfillment status</div>
            </div>
            <div class="small-muted"><?php echo $total_orders_count; ?> order(s) received</div>
        </div>
    </div>

    <!-- Filter Subtabs -->
    <div class="subtabs" style="margin-top:14px; margin-bottom:14px;">
        <?php
        $filters = [
            'All'        => 'All Orders',
            'Pending'    => 'Pending',
            'Processing' => 'Processing',
            'Ready'      => 'Ready for fulfillment',
            'Delivered'  => 'Delivered',
            'Cancelled'  => 'Cancelled'
        ];
        foreach ($filters as $val => $label) {
            $cls = ($status_filter === $val) ? 'active' : '';
            echo "<a class=\"subtab {$cls}\" href=\"?status=" . urlencode($val) . "\">{$label}</a>";
        }
        ?>
    </div>

    <!-- Orders List (Matching buyer/orders.php layout) -->
    <div style="display:flex; flex-direction:column; gap:14px;">
        <?php if (empty($orders)) { ?>
            <div class="section-card">
                <div class="small-muted" style="text-align:center; padding:28px 0;">No orders found for <strong><?php echo e($status_filter); ?></strong>.</div>
            </div>
        <?php } ?>

        <?php foreach ($orders as $o) { 
            $status_text = $o['order_status'];
            $badge_bg = '#fef3c7';
            $badge_color = '#b45309';

            if ($status_text === 'Confirmed') {
                $badge_bg = '#e0f2fe';
                $badge_color = '#0369a1';
            } elseif (in_array($status_text, ['Ready for Delivery', 'Ready for Pickup', 'Ready for Drop-off', 'Ready for fulfillment'], true)) {
                $badge_bg = '#dcfce7';
                $badge_color = '#15803d';
            } elseif ($status_text === 'Completed') {
                $badge_bg = '#d1fae5';
                $badge_color = '#065f46';
            } elseif ($status_text === 'Cancelled') {
                $badge_bg = '#fee2e2';
                $badge_color = '#991b1b';
            }
        ?>
            <div class="section-card" style="margin-top:0; background:#fff; border:1px solid var(--border); border-radius:14px; padding:20px; box-shadow:0 2px 8px rgba(0,0,0,0.02);">
                <!-- 1. Header Bar (Order no., Buyer Name + Chat, Placed On + Status Badge) -->
                <div style="display:flex; justify-content:space-between; align-items:flex-start; border-bottom:1px solid var(--border); padding-bottom:12px; margin-bottom:12px; gap:12px;">
                    <div style="display:flex; flex-direction:column; gap:4px; min-width:0;">
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                            <a href="<?php echo BASE_URL; ?>/farmer/order_details.php?order_id=<?php echo (int)$o['order_id']; ?>" style="font-weight:900; font-size:16px; color:var(--primary); text-decoration:none;" onmouseover="this.style.textDecoration='underline';" onmouseout="this.style.textDecoration='none';">
                                Order no. <?php echo (int)$o['order_id']; ?>
                            </a>
                            <span style="color:var(--border);">|</span>
                            <span style="font-weight:800; font-size:15px; color:var(--text);"><?php echo e($o['buyer_name']); ?></span>
                            <span class="small-muted" style="font-size:13px;">(@<?php echo e($o['buyer_username']); ?>)</span>
                            <a href="<?php echo BASE_URL; ?>/farmer/messages.php?buyer_id=<?php echo (int)$o['buyer_id']; ?>" title="Chat with buyer" aria-label="Chat with buyer" style="color:var(--primary); font-size:13px; text-decoration:none; display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:6px; background:#eff6ff; font-weight:700;" onmouseover="this.style.background='#dbeafe';" onmouseout="this.style.background='#eff6ff';">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                                <span>Chat</span>
                            </a>
                        </div>
                        <div class="small-muted" style="font-size:12px;">
                            Placed on <?php echo date('M d, Y h:i A', strtotime($o['order_date'])); ?>
                        </div>
                    </div>

                    <div style="flex-shrink:0; padding-top:2px;">
                        <span style="font-size:11px; font-weight:800; background:<?php echo $badge_bg; ?>; color:<?php echo $badge_color; ?>; padding:4px 10px; border-radius:999px; text-transform:uppercase; letter-spacing:0.3px;">
                            <?php echo e($status_text); ?>
                        </span>
                    </div>
                </div>

                <!-- Items List (Click product to view full Order Details) -->
                <div style="display:flex; flex-direction:column; gap:10px; margin-bottom:14px;">
                    <?php foreach ($o['items'] as $item) { 
                        $item_subtotal = (float)$item['price_at_purchase'] * (int)$item['quantity'];
                    ?>
                        <div style="display:flex; align-items:center; justify-content:space-between; gap:14px; padding:8px 0; border-bottom:1px dashed #e2e8f0;">
                            <a href="<?php echo BASE_URL; ?>/farmer/order_details.php?order_id=<?php echo (int)$o['order_id']; ?>" style="display:flex; align-items:center; gap:12px; min-width:0; flex:1; text-decoration:none; color:inherit;" title="Click to view order details">
                                <div style="width:60px; height:60px; border-radius:10px; overflow:hidden; background:#f8fafc; border:1px solid #e2e8f0; flex-shrink:0;">
                                    <?php if (!empty($item['image_url'])) { ?>
                                        <img src="<?php echo BASE_URL . '/' . e($item['image_url']); ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                                    <?php } else { ?>
                                        <div style="display:flex; align-items:center; justify-content:center; height:100%; font-size:20px;">🐟</div>
                                    <?php } ?>
                                </div>
                                <div style="min-width:0; flex:1;">
                                    <div style="font-size:14.5px; font-weight:800; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                        <?php echo e($item['product_name']); ?>
                                    </div>
                                    <div class="small-muted" style="font-size:12px; margin-top:3px;">
                                        Fresh Catch &bull; <?php echo (int)$item['quantity']; ?> kg &times; ₱<?php echo number_format((float)$item['price_at_purchase'], 2); ?>
                                    </div>
                                </div>
                            </a>
                            <div style="text-align:right; flex-shrink:0;">
                                <div style="font-size:14px; font-weight:800; color:var(--text);">
                                    ₱<?php echo number_format($item_subtotal, 2); ?>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>

                <!-- 5. Cost Summary Bar (Consistent Font Sizing across Delivery Fee, Payment, and Order Total) -->
                <div class="order-summary-row" style="background:#f8fafc; border-radius:12px; padding:12px 16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; margin-bottom:14px; border:1px solid var(--border);">
                    <div style="display:flex; align-items:center; gap:20px; flex-wrap:wrap;">
                        <div style="display:flex; align-items:baseline; gap:6px;">
                            <span style="font-size:13px; color:var(--muted);">Delivery Fee:</span>
                            <strong style="font-size:14px; color:var(--text); font-weight:800;">₱<?php echo number_format((float)$o['shipping_fee'], 2); ?></strong>
                        </div>
                        <div style="display:flex; align-items:baseline; gap:6px;">
                            <span style="font-size:13px; color:var(--muted);">Payment:</span>
                            <strong style="font-size:14px; color:var(--text); font-weight:800;"><?php echo e($o['payment_method']); ?></strong>
                        </div>
                    </div>
                    <div style="display:flex; align-items:baseline; gap:8px; margin-left:auto;">
                        <span style="font-size:13px; color:var(--muted);">Order Total (<?php echo count($o['items']); ?> item<?php echo count($o['items']) !== 1 ? 's' : ''; ?>):</span>
                        <span style="font-size:19px; font-weight:900; color:var(--primary);">
                            ₱<?php echo number_format((float)$o['total_amount'], 2); ?>
                        </span>
                    </div>
                </div>

                <!-- 6. Actions Bar (Update Status & Cancel/Details) -->
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                    <div>
                        <?php if ($o['order_status'] !== 'Cancelled') { 
                            $curr_level = $status_levels[$o['order_status']] ?? 1;
                            if ($curr_level >= 4) {
                        ?>
                            <div style="display:inline-flex; align-items:center; gap:6px; font-size:13px; font-weight:700; color:#065f46; background:#d1fae5; padding:5px 12px; border-radius:8px;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                <span>Delivered &bull; Completed</span>
                            </div>
                        <?php } else { ?>
                            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                <label style="font-weight:700; font-size:13px; color:var(--muted); margin:0;">Update Status:</label>
                                <form method="POST" action="<?php echo BASE_URL; ?>/farmer/actions/update_order_status.php" style="display:inline-flex; gap:8px; align-items:center; margin:0;">
                                    <input type="hidden" name="order_id" value="<?php echo (int)$o['order_id']; ?>">
                                    <input type="hidden" name="current_filter" value="<?php echo e($status_filter); ?>">
                                    <select name="new_status" style="padding:7px 12px; border:1px solid var(--border); border-radius:8px; font-weight:700; font-size:13px; background:var(--surface); cursor:pointer;" onchange="this.form.submit()">
                                        <?php 
                                        foreach ($allowed_statuses as $st_key => $st_name) { 
                                            // Only allow current or forward status levels
                                            if (($status_levels[$st_key] ?? 1) < $curr_level) {
                                                continue;
                                            }
                                            $is_sel = ($o['order_status'] === $st_key 
                                                       || ($st_key === 'Ready for fulfillment' && in_array($o['order_status'], ['Ready for fulfillment', 'Ready for Pickup', 'Ready for Delivery', 'Ready for Drop-off'], true))
                                                       || ($st_key === 'Order Placed' && $o['order_status'] === 'Order Placed')
                                                       || ($st_key === 'Completed' && $o['order_status'] === 'Completed')) ? 'selected' : '';
                                        ?>
                                            <option value="<?php echo e($st_key); ?>" <?php echo $is_sel; ?>>
                                                <?php echo e($st_name); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </form>
                            </div>
                        <?php } 
                        } ?>
                    </div>

                    <div style="margin-left:auto;">
                        <?php if ($o['order_status'] === 'Cancelled') { 
                            $cancel_data = [
                                'order_id'            => (int)$o['order_id'],
                                'cancelled_by'        => !empty($o['cancelled_by']) ? $o['cancelled_by'] : 'Farmer',
                                'cancelled_at'        => !empty($o['cancelled_at']) ? date('M d, Y h:i A', strtotime($o['cancelled_at'])) : date('M d, Y', strtotime($o['order_date'])),
                                'cancellation_reason' => !empty($o['cancellation_reason']) ? $o['cancellation_reason'] : 'No specific reason provided.'
                            ];
                        ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="openCancellationDetailsModal(<?php echo htmlspecialchars(json_encode($cancel_data), ENT_QUOTES, 'UTF-8'); ?>)" style="padding:7px 16px; font-weight:700; font-size:12.5px;">
                                Cancellation Details
                            </button>
                        <?php } elseif ($o['order_status'] === 'Order Placed') { ?>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="openFarmerCancelModal(<?php echo (int)$o['order_id']; ?>)" style="padding:7px 16px; font-weight:700; font-size:12.5px;">
                                Cancel Order
                            </button>
                        <?php } ?>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>
</main>

<!-- FARMER CANCEL ORDER MODAL WITH REQUIRED REASON -->
<div class="modal" id="farmerCancelModal" aria-hidden="true" style="display:none;" onclick="if (event.target === this) closeModal('farmerCancelModal')">
    <div class="modal-backdrop" onclick="closeModal('farmerCancelModal')"></div>
    <div class="modal-content" style="max-width:480px;">
        <div class="modal-header">
            <h2 id="farmerCancelModalTitle" style="font-size:18px; font-weight:800; margin:0; color:#b42318;">Cancel Order Confirmation</h2>
            <button type="button" class="modal-close-x" onclick="closeModal('farmerCancelModal')" aria-label="Close">&times;</button>
        </div>
        <div style="font-size:13px; color:#b42318; background:#fef2f2; border:1px solid #fee2e2; border-radius:8px; padding:8px 12px; margin-top:10px; margin-bottom:10px; font-weight:600;">
            Are you sure you want to cancel this order? This action cannot be undone.
        </div>
        <p style="font-size:13.5px; color:var(--muted); margin:0 0 14px 0;">
            Orders can only be cancelled before confirmation. Please indicate the reason so the buyer understands:
        </p>

        <form method="POST" action="<?php echo BASE_URL; ?>/farmer/actions/cancel_order.php">
            <input type="hidden" name="order_id" id="farmer_cancel_order_id_input" value="0">
            <input type="hidden" name="return" value="/farmer/orders.php<?php echo !empty($status_filter) && $status_filter !== 'All' ? '?status=' . urlencode($status_filter) : ''; ?>">

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

<!-- FARMER CANCELLATION DETAILS MODAL -->
<div class="modal" id="farmerCancelDetailsModal" aria-hidden="true" style="display:none;" onclick="if (event.target === this) closeModal('farmerCancelDetailsModal')">
    <div class="modal-backdrop" onclick="closeModal('farmerCancelDetailsModal')"></div>
    <div class="modal-content" style="max-width:480px;">
        <div class="modal-header">
            <h2 style="font-size:18px; font-weight:800; margin:0; color:var(--text);">Cancellation Details</h2>
            <button type="button" class="modal-close-x" onclick="closeModal('farmerCancelDetailsModal')" aria-label="Close">&times;</button>
        </div>
        <div style="margin-top:16px; display:flex; flex-direction:column; gap:12px;">
            <div style="background:#fef2f2; border:1px solid #fee2e2; border-radius:10px; padding:12px 14px;">
                <div style="font-size:11.5px; font-weight:700; color:#991b1b; text-transform:uppercase; letter-spacing:0.5px;">Order Status</div>
                <div style="font-size:15px; font-weight:800; color:#b91c1c; margin-top:2px;">Cancelled</div>
            </div>
            <div>
                <div class="small-muted" style="font-size:12px; font-weight:700;">Cancelled By</div>
                <div id="cancelDetailBy" style="font-weight:700; font-size:14px; color:var(--text); margin-top:2px;">-</div>
            </div>
            <div>
                <div class="small-muted" style="font-size:12px; font-weight:700;">Date & Time Cancelled</div>
                <div id="cancelDetailDate" style="font-weight:600; font-size:13.5px; color:var(--text); margin-top:2px;">-</div>
            </div>
            <div>
                <div class="small-muted" style="font-size:12px; font-weight:700;">Reason for Cancellation</div>
                <div id="cancelDetailReason" style="background:#f8fafc; border:1px solid var(--border); border-radius:8px; padding:10px 12px; font-size:13.5px; color:var(--text); margin-top:4px; line-height:1.45; word-break:break-word;">-</div>
            </div>
        </div>
        <div style="display:flex; justify-content:flex-end; margin-top:18px;">
            <button type="button" class="btn btn-secondary" onclick="closeModal('farmerCancelDetailsModal')">Close</button>
        </div>
    </div>
</div>

<script>
function openFarmerCancelModal(orderId) {
    var input = document.getElementById('farmer_cancel_order_id_input');
    if (input) input.value = orderId;
    var title = document.getElementById('farmerCancelModalTitle');
    if (title) title.textContent = 'Cancel Order no. ' + orderId;
    openModal('farmerCancelModal');
}

function openCancellationDetailsModal(data) {
    var byEl = document.getElementById('cancelDetailBy');
    var dateEl = document.getElementById('cancelDetailDate');
    var reasonEl = document.getElementById('cancelDetailReason');
    if (byEl) byEl.textContent = data.cancelled_by || 'Farmer';
    if (dateEl) dateEl.textContent = data.cancelled_at || 'N/A';
    if (reasonEl) reasonEl.textContent = data.cancellation_reason || 'No specific reason provided.';
    openModal('farmerCancelDetailsModal');
}
</script>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
