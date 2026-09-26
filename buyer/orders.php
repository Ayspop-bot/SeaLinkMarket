<?php
/**
 * SeaLink Web Application
 * File: /buyer/orders.php
 * Purpose: Buyer order history with product images in item rows, pleasant styling, and switched action/total positions.
 * Uses: order_tbl, order_item_tbl, product_tbl, farmer_tbl
 */

require_once __DIR__ . '/../includes/auth_check.php';
check_access('buyer');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$active_tab = 'orders';
$page_title = "My Orders - SeaLink";
require_once __DIR__ . '/../includes/layout_top.php';

$buyer_id      = (int)$_SESSION['user_id'];
$status_filter = trim((string)($_GET['status'] ?? 'All'));

$where  = "o.buyer_id = ?";
$params = [$buyer_id];
$types  = "i";

if ($status_filter === 'Pending') {
    $where .= " AND o.order_status IN ('Order Placed', 'Confirmed')";
} elseif ($status_filter === 'Out for Delivery') {
    $where .= " AND o.order_status IN ('Ready for fulfillment', 'Ready for Delivery', 'Ready for Pickup', 'Ready for Drop-off')";
} elseif ($status_filter === 'Delivered') {
    $where .= " AND o.order_status = 'Completed'";
} elseif ($status_filter === 'Cancelled') {
    $where .= " AND o.order_status = 'Cancelled'";
}

$sql = "
SELECT
    o.order_id, o.farmer_id, o.order_date, o.order_status, o.total_amount, o.shipping_fee,
    o.payment_method, o.fulfillment_type, o.cancellation_reason, o.cancelled_by, o.cancelled_at,
    f.username AS farmer_name, f.address AS farmer_address
FROM order_tbl o
JOIN farmer_tbl f ON f.farmer_id = o.farmer_id
WHERE {$where}
ORDER BY o.order_date DESC
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$orders = [];
while ($row = mysqli_fetch_assoc($res)) {
    $oid  = (int)$row['order_id'];
    $is   = mysqli_prepare($conn, "
        SELECT oi.quantity, oi.price_at_purchase, p.product_id, p.name AS product_name, p.image_url,
               fb.feedback_id, fb.rating
        FROM order_item_tbl oi
        JOIN product_tbl p ON p.product_id = oi.product_id
        LEFT JOIN feedback_tbl fb ON fb.product_id = oi.product_id AND fb.buyer_id = ?
        WHERE oi.order_id = ?
    ");
    mysqli_stmt_bind_param($is, "ii", $buyer_id, $oid);
    mysqli_stmt_execute($is);
    $ir = mysqli_stmt_get_result($is);
    $items = [];
    $all_rated = true;
    while ($i = mysqli_fetch_assoc($ir)) {
        if (empty($i['feedback_id'])) {
            $all_rated = false;
        }
        $items[] = $i;
    }
    mysqli_stmt_close($is);
    $row['items'] = $items;
    $row['is_rated'] = (!empty($items) && $all_rated);
    $orders[] = $row;
}
$tot_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM order_tbl WHERE buyer_id = ?");
mysqli_stmt_bind_param($tot_stmt, "i", $buyer_id);
mysqli_stmt_execute($tot_stmt);
$tot_res = mysqli_stmt_get_result($tot_stmt);
$tot_row = mysqli_fetch_assoc($tot_res);
$total_orders_count = (int)($tot_row['total'] ?? count($orders));
mysqli_stmt_close($tot_stmt);

mysqli_close($conn);
?>

<main class="dashboard-content buyer-dashboard">

    <!-- Header Inside Container -->
    <div class="section-card" style="margin-bottom:16px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div>
                <h1 style="margin:0; font-size:24px;">My Orders</h1>
                <div class="small-muted" style="margin-top:4px;">Track your SeaLink purchases, view delivery progress, and rate completed orders</div>
            </div>
            <div class="small-muted"><?php echo $total_orders_count; ?> order(s) placed</div>
        </div>
    </div>

    <!-- Filter Subtabs -->
    <div class="subtabs" style="margin-top:14px; margin-bottom:14px;">
        <?php
        $filters = [
            'All' => 'All Orders',
            'Pending' => 'Pending',
            'Out for Delivery' => 'Out for Delivery',
            'Delivered' => 'Delivered',
            'Cancelled' => 'Cancelled'
        ];
        foreach ($filters as $val => $label) {
            $cls = ($status_filter === $val) ? 'active' : '';
            echo "<a class=\"subtab {$cls}\" href=\"?status=" . urlencode($val) . "\">{$label}</a>";
        }
        ?>
    </div>

    <!-- Orders List -->
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
            $status_msg = "Your order has been placed. Waiting for seller confirmation.";

            if ($status_text === 'Confirmed') {
                $badge_bg = '#e0f2fe';
                $badge_color = '#0369a1';
                $status_msg = "Seller confirmed your order. Catch is being prepared & packaged.";
            } elseif ($status_text === 'Ready for Delivery') {
                $badge_bg = '#dcfce7';
                $badge_color = '#15803d';
                $status_msg = "Parcel is out for delivery with local Santa Fe dispatch.";
            } elseif ($status_text === 'Ready for Pickup') {
                $badge_bg = '#e0e7ff';
                $badge_color = '#3730a3';
                $status_msg = "Ready for collection at the seller's designated selling port / dock.";
            } elseif ($status_text === 'Ready for Drop-off' || $status_text === 'Ready for fulfillment') {
                $badge_bg = '#dcfce7';
                $badge_color = '#15803d';
                $status_msg = "Catch is on the way to the designated community drop-off point.";
            } elseif ($status_text === 'Completed') {
                $badge_bg = '#d1fae5';
                $badge_color = '#065f46';
                $status_msg = "Order successfully delivered & completed. Thank you for supporting local Santa Fe fisherfolk!";
            } elseif ($status_text === 'Cancelled') {
                $badge_bg = '#fee2e2';
                $badge_color = '#991b1b';
                $cancelled_by_lower = strtolower(trim((string)($o['cancelled_by'] ?? '')));
                if ($cancelled_by_lower === 'buyer') {
                    $cancel_title = "You cancelled this order.";
                } elseif ($cancelled_by_lower === 'farmer' || $cancelled_by_lower === 'seller') {
                    $cancel_title = "Seller cancelled the order.";
                } else {
                    $cancel_title = "This order was cancelled.";
                }
                $status_msg = $cancel_title . (!empty($o['cancellation_reason']) ? " Reason: " . e($o['cancellation_reason']) : "");
                $status_icon = "❌";
            }
        ?>
            <div class="section-card" style="margin-top:0; background:#fff; border:1px solid var(--border); border-radius:14px; padding:20px; box-shadow:0 2px 8px rgba(0,0,0,0.02);">
                <!-- 1. Store Header Bar (Seller Name + Chat Bubble + Address on Left; Status Badge on Right) -->
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border); padding-bottom:12px; margin-bottom:12px; flex-wrap:wrap; gap:10px;">
                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                        <a href="<?php echo BASE_URL; ?>/farmer_store.php?farmer_id=<?php echo (int)($o['farmer_id'] ?? 0); ?>" style="font-weight:800; font-size:15.5px; color:var(--text); text-decoration:none;" onmouseover="this.style.color='var(--primary)';" onmouseout="this.style.color='var(--text)';">
                            <?php echo e($o['farmer_name']); ?>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/buyer/messages.php?farmer_id=<?php echo (int)($o['farmer_id'] ?? 0); ?>" title="Chat with seller" aria-label="Chat with seller" style="color:var(--primary); font-size:14px; text-decoration:none; display:inline-flex; align-items:center; padding:1px 5px; border-radius:4px; background:#eff6ff;" onmouseover="this.style.background='#dbeafe';" onmouseout="this.style.background='#eff6ff';">
                        </a>
                        <span class="small-muted" style="font-size:13px;">(<?php echo e(!empty(trim($o['farmer_address'] ?? '')) ? $o['farmer_address'] : 'Santa Fe, Romblon'); ?>)</span>
                    </div>

                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="font-size:11px; font-weight:800; background:<?php echo $badge_bg; ?>; color:<?php echo $badge_color; ?>; padding:3px 10px; border-radius:999px; text-transform:uppercase; letter-spacing:0.3px;">
                            <?php echo e($status_text); ?>
                        </span>
                    </div>
                </div>

                <!-- 3. Items List (Shopee / TikTok Product Row Style - Clickable Image & Name to order_details.php) -->
                <div style="display:flex; flex-direction:column; gap:10px; margin-bottom:14px;">
                    <?php foreach ($o['items'] as $item) { 
                        $item_subtotal = (float)$item['price_at_purchase'] * (int)$item['quantity'];
                    ?>
                        <div style="display:flex; align-items:center; justify-content:space-between; gap:14px; padding:8px 0; border-bottom:1px dashed #e2e8f0;">
                            <a href="<?php echo BASE_URL; ?>/buyer/order_details.php?order_id=<?php echo (int)$o['order_id']; ?>" style="display:flex; align-items:center; gap:12px; min-width:0; flex:1; text-decoration:none; color:inherit;">
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

                <!-- 4. Cost Summary Bar (Consistent Font Sizing across Delivery Fee, Payment, and Order Total) -->
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

                <!-- 5. Actions Bar (Max 2 Buttons, Cleanly Aligned) -->
                <?php if ($o['order_status'] === 'Order Placed' || in_array($o['order_status'], ['Completed', 'Cancelled'], true)) { ?>
                    <div style="display:flex; justify-content:flex-end; align-items:center; gap:10px; flex-wrap:wrap;">
                        <?php if ($o['order_status'] === 'Order Placed') { ?>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="openBuyerCancelModal(<?php echo (int)$o['order_id']; ?>)" style="padding:7px 16px; font-weight:700; font-size:12.5px;">
                                Cancel Order
                            </button>
                        <?php } ?>

                        <?php if ($o['order_status'] === 'Cancelled') { 
                            $buy_again_url = BASE_URL . '/buyer/checkout.php?mode=reorder&order_id=' . (int)$o['order_id'];
                            $cancel_data = [
                                'order_id' => (int)$o['order_id'],
                                'cancelled_by' => !empty($o['cancelled_by']) ? $o['cancelled_by'] : 'Buyer',
                                'cancelled_at' => !empty($o['cancelled_at']) ? date('M d, Y h:i A', strtotime($o['cancelled_at'])) : date('M d, Y', strtotime($o['order_date'])),
                                'cancellation_reason' => !empty($o['cancellation_reason']) ? $o['cancellation_reason'] : 'No specific reason provided.'
                            ];
                        ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="openCancellationDetailsModal(<?php echo htmlspecialchars(json_encode($cancel_data), ENT_QUOTES, 'UTF-8'); ?>)" style="padding:7px 16px; font-weight:700; font-size:12.5px;">
                                Cancellation Details
                            </button>
                            <a href="<?php echo $buy_again_url; ?>" class="btn btn-sm btn-primary" style="padding:7px 18px; font-weight:700; font-size:12.5px; text-decoration:none;">
                                Buy Again
                            </a>
                        <?php } elseif ($o['order_status'] === 'Completed') { 
                            $buy_again_url = BASE_URL . '/buyer/checkout.php?mode=reorder&order_id=' . (int)$o['order_id'];
                        ?>
                            <?php if (empty($o['is_rated'])) { ?>
                                <a href="<?php echo BASE_URL; ?>/buyer/order_details.php?order_id=<?php echo (int)$o['order_id']; ?>" class="btn btn-sm btn-primary" style="padding:7px 18px; font-weight:800; font-size:12.5px; text-decoration:none;">
                                    Rate Order
                                </a>
                            <?php } ?>
                            <a href="<?php echo $buy_again_url; ?>" class="btn btn-sm btn-outline-primary" style="padding:7px 18px; font-weight:700; font-size:12.5px; text-decoration:none;">
                                Buy Again
                            </a>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
</main>

<!-- BUYER CANCEL ORDER MODAL WITH REQUIRED REASON -->
<div class="modal" id="buyerCancelModal" aria-hidden="true" style="display:none;" onclick="if (event.target === this) closeModal('buyerCancelModal')">
    <div class="modal-backdrop" onclick="closeModal('buyerCancelModal')"></div>
    <div class="modal-content" style="max-width:480px;">
        <div class="modal-header">
            <h2 id="buyerCancelModalTitle" style="font-size:18px; font-weight:800; margin:0; color:#b42318;">Cancel Order Confirmation</h2>
            <button type="button" class="modal-close-x" onclick="closeModal('buyerCancelModal')" aria-label="Close">&times;</button>
        </div>
        <div style="font-size:13px; color:#b42318; background:#fef2f2; border:1px solid #fee2e2; border-radius:8px; padding:8px 12px; margin-top:10px; margin-bottom:10px; font-weight:600;">
            Are you sure you want to cancel this order? This action cannot be undone.
        </div>
        <p style="font-size:13.5px; color:var(--muted); margin:0 0 14px 0;">
            You can only cancel before the farmer confirms your order. Please let the farmer understand why you are cancelling:
        </p>

        <form method="POST" action="<?php echo BASE_URL; ?>/buyer/actions/cancel_order.php">
            <input type="hidden" name="order_id" id="cancel_order_id_input" value="0">
            <input type="hidden" name="return" value="/buyer/orders.php<?php echo !empty($status_filter) && $status_filter !== 'All' ? '?status=' . urlencode($status_filter) : ''; ?>">

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
                <label style="font-weight:700; font-size:13px;">Additional explanation (optional):</label>
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

<!-- BUYER CANCELLATION DETAILS MODAL -->
<div class="modal" id="buyerCancelDetailsModal" aria-hidden="true" style="display:none;" onclick="if (event.target === this) closeModal('buyerCancelDetailsModal')">
    <div class="modal-backdrop" onclick="closeModal('buyerCancelDetailsModal')"></div>
    <div class="modal-content" style="max-width:480px;">
        <div class="modal-header">
            <h2 style="font-size:18px; font-weight:800; margin:0; color:var(--text);">Cancellation Details</h2>
            <button type="button" class="modal-close-x" onclick="closeModal('buyerCancelDetailsModal')" aria-label="Close">&times;</button>
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
            <button type="button" class="btn btn-secondary" onclick="closeModal('buyerCancelDetailsModal')">Close</button>
        </div>
    </div>
</div>

<script>
function openBuyerCancelModal(orderId) {
    var input = document.getElementById('cancel_order_id_input');
    if (input) input.value = orderId;
    var title = document.getElementById('buyerCancelModalTitle');
    if (title) title.textContent = 'Cancel Order no. ' + orderId;
    openModal('buyerCancelModal');
}

function openCancellationDetailsModal(data) {
    var byEl = document.getElementById('cancelDetailBy');
    var dateEl = document.getElementById('cancelDetailDate');
    var reasonEl = document.getElementById('cancelDetailReason');
    if (byEl) byEl.textContent = data.cancelled_by || 'Buyer';
    if (dateEl) dateEl.textContent = data.cancelled_at || 'N/A';
    if (reasonEl) reasonEl.textContent = data.cancellation_reason || 'No specific reason provided.';
    openModal('buyerCancelDetailsModal');
}
</script>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
