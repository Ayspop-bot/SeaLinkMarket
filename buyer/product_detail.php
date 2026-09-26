<?php
/**
 * SeaLink Web Application
 * File: /buyer/product_detail.php
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

$back_url = $return ? (BASE_URL . $return) : (BASE_URL . '/buyer/market.php');

/* Return path back to this product detail page */
$detail_self = '/buyer/product_detail.php?' . http_build_query([
    'product_id' => $product_id,
    'return'     => $return
]);
$detail_self_enc = urlencode($detail_self);

if ($product_id <= 0) {
    echo "<main class='dashboard-content'><p>Invalid product.</p></main>";
    require_once __DIR__ . '/../includes/layout_bottom.php';
    exit;
}

$sql = "
SELECT
  p.product_id, p.farmer_id, p.name, p.description, p.price, p.discounted_price, p.stock_quantity, p.image_url, p.status,
  p.harvested_at, p.shelf_life_hours, p.selling_deadline, p.is_boosted, p.boost_tier,
  c.category_name,
  f.username AS farmer_name, f.full_name AS farmer_fullname, f.address AS farmer_address, f.contact_number, f.facebook_account, f.profile_image,
  COALESCE(AVG(fb.rating), 0) AS avg_rating,
  COUNT(fb.feedback_id) AS rating_cnt
FROM product_tbl p
JOIN category_tbl c ON c.category_id = p.category_id
JOIN farmer_tbl f ON f.farmer_id = p.farmer_id
LEFT JOIN feedback_tbl fb ON fb.product_id = p.product_id
WHERE p.product_id = ? AND p.status = 'Active'
GROUP BY p.product_id
LIMIT 1
";
$stmt = mysqli_prepare($conn, $sql);
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

$is_discounted = is_product_discounted($product['price'], $product['discounted_price'] ?? null, $product['selling_deadline'] ?? null);
$effective_price = get_product_effective_price($product['price'], $product['discounted_price'] ?? null, $product['selling_deadline'] ?? null);
$discount_pct = ($is_discounted && (float)$product['price'] > 0) ? round((1 - ($effective_price / (float)$product['price'])) * 100) : 0;

$max_qty = max(0, (int)$product['stock_quantity']);

/* Load recent feedbacks for this product */
$feedbacks = [];
$stmt = mysqli_prepare($conn, "
    SELECT fb.rating, fb.comment, fb.created_at, b.full_name, b.username
    FROM feedback_tbl fb
    JOIN buyer_tbl b ON b.buyer_id = fb.buyer_id
    WHERE fb.product_id = ?
    ORDER BY fb.created_at DESC
    LIMIT 5
");
mysqli_stmt_bind_param($stmt, "i", $product_id);
mysqli_stmt_execute($stmt);
$fb_res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($fb_res)) {
    $feedbacks[] = $row;
}
mysqli_stmt_close($stmt);
/* Check if this buyer has a completed order for this product */
$buyer_id = (int)($_SESSION['user_id'] ?? 0);
$user_role = $_SESSION['user_role'] ?? '';
$has_completed_order = false;
$existing_user_review = null;
$completed_order_id = 0;

if ($buyer_id > 0 && $user_role === 'buyer') {
    $po_stmt = mysqli_prepare($conn, "
        SELECT o.order_id, fb.feedback_id, fb.rating, fb.comment
        FROM order_item_tbl oi
        JOIN order_tbl o ON o.order_id = oi.order_id
        LEFT JOIN feedback_tbl fb ON fb.product_id = oi.product_id AND fb.buyer_id = ?
        WHERE o.buyer_id = ? AND oi.product_id = ? AND o.order_status = 'Completed'
        ORDER BY o.order_date DESC
        LIMIT 1
    ");
    mysqli_stmt_bind_param($po_stmt, "iii", $buyer_id, $buyer_id, $product_id);
    mysqli_stmt_execute($po_stmt);
    $po_res = mysqli_fetch_assoc(mysqli_stmt_get_result($po_stmt));
    mysqli_stmt_close($po_stmt);

    if ($po_res) {
        $has_completed_order = true;
        $completed_order_id = (int)$po_res['order_id'];
        if (!empty($po_res['feedback_id'])) {
            $existing_user_review = $po_res;
        }
    }
}

mysqli_close($conn);
?>

<main class="dashboard-content buyer-dashboard">
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
        <a class="back-arrow" href="<?php echo e($back_url); ?>" onclick="if (document.referrer && document.referrer.indexOf(window.location.host) !== -1) { history.back(); return false; }" aria-label="Back">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path fill="currentColor" d="M15.5 19 8.5 12l7-7 1.5 1.5L11.5 12l5.5 5.5z"/>
            </svg>
        </a>
        <h1 style="margin:0; font-size:24px;">Product Details</h1>
    </div>

    <!-- Top 2-Column Layout -->
    <div style="display:grid; grid-template-columns: 1.2fr 1fr; gap:20px; align-items:stretch;">
        
        <!-- Left Side: Product Information, Purchase Options, and About Seller -->
        <div style="display:flex; flex-direction:column; gap:16px;">
            <!-- 1. Product Info Card (Top) -->
            <div class="section-card" style="margin-top:0; padding:20px;">
                <h1 style="font-size:24px; font-weight:800; margin:0 0 6px 0;"><?php echo e($product['name']); ?></h1>
                <?php if (!empty($product['is_boosted'])) { ?>
                    <div style="margin-bottom:8px;">
                        <span style="background:#fef08a; color:#854d0e; font-size:12px; font-weight:800; padding:3px 10px; border-radius:6px; display:inline-flex; align-items:center; gap:4px;">
                            Sponsored Listing
                        </span>
                    </div>
                <?php } ?>

                <div class="small-muted">Category: <strong><?php echo e($product['category_name']); ?></strong></div>
                <div class="small-muted" style="margin-top:4px;">
                    Rating: <strong><?php echo number_format((float)$product['avg_rating'], 1); ?> / 5.0</strong>
                    (<?php echo (int)$product['rating_cnt']; ?> review<?php echo (int)$product['rating_cnt'] === 1 ? '' : 's'; ?>)
                </div>

                <div style="margin-top:12px; display:flex; align-items:baseline; gap:10px; flex-wrap:wrap;">
                    <?php if ($is_discounted) { ?>
                        <span style="font-size:16px; color:var(--muted); text-decoration:line-through; font-weight:600;">
                            ₱<?php echo number_format((float)$product['price'], 2); ?>
                        </span>
                        <span style="font-weight:900; font-size:26px; color:#dc2626;">
                            ₱<?php echo number_format((float)$effective_price, 2); ?>
                        </span>
                        <span style="font-size:14px; font-weight:700; color:var(--muted);">/ kg</span>
                        <span style="background:#dc2626; color:#fff; font-size:12px; font-weight:800; padding:2px 8px; border-radius:6px;">
                            -<?php echo $discount_pct; ?>% OFF
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

                <?php if ($is_discounted && !empty($product['selling_deadline'])) { ?>
                    <div style="margin-top:12px; padding:10px 14px; background:#fef2f2; border:1px solid #fecaca; border-radius:8px;">
                        <div style="font-weight:800; font-size:13px; color:#991b1b; display:flex; align-items:center; gap:6px;">
                            Limited Time Sale: <span id="detailCountdown" data-deadline="<?php echo strtotime($product['selling_deadline']); ?>" style="font-size:14px; font-weight:900; color:#dc2626;">Calculating...</span>
                        </div>
                        <div class="small-muted" style="font-size:12px; margin-top:2px;">
                            Discounted price offered by farmer. Order before the sale ends to secure this discount!
                        </div>
                    </div>
                <?php } elseif (!empty($product['selling_deadline'])) { ?>
                    <div style="margin-top:12px; padding:10px 14px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px;">
                        <div style="font-weight:800; font-size:13px; color:#166534; display:flex; align-items:center; gap:6px;">
                            Freshness Window: <span id="detailCountdown" data-deadline="<?php echo strtotime($product['selling_deadline']); ?>" style="font-size:14px; font-weight:900; color:#15803d;">Calculating...</span>
                        </div>
                        <div class="small-muted" style="font-size:12px; margin-top:2px;">
                            Guaranteed fresh harvest. Order before the selling window ends to secure prime catch.
                        </div>
                    </div>
                <?php } ?>

                <!-- Suggestive Cooking Suggestion -->
                <div style="margin-top:12px; padding:10px 14px; background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px;">
                    <div style="font-weight:800; font-size:13px; color:#0369a1;">Culinary Suggestion</div>
                    <div style="font-size:13px; color:#0c4a6e; margin-top:2px;">
                        Fresh <?php echo e($product['name']); ?> pairs deliciously with local Santa Fe recipes in our Information Hub!
                        <a href="<?php echo BASE_URL; ?>/infohub/index.php?category=Recipes" style="color:#0284c7; font-weight:700; text-decoration:underline;">Browse Recipes &rarr;</a>
                    </div>
                </div>

                <?php if (!empty($product['description'])) { ?>
                    <div style="margin-top:14px; padding:14px; background:#f9fbfb; border-radius:10px; border:1px solid var(--border);">
                        <div style="font-weight:700; font-size:13px; margin-bottom:4px; color:var(--muted);">Description</div>
                        <div style="line-height:1.6; font-size:14px;"><?php echo nl2br(e($product['description'])); ?></div>
                    </div>
                <?php } ?>
            </div>

            <!-- 2. Purchase Options Card (Middle) -->
            <div class="section-card" style="margin-top:0; padding:20px;">
                <h2 style="font-size:18px; font-weight:800; margin-top:0; margin-bottom:12px;">Purchase Options</h2>

                <?php if ($max_qty <= 0) { ?>
                    <div class="alert alert-warning" style="margin-top:10px;">This product is currently out of stock.</div>
                <?php } else { ?>
                    <form method="POST" action="<?php echo BASE_URL; ?>/buyer/actions/cart_add.php" id="purchaseForm">
                        <input type="hidden" name="product_id" value="<?php echo (int)$product['product_id']; ?>">
                        <input type="hidden" name="return" value="<?php echo e($detail_self); ?>">

                        <div class="form-group">
                            <label style="font-weight:700;">Quantity (kg)</label>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="adjustQty(-1)">-</button>
                                <input type="number" name="quantity" id="qtyInput" value="1" min="1" max="<?php echo $max_qty; ?>"
                                       style="width:90px; text-align:center; padding:8px; border:1px solid var(--border); border-radius:8px;" required>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="adjustQty(1)">+</button>
                                <span class="small-muted" style="margin-left:6px;">max: <?php echo $max_qty; ?> kg</span>
                            </div>
                        </div>

                        <div style="margin-top:16px; display:flex; gap:12px; flex-wrap:wrap;">
                            <?php if (!is_logged_in() || ($_SESSION['user_role'] ?? '') !== 'buyer') { ?>
                                <button class="btn btn-cart" style="flex:1;" type="button" onclick="openGuestPromptModal('Cart')">
                                    Add to Cart
                                </button>

                                <button type="button" class="btn btn-confirm" style="flex:1;" onclick="openGuestPromptModal('Cart')">
                                    Buy Now
                                </button>
                            <?php } else { ?>
                                <button class="btn btn-cart" style="flex:1;" type="submit">
                                    Add to Cart
                                </button>

                                <button type="button" class="btn btn-confirm" style="flex:1;" onclick="goToBuyNow()">
                                    Buy Now
                                </button>
                            <?php } ?>
                        </div>
                    </form>
                <?php } ?>
            </div>

            <!-- 3. About Seller Panel (Bottom) -->
            <div class="section-card" style="margin-top:0; padding:20px;">
                <h2 style="font-size:18px; font-weight:800; margin-top:0; margin-bottom:12px;">About Seller</h2>

                <div style="display:flex; gap:12px; align-items:center;">
                    <div style="width:54px; height:54px; border-radius:50%; overflow:hidden; background:#f0f0f0; border:1px solid var(--border); flex-shrink:0;">
                        <?php if (!empty($product['profile_image'])) { ?>
                            <img src="<?php echo BASE_URL . '/' . e($product['profile_image']); ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                        <?php } else { ?>
                            <div style="display:flex;align-items:center;justify-content:center;height:100%;font-size:22px;"></div>
                        <?php } ?>
                    </div>
                    <div>
                        <div style="font-weight:800; font-size:16px; color:var(--text);"><?php echo e($product['farmer_name']); ?></div>
                        <div class="small-muted"><?php echo e($product['farmer_fullname']); ?></div>
                    </div>
                </div>

                <div class="small-muted" style="margin-top:10px;">
                    Location: <?php echo e($product['farmer_address']); ?>
                </div>

                <div style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap;">
                    <a class="btn btn-secondary btn-sm" style="flex:1; text-align:center;"
                       href="<?php echo BASE_URL; ?>/buyer/farmer_store.php?farmer_id=<?php echo (int)$product['farmer_id']; ?>&return=<?php echo $detail_self_enc; ?>">
                        Visit Store
                    </a>
                    <a class="btn btn-send btn-sm" style="flex:1; text-align:center;"
                       href="<?php echo BASE_URL; ?>/buyer/messages.php?farmer_id=<?php echo (int)$product['farmer_id']; ?>">
                        Message
                    </a>
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
            <?php if ($has_completed_order) { ?>
                <button type="button" class="btn btn-sm btn-secondary" onclick="openModal('productFeedbackModal')">
                    <?php echo $existing_user_review ? 'Edit Your Review' : 'Write a Review'; ?>
                </button>
            <?php } ?>
        </div>

        <?php if (!$has_completed_order) { ?>
            <div class="small-muted" style="font-size:13px; margin-bottom:14px; background:#f9fbfb; padding:10px 14px; border-radius:8px; border:1px solid var(--border);">
                <em>Have you ordered this product? Once your order is fulfilled/completed, you can rate and share feedback here.</em>
            </div>
        <?php } ?>

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

<script>
function adjustQty(change) {
    var input = document.getElementById('qtyInput');
    if (!input) return;
    var current = parseInt(input.value) || 1;
    var min = parseInt(input.min) || 1;
    var max = parseInt(input.max) || 9999;
    var updated = current + change;
    if (updated >= min && updated <= max) {
        input.value = updated;
    }
}

function goToBuyNow() {
    var input = document.getElementById('qtyInput');
    var qty = input ? (parseInt(input.value) || 1) : 1;
    var pid = <?php echo (int)$product['product_id']; ?>;
    var ret = encodeURIComponent('<?php echo $detail_self; ?>');
    window.location.href = '<?php echo BASE_URL; ?>/buyer/checkout.php?mode=buynow&product_id=' + pid + '&qty=' + qty + '&return=' + ret;
}

function updateDetailCountdown() {
    var el = document.getElementById('detailCountdown');
    if (!el) return;
    var deadlineSec = parseInt(el.getAttribute('data-deadline'));
    if (!deadlineSec || isNaN(deadlineSec)) return;
    var deadline = deadlineSec * 1000;
    var now = new Date().getTime();
    var distance = deadline - now;
    if (distance <= 0) {
        el.innerHTML = '<span style="color:#b42318;">Freshness Window Expired</span>';
        return;
    }
    var hours = Math.floor(distance / (1000 * 60 * 60));
    var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
    var seconds = Math.floor((distance % (1000 * 60)) / 1000);
    el.innerHTML = (hours < 10 ? '0' : '') + hours + 'h : ' + (minutes < 10 ? '0' : '') + minutes + 'm : ' + (seconds < 10 ? '0' : '') + seconds + 's';
}
if (document.getElementById('detailCountdown')) {
    updateDetailCountdown();
    setInterval(updateDetailCountdown, 1000);
}
</script>

<?php if ($has_completed_order) { ?>
<!-- Product Review Modal -->
<div class="modal" id="productFeedbackModal" aria-hidden="true">
    <div class="modal-backdrop" onclick="closeModal('productFeedbackModal')"></div>
    <div class="modal-content" style="max-width:480px;">
        <div class="modal-header">
            <h2 style="font-size:18px; font-weight:800; margin:0;">
                <?php echo $existing_user_review ? 'Edit Your Review' : 'Write a Product Review'; ?>
            </h2>
            <button type="button" class="modal-close-x" onclick="closeModal('productFeedbackModal')" aria-label="Close">&times;</button>
        </div>
        <form method="POST" action="<?php echo BASE_URL; ?>/buyer/actions/submit_feedback.php">
            <input type="hidden" name="product_id" value="<?php echo (int)$product['product_id']; ?>">
            <input type="hidden" name="farmer_id" value="<?php echo (int)$product['farmer_id']; ?>">
            <input type="hidden" name="order_id" value="<?php echo (int)$completed_order_id; ?>">
            <input type="hidden" name="return" value="<?php echo e($detail_self); ?>">

            <div style="margin:12px 0; background:#f8fafc; padding:10px 12px; border-radius:8px; border:1px solid #e2e8f0;">
                <span class="small-muted" style="font-size:12px;">Product:</span>
                <strong style="display:block; font-size:14px; margin-top:2px;"><?php echo e($product['name']); ?></strong>
            </div>

            <div class="form-group">
                <label style="font-weight:700; font-size:13px; margin-bottom:6px; display:block;">Rating (1 to 5 Stars) *</label>
                <select name="rating" required style="width:100%; padding:8px 12px; border:1px solid var(--border); border-radius:8px; font-size:13.5px;">
                    <?php
                    $cur_rating = (int)($existing_user_review['rating'] ?? 5);
                    $star_options = [
                        5 => '⭐⭐⭐⭐⭐ 5 Stars - Excellent Quality',
                        4 => '⭐⭐⭐⭐ 4 Stars - Very Good',
                        3 => '⭐⭐⭐ 3 Stars - Average',
                        2 => '⭐⭐ 2 Stars - Fair',
                        1 => '⭐ 1 Star - Poor'
                    ];
                    foreach ($star_options as $stars => $star_text) {
                        $sel = ($cur_rating === $stars) ? 'selected' : '';
                        echo "<option value=\"{$stars}\" {$sel}>" . htmlspecialchars($star_text) . "</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="form-group" style="margin-top:12px;">
                <label style="font-weight:700; font-size:13px; margin-bottom:6px; display:block;">Your Comment / Feedback *</label>
                <textarea name="comment" rows="4" required placeholder="Share your experience regarding freshness, packaging, and delivery..." style="width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; font-size:13px;"><?php echo e($existing_user_review['comment'] ?? ''); ?></textarea>
            </div>

            <div class="form-actions" style="display:flex; justify-content:flex-end; gap:10px; margin-top:16px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('productFeedbackModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><?php echo $existing_user_review ? 'Update Review' : 'Submit Review'; ?></button>
            </div>
        </form>
    </div>
</div>
<?php } ?>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>