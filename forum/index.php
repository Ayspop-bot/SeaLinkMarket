<?php
/**
 * SeaLink Web Application
 * File: /forum/index.php
 * Purpose: Community Discussion Forum for SeaLink Farmers and Buyers.
 * Connected To:
 * - /forum/post.php
 * - /forum/actions/post_create.php
 * Uses: forum_post_tbl, forum_comment_tbl
 */

require_once __DIR__ . '/../includes/auth_check.php';
$role = $_SESSION['user_role'] ?? '';
if (!in_array($role, ['farmer', 'buyer', 'Content Admin', 'User Admin'], true)) {
    redirect_path('/index.php');
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$active_tab = 'forum';
$page_title = "Community Forum - SeaLink";
require_once __DIR__ . '/../includes/layout_top.php';

$search = trim((string)($_GET['search'] ?? ''));
$category = trim((string)($_GET['category'] ?? 'All'));

$categories = [
    'All' => 'All Discussions',
    'General Discussion' => 'General',
    'Aquaculture & Techniques' => 'Farming Techniques',
    'Market & Pricing' => 'Market & Pricing',
    'Equipment & Supplies' => 'Supplies & Equipment',
    'Ask the Community' => 'Q&A'
];

$where = "1=1";
$params = [];
$types = "";

if ($category !== 'All' && $category !== '') {
    $where .= " AND p.category = ?";
    $params[] = $category;
    $types .= "s";
}

if ($search !== '') {
    $where .= " AND (p.title LIKE CONCAT('%', ?, '%') OR p.content LIKE CONCAT('%', ?, '%') OR p.author_name LIKE CONCAT('%', ?, '%'))";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $types .= "sss";
}

$sql = "
SELECT 
    p.post_id, p.user_id, p.user_role, p.author_name, p.title, p.category, p.content, p.created_at,
    COUNT(c.comment_id) AS comment_count
FROM forum_post_tbl p
LEFT JOIN forum_comment_tbl c ON c.post_id = p.post_id
WHERE {$where}
GROUP BY p.post_id
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

$posts = [];
while ($row = mysqli_fetch_assoc($res)) {
    $posts[] = $row;
}
mysqli_stmt_close($stmt);
mysqli_close($conn);
?>

<main class="dashboard-content <?php echo ($role === 'farmer') ? 'farmer-dashboard' : (($role === 'buyer') ? 'buyer-dashboard' : ''); ?>">
    <!-- Header -->
    <div class="section-card" style="margin-bottom:16px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div>
                <h1 style="margin:0; font-size:24px;">SeaLink Community Forum</h1>
                <div class="small-muted" style="margin-top:4px;">Ask questions, share coastal farming experiences, and connect with Santa Fe producers and buyers</div>
            </div>

            <button type="button" class="btn btn-primary" onclick="openModal('createPostModal')">
                Start New Discussion
            </button>
        </div>

        <!-- Search Bar -->
        <form method="GET" action="" style="margin-top:14px; display:flex; gap:10px; align-items:center;">
            <input type="hidden" name="category" value="<?php echo e($category); ?>">
            <div class="search-input-wrapper">
                <button type="button" class="search-clear-x" onclick="clearSearchAndRefresh(this)" title="Clear and refresh search" aria-label="Clear search" <?php echo empty($search) ? 'style="display:none;"' : 'style="display:flex;"'; ?>>&times;</button>
                <input type="text" name="search" placeholder="Search discussion topics, questions, or authors..."
                       value="<?php echo e($search); ?>" oninput="checkSearchClear(this)"
                       style="padding:10px 14px; border:1px solid var(--border); border-radius:8px; font-size:14px; background:#fff;">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Search</button>
        </form>
    </div>

    <!-- Category Filter Pills -->
    <div class="pill-row" style="margin-bottom:16px;">
        <?php foreach ($categories as $cat_k => $cat_lbl) { 
            $is_act = ($category === $cat_k);
        ?>
            <a href="?category=<?php echo urlencode($cat_k); ?>&search=<?php echo urlencode($search); ?>"
               class="pill-btn <?php echo $is_act ? 'active' : ''; ?>">
                <?php echo e($cat_lbl); ?>
            </a>
        <?php } ?>
    </div>

    <!-- Discussion Threads List -->
    <div style="display:flex; flex-direction:column; gap:14px;">
        <?php if (empty($posts)) { ?>
            <div class="section-card" style="text-align:center; padding:40px 20px; color:var(--muted);">
                No discussions found in this category. Click <strong>Start New Discussion</strong> to post the first topic!
            </div>
        <?php } ?>

        <?php foreach ($posts as $p) { 
            $role_badge_class = ($p['user_role'] === 'farmer') ? 'active' : '';
        ?>
            <div class="section-card" style="margin-top:0; transition:box-shadow .15s;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px;">
                    <div>
                        <!-- Category Badge + Author info -->
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px; flex-wrap:wrap;">
                            <span class="badge" style="background:rgba(100, 149, 237, 0.15); color:var(--primary); font-weight:700;">
                                <?php echo e($p['category']); ?>
                            </span>
                            <span class="small-muted">
                                Posted by <strong><?php echo e($p['author_name']); ?></strong> (<?php echo ucfirst(e($p['user_role'])); ?>) &bull; <?php echo date('M d, Y h:i A', strtotime($p['created_at'])); ?>
                            </span>
                        </div>

                        <!-- Thread Title -->
                        <h2 style="font-size:18px; font-weight:800; margin:0 0 6px 0;">
                            <a href="<?php echo BASE_URL; ?>/forum/post.php?post_id=<?php echo (int)$p['post_id']; ?>" style="text-decoration:none; color:var(--text);">
                                <?php echo e($p['title']); ?>
                            </a>
                        </h2>

                        <!-- Excerpt -->
                        <p class="small-muted" style="line-height:1.55; margin:0; font-size:14px;">
                            <?php echo e(mb_substr($p['content'], 0, 160)); ?><?php echo (mb_strlen($p['content']) > 160) ? '...' : ''; ?>
                        </p>
                    </div>

                    <!-- Right Column: Comments Count + View Button -->
                    <div style="display:flex; align-items:center; gap:12px; align-self:center;">
                        <span style="font-weight:700; font-size:13px; color:var(--muted); background:#f2f5f5; padding:6px 12px; border-radius:8px;">
                            <?php echo (int)$p['comment_count']; ?> Replies
                        </span>
                        <a href="<?php echo BASE_URL; ?>/forum/post.php?post_id=<?php echo (int)$p['post_id']; ?>" class="btn btn-sm btn-secondary">
                            View Discussion
                        </a>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>
</main>

<!-- CREATE POST MODAL -->
<div class="modal" id="createPostModal" aria-hidden="true">
    <div class="modal-content" style="max-width:580px;">
        <div class="modal-header">
            <h2>Start New Discussion</h2>
        </div>

        <form method="POST" action="<?php echo BASE_URL; ?>/forum/actions/post_create.php">
            <div class="form-group">
                <label>Discussion Topic Title *</label>
                <input type="text" name="title" required placeholder="e.g. Best seaweed planting line depth during monsoon season?">
            </div>

            <div class="form-group">
                <label>Category *</label>
                <select name="category" required>
                    <option value="General Discussion">General Discussion</option>
                    <option value="Aquaculture & Techniques">Farming Techniques</option>
                    <option value="Market & Pricing">Market & Pricing</option>
                    <option value="Equipment & Supplies">Supplies & Equipment</option>
                    <option value="Ask the Community">Q&A</option>
                </select>
            </div>

            <div class="form-group">
                <label>Your Message / Question *</label>
                <textarea name="content" rows="6" required placeholder="Describe your topic, question, or tips clearly for community members..."></textarea>
            </div>

            <div class="form-actions" style="justify-content:flex-end; margin-top:16px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createPostModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Publish Discussion</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
