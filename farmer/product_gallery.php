<?php
/**
 * SeaLink Web Application
 * File: /farmer/product_gallery.php
 * Note: Consolidated to unified /farmer_store.php
 */
require_once __DIR__ . '/../includes/auth_check.php';
check_access('farmer');
require_once __DIR__ . '/../config/app.php';

$farmer_id = (int)($_SESSION['user_id'] ?? 0);
$return = trim((string)($_GET['return'] ?? '/farmer/manage_products.php'));
header('Location: ' . BASE_URL . '/farmer_store.php?farmer_id=' . $farmer_id . '&return=' . urlencode($return));
exit;

/* ========== FARMER PROFILE SUMMARY ========== */
$stmt = mysqli_prepare($conn, "
    SELECT farmer_id, username, full_name, profile_image, address, contact_number, facebook_account, permit_number, verification_status, created_at
    FROM farmer_tbl 
    WHERE farmer_id = ? 
    LIMIT 1
");
mysqli_stmt_bind_param($stmt, "i", $farmer_id);
mysqli_stmt_execute($stmt);
$farmer = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

/* Overall rating across all products */
$stmt = mysqli_prepare($conn, "
  SELECT COALESCE(AVG(f.rating), 0) AS avg_rating, COUNT(f.feedback_id) AS total_reviews
  FROM feedback_tbl f
  JOIN product_tbl p ON p.product_id = f.product_id
  WHERE p.farmer_id = ?
");
mysqli_stmt_bind_param($stmt, "i", $farmer_id);
mysqli_stmt_execute($stmt);
$rating_res = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$overall_rating = (float)($rating_res['avg_rating'] ?? 0);
$total_reviews = (int)($rating_res['total_reviews'] ?? 0);
mysqli_stmt_close($stmt);

/* Total sold kg across completed orders */
$stmt = mysqli_prepare($conn, "
  SELECT COALESCE(SUM(oi.quantity), 0) AS sold_kg
  FROM order_item_tbl oi
  JOIN order_tbl o ON o.order_id = oi.order_id
  JOIN product_tbl p ON p.product_id = oi.product_id
  WHERE p.farmer_id = ? AND o.order_status = 'Completed'
");
mysqli_stmt_bind_param($stmt, "i", $farmer_id);
mysqli_stmt_execute($stmt);
$total_sold_kg = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['sold_kg'] ?? 0);
mysqli_stmt_close($stmt);

/* ========== PRODUCTS LIST ========== */
$stmt = mysqli_prepare($conn, "
  SELECT
    p.product_id, p.name, p.price, p.discounted_price, p.selling_deadline, p.stock_quantity, p.image_url, p.status,
    p.is_promoted, p.is_boosted,
    COALESCE(AVG(f.rating), 0) AS avg_rating,
    COUNT(f.feedback_id) AS review_count
  FROM product_tbl p
  LEFT JOIN feedback_tbl f ON f.product_id = p.product_id
  WHERE p.farmer_id = ? AND p.status = 'Active'
  GROUP BY p.product_id
  ORDER BY p.created_at DESC
");
mysqli_stmt_bind_param($stmt, "i", $farmer_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$products = [];
while ($row = mysqli_fetch_assoc($res)) $products[] = $row;
mysqli_stmt_close($stmt);

mysqli_close($conn);

$gallery_return = urlencode('/farmer/product_gallery.php');
?>

<main class="dashboard-content farmer-dashboard">

  <!-- Back arrow + title beside -->
  <div style="display:flex; align-items:center; gap:12px; margin-bottom:14px;">
    <a class="back-arrow" href="<?php echo BASE_URL; ?>/farmer/manage_products.php" onclick="if (document.referrer && document.referrer.indexOf(window.location.host) !== -1) { history.back(); return false; }" aria-label="Back">
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path fill="currentColor" d="M15.5 19 8.5 12l7-7 1.5 1.5L11.5 12l5.5 5.5z"/>
      </svg>
    </a>
    <h1 style="margin:0; font-size:24px;">All Products Gallery</h1>
  </div>

  <!-- Clickable Farmer profile summary card (triggers public profile modal) -->
  <div class="panel" style="display:flex; gap:16px; align-items:center; flex-wrap:wrap; margin-bottom:18px; cursor:pointer;" onclick="openModal('publicProfileModal')" title="Click to view public store profile">
    <div style="width:72px; height:72px; border-radius:999px; overflow:hidden; background:#f2f2f2; border:2px solid var(--secondary); flex-shrink:0; position:relative;">
      <?php if (!empty($farmer['profile_image'])) { ?>
        <img src="<?php echo BASE_URL . '/' . e($farmer['profile_image']); ?>"
             alt="<?php echo e($farmer['username']); ?>"
             style="width:100%;height:100%;object-fit:cover;display:block;">
      <?php } else { ?>
        <div style="display:flex;align-items:center;justify-content:center;height:100%;font-size:24px;"></div>
      <?php } ?>
    </div>

    <div style="flex:1 1 auto; min-width:220px;">
      <div style="font-weight:800; font-size:18px; display:flex; align-items:center; gap:8px;">
        <span style="color:var(--primary);"><?php echo e($farmer['username'] ?? ''); ?></span>
        <span class="badge active" style="font-size:11px;">Verified Seller</span>
      </div>
      <div class="small-muted"><?php echo e($farmer['full_name'] ?? ''); ?> &bull; <?php echo e($farmer['address'] ?? ''); ?></div>

      <div class="small-muted" style="margin-top:6px;">
        Overall Rating: <strong><?php echo number_format($overall_rating, 1); ?></strong> (<?php echo $total_reviews; ?> reviews)
        &nbsp;&bull;&nbsp;
        Total Sold: <strong><?php echo (int)$total_sold_kg; ?> kg</strong>
      </div>
    </div>
  </div>

  <!-- Product grid with centered action buttons -->
  <div class="product-grid">
    <?php if (count($products) === 0) { ?>
      <div class="small-muted" style="grid-column: 1 / -1; padding:20px; text-align:center;">
        No active products yet in your gallery.
      </div>
    <?php } ?>

    <?php foreach ($products as $p) { 
      $is_sale = is_product_discounted($p['price'], $p['discounted_price'] ?? null, $p['selling_deadline'] ?? null);
      $eff_price = get_product_effective_price($p['price'], $p['discounted_price'] ?? null, $p['selling_deadline'] ?? null);
      $pct_off = ($is_sale && (float)$p['price'] > 0) ? round((1 - ($eff_price / (float)$p['price'])) * 100) : 0;
    ?>
      <div class="product-card" style="position:relative; <?php echo (!empty($p['is_promoted']) || !empty($p['is_boosted'])) ? 'border:1.5px solid #f59e0b; box-shadow:0 4px 12px rgba(245,158,11,0.12);' : ''; ?>">
        <div class="img" style="position:relative;">
          <?php if (!empty($p['image_url'])) { ?>
            <img src="<?php echo BASE_URL . '/' . e($p['image_url']); ?>" alt="<?php echo e($p['name']); ?>">
          <?php } else { ?>
            <div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--muted);background:#f2f2f2;">No Image</div>
          <?php } ?>

          <?php if ($is_sale && $pct_off > 0) { ?>
            <div style="position:absolute; top:8px; left:8px; background:#dc2626; color:#fff; font-size:11px; font-weight:800; padding:2px 7px; border-radius:6px; z-index:2;">
              -<?php echo $pct_off; ?>% OFF
            </div>
          <?php } ?>

          <?php if (!empty($p['is_promoted']) || !empty($p['is_boosted'])) { ?>
            <div style="position:absolute; top:8px; right:8px; background:linear-gradient(135deg, #fef3c7, #fde68a); color:#92400e; border:1px solid #fcd34d; font-size:10px; font-weight:800; padding:2px 6px; border-radius:6px; z-index:2; box-shadow:0 1px 3px rgba(0,0,0,0.15);">
              Promoted
            </div>
          <?php } ?>
        </div>

        <div class="body">
          <div style="font-weight:800; font-size:16px;"><?php echo e($p['name']); ?></div>
          <div class="small-muted" style="margin-top:2px;">
            Rating: <?php echo number_format((float)$p['avg_rating'], 1); ?> (<?php echo (int)$p['review_count']; ?>)
          </div>
          
          <div style="margin-top:8px; display:flex; align-items:baseline; gap:6px;">
            <?php if ($is_sale) { ?>
              <span style="font-size:12px; color:var(--muted); text-decoration:line-through; font-weight:500;">
                ₱<?php echo number_format((float)$p['price'], 2); ?>
              </span>
              <span style="font-weight:900; color:#dc2626; font-size:17px;">
                ₱<?php echo number_format((float)$eff_price, 2); ?>
              </span>
            <?php } else { ?>
              <span style="font-weight:800; color:var(--primary); font-size:16px;">
                ₱<?php echo number_format((float)$p['price'], 2); ?>
              </span>
            <?php } ?>
          </div>
          <div class="small-muted" style="margin-top:2px;">
            Stock: <?php echo (int)$p['stock_quantity']; ?>
          </div>

          <div style="margin-top:14px; display:flex; justify-content:center; gap:8px;">
            <a class="btn btn-primary btn-sm" style="flex:1; text-align:center;"
               href="<?php echo BASE_URL; ?>/farmer/product_detail.php?product_id=<?php echo (int)$p['product_id']; ?>&return=<?php echo $gallery_return; ?>">
              View Details
            </a>
            <a class="btn btn-secondary btn-sm" style="flex:1; text-align:center;"
              href="<?php echo BASE_URL; ?>/farmer/product_feedback.php?product_id=<?php echo (int)$p['product_id']; ?>&return=<?php echo $gallery_return; ?>">
              Feedback
            </a>
          </div>
        </div>
      </div>
    <?php } ?>
  </div>

</main>

<!-- Public Store Profile Modal -->
<div class="modal" id="publicProfileModal" aria-hidden="true">
  <div class="modal-content" style="max-width:500px;">
    <div class="modal-header">
      <h2>Public Store Profile</h2>
    </div>

    <div style="text-align:center; padding:10px 0 16px 0;">
      <div style="width:84px; height:84px; border-radius:999px; overflow:hidden; margin:0 auto 10px auto; border:2px solid var(--primary); background:#eee;">
        <?php if (!empty($farmer['profile_image'])) { ?>
          <img src="<?php echo BASE_URL . '/' . e($farmer['profile_image']); ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
        <?php } else { ?>
          <div style="display:flex;align-items:center;justify-content:center;height:100%;font-size:32px;"></div>
        <?php } ?>
      </div>

      <h3 style="font-size:20px; font-weight:900; margin:0;"><?php echo e($farmer['username']); ?></h3>
      <div class="small-muted"><?php echo e($farmer['full_name']); ?></div>
      <div style="margin-top:6px;">
        <span class="badge active">Verified Aquatic Seller</span>
      </div>
    </div>

    <div style="display:flex; flex-direction:column; gap:10px; background:#f9fbfb; border:1px solid var(--border); border-radius:10px; padding:14px;">
      <div style="display:flex; justify-content:space-between; font-size:14px;">
        <span class="small-muted">Farm Location:</span>
        <strong><?php echo e($farmer['address']); ?></strong>
      </div>
      <div style="display:flex; justify-content:space-between; font-size:14px;">
        <span class="small-muted">Contact Number:</span>
        <strong><?php echo e($farmer['contact_number']); ?></strong>
      </div>
      <div style="display:flex; justify-content:space-between; font-size:14px;">
        <span class="small-muted">Facebook Profile:</span>
        <span><?php echo !empty($farmer['facebook_account']) ? e($farmer['facebook_account']) : 'N/A'; ?></span>
      </div>
      <div style="display:flex; justify-content:space-between; font-size:14px;">
        <span class="small-muted">Seller Rating:</span>
        <strong><?php echo number_format($overall_rating, 1); ?> / 5.0 (<?php echo $total_reviews; ?> reviews)</strong>
      </div>
      <div style="display:flex; justify-content:space-between; font-size:14px;">
        <span class="small-muted">Total Volume Sold:</span>
        <strong><?php echo $total_sold_kg; ?> kg</strong>
      </div>
      <div style="display:flex; justify-content:space-between; font-size:14px;">
        <span class="small-muted">SeaLink Member Since:</span>
        <strong><?php echo date('M Y', strtotime($farmer['created_at'])); ?></strong>
      </div>
    </div>

    <div class="form-actions" style="justify-content:center; margin-top:16px;">
      <button type="button" class="btn btn-primary" onclick="closeModal('publicProfileModal')">Close Profile</button>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>