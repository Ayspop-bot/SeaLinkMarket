<?php
/**
 * SeaLink Web Application
 * File: /market.php
 * Purpose: Unified SeaLink Marketplace for all roles (Guest, Buyer, Farmer, Admin).
 * Uses: product_tbl, farmer_tbl, category_tbl, feedback_tbl
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$active_tab = 'market';
$page_title = "SeaLink Marketplace";
require_once __DIR__ . '/includes/layout_top.php';

// Auto-sync expired promotions in real-time
check_and_expire_promotions($conn);

$search = trim((string)($_GET['search'] ?? ''));
$category_id = (int)($_GET['category_id'] ?? 0);

/* ========== CATEGORIES ========== */
$categories = [];
$res = mysqli_query($conn, "SELECT category_id, category_name FROM category_tbl ORDER BY category_name ASC");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) $categories[] = $row;
}

/* ========== BUILD FILTERS ========== */
$where = "p.status = 'Active'";
$params = [];
$types = "";

if ($search !== '') {
    $where .= " AND LOWER(p.name) LIKE LOWER(CONCAT('%', ?, '%'))";
    $params[] = $search;
    $types .= "s";
}

if ($category_id > 0) {
    $where .= " AND p.category_id = ?";
    $params[] = $category_id;
    $types .= "i";
}

/* ========== PRODUCTS QUERY ========== */
$sql = "
SELECT
    p.product_id, p.farmer_id, p.name, p.price, p.discounted_price, p.stock_quantity, p.image_url,
    p.harvested_at, p.shelf_life_hours, p.selling_deadline, p.is_boosted, p.boost_tier,
    p.is_promoted, p.promotion_end_date, p.promotion_plan, p.promotion_status,
    c.category_name,
    f.username AS farmer_name,
    f.full_name AS farmer_fullname,
    f.address AS farmer_address,
    f.profile_image AS farmer_image,
    f.contact_number AS farmer_contact,
    COALESCE(AVG(fb.rating), 0) AS avg_rating,
    COUNT(fb.feedback_id) AS rating_cnt
FROM product_tbl p
JOIN farmer_tbl f ON f.farmer_id = p.farmer_id
JOIN category_tbl c ON c.category_id = p.category_id
LEFT JOIN feedback_tbl fb ON fb.product_id = p.product_id
WHERE {$where}
GROUP BY p.product_id
ORDER BY p.is_promoted DESC, p.is_boosted DESC, p.created_at DESC
";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    $bind = [$stmt, $types];
    foreach ($params as $k => $v) {
        $bind[] = &$params[$k];
    }
    call_user_func_array('mysqli_stmt_bind_param', $bind);
}

mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$products = [];
while ($row = mysqli_fetch_assoc($res)) $products[] = $row;

mysqli_stmt_close($stmt);
mysqli_close($conn);

$return_market = urlencode('/buyer/market.php?' . http_build_query([
    'category_id' => $category_id,
    'search'      => $search
]));
?>

<main class="dashboard-content buyer-dashboard">
    <!-- Header Inside Container -->
    <div class="section-card" style="margin-bottom:16px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div>
                <h1 style="margin:0; font-size:24px;">SeaLink Marketplace</h1>
                <div class="small-muted" style="margin-top:4px;">Browse fresh products available from local farmers in Santa Fe, Romblon</div>
            </div>
            <div class="small-muted"><?php echo count($products); ?> product(s) available</div>
        </div>
    </div>

    <div class="market-toolbar">
        <form class="market-filters" method="GET" action="<?php echo BASE_URL; ?>/market.php" id="marketFilterForm" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; width:100%;">
            <select name="category_id" id="marketCategory">
                <option value="0">All SeaLink Categories</option>
                <?php foreach ($categories as $c) { ?>
                    <option value="<?php echo (int)$c['category_id']; ?>" <?php echo ($category_id === (int)$c['category_id']) ? 'selected' : ''; ?>>
                        <?php echo e($c['category_name']); ?>
                    </option>
                <?php } ?>
            </select>

            <div class="search-input-wrapper">
                <button type="button" class="search-clear-x" onclick="clearSearchAndRefresh(this)" title="Clear and refresh search" aria-label="Clear search" <?php echo empty($search) ? 'style="display:none;"' : 'style="display:flex;"'; ?>>&times;</button>
                <input type="text" name="search" id="marketSearchInput" placeholder="Search product, seller, or category"
                       value="<?php echo e($search); ?>" oninput="checkSearchClear(this)">
            </div>

            <button type="submit" class="btn btn-primary btn-sm">Search</button>
        </form>
    </div>

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
    </style>

    <div class="section-card" style="margin-top:0;">
        <div class="deals-fixed-grid">
            <?php if (count($products) === 0) { ?>
                <div class="small-muted" style="grid-column: 1 / -1; padding: 40px; text-align: center;">
                    No aquatic products found. Try changing categories or search keywords.
                </div>
            <?php } ?>

            <?php foreach ($products as $p) { ?>
                <div class="market-card">
                    <div class="img">
                        <?php if (!empty($p['image_url'])) { ?>
                            <img src="<?php echo BASE_URL . '/' . e($p['image_url']); ?>" alt="<?php echo e($p['name']); ?>">
                        <?php } else { ?>
                            <div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--muted);background:#f2f2f2;">No Image</div>
                        <?php } ?>
                    </div>

                    <div class="body" style="padding:14px; flex:1; display:flex; flex-direction:column; justify-content:space-between;">
                        <div>
                            <?php if (!empty($p['is_boosted']) && (int)$p['is_boosted'] === 1) { ?>
                                <div style="margin-bottom:6px;">
                                    <span style="background:linear-gradient(135deg, #fef3c7, #fde68a); color:#92400e; border:1px solid #fcd34d; font-size:11px; font-weight:800; padding:2px 8px; border-radius:6px; display:inline-flex; align-items:center; gap:4px; box-shadow:0 1px 3px rgba(245,158,11,0.2);">
                                        Recommended
                                    </span>
                                </div>
                            <?php } ?>

                            <div class="title" style="font-size:15px; font-weight:800; margin-bottom:3px; line-height:1.3;"><?php echo e($p['name']); ?></div>
                        
                            <div style="font-size:12px; color:var(--muted); margin-bottom:3px;">
                                by <?php echo e($p['farmer_name']); ?> · <?php echo e($p['category_name']); ?>
                            </div>

                            <?php if ((float)$p['avg_rating'] > 0) { ?>
                                <div style="font-size:12px; color:#d97706; margin-top:2px;">
                                    <?php echo number_format((float)$p['avg_rating'], 1); ?> (<?php echo (int)$p['rating_cnt']; ?>)
                                </div>
                            <?php } ?>
                        </div>

                        <?php
                            $is_sale = is_product_discounted($p['price'], $p['discounted_price'] ?? null, $p['selling_deadline'] ?? null);
                            $eff_price = get_product_effective_price($p['price'], $p['discounted_price'] ?? null, $p['selling_deadline'] ?? null);
                            $secs_left = !empty($p['selling_deadline']) ? (strtotime($p['selling_deadline']) - time()) : 0;
                            $hrs_left = $secs_left > 0 ? floor($secs_left / 3600) : 0;
                            $mins_left = $secs_left > 0 ? floor(($secs_left % 3600) / 60) : 0;
                        ?>
                        <div style="margin-top:12px;">
                            <div style="display:flex; align-items:baseline; gap:8px; margin-bottom:4px; flex-wrap:wrap;">
                                <?php if ($is_sale) { ?>
                                    <span style="font-size:20px; font-weight:900; color:#dc2626;">
                                        ₱<?php echo number_format((float)$eff_price, 2); ?>
                                    </span>
                                    <span style="font-size:12px; color:var(--muted); text-decoration:line-through;">
                                        ₱<?php echo number_format((float)$p['price'], 2); ?>
                                    </span>
                                <?php } else { ?>
                                    <span style="font-size:20px; font-weight:800; color:var(--primary);">
                                        ₱<?php echo number_format((float)$p['price'], 2); ?>
                                    </span>
                                <?php } ?>
                            </div>

                            <!-- Stock and Timer beside each other -->
                            <div style="font-size:12px; color:var(--muted); margin-bottom:10px; display:flex; align-items:center; justify-content:space-between; gap:6px; flex-wrap:wrap;">
                                <span style="font-size:12px; color:var(--muted);">
                                    <?php if ((int)$p['stock_quantity'] > 0) { ?>
                                        Stock: <?php echo (int)$p['stock_quantity']; ?> kg
                                    <?php } else { ?>
                                        <span style="color:#b42318; font-weight:700;">Out of Stock</span>
                                    <?php } ?>
                                </span>
                                <?php if ($secs_left > 0) { ?>
                                    <span style="font-size:11px; background:#fff7ed; color:#92400e; border:1px solid #fed7aa; padding:2px 7px; border-radius:4px; font-weight:700;">
                                        Ends in <?php echo $hrs_left > 0 ? "{$hrs_left}h {$mins_left}m" : "{$mins_left} min"; ?>
                                    </span>
                                <?php } ?>
                            </div>

                            <a class="btn btn-primary btn-block" style="text-align:center; font-size:13px; font-weight:700;"
                               href="<?php echo BASE_URL; ?>/product_detail.php?product_id=<?php echo (int)$p['product_id']; ?>&return=<?php echo $return_market; ?>">
                                View Details
                            </a>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</main>

<!-- Seller Public Profile Modal for Buyers -->
<div class="modal" id="sellerProfileModal" aria-hidden="true">
  <div class="modal-content" style="max-width:480px;">
    <div class="modal-header">
      <h2>SeaLink Seller Profile</h2>
      <button type="button" class="modal-close-x" onclick="closeModal('sellerProfileModal')" aria-label="Close">&times;</button>
    </div>

    <div style="text-align:center; padding:10px 0 16px 0;">
      <div style="width:76px; height:76px; border-radius:999px; overflow:hidden; margin:0 auto 10px auto; border:2px solid var(--primary); background:#eee;" id="modal_seller_avatar_box">
        <img id="modal_seller_img" src="" alt="" style="width:100%;height:100%;object-fit:cover;display:none;">
        <div id="modal_seller_fallback" style="display:flex;align-items:center;justify-content:center;height:100%;font-size:14px;color:var(--muted);">Seller</div>
      </div>

      <h3 id="modal_seller_username" style="font-size:18px; font-weight:900; margin:0;"></h3>
      <div id="modal_seller_fullname" class="small-muted"></div>
      <div style="margin-top:6px;">
        <span class="badge active">Verified SeaLink Seller</span>
      </div>
    </div>

    <div style="display:flex; flex-direction:column; gap:10px; background:#f9fbfb; border:1px solid var(--border); border-radius:10px; padding:14px;">
      <div style="display:flex; justify-content:space-between; font-size:14px;">
        <span class="small-muted">Location:</span>
        <strong id="modal_seller_address"></strong>
      </div>
      <div style="display:flex; justify-content:space-between; font-size:14px;">
        <span class="small-muted">Contact:</span>
        <strong id="modal_seller_contact"></strong>
      </div>
    </div>

    <div class="form-actions" style="justify-content:flex-end; margin-top:16px;">
      <a id="modal_seller_store_btn" href="#" class="btn btn-primary">Visit Store</a>
    </div>
  </div>
</div>

<script>
function showSellerProfile(username, fullname, address, contact, imgUrl, farmerId) {
    document.getElementById('modal_seller_username').textContent = username;
    document.getElementById('modal_seller_fullname').textContent = fullname;
    document.getElementById('modal_seller_address').textContent = address || 'Santa Fe, Romblon';
    document.getElementById('modal_seller_contact').textContent = contact || 'Available upon order';
    
    var storeBtn = document.getElementById('modal_seller_store_btn');
    if (storeBtn) {
        storeBtn.href = '<?php echo BASE_URL; ?>/buyer/farmer_store.php?farmer_id=' + farmerId + '&return=<?php echo $return_market; ?>';
    }

    var imgEl = document.getElementById('modal_seller_img');
    var fallback = document.getElementById('modal_seller_fallback');
    if (imgUrl && imgEl) {
        imgEl.src = imgUrl;
        imgEl.style.display = 'block';
        if (fallback) fallback.style.display = 'none';
    } else {
        if (imgEl) imgEl.style.display = 'none';
        if (fallback) fallback.style.display = 'flex';
    }

    openModal('sellerProfileModal');
}
</script>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
