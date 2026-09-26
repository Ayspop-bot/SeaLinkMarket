<?php
/**
 * SeaLink Web Application
 * File: /farmer/manage_products.php
 * Purpose: Full Manage Products interface with updated categories, unit selections, status definitions, and confirmation modals.
 * Connected To:
 *  - /farmer/actions/product_add.php
 *  - /farmer/actions/product_edit.php
 *  - /farmer/actions/product_delete.php
 *  - /farmer/product_gallery.php
 * Uses: product_tbl, category_tbl, order_item_tbl, order_tbl
 */

require_once __DIR__ . '/../includes/auth_check.php';
check_access('farmer');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$active_tab  = 'manage_products';
$page_title  = "Manage Products - SeaLink";
require_once __DIR__ . '/../includes/layout_top.php';

$farmer_id   = (int)($_SESSION['user_id'] ?? 0);
$is_verified = (($_SESSION['verification_status'] ?? '') === 'Verified');

// Auto-sync expired promotions in real-time
check_and_expire_promotions($conn);

// Retrieve dynamic platform GCash payment details and promotion rates
$promo_settings = get_promote_settings($conn);
$promo_packages = get_promotion_packages($conn, true);


/* ========== CATEGORIES ========== */
$categories = [];
$res = mysqli_query($conn, "SELECT category_id, category_name FROM category_tbl ORDER BY category_name ASC");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) $categories[] = $row;
}

$default_categories = [
    'Fish (Bangus, Tilapia, Tulingan)',
    'Shrimps / Crabs (Hipon, Alimango)',
    'Shellfish (Tahong, Talaba)',
    'Squid (Pusit)',
    'Octopus (Pugita)',
    'Seaweeds (Lato, Guso)'
];

/* ========== PRODUCTS LIST ========== */
$sql = "
SELECT
  p.product_id,
  p.category_id,
  p.name,
  p.description,
  p.price,
  p.stock_quantity,
  p.image_url,
  p.status,
  p.harvested_at,
  p.shelf_life_hours,
  p.selling_deadline,
  p.is_boosted,
  p.boost_tier,
  p.boost_expires_at,
  p.is_promoted,
  p.promotion_start_date,
  p.promotion_end_date,
  p.receipt_image_path,
  p.promotion_status,
  p.promotion_plan,
  p.discounted_price,
  c.category_name,
  COALESCE(s.sold_qty, 0) AS sold_qty
FROM product_tbl p
JOIN category_tbl c ON c.category_id = p.category_id
LEFT JOIN (
  SELECT
    oi.product_id,
    SUM(CASE WHEN o.order_status = 'Completed' THEN oi.quantity ELSE 0 END) AS sold_qty
  FROM order_item_tbl oi
  JOIN order_tbl o ON o.order_id = oi.order_id
  GROUP BY oi.product_id
) s ON s.product_id = p.product_id
WHERE p.farmer_id = ?
ORDER BY p.created_at DESC
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $farmer_id);
mysqli_stmt_execute($stmt);
$products_res = mysqli_stmt_get_result($stmt);

$products = [];
while ($row = mysqli_fetch_assoc($products_res)) $products[] = $row;
mysqli_stmt_close($stmt);

$product_count = count($products);
mysqli_close($conn);
?>

<main class="dashboard-content farmer-dashboard">
  <!-- Page Header Inside Container (matching Buyer UI) -->
  <div class="section-card" style="margin-bottom:16px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
      <div>
        <h1 style="margin:0; font-size:24px;">Manage Products</h1>
        <div class="small-muted">Add, edit, monitor stock, and manage your online inventory</div>
      </div>

      <div>
        <a class="btn btn-secondary" href="<?php echo BASE_URL; ?>/farmer/product_gallery.php">
          Store Gallery
        </a>
      </div>
    </div>
  </div>

  <?php if (!$is_verified) { ?>
    <div class="alert alert-warning">
      <strong>Account Verification Pending:</strong> Your account is currently awaiting verification by our administrator. Product adding, editing, and deleting will be enabled once your permit is verified.
    </div>
  <?php } ?>

  <!-- Products Table Container with Add Product at top-right -->
  <div class="section-card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
      <div>
        <div style="font-weight:800; font-size:16px;">Product Inventory</div>
        <div class="small-muted"><?php echo $product_count; ?> product(s) in your store</div>
      </div>

      <button class="btn btn-primary" type="button"
        <?php echo $is_verified ? 'onclick="openAddModal()"' : 'disabled aria-disabled="true" title="Posting is disabled until verified"'; ?>>
        Add Product
      </button>
    </div>

    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Product Name & Photo</th>
            <th>Category</th>
            <th>Selling Price</th>
            <th>Available Stock</th>
            <th>Total Sold</th>
            <th>Status</th>
            <th style="width:130px; text-align:center;">Actions</th>
          </tr>
        </thead>

        <tbody>
          <?php if ($product_count === 0) { ?>
            <tr>
              <td colspan="7" class="small-muted" style="text-align:center; padding:30px;">
                No products found. Click <strong>Add Product</strong> above to start listing your goods.
              </td>
            </tr>
          <?php } ?>

          <?php foreach ($products as $p) { 
            $is_active = ($p['status'] === 'Active');
            $status_label = $is_active ? 'On Sale' : 'Out of Stock';
            // Freshness / sale deadline calculation
            $deadline = !empty($p['selling_deadline']) ? strtotime($p['selling_deadline']) : null;
            $now = time();
            $freshness_html = '<span class="small-muted" style="font-size:12px;">—</span>';
            $sale_hours_left = '';
            if ($deadline) {
              $diff = $deadline - $now;
              if ($diff <= 0) {
                $freshness_html = '<span style="color:#b42318;font-size:12px;font-weight:700;">⚠️ Expired</span>';
                $sale_hours_left = 'Ended';
              } elseif ($diff < 3600) {
                $mins = max(1, (int)ceil($diff / 60));
                $freshness_html = '<span style="color:#b42318;font-size:12px;font-weight:700;">⏳ ' . $mins . ' min(s) left</span>';
                $sale_hours_left = $mins . 'm left';
              } elseif ($diff < 86400) {
                $hrs = (int)ceil($diff / 3600);
                $freshness_html = '<span style="color:#d97706;font-size:12px;font-weight:700;">⏳ ' . $hrs . 'h left</span>';
                $sale_hours_left = $hrs . 'h left';
              } else {
                $days = (int)floor($diff / 86400);
                $freshness_html = '<span style="color:#15803d;font-size:12px;font-weight:700;">✅ ' . $days . 'd left</span>';
                $sale_hours_left = $days . 'd left';
              }
            }

            // Duration in hours (for modal prefill)
            $duration_hours_val = '';
            if (!empty($p['selling_deadline'])) {
              $diff = strtotime($p['selling_deadline']) - $now;
              if ($diff > 0) {
                $duration_hours_val = (string)max(1, min(24, (int)ceil($diff / 3600)));
              }
            } elseif (!empty($p['shelf_life_hours'])) {
              $duration_hours_val = (string)min(24, (int)$p['shelf_life_hours']);
            }
          ?>
            <tr>
              <td>
                <div class="product-cell">
                  <div class="thumb">
                    <?php if (!empty($p['image_url'])) { ?>
                      <img src="<?php echo BASE_URL . '/' . e($p['image_url']); ?>" alt="">
                    <?php } else { ?>
                      <div style="display:flex;align-items:center;justify-content:center;height:100%;font-size:13px;color:var(--muted);">No Image</div>
                    <?php } ?>
                  </div>
                  <div>
                    <div style="font-weight:800; font-size:15px;"><?php echo e($p['name']); ?></div>
                  </div>
                </div>
              </td>

              <td><?php echo e($p['category_name']); ?></td>
              <td>
                <?php if (!empty($p['discounted_price']) && $p['discounted_price'] < $p['price']) { ?>
                  <div style="color:#dc2626; font-size:15px; font-weight:800;">₱<?php echo number_format((float)$p['discounted_price'], 2); ?></div>
                  <div style="font-size:12px; color:var(--muted); text-decoration:line-through; font-weight:400;">₱<?php echo number_format((float)$p['price'], 2); ?></div>
                <?php } else { ?>
                  <span style="font-weight:700;">₱<?php echo number_format((float)$p['price'], 2); ?></span>
                <?php } ?>
              </td>
              <td><?php echo (int)$p['stock_quantity']; ?></td>
              <td><strong><?php echo (int)$p['sold_qty']; ?></strong></td>

              <td>
                <span class="badge <?php echo $is_active ? 'active' : 'inactive'; ?>">
                  <?php echo e($status_label); ?>
                </span>
                <?php if (!empty($p['discounted_price']) && (float)$p['discounted_price'] < (float)$p['price']) { ?>
                  <div style="margin-top:4px;">
                    <span style="background:#fef2f2; color:#dc2626; border:1px solid #fecaca; border-radius:4px; padding:2px 6px; font-size:11px; font-weight:700; display:inline-block; line-height:1.2;">
                      Discounted<?php if ($sale_hours_left !== '') { echo ' &bull; ' . e($sale_hours_left); } ?>
                    </span>
                  </div>
                <?php } ?>
                <?php if (($p['promotion_status'] ?? '') === 'Pending') { ?>
                  <div style="margin-top:4px;">
                    <span style="background:#fef3c7; color:#b45309; border:1px solid #fde68a; border-radius:4px; padding:2px 6px; font-size:11px; font-weight:700; display:inline-block; line-height:1.2;">
                      Pending Promotion Approval
                    </span>
                  </div>
                <?php } elseif (!empty($p['is_promoted']) && !empty($p['promotion_end_date']) && strtotime($p['promotion_end_date']) > time()) { 
                  $promo_left = strtotime($p['promotion_end_date']) - time();
                  $p_days = floor($promo_left / 86400);
                  $p_hours = floor(($promo_left % 86400) / 3600);
                  $promo_time_str = ($p_days > 0 ? "{$p_days}d " : "") . "{$p_hours}h left";
                ?>
                  <div style="margin-top:4px;">
                    <span style="background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; border-radius:4px; padding:2px 6px; font-size:11px; font-weight:700; display:inline-block; line-height:1.2;">
                    Promoted <?php echo e($promo_time_str); ?>
                    </span>
                  </div>
                <?php } ?>
              </td>

              <td style="text-align:center; vertical-align:middle;">
                <div class="actions" style="display:flex; flex-direction:column; gap:6px; align-items:center; justify-content:center; width:100%; max-width:115px; margin:0 auto;">
                  <button type="button"
                    class="btn btn-sm"
                    style="width:100%; justify-content:center; background:linear-gradient(135deg, #f59e0b, #d97706); color:#fff; border:none; font-weight:700; display:inline-flex; align-items:center; gap:4px; padding:6px 10px; border-radius:6px; box-shadow:0 1px 2px rgba(0,0,0,0.1);"
                    <?php echo ($is_verified && $p['status'] === 'Active') ? '' : 'disabled'; ?>
                    data-id="<?php echo (int)$p['product_id']; ?>"
                    data-name="<?php echo e($p['name']); ?>"
                    data-price="<?php echo e($p['price']); ?>"
                    data-stock="<?php echo e($p['stock_quantity']); ?>"
                    data-img="<?php echo !empty($p['image_url']) ? BASE_URL . '/' . e($p['image_url']) : ''; ?>"
                    data-promo-status="<?php echo e($p['promotion_status'] ?? 'None'); ?>"
                    data-promo-plan="<?php echo e($p['promotion_plan'] ?? ''); ?>"
                    data-promo-end="<?php echo e($p['promotion_end_date'] ?? ''); ?>"
                    onclick="openPromoteProductModal(this)">
                    <span></span> Promote
                  </button>

                  <button type="button"
                    class="btn btn-sm btn-outline-primary"
                    style="width:100%; justify-content:center; padding:5px 10px;"
                    <?php echo $is_verified ? '' : 'disabled'; ?>
                    data-id="<?php echo (int)$p['product_id']; ?>"
                    data-name="<?php echo e($p['name']); ?>"
                    data-category-id="<?php echo (int)$p['category_id']; ?>"
                    data-description="<?php echo e($p['description']); ?>"
                    data-price="<?php echo e($p['price']); ?>"
                    data-stock="<?php echo (int)$p['stock_quantity']; ?>"
                    data-status="<?php echo e($p['status']); ?>"
                    data-sale-duration="<?php echo e($duration_hours_val); ?>"
                    data-discounted-price="<?php echo e($p['discounted_price'] ?? ''); ?>"
                    onclick="populateEditModal(this)">
                    Edit
                  </button>

                  <button type="button"
                    class="btn btn-sm btn-outline-danger"
                    style="width:100%; justify-content:center; padding:5px 10px;"
                    <?php echo $is_verified ? '' : 'disabled'; ?>
                    data-id="<?php echo (int)$p['product_id']; ?>"
                    data-name="<?php echo e($p['name']); ?>"
                    data-img="<?php echo !empty($p['image_url']) ? BASE_URL . '/' . e($p['image_url']) : ''; ?>"
                    onclick="populateDeleteModal(this)">
                    Delete
                  </button>
                </div>
              </td>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<!-- ADD PRODUCT MODAL -->
<div class="modal" id="addProductModal" aria-hidden="true">
  <div class="modal-content" style="max-width:580px;">
    <div class="modal-header">
      <h2>Add New Product</h2>
      <button type="button" class="modal-close-x" onclick="closeModal('addProductModal')" aria-label="Close">&times;</button>
    </div>

    <form method="POST" action="<?php echo BASE_URL; ?>/farmer/actions/product_add.php" enctype="multipart/form-data" id="addProductForm" onsubmit="return handleAddSubmit(event)">
      <div class="form-group">
        <label>Product Name *</label>
        <input type="text" name="name" required maxlength="100" placeholder="e.g. Fresh Milkfish, Dried Fish, Seaweeds">
      </div>

      <div class="form-group">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
          <label style="margin:0;">Category *</label>
          <button type="button" onclick="openModal('categoryGuideModal')" title="View Aquatic Category Guide" aria-label="Category Guide" style="background:none; border:none; cursor:pointer; padding:2px 4px; display:inline-flex; align-items:center; gap:5px; color:var(--primary); font-size:12px; font-weight:700;">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="16" x2="12" y2="12"></line>
              <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>
            <span>Category Guide</span>
          </button>
        </div>
        <select name="category_id" required>
          <option value="">Select Category</option>
          <?php foreach ($categories as $c) { ?>
            <option value="<?php echo (int)$c['category_id']; ?>"><?php echo e($c['category_name']); ?></option>
          <?php } ?>
          <?php if (empty($categories)) { ?>
            <?php foreach ($default_categories as $dCat) { ?>
              <option value="1"><?php echo e($dCat); ?></option>
            <?php } ?>
          <?php } ?>
        </select>
      </div>

      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
        <div class="form-group">
          <label>Selling Price *</label>
          <input type="number" name="price" required step="0.01" min="0" placeholder="₱0.00">
        </div>

        <div class="form-group">
          <label>Selling Unit *</label>
          <select name="unit_type" id="add_unit_type">
            <option value="kg">Per Kilogram (kg)</option>
            <option value="half_kg">Half Kilogram (1/2 kg)</option>
            <option value="quarter_kg">Quarter Kilogram (1/4 kg)</option>
            <option value="three_eighths_kg">3/8 Kilogram</option>
            <option value="pack">Per Pack</option>
            <option value="bundle">Per Bundle</option>
            <option value="piece">Per Piece</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label>Discounted Sale Price <span style="font-weight:400; color:var(--muted); font-size:12px;">(Optional — leave blank for no discount)</span></label>
        <input type="number" name="discounted_price" step="0.01" min="0" placeholder="e.g. ₱120.00 — must be lower than selling price">
        <div style="font-size:11px; color:#d97706; margin-top:4px;">Setting a discount will feature this product in <strong>Today's Deals</strong> on the homepage.</div>
      </div>

      <div class="form-group">
        <label>Available Stock Quantity *</label>
        <input type="number" name="stock_quantity" required min="0" placeholder="e.g. 50">
      </div>

      <div class="form-group">
        <label>Description</label>
        <textarea name="description" rows="3" placeholder="Describe product freshness, origin, harvesting method..."></textarea>
      </div>

      <div class="form-group">
        <label>Status *</label>
        <select name="status" required>
          <option value="Active">On Sale</option>
          <option value="Inactive">Out of Stock</option>
        </select>
      </div>

      <div class="form-group">
        <label>Product Image (JPG or PNG, max 2MB) *</label>
        <input type="file" name="image" accept="image/jpeg,image/png" required>
      </div>

      <div class="form-group custom-duration-picker" style="position:relative;">
        <label>Duration of Sale <span style="font-weight:400; color:var(--muted); font-size:12px;">(Optional &mdash; Max 24 hours. Leave blank if not on sale)</span></label>
        
        <input type="hidden" name="sale_duration_hours" id="add_sale_duration" value="">

        <!-- Trigger Button -->
        <button type="button" id="add_duration_trigger" onclick="toggleDurationDropdown('addDurationMenu')" 
                style="width:100%; display:flex; justify-content:space-between; align-items:center; padding:10px 14px; border:1px solid var(--border); border-radius:8px; background:#fff; font-size:14px; cursor:pointer; text-align:left; color:var(--text); box-sizing:border-box;">
          <span id="add_duration_label">No Sale Duration (Leave Blank)</span>
          <span style="font-size:11px; color:var(--muted);"></span>
        </button>

        <!-- Compact Scrollable Dropdown Menu (max-height 150px) -->
        <div id="addDurationMenu" class="custom-scrollable-duration" 
             style="display:none; position:absolute; top:calc(100% - 24px); left:0; right:0; max-height:150px; overflow-y:auto; background:#fff; border:1px solid var(--border); border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,0.18); z-index:1050;">
          <div onclick="selectSaleDuration('add', '', 'No Sale Duration (Leave Blank)')" 
               style="padding:8px 14px; font-size:13px; cursor:pointer; border-bottom:1px solid #f1f5f9; color:var(--muted);"
               onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
            No Sale Duration (Leave Blank)
          </div>
          <?php for ($h = 1; $h <= 24; $h++) { ?>
            <div onclick="selectSaleDuration('add', '<?php echo $h; ?>', '<?php echo $h; ?> hour<?php echo $h > 1 ? 's' : ''; ?>')"
                 style="padding:8px 14px; font-size:13px; cursor:pointer; border-bottom:1px solid #f8fafc; color:var(--text);"
                 onmouseover="this.style.background='#f0fdf4'" onmouseout="this.style.background='transparent'">
              <?php echo $h; ?> hour<?php echo $h > 1 ? 's' : ''; ?>
            </div>
          <?php } ?>
        </div>

        <!-- Quick Duration Chips -->
        <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap; margin-top:8px;">
          <span style="font-size:11px; color:var(--muted); font-weight:700;">Quick:</span>
          <button type="button" class="btn btn-sm" onclick="selectSaleDuration('add', '', 'No Sale Duration (Leave Blank)')" style="padding:2px 8px; font-size:11px; background:#f1f5f9; border:1px solid var(--border); border-radius:6px; color:var(--muted);">None</button>
          <button type="button" class="btn btn-sm" onclick="selectSaleDuration('add', '1', '1 hour')" style="padding:2px 8px; font-size:11px; background:#f8fafc; border:1px solid var(--border); border-radius:6px;">1h</button>
          <button type="button" class="btn btn-sm" onclick="selectSaleDuration('add', '3', '3 hours')" style="padding:2px 8px; font-size:11px; background:#f8fafc; border:1px solid var(--border); border-radius:6px;">3h</button>
          <button type="button" class="btn btn-sm" onclick="selectSaleDuration('add', '6', '6 hours')" style="padding:2px 8px; font-size:11px; background:#f8fafc; border:1px solid var(--border); border-radius:6px;">6h</button>
          <button type="button" class="btn btn-sm" onclick="selectSaleDuration('add', '12', '12 hours')" style="padding:2px 8px; font-size:11px; background:#f8fafc; border:1px solid var(--border); border-radius:6px;">12h</button>
          <button type="button" class="btn btn-sm" onclick="selectSaleDuration('add', '24', '24 hours')" style="padding:2px 8px; font-size:11px; background:#f8fafc; border:1px solid var(--border); border-radius:6px;">24h</button>
        </div>

        <div style="font-size:11px; color:var(--muted); margin-top:6px;">
          Sets how long the product will have a discounted price before returning to its original price.
        </div>
      </div>

      <div class="form-actions" style="justify-content:flex-end; margin-top:16px;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('addProductModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Product</button>
      </div>
    </form>
  </div>
</div>

<!-- ADD CONFIRMATION MODAL -->
<div class="modal" id="addConfirmModal" aria-hidden="true">
  <div class="modal-content" style="max-width:440px;">
    <div class="modal-header">
      <h2>Confirm Product Submission</h2>
      <button type="button" class="modal-close-x" onclick="closeModal('addConfirmModal')" aria-label="Close">&times;</button>
    </div>
    <p style="margin-top:10px;">
      Are you sure you want to add and list this product in your store?
    </p>
    <div class="form-actions" style="justify-content:flex-end; margin-top:16px;">
      <button type="button" class="btn btn-secondary" onclick="closeModal('addConfirmModal')">Back</button>
      <button type="button" class="btn btn-primary" onclick="proceedAddProduct()">Confirm and Post</button>
    </div>
  </div>
</div>

<!-- EDIT PRODUCT MODAL -->
<div class="modal" id="editProductModal" aria-hidden="true">
  <div class="modal-content" style="max-width:580px;">
    <div class="modal-header">
      <h2>Edit Product</h2>
    </div>

    <form method="POST" action="<?php echo BASE_URL; ?>/farmer/actions/product_edit.php" enctype="multipart/form-data" id="editProductForm" onsubmit="return handleEditSubmit(event)">
      <input type="hidden" name="product_id" id="edit_product_id" required>

      <div class="form-group">
        <label>Product Name *</label>
        <input type="text" name="name" id="edit_name" required maxlength="100">
      </div>

      <div class="form-group">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
          <label style="margin:0;">Category *</label>
          <button type="button" onclick="openModal('categoryGuideModal')" title="View Aquatic Category Guide" aria-label="Category Guide" style="background:none; border:none; cursor:pointer; padding:2px 4px; display:inline-flex; align-items:center; gap:5px; color:var(--primary); font-size:12px; font-weight:700;">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="16" x2="12" y2="12"></line>
              <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>
            <span>Category Guide</span>
          </button>
        </div>
        <select name="category_id" id="edit_category_id" required>
          <?php foreach ($categories as $c) { ?>
            <option value="<?php echo (int)$c['category_id']; ?>"><?php echo e($c['category_name']); ?></option>
          <?php } ?>
        </select>
      </div>

      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
        <div class="form-group">
          <label>Selling Price *</label>
          <input type="number" name="price" id="edit_price" required step="0.01" min="0">
        </div>

        <div class="form-group">
          <label>Stock Quantity *</label>
          <input type="number" name="stock_quantity" id="edit_stock" required min="0">
        </div>
      </div>

      <div class="form-group">
        <label>Discounted Sale Price <span style="font-weight:400; color:var(--muted); font-size:12px;">(Optional — leave blank to remove discount)</span></label>
        <input type="number" name="discounted_price" id="edit_discounted_price" step="0.01" min="0" placeholder="e.g. ₱120.00">
        <div style="font-size:11px; color:#d97706; margin-top:4px;">Products with a discount appear in <strong>Today's Deals</strong> on the public homepage.</div>
      </div>

      <div class="form-group">
        <label>Description</label>
        <textarea name="description" id="edit_description" rows="3"></textarea>
      </div>

      <div class="form-group">
        <label>Status *</label>
        <select name="status" id="edit_status" required>
          <option value="Active">On Sale</option>
          <option value="Inactive">Out of Stock</option>
        </select>
      </div>

      <div class="form-group">
        <label>Replace Image (Optional)</label>
        <input type="file" name="image" id="edit_image" accept="image/jpeg,image/png">
      </div>

      <div class="form-group custom-duration-picker" style="position:relative;">
        <label>Duration of Sale <span style="font-weight:400; color:var(--muted); font-size:12px;">(Optional &mdash; Max 24 hours. Leave blank if not on sale)</span></label>
        
        <input type="hidden" name="sale_duration_hours" id="edit_sale_duration" value="">

        <!-- Trigger Button -->
        <button type="button" id="edit_duration_trigger" onclick="toggleDurationDropdown('editDurationMenu')" 
                style="width:100%; display:flex; justify-content:space-between; align-items:center; padding:10px 14px; border:1px solid var(--border); border-radius:8px; background:#fff; font-size:14px; cursor:pointer; text-align:left; color:var(--text); box-sizing:border-box;">
          <span id="edit_duration_label">No Sale Duration (Leave Blank)</span>
          <span style="font-size:11px; color:var(--muted);"></span>
        </button>

        <!-- Compact Scrollable Dropdown Menu (max-height 150px) -->
        <div id="editDurationMenu" class="custom-scrollable-duration" 
             style="display:none; position:absolute; top:calc(100% - 24px); left:0; right:0; max-height:150px; overflow-y:auto; background:#fff; border:1px solid var(--border); border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,0.18); z-index:1050;">
          <div onclick="selectSaleDuration('edit', '', 'No Sale Duration (Leave Blank)')" 
               style="padding:8px 14px; font-size:13px; cursor:pointer; border-bottom:1px solid #f1f5f9; color:var(--muted);"
               onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
            No Sale Duration (Leave Blank)
          </div>
          <?php for ($h = 1; $h <= 24; $h++) { ?>
            <div onclick="selectSaleDuration('edit', '<?php echo $h; ?>', '<?php echo $h; ?> hour<?php echo $h > 1 ? 's' : ''; ?>')"
                 style="padding:8px 14px; font-size:13px; cursor:pointer; border-bottom:1px solid #f8fafc; color:var(--text);"
                 onmouseover="this.style.background='#f0fdf4'" onmouseout="this.style.background='transparent'">
              <?php echo $h; ?> hour<?php echo $h > 1 ? 's' : ''; ?>
            </div>
          <?php } ?>
        </div>

        <!-- Quick Duration Chips -->
        <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap; margin-top:8px;">
          <span style="font-size:11px; color:var(--muted); font-weight:700;">Quick:</span>
          <button type="button" class="btn btn-sm" onclick="selectSaleDuration('edit', '', 'No Sale Duration (Leave Blank)')" style="padding:2px 8px; font-size:11px; background:#f1f5f9; border:1px solid var(--border); border-radius:6px; color:var(--muted);">None</button>
          <button type="button" class="btn btn-sm" onclick="selectSaleDuration('edit', '1', '1 hour')" style="padding:2px 8px; font-size:11px; background:#f8fafc; border:1px solid var(--border); border-radius:6px;">1h</button>
          <button type="button" class="btn btn-sm" onclick="selectSaleDuration('edit', '3', '3 hours')" style="padding:2px 8px; font-size:11px; background:#f8fafc; border:1px solid var(--border); border-radius:6px;">3h</button>
          <button type="button" class="btn btn-sm" onclick="selectSaleDuration('edit', '6', '6 hours')" style="padding:2px 8px; font-size:11px; background:#f8fafc; border:1px solid var(--border); border-radius:6px;">6h</button>
          <button type="button" class="btn btn-sm" onclick="selectSaleDuration('edit', '12', '12 hours')" style="padding:2px 8px; font-size:11px; background:#f8fafc; border:1px solid var(--border); border-radius:6px;">12h</button>
          <button type="button" class="btn btn-sm" onclick="selectSaleDuration('edit', '24', '24 hours')" style="padding:2px 8px; font-size:11px; background:#f8fafc; border:1px solid var(--border); border-radius:6px;">24h</button>
        </div>

        <div style="font-size:11px; color:var(--muted); margin-top:6px;">
          Sets how long the product will have a discounted price before returning to its original price.
        </div>
      </div>

      <div class="form-actions" style="justify-content:flex-end; margin-top:16px;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('editProductModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT CONFIRMATION MODAL -->
<div class="modal" id="editConfirmModal" aria-hidden="true">
  <div class="modal-content" style="max-width:440px;">
    <div class="modal-header">
      <h2>Save Product Changes</h2>
      <button type="button" class="modal-close-x" onclick="closeModal('editConfirmModal')" aria-label="Close">&times;</button>
    </div>
    <p style="margin-top:10px;">
      Do you want to save the modifications to this product?
    </p>
    <div class="form-actions" style="justify-content:flex-end; margin-top:16px;">
      <button type="button" class="btn btn-secondary" onclick="closeModal('editConfirmModal')">Back</button>
      <button type="button" class="btn btn-primary" onclick="proceedEditProduct()">Save Changes</button>
    </div>
  </div>
</div>

<!-- NO CHANGES DETECTED MODAL -->
<div class="modal" id="noChangesModal" aria-hidden="true">
  <div class="modal-content" style="max-width:400px; text-align:center; padding:24px;">
    <button type="button" class="modal-close-x" onclick="closeModal('noChangesModal')" aria-label="Close">&times;</button>
    <h2 style="font-size:18px; margin:10px 0 8px 0;">No Changes Detected</h2>
    <p class="small-muted" style="margin:0 0 16px 0;">
      No modifications were made to the product details.
    </p>
    <div class="solo-btn-center">
      <button type="button" class="btn btn-primary" onclick="closeModal('noChangesModal')">OK</button>
    </div>
  </div>
</div>

<!-- DELETE CONFIRMATION MODAL -->
<div class="modal" id="deleteProductModal" aria-hidden="true">
  <div class="modal-content" style="max-width:460px; border:1px solid #ffcccc;">
    <div class="modal-header" style="border-bottom:1px solid #fee2e2; padding-bottom:10px; display:flex; justify-content:space-between; align-items:center;">
      <h2 style="color:#b42318; margin:0;">Delete Product</h2>
      <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('deleteProductModal')">Close</button>
    </div>

    <form method="POST" action="<?php echo BASE_URL; ?>/farmer/actions/product_delete.php">
      <input type="hidden" name="product_id" id="delete_product_id" required>

      <div style="padding:14px 0; text-align:center;">
        <div id="delete_thumb_container" style="width:70px; height:70px; border-radius:10px; overflow:hidden; margin:0 auto 10px auto; background:#f2f2f2; border:1px solid #ddd; display:none;">
          <img id="delete_product_img" src="" alt="" style="width:100%;height:100%;object-fit:cover;">
        </div>

        <p style="font-size:15px; margin:0 0 8px 0;">
          Are you sure you want to delete <strong id="delete_product_name"></strong>?
        </p>
        <p class="small-muted" style="color:#b42318; font-size:13px; margin:0;">
          This action is permanent and cannot be undone.
        </p>
      </div>

      <div class="form-actions" style="justify-content:flex-end; border-top:1px solid #fee2e2; padding-top:12px; margin-top:8px;">
        <button type="submit" class="btn btn-outline-danger" style="background:#b42318; color:#fff; width:100%;">Confirm Delete</button>
      </div>
    </form>
  </div>
</div>

<!-- CATEGORY GUIDE MODAL -->
<div class="modal" id="categoryGuideModal" aria-hidden="true">
  <div class="modal-content" style="max-width:540px;">
    <div class="modal-header">
      <div style="display:flex; align-items:center; gap:8px;">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="16" x2="12" y2="12"></line>
          <line x1="12" y1="8" x2="12.01" y2="8"></line>
        </svg>
        <h2 style="margin:0; font-size:18px;">Aquatic Category Guide</h2>
      </div>
      <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('categoryGuideModal')">Close</button>
    </div>

    <div style="padding:16px 0; max-height:460px; overflow-y:auto;">
      <p class="small-muted" style="margin-top:0; margin-bottom:14px; font-size:13px;">
        Use this guide to find which category your aquatic product belongs to:
      </p>

      <div style="display:flex; flex-direction:column; gap:12px;">
        <!-- Fish -->
        <div style="background:#f8fbfd; border:1px solid var(--border); border-radius:10px; padding:12px 16px;">
          <div style="font-weight:800; font-size:15px; color:var(--primary); margin-bottom:6px;">
            Fish (Bangus, Tilapia, Tulingan)
          </div>
          <ul style="margin:0; padding-left:20px; font-size:14px; color:var(--text); line-height:1.6;">
            <li>Bangus (Milkfish)</li>
            <li>Tilapia</li>
            <li>Tulingan / Tambakol</li>
            <li>Galunggong, Maya-maya, Lapu-lapu</li>
          </ul>
        </div>

        <!-- Shrimps / Crabs -->
        <div style="background:#f8fbfd; border:1px solid var(--border); border-radius:10px; padding:12px 16px;">
          <div style="font-weight:800; font-size:15px; color:var(--primary); margin-bottom:6px;">
            Shrimps / Crabs (Hipon, Alimango)
          </div>
          <ul style="margin:0; padding-left:20px; font-size:14px; color:var(--text); line-height:1.6;">
            <li>Hipon (White / Tiger Shrimp)</li>
            <li>Sugpo (Prawn)</li>
            <li>Alimango (Mud Crab)</li>
            <li>Alimasag (Blue Crab)</li>
          </ul>
        </div>

        <!-- Shellfish -->
        <div style="background:#f8fbfd; border:1px solid var(--border); border-radius:10px; padding:12px 16px;">
          <div style="font-weight:800; font-size:15px; color:var(--primary); margin-bottom:6px;">
            Shellfish (Tahong, Talaba)
          </div>
          <ul style="margin:0; padding-left:20px; font-size:14px; color:var(--text); line-height:1.6;">
            <li>Tahong (Mussels)</li>
            <li>Talaba (Oysters)</li>
            <li>Halaan (Clams)</li>
            <li>Tulya</li>
          </ul>
        </div>

        <!-- Squid -->
        <div style="background:#f8fbfd; border:1px solid var(--border); border-radius:10px; padding:12px 16px;">
          <div style="font-weight:800; font-size:15px; color:var(--primary); margin-bottom:6px;">
            Squid (Pusit)
          </div>
          <ul style="margin:0; padding-left:20px; font-size:14px; color:var(--text); line-height:1.6;">
            <li>Pusit Lumot</li>
            <li>Pusit Bisaya</li>
            <li>Dried Pusit / Daing na Pusit</li>
          </ul>
        </div>

        <!-- Octopus -->
        <div style="background:#f8fbfd; border:1px solid var(--border); border-radius:10px; padding:12px 16px;">
          <div style="font-weight:800; font-size:15px; color:var(--primary); margin-bottom:6px;">
            Octopus (Pugita)
          </div>
          <ul style="margin:0; padding-left:20px; font-size:14px; color:var(--text); line-height:1.6;">
            <li>Fresh Pugita (Baby / Regular Octopus)</li>
            <li>Dried Pugita</li>
          </ul>
        </div>

        <!-- Seaweeds -->
        <div style="background:#f8fbfd; border:1px solid var(--border); border-radius:10px; padding:12px 16px;">
          <div style="font-weight:800; font-size:15px; color:var(--primary); margin-bottom:6px;">
            Seaweeds (Lato, Guso)
          </div>
          <ul style="margin:0; padding-left:20px; font-size:14px; color:var(--text); line-height:1.6;">
            <li>Lato (Sea Grapes)</li>
            <li>Guso (Eucheuma)</li>
          </ul>
        </div>
      </div>
    </div>

    <div class="form-actions" style="justify-content:flex-end; border-top:1px solid var(--border); padding-top:12px; margin-top:10px;">
      <button type="button" class="btn btn-primary btn-sm" onclick="closeModal('categoryGuideModal')">Got it</button>
    </div>
  </div>
</div>

<!-- PROMOTE PRODUCT MODAL (3-Step Wizard) -->
<div class="modal" id="promoteProductModal" aria-hidden="true">
  <div class="modal-content" style="max-width:580px; max-height:92vh; overflow-y:auto; display:flex; flex-direction:column; padding:22px;">
    
    <!-- Modal Header -->
    <div class="modal-header" style="border-bottom:1px solid var(--border); padding-bottom:12px; margin-bottom:12px;">
      <div style="display:flex; align-items:center; gap:10px;">
        <span style="font-size:22px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; width:38px; height:38px; display:inline-flex; align-items:center; justify-content:center;">🚀</span>
        <div>
          <h2 style="margin:0; font-size:18px; color:var(--text);" id="promo_wizard_header_title">Step 1 of 3: Choose Promotion Package</h2>
          <div class="small-muted" style="font-size:12px;" id="promo_wizard_header_subtitle">Confirm listing and boost duration</div>
        </div>
      </div>
      <button type="button" class="modal-close-x" onclick="closeModal('promoteProductModal')" aria-label="Close">&times;</button>
    </div>

    <!-- Wizard Step Progress Indicator -->
    <div style="display:flex; align-items:center; justify-content:center; gap:8px; margin-bottom:18px;">
      <div id="step_dot_1" class="promo-step-badge active">
        <span>1</span> <span>Package</span>
      </div>
      <div id="step_line_1" style="width:28px; height:2px; background:#cbd5e1; transition:background .2s;"></div>
      <div id="step_dot_2" class="promo-step-badge">
        <span>2</span> <span>Scan & Pay</span>
      </div>
      <div id="step_line_2" style="width:28px; height:2px; background:#cbd5e1; transition:background .2s;"></div>
      <div id="step_dot_3" class="promo-step-badge">
        <span>3</span> <span>Verify</span>
      </div>
    </div>

    <!-- Pending Promotion Alert (Shown if product already pending) -->
    <div id="promo_already_pending_alert" style="display:none; background:#fef3c7; border:1px solid #fde68a; border-radius:10px; padding:12px 14px; margin-bottom:14px; color:#92400e; font-size:13px; line-height:1.5;">
      <strong>Promotion Already Pending:</strong> You have submitted a promotion request for this product. The Administrator is currently verifying your GCash payment screenshot.
    </div>

    <!-- Active Promotion Alert (Shown if currently active) -->
    <div id="promo_already_active_alert" style="display:none; background:#ecfdf5; border:1px solid #a7f3d0; border-radius:10px; padding:12px 14px; margin-bottom:14px; color:#065f46; font-size:13px; line-height:1.5;">
      <strong>Currently Promoted:</strong> This listing is currently boosted in the Home banner and pinned in Market search until <strong id="promo_active_end_text"></strong>.
    </div>

    <!-- Promotion Form -->
    <form method="POST" action="<?php echo BASE_URL; ?>/farmer/actions/promote_product.php" enctype="multipart/form-data" id="promoteProductForm" onsubmit="return handlePromoteSubmit(event)" style="display:flex; flex-direction:column; flex:1;">
      <input type="hidden" name="product_id" id="promo_product_id">
      <input type="hidden" name="package_id" id="promo_package_id_input" value="<?php echo !empty($promo_packages) ? (int)$promo_packages[0]['id'] : 0; ?>">
      <input type="hidden" name="package" id="promo_package_input" value="<?php echo !empty($promo_packages) ? e($promo_packages[0]['package_name']) : ''; ?>">

      <!-- ==================== STEP 1: PRODUCT INFO & PACKAGE SELECTION ==================== -->
      <div id="promo_wizard_step_1" class="wizard-step-container">
        <!-- Top Section (Product Preview) -->
        <div style="background:#f8fafc; border:1.5px solid var(--border); border-radius:12px; padding:12px 16px; margin-bottom:18px; display:flex; align-items:center; gap:12px;">
          <div id="promo_product_img_box" style="width:48px; height:48px; border-radius:10px; overflow:hidden; background:#e2e8f0; display:flex; align-items:center; justify-content:center; flex-shrink:0; border:1px solid #cbd5e1;">
            <img id="promo_product_img" src="" alt="" style="width:100%; height:100%; object-fit:cover; display:none;">
            <span id="promo_product_icon" style="font-size:24px;">🐟</span>
          </div>
          <div style="flex:1;">
            <div style="font-size:11px; font-weight:800; color:#0284c7; text-transform:uppercase; letter-spacing:0.5px;">Product Listing</div>
            <div style="font-weight:900; font-size:16px; color:var(--text);" id="promo_product_preview_text">Promoting: Bangus (50kg)</div>
            <div class="small-muted" style="font-size:12px;" id="promo_product_price_sub">Base Price: ₱0.00</div>
          </div>
        </div>

        <!-- Middle Section (Selection): Dynamic Active Promotion Packages -->
        <div style="margin-bottom:20px;">
          <label style="font-weight:800; font-size:13px; color:var(--text); display:block; margin-bottom:10px;">
            Choose Duration:
          </label>
          <?php if (empty($promo_packages)) { ?>
            <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:10px; padding:16px; text-align:center; color:#64748b; font-size:13.5px;">
              No promotion packages are currently active. Please contact the administrator.
            </div>
          <?php } else { ?>
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:14px;">
              <?php foreach ($promo_packages as $idx => $pkg) { 
                  $isSelected = ($idx === 0);
              ?>
                <div class="promo-pkg-card <?php echo $isSelected ? 'selected' : ''; ?>" 
                     id="pkg_card_<?php echo $pkg['id']; ?>" 
                     onclick="selectPromoPackage(<?php echo (int)$pkg['id']; ?>)"
                     style="border:<?php echo $isSelected ? '2.5px solid #0284c7' : '2px solid var(--border)'; ?>; background:<?php echo $isSelected ? '#f0f9ff' : '#fff'; ?>; border-radius:14px; padding:16px; cursor:pointer; transition:all .2s; <?php echo $isSelected ? 'box-shadow:0 3px 10px rgba(2,132,199,0.12);' : ''; ?> position:relative;">
                  <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="background:<?php echo $idx === 0 ? '#0284c7' : '#64748b'; ?>; color:#fff; font-size:10px; font-weight:900; padding:2px 7px; border-radius:6px; text-transform:uppercase;">
                      <?php echo (int)$pkg['duration_days']; ?> <?php echo $pkg['duration_days'] == 1 ? 'Day Boost' : 'Days Boost'; ?>
                    </span>
                    <input type="radio" name="pkg_radio" id="radio_pkg_<?php echo $pkg['id']; ?>" <?php echo $isSelected ? 'checked' : ''; ?> style="accent-color:#0284c7; width:20px; height:20px; margin:0; cursor:pointer;">
                  </div>
                  <div style="font-weight:900; font-size:15px; margin-top:8px; color:var(--text);"><?php echo e($pkg['package_name']); ?></div>
                  <div style="font-size:22px; font-weight:900; color:#0369a1; margin:6px 0 4px 0;">₱<?php echo number_format($pkg['price'], 2); ?></div>
                  <div class="small-muted" style="font-size:12px; line-height:1.4;">
                    Boost for <?php echo (int)$pkg['duration_days']; ?> <?php echo $pkg['duration_days'] == 1 ? 'day' : 'days'; ?> in Home banner & pinned on Market search.
                  </div>
                </div>
              <?php } ?>
            </div>
          <?php } ?>
        </div>

        <!-- Step 1 Buttons -->
        <div class="form-actions" style="justify-content:space-between; border-top:1px solid var(--border); padding-top:16px; margin-top:auto;">
          <button type="button" class="btn btn-secondary" onclick="closeModal('promoteProductModal')" style="font-weight:700; padding:10px 20px;">
            Cancel
          </button>
          <button type="button" id="btnNextStep1" class="btn btn-primary" onclick="goToPromoStep(2)" <?php echo empty($promo_packages) ? 'disabled' : ''; ?> style="font-weight:800; padding:10px 26px; font-size:14px; display:inline-flex; align-items:center; gap:6px;">
            <span>Next</span>
          </button>
        </div>
      </div>

      <!-- ==================== STEP 2: PAYMENT & QR SCANNING ==================== -->
      <div id="promo_wizard_step_2" class="wizard-step-container" style="display:none;">
        <!-- Top Text -->
        <div style="text-align:center; margin-bottom:14px; background:#f0f9ff; border:1px solid #bae6fd; border-radius:10px; padding:10px 14px;">
          <div style="font-size:14.5px; color:#0c4a6e; font-weight:700; line-height:1.5;">
            Please pay the exact amount of <span id="step2_amount_badge" style="background:#fef3c7; color:#b45309; border:1px solid #fde68a; padding:2px 8px; border-radius:6px; font-weight:900; font-size:16px;">₱<?php echo number_format(!empty($promo_packages) ? $promo_packages[0]['price'] : 0, 2); ?></span> to the Admin's GCash account below:
          </div>
        </div>

        <!-- Center Section: Massive QR Code Box (Takes up 50%+ of space) -->
        <div class="qr-code-huge-box" style="width:100%; min-height:280px; max-height:330px; background:#ffffff; border:2.5px solid #0284c7; border-radius:16px; padding:14px; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; box-shadow:0 8px 24px rgba(2,132,199,0.15); margin-bottom:16px;">
          <?php if (!empty($promo_settings['gcash_qr_image']) && file_exists(__DIR__ . '/../' . $promo_settings['gcash_qr_image'])) { ?>
            <img src="<?php echo BASE_URL . '/' . e($promo_settings['gcash_qr_image']); ?>" alt="Admin GCash QR Code" style="max-width:100%; max-height:260px; object-fit:contain; border-radius:10px;">
          <?php } else { ?>
            <svg width="140" height="140" viewBox="0 0 24 24" fill="none" stroke="#007dfe" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="3" width="7" height="7"></rect>
              <rect x="14" y="3" width="7" height="7"></rect>
              <rect x="14" y="14" width="7" height="7"></rect>
              <rect x="3" y="14" width="7" height="7"></rect>
              <line x1="7" y1="7" x2="7.01" y2="7"></line>
              <line x1="17" y1="7" x2="17.01" y2="7"></line>
              <line x1="17" y1="17" x2="17.01" y2="17"></line>
              <line x1="7" y1="17" x2="7.01" y2="17"></line>
            </svg>
            <div style="font-size:13px; font-weight:800; color:#007dfe; margin-top:8px;">SCAN GCASH QR TO PAY</div>
          <?php } ?>
          <div style="font-size:12px; font-weight:700; color:#64748b; margin-top:8px;">
            Open GCash app and Tap "Scan QR"
          </div>
        </div>

        <!-- Bottom Section: Manual Entry Details with Copy Button -->
        <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:12px; padding:12px 16px; margin-bottom:18px;">
          <div class="small-muted" style="font-size:11px; font-weight:800; text-transform:uppercase; margin-bottom:4px;">Or send via GCash Number:</div>
          <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
            <div>
              <div style="font-size:12px; color:#475569;">Name: <strong style="color:var(--text);" id="promo_gcash_name_text"><?php echo e($promo_settings['gcash_name']); ?></strong></div>
              <div style="font-size:18px; font-weight:900; color:#0369a1; font-family:monospace; letter-spacing:1px;" id="promo_gcash_num_text"><?php echo e($promo_settings['gcash_number']); ?></div>
            </div>
            <button type="button" id="copyGcashBtn" class="btn btn-outline-primary btn-sm" onclick="copyGcashNumber()" style="font-weight:700; padding:7px 14px; border-radius:8px; display:inline-flex; align-items:center; gap:6px;">
              <span></span> <span id="copyGcashBtnText">Copy</span>
            </button>
          </div>
        </div>

        <!-- Step 2 Buttons -->
        <div class="form-actions" style="justify-content:space-between; border-top:1px solid var(--border); padding-top:16px; margin-top:auto;">
          <button type="button" class="btn btn-secondary" onclick="goToPromoStep(1)" style="font-weight:700; padding:10px 18px;">
            Back
          </button>
          <button type="button" class="btn btn-primary" onclick="goToPromoStep(3)" style="font-weight:800; padding:10px 22px; font-size:14px; display:inline-flex; align-items:center; gap:6px;">
            <span>Next</span> <span></span>
          </button>
        </div>
      </div>

      <!-- ==================== STEP 3: UPLOAD PROOF OF PAYMENT ==================== -->
      <div id="promo_wizard_step_3" class="wizard-step-container" style="display:none;">
        <!-- Top Text -->
        <div style="margin-bottom:14px;">
          <div style="font-size:13.5px; color:#334155; line-height:1.5;">
            Upload a screenshot of your GCash receipt and enter the Reference Number so the Admin can approve your promotion quickly.
          </div>
        </div>

        <!-- Form Field 1: Large Image Upload Box -->
        <div style="margin-bottom:16px;">
          <label style="font-weight:800; font-size:13px; color:var(--text); display:block; margin-bottom:6px;">
            Step 1: Upload GCash Receipt Screenshot *
          </label>
          
          <div id="receiptDropArea" onclick="document.getElementById('promo_receipt_input').click();"
               style="border:2.5px dashed #0284c7; border-radius:14px; padding:22px 16px; text-align:center; background:#f0f9ff; cursor:pointer; transition:all .2s;">
            <div id="receiptPlaceholder">
              <div style="font-size:36px; margin-bottom:6px;"></div>
              <div style="font-size:14.5px; font-weight:800; color:#0369a1;">Tap here to upload screenshot</div>
              <div class="small-muted" style="font-size:11.5px; margin-top:4px;">Accepts JPG, PNG, or WebP (Max 5MB)</div>
            </div>

            <div id="receiptPreviewContainer" style="display:none; text-align:center;">
              <img id="receiptPreviewImg" src="" alt="GCash Receipt Preview" style="max-height:170px; max-width:100%; border-radius:8px; border:1px solid var(--border); box-shadow:0 4px 12px rgba(0,0,0,0.1);">
              <div style="margin-top:8px; font-size:12px; font-weight:800; color:#0284c7;">Tap here to change receipt screenshot</div>
            </div>
          </div>

          <input type="file" name="receipt_image" id="promo_receipt_input" accept="image/*" style="display:none;" onchange="previewPromoReceipt(this)">
        </div>

        <!-- Form Field 2: Reference Number Box -->
        <div style="margin-bottom:20px;">
          <label for="promo_gcash_ref" style="font-weight:800; font-size:13px; color:var(--text); display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
            <span>Step 2: Enter 13-digit GCash Ref No.</span>
            <span style="font-size:11px; font-weight:700; color:#0284c7; background:rgba(2,132,199,0.1); padding:2px 8px; border-radius:999px;">Recommended</span>
          </label>
          <input type="text" name="gcash_reference_number" id="promo_gcash_ref" 
                 placeholder="e.g. 1002 9384 1029" 
                 maxlength="35"
                 style="width:100%; font-size:15px; font-weight:800; font-family:'Courier New', Courier, monospace; letter-spacing:1px; padding:12px 14px; border:2px solid #cbd5e1; border-radius:8px; background:#ffffff; color:#0f172a; box-sizing:border-box;">
          <div class="small-muted" style="font-size:11.5px; margin-top:5px;">
            Tip: Entering your GCash Ref No. helps the Administrator verify and approve your boost instantly.
          </div>
        </div>

        <!-- Step 3 Buttons -->
        <div class="form-actions" style="justify-content:space-between; border-top:1px solid var(--border); padding-top:16px; margin-top:auto;">
          <button type="button" class="btn btn-secondary" onclick="goToPromoStep(2)" style="font-weight:700; padding:10px 18px;">
            Back
          </button>
          <button type="submit" id="promoSubmitBtn" class="btn" style="background:#16a34a; color:#fff; border:none; font-weight:800; padding:11px 26px; border-radius:8px; box-shadow:0 2px 8px rgba(22,163,74,0.3); font-size:15px; display:inline-flex; align-items:center; gap:6px;">
            <span>Submit Request</span>
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<style>
.promo-step-badge {
  font-size: 12px;
  font-weight: 800;
  padding: 4px 12px;
  border-radius: 999px;
  background: #f1f5f9;
  color: #64748b;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  transition: all .2s ease;
}
.promo-step-badge.active {
  background: #0284c7 !important;
  color: #ffffff !important;
  box-shadow: 0 2px 6px rgba(2,132,199,0.35);
}
.promo-step-badge.completed {
  background: #ecfdf5 !important;
  color: #059669 !important;
  border: 1px solid #a7f3d0;
}

/* Full-Screen Mobile Modal Optimization */
@media (max-width: 640px) {
  #promoteProductModal .modal-content {
    width: 100vw !important;
    max-width: 100vw !important;
    height: 100vh !important;
    max-height: 100vh !important;
    border-radius: 0 !important;
    margin: 0 !important;
    padding: 16px !important;
    display: flex !important;
    flex-direction: column !important;
  }
  #promoteProductModal .wizard-step-container {
    flex: 1 !important;
    display: flex !important;
    flex-direction: column !important;
    justify-content: space-between !important;
    overflow-y: auto !important;
  }
  #promoteProductModal .qr-code-huge-box {
    height: 48vh !important;
    max-height: 48vh !important;
    min-height: 240px !important;
  }
  #promoteProductModal .qr-code-huge-box img,
  #promoteProductModal .qr-code-huge-box svg {
    max-height: 40vh !important;
  }
}
</style>

<style>
.boost-tier-btn.selected {
  border-color: var(--primary) !important;
  background: #f0f9ff !important;
}
.boost-tier-btn:hover {
  border-color: var(--primary);
}
</style>

<script>
var editOriginalState = {};

function toggleDurationDropdown(menuId) {
  var menu = document.getElementById(menuId);
  if (!menu) return;
  var isShown = menu.style.display === 'block';
  document.querySelectorAll('.custom-scrollable-duration').forEach(function(m) { m.style.display = 'none'; });
  menu.style.display = isShown ? 'none' : 'block';
}

function selectSaleDuration(prefix, val, label) {
  var inputEl = document.getElementById(prefix + '_sale_duration');
  var labelEl = document.getElementById(prefix + '_duration_label');
  if (inputEl) inputEl.value = val;
  if (labelEl) labelEl.innerText = label;
  var menu = document.getElementById(prefix + 'DurationMenu');
  if (menu) menu.style.display = 'none';
}

// Close duration menu when clicking outside
document.addEventListener('click', function(e) {
  if (!e.target.closest('.custom-duration-picker') && !e.target.closest('.custom-scrollable-duration')) {
    document.querySelectorAll('.custom-scrollable-duration').forEach(function(m) { m.style.display = 'none'; });
  }
});

function openAddModal() {
  selectSaleDuration('add', '', 'No Sale Duration (Leave Blank)');
  openModal('addProductModal');
}

function handleAddSubmit(e) {
  e.preventDefault();
  openModal('addConfirmModal');
  return false;
}

function proceedAddProduct() {
  closeModal('addConfirmModal');
  document.getElementById('addProductForm').submit();
}

function populateEditModal(btn) {
  if (!btn || btn.disabled) return;

  var id = btn.dataset.id || '';
  var name = btn.dataset.name || '';
  var catId = btn.dataset.categoryId || '';
  var desc = btn.dataset.description || '';
  var price = btn.dataset.price || '';
  var stock = btn.dataset.stock || '';
  var status = btn.dataset.status || 'Active';
  var saleDuration = btn.dataset.saleDuration || '';
  var discountedPrice = btn.dataset.discountedPrice || '';

  document.getElementById('edit_product_id').value = id;
  document.getElementById('edit_name').value = name;
  document.getElementById('edit_category_id').value = catId;
  document.getElementById('edit_description').value = desc;
  document.getElementById('edit_price').value = price;
  document.getElementById('edit_stock').value = stock;
  document.getElementById('edit_status').value = status;
  document.getElementById('edit_image').value = '';
  document.getElementById('edit_discounted_price').value = discountedPrice;
  
  var saleLabel = saleDuration ? (saleDuration + ' hour' + (parseInt(saleDuration, 10) > 1 ? 's' : '')) : 'No Sale Duration (Leave Blank)';
  selectSaleDuration('edit', saleDuration, saleLabel);

  editOriginalState = {
    name: name,
    category_id: catId,
    description: desc,
    price: price,
    stock: stock,
    status: status,
    sale_duration: saleDuration,
    discounted_price: discountedPrice
  };

  openModal('editProductModal');
}

function handleEditSubmit(e) {
  e.preventDefault();
  var name = document.getElementById('edit_name').value;
  var catId = document.getElementById('edit_category_id').value;
  var desc = document.getElementById('edit_description').value;
  var price = document.getElementById('edit_price').value;
  var stock = document.getElementById('edit_stock').value;
  var status = document.getElementById('edit_status').value;
  var imageFile = document.getElementById('edit_image').files.length > 0;
  var saleDuration = document.getElementById('edit_sale_duration').value;
  var discountedPrice = document.getElementById('edit_discounted_price').value;

  var hasChanges = imageFile ||
    (name !== editOriginalState.name) ||
    (catId !== editOriginalState.category_id) ||
    (desc !== editOriginalState.description) ||
    (price !== editOriginalState.price) ||
    (stock !== editOriginalState.stock) ||
    (status !== editOriginalState.status) ||
    (saleDuration !== editOriginalState.sale_duration) ||
    (discountedPrice !== editOriginalState.discounted_price);

  if (!hasChanges) {
    openModal('noChangesModal');
    return false;
  }

  openModal('editConfirmModal');
  return false;
}

function proceedEditProduct() {
  closeModal('editConfirmModal');
  document.getElementById('editProductForm').submit();
}

function populateDeleteModal(btn) {
  if (!btn || btn.disabled) return;
  var id = btn.dataset.id || '';
  var name = btn.dataset.name || '';
  var img = btn.dataset.img || '';

  document.getElementById('delete_product_id').value = id;
  document.getElementById('delete_product_name').textContent = name;

  var thumbContainer = document.getElementById('delete_thumb_container');
  var imgEl = document.getElementById('delete_product_img');
  if (img && thumbContainer && imgEl) {
    imgEl.src = img;
    thumbContainer.style.display = 'block';
  } else if (thumbContainer) {
    thumbContainer.style.display = 'none';
  }

  openModal('deleteProductModal');
}

var PROMO_PACKAGES = <?php echo json_encode($promo_packages); ?>;

var currentPromoStep = 1;

function goToPromoStep(step) {
  if (step < 1 || step > 3) return;

  // Validate step 1 before proceeding
  if (currentPromoStep === 1 && step > 1) {
    var pkgIdInput = document.getElementById('promo_package_id_input');
    if (!pkgIdInput || !pkgIdInput.value || pkgIdInput.value === '0') {
      alert('Please select a promotion package before proceeding.');
      return;
    }
  }

  // Update step visibility
  document.getElementById('promo_wizard_step_1').style.display = (step === 1) ? 'block' : 'none';
  document.getElementById('promo_wizard_step_2').style.display = (step === 2) ? 'block' : 'none';
  document.getElementById('promo_wizard_step_3').style.display = (step === 3) ? 'block' : 'none';

  currentPromoStep = step;

  // Update Modal Header Title & Subtitle
  var titleEl = document.getElementById('promo_wizard_header_title');
  var subEl = document.getElementById('promo_wizard_header_subtitle');
  if (step === 1) {
    if (titleEl) titleEl.textContent = 'Step 1 of 3: Choose Promotion Package';
    if (subEl) subEl.textContent = 'Confirm listing and boost duration';
  } else if (step === 2) {
    if (titleEl) titleEl.textContent = 'Step 2 of 3: Scan & Pay';
    if (subEl) subEl.textContent = 'Scan Admin GCash QR or send via mobile number';
  } else if (step === 3) {
    if (titleEl) titleEl.textContent = 'Step 3 of 3: Verify Payment';
    if (subEl) subEl.textContent = 'Upload receipt screenshot and enter reference number';
  }

  // Update Progress Stepper Badges
  var dot1 = document.getElementById('step_dot_1');
  var dot2 = document.getElementById('step_dot_2');
  var dot3 = document.getElementById('step_dot_3');
  var line1 = document.getElementById('step_line_1');
  var line2 = document.getElementById('step_line_2');

  if (dot1) dot1.className = 'promo-step-badge ' + (step === 1 ? 'active' : 'completed');
  if (dot2) dot2.className = 'promo-step-badge ' + (step === 2 ? 'active' : (step > 2 ? 'completed' : ''));
  if (dot3) dot3.className = 'promo-step-badge ' + (step === 3 ? 'active' : '');

  if (line1) line1.style.background = (step >= 2) ? '#0284c7' : '#cbd5e1';
  if (line2) line2.style.background = (step >= 3) ? '#0284c7' : '#cbd5e1';

  // Scroll to top of modal content
  var modalBox = document.querySelector('#promoteProductModal .modal-content');
  if (modalBox) modalBox.scrollTop = 0;
}

function selectPromoPackage(pkgId, price, days, name) {
  if (PROMO_PACKAGES && PROMO_PACKAGES.length > 0) {
    var found = PROMO_PACKAGES.find(function(p) { return p.id == pkgId; });
    if (found) {
      if (price === undefined || price === null) price = found.price;
      if (days === undefined || days === null) days = found.duration_days;
      if (!name) name = found.package_name;
    }
  }

  var pkgIdInput = document.getElementById('promo_package_id_input');
  var pkgInput = document.getElementById('promo_package_input');
  if (pkgIdInput) pkgIdInput.value = pkgId;
  if (pkgInput) pkgInput.value = name || '';

  // Unhighlight all package cards and check the selected radio
  document.querySelectorAll('.promo-pkg-card').forEach(function(card) {
    card.style.borderColor = 'var(--border)';
    card.style.background = '#fff';
    card.style.boxShadow = 'none';
  });
  document.querySelectorAll('input[name="pkg_radio"]').forEach(function(rad) {
    rad.checked = false;
  });

  var selectedCard = document.getElementById('pkg_card_' + pkgId);
  var selectedRad = document.getElementById('radio_pkg_' + pkgId);
  if (selectedCard) {
    selectedCard.style.borderColor = '#0284c7';
    selectedCard.style.background = '#f0f9ff';
    selectedCard.style.boxShadow = '0 3px 10px rgba(2,132,199,0.12)';
  }
  if (selectedRad) {
    selectedRad.checked = true;
  }

  var formattedPrice = '₱' + parseFloat(price || 0).toFixed(2);
  var badge2 = document.getElementById('step2_amount_badge');
  if (badge2) badge2.textContent = formattedPrice;

  var nextBtn1 = document.getElementById('btnNextStep1');
  if (nextBtn1) nextBtn1.disabled = false;
}

function copyGcashNumber() {
  var numEl = document.getElementById('promo_gcash_num_text');
  var textToCopy = numEl ? numEl.textContent.trim() : <?php echo json_encode($promo_settings['gcash_number']); ?>;

  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(textToCopy).then(showCopySuccess, fallbackCopy);
  } else {
    fallbackCopy();
  }

  function fallbackCopy() {
    var textArea = document.createElement('textarea');
    textArea.value = textToCopy;
    textArea.style.position = 'fixed';
    textArea.style.opacity = '0';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    try {
      document.execCommand('copy');
      showCopySuccess();
    } catch (err) {
      alert('GCash Number: ' + textToCopy);
    }
    document.body.removeChild(textArea);
  }

  function showCopySuccess() {
    var btnText = document.getElementById('copyGcashBtnText');
    var btn = document.getElementById('copyGcashBtn');
    if (btnText) btnText.textContent = 'Copied!';
    if (btn) {
      btn.style.borderColor = '#10b981';
      btn.style.color = '#10b981';
    }
    setTimeout(function() {
      if (btnText) btnText.textContent = 'Copy';
      if (btn) {
        btn.style.borderColor = '';
        btn.style.color = '';
      }
    }, 2000);
  }
}

function openPromoteProductModal(btn) {
  if (!btn || btn.disabled) return;
  var id = btn.dataset.id || '';
  var name = btn.dataset.name || '';
  var stock = btn.dataset.stock || '';
  var price = btn.dataset.price ? parseFloat(btn.dataset.price).toFixed(2) : '0.00';
  var img = btn.dataset.img || '';
  var promoStatus = btn.dataset.promoStatus || 'None';
  var promoPlan = btn.dataset.promoPlan || '';
  var promoEnd = btn.dataset.promoEnd || '';

  document.getElementById('promo_product_id').value = id;
  
  // Format Product Preview: Bangus (50kg)
  var previewStr = 'Promoting: ' + name;
  if (stock) previewStr += ' (' + stock + 'kg)';
  document.getElementById('promo_product_preview_text').textContent = previewStr;
  document.getElementById('promo_product_price_sub').textContent = 'Base Price: ₱' + price;

  // Product Image thumbnail
  var imgEl = document.getElementById('promo_product_img');
  var iconEl = document.getElementById('promo_product_icon');
  if (img) {
    imgEl.src = img;
    imgEl.style.display = 'block';
    iconEl.style.display = 'none';
  } else {
    imgEl.style.display = 'none';
    iconEl.style.display = 'inline';
  }

  // Handle status alerts
  var pendingAlert = document.getElementById('promo_already_pending_alert');
  var activeAlert = document.getElementById('promo_already_active_alert');
  var submitBtn = document.getElementById('promoSubmitBtn');

  if (pendingAlert) pendingAlert.style.display = 'none';
  if (activeAlert) activeAlert.style.display = 'none';
  if (submitBtn) {
    submitBtn.disabled = false;
    submitBtn.innerHTML = '<span>🚀</span> <span>Submit Request</span>';
  }

  if (promoStatus === 'Pending') {
    if (pendingAlert) pendingAlert.style.display = 'block';
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span>⏳</span> <span>Approval Pending</span>';
    }
  } else if (promoStatus === 'Active') {
    if (activeAlert) activeAlert.style.display = 'block';
    var endTextEl = document.getElementById('promo_active_end_text');
    if (endTextEl) endTextEl.textContent = promoEnd || 'Active Period';
  }

  // Reset to Step 1
  goToPromoStep(1);

  // Reset package selection to first available active package
  if (PROMO_PACKAGES && PROMO_PACKAGES.length > 0) {
    var firstPkg = PROMO_PACKAGES[0];
    selectPromoPackage(firstPkg.id, firstPkg.price, firstPkg.duration_days, firstPkg.package_name);
  }

  // Reset file input and preview
  var fileInput = document.getElementById('promo_receipt_input');
  if (fileInput) fileInput.value = '';
  var prevContainer = document.getElementById('receiptPreviewContainer');
  if (prevContainer) prevContainer.style.display = 'none';
  var ph = document.getElementById('receiptPlaceholder');
  if (ph) ph.style.display = 'block';
  var refInput = document.getElementById('promo_gcash_ref');
  if (refInput) refInput.value = '';

  openModal('promoteProductModal');
}

function previewPromoReceipt(input) {
  if (input.files && input.files[0]) {
    var file = input.files[0];
    if (file.size > 5 * 1024 * 1024) {
      alert('The selected receipt file exceeds the 5MB size limit.');
      input.value = '';
      return;
    }
    var reader = new FileReader();
    reader.onload = function(e) {
      var img = document.getElementById('receiptPreviewImg');
      if (img) img.src = e.target.result;
      var prev = document.getElementById('receiptPreviewContainer');
      if (prev) prev.style.display = 'block';
      var ph = document.getElementById('receiptPlaceholder');
      if (ph) ph.style.display = 'none';
    };
    reader.readAsDataURL(file);
  }
}

function handlePromoteSubmit(e) {
  var fileInput = document.getElementById('promo_receipt_input');
  if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
    alert('Please upload a screenshot of your GCash receipt before submitting.');
    if (e && e.preventDefault) e.preventDefault();
    return false;
  }
  return true;
}
</script>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
