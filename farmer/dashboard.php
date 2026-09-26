<?php
/**
 * SeaLink Web Application
 * File: /farmer/dashboard.php
 * Purpose: Farmer Home Dashboard (Welcome, KPIs, Recent Notifications preview, Recent Customer Feedback preview, Manage Products preview).
 * Connected To:
 *  - /includes/nav_farmer.php (Home tab)
 *  - /farmer/manage_products.php (Manage Products tab)
 *  - /farmer/product_gallery.php
 *  - /farmer/orders.php
 */

require_once __DIR__ . '/../includes/auth_check.php';
check_access('farmer');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$active_tab  = 'home';
$page_title  = "Farmer Home - SeaLink";
require_once __DIR__ . '/../includes/layout_top.php';

$farmer_id   = (int)($_SESSION['user_id'] ?? 0);
$is_verified = (($_SESSION['verification_status'] ?? '') === 'Verified');

/* ========== KPI QUERIES ========== */
// 1. Total Products
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM product_tbl WHERE farmer_id = ?");
mysqli_stmt_bind_param($stmt, "i", $farmer_id);
mysqli_stmt_execute($stmt);
$total_products = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'] ?? 0);
mysqli_stmt_close($stmt);

// 2. Total Orders
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM order_tbl WHERE farmer_id = ?");
mysqli_stmt_bind_param($stmt, "i", $farmer_id);
mysqli_stmt_execute($stmt);
$total_orders = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'] ?? 0);
mysqli_stmt_close($stmt);

// 3. Total Customers
$stmt = mysqli_prepare($conn, "SELECT COUNT(DISTINCT buyer_id) AS c FROM order_tbl WHERE farmer_id = ?");
mysqli_stmt_bind_param($stmt, "i", $farmer_id);
mysqli_stmt_execute($stmt);
$total_customers = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'] ?? 0);
mysqli_stmt_close($stmt);

// 4. Total Revenue
$stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(total_amount), 0) AS total FROM order_tbl WHERE farmer_id = ? AND order_status = 'Completed'");
mysqli_stmt_bind_param($stmt, "i", $farmer_id);
mysqli_stmt_execute($stmt);
$total_revenue = (float)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'] ?? 0);
mysqli_stmt_close($stmt);

/* ========== RECENT NOTIFICATIONS (TOP 5) ========== */
$recent_notifs = [];
$sql = "
SELECT * FROM (
  SELECT
    o.order_date AS ts,
    'Order' AS type,
    CONCAT('New Order #', o.order_id, ' from ', b.full_name, ' (₱', o.total_amount, ')') AS title,
    CONCAT('Status: ', o.order_status, '\nBuyer: ', b.full_name, '\nTotal: ₱', o.total_amount, '\nFulfillment: ', o.fulfillment_type) AS body
  FROM order_tbl o
  JOIN buyer_tbl b ON b.buyer_id = o.buyer_id
  WHERE o.farmer_id = ?

  UNION ALL

  SELECT
    m.sent_at AS ts,
    'Message' AS type,
    CONCAT('Message from ', b.full_name) AS title,
    m.content AS body
  FROM message_tbl m
  JOIN buyer_tbl b ON b.buyer_id = m.buyer_id
  WHERE m.farmer_id = ? AND m.sender_type = 'Buyer'

  UNION ALL

  SELECT
    s.updated_at AS ts,
    'Support' AS type,
    'Admin replied to your support message' AS title,
    CONCAT('Status: ', s.status, '\n\nAdmin Reply:\n', s.admin_reply) AS body
  FROM admin_support_tbl s
  WHERE s.farmer_id = ?
    AND s.admin_reply IS NOT NULL
    AND s.admin_reply <> ''
) x
ORDER BY x.ts DESC
LIMIT 5
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "iii", $farmer_id, $farmer_id, $farmer_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) $recent_notifs[] = $row;
mysqli_stmt_close($stmt);

/* ========== LOW STOCK & EXPIRING PRODUCTS ALERT ========== */
$low_stock_products = [];
$ls_stmt = mysqli_prepare($conn, "
    SELECT product_id, name, stock_quantity, image_url, selling_deadline, is_boosted
    FROM product_tbl
    WHERE farmer_id = ? AND status = 'Active' AND stock_quantity <= 3
    ORDER BY stock_quantity ASC
");
if ($ls_stmt) {
    mysqli_stmt_bind_param($ls_stmt, "i", $farmer_id);
    mysqli_stmt_execute($ls_stmt);
    $ls_res = mysqli_stmt_get_result($ls_stmt);
    while ($row = mysqli_fetch_assoc($ls_res)) $low_stock_products[] = $row;
    mysqli_stmt_close($ls_stmt);
}

/* ========== RECENT CUSTOMER FEEDBACK (TOP 4) ========== */
$feedbacks = [];
$sql = "
SELECT f.feedback_id, f.rating, f.comment, f.created_at, b.full_name, b.username, p.name AS product_name, p.image_url AS product_image
FROM feedback_tbl f
JOIN buyer_tbl b ON b.buyer_id = f.buyer_id
JOIN product_tbl p ON p.product_id = f.product_id
WHERE p.farmer_id = ?
ORDER BY f.created_at DESC
LIMIT 4
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $farmer_id);
mysqli_stmt_execute($stmt);
$fb_res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($fb_res)) {
    $feedbacks[] = $row;
}
mysqli_stmt_close($stmt);

/* ========== PRODUCTS PREVIEW (TOP 4) ========== */
$sql = "
SELECT
  p.product_id,
  p.category_id,
  p.name,
  p.price,
  p.discounted_price,
  p.selling_deadline,
  p.stock_quantity,
  p.image_url,
  p.status,
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
LIMIT 4
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $farmer_id);
mysqli_stmt_execute($stmt);
$products_res = mysqli_stmt_get_result($stmt);

$preview_products = [];
while ($row = mysqli_fetch_assoc($products_res)) $preview_products[] = $row;
mysqli_stmt_close($stmt);
mysqli_close($conn);
?>

<style>
/* Keep KPI cards in a single row on smaller screens */
@media (max-width: 900px) {
  .farmer-dashboard .kpi-grid {
    display: grid !important;
    grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
    gap: 8px !important;
  }
  .farmer-dashboard .kpi-card {
    padding: 10px 6px !important;
  }
  .farmer-dashboard .kpi-card .value {
    font-size: 16px !important;
  }
  .farmer-dashboard .kpi-card .label {
    font-size: 11px !important;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
}
@media (max-width: 480px) {
  .farmer-dashboard .kpi-grid {
    display: grid !important;
    grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
    gap: 5px !important;
  }
  .farmer-dashboard .kpi-card {
    padding: 8px 3px !important;
  }
  .farmer-dashboard .kpi-card .value {
    font-size: 13px !important;
  }
  .farmer-dashboard .kpi-card .label {
    font-size: 9px !important;
    letter-spacing: -0.2px;
  }
}
</style>

<main class="dashboard-content farmer-dashboard">

  <!-- Header & KPI Overview Panel -->
  <section class="section-card">
    <section class="dashboard-hero">
      <h1 class="title">
        Welcome to SeaLink, <?php echo e($_SESSION['full_name'] ?? ($_SESSION['username'] ?? 'Farmer')); ?>
      </h1>
      <div class="sub">Here is your farm store overview and recent activity.</div>
    </section>

    <!-- KPI Grid: Total Products, Total Orders, Total Customers, Total Revenue -->
    <div class="kpi-grid">
      <div class="kpi-card" style="text-align:center;">
        <div class="label">Total Products</div>
        <div class="value"><?php echo $total_products; ?></div>
      </div>

      <div class="kpi-card" style="text-align:center;">
        <div class="label">Total Orders</div>
        <div class="value"><?php echo $total_orders; ?></div>
      </div>

      <div class="kpi-card" style="text-align:center;">
        <div class="label">Total Customers</div>
        <div class="value"><?php echo $total_customers; ?></div>
      </div>

      <div class="kpi-card" style="text-align:center;">
        <div class="label">Total Revenue</div>
        <div class="value">₱<?php echo number_format($total_revenue, 2); ?></div>
      </div>
    </div>
  </section>

  <!-- Low Stock Alert (Own Container Card) -->
  <?php if (!empty($low_stock_products)) { ?>
    <div class="section-card" style="border-left: 4px solid #f59e0b; background:#fffbeb;">
      <div style="font-weight:800; font-size:15px; color:#b45309; display:flex; align-items:center; gap:8px;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
          <line x1="12" y1="9" x2="12" y2="13"/>
          <line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
        <span>Low Stock Alert</span>
      </div>
      <div class="small-muted" style="color:#92400e; margin-top:2px; font-size:13px;">
        The following products are running low in stock. Current inventory remaining:
      </div>
      <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:12px;">
        <?php foreach ($low_stock_products as $lsp) { ?>
          <div style="background:#fff; border:1px solid #fcd34d; padding:8px 12px; border-radius:10px; font-size:13px; display:inline-flex; align-items:center; gap:10px; box-shadow:0 2px 5px rgba(0,0,0,0.03);">
            <div style="width:38px; height:38px; border-radius:6px; overflow:hidden; background:#fef3c7; flex-shrink:0; display:flex; align-items:center; justify-content:center; border:1px solid #fed7aa;">
              <?php if (!empty($lsp['image_url'])) { ?>
                <img src="<?php echo BASE_URL . '/' . e($lsp['image_url']); ?>" alt="<?php echo e($lsp['name']); ?>" style="width:100%; height:100%; object-fit:cover; display:block;">
              <?php } else { ?>
                <span style="font-size:16px;">🐟</span>
              <?php } ?>
            </div>
            <div>
              <div style="font-weight:800; color:var(--text);"><?php echo e($lsp['name']); ?></div>
              <div style="color:#b45309; font-weight:800; font-size:12px;"><?php echo (int)$lsp['stock_quantity']; ?> kg left</div>
            </div>
          </div>
        <?php } ?>
      </div>
    </div>
  <?php } ?>

  <!-- Recent Notifications Preview (Top 5) -->
  <div class="section-card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
      <div>
        <div style="font-weight:800; font-size:16px;">Recent Notifications</div>
        <div class="small-muted">Latest activity and platform alerts</div>
      </div>
      <a class="btn btn-secondary btn-sm" href="<?php echo BASE_URL; ?>/notifications/index.php">View All</a>
    </div>

    <div id="notifContext"
        data-user-id="<?php echo (int)($_SESSION['user_id'] ?? 0); ?>"
        data-role="<?php echo e($_SESSION['user_role'] ?? ''); ?>"
        style="display:none;"></div>

    <div class="notif-list" style="margin-top:10px;">
      <?php if (count($recent_notifs) === 0) { ?>
        <div class="small-muted">No recent notifications.</div>
      <?php } ?>

      <?php foreach ($recent_notifs as $n) {
        $nid = sha1(($n['type'] ?? '') . '|' . ($n['title'] ?? '') . '|' . ($n['ts'] ?? ''));
      ?>
        <button type="button"
          class="notif-item"
          data-notif-id="<?php echo e($nid); ?>"
          data-notif-type="<?php echo e($n['type']); ?>"
          data-notif-title="<?php echo e($n['title']); ?>"
          data-notif-body="<?php echo e($n['body']); ?>"
          data-notif-ts="<?php echo e($n['ts']); ?>"
          onclick="openNotificationModal(this)">

          <div class="notif-top">
            <div class="notif-title"><?php echo e($n['title']); ?></div>
            <div class="notif-time"><?php echo date('M d, Y h:i A', strtotime($n['ts'])); ?></div>
          </div>

          <div class="notif-preview">Tap to view details</div>
        </button>
      <?php } ?>
    </div>
  </div>

  <!-- Recent Customer Feedback Preview (Switched to appear above Manage Products) -->
  <div class="section-card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:12px;">
      <div>
        <div style="font-weight:800; font-size:16px;">Recent Customer Feedback</div>
        <div class="small-muted">Latest reviews from verified buyers</div>
      </div>
      <a class="btn btn-secondary btn-sm" href="<?php echo BASE_URL; ?>/farmer/product_gallery.php">View Store Gallery</a>
    </div>

    <?php if (empty($feedbacks)) { ?>
      <div class="small-muted" style="padding:16px 0;">No customer reviews received yet.</div>
    <?php } else { ?>
      <div style="display:flex; flex-direction:column; gap:10px;">
        <?php foreach ($feedbacks as $fb) { ?>
          <div class="panel" style="border:1px solid var(--border); background:#fff; padding:12px 16px;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
              <div>
                <strong><?php echo e($fb['full_name']); ?></strong>
                <span class="small-muted">on product <strong><?php echo e($fb['product_name']); ?></strong></span>
              </div>
              <span class="small-muted" style="font-size:12px;"><?php echo date('M d, Y', strtotime($fb['created_at'])); ?></span>
            </div>

            <div style="color:#d9891c; font-size:14px; margin-top:4px;">
              <?php for ($i = 1; $i <= 5; $i++) echo ($i <= $fb['rating'] ? '★' : '☆'); ?>
              <span style="color:var(--text); font-size:12px; margin-left:6px; font-weight:700;"><?php echo (int)$fb['rating']; ?>/5</span>
            </div>

            <div style="margin-top:6px; font-size:14px; line-height:1.45;">
              <?php echo nl2br(e($fb['comment'])); ?>
            </div>
          </div>
        <?php } ?>
      </div>
    <?php } ?>
  </div>

  <!-- Manage Products Preview -->
  <div class="section-card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:12px;">
      <div>
        <div style="font-weight:800; font-size:16px;">Manage Products Preview</div>
        <div class="small-muted">Quick look at your recent listed products</div>
      </div>
      <a class="btn btn-primary btn-sm" href="<?php echo BASE_URL; ?>/farmer/manage_products.php">
        View All Products
      </a>
    </div>

    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Product</th>
            <th>Category</th>
            <th>Price</th>
            <th>Stock</th>
            <th>Sales</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($preview_products)) { ?>
            <tr><td colspan="6" class="small-muted" style="text-align:center; padding:20px;">No products listed yet. Go to Manage Products to add your first product.</td></tr>
          <?php } ?>

          <?php foreach ($preview_products as $p) { ?>
            <tr>
              <td>
                <div class="product-cell">
                  <div class="thumb">
                    <?php if (!empty($p['image_url'])) { ?>
                      <img src="<?php echo BASE_URL . '/' . e($p['image_url']); ?>" alt="">
                    <?php } ?>
                  </div>
                  <div>
                    <div style="font-weight:800;"><?php echo e($p['name']); ?></div>
                  </div>
                </div>
              </td>
              <td><?php echo e($p['category_name']); ?></td>
              <td>
                <?php if (is_product_discounted($p['price'], $p['discounted_price'] ?? null, $p['selling_deadline'] ?? null)) { 
                  $eff_price = get_product_effective_price($p['price'], $p['discounted_price'] ?? null, $p['selling_deadline'] ?? null);
                ?>
                  <div style="color:#dc2626; font-weight:800; font-size:14px;">₱<?php echo number_format((float)$eff_price, 2); ?></div>
                  <div style="font-size:11px; color:var(--muted); text-decoration:line-through;">₱<?php echo number_format((float)$p['price'], 2); ?></div>
                <?php } else { ?>
                  <span style="font-weight:700;">₱<?php echo number_format((float)$p['price'], 2); ?></span>
                <?php } ?>
              </td>
              <td><?php echo (int)$p['stock_quantity']; ?></td>
              <td><?php echo (int)$p['sold_qty']; ?></td>
              <td>
                <span class="badge <?php echo ($p['status'] === 'Active' ? 'active' : 'inactive'); ?>">
                  <?php echo ($p['status'] === 'Active' ? 'On Sale' : 'Out of Stock'); ?>
                </span>
              </td>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  </div>

</main>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>