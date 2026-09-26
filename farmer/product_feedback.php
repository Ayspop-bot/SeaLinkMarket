<?php
/**
 * SeaLink Web Application
 * File: /farmer/product_feedback.php
 * Purpose: Shows feedback list for a farmer-owned product.
 * Connected To: dashboard table "View Feedback", product_gallery cards
 * Uses: product_tbl, feedback_tbl, buyer_tbl
 * Notes:
 * - No nav tabs on top ($hide_nav = true)
 * - Back button returns to return param
 * - Shows product header with image + price /kg
 */

require_once __DIR__ . '/../includes/auth_check.php';
check_access('farmer');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

/* ========== PAGE SETTINGS ========== */
$hide_nav = true;
$page_title = "Product Feedback - SeaLink";
require_once __DIR__ . '/../includes/layout_top.php';

/* ========== INPUT VALIDATION ========== */
$farmer_id  = (int)($_SESSION['user_id'] ?? 0);
$product_id = (int)($_GET['product_id'] ?? 0);

$return = trim((string)($_GET['return'] ?? ''));
/* Safety: allow internal paths only */
if ($return !== '' && ($return[0] !== '/' || preg_match('/^\s*https?:/i', $return))) {
    $return = '';
}
$back_url = $return ? (BASE_URL . $return) : (BASE_URL . '/farmer/manage_products.php');

if ($product_id <= 0) {
    echo "<main class='dashboard-content'><p>Invalid product.</p></main>";
    require_once __DIR__ . '/../includes/layout_bottom.php';
    exit;
}

/* ========== PRODUCT + OWNERSHIP CHECK ========== */
$stmt = mysqli_prepare($conn, "
    SELECT product_id, name, price, image_url, stock_quantity
    FROM product_tbl
    WHERE product_id = ? AND farmer_id = ?
    LIMIT 1
");
mysqli_stmt_bind_param($stmt, "ii", $product_id, $farmer_id);
mysqli_stmt_execute($stmt);
$product = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$product) {
    mysqli_close($conn);
    echo "<main class='dashboard-content'><p>Product not found or not owned by you.</p></main>";
    require_once __DIR__ . '/../includes/layout_bottom.php';
    exit;
}

/* ========== FEEDBACK LIST ========== */
$stmt = mysqli_prepare($conn, "
    SELECT f.feedback_id, f.rating, f.comment, f.created_at, b.full_name, b.username
    FROM feedback_tbl f
    JOIN buyer_tbl b ON b.buyer_id = f.buyer_id
    WHERE f.product_id = ?
    ORDER BY f.created_at DESC
");
mysqli_stmt_bind_param($stmt, "i", $product_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$feedbacks = [];
$total_stars = 0;
while ($row = mysqli_fetch_assoc($res)) {
    $feedbacks[] = $row;
    $total_stars += (int)$row['rating'];
}
mysqli_stmt_close($stmt);

$feedback_count = count($feedbacks);
$avg_rating = $feedback_count > 0 ? ($total_stars / $feedback_count) : 0;

mysqli_close($conn);
?>

<main class="dashboard-content farmer-dashboard">
    <!-- Back arrow + title -->
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
        <a class="back-arrow" href="<?php echo e($back_url); ?>" aria-label="Back">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path fill="currentColor" d="M15.5 19 8.5 12l7-7 1.5 1.5L11.5 12l5.5 5.5z"/>
            </svg>
        </a>
        <h1 style="margin:0; font-size:24px;">Product Feedback</h1>
    </div>

    <!-- Product Summary Card -->
    <div class="panel" style="display:flex; gap:16px; flex-wrap:wrap; align-items:center; margin-bottom:20px;">
        <div style="width:100px; height:80px; border-radius:10px; overflow:hidden; background:#f2f2f2; border:1px solid #eee; flex-shrink:0;">
            <?php if (!empty($product['image_url'])) { ?>
                <img src="<?php echo BASE_URL . '/' . e($product['image_url']); ?>"
                     alt="<?php echo e($product['name']); ?>"
                     style="width:100%;height:100%;object-fit:cover;display:block;">
            <?php } else { ?>
                <div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--muted);">No Image</div>
            <?php } ?>
        </div>

        <div>
            <h2 style="margin:0 0 4px 0; font-size:18px; font-weight:800;"><?php echo e($product['name']); ?></h2>
            <div style="color:var(--primary); font-weight:800; font-size:16px;">
                ₱<?php echo number_format((float)$product['price'], 2); ?> / kg
            </div>
            <div class="small-muted" style="margin-top:2px;">
                Average Rating: <strong>⭐ <?php echo number_format($avg_rating, 1); ?> / 5.0</strong>
                (<?php echo $feedback_count; ?> review<?php echo $feedback_count === 1 ? '' : 's'; ?>)
            </div>
        </div>
    </div>

    <h2 style="font-size:18px; font-weight:800; margin:0 0 12px 0;">Customer Reviews</h2>

    <?php if ($feedback_count === 0) { ?>
        <div class="panel" style="text-align:center; padding:30px; color:var(--muted);">
            No customer feedback has been submitted for this product yet.
        </div>
    <?php } ?>

    <div style="display:flex; flex-direction:column; gap:12px;">
        <?php foreach ($feedbacks as $f) { ?>
            <div class="panel" style="border:1px solid var(--border);">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                    <div>
                        <strong><?php echo e($f['full_name']); ?></strong>
                        <span class="small-muted">(@<?php echo e($f['username']); ?>)</span>
                    </div>
                    <span class="small-muted"><?php echo date('M d, Y h:i A', strtotime($f['created_at'])); ?></span>
                </div>

                <div style="margin-top:6px; color:#d9891c; font-size:15px; font-weight:700;">
                    <?php
                        $stars = (int)$f['rating'];
                        for ($i = 1; $i <= 5; $i++) {
                            echo ($i <= $stars) ? '★' : '☆';
                        }
                    ?>
                    <span style="color:var(--text); font-size:13px; margin-left:6px;"><?php echo $stars; ?>/5</span>
                </div>

                <div style="margin-top:8px; line-height:1.5; color:var(--text);">
                    <?php echo nl2br(e($f['comment'] ?? '')); ?>
                </div>
            </div>
        <?php } ?>
    </div>

</main>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>