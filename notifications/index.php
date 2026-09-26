<?php
/**
 * SeaLink Web Application
 * File: /notifications/index.php
 * Purpose: Notifications page for all roles (short, clear one-line titles + modal + read marking).
 * Connected To: /profile/index.php (Notifications link/button)
 * Uses:
 *  Farmer: order_tbl, order_item_tbl, product_tbl, buyer_tbl, message_tbl, admin_support_tbl, feedback_tbl
 *  Buyer:  order_tbl, order_item_tbl, product_tbl, farmer_tbl, message_tbl, admin_support_tbl
 *  Admin:  admin_support_tbl, farmer_tbl, buyer_tbl
 * Notes:
 *  - No deep links required; modal shows details
 *  - Read/unread is browser-only (localStorage) via base.js using #notifContext
 */

require_once __DIR__ . '/../includes/auth_check.php';
check_access(['farmer', 'buyer', 'Content Admin', 'User Admin']);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Auto-sync expired promotions in real-time
check_and_expire_promotions($conn);

/* ========== PAGE SETTINGS ========== */
$hide_search = true;
$active_tab  = '';
$page_title  = "Notifications - SeaLink";
require_once __DIR__ . '/../includes/layout_top.php';

/* ========== CONTEXT ========== */
$role    = $_SESSION['user_role'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);

$items = []; // ts, type, title, body

/* ========== HELPERS ========== */
function push_item(array &$items, $ts, $type, $title, $body): void
{
  if (!$ts) return;
  $items[] = [
    'ts'    => (string)$ts,
    'type'  => (string)$type,   // Order | Message | Support | Feedback | Verification
    'title' => (string)$title,  // WITHOUT repeating the type word
    'body'  => (string)$body,   // modal details
  ];
}

function safe_int($v): int { return (int)$v; }
function safe_float($v): float { return (float)$v; }

/* =========================================================
   FARMER NOTIFICATIONS
========================================================= */
if ($role === 'farmer') {

  /* ---------- ORDERS (include qty + product + buyer) ---------- */
  $stmt = mysqli_prepare($conn, "
    SELECT
      o.order_id,
      o.order_date AS ts,
      o.order_status,
      o.total_amount,
      b.full_name AS buyer_name,
      COALESCE(ps.total_qty, 0) AS total_qty,
      COALESCE(ps.first_product, 'Product') AS first_product,
      COALESCE(ps.item_count, 0) AS item_count
    FROM order_tbl o
    JOIN buyer_tbl b ON b.buyer_id = o.buyer_id
    LEFT JOIN (
      SELECT
        oi.order_id,
        SUM(oi.quantity) AS total_qty,
        SUBSTRING_INDEX(GROUP_CONCAT(p.name ORDER BY p.name SEPARATOR ', '), ', ', 1) AS first_product,
        COUNT(*) AS item_count
      FROM order_item_tbl oi
      JOIN product_tbl p ON p.product_id = oi.product_id
      GROUP BY oi.order_id
    ) ps ON ps.order_id = o.order_id
    WHERE o.farmer_id = ?
    ORDER BY o.order_date DESC
    LIMIT 60
  ");
  mysqli_stmt_bind_param($stmt, "i", $user_id);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  while ($r = mysqli_fetch_assoc($res)) {
    $qty = safe_int($r['total_qty']);
    $product = (string)$r['first_product'];
    $buyer = (string)$r['buyer_name'];
    $item_count = safe_int($r['item_count']);

    $extra = ($item_count > 1) ? (" (+" . ($item_count - 1) . " item(s))") : "";
    $title = "{$qty}kg {$product} from {$buyer}{$extra}";

    $body = "Status: " . $r['order_status'] . "\n"
          . "Buyer: " . $buyer . "\n"
          . "Items: " . $item_count . "\n"
          . "Total: ₱" . number_format(safe_float($r['total_amount']), 2);

    push_item($items, $r['ts'], 'Order', $title, $body);
  }
  mysqli_stmt_close($stmt);

  /* ---------- MESSAGES FROM BUYERS ---------- */
  $stmt = mysqli_prepare($conn, "
    SELECT
      m.sent_at AS ts,
      b.full_name AS buyer_name,
      m.content
    FROM message_tbl m
    JOIN buyer_tbl b ON b.buyer_id = m.buyer_id
    WHERE m.farmer_id = ? AND m.sender_type = 'Buyer'
    ORDER BY m.sent_at DESC
    LIMIT 80
  ");
  mysqli_stmt_bind_param($stmt, "i", $user_id);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  while ($r = mysqli_fetch_assoc($res)) {
    $name = (string)$r['buyer_name'];
    $title = "from {$name}";
    $body  = (string)$r['content'];
    push_item($items, $r['ts'], 'Message', $title, $body);
  }
  mysqli_stmt_close($stmt);

  /* ---------- SUPPORT REPLIES ---------- */
  $stmt = mysqli_prepare($conn, "
    SELECT
      updated_at AS ts,
      status,
      admin_reply
    FROM admin_support_tbl
    WHERE farmer_id = ?
      AND admin_reply IS NOT NULL AND admin_reply <> ''
    ORDER BY updated_at DESC
    LIMIT 80
  ");
  mysqli_stmt_bind_param($stmt, "i", $user_id);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  while ($r = mysqli_fetch_assoc($res)) {
    $title = "Admin replied to your concern";
    $body  = "Status: " . $r['status'] . "\n\n" . $r['admin_reply'];
    push_item($items, $r['ts'], 'Support', $title, $body);
  }
  mysqli_stmt_close($stmt);

  /* ---------- FEEDBACK / RATINGS ---------- */
  $stmt = mysqli_prepare($conn, "
    SELECT
      f.created_at AS ts,
      f.rating,
      f.comment,
      b.full_name AS buyer_name,
      p.name AS product_name
    FROM feedback_tbl f
    JOIN buyer_tbl b ON b.buyer_id = f.buyer_id
    JOIN product_tbl p ON p.product_id = f.product_id
    WHERE p.farmer_id = ?
    ORDER BY f.created_at DESC
    LIMIT 80
  ");
  mysqli_stmt_bind_param($stmt, "i", $user_id);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  while ($r = mysqli_fetch_assoc($res)) {
    $rating = safe_int($r['rating']);
    $product = (string)$r['product_name'];
    $buyer = (string)$r['buyer_name'];

    $title = "{$rating}/5 {$product} from {$buyer}";
    $body  = "Product: {$product}\n"
           . "Buyer: {$buyer}\n"
           . "Rating: {$rating}/5\n\n"
           . (string)$r['comment'];

    push_item($items, $r['ts'], 'Feedback', $title, $body);
  }
  mysqli_stmt_close($stmt);

  /* ---------- PROMOTION UPDATES (APPROVED / EXPIRED / REJECTED) ---------- */
  $stmt = mysqli_prepare($conn, "
    SELECT 
      b.boost_id,
      COALESCE(b.approved_at, b.created_at) AS ts,
      b.status,
      b.duration_days,
      b.promotion_plan,
      b.expires_at,
      p.name AS product_name
    FROM product_boost_tbl b
    JOIN product_tbl p ON p.product_id = b.product_id
    WHERE b.farmer_id = ?
    ORDER BY b.created_at DESC
    LIMIT 60
  ");
  mysqli_stmt_bind_param($stmt, "i", $user_id);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  while ($r = mysqli_fetch_assoc($res)) {
    $pname = (string)$r['product_name'];
    $st = (string)$r['status'];
    $dur = !empty($r['promotion_plan']) ? $r['promotion_plan'] : ($r['duration_days'] . ' Days');

    if ($st === 'Active') {
      $title = "Promotion Approved: {$pname} is now boosted!";
      $body = "Your {$dur} promotion for {$pname} is active!\n\n"
            . "It is now featured in the Home carousel and pinned to the top row of Market search.\n"
            . "Active until: " . date('M d, Y h:i A', strtotime($r['expires_at']));
      push_item($items, $r['ts'], 'Promotion', $title, $body);
    } elseif ($st === 'Expired') {
      $exp_ts = !empty($r['expires_at']) ? $r['expires_at'] : $r['ts'];
      $title = "Your promotion for {$pname} has ended";
      $body = "Your {$dur} promotion period for {$pname} has completed.\n\n"
            . "Visit Manage Products in your dashboard and click 'Promote' to feature it again!";
      push_item($items, $exp_ts, 'Promotion', $title, $body);
    } elseif ($st === 'Rejected') {
      $title = "Promotion Request Declined: {$pname}";
      $body = "Your promotion request for {$pname} could not be approved. Please check your GCash payment slip and re-submit in Manage Products.";
      push_item($items, $r['ts'], 'Promotion', $title, $body);
    }
  }
  mysqli_stmt_close($stmt);
}

/* =========================================================
   BUYER NOTIFICATIONS
========================================================= */
if ($role === 'buyer') {

  /* ---------- ORDER STATUS UPDATES ---------- */
  $stmt = mysqli_prepare($conn, "
    SELECT
      o.order_id,
      o.order_date AS ts,
      o.order_status,
      o.total_amount,
      f.username AS farmer_name,
      COALESCE(ps.total_qty, 0) AS total_qty,
      COALESCE(ps.first_product, 'Product') AS first_product,
      COALESCE(ps.item_count, 0) AS item_count
    FROM order_tbl o
    JOIN farmer_tbl f ON f.farmer_id = o.farmer_id
    LEFT JOIN (
      SELECT
        oi.order_id,
        SUM(oi.quantity) AS total_qty,
        SUBSTRING_INDEX(GROUP_CONCAT(p.name ORDER BY p.name SEPARATOR ', '), ', ', 1) AS first_product,
        COUNT(*) AS item_count
      FROM order_item_tbl oi
      JOIN product_tbl p ON p.product_id = oi.product_id
      GROUP BY oi.order_id
    ) ps ON ps.order_id = o.order_id
    WHERE o.buyer_id = ?
    ORDER BY o.order_date DESC
    LIMIT 100
  ");
  mysqli_stmt_bind_param($stmt, "i", $user_id);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  while ($r = mysqli_fetch_assoc($res)) {
    $qty = safe_int($r['total_qty']);
    $product = (string)$r['first_product'];
    $farmer = (string)$r['farmer_name'];
    $item_count = safe_int($r['item_count']);

    $extra = ($item_count > 1) ? (" (+" . ($item_count - 1) . " item(s))") : "";
    $title = "{$qty}kg {$product} from {$farmer}{$extra}";

    $body = "Status: " . $r['order_status'] . "\n"
          . "Seller: " . $farmer . "\n"
          . "Items: " . $item_count . "\n"
          . "Total: ₱" . number_format(safe_float($r['total_amount']), 2);

    push_item($items, $r['ts'], 'Order', $title, $body);
  }
  mysqli_stmt_close($stmt);

  /* ---------- MESSAGES FROM FARMERS ---------- */
  $stmt = mysqli_prepare($conn, "
    SELECT
      m.sent_at AS ts,
      f.username AS farmer_name,
      m.content
    FROM message_tbl m
    JOIN farmer_tbl f ON f.farmer_id = m.farmer_id
    WHERE m.buyer_id = ? AND m.sender_type = 'Farmer'
    ORDER BY m.sent_at DESC
    LIMIT 120
  ");
  mysqli_stmt_bind_param($stmt, "i", $user_id);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  while ($r = mysqli_fetch_assoc($res)) {
    $name = (string)$r['farmer_name'];
    $title = "from {$name}";
    $body  = (string)$r['content'];
    push_item($items, $r['ts'], 'Message', $title, $body);
  }
  mysqli_stmt_close($stmt);

  /* ---------- SUPPORT REPLIES ---------- */
  $stmt = mysqli_prepare($conn, "
    SELECT
      updated_at AS ts,
      status,
      admin_reply
    FROM admin_support_tbl
    WHERE buyer_id = ?
      AND admin_reply IS NOT NULL AND admin_reply <> ''
    ORDER BY updated_at DESC
    LIMIT 120
  ");
  mysqli_stmt_bind_param($stmt, "i", $user_id);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  while ($r = mysqli_fetch_assoc($res)) {
    $title = "Admin replied to your concern";
    $body  = "Status: " . $r['status'] . "\n\n" . $r['admin_reply'];
    push_item($items, $r['ts'], 'Support', $title, $body);
  }
  mysqli_stmt_close($stmt);
}

/* =========================================================
   ADMIN NOTIFICATIONS
========================================================= */
if ($role === 'Content Admin' || $role === 'User Admin') {

  /* Support tickets (from farmers or buyers) */
  $res = mysqli_query($conn, "
    SELECT
      s.created_at AS ts,
      'Support' AS type,
      COALESCE(f.full_name, b.full_name, 'User') AS sender_name,
      s.status,
      s.message
    FROM admin_support_tbl s
    LEFT JOIN farmer_tbl f ON f.farmer_id = s.farmer_id
    LEFT JOIN buyer_tbl  b ON b.buyer_id  = s.buyer_id
    ORDER BY s.created_at DESC
    LIMIT 150
  ");
  while ($r = mysqli_fetch_assoc($res)) {
    $title = "Ticket from " . $r['sender_name'];
    $body  = "Status: " . $r['status'] . "\n\n" . $r['message'];
    push_item($items, $r['ts'], 'Support', $title, $body);
  }

  /* Pending farmer verification reminder */
  $res = mysqli_query($conn, "
    SELECT
      created_at AS ts,
      username
    FROM farmer_tbl
    WHERE verification_status = 'Pending'
    ORDER BY created_at DESC
    LIMIT 120
  ");
  while ($r = mysqli_fetch_assoc($res)) {
    $title = "Farmer verification pending";
    $body  = "Farmer: " . $r['username'];
    push_item($items, $r['ts'], 'Verification', $title, $body);
  }

  /* Pending promotion requests */
  $res = mysqli_query($conn, "
    SELECT 
      b.created_at AS ts,
      b.amount_paid,
      b.gcash_reference,
      b.duration_days,
      b.promotion_plan,
      f.full_name AS farmer_name,
      f.username AS farmer_username,
      p.name AS product_name
    FROM product_boost_tbl b
    JOIN farmer_tbl f ON f.farmer_id = b.farmer_id
    JOIN product_tbl p ON p.product_id = b.product_id
    WHERE b.status = 'Pending'
    ORDER BY b.created_at DESC
    LIMIT 60
  ");
  if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
      $plan_txt = !empty($r['promotion_plan']) ? $r['promotion_plan'] : ($r['duration_days'] . ' Days');
      $title = "New Promotion Request from " . $r['farmer_name'];
      $body = "Farmer: " . $r['farmer_name'] . " (@" . $r['farmer_username'] . ")\n"
            . "Product: " . $r['product_name'] . "\n"
            . "Plan: " . $plan_txt . "\n"
            . "Amount: ₱" . number_format((float)$r['amount_paid'], 2) . "\n"
            . "GCash Ref: " . (!empty($r['gcash_reference']) ? $r['gcash_reference'] : 'None') . "\n\n"
            . "Check User Admin Dashboard to review GCash receipt and approve.";
      push_item($items, $r['ts'], 'Promotion', $title, $body);
    }
  }
}

mysqli_close($conn);

/* ========== SORT DESCENDING ORDER (NEWEST FIRST) ========== */
usort($items, function($a, $b) {
  return strcmp($b['ts'], $a['ts']);
});
?>

<main class="dashboard-content">
  <div class="section-card" style="margin-bottom:16px;">
    <h1 style="margin:0; font-size:24px;">Notifications</h1>
    <div class="small-muted" style="margin-top:4px;">Platform updates, order alerts, and messages in chronological order. Tap to view details.</div>
  </div>

  <!-- Required for localStorage read/unread -->
  <div id="notifContext"
       data-user-id="<?php echo (int)($_SESSION['user_id'] ?? 0); ?>"
       data-role="<?php echo e($_SESSION['user_role'] ?? ''); ?>"
       style="display:none;"></div>

  <div class="notif-list">
    <?php if (count($items) === 0) { ?>
      <div class="section-card" style="text-align:center; padding:30px;">
        <div class="small-muted">No notifications yet.</div>
      </div>
    <?php } ?>

    <?php foreach ($items as $n) {
      $nid = sha1(($n['type'] ?? '') . '|' . ($n['title'] ?? '') . '|' . ($n['ts'] ?? ''));
      $line = trim($n['type'] . ' ' . $n['title']);
      $formatted_time = !empty($n['ts']) ? date('M d, Y h:i A', strtotime($n['ts'])) : '';
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
          <div class="notif-title"><?php echo e($line); ?></div>
          <div class="notif-time"><?php echo e($formatted_time); ?></div>
        </div>

        <div class="notif-preview">Tap to read the details</div>
      </button>
    <?php } ?>
  </div>
</main>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>