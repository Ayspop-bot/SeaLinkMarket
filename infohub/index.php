<?php
/**
 * SeaLink Web Application
 * File: /infohub/index.php
 * Purpose: Information Hub for Farmers and Buyers (Article, Research, Tutorial, Recipes, Forum).
 * Uses: info_hub_tbl, forum_post_tbl
 */

require_once __DIR__ . '/../includes/functions.php';
$role = $_SESSION['user_role'] ?? '';

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$active_tab = 'infohub';
$page_title = "Information Hub - SeaLink";
require_once __DIR__ . '/../includes/layout_top.php';

// Master Toggle Tab: 'knowledge' (default) or 'forum'
$current_tab = trim((string)($_GET['tab'] ?? ''));
if ($current_tab === '' && isset($_GET['category']) && $_GET['category'] === 'Forum') {
    $current_tab = 'forum';
}
if ($current_tab !== 'forum') {
    $current_tab = 'knowledge';
}

// Logged-in user information for composer
$user_id = (int)($_SESSION['user_id'] ?? 0);
$user_role = $_SESSION['user_role'] ?? '';
$curr_user_avatar = null;
$curr_user_name = $_SESSION['full_name'] ?? ($_SESSION['username'] ?? 'Guest');

if ($user_id > 0) {
    if ($user_role === 'farmer') {
        $uq = mysqli_query($conn, "SELECT profile_image, full_name, username FROM farmer_tbl WHERE farmer_id = $user_id LIMIT 1");
    } elseif ($user_role === 'buyer') {
        $uq = mysqli_query($conn, "SELECT profile_image, full_name, username FROM buyer_tbl WHERE buyer_id = $user_id LIMIT 1");
    } else {
        $uq = mysqli_query($conn, "SELECT profile_image, full_name, username FROM admin_tbl WHERE admin_id = $user_id LIMIT 1");
    }
    if ($uq && $urow = mysqli_fetch_assoc($uq)) {
        $curr_user_avatar = $urow['profile_image'];
        $curr_user_name = !empty($urow['full_name']) ? $urow['full_name'] : $urow['username'];
    }
}

// --- 1. KNOWLEDGE BASE DATA ---
$kb_categories = [
    'All'      => 'All',
    'Article'  => 'Article',
    'Research' => 'Research',
    'Tutorial' => 'Tutorial',
    'Recipes'  => 'Recipes'
];

$kb_category = trim((string)($_GET['category'] ?? 'All'));
if ($kb_category === 'Forum' || !array_key_exists($kb_category, $kb_categories)) {
    $kb_category = 'All';
}
$kb_search = trim((string)($_GET['search'] ?? ''));

$articles = [];
if ($current_tab === 'knowledge') {
    $where = "status = 'Published'";
    $params = [];
    $types = "";

    if ($kb_category !== 'All') {
        $where .= " AND category = ?";
        $params[] = $kb_category;
        $types .= "s";
    }

    if ($kb_search !== '') {
        $where .= " AND (title LIKE CONCAT('%', ?, '%') OR content LIKE CONCAT('%', ?, '%') OR category LIKE CONCAT('%', ?, '%'))";
        $params[] = $kb_search;
        $params[] = $kb_search;
        $params[] = $kb_search;
        $types .= "sss";
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

// --- 2. COMMUNITY FORUM DATA ---
$forum_categories = [
    'All'                      => 'All Discussions',
    'General Discussion'       => 'General',
    'Aquaculture & Techniques' => 'Farming Techniques',
    'Market & Pricing'         => 'Market & Pricing',
    'Equipment & Supplies'     => 'Supplies & Equipment',
    'Ask the Community'        => 'Q&A'
];

$forum_cat = trim((string)($_GET['forum_cat'] ?? 'All'));
$forum_search = trim((string)($_GET['forum_search'] ?? ''));

$forum_posts = [];
if ($current_tab === 'forum') {
    $f_where = "1=1";
    $f_params = [];
    $f_types = "";

    if ($forum_cat !== 'All' && array_key_exists($forum_cat, $forum_categories)) {
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
               COUNT(DISTINCT c.comment_id) AS comment_count,
               COALESCE(f.profile_image, b.profile_image, a.profile_image) AS author_image
        FROM forum_post_tbl p 
        LEFT JOIN farmer_tbl f ON (p.user_role = 'farmer' AND f.farmer_id = p.user_id)
        LEFT JOIN buyer_tbl b ON (p.user_role = 'buyer' AND b.buyer_id = p.user_id)
        LEFT JOIN admin_tbl a ON (p.user_role IN ('admin', 'Content Admin', 'User Admin') AND a.admin_id = p.user_id)
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

$pub_count_res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM info_hub_tbl WHERE status = 'Published'");
$pub_count_row = $pub_count_res ? mysqli_fetch_assoc($pub_count_res) : null;
$total_publications = (int)($pub_count_row['total'] ?? 4);

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

<main class="dashboard-content <?php echo ($role === 'farmer') ? 'farmer-dashboard' : (($role === 'buyer') ? 'buyer-dashboard' : ''); ?>">
    <!-- Header Section Card -->
    <div class="section-card" style="margin-bottom:16px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div>
                <h1 style="margin:0; font-size:24px;">SeaLink Information Hub</h1>
                <div class="small-muted" style="margin-top:4px;">Educational guides, research, aquaculture tutorials, recipes, and community knowledge</div>
            </div>
            <div class="small-muted"><?php echo $total_publications; ?> publication(s)</div>
        </div>
    </div>

    <!-- Master Toggle Tabs: [Knowledge Base] and [Community Forum] -->
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:18px; border-bottom:2px solid var(--border); padding-bottom:12px;">
        <a href="?tab=knowledge" class="btn <?php echo ($current_tab === 'knowledge') ? 'btn-primary' : 'btn-secondary'; ?>" 
           style="border-radius:24px; padding:9px 24px; font-weight:800; font-size:14px; text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
            <span></span> Publications
        </a>
        <a href="?tab=forum" class="btn <?php echo ($current_tab === 'forum') ? 'btn-primary' : 'btn-secondary'; ?>" 
           style="border-radius:24px; padding:9px 24px; font-weight:800; font-size:14px; text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
            <span></span> Community Forum
        </a>
    </div>

    <?php if ($current_tab === 'knowledge') { ?>
        <!-- ============================================== -->
        <!-- TAB 1: KNOWLEDGE BASE                          -->
        <!-- ============================================== -->
        <!-- Search & Filter Container (Category Dropdown aligned with Search Bar base from market.php) -->
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
                    <input type="text" name="search" placeholder="Search articles, research, tutorials, recipes, or topics..."
                           value="<?php echo e($kb_search); ?>" oninput="checkSearchClear(this)">
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Search</button>
            </form>
        </div>


        <!-- Publications Grid (Fixed 4 columns per row) -->
        <div class="deals-fixed-grid">
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
                        <a href="<?php echo BASE_URL; ?>/infohub/read.php?article_id=<?php echo (int)$a['article_id']; ?>"
                           class="btn btn-sm btn-primary btn-block" style="text-align:center;">
                            Read Guide
                        </a>
                    </div>
                </div>
            <?php } ?>
        </div>

    <?php } else { ?>
        <!-- ============================================== -->
        <!-- TAB 2: COMMUNITY FORUM FEED (DIRECT LOAD)      -->
        <!-- ============================================== -->
        <!-- Forum Search & Filter Bar (Category Dropdown aligned with Search Bar base from market.php) -->
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

        

        <!-- 3. Facebook/LinkedIn Styled "Create Post" Composer Box -->
        <div class="section-card" style="margin-top:0; margin-bottom:18px; padding:16px 20px; border-radius:14px; background:#fff; border:1px solid var(--border); box-shadow:0 2px 8px rgba(0,0,0,0.02);">
            <div style="display:flex; align-items:center; gap:14px;">
                <!-- User Avatar -->
                <div style="width:44px; height:44px; border-radius:50%; overflow:hidden; background:#eff6ff; flex-shrink:0; border:2px solid var(--border); display:flex; align-items:center; justify-content:center;">
                    <?php if (!empty($curr_user_avatar)) { ?>
                        <img src="<?php echo BASE_URL . '/' . e($curr_user_avatar); ?>" alt="<?php echo e($curr_user_name); ?>" style="width:100%; height:100%; object-fit:cover;">
                    <?php } else { ?>
                        <span style="font-weight:800; font-size:16px; color:var(--primary);">
                            <?php echo e(strtoupper(substr($curr_user_name, 0, 1))); ?>
                        </span>
                    <?php } ?>
                </div>

                <!-- Composer Input Trigger -->
                <div onclick="openStartDiscussionModal()"
                     style="flex:1; background:#f1f5f9; border:1px solid #cbd5e1; border-radius:999px; padding:12px 20px; font-size:14px; color:#64748b; cursor:pointer; transition:all 0.2s; user-select:none;"
                     onmouseover="this.style.background='#e2e8f0'; this.style.borderColor='#94a3b8';"
                     onmouseout="this.style.background='#f1f5f9'; this.style.borderColor='#cbd5e1';">
                    <span>Ask a question, share an experience, or start a discussion...</span>
                </div>
            </div>
        </div>

        <!-- 4. Social Media Post Feed Cards -->
        <div style="display:flex; flex-direction:column; gap:14px;">
            <?php if (empty($forum_posts)) { ?>
                <div class="section-card" style="text-align:center; padding:40px 20px; color:var(--muted);">
                    No community discussions found matching your filter. Click the composer box above to start a discussion!
                </div>
            <?php } ?>

            <?php foreach ($forum_posts as $p) { 
                $role_badge_bg = ($p['user_role'] === 'farmer') ? '#dcfce7' : (($p['user_role'] === 'buyer') ? '#eff6ff' : '#f3e8ff');
                $role_badge_color = ($p['user_role'] === 'farmer') ? '#15803d' : (($p['user_role'] === 'buyer') ? '#1e40af' : '#7e22ce');
            ?>
                <div class="section-card forum-post-card" style="margin-top:0; padding:20px; border-radius:14px; background:#fff; border:1px solid var(--border); box-shadow:0 2px 8px rgba(0,0,0,0.02); transition:box-shadow .15s;">
                    <!-- Post Header: Avatar, Name, Role, Timestamp & Category -->
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px; flex-wrap:wrap; gap:10px;">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <!-- Author Avatar Circle -->
                            <div style="width:44px; height:44px; border-radius:50%; overflow:hidden; background:#f1f5f9; border:2px solid #e2e8f0; flex-shrink:0; display:flex; align-items:center; justify-content:center;">
                                <?php if (!empty($p['author_image'])) { ?>
                                    <img src="<?php echo BASE_URL . '/' . e($p['author_image']); ?>" alt="<?php echo e($p['author_name']); ?>" style="width:100%; height:100%; object-fit:cover;">
                                <?php } else { ?>
                                    <span style="font-weight:800; font-size:16px; color:var(--primary);">
                                        <?php echo e(strtoupper(substr($p['author_name'] ?? 'U', 0, 1))); ?>
                                    </span>
                                <?php } ?>
                            </div>

                            <!-- Author Metadata -->
                            <div>
                                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                    <span style="font-size:14.5px; font-weight:800; color:var(--text);">
                                        Posted by <?php echo e($p['author_name']); ?>
                                    </span>
                                    <span class="badge" style="font-size:10.5px; padding:2px 8px; border-radius:999px; background:<?php echo $role_badge_bg; ?>; color:<?php echo $role_badge_color; ?>; font-weight:700;">
                                        <?php echo ucfirst(e($p['user_role'])); ?>
                                    </span>
                                </div>
                                <div class="small-muted" style="font-size:12px; margin-top:2px;">
                                    <?php echo date('M d, Y · g:i A', strtotime($p['created_at'])); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Category Pill -->
                        <div>
                            <span class="badge" style="background:rgba(100, 149, 237, 0.12); color:var(--primary); font-weight:700; font-size:11.5px; border-radius:6px; padding:4px 10px;">
                                <?php echo e($p['category']); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Post Body: Title & Content -->
                    <div style="margin-bottom:14px;">
                        <h2 style="font-size:17.5px; font-weight:800; margin:0 0 8px 0; line-height:1.35;">
                            <a href="<?php echo BASE_URL; ?>/forum/post.php?post_id=<?php echo (int)$p['post_id']; ?>&return=<?php echo urlencode('/infohub/index.php?tab=forum'); ?>"
                               style="color:var(--text); text-decoration:none;"
                               onmouseover="this.style.color='var(--primary)';"
                               onmouseout="this.style.color='var(--text)';">
                                <?php echo e($p['title']); ?>
                            </a>
                        </h2>

                        <p style="font-size:14px; line-height:1.6; color:#334155; margin:0; white-space:pre-line;">
                            <?php echo e($p['content']); ?>
                        </p>
                    </div>

                    <!-- Post Interactive Footer: Replies & [ Reply ] Button -->
                    <div style="border-top:1px solid #f1f5f9; padding-top:12px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                        <div style="font-size:13px; font-weight:700; color:var(--muted); display:flex; align-items:center; gap:6px;">
                            <span></span> <?php echo (int)$p['comment_count']; ?> <?php echo (int)$p['comment_count'] === 1 ? 'Reply' : 'Replies'; ?>
                        </div>

                        <?php if (is_logged_in()) { ?>
                            <a href="<?php echo BASE_URL; ?>/forum/post.php?post_id=<?php echo (int)$p['post_id']; ?>&return=<?php echo urlencode('/infohub/index.php?tab=forum'); ?>" 
                               class="btn btn-sm btn-outline-primary" style="font-weight:700; font-size:12.5px; padding:6px 18px; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                                Reply
                            </a>
                        <?php } else { ?>
                            <button type="button" onclick="openGuestPromptModal('Forum')" 
                                    class="btn btn-sm btn-outline-primary" style="font-weight:700; font-size:12.5px; padding:6px 18px; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
                                Reply
                            </button>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    <?php } ?>

    <!-- START A DISCUSSION MODAL -->
    <div class="modal" id="createPostModal" aria-hidden="true" style="display:none;" onclick="if(event.target === this) closeStartDiscussionModal();">
        <div class="modal-content" style="max-width:580px;">
            <div class="modal-header">
                <h2 style="font-size:18px; font-weight:800; margin:0;">Start a Discussion</h2>
                <button type="button" class="modal-close-x" onclick="closeStartDiscussionModal()" aria-label="Close"
                        style="background:none; border:none; font-size:22px; cursor:pointer; color:var(--muted); line-height:1;">&times;</button>
            </div>

            <form method="POST" action="<?php echo BASE_URL; ?>/forum/actions/post_create.php">
                <input type="hidden" name="return" value="/infohub/index.php?tab=forum">

                <div class="form-group" style="margin-top:14px;">
                    <label style="font-weight:700; font-size:13px; display:block; margin-bottom:6px;">Discussion Topic Title *</label>
                    <input type="text" name="title" required placeholder="e.g. Best seaweed planting line depth during monsoon season?"
                           style="width:100%; padding:10px 14px; border:1px solid var(--border); border-radius:8px; font-size:14px; box-sizing:border-box;">
                </div>

                <div class="form-group" style="margin-top:14px;">
                    <label style="font-weight:700; font-size:13px; display:block; margin-bottom:6px;">Category *</label>
                    <select name="category" required style="width:100%; padding:10px 14px; border:1px solid var(--border); border-radius:8px; font-size:14px; background:#fff; box-sizing:border-box;">
                        <option value="General Discussion">General Discussion</option>
                        <option value="Aquaculture & Techniques">Farming Techniques</option>
                        <option value="Market & Pricing">Market & Pricing</option>
                        <option value="Equipment & Supplies">Supplies & Equipment</option>
                        <option value="Ask the Community">Q&A</option>
                    </select>
                </div>

                <div class="form-group" style="margin-top:14px;">
                    <label style="font-weight:700; font-size:13px; display:block; margin-bottom:6px;">Your Message / Question *</label>
                    <textarea name="content" rows="6" required placeholder="Describe your topic, question, or tips clearly for community members..."
                              style="width:100%; padding:10px 14px; border:1px solid var(--border); border-radius:8px; font-size:14px; font-family:inherit; box-sizing:border-box;"></textarea>
                </div>

                <div class="form-actions" style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeStartDiscussionModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="font-weight:800; padding:10px 24px;">Submit</button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php if (!is_logged_in()) { 
    require_once __DIR__ . '/../includes/modal_guest.php'; 
} ?>

<script>
function openStartDiscussionModal() {
    <?php if (is_logged_in()) { ?>
        var modal = document.getElementById('createPostModal');
        if (modal) {
            modal.style.display = 'flex';
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
            var titleInput = modal.querySelector('input[name="title"]');
            if (titleInput) {
                setTimeout(function() { titleInput.focus(); }, 120);
            }
        }
    <?php } else { ?>
        if (typeof openGuestPromptModal === 'function') {
            openGuestPromptModal('Forum');
        } else {
            window.location.href = '<?php echo BASE_URL; ?>/login.php?redirect=' + encodeURIComponent('/infohub/index.php?tab=forum');
        }
    <?php } ?>
}

function closeStartDiscussionModal() {
    var modal = document.getElementById('createPostModal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
