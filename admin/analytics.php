<?php
/**
 * SeaLink Web Application
 * File: /admin/analytics.php
 * Purpose: Platform Analytics & Insights dashboard for Administrators (Sales breakdown, Top Products, Top Sellers, and User Registration Growth).
 * Connected To:
 * - /includes/nav_admin_user.php & /includes/nav_admin_content.php
 * Uses: order_tbl, order_item_tbl, product_tbl, farmer_tbl, buyer_tbl, category_tbl
 */

require_once __DIR__ . '/../includes/auth_check.php';
check_access(['User Admin', 'Content Admin']);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$active_tab = 'analytics';
$page_title = "Platform Analytics - SeaLink Admin";
require_once __DIR__ . '/../includes/layout_top.php';

/* ========== OVERALL TOTALS ========== */
// Total Gross Revenue
$rev_res = mysqli_query($conn, "SELECT COALESCE(SUM(total_amount), 0) AS rev FROM order_tbl WHERE order_status = 'Completed'");
$total_revenue = (float)(mysqli_fetch_assoc($rev_res)['rev'] ?? 0);

// Total Completed Orders
$ord_res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM order_tbl WHERE order_status = 'Completed'");
$completed_orders = (int)(mysqli_fetch_assoc($ord_res)['c'] ?? 0);

// Total Active Users
$f_res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM farmer_tbl WHERE status = 'Active'");
$active_farmers = (int)(mysqli_fetch_assoc($f_res)['c'] ?? 0);

$b_res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM buyer_tbl WHERE status = 'Active'");
$active_buyers = (int)(mysqli_fetch_assoc($b_res)['c'] ?? 0);
$total_active_users = $active_farmers + $active_buyers;

// Total Products Listed
$p_res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM product_tbl WHERE status = 'Active'");
$active_products = (int)(mysqli_fetch_assoc($p_res)['c'] ?? 0);

// Active Orders (in fulfillment)
$act_ord_res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM order_tbl WHERE order_status NOT IN ('Completed', 'Cancelled')");
$active_orders = (int)(mysqli_fetch_assoc($act_ord_res)['c'] ?? 0);

// Average Order Value (AOV)
$avg_order_value = ($completed_orders > 0) ? ($total_revenue / $completed_orders) : 0.0;

/* ========== MONTHLY SALES BREAKDOWN ========== */
$monthly_sales = [];
$m_sql = "
SELECT 
    DATE_FORMAT(order_date, '%Y-%m') AS sale_month,
    COUNT(order_id) AS order_count,
    SUM(total_amount) AS monthly_total
FROM order_tbl
WHERE order_status = 'Completed'
GROUP BY sale_month
ORDER BY sale_month DESC
LIMIT 6
";
$m_res = mysqli_query($conn, $m_sql);
if ($m_res) {
    while ($row = mysqli_fetch_assoc($m_res)) {
        $monthly_sales[] = $row;
    }
}

/* ========== TOP 5 SELLING PRODUCTS ========== */
$top_products = [];
$tp_sql = "
SELECT 
    p.name AS product_name,
    c.category_name,
    f.username AS farmer_name,
    SUM(oi.quantity) AS units_sold,
    SUM(oi.price_at_purchase * oi.quantity) AS gross_sales
FROM order_item_tbl oi
JOIN order_tbl o ON o.order_id = oi.order_id
JOIN product_tbl p ON p.product_id = oi.product_id
JOIN category_tbl c ON c.category_id = p.category_id
JOIN farmer_tbl f ON f.farmer_id = p.farmer_id
WHERE o.order_status = 'Completed'
GROUP BY oi.product_id
ORDER BY units_sold DESC
LIMIT 5
";
$tp_res = mysqli_query($conn, $tp_sql);
if ($tp_res) {
    while ($row = mysqli_fetch_assoc($tp_res)) {
        $top_products[] = $row;
    }
}

/* ========== TOP FARMER PRODUCERS ========== */
$top_farmers = [];
$tf_sql = "
SELECT 
    f.full_name,
    f.username,
    COUNT(DISTINCT o.order_id) AS total_orders,
    SUM(o.total_amount) AS total_earned
FROM order_tbl o
JOIN farmer_tbl f ON f.farmer_id = o.farmer_id
WHERE o.order_status = 'Completed'
GROUP BY f.farmer_id
ORDER BY total_earned DESC
LIMIT 5
";
$tf_res = mysqli_query($conn, $tf_sql);
if ($tf_res) {
    while ($row = mysqli_fetch_assoc($tf_res)) {
        $top_farmers[] = $row;
    }
}

/* ========== USER REGISTRATION GROWTH OVER TIME ========== */
$user_growth = [];
$ug_sql = "
SELECT 
    m.reg_month,
    COALESCE(f.farmer_count, 0) AS new_farmers,
    COALESCE(b.buyer_count, 0) AS new_buyers,
    (COALESCE(f.farmer_count, 0) + COALESCE(b.buyer_count, 0)) AS total_new_users
FROM (
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS reg_month FROM farmer_tbl
    UNION
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS reg_month FROM buyer_tbl
) m
LEFT JOIN (
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS f_month, COUNT(*) AS farmer_count
    FROM farmer_tbl GROUP BY f_month
) f ON f.f_month = m.reg_month
LEFT JOIN (
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS b_month, COUNT(*) AS buyer_count
    FROM buyer_tbl GROUP BY b_month
) b ON b.b_month = m.reg_month
ORDER BY m.reg_month DESC
LIMIT 6
";
$ug_res = mysqli_query($conn, $ug_sql);
if ($ug_res) {
    while ($row = mysqli_fetch_assoc($ug_res)) {
        $user_growth[] = $row;
    }
}

mysqli_close($conn);
?>

<main class="dashboard-content">
    <!-- Header Card -->
    <div class="section-card" style="margin-bottom:16px;">
        <h1 style="margin:0; font-size:24px;">Platform Performance &amp; Analytics</h1>
        <div class="small-muted" style="margin-top:4px;">Real-time metrics, transaction volumes, and commercial trading trends across Santa Fe, Romblon</div>
    </div>

    <style>
    .analytics-kpi-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
        margin-top: 14px;
        margin-bottom: 6px;
    }
    /* Keep metric cards in a single row on smaller screens matching farmer/dashboard.php */
    @media (max-width: 900px) {
        .analytics-kpi-grid {
            display: grid !important;
            grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
            gap: 8px !important;
        }
        .analytics-kpi-grid .kpi-card {
            padding: 10px 6px !important;
            text-align: center;
        }
        .analytics-kpi-grid .kpi-card .value {
            font-size: 15px !important;
        }
        .analytics-kpi-grid .kpi-card .label {
            font-size: 11px !important;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .analytics-kpi-grid .kpi-card .sub {
            display: none !important;
        }
    }
    @media (max-width: 480px) {
        .analytics-kpi-grid {
            display: grid !important;
            grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
            gap: 5px !important;
        }
        .analytics-kpi-grid .kpi-card {
            padding: 8px 3px !important;
            text-align: center;
        }
        .analytics-kpi-grid .kpi-card .value {
            font-size: 12px !important;
        }
        .analytics-kpi-grid .kpi-card .label {
            font-size: 9px !important;
            letter-spacing: -0.2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .analytics-kpi-grid .kpi-card .sub {
            display: none !important;
        }
    }

    .analytics-tables-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-top: 16px;
    }
    @media (max-width: 800px) {
        .analytics-tables-grid {
            grid-template-columns: 1fr !important;
        }
    }
    </style>

    <!-- 4 High-Level Metric Cards (Fixed to 4-column scale matching user_dashboard.php) -->
    <div class="kpi-grid analytics-kpi-grid">
        <div class="kpi-card">
            <div class="label">Active Products</div>
            <div class="value" style="color:#FFFFFF;"><?php echo $active_products; ?></div>
            <div class="sub" style="font-size:12px; margin-top:4px; color:rgba(255,255,255,0.92);">Live in market</div>
        </div>

        <div class="kpi-card">
            <div class="label">Active Orders</div>
            <div class="value" style="color:#FFFFFF;"><?php echo $active_orders; ?></div>
            <div class="sub" style="font-size:12px; margin-top:4px; color:rgba(255,255,255,0.92);">In fulfillment</div>
        </div>

        <div class="kpi-card">
            <div class="label">Gross Merchandise Value</div>
            <div class="value" style="color:#FFFFFF;">₱<?php echo number_format($total_revenue, 2); ?></div>
            <div class="sub" style="font-size:12px; margin-top:4px; color:rgba(255,255,255,0.92);">Completed Trades</div>
        </div>

        <div class="kpi-card" style="background:linear-gradient(135deg,#047857,#065f46);">
            <div class="label">Average Order Value</div>
            <div class="value" style="color:#FFFFFF;">₱<?php echo number_format($avg_order_value, 2); ?></div>
            <div class="sub" style="font-size:12px; margin-top:4px; color:rgba(255,255,255,0.92);">Avg. basket per trade</div>
        </div>
    </div>

    <!-- 2 Column Analytics Grid -->
    <div class="analytics-tables-grid">
        
        <!-- Monthly Sales History -->
        <div class="section-card" style="margin-top:0;">
            <h3 style="font-size:16px; font-weight:800; margin:0 0 12px 0;">Monthly Revenue Overview</h3>
            
            <div class="table-wrap">
                <table class="table" style="font-size:13px;">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th style="text-align:center;">Orders</th>
                            <th style="text-align:right;">Total Volume</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($monthly_sales)) { ?>
                            <tr><td colspan="3" class="small-muted" style="text-align:center; padding:20px;">No completed sales records yet.</td></tr>
                        <?php } ?>

                        <?php foreach ($monthly_sales as $ms) { ?>
                            <tr>
                                <td><strong><?php echo date('F Y', strtotime($ms['sale_month'] . '-01')); ?></strong></td>
                                <td style="text-align:center;"><?php echo (int)$ms['order_count']; ?></td>
                                <td style="text-align:right; font-weight:700; color:var(--primary);">
                                    ₱<?php echo number_format((float)$ms['monthly_total'], 2); ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top Performing Producers -->
        <div class="section-card" style="margin-top:0;">
            <h3 style="font-size:16px; font-weight:800; margin:0 0 12px 0;">Top Selling Farmers</h3>

            <div class="table-wrap">
                <table class="table" style="font-size:13px;">
                    <thead>
                        <tr>
                            <th>Farmer</th>
                            <th style="text-align:center;">Orders</th>
                            <th style="text-align:right;">Total Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($top_farmers)) { ?>
                            <tr><td colspan="3" class="small-muted" style="text-align:center; padding:20px;">No producer sales history yet.</td></tr>
                        <?php } ?>

                        <?php foreach ($top_farmers as $tf) { ?>
                            <tr>
                                <td>
                                    <strong><?php echo e($tf['full_name']); ?></strong>
                                    <div class="small-muted">@<?php echo e($tf['username']); ?></div>
                                </td>
                                <td style="text-align:center; font-weight:700;"><?php echo (int)$tf['total_orders']; ?></td>
                                <td style="text-align:right; font-weight:800; color:var(--primary);">
                                    ₱<?php echo number_format((float)$tf['total_earned'], 2); ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Top Selling Products Leaderboard -->
    <div class="section-card" style="margin-top:16px;">
        <h3 style="font-size:16px; font-weight:800; margin:0 0 12px 0;">Best Selling Aquatic Products</h3>

        <div class="table-wrap">
            <table class="table" style="font-size:13px;">
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Producer</th>
                        <th style="text-align:center;">Units Sold (kg/pack)</th>
                        <th style="text-align:right;">Gross Sales</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($top_products)) { ?>
                        <tr><td colspan="5" class="small-muted" style="text-align:center; padding:24px;">No product trade history recorded.</td></tr>
                    <?php } ?>

                    <?php foreach ($top_products as $tp) { ?>
                        <tr>
                            <td><strong><?php echo e($tp['product_name']); ?></strong></td>
                            <td><?php echo e($tp['category_name']); ?></td>
                            <td>@<?php echo e($tp['farmer_name']); ?></td>
                            <td style="text-align:center; font-weight:700;"><?php echo (int)$tp['units_sold']; ?></td>
                            <td style="text-align:right; font-weight:800; color:var(--primary);">
                                ₱<?php echo number_format((float)$tp['gross_sales'], 2); ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- USER GROWTH SECTION: New User Registrations Growth Over Time -->
    <div class="section-card" style="margin-top:16px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:10px;">
            <div>
                <h3 style="font-size:16px; font-weight:800; margin:0;">User Growth &amp; Registration Trends</h3>
                <div class="small-muted" style="margin-top:2px;">New user registrations onboarding to SeaLink platform over time</div>
            </div>
        </div>

        <div class="table-wrap">
            <table class="table" style="font-size:13px;">
                <thead>
                    <tr>
                        <th>Registration Period</th>
                        <th style="text-align:center;">New Farmers Registered</th>
                        <th style="text-align:center;">New Buyers Registered</th>
                        <th style="text-align:center;">Total New Accounts</th>
                        <th>Growth Indicator</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($user_growth)) { ?>
                        <tr><td colspan="5" class="small-muted" style="text-align:center; padding:20px;">No user registration records yet.</td></tr>
                    <?php } ?>

                    <?php foreach ($user_growth as $ug) { 
                        $total = (int)$ug['total_new_users'];
                        $f_pct = ($total > 0) ? round(((int)$ug['new_farmers'] / $total) * 100) : 0;
                    ?>
                        <tr>
                            <td><strong><?php echo date('F Y', strtotime($ug['reg_month'] . '-01')); ?></strong></td>
                            <td style="text-align:center;">
                                <span class="badge active" style="font-size:12px; padding:3px 10px;">
                                    <?php echo (int)$ug['new_farmers']; ?> Farmers
                                </span>
                            </td>
                            <td style="text-align:center;">
                                <span class="badge" style="font-size:12px; padding:3px 10px; background:#eef7f7; color:var(--primary);">
                                    <?php echo (int)$ug['new_buyers']; ?> Buyers
                                </span>
                            </td>
                            <td style="text-align:center; font-weight:900; font-size:14px;">
                                <?php echo $total; ?>
                            </td>
                            <td>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <div style="flex:1; height:8px; background:#e2e2e2; border-radius:4px; overflow:hidden;">
                                        <div style="height:100%; width:<?php echo $f_pct; ?>%; background:var(--primary); border-radius:4px;"></div>
                                    </div>
                                    <span class="small-muted" style="font-size:11px;"><?php echo $f_pct; ?>% Farmers</span>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
