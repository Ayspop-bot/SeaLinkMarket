<?php
/**
 * SeaLink Web Application
 * File: /admin/infohub.php
 * Purpose: Content Admin Information Hub Management & Live View matching Image wireframe.
 * Connected To:
 * - /admin/actions/infohub_add.php
 * - /admin/actions/infohub_edit.php
 * - /admin/actions/infohub_delete.php
 * - /admin/actions/infohub_toggle.php
 * Uses: info_hub_tbl, admin_tbl
 */

require_once __DIR__ . '/../includes/auth_check.php';
check_access(['Content Admin', 'User Admin']);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$active_tab = 'infohub';
$page_title = "Information Hub - SeaLink Admin";
require_once __DIR__ . '/../includes/layout_top.php';

$is_content_admin = (($_SESSION['user_role'] ?? '') === 'Content Admin');
$admin_id = (int)($_SESSION['user_id'] ?? 0);

$current_tab = trim((string)($_GET['tab'] ?? 'knowledge'));
if ($current_tab !== 'forum') {
    $current_tab = 'knowledge';
}

// 1. Publications / Knowledge Data
$kb_categories = [
    'All'      => 'All Categories',
    'Article'  => 'Article',
    'Research' => 'Research',
    'Tutorial' => 'Tutorial',
    'Recipes'  => 'Recipes'
];
$kb_category = trim((string)($_GET['category'] ?? 'All'));
if (!array_key_exists($kb_category, $kb_categories)) {
    $kb_category = 'All';
}
$kb_search = trim((string)($_GET['search'] ?? ''));

$articles = [];
if ($current_tab === 'knowledge') {
    $where = "1=1";
    $params = [];
    $types = "";

    if ($kb_category !== 'All') {
        $where .= " AND category = ?";
        $params[] = $kb_category;
        $types .= "s";
    }

    if ($kb_search !== '') {
        $where .= " AND (title LIKE CONCAT('%', ?, '%') OR content LIKE CONCAT('%', ?, '%'))";
        $params[] = $kb_search;
        $params[] = $kb_search;
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
    while ($row = mysqli_fetch_assoc($res)) {
        $articles[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// 2. Forum Data
$forum_categories = [
    'All'                      => 'All Discussions',
    'General Discussion'       => 'General',
    'Aquaculture & Techniques' => 'Farming Techniques',
    'Market & Pricing'         => 'Market & Pricing',
    'Equipment & Supplies'     => 'Supplies & Equipment',
    'Ask the Community'        => 'Q&A'
];
$forum_cat = trim((string)($_GET['forum_cat'] ?? 'All'));
if (!array_key_exists($forum_cat, $forum_categories)) {
    $forum_cat = 'All';
}
$forum_search = trim((string)($_GET['forum_search'] ?? ''));

$forum_posts = [];
if ($current_tab === 'forum') {
    $f_where = "1=1";
    $f_params = [];
    $f_types = "";

    if ($forum_cat !== 'All') {
        $f_where .= " AND p.category = ?";
        $f_params[] = $forum_cat;
        $f_types .= "s";
    }

    if ($forum_search !== '') {
        $f_where .= " AND (p.title LIKE CONCAT('%', ?, '%') OR p.content LIKE CONCAT('%', ?, '%') OR p.author_name LIKE CONCAT('%', ?, '%'))";
        $f_params[] = $forum_search;
        $f_params[] = $forum_search;
        $f_params[] = $forum_search;
        $f_types .= "sss";
    }

    $fp_sql = "
        SELECT p.post_id, p.user_id, p.user_role, p.author_name, p.title, p.category, p.content, p.created_at,
               COUNT(c.comment_id) AS comment_count 
        FROM forum_post_tbl p 
        LEFT JOIN forum_comment_tbl c ON c.post_id = p.post_id 
        WHERE {$f_where}
        GROUP BY p.post_id 
        ORDER BY p.created_at DESC
    ";
    $f_stmt = mysqli_prepare($conn, $fp_sql);
    if (!empty($f_params)) {
        $f_bind = [$f_stmt, $f_types];
        foreach ($f_params as $k => $v) $f_bind[] = &$f_params[$k];
        call_user_func_array('mysqli_stmt_bind_param', $f_bind);
    }
    mysqli_stmt_execute($f_stmt);
    $fp_res = mysqli_stmt_get_result($f_stmt);
    while ($r = mysqli_fetch_assoc($fp_res)) {
        $forum_posts[] = $r;
    }
    mysqli_stmt_close($f_stmt);
}

mysqli_close($conn);
?>

<style>
.deals-fixed-grid {
    display: grid !important;
    grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
    gap: 16px !important;
}
@media (max-width: 1024px) {
    .deals-fixed-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
    }
}
@media (max-width: 768px) {
    .deals-fixed-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }
}
@media (max-width: 480px) {
    .deals-fixed-grid {
        grid-template-columns: repeat(1, minmax(0, 1fr)) !important;
    }
}
</style>

<main class="dashboard-content">
    <!-- Header Section Card (copied design from infohub/index.php) -->
    <div class="section-card" style="margin-bottom:16px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div>
                <h1 style="margin:0; font-size:24px;">SeaLink Information Hub</h1>
                <div class="small-muted" style="margin-top:4px;">Educational guides, research, aquaculture tutorials, recipes, and community knowledge</div>
            </div>
            <div style="display:flex; align-items:center; gap:12px;">
                <div class="small-muted">
                    <?php if ($current_tab === 'knowledge') { ?>
                        <?php echo count($articles); ?> publication(s)
                    <?php } else { ?>
                        <?php echo count($forum_posts); ?> discussion(s)
                    <?php } ?>
                </div>
                <?php if ($is_content_admin && $current_tab === 'knowledge') { ?>
                    <button type="button" class="btn btn-primary" onclick="openModal('addArticleModal')">
                        + Add Article
                    </button>
                <?php } ?>
            </div>
        </div>
    </div>

    <!-- Master Toggle Tabs: [Publications] and [Community Forum] (copied design from infohub/index.php) -->
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:18px; border-bottom:2px solid var(--border); padding-bottom:12px;">
        <a href="?tab=knowledge" class="btn <?php echo ($current_tab === 'knowledge') ? 'btn-primary' : 'btn-secondary'; ?>" 
           style="border-radius:24px; padding:9px 24px; font-weight:800; font-size:14px; text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
            <span>Publications</span> 
        </a>
        <a href="?tab=forum" class="btn <?php echo ($current_tab === 'forum') ? 'btn-primary' : 'btn-secondary'; ?>" 
           style="border-radius:24px; padding:9px 24px; font-weight:800; font-size:14px; text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
            <span>Community Forum</span> 
        </a>
    </div>

    <?php if ($current_tab === 'knowledge') { ?>
        <!-- Category Dropdown aligned with Search Bar (base from market.php / infohub) -->
        <div class="market-toolbar" style="margin-bottom:16px;">
            <form class="market-filters" method="GET" action="" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; width:100%;">
                <input type="hidden" name="tab" value="knowledge">
                <select name="category" onchange="this.form.submit()">
                    <?php foreach ($kb_categories as $cat_key => $cat_label) { ?>
                        <option value="<?php echo e($cat_key); ?>" <?php echo ($kb_category === $cat_key) ? 'selected' : ''; ?>>
                            <?php echo e($cat_label); ?>
                        </option>
                    <?php } ?>
                </select>

                <div class="search-input-wrapper">
                    <button type="button" class="search-clear-x" onclick="clearSearchAndRefresh(this)" title="Clear and refresh search" aria-label="Clear search" <?php echo empty($kb_search) ? 'style="display:none;"' : 'style="display:flex;"'; ?>>&times;</button>
                    <input type="text" name="search" placeholder="Search guides, research, tutorials, recipes, or topics..."
                           value="<?php echo e($kb_search); ?>" oninput="checkSearchClear(this)">
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Search</button>
            </form>
        </div>

        <!-- Educational Articles Card Grid (Fixed 4 columns per row like market.php) -->
        <div class="deals-fixed-grid">
            <?php if (empty($articles)) { ?>
                <div class="section-card" style="grid-column:1 / -1; text-align:center; padding:40px 20px; color:var(--muted);">
                    No publications found matching your criteria. <?php if ($is_content_admin) { ?>Click <strong>+ Add Article</strong> above to create one.<?php } ?>
                </div>
            <?php } ?>

            <?php foreach ($articles as $a) { 
                $is_pub = ($a['status'] === 'Published');
            ?>
                <!-- Card matching Image Wireframe: Image on top, Title, Author/Category, Date, and 3 buttons: Edit, Unpublish/Publish, Delete -->
                <div class="section-card" style="margin-top:0; padding:0; overflow:hidden; display:flex; flex-direction:column; justify-content:space-between; border-radius:14px;">
                    <div>
                        <!-- Cover Image Box with placeholder -->
                        <div style="width:100%; height:160px; background:#e8eded; display:flex; align-items:center; justify-content:center; overflow:hidden; position:relative;">
                            <?php if (!empty($a['image_url'])) { ?>
                                <img src="<?php echo BASE_URL . '/' . e($a['image_url']); ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                            <?php } else { ?>
                                <!-- Wireframe X placeholder matching attached image -->
                                <svg width="100%" height="100%" viewBox="0 0 200 120" preserveAspectRatio="none" style="position:absolute; inset:0; opacity:0.25;">
                                    <line x1="0" y1="0" x2="200" y2="120" stroke="#000" stroke-width="1.5" />
                                    <line x1="200" y1="0" x2="0" y2="120" stroke="#000" stroke-width="1.5" />
                                </svg>
                                <span style="position:relative; font-size:13px; font-weight:700; color:var(--muted);"><?php echo e($a['category']); ?></span>
                            <?php } ?>
                        </div>

                        <!-- Card Body -->
                        <div style="padding:16px;">
                            <h3 style="font-size:16px; font-weight:800; line-height:1.35; margin:0 0 10px 0; color:var(--text);">
                                <a href="<?php echo BASE_URL; ?>/infohub/read.php?article_id=<?php echo (int)$a['article_id']; ?>" target="_blank" style="text-decoration:none; color:inherit;">
                                    <?php echo e($a['title']); ?>
                                </a>
                            </h3>

                            <div class="small-muted" style="font-size:13px; margin-bottom:4px;">
                                Category: <strong><?php echo e($a['category']); ?></strong>
                            </div>

                            <div style="display:flex; justify-content:space-between; font-size:12px; color:var(--muted); margin-top:8px;">
                                <span><?php echo date('M d, Y', strtotime($a['created_at'])); ?></span>
                                <span class="badge <?php echo $is_pub ? 'active' : 'inactive'; ?>" style="font-size:11px; padding:2px 8px;">
                                    <?php echo e($a['status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Action Buttons: Edit, Unpublish/Publish, Delete -->
                    <div style="padding:12px 16px; border-top:1px solid var(--border); background:#fbfbfb; display:flex; gap:8px;">
                        <?php if ($is_content_admin) { ?>
                            <button type="button" class="btn btn-sm btn-secondary" style="flex:1; text-align:center;"
                                    data-id="<?php echo (int)$a['article_id']; ?>"
                                    data-title="<?php echo e($a['title']); ?>"
                                    data-category="<?php echo e($a['category']); ?>"
                                    data-status="<?php echo e($a['status']); ?>"
                                    data-content="<?php echo htmlspecialchars($a['content'], ENT_QUOTES); ?>"
                                    onclick="populateEditArticle(this)">
                                Edit
                            </button>

                            <form method="POST" action="<?php echo BASE_URL; ?>/admin/actions/infohub_toggle.php" style="flex:1; margin:0;">
                                <input type="hidden" name="article_id" value="<?php echo (int)$a['article_id']; ?>">
                                <button type="submit" class="btn btn-sm btn-secondary" style="width:100%;">
                                    <?php echo $is_pub ? 'Unpublish' : 'Publish'; ?>
                                </button>
                            </form>

                            <form method="POST" action="<?php echo BASE_URL; ?>/admin/actions/infohub_delete.php" style="flex:1; margin:0;"
                                  onsubmit="return confirm('Delete this article?');">
                                <input type="hidden" name="article_id" value="<?php echo (int)$a['article_id']; ?>">
                                <button type="submit" class="btn btn-sm" style="width:100%; background:#b42318; color:#fff; border:none; border-radius:10px; font-weight:700;">
                                    Delete
                                </button>
                            </form>
                        <?php } else { ?>
                            <a href="<?php echo BASE_URL; ?>/infohub/read.php?article_id=<?php echo (int)$a['article_id']; ?>" target="_blank"
                               class="btn btn-sm btn-primary btn-block" style="text-align:center;">
                                Read Guide
                            </a>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>

    <?php } else { ?>
        <!-- ============================================== -->
        <!-- TAB 2: COMMUNITY FORUM FEED                    -->
        <!-- ============================================== -->
        <div class="market-toolbar" style="margin-bottom:16px;">
            <form class="market-filters" method="GET" action="" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; width:100%;">
                <input type="hidden" name="tab" value="forum">
                <select name="forum_cat" onchange="this.form.submit()">
                    <?php foreach ($forum_categories as $cat_k => $cat_lbl) { ?>
                        <option value="<?php echo e($cat_k); ?>" <?php echo ($forum_cat === $cat_k) ? 'selected' : ''; ?>>
                            <?php echo e($cat_lbl); ?>
                        </option>
                    <?php } ?>
                </select>

                <div class="search-input-wrapper">
                    <button type="button" class="search-clear-x" onclick="clearSearchAndRefresh(this)" title="Clear and refresh search" aria-label="Clear search" <?php echo empty($forum_search) ? 'style="display:none;"' : 'style="display:flex;"'; ?>>&times;</button>
                    <input type="text" name="forum_search" placeholder="Search discussion topics, questions, or authors..."
                           value="<?php echo e($forum_search); ?>" oninput="checkSearchClear(this)">
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Search</button>
            </form>
        </div>

        <div class="section-card" style="margin-top:0;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                <h2 style="font-size:18px; font-weight:800; margin:0;">Community Discussions (Forum)</h2>
            </div>

            <?php if (empty($forum_posts)) { ?>
                <div class="small-muted" style="text-align:center; padding:30px;">No community forum discussions found.</div>
            <?php } ?>

            <div style="display:flex; flex-direction:column; gap:12px;">
                <?php foreach ($forum_posts as $fp) { ?>
                    <div class="panel" style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
                        <div>
                            <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                                <span class="badge" style="font-size:11px; background:rgba(100,149,237,0.15); color:var(--primary);">
                                    <?php echo e($fp['category']); ?>
                                </span>
                                <span class="small-muted">
                                    By <strong><?php echo e($fp['author_name']); ?></strong> (<?php echo ucfirst(e($fp['user_role'])); ?>) &bull; <?php echo date('M d, Y', strtotime($fp['created_at'])); ?>
                                </span>
                            </div>
                            <h3 style="font-size:16px; font-weight:800; margin:0 0 6px 0;">
                                <a href="<?php echo BASE_URL; ?>/forum/post.php?post_id=<?php echo (int)$fp['post_id']; ?>" target="_blank" style="text-decoration:none; color:var(--text);">
                                    <?php echo e($fp['title']); ?>
                                </a>
                            </h3>
                            <p class="small-muted" style="margin:0; font-size:13px;"><?php echo e(mb_substr($fp['content'], 0, 140)); ?>...</p>
                        </div>

                        <div style="display:flex; align-items:center; gap:10px;">
                            <a href="<?php echo BASE_URL; ?>/forum/post.php?post_id=<?php echo (int)$fp['post_id']; ?>" target="_blank" class="btn btn-sm btn-secondary">
                                View (<?php echo (int)$fp['comment_count']; ?> Replies)
                            </a>
                            <?php if ($is_content_admin) { ?>
                                <form method="POST" action="<?php echo BASE_URL; ?>/admin/actions/forum_delete.php" style="display:inline;" onsubmit="return confirm('Delete this forum post?');">
                                    <input type="hidden" name="post_id" value="<?php echo (int)$fp['post_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    <?php } ?>
</main>

<!-- ADD ARTICLE MODAL -->
<div class="modal" id="addArticleModal" aria-hidden="true">
    <div class="modal-content" style="max-width:620px;">
        <div class="modal-header">
            <h2>Add New Educational Guide</h2>
            <button type="button" class="modal-close-x" onclick="closeModal('addArticleModal')" aria-label="Close">&times;</button>
        </div>

        <form method="POST" action="<?php echo BASE_URL; ?>/admin/actions/infohub_add.php" enctype="multipart/form-data">
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" required placeholder="e.g. Complete Guide to Seaweed Farming">
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label>Category *</label>
                    <select name="category" required>
                        <option value="Article">Article</option>
                        <option value="Research">Research</option>
                        <option value="Tutorial">Tutorial</option>
                        <option value="Recipes">Recipes</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Status *</label>
                    <select name="status" required>
                        <option value="Published">Published</option>
                        <option value="Draft">Draft</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Content *</label>
                <textarea name="content" rows="8" required placeholder="Write the full guide, instructions, or recommendations..."></textarea>
            </div>

            <div class="form-group">
                <label>Cover Photo (Optional)</label>
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
            </div>

            <div class="form-actions" style="justify-content:flex-end; margin-top:16px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addArticleModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Guide</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT ARTICLE MODAL -->
<div class="modal" id="editArticleModal" aria-hidden="true">
    <div class="modal-content" style="max-width:620px;">
        <div class="modal-header">
            <h2>Edit Educational Guide</h2>
            <button type="button" class="modal-close-x" onclick="closeModal('editArticleModal')" aria-label="Close">&times;</button>
        </div>

        <form method="POST" action="<?php echo BASE_URL; ?>/admin/actions/infohub_edit.php" enctype="multipart/form-data">
            <input type="hidden" name="article_id" id="edit_article_id" required>

            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" id="edit_title" required>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label>Category *</label>
                    <select name="category" id="edit_category" required>
                        <option value="Article">Article</option>
                        <option value="Research">Research</option>
                        <option value="Tutorial">Tutorial</option>
                        <option value="Recipes">Recipes</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Status *</label>
                    <select name="status" id="edit_status" required>
                        <option value="Published">Published</option>
                        <option value="Draft">Draft</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Content *</label>
                <textarea name="content" id="edit_content" rows="8" required></textarea>
            </div>

            <div class="form-group">
                <label>Replace Cover Photo (Optional)</label>
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
            </div>

            <div class="form-actions" style="justify-content:flex-end; margin-top:16px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editArticleModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Guide</button>
            </div>
        </form>
    </div>
</div>

<script>
function populateEditArticle(btn) {
    document.getElementById('edit_article_id').value = btn.getAttribute('data-id');
    document.getElementById('edit_title').value = btn.getAttribute('data-title');
    document.getElementById('edit_category').value = btn.getAttribute('data-category');
    document.getElementById('edit_status').value = btn.getAttribute('data-status');
    document.getElementById('edit_content').value = btn.getAttribute('data-content');
    openModal('editArticleModal');
}
</script>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
