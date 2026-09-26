<?php
/**
 * SeaLink Web Application
 * File: /index.php
 * Purpose: Public storefront — swipable Flash Sales carousel (boosted products),
 *          value proposition strip, emoji category tiles, Today's Deals (discounted),
 *          Info Hub highlights, guest CTA, newsletter subscription, and contact footer.
 * Accessible to: All users and guests.
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

/* Handle Guest Alert / Newsletter Subscription */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guest_email'])) {
    $guest_email = normalize_spaces($_POST['guest_email'] ?? '');
    $category_interest = sanitize_input($_POST['category_interest'] ?? 'All');
    if (validate_email($guest_email)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO guest_alert_tbl (email, category_interest) VALUES (?, ?)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ss", $guest_email, $category_interest);
            @mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            set_message('success', 'Thank you! You will be notified when fresh catch arrives in Santa Fe.');
        }
    } else {
        set_message('error', 'Please enter a valid email address for catch alerts.');
    }
    redirect_path('/index.php');
}

$active_tab = 'home';
$page_title = "SeaLink - Santa Fe Aquatic Marketplace";
require_once __DIR__ . '/includes/layout_top.php';

/* ========== 1. FLASH SALES — BOOSTED PRODUCTS ========== */
$boosted_products = [];
$b_res = mysqli_query($conn, "
    SELECT p.product_id, p.name, p.price, p.discounted_price, p.image_url, p.description,
           p.boost_tier, p.boost_expires_at, p.selling_deadline,
           f.username AS farmer_name, f.full_name AS farmer_full_name
    FROM product_tbl p
    JOIN farmer_tbl f ON f.farmer_id = p.farmer_id
    WHERE p.status = 'Active' AND p.is_boosted = 1
      AND (p.boost_expires_at IS NULL OR p.boost_expires_at > NOW())
    ORDER BY p.boost_expires_at ASC
    LIMIT 8
");
if ($b_res && mysqli_num_rows($b_res) > 0) {
    while ($row = mysqli_fetch_assoc($b_res)) $boosted_products[] = $row;
}

/* ========== 2. TODAY'S DEALS — DISCOUNTED PRODUCTS ========== */
$todays_deals = [];
$td_res = mysqli_query($conn, "
    SELECT p.product_id, p.name, p.price, p.discounted_price, p.stock_quantity, p.image_url,
           p.harvested_at, p.shelf_life_hours, p.selling_deadline,
           c.category_name, f.username AS farmer_name, f.full_name AS farmer_full_name,
           COALESCE(AVG(fb.rating), 0) AS avg_rating,
           COUNT(fb.feedback_id) AS rating_cnt
    FROM product_tbl p
    JOIN category_tbl c ON c.category_id = p.category_id
    JOIN farmer_tbl f ON f.farmer_id = p.farmer_id
    LEFT JOIN feedback_tbl fb ON fb.product_id = p.product_id
    WHERE p.status = 'Active'
      AND p.discounted_price IS NOT NULL
      AND p.discounted_price > 0
      AND p.discounted_price < p.price
    GROUP BY p.product_id
    ORDER BY (p.price - p.discounted_price) / p.price DESC
    LIMIT 4
");
if ($td_res) {
    while ($row = mysqli_fetch_assoc($td_res)) $todays_deals[] = $row;
}

/* ========== 3. INFO HUB PREVIEWS — 4 RECENT PUBLICATIONS ========== */
$hub_publications = [];
$hub_res = mysqli_query($conn, "
    SELECT info_hub_id, title, category, type, image_url, created_at, SUBSTRING(content, 1, 130) AS excerpt
    FROM info_hub_tbl
    WHERE status = 'Published'
    ORDER BY created_at DESC
    LIMIT 4
");
if ($hub_res) {
    while ($row = mysqli_fetch_assoc($hub_res)) $hub_publications[] = $row;
}

/* ========== 3b. FORUM PREVIEWS — 4 RECENT DISCUSSIONS ========== */
$forum_preview_posts = [];
$fp_res = mysqli_query($conn, "
    SELECT p.post_id, p.title, p.category, p.author_name, p.user_role, p.created_at,
           SUBSTRING(p.content, 1, 130) AS excerpt,
           COUNT(c.comment_id) AS comment_count
    FROM forum_post_tbl p
    LEFT JOIN forum_comment_tbl c ON c.post_id = p.post_id
    GROUP BY p.post_id
    ORDER BY p.created_at DESC
    LIMIT 4
");
if ($fp_res) {
    while ($row = mysqli_fetch_assoc($fp_res)) $forum_preview_posts[] = $row;
}

/* ========== 4. CATEGORIES — Fetch real IDs from DB ========== */
$cat_map = [];
$cat_res = mysqli_query($conn, "SELECT category_id, category_name FROM category_tbl ORDER BY category_id ASC");
if ($cat_res) {
    while ($c = mysqli_fetch_assoc($cat_res)) {
        $cat_map[strtolower($c['category_name'])] = $c;
    }
}

function find_cat_id($map, $keyword) {
    foreach ($map as $k => $c) {
        if (stripos($k, $keyword) !== false) return (int)$c['category_id'];
    }
    return 0;
}

$display_categories = [
    [
        'label'    => 'Fish',
        'desc'     => 'Bangus, Tilapia, Tulingan etc.',
        'cat_id'   => find_cat_id($cat_map, 'fish'),
    ],
    [
        'label'    => 'Shrimps / Crabs',
        'desc'     => 'Hipon, Alimango, Alimasag, Sugpo',
        'cat_id'   => find_cat_id($cat_map, 'shrimp'),
    ],
    [
        'label'    => 'Shellfish',
        'desc'     => 'Tahong, Talaba, Halaan, Tulya',
        'cat_id'   => find_cat_id($cat_map, 'shell'),
    ],
    [
        'label'    => 'Squid',
        'desc'     => 'Pusit (Fresh & Dried)',
        'cat_id'   => find_cat_id($cat_map, 'squid'),
    ],
    [
        'label'    => 'Octopus',
        'desc'     => 'Pugita (Fresh & Dried)',
        'cat_id'   => find_cat_id($cat_map, 'octopus'),
    ],
    [
        'label'    => 'Seaweeds',
        'desc'     => 'Lato, Guso etc.',
        'cat_id'   => find_cat_id($cat_map, 'seaweed'),
    ],
];

mysqli_close($conn);
?>

<main class="dashboard-content public-homepage">

<?php display_message(true); ?>

<?php if (is_logged_in()) {
    $role = $_SESSION['user_role'] ?? '';
    $dash_url = $role === 'farmer' ? BASE_URL . '/farmer/dashboard.php'
              : ($role === 'buyer' ? BASE_URL . '/buyer/dashboard.php' : BASE_URL . '/admin/content_dashboard.php');
?>
  <div class="section-card" style="margin-top:0; margin-bottom:18px; background:linear-gradient(135deg, #e0f2fe, #bae6fd); border:1px solid #7dd3fc; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; padding:14px 20px; border-radius:12px;">
    <div>
      <strong style="color:#0369a1; font-size:15px;">Welcome back, <?php echo e($_SESSION['full_name'] ?? $_SESSION['username']); ?>!</strong>
      <div style="font-size:13px; color:#0284c7;">You are logged in as <strong><?php echo e(ucfirst($role)); ?></strong>.</div>
    </div>
    <a href="<?php echo $dash_url; ?>" class="btn btn-primary btn-sm" style="font-weight:700;">Go to Dashboard</a>
  </div>
<?php } ?>

  <!-- ===== 1. FLASH SALES / HERO CAROUSEL ===== -->
  <style>
    .flash-carousel-container {
      position: relative;
      overflow: hidden;
      cursor: grab;
      user-select: none;
    }
    .flash-slide-content {
      position: relative;
      z-index: 2;
      padding: 20px 28px;
      color: #fff;
      display: flex;
      align-items: center;
      gap: 22px;
      min-height: 200px;
      box-sizing: border-box;
    }
    .flash-slide-img-box {
      width: 190px;
      height: 150px;
      min-width: 170px;
      border-radius: 14px;
      overflow: hidden;
      box-shadow: 0 8px 24px rgba(0,0,0,0.3);
      border: 2px solid rgba(255,255,255,0.3);
      background: rgba(0,0,0,0.3);
      flex-shrink: 0;
      position: relative;
    }
    .flash-slide-info {
      flex: 1;
      min-width: 220px;
    }
    .deals-fixed-grid {
      display: grid !important;
      grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
      gap: 16px !important;
    }
    @media (max-width: 1024px) {
      .deals-fixed-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
      }
    }
    @media (max-width: 600px) {
      .deals-fixed-grid {
        grid-template-columns: 1fr !important;
      }
    }
    .flash-slide-title {
      font-size: 22px;
      font-weight: 900;
      margin: 0 0 4px 0;
      color: #fff;
      line-height: 1.25;
    }
    .flash-slide-farmer {
      font-size: 12.5px;
      color: rgba(255,255,255,0.9);
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .flash-slide-price-row {
      display: flex;
      align-items: baseline;
      gap: 10px;
      flex-wrap: wrap;
      margin-bottom: 12px;
    }
    .flash-slide-price {
      font-size: 26px;
      font-weight: 900;
      color: #fef08a;
      text-shadow: 0 2px 8px rgba(0,0,0,0.25);
    }
    .flash-slide-orig-price {
      font-size: 15px;
      color: rgba(255,255,255,0.7);
      text-decoration: line-through;
      font-weight: 600;
    }
    .flash-slide-btn {
      background: #fff;
      color: #0369a1;
      border-color: #fff;
      font-weight: 800;
      font-size: 12.5px;
      padding: 7px 20px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.18);
      border-radius: 8px;
      display: inline-block;
      text-decoration: none;
    }
    .flash-arrow-btn {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      z-index: 10;
      background: rgba(0,0,0,0.45);
      border: none;
      border-radius: 50%;
      width: 36px;
      height: 36px;
      color: #fff;
      cursor: pointer;
      font-size: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: background 0.2s;
    }
    .flash-arrow-btn:hover {
      background: rgba(0,0,0,0.75);
    }
    .flash-arrow-prev { left: 12px; }
    .flash-arrow-next { right: 12px; }
    .flash-bottom-bar {
      background: #0284c7;
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 10px 20px;
      border-top: 1px solid rgba(255,255,255,0.15);
    }
    .flash-fallback-hero {
      padding: 28px 28px;
      background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
      color: #fff;
      min-height: 180px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 20px;
      flex-wrap: wrap;
    }
    .flash-fallback-badge {
      width: 190px;
      height: 130px;
      border-radius: 12px;
      background: rgba(255,255,255,0.12);
      border: 1px solid rgba(255,255,255,0.25);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 12px;
      backdrop-filter: blur(6px);
    }

    @media (max-width: 600px) {
      .flash-slide-content {
        padding: 12px 14px;
        gap: 12px;
        min-height: 145px;
      }
      .flash-slide-img-box {
        width: 105px;
        height: 105px;
        min-width: 105px;
        border-radius: 10px;
      }
      .flash-slide-info {
        min-width: 0;
      }
      .flash-slide-title {
        font-size: 15px;
        margin-bottom: 2px;
        line-height: 1.2;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
      }
      .flash-slide-farmer {
        font-size: 11px;
        margin-bottom: 4px;
      }
      .flash-slide-timer {
        padding: 2px 6px !important;
        font-size: 10px !important;
        margin-bottom: 4px !important;
      }
      .flash-slide-timer .flash-countdown {
        font-size: 12px !important;
      }
      .flash-slide-price-row {
        gap: 6px;
        margin-bottom: 6px;
      }
      .flash-slide-price {
        font-size: 17px;
      }
      .flash-slide-orig-price {
        font-size: 11.5px;
      }
      .flash-slide-btn {
        font-size: 11px;
        padding: 4px 12px;
      }
      .flash-arrow-btn {
        width: 26px;
        height: 26px;
        font-size: 15px;
      }
      .flash-arrow-prev { left: 4px; }
      .flash-arrow-next { right: 4px; }
      .flash-bottom-bar {
        padding: 6px 12px;
      }
      .flash-bottom-bar a {
        font-size: 11px !important;
        padding: 5px 12px !important;
      }
      .flash-fallback-hero {
        padding: 16px 14px;
        gap: 12px;
      }
      .flash-fallback-hero h1 {
        font-size: 20px !important;
      }
      .flash-fallback-hero p {
        font-size: 12.5px !important;
      }
      .flash-fallback-badge {
        width: 100%;
        height: auto;
        padding: 10px;
        flex-direction: row;
        gap: 10px;
      }
      .flash-fallback-badge > div:first-child {
        font-size: 24px !important;
        margin-bottom: 0 !important;
      }
    }
  </style>
  <div class="section-card" style="margin-top:0; padding:0; overflow:hidden; border-radius:18px; position:relative; box-shadow:0 10px 30px rgba(2,132,199,0.18); border:none;">

    <?php if (empty($boosted_products)) { ?>
      <!-- Fallback Hero when no boosted products -->
      <div class="flash-fallback-hero">
        <div style="flex:1; min-width:240px; max-width:620px;">
          <div style="background:rgba(255,255,255,0.2); backdrop-filter:blur(4px); display:inline-flex; align-items:center; gap:6px; padding:3px 12px; border-radius:999px; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px;">
            Santa Fe Fresh Catch Marketplace
          </div>
          <h1 style="font-size:24px; font-weight:900; margin:0 0 6px 0; line-height:1.25; color:#fff;">
            Direct Aquatic Harvest from Boat to Table
          </h1>
          <p style="font-size:13.5px; color:rgba(255,255,255,0.9); margin:0; line-height:1.45;">
            Fresh Bangus, live Hipon, crabs, and seaweeds harvested daily by verified local fisherfolk in Santa Fe, Romblon.
          </p>
        </div>

      </div>
    <?php } else { ?>
      <!-- Real Interactive Swipable Carousel with Boosted Products -->
      <div class="flash-carousel flash-carousel-container" id="flashCarousel">
        <div class="flash-track" id="flashTrack" style="display:flex; transition:transform 0.4s cubic-bezier(0.2, 0, 0, 1); will-change:transform;">
          <?php foreach ($boosted_products as $i => $bp) {
            $orig_price = (float)$bp['price'];
            $is_discounted = is_product_discounted($orig_price, $bp['discounted_price'] ?? null, $bp['selling_deadline'] ?? null);
            $disc_price = $is_discounted ? (float)$bp['discounted_price'] : $orig_price;
            $pct_off = ($is_discounted && $orig_price > 0) ? round((1 - ($disc_price / $orig_price)) * 100) : 0;
            $discount_secs = ($is_discounted && !empty($bp['selling_deadline'])) ? max(0, strtotime($bp['selling_deadline']) - time()) : 0;
            $d_h = floor($discount_secs / 3600);
            $d_m = floor(($discount_secs % 3600) / 60);
            $d_s = $discount_secs % 60;
          ?>
          <div class="flash-slide" data-index="<?php echo $i; ?>"
               style="flex:0 0 100%; position:relative; overflow:hidden;">
            <!-- Ambient Oceanic Background with Soft Contrast Overlay -->
            <div style="position:absolute; inset:0; background:linear-gradient(135deg, #0369a1 0%, #0284c7 50%, #075985 100%);"></div>
            <?php if (!empty($bp['image_url'])) { ?>
              <div style="position:absolute; inset:0; background:url('<?php echo BASE_URL . '/' . e($bp['image_url']); ?>') center/cover no-repeat; filter:blur(16px) brightness(0.25); transform:scale(1.1);"></div>
            <?php } ?>
            <div style="position:absolute; inset:0; background:linear-gradient(90deg, rgba(3, 105, 161, 0.94) 0%, rgba(2, 132, 199, 0.82) 55%, rgba(7, 89, 133, 0.90) 100%);"></div>

            <!-- Slide Content -->
            <div class="flash-slide-content">
              
              <!-- 2. Product Image Container on the Left Side of Banner -->
              <div class="flash-slide-img-box">
                <?php if (!empty($bp['image_url'])) { ?>
                  <img src="<?php echo BASE_URL . '/' . e($bp['image_url']); ?>" alt="<?php echo e($bp['name']); ?>" style="width:100%; height:100%; object-fit:cover; display:block;">
                <?php } else { ?>
                  <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; font-size:40px; color:rgba(255,255,255,0.7);">
                  </div>
                <?php } ?>
                <?php if ($pct_off > 0) { ?>
                  <div style="position:absolute; top:8px; left:8px; background:#dc2626; color:#fff; padding:2px 7px; border-radius:5px; font-weight:900; font-size:10.5px; box-shadow:0 2px 6px rgba(0,0,0,0.35); z-index:2;">
                    -<?php echo $pct_off; ?>% OFF
                  </div>
                <?php } ?>
                <!-- Featured Fresh Catch aligned on the right on top of the image -->
                <div style="position:absolute; top:8px; right:8px; background:rgba(15,23,42,0.75); backdrop-filter:blur(4px); border:1px solid rgba(255,255,255,0.3); color:#fef08a; padding:2px 7px; border-radius:5px; font-weight:800; font-size:9.5px; box-shadow:0 2px 6px rgba(0,0,0,0.35); z-index:2; display:flex; align-items:center; gap:3px; text-transform:uppercase; letter-spacing:0.3px;">
                  Featured Fresh Catch
                </div>
              </div>

              <!-- Product Information on Right Side of Image -->
              <div class="flash-slide-info">
                <h2 class="flash-slide-title">
                  <?php echo e($bp['name']); ?>
                </h2>
                
                <div class="flash-slide-farmer">
                  <span>Harvested by <strong><?php echo e($bp['farmer_full_name'] ?? $bp['farmer_name']); ?></strong></span>
                </div>

                <!-- 1. Countdown Timer (Only when farmer discounted the product and set a deadline) -->
                <?php if ($discount_secs > 0) { ?>
                <div class="flash-slide-timer" style="display:inline-flex; align-items:center; gap:6px; background:rgba(0,0,0,0.42); border:1px solid rgba(255,255,255,0.25); backdrop-filter:blur(6px); border-radius:6px; padding:3px 10px; margin-bottom:8px;">
                  <span style="font-size:10.5px; font-weight:800; color:#fef08a; text-transform:uppercase; letter-spacing:0.5px;">Sale Ends In:</span>
                  <span class="flash-countdown"
                        data-ends="<?php echo (int)strtotime($bp['selling_deadline']); ?>"
                        style="font-size:14px; font-weight:900; letter-spacing:1px; font-family:monospace; color:#ffffff;">
                    <?php printf('%02d:%02d:%02d', $d_h, $d_m, $d_s); ?>
                  </span>
                </div>
                <?php } ?>

                <!-- 3. Price Display -->
                <div class="flash-slide-price-row">
                  <?php if ($is_discounted) { ?>
                    <span class="flash-slide-price">
                      ₱<?php echo number_format($disc_price, 2); ?>
                    </span>
                    <span class="flash-slide-orig-price">
                      ₱<?php echo number_format($orig_price, 2); ?>
                    </span>
                    <?php if ($pct_off > 0) { ?>
                      <span style="background:#dc2626; color:#fff; font-size:10.5px; font-weight:900; padding:2px 6px; border-radius:999px;">
                        Save <?php echo $pct_off; ?>%
                      </span>
                    <?php } ?>
                  <?php } else { ?>
                    <span class="flash-slide-price" style="color:#ffffff;">
                      ₱<?php echo number_format($orig_price, 2); ?>
                    </span>
                  <?php } ?>
                </div>

                <div>
                  <a href="<?php echo BASE_URL; ?>/product_detail.php?product_id=<?php echo (int)$bp['product_id']; ?>"
                     class="flash-slide-btn">
                    View Product
                  </a>
                </div>
              </div>

            </div>
          </div>
          <?php } ?>
        </div>

        <!-- Arrows -->
        <?php if (count($boosted_products) > 1) { ?>
        <button onclick="flashMove(-1)" aria-label="Previous Slide" class="flash-arrow-btn flash-arrow-prev">
          &#8249;
        </button>
        <button onclick="flashMove(1)" aria-label="Next Slide" class="flash-arrow-btn flash-arrow-next">
          &#8250;
        </button>
        <?php } ?>
      </div>

      <!-- 4. Carousel Navigation Bar with Highly Visible See All Flash Sales CTA Button -->
      <div class="flash-bottom-bar">
        <div id="flashDots" style="display:flex; gap:6px; align-items:center;">
          <?php foreach ($boosted_products as $i => $bp) { ?>
            <button onclick="flashGoTo(<?php echo $i; ?>)" aria-label="Go to slide <?php echo $i+1; ?>"
              class="flash-dot" data-dot="<?php echo $i; ?>"
              style="width:<?php echo $i===0?'24':'8'; ?>px; height:6px; border-radius:3px; background:<?php echo $i===0?'#fff':'rgba(255,255,255,0.4)'; ?>; border:none; cursor:pointer; padding:0; transition:all 0.25s;">
            </button>
          <?php } ?>
        </div>
        <a href="<?php echo BASE_URL; ?>/flash_sales.php"
           style="display:inline-flex; align-items:center; gap:8px; background:#fef08a; color:#854d0e; font-weight:900; font-size:12.5px; text-transform:uppercase; letter-spacing:0.5px; padding:6px 16px; border-radius:999px; text-decoration:none; box-shadow:0 3px 10px rgba(0,0,0,0.18); transition:all 0.2s;"
           onmouseover="this.style.background='#fff'; this.style.color='#0369a1'; this.style.transform='translateY(-1px)';"
           onmouseout="this.style.background='#fef08a'; this.style.color='#854d0e'; this.style.transform='translateY(0)';">
          See All Flash Sales
        </a>
      </div>
    <?php } ?>
  </div>

  <!-- ===== TODAY'S DEALS ===== -->
  <div class="section-card" style="margin-top:18px; padding:22px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; flex-wrap:wrap; gap:12px;">
      <div>
        <div style="display:flex; align-items:center; gap:8px;">
          <h2 style="margin:0; font-size:20px; font-weight:800;">Today's Deals</h2>
          <span style="background:#fee2e2; color:#dc2626; font-size:11px; font-weight:800; padding:2px 8px; border-radius:999px;">
            <?php echo date('M d, Y'); ?>
          </span>
        </div>
        <div class="small-muted" style="font-size:13px; margin-top:2px;">Special discounted prices posted by Santa Fe aquatic farmers</div>
      </div>
      <a href="<?php echo BASE_URL; ?>/deals.php" class="btn btn-primary btn-sm" style="background:#dc2626; border-color:#dc2626; font-weight:700;">
        See All Deals
      </a>
    </div>

    <?php if (empty($todays_deals)) { ?>
      <div style="text-align:center; padding:36px 20px; background:#f8fafc; border:1px dashed var(--border); border-radius:12px;">
        <div style="font-size:36px; margin-bottom:8px;"></div>
        <div style="font-weight:700; font-size:15px; margin-bottom:4px;">No Deals Posted Today</div>
        <div class="small-muted" style="max-width:400px; margin:0 auto; font-size:13px;">
          Santa Fe farmers haven't posted discounted listings yet today. Check back later for fresh promotions!
        </div>
      </div>
    <?php } else { ?>
      <div class="deals-fixed-grid">
        <?php foreach ($todays_deals as $p) {
          $pct_off = $p['price'] > 0 ? round((1 - $p['discounted_price'] / $p['price']) * 100) : 0;
          $secs_left = !empty($p['selling_deadline']) ? max(0, strtotime($p['selling_deadline']) - time()) : 0;
          $hrs_left = floor($secs_left / 3600);
          $mins_left = floor(($secs_left % 3600) / 60);
        ?>
          <div class="market-card" style="display:flex; flex-direction:column; justify-content:space-between; position:relative; border-radius:14px; overflow:hidden; border:1px solid var(--border);">
            <!-- % OFF Badge -->
            <div style="position:absolute; top:10px; left:10px; background:#dc2626; color:#fff; font-size:11px; font-weight:900; padding:3px 8px; border-radius:6px; z-index:2; box-shadow:0 2px 6px rgba(220,38,38,0.3);">
              -<?php echo $pct_off; ?>%
            </div>

            <div class="img" style="height:170px; overflow:hidden; background:#f1f5f9; position:relative;">
              <?php if (!empty($p['image_url'])) { ?>
                <img src="<?php echo BASE_URL . '/' . e($p['image_url']); ?>" alt="<?php echo e($p['name']); ?>" style="width:100%; height:100%; object-fit:cover;">
              <?php } else { ?>
                <div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--muted);font-size:13px;">Fresh Catch</div>
              <?php } ?>
            </div>

            <div class="body" style="padding:14px; flex:1; display:flex; flex-direction:column; justify-content:space-between;">
              <div>
                <div class="title" style="font-size:15px; font-weight:800; margin-bottom:3px; line-height:1.3;"><?php echo e($p['name']); ?></div>
                <div style="font-size:12px; color:var(--muted);"><?php echo e($p['farmer_name']); ?> &bull; <?php echo e($p['category_name']); ?></div>

                <?php if ((float)$p['avg_rating'] > 0) { ?>
                  <div style="font-size:12px; color:#d97706; margin-top:2px;"> <?php echo number_format((float)$p['avg_rating'], 1); ?> (<?php echo (int)$p['rating_cnt']; ?>)</div>
                <?php } ?>
              </div>

              <div style="margin-top:12px;">
                <!-- Price: Discounted first, crossed original second -->
                <div style="display:flex; align-items:baseline; gap:8px; flex-wrap:wrap; margin-bottom:4px;">
                  <span style="font-size:20px; font-weight:900; color:#dc2626;">
                    ₱<?php echo number_format((float)$p['discounted_price'], 2); ?>
                  </span>
                  <span style="font-size:12px; color:var(--muted); text-decoration:line-through;">
                    ₱<?php echo number_format((float)$p['price'], 2); ?>
                  </span>
                </div>

                <!-- Stock and Timer beside each other -->
                <div style="font-size:12px; color:var(--muted); margin-bottom:10px; display:flex; align-items:center; justify-content:space-between; gap:6px; flex-wrap:wrap;">
                  <span style="font-size:12px; color:var(--muted);">Stock: <?php echo (int)$p['stock_quantity']; ?> kg</span>
                  <?php if ($secs_left > 0) { ?>
                    <span style="font-size:11px; color:#92400e; background:#fff7ed; border:1px solid #fed7aa; padding:2px 7px; border-radius:4px; font-weight:700;">
                      Ends in <?php echo $hrs_left > 0 ? "{$hrs_left}h {$mins_left}m" : "{$mins_left} min"; ?>
                    </span>
                  <?php } ?>
                </div>

                <a href="<?php echo BASE_URL; ?>/product_detail.php?product_id=<?php echo (int)$p['product_id']; ?>"
                   class="btn btn-primary btn-block" style="text-align:center; font-size:13px; background:#dc2626; border-color:#dc2626; font-weight:700;">
                  View Deal
                </a>
              </div>
            </div>
          </div>
        <?php } ?>
      </div>
    <?php } ?>
  </div>

  <!-- ===== 5. INFORMATION HUB CONTAINER ===== -->
  <style>
    .infohub-pub-row:hover {
      border-color: #93c5fd !important;
      box-shadow: 0 4px 14px rgba(2, 132, 199, 0.08);
    }
    @media (max-width: 520px) {
      .infohub-pub-row {
        flex-direction: column;
        align-items: flex-start !important;
      }
      .infohub-pub-row > div:first-child {
        width: 100% !important;
        height: 140px !important;
      }
    }
  </style>
  <div class="section-card" style="margin-top:18px; padding:22px; border-radius:16px;">
    <div style="margin-bottom:14px;">
      <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:800; color:var(--text);">SeaLink Information Hub</h2>
      <div class="small-muted" style="font-size:13px;">Educational guides, research studies, and Santa Fe seafood recipes</div>
    </div>

    <!-- 1-Column Publication List (Not scrollable) -->
    <div class="infohub-list" style="display:flex; flex-direction:column; gap:12px;">
      <?php if (empty($hub_publications)) { ?>
        <div style="text-align:center; padding:30px 20px; color:var(--muted); font-weight:600;">
          No publications available at the moment.
        </div>
      <?php } else { ?>
        <?php foreach ($hub_publications as $item) {
          $art_link = BASE_URL . '/infohub/read.php?article_id=' . (int)$item['info_hub_id'];
          $cat_badge_color = '#0284c7';
          $cat_badge_bg = '#e0f2fe';
          if (($item['category'] ?? '') === 'Recipes') {
              $cat_badge_color = '#b45309';
              $cat_badge_bg = '#fef3c7';
          } elseif (($item['category'] ?? '') === 'Research') {
              $cat_badge_color = '#4338ca';
              $cat_badge_bg = '#e0e7ff';
          }
        ?>
          <div class="infohub-pub-row" style="background:#f8fafc; border:1px solid var(--border); border-radius:12px; padding:12px 16px; display:flex; align-items:center; gap:16px; transition:all 0.2s;">
            <!-- Left Thumbnail Image -->
            <div style="width:110px; height:82px; min-width:110px; border-radius:10px; overflow:hidden; background:#e2e8f0; flex-shrink:0;">
              <?php if (!empty($item['image_url'])) { ?>
                <img src="<?php echo BASE_URL . '/' . e($item['image_url']); ?>" alt="" style="width:100%; height:100%; object-fit:cover; display:block;">
              <?php } else { ?>
                <div style="display:flex; align-items:center; justify-content:center; height:100%; font-size:28px;"></div>
              <?php } ?>
            </div>

            <!-- Body Details -->
            <div style="flex:1; min-width:0;">
              <div style="display:flex; align-items:center; gap:8px; margin-bottom:5px;">
                <span style="font-size:11px; font-weight:800; background:<?php echo $cat_badge_bg; ?>; color:<?php echo $cat_badge_color; ?>; padding:2px 8px; border-radius:999px; text-transform:uppercase;">
                  <?php echo e($item['category'] ?? 'Article'); ?>
                </span>
                <span style="font-size:12px; color:var(--muted);">
                  <?php echo date('M d, Y', strtotime($item['created_at'])); ?>
                </span>
              </div>

              <h3 style="margin:0 0 4px 0; font-size:15px; font-weight:800; line-height:1.35; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                <a href="<?php echo $art_link; ?>" style="color:inherit; text-decoration:none;">
                  <?php echo e($item['title']); ?>
                </a>
              </h3>

              <p style="margin:0; font-size:12.5px; color:var(--muted); line-height:1.4; overflow:hidden; display:-webkit-box; -webkit-line-clamp:1; -webkit-box-orient:vertical;">
                <?php echo e($item['excerpt'] ?? ''); ?>...
              </p>
            </div>

            <!-- Action Button -->
            <div style="flex-shrink:0;">
              <a href="<?php echo $art_link; ?>" class="btn btn-sm btn-secondary" style="font-weight:700; font-size:12px; white-space:nowrap; padding:6px 14px;">
                Read
              </a>
            </div>
          </div>
        <?php } ?>
      <?php } ?>
    </div>

    <!-- Bottom CTA Button -->
    <div style="text-align:center; margin-top:16px; padding-top:14px; border-top:1px solid var(--border);">
      <a href="<?php echo BASE_URL; ?>/infohub/index.php" class="btn btn-primary" style="font-weight:800; font-size:13.5px; padding:9px 24px; border-radius:999px;">
        See More Publications
      </a>
    </div>
  </div>

  <!-- ===== 6. COMMUNITY FORUM PREVIEW ===== -->
  <div class="section-card" style="margin-top:18px; padding:22px; border-radius:16px;">
    <div style="margin-bottom:16px;">
      <h2 style="margin:0 0 4px 0; font-size:20px; font-weight:800; color:var(--text);">Community Forum</h2>
      <div class="small-muted" style="font-size:13px;">Connect, ask questions, and share aquaculture techniques with Santa Fe fisherfolk and buyers</div>
    </div>

    <!-- Forum Discussions Preview List (Not scrollable) -->
    <div class="forum-preview-list" style="display:flex; flex-direction:column; gap:12px;">
      <?php if (empty($forum_preview_posts)) { ?>
        <div style="text-align:center; padding:32px 20px; background:#f8fafc; border:1px dashed var(--border); border-radius:12px;">
          <div style="font-size:32px; margin-bottom:6px;"></div>
          <div style="font-weight:700; font-size:14px; margin-bottom:2px;">No Community Discussions Yet</div>
          <div class="small-muted" style="font-size:12.5px;">Be the first to ask questions or discuss local aquatic techniques with Santa Fe fisherfolk!</div>
        </div>
      <?php } else { ?>
        <?php foreach ($forum_preview_posts as $fp) {
          $forum_link = is_logged_in()
              ? (BASE_URL . '/forum/post.php?post_id=' . (int)$fp['post_id'])
              : 'javascript:openGuestPromptModal(\'Forum\')';
        ?>
          <div class="publication-card-row" style="background:#f8fafc; border:1px solid var(--border); border-radius:12px; padding:14px 18px; display:flex; align-items:center; gap:16px; transition:all 0.2s;">
            <!-- Left Category / Discussion Icon Badge -->
            <div style="width:54px; height:54px; min-width:54px; border-radius:12px; background:linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%); color:#4338ca; display:flex; align-items:center; justify-content:center; font-size:24px; flex-shrink:0;">
            </div>

            <!-- Body Details -->
            <div style="flex:1; min-width:0;">
              <div style="display:flex; align-items:center; gap:8px; margin-bottom:5px; flex-wrap:wrap;">
                <span style="font-size:11px; font-weight:800; background:#e0e7ff; color:#4338ca; padding:2px 8px; border-radius:999px; text-transform:uppercase;">
                  <?php echo e($fp['category'] ?? 'General'); ?>
                </span>
                <span style="font-size:12px; color:var(--muted);">
                  By <strong><?php echo e($fp['author_name']); ?></strong> (<?php echo ucfirst(e($fp['user_role'] ?? 'Member')); ?>) &bull; <?php echo date('M d, Y', strtotime($fp['created_at'])); ?>
                </span>
                <span style="font-size:11.5px; color:#0284c7; font-weight:700; background:#e0f2fe; padding:1px 7px; border-radius:999px;">
                   <?php echo (int)$fp['comment_count']; ?> repl<?php echo (int)$fp['comment_count'] === 1 ? 'y' : 'ies'; ?>
                </span>
              </div>

              <h3 style="margin:0 0 4px 0; font-size:15px; font-weight:800; line-height:1.35; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                <a href="<?php echo $forum_link; ?>" style="color:inherit; text-decoration:none;">
                  <?php echo e($fp['title']); ?>
                </a>
              </h3>

              <p style="margin:0; font-size:12.5px; color:var(--muted); line-height:1.4; overflow:hidden; display:-webkit-box; -webkit-line-clamp:1; -webkit-box-orient:vertical;">
                <?php echo e($fp['excerpt'] ?? ''); ?>...
              </p>
            </div>

            <!-- Action Button -->
            <div style="flex-shrink:0;">
              <a href="<?php echo $forum_link; ?>" class="btn btn-sm btn-secondary" style="font-weight:700; font-size:12px; white-space:nowrap; padding:6px 14px;">
                View Thread
              </a>
            </div>
          </div>
        <?php } ?>
      <?php } ?>
    </div>

    <!-- Bottom CTA Button -->
    <div style="text-align:center; margin-top:16px; padding-top:14px; border-top:1px solid var(--border);">
      <?php if (is_logged_in()) { ?>
        <a href="<?php echo BASE_URL; ?>/forum/index.php" class="btn btn-secondary" style="font-weight:800; font-size:13.5px; padding:9px 24px; border-radius:999px;">
          Open Community Forum
        </a>
      <?php } else { ?>
        <button type="button" onclick="openGuestPromptModal('Forum')" class="btn btn-secondary" style="font-weight:800; font-size:13.5px; padding:9px 24px; border-radius:999px; cursor:pointer;">
          Open Community Forum
        </button>
      <?php } ?>
    </div>
  </div>


  <!-- ===== 7. FOOTER + EMAIL ALERTS ===== -->
  <footer class="section-card" style="margin-top:18px; padding:28px 24px; background:#f8fafc; border-radius:18px; border:1px solid var(--border);">
    <div style="max-width:620px; margin:0 auto 24px auto; text-align:center;">
      <div style="font-weight:800; font-size:17px; color:var(--text); margin-bottom:4px;">Fresh Catch Alerts via Email</div>
      <div class="small-muted" style="font-size:13px; margin-bottom:14px;">Receive instant alerts when fresh Bangus, Crabs, or live Shrimps arrive from Santa Fe boats.</div>
      <form method="POST" action="" style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap;">
        <input type="email" name="guest_email" placeholder="Enter your email address" required
               style="padding:10px 16px; border:1px solid var(--border); border-radius:10px; min-width:260px; font-size:14px; background:#fff;">
        <button type="submit" class="btn btn-primary" style="font-weight:800; padding:10px 20px;">Subscribe Alerts</button>
      </form>
    </div>

    <hr style="border:0; border-top:1px solid var(--border); margin:22px 0;">

    <div style="display:flex; justify-content:space-around; align-items:center; flex-wrap:wrap; gap:20px; text-align:center;">
      <div style="display:flex; align-items:center; gap:10px;">
        <span style="font-size:24px;"></span>
        <div style="text-align:left;">
          <div style="font-weight:800; font-size:13px;">Municipal Office</div>
          <div class="small-muted" style="font-size:12px;">Santa Fe, Romblon, Philippines</div>
        </div>
      </div>
      <div style="display:flex; align-items:center; gap:10px;">
        <span style="font-size:24px;"></span>
        <div style="text-align:left;">
          <div style="font-weight:800; font-size:13px;">Email Support</div>
          <div class="small-muted" style="font-size:12px;">sealink@support.com</div>
        </div>
      </div>
      <div style="display:flex; align-items:center; gap:10px;">
        <span style="font-size:24px;"></span>
        <div style="text-align:left;">
          <div style="font-weight:800; font-size:13px;">Facebook Page</div>
          <div class="small-muted" style="font-size:12px;">facebook.com/sealink.santafe</div>
        </div>
      </div>
    </div>

    <div style="text-align:center; margin-top:22px; font-size:12px; color:var(--muted);">
      &copy; <?php echo date('Y'); ?> SeaLink Santa Fe. Connecting Certified Aquatic Farmers &amp; Buyers.
    </div>
  </footer>

</main>

<script>
// ===== FLASH SALES CAROUSEL =====
(function() {
  var track = document.getElementById('flashTrack');
  if (!track) return;

  var slides = track.querySelectorAll('.flash-slide');
  var total = slides.length;
  if (total < 2) return;

  var current = 0;
  var autoTimer = null;
  var startX = 0;
  var isDragging = false;

  function goTo(idx) {
    current = (idx + total) % total;
    track.style.transform = 'translateX(-' + (current * 100) + '%)';
    document.querySelectorAll('.flash-dot').forEach(function(d, i) {
      var active = i === current;
      d.style.width = active ? '24px' : '8px';
      d.style.background = active ? '#fff' : 'rgba(255,255,255,0.4)';
    });
  }

  window.flashMove = function(dir) { goTo(current + dir); resetAuto(); };
  window.flashGoTo = function(i) { goTo(i); resetAuto(); };

  function resetAuto() {
    clearInterval(autoTimer);
    autoTimer = setInterval(function() { goTo(current + 1); }, 5000);
  }
  autoTimer = setInterval(function() { goTo(current + 1); }, 5000);

  // Touch / mouse swipe
  var carousel = document.getElementById('flashCarousel');
  carousel.addEventListener('mousedown', function(e) { startX = e.clientX; isDragging = true; });
  carousel.addEventListener('mousemove', function(e) { if (isDragging) e.preventDefault(); });
  carousel.addEventListener('mouseup', function(e) {
    if (!isDragging) return;
    var diff = e.clientX - startX;
    if (Math.abs(diff) > 40) { flashMove(diff < 0 ? 1 : -1); }
    isDragging = false;
  });
  carousel.addEventListener('mouseleave', function() { isDragging = false; });

  carousel.addEventListener('touchstart', function(e) { startX = e.touches[0].clientX; }, {passive: true});
  carousel.addEventListener('touchend', function(e) {
    var diff = e.changedTouches[0].clientX - startX;
    if (Math.abs(diff) > 40) flashMove(diff < 0 ? 1 : -1);
  });
})();

// ===== FLASH SALE COUNTDOWN TIMERS =====
(function() {
  function tick() {
    var now = Math.floor(Date.now() / 1000);
    document.querySelectorAll('.flash-countdown[data-ends]').forEach(function(el) {
      var ends = parseInt(el.dataset.ends, 10);
      var secs = Math.max(0, ends - now);
      var h = Math.floor(secs / 3600);
      var m = Math.floor((secs % 3600) / 60);
      var s = secs % 60;
      el.textContent = ('0'+h).slice(-2) + ':' + ('0'+m).slice(-2) + ':' + ('0'+s).slice(-2);
      if (secs <= 300) el.style.color = '#ff4444';
    });
  }
  tick();
  setInterval(tick, 1000);
})();
</script>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>