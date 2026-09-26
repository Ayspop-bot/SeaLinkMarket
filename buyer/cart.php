<?php
/**
 * SeaLink Web Application
 * File: /buyer/cart.php
 * Purpose: Shows buyer's cart items with selectable checkboxes so buyers can choose which products to checkout.
 * Connected To:
 * - /buyer/checkout.php
 * - /buyer/actions/cart_update.php
 * - /buyer/actions/cart_remove.php
 * Uses: cart_item_tbl, product_tbl, farmer_tbl
 */

require_once __DIR__ . '/../includes/auth_check.php';
check_access('buyer');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$active_tab = 'cart';
$page_title = "My Cart - SeaLink";
require_once __DIR__ . '/../includes/layout_top.php';

$buyer_id = (int)$_SESSION['user_id'];

// Get cart items grouped by farmer
$sql = "
SELECT 
    c.cart_item_id, c.product_id, c.quantity, p.name, p.price, p.discounted_price, p.selling_deadline, p.image_url, p.stock_quantity, p.status,
    f.farmer_id, f.username AS farmer_name, f.address AS farmer_address
FROM cart_item_tbl c
JOIN product_tbl p ON p.product_id = c.product_id
JOIN farmer_tbl f ON f.farmer_id = p.farmer_id
WHERE c.buyer_id = ?
ORDER BY f.farmer_id ASC, c.added_at DESC
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $buyer_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$cart_groups = [];
$total_items_count = 0;

while ($row = mysqli_fetch_assoc($res)) {
    $fid = (int)$row['farmer_id'];
    if (!isset($cart_groups[$fid])) {
        $cart_groups[$fid] = [
            'farmer_name'    => $row['farmer_name'],
            'farmer_address' => $row['farmer_address'],
            'items'          => []
        ];
    }
    $cart_groups[$fid]['items'][] = $row;
    $total_items_count++;
}
mysqli_stmt_close($stmt);
mysqli_close($conn);
?>

<main class="dashboard-content buyer-dashboard">
    <!-- Header Inside Container (matching market.php) -->
    <div class="section-card" style="margin-bottom:16px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div>
                <h1 style="margin:0; font-size:24px;">SeaLink Shopping Cart</h1>
                <div class="small-muted" style="margin-top:4px;">Review your selected items and proceed to order from Santa Fe fisherfolk</div>
            </div>
            <div class="small-muted"><?php echo $total_items_count; ?> product(s) in cart</div>
        </div>
    </div>

    <?php if (empty($cart_groups)) { ?>
        <div class="panel" style="text-align:center; padding:40px 20px;">
            <div style="font-size:42px; margin-bottom:10px;"></div>
            <h2 style="font-size:20px; margin:0 0 8px 0;">Your cart is currently empty</h2>
            <p class="small-muted" style="margin-bottom:16px;">Explore fresh aquatic products from our local farmers in Santa Fe.</p>
            <a href="<?php echo BASE_URL; ?>/buyer/market.php" class="btn btn-primary">
                Browse Marketplace
            </a>
        </div>
    <?php } else { ?>
        
        <form method="GET" action="<?php echo BASE_URL; ?>/buyer/checkout.php" id="cartCheckoutForm" onsubmit="return validateSelectedCheckout()">
            <input type="hidden" name="mode" value="cart">

            <div style="display:flex; flex-direction:column; gap:16px;">
                <?php foreach ($cart_groups as $farmer_id => $group) { ?>
                    <div class="section-card">
                        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border); padding-bottom:10px; margin-bottom:14px; flex-wrap:wrap; gap:8px;">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <input type="checkbox" class="seller-checkbox" data-farmer-id="<?php echo (int)$farmer_id; ?>" checked onchange="toggleSellerGroup(<?php echo (int)$farmer_id; ?>, this.checked)" style="width:18px; height:18px; cursor:pointer;" title="Select all from <?php echo e($group['farmer_name']); ?>">
                                <span style="font-weight:800; font-size:16px; color:var(--text);"><?php echo e($group['farmer_name']); ?></span>
                                <span class="small-muted" style="margin-left:2px; font-size:13px;">(<?php echo e($group['farmer_address']); ?>)</span>
                            </div>

                            <!-- "..." button at edge side aligned with seller's name and address -->
                            <div class="cart-dropdown-wrapper" style="position:relative; display:inline-block;">
                                <button type="button" class="btn btn-sm btn-secondary cart-more-btn" onclick="toggleCartMenu(this, event)" style="width:32px; height:32px; padding:0; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; font-size:18px; font-weight:900; line-height:1;" title="Seller options" aria-label="Seller options">
                                    &hellip;
                                </button>
                                <div class="cart-menu-popover" style="display:none; position:absolute; right:0; top:calc(100% + 4px); background:#fff; border:1px solid var(--border); border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,0.12); z-index:60; min-width:145px; overflow:hidden;">
                                    <a href="<?php echo BASE_URL; ?>/farmer_store.php?farmer_id=<?php echo (int)$farmer_id; ?>" style="display:flex; align-items:center; gap:8px; padding:10px 14px; font-size:13px; font-weight:700; color:var(--text); text-decoration:none; transition:background 0.15s;" onmouseover="this.style.background='#f1f5f9';" onmouseout="this.style.background='transparent';">
                                        <span></span> Add More
                                    </a>
                                    <div style="height:1px; background:var(--border);"></div>
                                    <button type="button" onclick="openDeleteSellerModal(<?php echo (int)$farmer_id; ?>, '<?php echo e(addslashes($group['farmer_name'])); ?>')" style="width:100%; border:none; background:transparent; display:flex; align-items:center; gap:8px; padding:10px 14px; font-size:13px; font-weight:700; color:#dc2626; cursor:pointer; text-align:left; transition:background 0.15s;" onmouseover="this.style.background='#fee2e2';" onmouseout="this.style.background='transparent';">
                                        <span></span> Remove
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div style="display:flex; flex-direction:column; gap:12px;">
                            <?php foreach ($group['items'] as $item) {
                                $eff_price = get_product_effective_price($item['price'], $item['discounted_price'] ?? null, $item['selling_deadline'] ?? null);
                                $is_discounted = is_product_discounted($item['price'], $item['discounted_price'] ?? null, $item['selling_deadline'] ?? null);
                                $subtotal = $eff_price * (int)$item['quantity'];
                                $is_out_of_stock = ((int)$item['stock_quantity'] <= 0 || $item['status'] !== 'Active');
                            ?>
                                <div class="panel" style="display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; background:#fff; border:1px solid var(--border);">
                                    
                                    <!-- Checkbox + Image + Info -->
                                    <div style="display:flex; align-items:center; gap:14px; min-width:240px; flex:1;">
                                        <input type="checkbox" name="selected_items[]" value="<?php echo (int)$item['cart_item_id']; ?>"
                                               class="item-checkbox"
                                               data-farmer-id="<?php echo (int)$farmer_id; ?>"
                                               data-price="<?php echo (float)$eff_price; ?>"
                                               data-qty="<?php echo (int)$item['quantity']; ?>"
                                               data-subtotal="<?php echo $subtotal; ?>"
                                               <?php echo $is_out_of_stock ? 'disabled' : 'checked'; ?>
                                               onchange="onItemCheckboxChange(<?php echo (int)$farmer_id; ?>)"
                                               style="width:18px; height:18px; cursor:pointer;">

                                        <div style="width:70px; height:70px; border-radius:10px; overflow:hidden; background:#f2f2f2; border:1px solid #eee; flex-shrink:0;">
                                            <?php if (!empty($item['image_url'])) { ?>
                                                <img src="<?php echo BASE_URL . '/' . e($item['image_url']); ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                                            <?php } else { ?>
                                                <div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--muted);font-size:12px;">No Image</div>
                                            <?php } ?>
                                        </div>

                                        <div>
                                            <div style="font-weight:800; font-size:16px;">
                                                <a href="<?php echo BASE_URL; ?>/product_detail.php?product_id=<?php echo (int)$item['product_id']; ?>&return=<?php echo urlencode('/buyer/cart.php'); ?>" style="color:inherit; text-decoration:none;">
                                                    <?php echo e($item['name']); ?>
                                                </a>
                                            </div>
                                            <?php if ($is_discounted) { ?>
                                                <div style="margin-top:2px; display:flex; align-items:baseline; gap:6px; flex-wrap:wrap;">
                                                    <span style="color:#dc2626; font-size:16px; font-weight:900;">
                                                        ₱<?php echo number_format((float)$eff_price, 2); ?>
                                                    </span>
                                                    <span style="font-size:12px; color:var(--muted); text-decoration:line-through; font-weight:500;">
                                                        ₱<?php echo number_format((float)$item['price'], 2); ?>
                                                    </span>
                                                    <span style="font-size:12px; color:var(--muted); font-weight:600;">/ kg</span>
                                                </div>
                                            <?php } else { ?>
                                                <div style="color:var(--primary); font-weight:800; font-size:16px; margin-top:2px;">
                                                    ₱<?php echo number_format((float)$item['price'], 2); ?> <span style="font-size:12px; color:var(--muted); font-weight:600;">/ kg</span>
                                                </div>
                                            <?php } ?>
                                            <div class="small-muted" style="margin-top:2px;">
                                                Available stock: <?php echo (int)$item['stock_quantity']; ?> kg
                                            </div>
                                            <?php if ($is_out_of_stock) { ?>
                                                <div style="color:#b42318; font-size:12px; font-weight:700; margin-top:4px;">Unavailable / Out of stock</div>
                                            <?php } ?>
                                        </div>
                                    </div>

                                    <!-- Qty Controls -->
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <button type="button" class="btn btn-sm btn-secondary" onclick="updateItemQty(<?php echo (int)$item['product_id']; ?>, 'decrease')" style="width:32px; height:32px; padding:0; display:inline-flex; align-items:center; justify-content:center;">-</button>

                                        <span style="font-weight:800; width:44px; text-align:center; font-size:15px;"><?php echo (int)$item['quantity']; ?> kg</span>

                                        <button type="button" class="btn btn-sm btn-secondary" onclick="updateItemQty(<?php echo (int)$item['product_id']; ?>, 'increase')" style="width:32px; height:32px; padding:0; display:inline-flex; align-items:center; justify-content:center;" <?php echo ((int)$item['quantity'] >= (int)$item['stock_quantity'] ? 'disabled' : ''); ?>>+</button>
                                    </div>

                                    <!-- Subtotal (redundant product ellipsis removed) -->
                                    <div style="font-weight:800; font-size:16px; color:var(--text); text-align:right; min-width:90px;">
                                        ₱<?php echo number_format($subtotal, 2); ?>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>

                <!-- Grand Total of Selected Items & Checkout Action (Check Out on the edge) -->
                <div class="panel" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; background:#fff; border:1px solid var(--border); position:sticky; bottom:12px; z-index:90; box-shadow: 0 4px 16px rgba(0,0,0,0.08); border-radius:14px; padding:12px 14px 12px 20px;">
                    <!-- Left: Select All Checkbox -->
                    <div style="display:flex; align-items:center; gap:10px;">
                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer; font-weight:700; margin:0; font-size:15px; color:var(--text);">
                            <input type="checkbox" id="selectAllCheckbox" checked onchange="toggleSelectAll(this.checked)" style="width:19px; height:19px; cursor:pointer;">
                            <span>Select All Items for Checkout</span>
                        </label>
                        <span class="small-muted" id="selectedCountText" style="font-size:13px;">(0 selected)</span>
                    </div>

                    <!-- Right: Selected Total & Checkout Action on the Far Edge -->
                    <div style="display:flex; align-items:center; gap:20px; margin-left:auto; flex-wrap:wrap;">
                        <div style="display:flex; align-items:baseline; gap:8px;">
                            <span class="small-muted" style="font-size:13px;">Selected Items Total (excluding delivery fee):</span>
                            <span style="font-size:22px; font-weight:900; color:var(--primary); line-height:1;" id="selectedGrandTotal">
                                ₱0.00
                            </span>
                        </div>

                        <button type="submit" class="btn btn-primary" id="checkoutBtn" style="padding:13px 32px; font-weight:800; font-size:15.5px; border-radius:10px; margin-left:8px;">
                            Check Out
                        </button>
                    </div>
                </div>
            </div>
        </form>
    <?php } ?>
</main>

<!-- Hidden form for Qty AJAX / Action -->
<form id="qtyActionForm" method="POST" action="<?php echo BASE_URL; ?>/buyer/actions/cart_update.php" style="display:none;">
    <input type="hidden" name="product_id" id="qty_product_id">
    <input type="hidden" name="action" id="qty_action">
</form>

<!-- Delete Cart Item Confirmation Modal -->
<div class="modal" id="deleteCartModal" aria-hidden="true">
    <div class="modal-content" style="max-width:440px;">
        <div class="modal-header">
            <h2>Remove Item</h2>
        </div>
        <form method="POST" action="<?php echo BASE_URL; ?>/buyer/actions/cart_remove.php">
            <input type="hidden" name="product_id" id="delete_cart_product_id" required>
            <p style="margin-top:10px;">
                Are you sure you want to remove <strong id="delete_cart_product_name"></strong> from your cart?
            </p>
            <div class="form-actions" style="justify-content:flex-end; margin-top:16px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteCartModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Remove</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Selected Items From Seller Confirmation Modal -->
<div class="modal" id="deleteSellerModal" aria-hidden="true">
    <div class="modal-content" style="max-width:440px;">
        <div class="modal-header">
            <h2>Remove Selected Items</h2>
        </div>
        <form method="POST" action="<?php echo BASE_URL; ?>/buyer/actions/cart_remove.php">
            <div id="delete_selected_ids_container"></div>
            <p style="margin-top:10px;">
                Are you sure you want to remove the <strong id="delete_seller_item_count"></strong> from <strong id="delete_seller_farmer_name"></strong> from your cart?
            </p>
            <div class="form-actions" style="justify-content:flex-end; margin-top:16px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteSellerModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="background:#dc2626; border-color:#dc2626; color:#fff;">Remove Selected</button>
            </div>
        </form>
    </div>
</div>

<script>
function openDeleteSellerModal(farmerId, sellerName) {
    // Look up selected products for this seller
    var checkedItems = document.querySelectorAll('.item-checkbox[data-farmer-id="' + farmerId + '"]:checked');
    if (checkedItems.length === 0) {
        alert('Please select at least one item from ' + sellerName + ' to remove.');
        return;
    }

    var container = document.getElementById('delete_selected_ids_container');
    container.innerHTML = '';

    var count = checkedItems.length;
    checkedItems.forEach(function(cb) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'cart_item_ids[]';
        input.value = cb.value;
        container.appendChild(input);
    });

    document.getElementById('delete_seller_item_count').textContent = count + ' selected item' + (count !== 1 ? 's' : '');
    document.getElementById('delete_seller_farmer_name').textContent = sellerName;
    openModal('deleteSellerModal');
}

function openDeleteCartModal(id, name) {
    document.getElementById('delete_cart_product_id').value = id;
    document.getElementById('delete_cart_product_name').textContent = name;
    openModal('deleteCartModal');
}

function updateItemQty(productId, action) {
    document.getElementById('qty_product_id').value = productId;
    document.getElementById('qty_action').value = action;
    document.getElementById('qtyActionForm').submit();
}

function toggleCartMenu(btn, e) {
    if (e) e.stopPropagation();
    var popover = btn.nextElementSibling;
    var allPopovers = document.querySelectorAll('.cart-menu-popover');
    allPopovers.forEach(function(p) {
        if (p !== popover) p.style.display = 'none';
    });
    if (popover) {
        popover.style.display = (popover.style.display === 'block') ? 'none' : 'block';
    }
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.cart-dropdown-wrapper')) {
        document.querySelectorAll('.cart-menu-popover').forEach(function(p) {
            p.style.display = 'none';
        });
    }
});

function toggleSelectAll(isChecked) {
    var checkboxes = document.querySelectorAll('.item-checkbox:not(:disabled)');
    checkboxes.forEach(function(cb) {
        cb.checked = isChecked;
    });
    var sellerCheckboxes = document.querySelectorAll('.seller-checkbox');
    sellerCheckboxes.forEach(function(scb) {
        scb.checked = isChecked;
    });
    recalcCartTotal();
}

function toggleSellerGroup(farmerId, isChecked) {
    var items = document.querySelectorAll('.item-checkbox[data-farmer-id="' + farmerId + '"]:not(:disabled)');
    items.forEach(function(cb) {
        cb.checked = isChecked;
    });
    syncSelectAllState();
    recalcCartTotal();
}

function onItemCheckboxChange(farmerId) {
    var sellerItems = document.querySelectorAll('.item-checkbox[data-farmer-id="' + farmerId + '"]:not(:disabled)');
    var sellerChecked = document.querySelectorAll('.item-checkbox[data-farmer-id="' + farmerId + '"]:checked');
    var sellerCb = document.querySelector('.seller-checkbox[data-farmer-id="' + farmerId + '"]');
    if (sellerCb) {
        sellerCb.checked = (sellerItems.length > 0 && sellerItems.length === sellerChecked.length);
    }
    syncSelectAllState();
    recalcCartTotal();
}

function syncSelectAllState() {
    var allItems = document.querySelectorAll('.item-checkbox:not(:disabled)');
    var allChecked = document.querySelectorAll('.item-checkbox:checked');
    var selectAllCb = document.getElementById('selectAllCheckbox');
    if (selectAllCb) {
        selectAllCb.checked = (allItems.length > 0 && allItems.length === allChecked.length);
    }
}

function recalcCartTotal() {
    var checkboxes = document.querySelectorAll('.item-checkbox:checked');
    var total = 0;
    var count = 0;
    checkboxes.forEach(function(cb) {
        var subtotal = parseFloat(cb.dataset.subtotal) || 0;
        total += subtotal;
        count++;
    });

    var totalEl = document.getElementById('selectedGrandTotal');
    if (totalEl) {
        totalEl.textContent = '₱' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    var countText = document.getElementById('selectedCountText');
    if (countText) {
        countText.textContent = '(' + count + ' selected)';
    }

    var btn = document.getElementById('checkoutBtn');
    if (btn) {
        btn.disabled = (count === 0);
    }
}

function validateSelectedCheckout() {
    var selected = document.querySelectorAll('.item-checkbox:checked');
    if (selected.length === 0) {
        alert('Please select at least one item to proceed to checkout.');
        return false;
    }
    return true;
}

document.addEventListener('DOMContentLoaded', function() {
    // Initial sync of seller checkboxes
    var sellers = document.querySelectorAll('.seller-checkbox');
    sellers.forEach(function(scb) {
        var fid = scb.dataset.farmerId;
        var sItems = document.querySelectorAll('.item-checkbox[data-farmer-id="' + fid + '"]:not(:disabled)');
        var sChecked = document.querySelectorAll('.item-checkbox[data-farmer-id="' + fid + '"]:checked');
        scb.checked = (sItems.length > 0 && sItems.length === sChecked.length);
    });
    syncSelectAllState();
    recalcCartTotal();
});
</script>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
