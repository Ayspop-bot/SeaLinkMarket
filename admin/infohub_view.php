<?php
/**
 * SeaLink Web Application
 * File: /admin/infohub_view.php
 * Note: Consolidated to unified /infohub/index.php
 */
require_once __DIR__ . '/../config/app.php';
$qs = !empty($_SERVER['QUERY_STRING']) ? ('?' . $_SERVER['QUERY_STRING']) : '';
header('Location: ' . BASE_URL . '/infohub/index.php' . $qs);
exit;

$category = trim((string)($_GET['category'] ?? 'All'));
$search   = trim((string)($_GET['search'] ?? ''));

$categories_list = [
    'All'      => 'All',
    'Article'  => 'Article',
    'Research' => 'Research',
    'Tutorial' => 'Tutorial',
    'Recipes'  => 'Recipes',
    'Forum'    => 'Forum'
];

$where = "status = 'Published'";
$params = [];
$types = "";

if ($category !== 'All' && $category !== 'Forum' && $category !== '') {
    $where .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}

if ($search !== '') {
    $where .= " AND (title LIKE CONCAT('%', ?, '%') OR content LIKE CONCAT('%', ?, '%'))";
    $params[] = $search;
    $params[] = $search;
    $types .= "ss";
}

$sql = "SELECT info_hub_id AS article_id, info_hub_tbl.* FROM info_hub_tbl WHERE {$where} ORDER BY created_at DESC";
$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    $bind = [$stmt, $types];
    foreach ($params as $k => $v) $bind[] = &$params[$k];
    call_user_func_array('mysqli_stmt_bind_param', $bind);
}
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$articles = [];
while ($row = mysqli_fetch_assoc($res)) {
    $articles[] = $row;
}
mysqli_stmt_close($stmt);

$forum_posts = [];
if ($category === 'Forum') {
    $fp_sql = "
        SELECT p.post_id, p.user_id, p.user_role, p.author_name, p.title, p.category, p.content, p.created_at,
               COUNT(c.comment_id) AS comment_count 
        FROM forum_post_tbl p 
        LEFT JOIN forum_comment_tbl c ON c.post_id = p.post_id 
        GROUP BY p.post_id 
        ORDER BY p.created_at DESC
    ";
    $fp_res = mysqli_query($conn, $fp_sql);
    if ($fp_res) while ($r = mysqli_fetch_assoc($fp_res)) $forum_posts[] = $r;
}

mysqli_close($conn);
?>

<main class="dashboard-content">
    <!-- Header Card -->
    <div class="section-card" style="margin-bottom:16px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div>
                <h1 style="margin:0; font-size:24px;">SeaLink Information Hub</h1>
                <div class="small-muted" style="margin-top:4px;">Educational guides, modern aquaculture techniques, and quality standards for Romblon fisherfolk</div>
            </div>
            <div class="small-muted"><?php echo count($articles); ?> publication(s)</div>
        </div>
    </div>

    <!-- Filter Subtabs -->
    <div class="subtabs" style="margin-top:14px; margin-bottom:14px;">
        <?php foreach ($categories_list as $cat_key => $cat_label) { 
            $is_active = ($category === $cat_key);
        ?>
            <a href="?category=<?php echo urlencode($cat_key); ?>&search=<?php echo urlencode($search); ?>"
               class="subtab <?php echo $is_active ? 'active' : ''; ?>">
                <?php echo e($cat_label); ?>
            </a>
        <?php } ?>
    </div>

    <!-- Search Container -->
    <div class="section-card" style="margin-bottom:16px;">
        <!-- Search Bar -->
        <form method="GET" action="" style="display:flex; gap:10px; align-items:center;">
            <input type="hidden" name="category" value="<?php echo e($category); ?>">
            <input type="text" name="search" placeholder="Search guides, research, tutorials, recipes, or topics..."
                   value="<?php echo e($search); ?>"
                   style="flex:1; padding:10px 14px; border:1px solid var(--border); border-radius:8px; font-size:14px; background:#fff;">
            <button type="submit" class="btn btn-primary btn-sm">Search</button>
            <a href="<?php echo BASE_URL; ?>/admin/infohub_view.php" class="btn btn-secondary btn-sm">Reset</a>
        </form>
    </div>

    <!-- Forum or Articles Grid -->
    <?php if ($category === 'Forum') { ?>
        <div class="section-card">
            <h2 style="font-size:18px; font-weight:800; margin:0 0 14px 0;">Community Discussions</h2>
            <?php if (empty($forum_posts)) { ?>
                <div class="small-muted" style="text-align:center; padding:30px;">No community forum discussions found.</div>
            <?php } ?>
            <div style="display:flex; flex-direction:column; gap:12px;">
                <?php foreach ($forum_posts as $fp) { ?>
                    <div class="panel" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                        <div>
                            <span class="badge" style="font-size:11px;"><?php echo e($fp['category']); ?></span>
                            <h3 style="font-size:16px; font-weight:800; margin:4px 0 2px 0;"><?php echo e($fp['title']); ?></h3>
                            <div class="small-muted" style="font-size:12px;">By <?php echo e($fp['author_name']); ?> &bull; <?php echo date('M d, Y', strtotime($fp['created_at'])); ?></div>
                        </div>
                        <a href="<?php echo BASE_URL; ?>/forum/post.php?post_id=<?php echo (int)$fp['post_id']; ?>" target="_blank" class="btn btn-sm btn-secondary">
                            View Thread
                        </a>
                    </div>
                <?php } ?>
            </div>
        </div>
    <?php } else { ?>
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:18px;">
            <?php if (empty($articles)) { ?>
                <div class="section-card" style="grid-column:1 / -1; text-align:center; padding:40px 20px; color:var(--muted);">
                    No publications found in this category.
                </div>
            <?php } ?>

            <?php foreach ($articles as $a) { ?>
                <div class="section-card" style="margin-top:0; padding:0; overflow:hidden; display:flex; flex-direction:column; justify-content:space-between; border-radius:14px;">
                    <div>
                        <div style="width:100%; height:160px; background:#e8eded; display:flex; align-items:center; justify-content:center; overflow:hidden; position:relative;">
                            <?php if (!empty($a['image_url'])) { ?>
                                <img src="<?php echo BASE_URL . '/' . e($a['image_url']); ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                            <?php } else { ?>
                                <svg width="100%" height="100%" viewBox="0 0 200 120" preserveAspectRatio="none" style="position:absolute; inset:0; opacity:0.25;">
                                    <line x1="0" y1="0" x2="200" y2="120" stroke="#000" stroke-width="1.5" />
                                    <line x1="200" y1="0" x2="0" y2="120" stroke="#000" stroke-width="1.5" />
                                </svg>
                                <span style="position:relative; font-size:13px; font-weight:700; color:var(--muted);"><?php echo e($a['category']); ?></span>
                            <?php } ?>
                        </div>

                        <div style="padding:16px;">
                            <h3 style="font-size:16px; font-weight:800; line-height:1.35; margin:0 0 8px 0; color:var(--text);">
                                <?php echo e($a['title']); ?>
                            </h3>

                            <div class="small-muted" style="font-size:13px; margin-bottom:4px;">
                                Category: <strong><?php echo e($a['category']); ?></strong>
                            </div>

                            <div class="small-muted" style="font-size:12px; margin-top:6px;">
                                <?php echo date('M d, Y', strtotime($a['created_at'])); ?>
                            </div>
                        </div>
                    </div>

                    <div style="padding:12px 16px; border-top:1px solid var(--border); background:#fbfbfb;">
                        <a href="<?php echo BASE_URL; ?>/infohub/read.php?article_id=<?php echo (int)$a['article_id']; ?>" target="_blank"
                           class="btn btn-sm btn-primary btn-block" style="text-align:center;">
                            Read Guide
                        </a>
                    </div>
                </div>
            <?php } ?>
        </div>
    <?php } ?>
</main>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
