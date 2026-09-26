<?php
/**
 * SeaLink Web Application
 * File: /forum/post.php
 * Purpose: Single Forum Discussion Thread View with Comment Replies.
 * Connected To:
 * - /forum/index.php
 * - /forum/actions/comment_create.php
 * Uses: forum_post_tbl, forum_comment_tbl
 */

require_once __DIR__ . '/../includes/auth_check.php';
$role = $_SESSION['user_role'] ?? '';
if (!in_array($role, ['farmer', 'buyer', 'Content Admin', 'User Admin'], true)) {
    redirect_path('/index.php');
}

$hide_nav = true;
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$post_id = (int)($_GET['post_id'] ?? 0);
if ($post_id <= 0) {
    redirect_path('/forum/index.php');
}

// Fetch main post
$stmt = mysqli_prepare($conn, "SELECT * FROM forum_post_tbl WHERE post_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $post_id);
mysqli_stmt_execute($stmt);
$post = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$post) {
    mysqli_close($conn);
    redirect_path('/forum/index.php');
}

// Fetch comments
$c_stmt = mysqli_prepare($conn, "SELECT * FROM forum_comment_tbl WHERE post_id = ? ORDER BY created_at ASC");
mysqli_stmt_bind_param($c_stmt, "i", $post_id);
mysqli_stmt_execute($c_stmt);
$c_res = mysqli_stmt_get_result($c_stmt);
$comments = [];
while ($row = mysqli_fetch_assoc($c_res)) {
    $comments[] = $row;
}
mysqli_stmt_close($c_stmt);
mysqli_close($conn);

$return = trim((string)($_GET['return'] ?? ''));
if (!empty($return)) {
    $back_url = (strpos($return, 'http') === 0) ? $return : (BASE_URL . '/' . ltrim($return, '/'));
} else {
    $back_url = BASE_URL . '/infohub/index.php?tab=forum';
}

$page_title = e($post['title']) . " - SeaLink Community Forum";
require_once __DIR__ . '/../includes/layout_top.php';
?>

<main class="dashboard-content <?php echo ($role === 'farmer') ? 'farmer-dashboard' : (($role === 'buyer') ? 'buyer-dashboard' : ''); ?>">
    <!-- Back Navigation -->
    <div style="display:flex; align-items:center; gap:8px; margin-bottom:16px;">
        <a class="back-arrow" href="<?php echo e($back_url); ?>" aria-label="Back to Discussions" style="text-decoration:none;">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path fill="currentColor" d="M15.5 19 8.5 12l7-7 1.5 1.5L11.5 12l5.5 5.5z"/>
            </svg>
        </a>
        <a href="<?php echo e($back_url); ?>" style="text-decoration:none; font-weight:700; color:var(--muted); font-size:14px;"
           onmouseover="this.style.color='var(--primary)';"
           onmouseout="this.style.color='var(--muted)';">
            Back to Discussions
        </a>
    </div>

    <!-- Main Thread Card -->
    <article class="section-card" style="margin-top:0; padding:24px 28px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:10px;">
            <span class="badge" style="background:rgba(100, 149, 237, 0.15); color:var(--primary); font-weight:800; font-size:13px;">
                <?php echo e($post['category']); ?>
            </span>
            <span class="small-muted">
                Posted on <?php echo date('F j, Y h:i A', strtotime($post['created_at'])); ?>
            </span>
        </div>

        <h1 style="font-size:22px; font-weight:900; margin:0 0 10px 0; color:var(--text);">
            <?php echo e($post['title']); ?>
        </h1>

        <div class="small-muted" style="margin-bottom:18px;">
            Started by <strong><?php echo e($post['author_name']); ?></strong> (<?php echo ucfirst(e($post['user_role'])); ?>)
        </div>

        <div style="font-size:15px; line-height:1.75; color:var(--text); white-space:pre-line; border-top:1px solid var(--border); padding-top:16px;">
            <?php echo e($post['content']); ?>
        </div>
    </article>

    <!-- Comments / Replies Section -->
    <div class="section-card" style="margin-top:20px;">
        <h2 style="font-size:18px; font-weight:800; margin:0 0 16px 0;">
            Community Replies (<?php echo count($comments); ?>)
        </h2>

        <?php if (empty($comments)) { ?>
            <div class="small-muted" style="padding:16px 0; text-align:center;">
                No replies yet. Be the first to join the conversation below!
            </div>
        <?php } else { ?>
            <div style="display:flex; flex-direction:column; gap:14px;">
                <?php foreach ($comments as $c) { ?>
                    <div style="background:#f9fbfb; border:1px solid var(--border); border-radius:10px; padding:14px 16px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                            <div>
                                <strong><?php echo e($c['author_name']); ?></strong>
                                <span class="badge" style="font-size:11px; padding:2px 8px; margin-left:6px;"><?php echo ucfirst(e($c['user_role'])); ?></span>
                            </div>
                            <span class="small-muted" style="font-size:12px;">
                                <?php echo date('M d, Y h:i A', strtotime($c['created_at'])); ?>
                            </span>
                        </div>
                        <div style="font-size:14px; line-height:1.55; color:var(--text); white-space:pre-line;">
                            <?php echo e($c['content']); ?>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>

        <!-- Reply Form -->
        <div style="margin-top:24px; padding-top:18px; border-top:1px solid var(--border);">
            <h3 style="font-size:15px; font-weight:800; margin:0 0 10px 0;">Write a Reply</h3>
            <form method="POST" action="<?php echo BASE_URL; ?>/forum/actions/comment_create.php">
                <input type="hidden" name="post_id" value="<?php echo (int)$post['post_id']; ?>">
                <input type="hidden" name="return" value="<?php echo e(!empty($return) ? $return : '/infohub/index.php?tab=forum'); ?>">
                <div class="form-group">
                    <textarea name="content" rows="4" required placeholder="Share your experience, advice, or reply to this topic..."></textarea>
                </div>
                <div style="display:flex; justify-content:flex-end;">
                    <button type="submit" class="btn btn-primary">Post Reply</button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
