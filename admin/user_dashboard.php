<?php
/**
 * SeaLink Web Application
 * File: /admin/user_dashboard.php
 * Purpose: User Admin Home Dashboard (Welcome, KPIs, user-admin notifications, users preview, products preview, analytics preview).
 * Connected To:
 * - /includes/nav_admin_user.php (Home tab)
 * - /admin/users.php
 * - /admin/products.php
 * - /admin/analytics.php
 */

require_once __DIR__ . '/../includes/auth_check.php';
check_access('User Admin');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$active_tab = 'home';
$page_title = "User Admin Home - SeaLink";
require_once __DIR__ . '/../includes/layout_top.php';

$admin_id = (int)$_SESSION['user_id'];
$admin_username = $_SESSION['username'] ?? 'User Admin';

// Auto-sync expired promotions in real-time
check_and_expire_promotions($conn);

/* ========== KPI STATS ========== */
// Total Farmers
$stmt = mysqli_query($conn, "SELECT COUNT(*) as c FROM farmer_tbl");
$total_farmers = (int)(mysqli_fetch_assoc($stmt)['c'] ?? 0);

// Total Buyers
$stmt = mysqli_query($conn, "SELECT COUNT(*) as c FROM buyer_tbl");
$total_buyers = (int)(mysqli_fetch_assoc($stmt)['c'] ?? 0);
$total_users = $total_farmers + $total_buyers;

// Pending Farmers
$stmt = mysqli_query($conn, "SELECT COUNT(*) as c FROM farmer_tbl WHERE verification_status = 'Pending'");
$pending_farmers = (int)(mysqli_fetch_assoc($stmt)['c'] ?? 0);

// Total Products
$stmt = mysqli_query($conn, "SELECT COUNT(*) as c FROM product_tbl");
$total_products = (int)(mysqli_fetch_assoc($stmt)['c'] ?? 0);

// Active Orders
$stmt = mysqli_query($conn, "SELECT COUNT(*) as c FROM order_tbl WHERE order_status NOT IN ('Completed', 'Cancelled')");
$active_orders = (int)(mysqli_fetch_assoc($stmt)['c'] ?? 0);

// Revenue
$stmt = mysqli_query($conn, "SELECT COALESCE(SUM(total_amount), 0) as total FROM order_tbl WHERE order_status = 'Completed'");
$total_revenue = (float)(mysqli_fetch_assoc($stmt)['total'] ?? 0);

// Platform Revenue (2% fee from completed orders)
$stmt = mysqli_query($conn, "SELECT COALESCE(SUM(platform_fee), 0) as pf FROM order_tbl WHERE order_status = 'Completed'");
$platform_revenue = (float)(mysqli_fetch_assoc($stmt)['pf'] ?? 0);

// Pending boost requests
$stmt = mysqli_query($conn, "SELECT COUNT(*) as c FROM product_boost_tbl WHERE status = 'Pending'");
$pending_boosts = (int)(mysqli_fetch_assoc($stmt)['c'] ?? 0);

/* ========== NOTIFICATIONS (TAILORED FOR USER ADMIN) ========== */
$user_admin_notifs = [];
$sql = "
SELECT * FROM (
    SELECT 
        created_at AS ts,
        'Verification' AS type,
        CONCAT('Farmer verification pending: ', full_name, ' (@', username, ')') AS title,
        CONCAT('Permit Number: ', permit_number, '\nAddress: ', address, '\nContact: ', contact_number) AS body
    FROM farmer_tbl
    WHERE verification_status = 'Pending'

    UNION ALL

    SELECT 
        b.created_at AS ts,
        'Promotion' AS type,
        CONCAT('New Promotion Request from ', f.full_name) AS title,
        CONCAT('Farmer: ', f.full_name, ' (@', f.username, ')\nProduct: ', p.name, '\nPlan: ', COALESCE(b.promotion_plan, CONCAT(b.duration_days, ' Days')), '\nAmount Paid: ₱', FORMAT(b.amount_paid, 2), '\nGCash Ref: ', COALESCE(b.gcash_reference, 'N/A')) AS body
    FROM product_boost_tbl b
    JOIN product_tbl p ON p.product_id = b.product_id
    JOIN farmer_tbl f ON f.farmer_id = b.farmer_id
    WHERE b.status = 'Pending'

    UNION ALL

    SELECT 
        created_at AS ts,
        'New Registration' AS type,
        CONCAT('New Buyer Registered: ', full_name, ' (@', username, ')') AS title,
        CONCAT('Email: ', email, '\nContact: ', contact_number) AS body
    FROM buyer_tbl

    UNION ALL

    SELECT 
        created_at AS ts,
        'Support' AS type,
        CONCAT('Open Support Message: ', LEFT(message, 40), '...') AS title,
        message AS body
    FROM admin_support_tbl
    WHERE status = 'Open'
) x
ORDER BY x.ts DESC
LIMIT 6
";
$res = mysqli_query($conn, $sql);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $user_admin_notifs[] = $row;
    }
}

/* ========== PENDING PROMOTIONS MODULE ========== */
$pending_promotions = [];
$p_sql = "
    SELECT 
        b.boost_id, b.product_id, b.farmer_id, b.amount_paid, 
        COALESCE(b.gcash_reference, p.gcash_reference_number) AS gcash_reference,
        p.gcash_reference_number,
        b.receipt_image,
        b.duration_days, b.promotion_plan, b.created_at,
        p.name AS product_name, p.price AS product_price, p.image_url AS product_image,
        f.full_name AS farmer_name, f.username AS farmer_username, f.contact_number AS farmer_contact
    FROM product_boost_tbl b
    JOIN product_tbl p ON p.product_id = b.product_id
    JOIN farmer_tbl f ON f.farmer_id = b.farmer_id
    WHERE b.status = 'Pending'
    ORDER BY b.created_at ASC
";
$p_res = mysqli_query($conn, $p_sql);
if ($p_res) {
    while ($r = mysqli_fetch_assoc($p_res)) {
        $pending_promotions[] = $r;
    }
}

/* ========== USERS PREVIEW (RECENT 4 FARMERS & BUYERS) ========== */
$recent_users = [];
$sql = "
(SELECT farmer_id AS id, username, full_name, email, verification_status, status, 'Farmer' AS role, created_at FROM farmer_tbl)
UNION ALL
(SELECT buyer_id AS id, username, full_name, email, 'Verified' AS verification_status, status, 'Buyer' AS role, created_at FROM buyer_tbl)
ORDER BY created_at DESC
LIMIT 5
";
$res = mysqli_query($conn, $sql);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $recent_users[] = $row;
    }
}

/* ========== PRODUCTS PREVIEW (RECENT 4) ========== */
$recent_products = [];
$sql = "
SELECT p.product_id, p.name, p.price, p.stock_quantity, p.status, c.category_name, f.username AS farmer_name
FROM product_tbl p
JOIN category_tbl c ON c.category_id = p.category_id
JOIN farmer_tbl f ON f.farmer_id = p.farmer_id
ORDER BY p.created_at DESC
LIMIT 4
";
$res = mysqli_query($conn, $sql);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $recent_products[] = $row;
    }
}

mysqli_close($conn);
?>

<style>
/* Keep KPI cards in a single row on smaller screens matching farmer/dashboard.php */
@media (max-width: 900px) {
  .user-dashboard-kpi-grid {
    display: grid !important;
    grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
    gap: 8px !important;
  }
  .user-dashboard-kpi-grid .kpi-card {
    padding: 10px 6px !important;
    text-align: center;
  }
  .user-dashboard-kpi-grid .kpi-card .value {
    font-size: 16px !important;
  }
  .user-dashboard-kpi-grid .kpi-card .label {
    font-size: 11px !important;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .user-dashboard-kpi-grid .kpi-card .sub {
    display: none !important;
  }
}
@media (max-width: 480px) {
  .user-dashboard-kpi-grid {
    display: grid !important;
    grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
    gap: 5px !important;
  }
  .user-dashboard-kpi-grid .kpi-card {
    padding: 8px 3px !important;
    text-align: center;
  }
  .user-dashboard-kpi-grid .kpi-card .value {
    font-size: 13px !important;
  }
  .user-dashboard-kpi-grid .kpi-card .label {
    font-size: 9px !important;
    letter-spacing: -0.2px;
  }
  .user-dashboard-kpi-grid .kpi-card .sub {
    display: none !important;
  }
}

.user-bottom-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-top: 16px;
}
@media (max-width: 800px) {
    .user-bottom-grid {
        grid-template-columns: 1fr !important;
    }
    .user-bottom-grid .recent-users-container {
        order: 1 !important;
    }
    .user-bottom-grid .recent-products-container {
        order: 2 !important;
    }
}
</style>

<main class="dashboard-content">
    <!-- Hero Welcome -->
    <section class="section-card">
        <section class="dashboard-hero">
            <h1 class="title">Welcome, <?php echo e($admin_username); ?>!</h1>
            <div class="sub">User Administrator Portal &bull; SeaLink Management Console</div>
        </section>

        <!-- KPI Cards -->
        <div class="kpi-grid user-dashboard-kpi-grid" style="margin-top:14px;">
            <div class="kpi-card">
                <div class="label">Pending Farmers</div>
                <div class="value"><?php echo $pending_farmers; ?></div>
                <div class="sub" style="font-size:12px; margin-top:4px; color:rgba(255,255,255,0.92);">Awaiting verification</div>
            </div>

            <div class="kpi-card" style="background:linear-gradient(135deg,#c2410c,#9a3412);">
                <div class="label">Pending Boost Requests</div>
                <div class="value"><?php echo $pending_boosts; ?></div>
                <div class="sub" style="font-size:12px; margin-top:4px; color:rgba(255,255,255,0.92);">Awaiting admin approval</div>
            </div>

            <div class="kpi-card">
                <div class="label">Total Users</div>
                <div class="value"><?php echo $total_users; ?></div>
                <div class="sub" style="font-size:12px; margin-top:4px; color:rgba(255,255,255,0.92);"><?php echo $total_farmers; ?> Farmers &bull; <?php echo $total_buyers; ?> Buyers</div>
            </div>

            <div class="kpi-card" style="background:linear-gradient(135deg,#047857,#065f46);">
                <div class="label">Platform Revenue</div>
                <div class="value">₱<?php echo number_format($platform_revenue, 2); ?></div>
                <div class="sub" style="font-size:12px; margin-top:4px; color:rgba(255,255,255,0.92);">SeaLink sustainability earnings</div>
            </div>
        </div>

        <?php if ($platform_revenue > 0 || $pending_boosts > 0) { ?>
        <div style="margin-top:12px; background:rgba(255,255,255,0.12); border-radius:10px; padding:10px 14px; font-size:13px; color:rgba(255,255,255,0.9);">
            <strong>Platform Note:</strong> SeaLink earns a 2% sustainability fee on each completed sale. Platform revenue helps fund fisheries support programs and infrastructure for coastal communities.
            <?php if ($pending_boosts > 0) { ?>
            There <?php echo $pending_boosts === 1 ? 'is' : 'are'; ?> <strong><a href="#pending_promotions" style="color:#fff; text-decoration:underline; font-weight:800;"><?php echo $pending_boosts; ?> promotion request(s)</a></strong> awaiting your confirmation.
            <?php } ?>
        </div>
        <?php } ?>
    </section>

    <!-- ==================== PENDING PROMOTIONS MODULE ==================== -->
    <div class="section-card" id="pending_promotions">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
            <div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <h2 style="font-weight:800; font-size:18px; margin:0; color:var(--text);">Pending Promotions</h2>
                    <span class="badge <?php echo count($pending_promotions) > 0 ? 'active' : ''; ?>" style="font-size:12px;">
                        <?php echo count($pending_promotions); ?> Awaiting Review
                    </span>
                </div>
                <div class="small-muted" style="margin-top:3px;">Verify uploaded GCash receipts and approve product boosts for Home Banner & Pinned Market placement</div>
            </div>
            <?php if (count($pending_promotions) > 0) { ?>
                <span style="font-size:12px; font-weight:700; color:#d97706; background:#fffbeb; border:1px solid #fde68a; padding:4px 10px; border-radius:6px;">
                    Verify GCash amount in app before approving
                </span>
            <?php } ?>
        </div>

        <div class="table-wrap">
            <table class="table" style="font-size:13px;">
                <thead>
                    <tr>
                        <th>Farmer Name</th>
                        <th>Product</th>
                        <th>Duration Requested</th>
                        <th>Amount Paid</th>
                        <th style="text-align:center;">Receipt Screenshot</th>
                        <th style="text-align:center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pending_promotions)) { ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding:32px 14px; color:var(--muted);">
                                <div style="font-size:28px; margin-bottom:6px;"></div>
                                <strong>No Pending Promotions</strong>
                                <div class="small-muted" style="margin-top:2px;">All farmer promotion requests and GCash receipts have been processed.</div>
                            </td>
                        </tr>
                    <?php } else { ?>
                        <?php foreach ($pending_promotions as $promo) { 
                            $raw_receipt = trim((string)($promo['receipt_image'] ?? ''));
                            $receipt_url = '';
                            if (!empty($raw_receipt)) {
                                if (str_starts_with($raw_receipt, 'http://') || str_starts_with($raw_receipt, 'https://')) {
                                    $receipt_url = $raw_receipt;
                                } elseif (str_starts_with($raw_receipt, BASE_URL . '/')) {
                                    $receipt_url = $raw_receipt;
                                } else {
                                    $receipt_url = BASE_URL . '/' . ltrim($raw_receipt, '/');
                                }
                            }
                            $days_label = !empty($promo['promotion_plan']) ? $promo['promotion_plan'] : ($promo['duration_days'] . ' Days Pinned');
                        ?>
                            <tr>
                                <!-- Farmer Name -->
                                <td>
                                    <strong><?php echo e($promo['farmer_name']); ?></strong>
                                    <div class="small-muted">@<?php echo e($promo['farmer_username']); ?></div>
                                    <div class="small-muted" style="font-size:11px;"><?php echo e($promo['farmer_contact']); ?></div>
                                </td>

                                <!-- Product -->
                                <td>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <div style="width:36px; height:36px; border-radius:6px; overflow:hidden; background:#f1f5f9; flex-shrink:0; border:1px solid var(--border); display:flex; align-items:center; justify-content:center;">
                                            <?php if (!empty($promo['product_image'])) { ?>
                                                <img src="<?php echo BASE_URL . '/' . e($promo['product_image']); ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                                            <?php } else { ?>
                                                <span></span>
                                            <?php } ?>
                                        </div>
                                        <div>
                                            <strong><?php echo e($promo['product_name']); ?></strong>
                                            <div class="small-muted">Base Price: ₱<?php echo number_format((float)$promo['product_price'], 2); ?></div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Duration Requested -->
                                <td>
                                    <span style="background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; padding:3px 8px; border-radius:6px; font-weight:700; font-size:12px; display:inline-block;">
                                        <?php echo e($days_label); ?>
                                    </span>
                                    <div class="small-muted" style="font-size:11px; margin-top:2px;">
                                        Requested <?php echo date('M d, Y h:i A', strtotime($promo['created_at'])); ?>
                                    </div>
                                </td>

                                <!-- Amount Paid -->
                                <td>
                                    <strong style="color:#059669; font-size:14px;">₱<?php echo number_format((float)$promo['amount_paid'], 2); ?></strong>
                                    <?php 
                                    $ref_display = !empty($promo['gcash_reference']) ? $promo['gcash_reference'] : (!empty($promo['gcash_reference_number']) ? $promo['gcash_reference_number'] : '');
                                    if (!empty($ref_display)) { ?>
                                        <div style="margin-top:4px;">
                                            <span style="background:#eff6ff; color:#0369a1; border:1px solid #bae6fd; font-family:monospace; font-size:11.5px; font-weight:700; padding:2px 6px; border-radius:4px; display:inline-block; letter-spacing:0.5px;" title="GCash Reference Number">
                                                Ref: <?php echo e($ref_display); ?>
                                            </span>
                                        </div>
                                    <?php } else { ?>
                                        <div class="small-muted" style="font-size:11px; margin-top:2px;">(No ref entered)</div>
                                    <?php } ?>
                                </td>

                                <!-- Receipt Image -->
                                <td style="text-align:center;">
                                    <?php if (!empty($receipt_url)) { ?>
                                        <div style="display:inline-flex; flex-direction:column; align-items:center; gap:4px;">
                                            <div data-receipt="<?php echo e($receipt_url); ?>"
                                                 data-farmer="<?php echo e($promo['farmer_name']); ?>"
                                                 data-product="<?php echo e($promo['product_name']); ?>"
                                                 onclick="viewPromoReceiptEl(this)"
                                                 style="width:52px; height:52px; border-radius:8px; overflow:hidden; border:2px solid #3b82f6; cursor:pointer; box-shadow:0 2px 6px rgba(59,130,246,0.2); transition:transform .15s; background:#0f172a; display:inline-flex; align-items:center; justify-content:center;"
                                                 onmouseover="this.style.transform='scale(1.06)'" onmouseout="this.style.transform='scale(1)'"
                                                 title="Click to view GCash receipt screenshot">
                                                <img src="<?php echo e($receipt_url); ?>" alt="Receipt" style="width:100%; height:100%; object-fit:cover;">
                                            </div>
                                            <button type="button" class="btn-link" style="font-size:11.5px; font-weight:700; padding:2px 4px; text-decoration:underline; color:#0284c7; cursor:pointer;"
                                                    data-receipt="<?php echo e($receipt_url); ?>"
                                                    data-farmer="<?php echo e($promo['farmer_name']); ?>"
                                                    data-product="<?php echo e($promo['product_name']); ?>"
                                                    onclick="viewPromoReceiptEl(this)">
                                                View Receipt
                                            </button>
                                        </div>
                                    <?php } else { ?>
                                        <span class="small-muted">No Receipt</span>
                                    <?php } ?>
                                </td>

                                <!-- Action (Approve / Reject) -->
                                <td style="text-align:center;">
                                    <div style="display:inline-flex; gap:6px; flex-wrap:wrap; justify-content:center;">
                                        <!-- Approve Button -->
                                        <button type="button" class="btn btn-sm"
                                                style="background:#059669; color:#fff; border:none; font-weight:700; padding:6px 12px; border-radius:6px; display:inline-flex; align-items:center; gap:4px;"
                                                data-boost-id="<?php echo (int)$promo['boost_id']; ?>"
                                                data-product-id="<?php echo (int)$promo['product_id']; ?>"
                                                data-product-name="<?php echo e($promo['product_name']); ?>"
                                                data-farmer-name="<?php echo e($promo['farmer_name']); ?>"
                                                data-duration="<?php echo e($days_label); ?>"
                                                data-amount="<?php echo number_format((float)$promo['amount_paid'], 2); ?>"
                                                data-receipt="<?php echo e($receipt_url); ?>"
                                                onclick="openApprovePromoModalEl(this)">
                                            ✔ Approve
                                        </button>

                                        <!-- Reject Button -->
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                style="padding:6px 12px; border-radius:6px;"
                                                onclick="openRejectPromoModal(<?php echo (int)$promo['boost_id']; ?>, <?php echo (int)$promo['product_id']; ?>, '<?php echo e(addslashes($promo['product_name'])); ?>', '<?php echo e(addslashes($promo['farmer_name'])); ?>')">
                                            ✖ Reject
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Notifications Preview (Top 5) -->
    <div class="section-card">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div>
                <div style="font-weight:800; font-size:16px;">Recent Notifications</div>
                <div class="small-muted">Platform events, registrations, and support inquiries</div>
            </div>
            <a class="btn btn-secondary btn-sm" href="<?php echo BASE_URL; ?>/notifications/index.php">View All</a>
        </div>

        <div id="notifContext"
             data-user-id="<?php echo (int)($_SESSION['user_id'] ?? 0); ?>"
             data-role="<?php echo e($_SESSION['user_role'] ?? ''); ?>"
             style="display:none;"></div>

        <div class="notif-list" style="margin-top:12px;">
            <?php if (empty($user_admin_notifs)) { ?>
                <div class="small-muted">No pending user alerts at the moment.</div>
            <?php } ?>

            <?php foreach ($user_admin_notifs as $n) { 
                $nid = sha1(($n['type'] ?? '') . '|' . ($n['title'] ?? '') . '|' . ($n['ts'] ?? ''));
            ?>
                <button type="button" class="notif-item"
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

    <!-- Users Preview & Products Preview in 2 columns -->
    <div class="user-bottom-grid">
        <!-- Users Preview -->
        <div class="section-card recent-users-container" style="margin-top:0;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <div style="font-weight:800; font-size:16px;">Recent Users</div>
                <a class="btn btn-sm btn-primary" href="<?php echo BASE_URL; ?>/admin/users.php">View Users</a>
            </div>

            <div class="table-wrap">
                <table class="table" style="font-size:13px;">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_users as $u) { ?>
                            <tr>
                                <td>
                                    <strong><?php echo e($u['full_name']); ?></strong>
                                    <div class="small-muted">@<?php echo e($u['username']); ?></div>
                                </td>
                                <td><span class="badge"><?php echo e($u['role']); ?></span></td>
                                <td>
                                    <span class="badge <?php echo ($u['verification_status'] === 'Verified' ? 'active' : 'inactive'); ?>">
                                        <?php echo e($u['verification_status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Products Preview -->
        <div class="section-card recent-products-container" style="margin-top:0;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <div style="font-weight:800; font-size:16px;">Recent Products</div>
                <a class="btn btn-sm btn-secondary" href="<?php echo BASE_URL; ?>/admin/products.php">All Products</a>
            </div>

            <div class="table-wrap">
                <table class="table" style="font-size:13px;">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Seller</th>
                            <th>Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_products as $p) { ?>
                            <tr>
                                <td>
                                    <strong><?php echo e($p['name']); ?></strong>
                                    <div class="small-muted"><?php echo e($p['category_name']); ?></div>
                                </td>
                                <td><?php echo e($p['farmer_name']); ?></td>
                                <td style="font-weight:700; color:var(--primary);">₱<?php echo number_format((float)$p['price'], 2); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- RECEIPT LIGHTBOX MODAL -->
<div class="modal" id="promoReceiptModal" aria-hidden="true">
  <div class="modal-content" style="max-width:620px; text-align:center;">
    <div class="modal-header" style="border-bottom:1px solid var(--border); padding-bottom:12px;">
      <div style="text-align:left;">
        <h2 style="margin:0; font-size:18px;">GCash Payment Receipt</h2>
        <div class="small-muted" style="font-size:12px;">
          <span id="receipt_farmer_name"></span> &bull; <span id="receipt_product_name"></span>
        </div>
      </div>
      <button type="button" class="modal-close-x" onclick="closeModal('promoReceiptModal')" aria-label="Close">&times;</button>
    </div>

    <div style="padding:16px 0;">
      <div style="max-height:520px; overflow:auto; border-radius:12px; background:#0f172a; padding:10px; display:inline-block; border:1px solid var(--border); max-width:100%;">
        <img id="receiptModalImg" src="" alt="GCash Receipt" style="max-height:480px; max-width:100%; border-radius:8px; display:block; margin:0 auto; cursor:zoom-in;" onclick="openReceiptInNewTab()" title="Click to view image in a new tab">
      </div>
      <div class="small-muted" style="margin-top:10px; font-size:12px;">
        Click on image or the button below to view in original resolution.
      </div>
    </div>

    <div class="form-actions" style="justify-content:space-between; border-top:1px solid var(--border); padding-top:12px;">
      <a id="receiptDownloadLink" href="" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm" style="display:inline-flex; align-items:center; gap:6px;">
        <span></span> <span>Open in New Tab</span>
      </a>
      <button type="button" class="btn btn-primary btn-sm" onclick="closeModal('promoReceiptModal')">Close</button>
    </div>
  </div>
</div>

<!-- APPROVE PROMOTION CONFIRMATION MODAL -->
<div class="modal" id="approvePromoModal" aria-hidden="true">
  <div class="modal-content" style="max-width:480px;">
    <div class="modal-header" style="border-bottom:1px solid var(--border); padding-bottom:12px;">
      <div style="display:flex; align-items:center; gap:8px;">
        <span style="font-size:22px; color:#059669;">✔</span>
        <h2 style="margin:0; font-size:18px; color:#059669;">Approve Product Promotion</h2>
      </div>
      <button type="button" class="modal-close-x" onclick="closeModal('approvePromoModal')" aria-label="Close">&times;</button>
    </div>

    <form method="POST" action="<?php echo BASE_URL; ?>/admin/actions/promotion_approve.php" id="approvePromoForm">
      <input type="hidden" name="boost_id" id="approve_boost_id">
      <input type="hidden" name="product_id" id="approve_product_id">

      <div style="padding:14px 0;">
        <p style="margin:0 0 12px 0; font-size:14px; line-height:1.5;">
          Are you sure you want to approve the promotion for <strong id="approve_product_name"></strong> from <strong id="approve_farmer_name"></strong>?
        </p>

        <div style="background:#ecfdf5; border:1px solid #a7f3d0; border-radius:10px; padding:12px; font-size:13px; color:#065f46; line-height:1.5;">
          <div><strong>Plan:</strong> <span id="approve_duration"></span></div>
          <div><strong>Amount Confirmed:</strong> ₱<span id="approve_amount"></span></div>
          
          <div id="approve_receipt_preview_box" style="margin-top:10px; padding-top:10px; border-top:1px dashed #a7f3d0; display:none; align-items:center; gap:10px;">
            <div style="width:44px; height:44px; border-radius:6px; overflow:hidden; border:1.5px solid #059669; flex-shrink:0; background:#0f172a; cursor:pointer;" onclick="openReceiptFromApproveModal()" title="Click to view receipt">
              <img id="approve_receipt_thumb" src="" alt="Receipt" style="width:100%; height:100%; object-fit:cover;">
            </div>
            <div style="flex:1; font-size:12px;">
              <span style="font-weight:700; color:#065f46;">Receipt Attached:</span>
              <a href="javascript:void(0)" onclick="openReceiptFromApproveModal()" style="color:#0284c7; font-weight:800; text-decoration:underline; margin-left:4px;">🔍 View Screenshot</a>
            </div>
          </div>

          <div style="margin-top:8px; font-size:12px; color:#047857;">
            Upon approval, this product will immediately be placed in the <strong>Featured Fresh Catch</strong> home carousel and <strong>pinned to the top</strong> of all Market search results!
          </div>
        </div>
      </div>

      <div class="form-actions" style="justify-content:flex-end; border-top:1px solid var(--border); padding-top:12px; gap:8px;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('approvePromoModal')">Cancel</button>
        <button type="submit" class="btn" style="background:#059669; color:#fff; border:none; font-weight:800; padding:8px 16px; border-radius:8px;">
          Yes, Confirm & Activate Boost
        </button>
      </div>
    </form>
  </div>
</div>

<!-- REJECT PROMOTION CONFIRMATION MODAL -->
<div class="modal" id="rejectPromoModal" aria-hidden="true">
  <div class="modal-content" style="max-width:460px;">
    <div class="modal-header" style="border-bottom:1px solid #fee2e2; padding-bottom:12px;">
      <div style="display:flex; align-items:center; gap:8px;">
        <span style="font-size:22px; color:#dc2626;">✖</span>
        <h2 style="margin:0; font-size:18px; color:#dc2626;">Reject Promotion Request</h2>
      </div>
      <button type="button" class="modal-close-x" onclick="closeModal('rejectPromoModal')" aria-label="Close">&times;</button>
    </div>

    <form method="POST" action="<?php echo BASE_URL; ?>/admin/actions/promotion_reject.php" id="rejectPromoForm">
      <input type="hidden" name="boost_id" id="reject_boost_id">
      <input type="hidden" name="product_id" id="reject_product_id">

      <div style="padding:14px 0;">
        <p style="margin:0 0 10px 0; font-size:14px; line-height:1.5;">
          Are you sure you want to decline the promotion request for <strong id="reject_product_name"></strong>?
        </p>
        <div class="small-muted" style="font-size:12px; color:#b42318; line-height:1.4;">
          The request will be marked as Rejected and the product will not be featured. The farmer can re-submit with a valid receipt.
        </div>
      </div>

      <div class="form-actions" style="justify-content:flex-end; border-top:1px solid #fee2e2; padding-top:12px; gap:8px;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('rejectPromoModal')">Cancel</button>
        <button type="submit" class="btn btn-outline-danger" style="background:#dc2626; color:#fff; border:none; font-weight:700; padding:8px 16px; border-radius:8px;">
          Confirm Reject
        </button>
      </div>
    </form>
  </div>
</div>

<script>
var currentApproveReceipt = '';
var currentApproveFarmer = '';
var currentApproveProduct = '';

function viewPromoReceipt(imageUrl, farmerName, productName) {
  var imgEl = document.getElementById('receiptModalImg');
  var linkEl = document.getElementById('receiptDownloadLink');
  if (imgEl) imgEl.src = imageUrl;
  if (linkEl) linkEl.href = imageUrl;
  var farmerEl = document.getElementById('receipt_farmer_name');
  var prodEl = document.getElementById('receipt_product_name');
  if (farmerEl) farmerEl.textContent = farmerName || '';
  if (prodEl) prodEl.textContent = productName || '';
  openModal('promoReceiptModal');
}

function viewPromoReceiptEl(el) {
  if (!el) return;
  var url = el.dataset.receipt || el.getAttribute('data-receipt') || '';
  var farmer = el.dataset.farmer || el.getAttribute('data-farmer') || '';
  var prod = el.dataset.product || el.getAttribute('data-product') || '';
  viewPromoReceipt(url, farmer, prod);
}

function openReceiptInNewTab() {
  var img = document.getElementById('receiptModalImg');
  if (img && img.src) {
    window.open(img.src, '_blank');
  }
}

function openReceiptFromApproveModal() {
  if (currentApproveReceipt) {
    viewPromoReceipt(currentApproveReceipt, currentApproveFarmer, currentApproveProduct);
  }
}

function openApprovePromoModal(boostId, prodId, product, farmer, duration, amount, receiptUrl) {
  document.getElementById('approve_boost_id').value = boostId;
  document.getElementById('approve_product_id').value = prodId;
  document.getElementById('approve_product_name').textContent = product;
  document.getElementById('approve_farmer_name').textContent = farmer;
  document.getElementById('approve_duration').textContent = duration;
  document.getElementById('approve_amount').textContent = amount;

  currentApproveReceipt = receiptUrl || '';
  currentApproveFarmer = farmer || '';
  currentApproveProduct = product || '';

  var previewBox = document.getElementById('approve_receipt_preview_box');
  var thumbImg = document.getElementById('approve_receipt_thumb');
  if (receiptUrl && previewBox && thumbImg) {
    thumbImg.src = receiptUrl;
    previewBox.style.display = 'flex';
  } else if (previewBox) {
    previewBox.style.display = 'none';
  }

  openModal('approvePromoModal');
}

function openApprovePromoModalEl(el) {
  if (!el) return;
  var bId = el.dataset.boostId || el.getAttribute('data-boost-id') || '';
  var pId = el.dataset.productId || el.getAttribute('data-product-id') || '';
  var pName = el.dataset.productName || el.getAttribute('data-product-name') || '';
  var fName = el.dataset.farmerName || el.getAttribute('data-farmer-name') || '';
  var dur = el.dataset.duration || el.getAttribute('data-duration') || '';
  var amt = el.dataset.amount || el.getAttribute('data-amount') || '';
  var rUrl = el.dataset.receipt || el.getAttribute('data-receipt') || '';
  openApprovePromoModal(bId, pId, pName, fName, dur, amt, rUrl);
}

function openRejectPromoModal(boostId, prodId, product, farmer) {
  document.getElementById('reject_boost_id').value = boostId;
  document.getElementById('reject_product_id').value = prodId;
  document.getElementById('reject_product_name').textContent = product;
  openModal('rejectPromoModal');
}
</script>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>