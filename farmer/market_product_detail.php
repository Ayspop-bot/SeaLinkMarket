<?php
/**
 * SeaLink Web Application
 * File: /farmer/market_product_detail.php
 * Note: Consolidated to unified /product_detail.php
 */
require_once __DIR__ . '/../config/app.php';
$qs = !empty($_SERVER['QUERY_STRING']) ? ('?' . $_SERVER['QUERY_STRING']) : '';
header('Location: ' . BASE_URL . '/product_detail.php' . $qs);
exit;

$product_id = (int)($_GET['product_id'] ?? 0);
$return = trim((string)($_GET['return'] ?? ''));

/* Safety: allow internal paths only */
if ($return !== '' && ($return[0] !== '/' || preg_match('/^\s*https?:/i', $return))) {
    $return = '';
}
$back_url = $return ? (BASE_URL . $return) : (BASE_URL . '/farmer/market.php');

if ($product_id <= 0) {
    echo "<main class='dashboard-content'><p>Invalid product.</p></main>";
    require_once __DIR__ . '/../includes/layout_bottom.php';
    exit;
}

$stmt = mysqli_prepare($conn, "
    SELECT
        p.product_id, p.farmer_id, p.name, p.description, p.price, p.discounted_price, p.selling_deadline, p.stock_quantity, p.image_url, p.status,
        c.category_name,
        f.username AS farmer_name, f.full_name AS farmer_fullname, f.address AS farmer_address,
        COALESCE(AVG(fb.rating), 0) AS avg_rating,
        COUNT(fb.feedback_id) AS rating_cnt
    FROM product_tbl p
    JOIN category_tbl c ON c.category_id = p.category_id
    JOIN farmer_tbl f ON f.farmer_id = p.farmer_id
    LEFT JOIN feedback_tbl fb ON fb.product_id = p.product_id
    WHERE p.product_id = ? AND p.status = 'Active'
    GROUP BY p.product_id
    LIMIT 1
");
mysqli_stmt_bind_param($stmt, "i", $product_id);
mysqli_stmt_execute($stmt);
$product = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$product) {
    mysqli_close($conn);
    echo "<main class='dashboard-content'><p>Product not found or inactive.</p></main>";
    require_once __DIR__ . '/../includes/layout_bottom.php';
    exit;
}

$feedbacks = [];
$fb_stmt = mysqli_prepare($conn, "
    SELECT fb.rating, fb.comment, fb.created_at, b.full_name, b.username
    FROM feedback_tbl fb
    JOIN buyer_tbl b ON b.buyer_id = fb.buyer_id
    WHERE fb.product_id = ?
    ORDER BY fb.created_at DESC
    LIMIT 5
");
mysqli_stmt_bind_param($fb_stmt, "i", $product_id);
mysqli_stmt_execute($fb_stmt);
$fb_res = mysqli_stmt_get_result($fb_stmt);
while ($row = mysqli_fetch_assoc($fb_res)) {
    $feedbacks[] = $row;
}
mysqli_stmt_close($fb_stmt);

mysqli_close($conn);
?>

<main class="dashboard-content farmer-dashboard">
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
        <a class="back-arrow" href="<?php echo e($back_url); ?>" onclick="if (document.referrer && document.referrer.indexOf(window.location.host) !== -1) { history.back(); return false; }" aria-label="Back">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path fill="currentColor" d="M15.5 19 8.5 12l7-7 1.5 1.5L11.5 12l5.5 5.5z"/>
            </svg>
        </a>
        <h1 style="margin:0; font-size:24px;">Marketplace Product Details</h1>
    </div>

    <!-- Top 2-Column Layout -->
    <div style="display:grid; grid-template-columns: 1.2fr 1fr; gap:20px; align-items:stretch;">
        
        <!-- Left Side: Product Details, Purchase/Market Options, and About Seller -->
        <div style="display:flex; flex-direction:column; gap:16px;">
            <!-- 1. Product Info Card (Top) -->
            <div class="section-card" style="margin-top:0; padding:20px;">
                <h1 style="font-size:24px; font-weight:800; margin:0 0 6px 0;"><?php echo e($product['name']); ?></h1>
                <div class="small-muted">Category: <strong><?php echo e($product['category_name']); ?></strong></div>
                <div class="small-muted" style="margin-top:4px;">
                    Rating: <strong> <?php echo number_format((float)$product['avg_rating'], 1); ?> / 5.0</strong>
                    (<?php echo (int)$product['rating_cnt']; ?> review<?php echo (int)$product['rating_cnt'] === 1 ? '' : 's'; ?>)
                </div>

                <?php
                    $is_sale = is_product_discounted($product['price'], $product['discounted_price'] ?? null, $product['selling_deadline'] ?? null);
                    $eff_price = get_product_effective_price($product['price'], $product['discounted_price'] ?? null, $product['selling_deadline'] ?? null);
                ?>
                <div style="margin-top:12px; display:flex; align-items:baseline; gap:10px; flex-wrap:wrap;">
                    <?php if ($is_sale) { ?>
                        <span style="font-size:16px; color:var(--muted); text-decoration:line-through; font-weight:600;">
                            ₱<?php echo number_format((float)$product['price'], 2); ?>
                        </span>
                        <span style="font-weight:900; font-size:24px; color:#dc2626;">
                            ₱<?php echo number_format((float)$eff_price, 2); ?>
                        </span>
                        <span style="font-size:14px; font-weight:700; color:var(--muted);">/ kg</span>
                        <span style="background:#dc2626; color:#fff; font-size:11px; font-weight:800; padding:2px 8px; border-radius:6px;">
                            SALE
                        </span>
                    <?php } else { ?>
                        <span style="font-weight:800; font-size:24px; color:var(--primary);">
                            ₱<?php echo number_format((float)$product['price'], 2); ?> / kg
                        </span>
                    <?php } ?>
                </div>
                <div class="small-muted" style="margin-top:2px;">
                    Available Stock: <strong><?php echo (int)$product['stock_quantity']; ?> kg</strong>
                </div>

                <?php if (!empty($product['description'])) { ?>
                    <div style="margin-top:14px; padding:14px; background:#f9fbfb; border-radius:10px; border:1px solid var(--border);">
                        <div style="font-weight:700; font-size:13px; margin-bottom:4px; color:var(--muted);">Description</div>
                        <div style="line-height:1.6; font-size:14px;"><?php echo nl2br(e($product['description'])); ?></div>
                    </div>
                <?php } ?>
            </div>

            <!-- 2. Purchase / Trading Info Card (Middle) -->
            <div class="section-card" style="margin-top:0; padding:20px;">
                <h2 style="font-size:18px; font-weight:800; margin-top:0; margin-bottom:12px;">Market Trading Details</h2>
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <div>
                        <div class="small-muted">Listing Status</div>
                        <span class="badge active" style="margin-top:4px;"><?php echo e($product['status']); ?></span>
                    </div>
                    <div>
                        <div class="small-muted">Supply Volume</div>
                        <strong style="font-size:16px;"><?php echo (int)$product['stock_quantity']; ?> kg available</strong>
                    </div>
                </div>

                <div style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap;">
                    <a class="btn btn-primary btn-sm" style="flex:1; text-align:center;"
                       href="<?php echo BASE_URL; ?>/farmer/messages.php">
                        Contact Farmers
                    </a>
                </div>
            </div>

            <!-- 3. About Seller Panel (Bottom) -->
            <div class="section-card" style="margin-top:0; padding:20px;">
                <h2 style="font-size:18px; font-weight:800; margin-top:0; margin-bottom:12px;">About Seller</h2>

                <div style="display:flex; gap:12px; align-items:center;">
                    <div style="width:54px; height:54px; border-radius:50%; overflow:hidden; background:#f0f0f0; border:1px solid var(--border); display:flex; align-items:center; justify-content:center; font-size:22px; flex-shrink:0;">
                        
                    </div>
                    <div>
                        <div style="font-weight:800; font-size:16px; color:var(--text);"><?php echo e($product['farmer_name']); ?></div>
                        <div class="small-muted"><?php echo e($product['farmer_fullname']); ?></div>
                    </div>
                </div>

                <div class="small-muted" style="margin-top:10px;">
                    Location: <?php echo e($product['farmer_address']); ?>
                </div>
            </div>
        </div>

        <!-- Right Side: Picture of the Product (Matching height of left side) -->
        <div style="display:flex; flex-direction:column; height:100%;">
            <div class="section-card" style="margin-top:0; padding:0; overflow:hidden; height:100%; min-height:480px; display:flex; flex-direction:column; border-radius:14px; border:1px solid var(--border); background:var(--bg); box-shadow:var(--card-shadow);">
                <?php if (!empty($product['image_url'])) { ?>
                    <img src="<?php echo BASE_URL . '/' . e($product['image_url']); ?>" alt="<?php echo e($product['name']); ?>" style="width:100%; height:100%; flex:1; object-fit:cover; display:block;">
                <?php } else { ?>
                    <div style="display:flex;align-items:center;justify-content:center;height:100%;flex:1;color:var(--muted);">No Image Available</div>
                <?php } ?>
            </div>
        </div>
    </div>

    <!-- Customer Reviews Section (Horizontally Matching Full Width Underneath) -->
    <div class="section-card" style="margin-top:20px; padding:22px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:14px; border-bottom:1px solid var(--border); padding-bottom:10px;">
            <h3 style="font-size:18px; font-weight:800; margin:0;">Customer Reviews</h3>
            <span class="small-muted"><?php echo number_format((float)$product['avg_rating'], 1); ?> / 5.0 (<?php echo (int)$product['rating_cnt']; ?> reviews)</span>
        </div>

        <?php if (empty($feedbacks)) { ?>
            <div class="small-muted" style="padding:16px 0; text-align:center;">No reviews yet for this product.</div>
        <?php } else { ?>
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:12px;">
                <?php foreach ($feedbacks as $fb) { ?>
                    <div style="padding:14px; background:#fafafa; border:1px solid var(--border); border-radius:10px; display:flex; flex-direction:column; justify-content:space-between;">
                        <div>
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <strong style="font-size:14px;"><?php echo e($fb['full_name']); ?></strong>
                                <span class="small-muted" style="font-size:11px;"><?php echo date('M d, Y', strtotime($fb['created_at'])); ?></span>
                            </div>
                            <div style="color:#f39c12; font-size:14px; margin-top:2px;">
                                <?php for($i = 1; $i <= 5; $i++) echo ($i <= $fb['rating'] ? '★' : '☆'); ?>
                            </div>
                            <div style="margin-top:6px; font-size:14px; line-height:1.5; color:var(--text);"><?php echo nl2br(e($fb['comment'])); ?></div>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
