<?php
/**
 * SeaLink Web Application
 * File: /farmer_store.php
 * Purpose: Unified aquatic farmer storefront view for all roles with public profile popup and available products.
 * Uses: product_tbl, farmer_tbl, category_tbl, feedback_tbl, order_tbl, order_item_tbl
 */

$hide_nav = true;
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$active_tab = 'market';
$page_title = "Farmer Store - SeaLink";
require_once __DIR__ . '/includes/layout_top.php';

$farmer_id = (int)($_GET['farmer_id'] ?? 0);
$return = trim((string)($_GET['return'] ?? ''));

/* Safety: internal paths only */
if ($return !== '' && ($return[0] !== '/' || preg_match('/^\s*https?:/i', $return))) {
    $return = '';
}

$back_url = $return ? (BASE_URL . $return) : (BASE_URL . '/market.php');

if ($farmer_id <= 0) {
    echo "<main class='dashboard-content'><p>Invalid seller store.</p></main>";
    require_once __DIR__ . '/../includes/layout_bottom.php';
    exit;
}

/* Fetch farmer profile info */
$stmt = mysqli_prepare($conn, "
    SELECT farmer_id, username, full_name, address, contact_number, facebook_account, profile_image, verification_status, created_at
    FROM farmer_tbl
    WHERE farmer_id = ?
    LIMIT 1
");
mysqli_stmt_bind_param($stmt, "i", $farmer_id);
mysqli_stmt_execute($stmt);
$farmer = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$farmer) {
    mysqli_close($conn);
    echo "<main class='dashboard-content'><p>Farmer store not found.</p></main>";
    require_once __DIR__ . '/../includes/layout_bottom.php';
    exit;
}

/* Overall rating for farmer */
$stmt = mysqli_prepare($conn, "
    SELECT COALESCE(AVG(f.rating), 0) AS avg_rating, COUNT(f.feedback_id) AS total_reviews
    FROM feedback_tbl f
    JOIN product_tbl p ON p.product_id = f.product_id
    WHERE p.farmer_id = ?
");
mysqli_stmt_bind_param($stmt, "i", $farmer_id);
mysqli_stmt_execute($stmt);
$r_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$avg_rating = (float)($r_data['avg_rating'] ?? 0);
$total_reviews = (int)($r_data['total_reviews'] ?? 0);
mysqli_stmt_close($stmt);

/* Total sold kg */
$stmt = mysqli_prepare($conn, "
    SELECT COALESCE(SUM(oi.quantity), 0) AS sold_kg
    FROM order_item_tbl oi
    JOIN order_tbl o ON o.order_id = oi.order_id
    JOIN product_tbl p ON p.product_id = oi.product_id
    WHERE p.farmer_id = ? AND o.order_status = 'Completed'
");
mysqli_stmt_bind_param($stmt, "i", $farmer_id);
mysqli_stmt_execute($stmt);
$sold_kg = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['sold_kg'] ?? 0);
mysqli_stmt_close($stmt);

/* Active products */
$sql = "
SELECT
    p.product_id, p.name, p.price, p.discounted_price, p.selling_deadline, p.stock_quantity, p.image_url,
    c.category_name,
    COALESCE(AVG(fb.rating), 0) AS avg_rating
FROM product_tbl p
JOIN category_tbl c ON c.category_id = p.category_id
LEFT JOIN feedback_tbl fb ON fb.product_id = p.product_id
WHERE p.farmer_id = ? AND p.status = 'Active'
GROUP BY p.product_id
ORDER BY p.created_at DESC
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $farmer_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$products = [];
while ($row = mysqli_fetch_assoc($res)) {
    $products[] = $row;
}
mysqli_stmt_close($stmt);
mysqli_close($conn);

$store_self = '/buyer/farmer_store.php?' . http_build_query([
    'farmer_id' => $farmer_id,
    'return'    => $return
]);
$store_self_enc = urlencode($store_self);
?>

<style>
  .deals-fixed-grid {
    display: grid !important;
    grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
    gap: 16px !important;
  }
  @media (max-width: 1024px) {
    .deals-fixed-grid {
      grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
    }
  }
  @media (max-width: 768px) {
    .deals-fixed-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }
  }
  @media (max-width: 480px) {
    .deals-fixed-grid {
      grid-template-columns: repeat(1, minmax(0, 1fr)) !important;
    }
  }
  .deals-fixed-grid .market-card {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    border-radius: 14px;
    overflow: hidden;
    border: 1.5px solid var(--border);
    box-shadow: 0 4px 14px rgba(0,0,0,0.05);
    transition: transform 0.2s, box-shadow 0.2s;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    position: relative;
  }
  .deals-fixed-grid .market-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
  }
  .deals-fixed-grid .market-card .img {
    width: 100%;
    height: 180px;
    overflow: hidden;
    position: relative;
    background: #f1f5f9;
  }
  .deals-fixed-grid .market-card .img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }
</style>

<main class="dashboard-content buyer-dashboard">
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
        <a class="back-arrow" href="<?php echo e($back_url); ?>" onclick="if (document.referrer && document.referrer.indexOf(window.location.host) !== -1) { history.back(); return false; }" aria-label="Back">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path fill="currentColor" d="M15.5 19 8.5 12l7-7 1.5 1.5L11.5 12l5.5 5.5z"/>
            </svg>
        </a>
        <h1 style="margin:0; font-size:24px;"><?php echo e($farmer['username']); ?> Storefront</h1>
    </div>

    <!-- Farmer Store Header Banner (Clickable info opens profile popup) -->
    <div class="panel" style="display:flex; gap:18px; align-items:center; justify-content:space-between; flex-wrap:wrap; margin-bottom:20px;">
        <div style="display:flex; gap:16px; align-items:center; flex-wrap:wrap; cursor:pointer;" onclick="openModal('publicFarmerProfileModal')" title="Click to view seller profile">
            <div style="width:80px; height:80px; border-radius:999px; overflow:hidden; background:#f0f0f0; border:2px solid var(--primary); flex-shrink:0;">
                <?php if (!empty($farmer['profile_image'])) { ?>
                    <img src="<?php echo BASE_URL . '/' . e($farmer['profile_image']); ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                <?php } else { ?>
                    <div style="display:flex;align-items:center;justify-content:center;height:100%;font-size:32px;"></div>
                <?php } ?>
            </div>

            <div>
                <div style="font-weight:800; font-size:20px; display:flex; align-items:center; gap:8px;">
                    <span style="color:var(--primary);"><?php echo e($farmer['username']); ?></span>
                    <span class="badge active" style="font-size:11px;">Verified Seller</span>
                </div>
                <div class="small-muted"><?php echo e($farmer['full_name']); ?> &bull; <?php echo e($farmer['address']); ?></div>

                <div class="small-muted" style="margin-top:6px;">
                    Rating: <strong><?php echo number_format($avg_rating, 1); ?> / 5.0</strong> (<?php echo $total_reviews; ?> reviews)
                    &nbsp;•&nbsp;
                    Sold: <strong><?php echo $sold_kg; ?> kg</strong>
                </div>
            </div>
        </div>

        <div>
            <?php if (!is_logged_in()) { ?>
                <button type="button" class="btn btn-primary" onclick="openGuestPromptModal('Messages');">
                    Message Seller
                </button>
            <?php } elseif (($_SESSION['user_role'] ?? '') === 'buyer') { ?>
                <a class="btn btn-primary" href="<?php echo BASE_URL; ?>/buyer/messages.php?farmer_id=<?php echo (int)$farmer['farmer_id']; ?>">
                    Message Seller
                </a>
            <?php } ?>
        </div>
    </div>

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
        <h2 style="font-size:18px; font-weight:800; margin:0;">Available Products in Store</h2>
        <div class="small-muted"><?php echo count($products); ?> product(s)</div>
    </div>

    <div class="deals-fixed-grid">
        <?php if (count($products) === 0) { ?>
            <div class="small-muted" style="grid-column: 1 / -1; padding:30px; text-align:center;">
                This seller has no active products at the moment.
            </div>
        <?php } ?>

        <?php foreach ($products as $p) { ?>
            <?php
                $is_sale = is_product_discounted($p['price'], $p['discounted_price'] ?? null, $p['selling_deadline'] ?? null);
                $eff_price = get_product_effective_price($p['price'], $p['discounted_price'] ?? null, $p['selling_deadline'] ?? null);
                $pct_off = ($is_sale && $p['price'] > 0) ? round((1 - $eff_price / $p['price']) * 100) : 0;
            ?>
            <div class="market-card">
                <?php if ($pct_off > 0) { ?>
                    <div style="position:absolute; top:8px; left:8px; background:#dc2626; color:#fff; font-size:11px; font-weight:900; padding:3px 8px; border-radius:6px; z-index:2; letter-spacing:0.3px;">
                        -<?php echo $pct_off; ?>%
                    </div>
                <?php } ?>
                <div class="img">
                    <?php if (!empty($p['image_url'])) { ?>
                        <img src="<?php echo BASE_URL . '/' . e($p['image_url']); ?>" alt="<?php echo e($p['name']); ?>">
                    <?php } else { ?>
                        <div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--muted);background:#f2f2f2;">No Image</div>
                    <?php } ?>
                </div>

                <div class="body" style="padding:14px; flex:1; display:flex; flex-direction:column; justify-content:space-between;">
                    <div>
                        <div class="title" style="font-size:15px; font-weight:800; margin-bottom:4px;"><?php echo e($p['name']); ?></div>
                        <div style="font-size:12px; color:var(--muted); margin-bottom:3px;"><?php echo e($p['category_name']); ?></div>

                        <?php if ((float)$p['avg_rating'] > 0) { ?>
                            <div style="font-size:12px; color:#d97706; margin-top:3px;">
                                <?php echo number_format((float)$p['avg_rating'], 1); ?>
                            </div>
                        <?php } ?>

                        <?php if (!empty($p['selling_deadline'])) { 
                            $secs_left = strtotime($p['selling_deadline']) - time();
                            if ($secs_left > 0) {
                                $hrs_left = floor($secs_left / 3600);
                                $mins_left = floor(($secs_left % 3600) / 60);
                        ?>
                            <div style="margin-top:6px; font-size:11px; background:#dcfce7; color:#166534; padding:2px 7px; border-radius:4px; display:inline-block; font-weight:700;">
                                Ends in <?php echo $hrs_left > 0 ? "{$hrs_left}h {$mins_left}m" : "{$mins_left} min"; ?>
                            </div>
                        <?php } } ?>
                    </div>

                    <div style="margin-top:12px;">
                        <div style="display:flex; align-items:flex-end; gap:8px; margin-bottom:6px; flex-wrap:wrap;">
                            <?php if ($is_sale) { ?>
                                <span style="font-size:19px; font-weight:900; color:#dc2626;">
                                    ₱<?php echo number_format((float)$eff_price, 2); ?>
                                </span>
                                <span style="font-size:12px; color:var(--muted); text-decoration:line-through;">
                                    ₱<?php echo number_format((float)$p['price'], 2); ?>
                                </span>
                            <?php } else { ?>
                                <span style="font-size:19px; font-weight:800; color:var(--primary);">
                                    ₱<?php echo number_format((float)$p['price'], 2); ?>
                                </span>
                            <?php } ?>
                        </div>

                        <div style="font-size:12px; color:var(--muted); margin-bottom:10px;">
                            <?php if ((int)$p['stock_quantity'] > 0) { ?>
                                Stock: <?php echo (int)$p['stock_quantity']; ?>
                            <?php } else { ?>
                                <span style="color:#b42318; font-weight:700;">Out of Stock</span>
                            <?php } ?>
                        </div>

                        <a class="btn btn-primary btn-block" style="text-align:center; font-size:13px;"
                           href="<?php echo BASE_URL; ?>/product_detail.php?product_id=<?php echo (int)$p['product_id']; ?>&return=<?php echo $store_self_enc; ?>">
                            View Details
                        </a>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>
</main>

<!-- Public Farmer Profile Modal -->
<div class="modal" id="publicFarmerProfileModal" aria-hidden="true">
  <div class="modal-content" style="max-width:500px;">
    <div class="modal-header">
      <h2>Aquatic Seller Information</h2>
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
        <span class="badge active">Verified SeaLink Seller</span>
      </div>
    </div>

    <div style="display:flex; flex-direction:column; gap:10px; background:#f9fbfb; border:1px solid var(--border); border-radius:10px; padding:14px;">
      <div style="display:flex; justify-content:space-between; font-size:14px;">
        <span class="small-muted">Location:</span>
        <strong><?php echo e($farmer['address']); ?></strong>
      </div>
      <div style="display:flex; justify-content:space-between; font-size:14px;">
        <span class="small-muted">Contact Number:</span>
        <strong><?php echo e($farmer['contact_number']); ?></strong>
      </div>
      <div style="display:flex; justify-content:space-between; font-size:14px;">
        <span class="small-muted">Facebook Account:</span>
        <span><?php echo !empty($farmer['facebook_account']) ? e($farmer['facebook_account']) : 'N/A'; ?></span>
      </div>
      <div style="display:flex; justify-content:space-between; font-size:14px;">
        <span class="small-muted">Seller Rating:</span>
        <strong><?php echo number_format($avg_rating, 1); ?> / 5.0 (<?php echo $total_reviews; ?> reviews)</strong>
      </div>
      <div style="display:flex; justify-content:space-between; font-size:14px;">
        <span class="small-muted">Total Volume Sold:</span>
        <strong><?php echo $sold_kg; ?> kg</strong>
      </div>
      <div style="display:flex; justify-content:space-between; font-size:14px;">
        <span class="small-muted">SeaLink Seller Since:</span>
        <strong><?php echo date('M Y', strtotime($farmer['created_at'])); ?></strong>
      </div>
    </div>

    <div class="form-actions" style="justify-content:center; margin-top:16px;">
      <button type="button" class="btn btn-primary" onclick="closeModal('publicFarmerProfileModal')">Close Profile</button>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/modal_guest.php'; ?>
<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>