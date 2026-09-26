<?php
/**
 * SeaLink Web Application
 * File: /infohub/read.php
 * Purpose: Full Article Reader for Educational Info Hub articles.
 * Connected To:
 * - /infohub/index.php
 * Uses: info_hub_tbl
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$role = $_SESSION['user_role'] ?? '';
$hide_nav = true;

$article_id = (int)($_GET['article_id'] ?? 0);
if ($article_id <= 0) {
    redirect_path('/infohub/index.php');
}

// Fetch main article
$stmt = mysqli_prepare($conn, "SELECT info_hub_id AS article_id, info_hub_tbl.* FROM info_hub_tbl WHERE info_hub_id = ? AND status = 'Published' LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $article_id);
mysqli_stmt_execute($stmt);
$article = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$article) {
    mysqli_close($conn);
    redirect_path('/infohub/index.php');
}

// Fetch related articles
$rel_stmt = mysqli_prepare($conn, "
    SELECT info_hub_id AS article_id, info_hub_id, title, category, created_at 
    FROM info_hub_tbl 
    WHERE status = 'Published' AND info_hub_id <> ? 
    ORDER BY created_at DESC 
    LIMIT 4
");
mysqli_stmt_bind_param($rel_stmt, "i", $article_id);
mysqli_stmt_execute($rel_stmt);
$rel_res = mysqli_stmt_get_result($rel_stmt);
$related = [];
while ($r = mysqli_fetch_assoc($rel_res)) {
    $related[] = $r;
}
mysqli_stmt_close($rel_stmt);
mysqli_close($conn);

$page_title = e($article['title']) . " - SeaLink Information Hub";
require_once __DIR__ . '/../includes/layout_top.php';
?>

<main class="dashboard-content <?php echo ($role === 'farmer') ? 'farmer-dashboard' : (($role === 'buyer') ? 'buyer-dashboard' : ''); ?>">
    <!-- Back to Hub Navigation -->
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
        <a class="back-arrow" href="<?php echo BASE_URL; ?>/infohub/index.php" aria-label="Back to Information Hub">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path fill="currentColor" d="M15.5 19 8.5 12l7-7 1.5 1.5L11.5 12l5.5 5.5z"/>
            </svg>
        </a>
        <div class="small-muted">Back to Information Hub</div>
    </div>

    <div style="display:grid; grid-template-columns: 2.2fr 1fr; gap:20px;">
        <!-- Left: Main Article View -->
        <article class="section-card" style="margin-top:0; padding:28px 32px;">
            <!-- Category Badge & Publish Date -->
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
                <span class="badge" style="background:rgba(100, 149, 237, 0.15); color:var(--primary); font-weight:800; font-size:13px; padding:6px 14px;">
                    <?php echo e($article['category']); ?>
                </span>
                <span class="small-muted">
                    Published on <?php echo date('F j, Y', strtotime($article['created_at'])); ?>
                </span>
            </div>

            <!-- Title -->
            <h1 style="font-size:26px; font-weight:900; line-height:1.35; margin:0 0 20px 0; color:var(--text);">
                <?php echo e($article['title']); ?>
            </h1>

            <!-- Image Banner if exists -->
            <?php if (!empty($article['image_url'])) { ?>
                <div style="width:100%; max-height:360px; border-radius:12px; overflow:hidden; margin-bottom:24px; border:1px solid var(--border);">
                    <img src="<?php echo BASE_URL . '/' . e($article['image_url']); ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                </div>
            <?php } ?>

            <!-- Formatted Article Content -->
            <div style="font-size:15px; line-height:1.8; color:var(--text); white-space:pre-line;">
                <?php echo e($article['content']); ?>
            </div>

            <!-- Footer Meta -->
            <div style="margin-top:32px; padding-top:18px; border-top:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <span class="small-muted">SeaLink Knowledge Base &bull; Municipal Agriculture Resource</span>
                <a href="<?php echo BASE_URL; ?>/infohub/index.php" class="btn btn-secondary btn-sm">Browse More Articles</a>
            </div>
        </article>

        <!-- Right: Related Articles Sidebar -->
        <aside style="display:flex; flex-direction:column; gap:14px;">
            <div class="section-card" style="margin-top:0;">
                <h3 style="font-size:16px; font-weight:800; margin:0 0 12px 0;">Related Guides</h3>

                <?php if (empty($related)) { ?>
                    <div class="small-muted">No additional guides available.</div>
                <?php } ?>

                <div style="display:flex; flex-direction:column; gap:12px;">
                    <?php foreach ($related as $rel) { ?>
                        <a href="<?php echo BASE_URL; ?>/infohub/read.php?article_id=<?php echo (int)$rel['article_id']; ?>"
                           style="text-decoration:none; padding:10px 12px; background:#f9fbfb; border:1px solid var(--border); border-radius:10px; display:block; transition:background .15s;">
                            <div style="font-size:11px; font-weight:700; color:var(--primary);"><?php echo e($rel['category']); ?></div>
                            <div style="font-weight:700; font-size:13px; color:var(--text); margin-top:2px; line-height:1.35;"><?php echo e($rel['title']); ?></div>
                            <div class="small-muted" style="font-size:11px; margin-top:4px;"><?php echo date('M d, Y', strtotime($rel['created_at'])); ?></div>
                        </a>
                    <?php } ?>
                </div>
            </div>

            <!-- Info Box -->
            <div class="section-card" style="margin-top:0; background:#f0f8ff; border:1px solid rgba(100,149,237,0.35);">
                <div style="font-weight:800; font-size:14px; color:var(--primary); margin-bottom:4px;">Have questions?</div>
                <div class="small-muted" style="font-size:12px; line-height:1.5;">
                    Visit our Community Forum to discuss techniques with experienced fish farmers or submit a support inquiry.
                </div>
                <div style="margin-top:10px;">
                    <?php if (is_logged_in()) { ?>
                        <a href="<?php echo BASE_URL; ?>/forum/index.php" class="btn btn-sm btn-primary btn-block" style="text-align:center;">
                            Open Forum
                        </a>
                    <?php } else { ?>
                        <button type="button" onclick="openGuestPromptModal('Forum')" class="btn btn-sm btn-primary btn-block" style="text-align:center; cursor:pointer;">
                            Open Forum
                        </button>
                    <?php } ?>
                </div>
            </div>
        </aside>
    </div>
</main>

<?php
if (!is_logged_in()) {
    require_once __DIR__ . '/../includes/modal_guest.php';
}
require_once __DIR__ . '/../includes/layout_bottom.php';
?>
