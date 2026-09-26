<?php
/**
 * SeaLink Web Application
 * File: /admin/products.php
 * Purpose: Full Admin Product Monitoring with category & status filters, search, and status toggle.
 * Connected To:
 * - /admin/actions/product_status.php
 * Uses: product_tbl, farmer_tbl, category_tbl
 */

require_once __DIR__ . '/../includes/auth_check.php';
check_access(['Content Admin', 'User Admin']);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$active_tab = 'products';
$page_title = "Product Management - SeaLink Admin";
require_once __DIR__ . '/../includes/layout_top.php';

$search = trim((string)($_GET['search'] ?? ''));
$category_id = (int)($_GET['category_id'] ?? 0);
$status_filter = trim((string)($_GET['status'] ?? 'All'));

/* ========== CATEGORIES ========== */
$categories = [];
$c_res = mysqli_query($conn, "SELECT category_id, category_name FROM category_tbl ORDER BY category_name ASC");
if ($c_res) {
    while ($row = mysqli_fetch_assoc($c_res)) $categories[] = $row;
}

/* ========== BUILD QUERY ========== */
$where = "1=1";
$params = [];
$types = "";

if ($search !== '') {
    $where .= " AND (p.name LIKE CONCAT('%', ?, '%') OR f.username LIKE CONCAT('%', ?, '%') OR f.full_name LIKE CONCAT('%', ?, '%'))";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $types .= "sss";
}

if ($category_id > 0) {
    $where .= " AND p.category_id = ?";
    $params[] = $category_id;
    $types .= "i";
}

if ($status_filter === 'Active' || $status_filter === 'Inactive') {
    $where .= " AND p.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$sql = "
SELECT 
    p.product_id, p.name, p.price, p.discounted_price, p.selling_deadline, p.stock_quantity, p.image_url, p.status, p.created_at,
    f.farmer_id, f.username AS farmer_name, f.full_name AS farmer_fullname,
    c.category_name,
    COALESCE(SUM(CASE WHEN o.order_status = 'Completed' THEN oi.quantity ELSE 0 END), 0) AS total_sold
FROM product_tbl p
JOIN farmer_tbl f ON f.farmer_id = p.farmer_id
JOIN category_tbl c ON c.category_id = p.category_id
LEFT JOIN order_item_tbl oi ON oi.product_id = p.product_id
LEFT JOIN order_tbl o ON o.order_id = oi.order_id
WHERE {$where}
GROUP BY p.product_id
ORDER BY p.created_at DESC
";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    $bind = [$stmt, $types];
    foreach ($params as $k => $v) $bind[] = &$params[$k];
    call_user_func_array('mysqli_stmt_bind_param', $bind);
}
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$products = [];
while ($row = mysqli_fetch_assoc($res)) {
    $products[] = $row;
}
mysqli_stmt_close($stmt);
mysqli_close($conn);

$total_items = count($products);
?>

<main class="dashboard-content">
    <!-- Header Section Card -->
    <div class="section-card" style="margin-bottom:16px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div>
                <h1 style="margin:0; font-size:24px;">SeaLink Marketplace Products</h1>
                <div class="small-muted" style="margin-top:4px;">Monitor all listed aquatic goods, inventory stocks, and vendor offerings</div>
            </div>
            <div class="small-muted"><?php echo $total_items; ?> product(s) found</div>
        </div>
    </div>

    <!-- Filter / Search Toolbar (Split from text header, just like in Market tab) -->
    <div class="market-toolbar" style="margin-bottom:16px;">
        <form method="GET" action="" class="market-filters" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; width:100%;">
            <select name="category_id">
                <option value="0">All Categories</option>
                <?php foreach ($categories as $c) { ?>
                    <option value="<?php echo (int)$c['category_id']; ?>" <?php echo ($category_id === (int)$c['category_id']) ? 'selected' : ''; ?>>
                        <?php echo e($c['category_name']); ?>
                    </option>
                <?php } ?>
            </select>

            <select name="status">
                <option value="All" <?php echo ($status_filter === 'All') ? 'selected' : ''; ?>>All Status</option>
                <option value="Active" <?php echo ($status_filter === 'Active') ? 'selected' : ''; ?>>Active (On Sale)</option>
                <option value="Inactive" <?php echo ($status_filter === 'Inactive') ? 'selected' : ''; ?>>Inactive (Out of Stock)</option>
            </select>

            <div class="search-input-wrapper">
                <button type="button" class="search-clear-x" onclick="clearSearchAndRefresh(this)" title="Clear and refresh search" aria-label="Clear search" <?php echo empty($search) ? 'style="display:none;"' : 'style="display:flex;"'; ?>>&times;</button>
                <input type="text" name="search" placeholder="Search product or farmer..."
                       value="<?php echo e($search); ?>" oninput="checkSearchClear(this)">
            </div>

            <button type="submit" class="btn btn-primary btn-sm">Search</button>
        </form>
    </div>

    <!-- Products Table -->
    <div class="section-card">
        <div class="table-wrap">
            <table class="table" style="font-size:14px;">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Seller / Farmer</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock Available</th>
                        <th>Total Sold</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)) { ?>
                        <tr><td colspan="7" class="small-muted" style="text-align:center; padding:30px;">No products match your filter criteria.</td></tr>
                    <?php } ?>

                    <?php foreach ($products as $p) { 
                        $is_active = ($p['status'] === 'Active');
                    ?>
                        <tr>
                            <td>
                                <div style="display:flex; align-items:center; gap:12px;">
                                    <div style="width:42px; height:42px; border-radius:8px; overflow:hidden; background:#eee; flex-shrink:0;">
                                        <?php if (!empty($p['image_url'])) { ?>
                                            <img src="<?php echo BASE_URL . '/' . e($p['image_url']); ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                                        <?php } else { ?>
                                            <div style="display:flex;align-items:center;justify-content:center;height:100%;font-size:11px;color:var(--muted);">No Img</div>
                                        <?php } ?>
                                    </div>
                                    <div>
                                        <strong><?php echo e($p['name']); ?></strong>
                                        <div class="small-muted" style="font-size:11px;">Listed on <?php echo date('M d, Y', strtotime($p['created_at'])); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div><strong><?php echo e($p['farmer_fullname']); ?></strong></div>
                                <div class="small-muted" style="font-size:12px;">@<?php echo e($p['farmer_name']); ?></div>
                            </td>
                            <td><?php echo e($p['category_name']); ?></td>
                            <td style="font-weight:700; color:var(--primary);">
                                <?php if (is_product_discounted($p['price'], $p['discounted_price'] ?? null, $p['selling_deadline'] ?? null)) { 
                                    $eff_price = get_product_effective_price($p['price'], $p['discounted_price'] ?? null, $p['selling_deadline'] ?? null);
                                ?>
                                    <div style="font-size:11px; color:var(--muted); text-decoration:line-through; font-weight:normal;">₱<?php echo number_format((float)$p['price'], 2); ?></div>
                                    <div style="color:#dc2626;">₱<?php echo number_format((float)$eff_price, 2); ?></div>
                                <?php } else { ?>
                                    ₱<?php echo number_format((float)$p['price'], 2); ?>
                                <?php } ?>
                            </td>
                            <td><?php echo (int)$p['stock_quantity']; ?></td>
                            <td><strong><?php echo (int)$p['total_sold']; ?></strong></td>
                            <td>
                                <span class="badge <?php echo $is_active ? 'active' : 'inactive'; ?>">
                                    <?php echo $is_active ? 'On Sale' : 'Out of Stock'; ?>
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
