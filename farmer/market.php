<?php
/**
 * SeaLink Web Application
 * File: /farmer/market.php
 * Note: Consolidated to unified /market.php
 */
require_once __DIR__ . '/../config/app.php';
$qs = !empty($_SERVER['QUERY_STRING']) ? ('?' . $_SERVER['QUERY_STRING']) : '';
header('Location: ' . BASE_URL . '/market.php' . $qs);
exit;

/* ========== INPUTS ========== */
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
    $where .= " AND (p.name LIKE CONCAT('%', ?, '%') OR f.username LIKE CONCAT('%', ?, '%') OR c.category_name LIKE CONCAT('%', ?, '%'))";
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

/* ========== PRODUCTS QUERY ========== */
$sql = "
SELECT
  p.product_id, p.farmer_id, p.name, p.price, p.discounted_price, p.selling_deadline, p.stock_quantity, p.image_url,
  c.category_name,
  f.username AS farmer_name,
  f.address AS farmer_address,
  COALESCE(AVG(fb.rating), 0) AS avg_rating
FROM product_tbl p
JOIN farmer_tbl f ON f.farmer_id = p.farmer_id
JOIN category_tbl c ON c.category_id = p.category_id
LEFT JOIN feedback_tbl fb ON fb.product_id = p.product_id
WHERE {$where}
GROUP BY p.product_id
ORDER BY p.created_at DESC
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

$return_path = '/farmer/market.php?' . http_build_query([
    'category_id' => $category_id,
    'search'      => $search
]);
$return_enc = urlencode($return_path);
?>

<main class="dashboard-content farmer-dashboard">
    <!-- Header Inside Container -->
    <div class="section-card" style="margin-bottom:16px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div>
                <h1 style="margin:0; font-size:24px;">SeaLink Marketplace</h1>
                <div class="small-muted" style="margin-top:4px;">Browse products listed by local producers across Santa Fe</div>
            </div>
            <div class="small-muted"><?php echo count($products); ?> product(s) found</div>
        </div>
    </div>

    <div class="market-toolbar">
        <form class="market-filters" method="GET" action="<?php echo BASE_URL; ?>/farmer/market.php" id="marketFilterForm">
            <select name="category_id" id="marketCategory">
                <option value="0">All Categories</option>
                <?php foreach ($categories as $c) { ?>
                    <option value="<?php echo (int)$c['category_id']; ?>" <?php echo ($category_id === (int)$c['category_id']) ? 'selected' : ''; ?>>
                        <?php echo e($c['category_name']); ?>
                    </option>
                <?php } ?>
            </select>

            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                <input type="text" name="search" placeholder="Search product, seller, or category" value="<?php echo e($search); ?>">

                <button type="submit" class="btn btn-primary btn-sm">Search</button>
                <a href="<?php echo BASE_URL; ?>/farmer/market.php" class="btn btn-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>

    <div class="section-card" style="margin-top:0;">
        <div class="market-grid">
            <?php if (count($products) === 0) { ?>
                <div class="panel" style="grid-column: 1 / -1; padding: 40px; text-align:center; color:var(--muted);">
                    No products found matching your search. Try another category or search term.
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

                    <div class="body">
                        <div class="title" style="font-weight:800; font-size:16px;"><?php echo e($p['name']); ?></div>
                        <div class="meta">Seller: <strong><?php echo e($p['farmer_name']); ?></strong></div>
                        <div class="meta">Category: <?php echo e($p['category_name']); ?></div>
                        <div class="meta">Rating: <?php echo number_format((float)$p['avg_rating'], 1); ?> / 5.0</div>
                        <?php
                            $is_sale = is_product_discounted($p['price'], $p['discounted_price'] ?? null, $p['selling_deadline'] ?? null);
                            $eff_price = get_product_effective_price($p['price'], $p['discounted_price'] ?? null, $p['selling_deadline'] ?? null);
                        ?>
                        <div class="row" style="margin-top:10px; align-items:center;">
                            <div class="price" style="color:var(--primary); font-size:16px; font-weight:800;">
                                <?php if ($is_sale) { ?>
                                    <span style="font-size:12px; color:var(--muted); text-decoration:line-through; font-weight:500;">
                                        ₱<?php echo number_format((float)$p['price'], 2); ?>
                                    </span>
                                    <span style="color:#dc2626; font-size:17px; font-weight:900; margin-left:4px;">
                                        ₱<?php echo number_format((float)$eff_price, 2); ?>
                                    </span>
                                <?php } else { ?>
                                    ₱<?php echo number_format((float)$p['price'], 2); ?>
                                <?php } ?>
                            </div>
                            <div class="meta">Stock: <?php echo (int)$p['stock_quantity']; ?></div>
                        </div>

                        <!-- Centered View Details button -->
                        <div style="margin-top:14px; display:flex; justify-content:center;">
                            <a class="btn btn-secondary btn-block" style="text-align:center;"
                               href="<?php echo BASE_URL; ?>/farmer/market_product_detail.php?product_id=<?php echo (int)$p['product_id']; ?>&return=<?php echo $return_enc; ?>">
                                View Details
                            </a>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>