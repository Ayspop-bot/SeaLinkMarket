<?php
/**
 * SeaLink Web Application
 * File: /admin/content_dashboard.php
 * Purpose: Content Admin Home Dashboard (Welcome, KPIs, content-admin notifications, info hub preview, product preview, marketplace preview).
 * Connected To:
 * - /includes/nav_admin_content.php (Home tab)
 * - /admin/info_hub.php
 * - /admin/products.php
 * - /admin/market.php
 * - /admin/support.php
 */

require_once __DIR__ . '/../includes/auth_check.php';
check_access('Content Admin');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$active_tab = 'home';
$page_title = "Content Admin Home - SeaLink";
require_once __DIR__ . '/../includes/layout_top.php';

$admin_id = (int)$_SESSION['user_id'];
$admin_username = $_SESSION['username'] ?? 'Content Admin';

/* ========== KPI STATS ========== */
// Educational Guides (Published)
$stmt = mysqli_query($conn, "SELECT COUNT(*) as c FROM info_hub_tbl WHERE status = 'Published'");
$published_guides = (int)(mysqli_fetch_assoc($stmt)['c'] ?? 0);

// Draft Guides
$stmt = mysqli_query($conn, "SELECT COUNT(*) as c FROM info_hub_tbl WHERE status = 'Draft'");
$draft_guides = (int)(mysqli_fetch_assoc($stmt)['c'] ?? 0);

// Community Discussions (Forum Posts)
$stmt = mysqli_query($conn, "SELECT COUNT(*) as c FROM forum_post_tbl");
$total_forum_posts = (int)(mysqli_fetch_assoc($stmt)['c'] ?? 0);

// Platform Revenue
$stmt = mysqli_query($conn, "SELECT COALESCE(SUM(platform_fee), 0) as pf FROM order_tbl WHERE order_status = 'Completed'");
$platform_revenue = (float)(mysqli_fetch_assoc($stmt)['pf'] ?? 0);

/* ========== NOTIFICATIONS (TAILORED FOR CONTENT ADMIN) ========== */
$content_notifs = [];
$sql = "
SELECT * FROM (
    SELECT 
        created_at AS ts,
        'Support' AS type,
        CONCAT('Open support message: ', LEFT(message, 40), '...') AS title,
        message AS body
    FROM admin_support_tbl
    WHERE status = 'Open'

    UNION ALL

    SELECT 
        created_at AS ts,
        'Product Added' AS type,
        CONCAT('New Product listed: ', name, ' (₱', price, ')') AS title,
        CONCAT('Stock: ', stock_quantity, '\nDescription: ', description) AS body
    FROM product_tbl

    UNION ALL

    SELECT 
        created_at AS ts,
        'Info Hub' AS type,
        CONCAT('Article Created: ', title) AS title,
        COALESCE(category, 'Uncategorized') AS body
    FROM info_hub_tbl
) x
ORDER BY x.ts DESC
LIMIT 5
";
$res = mysqli_query($conn, $sql);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $content_notifs[] = $row;
    }
}

/* ========== INFO HUB PREVIEW (RECENT 4 ARTICLES) ========== */
$recent_articles = [];
$sql = "
SELECT info_hub_id AS article_id, info_hub_id, title, COALESCE(category, type, 'Article') AS category, status, created_at
FROM info_hub_tbl
ORDER BY created_at DESC
LIMIT 4
";
$res = mysqli_query($conn, $sql);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $recent_articles[] = $row;
    }
}

/* ========== MARKET PRODUCTS PREVIEW (RECENT 4) ========== */
$recent_products = [];
$sql = "
SELECT p.product_id, p.name, p.price, p.stock_quantity, p.image_url, c.category_name, f.username AS farmer_name
FROM product_tbl p
JOIN category_tbl c ON c.category_id = p.category_id
JOIN farmer_tbl f ON f.farmer_id = p.farmer_id
WHERE p.status = 'Active'
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

<main class="dashboard-content">
    <!-- Hero Welcome -->
    <section class="section-card">
        <section class="dashboard-hero">
            <h1 class="title">Welcome, <?php echo e($admin_username); ?>!</h1>
            <div class="sub">Content Administrator Portal &bull; SeaLink Educational & Marketplace Management</div>
        </section>

        <style>
        .content-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-top: 14px;
            margin-bottom: 6px;
        }
        /* Keep KPI cards in a single row on smaller screens matching farmer/dashboard.php */
        @media (max-width: 900px) {
            .content-kpi-grid {
                display: grid !important;
                grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
                gap: 8px !important;
            }
            .content-kpi-grid .kpi-card {
                padding: 10px 6px !important;
                text-align: center;
            }
            .content-kpi-grid .kpi-card .value {
                font-size: 16px !important;
            }
            .content-kpi-grid .kpi-card .label {
                font-size: 11px !important;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .content-kpi-grid .kpi-card .sub {
                display: none !important;
            }
        }
        @media (max-width: 480px) {
            .content-kpi-grid {
                display: grid !important;
                grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
                gap: 5px !important;
            }
            .content-kpi-grid .kpi-card {
                padding: 8px 3px !important;
                text-align: center;
            }
            .content-kpi-grid .kpi-card .value {
                font-size: 13px !important;
            }
            .content-kpi-grid .kpi-card .label {
                font-size: 9px !important;
                letter-spacing: -0.2px;
            }
            .content-kpi-grid .kpi-card .sub {
                display: none !important;
            }
        }

        .content-bottom-grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 16px;
            margin-top: 16px;
        }
        @media (max-width: 800px) {
            .content-bottom-grid {
                grid-template-columns: 1fr !important;
            }
            .content-bottom-grid .infohub-container {
                order: 1 !important;
            }
            .content-bottom-grid .products-container {
                order: 2 !important;
            }
        }
        </style>

        <!-- KPI Cards (4-column row matching user_dashboard.php) -->
        <div class="kpi-grid content-kpi-grid">
            <div class="kpi-card">
                <div class="label">Educational Guides</div>
                <div class="value"><?php echo $published_guides; ?></div>
                <div class="sub" style="font-size:12px; margin-top:4px; color:rgba(255,255,255,0.92);">Live in Information Hub</div>
            </div>

            <div class="kpi-card">
                <div class="label">Community Discussions</div>
                <div class="value"><?php echo $total_forum_posts; ?></div>
                <div class="sub" style="font-size:12px; margin-top:4px; color:rgba(255,255,255,0.92);">Active forum topics</div>
            </div>

            <div class="kpi-card" <?php echo ($draft_guides > 0) ? 'style="background:linear-gradient(135deg,#c2410c,#9a3412);"' : ''; ?>>
                <div class="label">Draft Guides</div>
                <div class="value"><?php echo $draft_guides; ?></div>
                <div class="sub" style="font-size:12px; margin-top:4px; color:rgba(255,255,255,0.92);"><?php echo ($draft_guides > 0) ? 'Awaiting publication' : 'All guides published'; ?></div>
            </div>

            <div class="kpi-card" style="background:linear-gradient(135deg,#047857,#065f46);">
                <div class="label">Platform Revenue</div>
                <div class="value">₱<?php echo number_format($platform_revenue, 2); ?></div>
                <div class="sub" style="font-size:12px; margin-top:4px; color:rgba(255,255,255,0.92);">SeaLink sustainability earnings</div>
            </div>
        </div>
    </section>

    <!-- Recent Notifications Preview (Top 5) -->
    <div class="section-card">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div>
                <div style="font-weight:800; font-size:16px;">Recent Notifications</div>
                <div class="small-muted">Latest activity, article additions, and support alerts</div>
            </div>
            <a class="btn btn-secondary btn-sm" href="<?php echo BASE_URL; ?>/notifications/index.php">View All</a>
        </div>

        <div id="notifContext"
             data-user-id="<?php echo (int)($_SESSION['user_id'] ?? 0); ?>"
             data-role="<?php echo e($_SESSION['user_role'] ?? ''); ?>"
             style="display:none;"></div>

        <div class="notif-list" style="margin-top:12px;">
            <?php if (empty($content_notifs)) { ?>
                <div class="small-muted">No pending content notifications.</div>
            <?php } ?>

            <?php foreach ($content_notifs as $n) { 
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

    <!-- Info Hub Preview & Products Preview in 2 columns -->
    <div class="content-bottom-grid">
        <!-- Info Hub Preview -->
        <div class="section-card infohub-container" style="margin-top:0;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <div style="font-weight:800; font-size:16px;">Info Hub Articles</div>
                <a class="btn btn-sm btn-primary" href="<?php echo BASE_URL; ?>/admin/infohub.php">Manage Info Hub</a>
            </div>

            <div class="table-wrap">
                <table class="table" style="font-size:13px;">
                    <thead>
                        <tr>
                            <th>Article Title</th>
                            <th>Category</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_articles)) { ?>
                            <tr><td colspan="3" class="small-muted" style="text-align:center;">No articles published yet.</td></tr>
                        <?php } ?>
                        <?php foreach ($recent_articles as $a) { ?>
                            <tr>
                                <td><strong><?php echo e($a['title']); ?></strong></td>
                                <td><?php echo e($a['category']); ?></td>
                                <td>
                                    <span class="badge <?php echo ($a['status'] === 'Published' ? 'active' : 'inactive'); ?>">
                                        <?php echo e($a['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Marketplace Monitor Preview -->
        <div class="section-card products-container" style="margin-top:0;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <div style="font-weight:800; font-size:16px;">Live Products</div>
                <a class="btn btn-sm btn-secondary" href="<?php echo BASE_URL; ?>/admin/market.php">View Market</a>
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
                        <?php if (empty($recent_products)) { ?>
                            <tr><td colspan="3" class="small-muted" style="text-align:center;">No active products.</td></tr>
                        <?php } ?>
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

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>