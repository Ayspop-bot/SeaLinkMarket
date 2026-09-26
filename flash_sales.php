<?php
/**
 * SeaLink Web Application
 * File: /flash_sales.php
 * Purpose: Flash Sales — full listing of all boosted/advertised products with live countdown timers (guest-accessible).
 * Connected To: /index.php Flash Sales "View All" link
 * Uses: product_tbl, category_tbl, farmer_tbl, product_boost_tbl
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$active_tab = 'home';
$hide_nav = true;
$page_title = "Flash Sales — Limited Time | SeaLink";
require_once __DIR__ . '/includes/layout_top.php';

// Fetch categories for clickable category filters
$categories = [];
$c_res = mysqli_query($conn, "SELECT category_id, category_name FROM category_tbl ORDER BY category_name ASC");
if ($c_res) {
    while ($row = mysqli_fetch_assoc($c_res)) $categories[] = $row;
}

$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$search = trim($_GET['search'] ?? '');

$where = [
    "p.status = 'Active'",
    "p.is_boosted = 1",
    "(p.boost_expires_at IS NULL OR p.boost_expires_at > NOW())"
];

if ($category_id > 0) {
    $where[] = "p.category_id = " . (int)$category_id;
}
if ($search !== '') {
    $esc = mysqli_real_escape_string($conn, $search);
    $where[] = "LOWER(p.name) LIKE LOWER('%$esc%')";
}
$where_sql = implode(" AND ", $where);

// Fetch all active boosted products with boost expiry
$flash = [];
$res = mysqli_query($conn, "
    SELECT p.product_id, p.category_id, p.name, p.price, p.discounted_price, p.stock_quantity,
           p.image_url, p.is_boosted, p.boost_tier, p.boost_expires_at,
           p.harvested_at, p.shelf_life_hours, p.selling_deadline,
           c.category_name, f.username AS farmer_name,
           COALESCE(AVG(fb.rating), 0) AS avg_rating,
           COUNT(fb.feedback_id) AS rating_cnt
    FROM product_tbl p
    JOIN category_tbl c ON c.category_id = p.category_id
    JOIN farmer_tbl f ON f.farmer_id = p.farmer_id
    LEFT JOIN feedback_tbl fb ON fb.product_id = p.product_id
    WHERE {$where_sql}
    GROUP BY p.product_id
    ORDER BY p.boost_expires_at ASC
");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) $flash[] = $row;
}

mysqli_close($conn);

$tier_icons = [
    'Flash Banner' ,
    'Top Search'   ,
    'Featured Badge',
];

$home_url = is_logged_in() && ($_SESSION['user_role'] ?? '') === 'buyer' ? (BASE_URL . '/buyer/dashboard.php') : (BASE_URL . '/index.php');
?>

<main class="dashboard-content public-homepage" style="max-width:1200px; margin:0 auto; padding-bottom:60px; min-height:100vh; overflow-y:auto;">

  <!-- Back button to Home Tab -->
  <div style="display:inline-flex; align-items:center; gap:8px; margin-bottom:14px;">
    <a class="back-arrow" href="<?php echo $home_url; ?>" onclick="if (document.referrer && document.referrer.indexOf(window.location.host) !== -1) { history.back(); return false; }" aria-label="Back" style="margin-bottom:0;">
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path fill="currentColor" d="M15.5 19 8.5 12l7-7 1.5 1.5L11.5 12l5.5 5.5z"/>
      </svg>
    </a>
    <a href="<?php echo $home_url; ?>" onclick="if (document.referrer && document.referrer.indexOf(window.location.host) !== -1) { history.back(); return false; }" style="font-weight:700; font-size:15px; color:var(--text); text-decoration:none;">Back</a>
  </div>

  <style>
    /* Fixed 4-per-screen product grid matching buyer/market.php */
    .flash-sale-fixed-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 16px;
    }
    @media (max-width: 1024px) {
      .flash-sale-fixed-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
      }
    }
    @media (max-width: 768px) {
      .flash-sale-fixed-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }
    @media (max-width: 480px) {
      .flash-sale-fixed-grid {
        grid-template-columns: repeat(1, minmax(0, 1fr));
      }
    }
    .flash-sale-fixed-grid .market-card {
      width: 100%;
      max-width: 100%;
      box-sizing: border-box;
      border-radius: 14px;
      overflow: hidden;
      border: 1.5px solid #f59e0b;
      box-shadow: 0 4px 14px rgba(245,158,11,0.12);
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .flash-sale-fixed-grid .market-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 24px rgba(245,158,11,0.22);
    }
    .flash-sale-fixed-grid .market-card .img {
      width: 100%;
      height: 190px;
      aspect-ratio: auto;
      overflow: hidden;
      position: relative;
      background: #f1f5f9;
    }
    .flash-sale-fixed-grid .market-card .img img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }
  </style>

  <!-- Page Header Banner -->
  <div class="section-card" style="margin-top:0; padding:14px 18px; background:linear-gradient(135deg,#0284c7 0%,#0369a1 100%); color:#fff; border-radius:14px; margin-bottom:12px; box-shadow:0 4px 14px rgba(2,132,199,0.2);">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
      <div>
        <h1 style="margin:0 0 2px 0; font-size:20px; font-weight:800; color:#fff; line-height:1.2;">All Flash Sale Products</h1>
        <p style="margin:0; font-size:13px; color:rgba(255,255,255,0.9); line-height:1.35;">
          Limited-time catches direct from verified Santa Fe fisherfolk
        </p>
      </div>
      <div style="text-align:right; display:flex; align-items:baseline; gap:6px;">
        <span style="font-size:24px; font-weight:900; color:#fef08a;" id="flash_active_badge_count"><?php echo count($flash); ?></span>
        <span style="font-size:12px; color:rgba(255,255,255,0.85);">Active Deals</span>
      </div>
    </div>
  </div>

  <!-- Dropdown Category & Search Toolbar -->
  <div class="market-toolbar" style="margin-bottom:16px;">
    <form class="market-filters" method="GET" action="" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; width:100%;">
      <select name="category_id" id="flashCategory" onchange="this.form.submit()">
        <option value="0">All SeaLink Categories</option>
        <?php foreach ($categories as $cat) { ?>
          <option value="<?php echo (int)$cat['category_id']; ?>" <?php echo ($category_id === (int)$cat['category_id']) ? 'selected' : ''; ?>>
            <?php echo e($cat['category_name']); ?>
          </option>
        <?php } ?>
      </select>

      <div class="search-input-wrapper">
        <button type="button" class="search-clear-x" onclick="clearSearchAndRefresh(this)" title="Clear and refresh search" aria-label="Clear search" <?php echo empty($search) ? 'style="display:none;"' : 'style="display:flex;"'; ?>>&times;</button>
        <input type="text" name="search" id="flashSearchInput" placeholder="Search flash sales, products, or farmers..."
               value="<?php echo e($search); ?>" oninput="checkSearchClear(this)">
      </div>

      <button type="submit" class="btn btn-primary btn-sm" style="background:#0284c7; border-color:#0284c7;">Search</button>
    </form>
  </div>

  <?php if (empty($flash)) { ?>
    <div class="section-card" style="text-align:center; padding:60px 24px; border-radius:16px;">
      <h2 style="font-size:20px; font-weight:800; margin:0 0 8px 0;">No aquatic products found.</h2>
      <p class="small-muted" style="margin:0 0 20px 0; max-width:440px; margin-left:auto; margin-right:auto;">
        Try changing categories or search keywords.
      </p>
      <div style="display:flex; justify-content:center; gap:10px; flex-wrap:wrap;">
        <?php if (!empty($search) || !empty($category_id)) { ?>
          <a href="<?php echo BASE_URL; ?>/flash_sales.php" class="btn btn-secondary">Clear Search & Filters</a>
        <?php } else { ?>
          <a href="<?php echo $home_url; ?>" class="btn btn-secondary">&larr; Back to Home</a>
        <?php } ?>
        <a href="<?php echo BASE_URL; ?>/buyer/market.php" class="btn btn-primary">Browse Full Market</a>
      </div>
    </div>
  <?php } else { ?>

    <div class="section-card" style="padding:20px; border-radius:16px;">
      <div style="font-size:13px; color:var(--muted); margin-bottom:16px;">
        Showing <strong id="flash_shown_count"><?php echo count($flash); ?></strong> limited-time flash promotion<?php echo count($flash) !== 1 ? 's' : ''; ?> from local producers:
      </div>

      <!-- Fixed 4-Column Responsive Grid -->
      <div class="flash-sale-fixed-grid">
        <div id="flash_no_filter_res" style="display:none; grid-column:1/-1; text-align:center; padding:40px 20px; color:var(--muted); font-weight:700;">
          No aquatic products found. Try changing categories or search keywords.
        </div>

        <?php foreach ($flash as $p) {
          $orig_price = (float)$p['price'];
          $is_discounted = is_product_discounted($orig_price, $p['discounted_price'] ?? null, $p['selling_deadline'] ?? null);
          $disc_price = $is_discounted ? (float)$p['discounted_price'] : $orig_price;
          $pct_off = ($is_discounted && $orig_price > 0) ? round((1 - ($disc_price / $orig_price)) * 100) : 0;
          $sale_secs = ($is_discounted && !empty($p['selling_deadline'])) ? max(0, strtotime($p['selling_deadline']) - time()) : 0;
          $b_hrs = floor($sale_secs / 3600);
          $b_mins = floor(($sale_secs % 3600) / 60);
          $b_secs_rem = $sale_secs % 60;
          $tier_icon = $tier_icons[$p['boost_tier'] ?? ''];
          $boost_id = 'boost_' . (int)$p['product_id'];
        ?>
          <div class="market-card flash-sale-card-item" data-category="<?php echo (int)$p['category_id']; ?>" style="display:flex; flex-direction:column; justify-content:space-between; position:relative;">
            <!-- Tier badge -->
            <div style="position:absolute; top:8px; left:8px; background:#0284c7; color:#fff; font-size:10px; font-weight:900; padding:3px 8px; border-radius:6px; z-index:2; box-shadow:0 2px 4px rgba(0,0,0,0.2);">
              <?php echo $tier_icon; ?> <?php echo e($p['boost_tier'] ?? 'Flash Sale'); ?>
            </div>

            <?php if ($is_discounted && $pct_off > 0) { ?>
              <div style="position:absolute; top:8px; right:8px; background:#dc2626; color:#fff; font-size:10px; font-weight:900; padding:3px 8px; border-radius:6px; z-index:2; box-shadow:0 2px 4px rgba(0,0,0,0.2);">
                -<?php echo $pct_off; ?>%
              </div>
            <?php } ?>

            <div class="img">
              <?php if (!empty($p['image_url'])) { ?>
                <img src="<?php echo BASE_URL . '/' . e($p['image_url']); ?>" alt="<?php echo e($p['name']); ?>">
              <?php } else { ?>
                <div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--muted);background:#f1f5f9;font-size:32px;">🐟</div>
              <?php } ?>
            </div>

            <div class="body" style="padding:14px; flex:1; display:flex; flex-direction:column; justify-content:space-between;">
              <div>
                <div class="title" style="font-size:15px; font-weight:800; margin-bottom:3px;"><?php echo e($p['name']); ?></div>
                <div style="font-size:12px; color:var(--muted);">by <strong><?php echo e($p['farmer_name']); ?></strong> &bull; <?php echo e($p['category_name']); ?></div>

                <?php if ($p['avg_rating'] > 0) { ?>
                  <div style="font-size:12px; color:#d97706; margin-top:3px;">
                    <?php echo number_format((float)$p['avg_rating'], 1); ?> (<?php echo (int)$p['rating_cnt']; ?>)
                  </div>
                <?php } ?>
              </div>

              <div style="margin-top:12px;">
                <!-- Price display: genuine regular or discounted -->
                <div style="display:flex; align-items:baseline; gap:8px; margin-bottom:4px; flex-wrap:wrap;">
                  <?php if ($is_discounted) { ?>
                    <span style="font-size:19px; font-weight:900; color:#dc2626;">₱<?php echo number_format($disc_price, 2); ?></span>
                    <span style="font-size:12px; color:var(--muted); text-decoration:line-through; font-weight:600;">₱<?php echo number_format($orig_price, 2); ?></span>
                  <?php } else { ?>
                    <span style="font-size:19px; font-weight:900; color:var(--primary);">₱<?php echo number_format($orig_price, 2); ?></span>
                  <?php } ?>
                </div>

                <!-- Stock and Timer beside each other -->
                <div style="font-size:12px; color:var(--muted); margin-bottom:10px; display:flex; align-items:center; justify-content:space-between; gap:6px; flex-wrap:wrap;">
                  <span style="font-size:12px; color:var(--muted);">Stock: <?php echo (int)$p['stock_quantity']; ?> kg</span>
                  <?php if ($is_discounted && $sale_secs > 0) { ?>
                    <span style="font-size:11px; color:#92400e; background:#fff7ed; border:1px solid #fed7aa; padding:2px 7px; border-radius:4px; font-weight:700;">
                      Ends in <span id="<?php echo $boost_id; ?>" data-ends="<?php echo (int)strtotime($p['selling_deadline']); ?>"><?php echo $b_hrs > 0 ? "{$b_hrs}h {$b_mins}m" : "{$b_mins}m"; ?></span>
                    </span>
                  <?php } ?>
                </div>

                <a href="<?php echo BASE_URL; ?>/product_detail.php?product_id=<?php echo (int)$p['product_id']; ?>&return=<?php echo urlencode('/flash_sales.php'); ?>"
                   class="btn btn-primary btn-block" style="text-align:center; font-size:13px; font-weight:800; padding:8px 12px; border-radius:8px;">
                  View Flash Deal
                </a>
              </div>
            </div>
          </div>
        <?php } ?>
      </div>
    </div>

  <?php } ?>

</main>

<script>
// Live countdown timers for each flash sale card (matching Today's Deal format)
(function() {
  function updateCountdowns() {
    var timers = document.querySelectorAll('[id^="boost_"][data-ends]');
    var now = Math.floor(Date.now() / 1000);
    timers.forEach(function(el) {
      var ends = parseInt(el.dataset.ends, 10);
      var secs = Math.max(0, ends - now);
      var h = Math.floor(secs / 3600);
      var m = Math.floor((secs % 3600) / 60);
      var s = secs % 60;
      if (h > 0) {
        el.textContent = h + 'h ' + m + 'm';
      } else if (m > 0) {
        el.textContent = m + 'm ' + s + 's';
      } else {
        el.textContent = s + 's';
      }
      if (secs <= 300) el.style.color = '#dc2626';
    });
  }
  updateCountdowns();
  setInterval(updateCountdowns, 1000);
})();
</script>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>

