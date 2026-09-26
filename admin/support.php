<?php
/**
 * SeaLink Web Application
 * File: /admin/support.php
 * Purpose: Admin interface to view, reply to, and resolve support messages.
 * Uses: admin_support_tbl, farmer_tbl, buyer_tbl, admin_tbl
 */

require_once __DIR__ . '/../includes/auth_check.php';
check_access(['Content Admin', 'User Admin']);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$active_tab = 'support';
$page_title = "Support Messages - SeaLink Admin";
require_once __DIR__ . '/../includes/layout_top.php';

$support_id = (int)($_GET['support_id'] ?? 0);
$status_filter = trim((string)($_GET['status'] ?? 'All'));
$search_query = trim((string)($_GET['search'] ?? ''));

$where_clause = "1=1";
if ($status_filter === 'Open') {
    $where_clause = "t.status = 'Open'";
} elseif ($status_filter === 'Replied') {
    $where_clause = "t.status = 'Replied'";
} elseif ($status_filter === 'Resolved' || $status_filter === 'Concern Resolved') {
    $where_clause = "t.status IN ('Closed', 'Resolved', 'Concern Resolved')";
}

if ($search_query !== '') {
    $s_esc = mysqli_real_escape_string($conn, $search_query);
    $where_clause .= " AND (t.message LIKE '%{$s_esc}%' OR f.username LIKE '%{$s_esc}%' OR f.full_name LIKE '%{$s_esc}%' OR b.username LIKE '%{$s_esc}%' OR b.full_name LIKE '%{$s_esc}%' OR f.email LIKE '%{$s_esc}%' OR b.email LIKE '%{$s_esc}%')";
}

// Fetch all messages
$sql = "
SELECT t.*, 
       COALESCE(f.username, b.username) AS username,
       COALESCE(f.full_name, b.full_name) AS full_name,
       COALESCE(f.email, b.email) AS email,
       COALESCE(f.contact_number, b.contact_number) AS contact_number,
       COALESCE(f.address, '') AS address,
       COALESCE(f.profile_image, b.profile_image) AS profile_image,
       COALESCE(f.verification_status, 'N/A') AS verification_status,
       COALESCE(f.status, b.status) AS account_status,
       COALESCE(f.created_at, b.created_at) AS user_joined,
       CASE WHEN f.farmer_id IS NOT NULL THEN 'Farmer' ELSE 'Buyer' END AS user_role,
       a.full_name AS replied_by_admin,
       a.role AS replied_by_role
FROM admin_support_tbl t
LEFT JOIN farmer_tbl f ON f.farmer_id = t.farmer_id
LEFT JOIN buyer_tbl b ON b.buyer_id = t.buyer_id
LEFT JOIN admin_tbl a ON a.admin_id = t.admin_id
WHERE {$where_clause}
ORDER BY CASE WHEN t.status = 'Open' THEN 0 WHEN t.status = 'Replied' THEN 1 ELSE 2 END, t.created_at DESC
";
$res = mysqli_query($conn, $sql);
$messages = [];
while ($row = mysqli_fetch_assoc($res)) {
    $messages[] = $row;
}

if ($support_id <= 0 && !empty($messages)) {
    $support_id = (int)$messages[0]['support_id'];
}

$selected_msg = null;
$thread_replies = [];
$has_admin_replied = false;

if ($support_id > 0) {
    foreach ($messages as $m) {
        if ((int)$m['support_id'] === $support_id) {
            $selected_msg = $m;
            break;
        }
    }

    if ($selected_msg) {
        $sid = (int)$selected_msg['support_id'];
        $t_res = mysqli_query($conn, "SELECT * FROM admin_support_reply_tbl WHERE support_id = $sid ORDER BY created_at ASC");
        if ($t_res) {
            while ($tr = mysqli_fetch_assoc($t_res)) {
                $thread_replies[] = $tr;
                if ($tr['sender_type'] === 'admin') {
                    $has_admin_replied = true;
                }
            }
        }
        if (!empty($selected_msg['admin_reply'])) {
            $has_admin_replied = true;
        }
    }
}
mysqli_close($conn);

function format_support_status($st) {
    if ($st === 'Closed' || $st === 'Resolved' || $st === 'Concern Resolved') {
        return 'Concern Resolved';
    }
    return $st;
}

function format_admin_role_label($r) {
    if ($r === 'User Admin') return 'User Administrator';
    if ($r === 'Content Admin') return 'Content Administrator';
    return $r ?: 'Administrator';
}
?>

<style>
.support-container {
    display: grid;
    grid-template-columns: 360px 1fr;
    gap: 16px;
    height: calc(100vh - 220px);
    min-height: 520px;
    margin-bottom: 20px;
}
.support-inquiries-pane {
    margin-top: 0;
    padding: 0;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 12px;
}
.support-inquiries-list {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
}
.support-inquiries-item {
    display: block;
    padding: 11px 14px;
    border-bottom: 1px solid var(--border);
    text-decoration: none;
    color: var(--text);
    transition: background 0.15s ease;
}
.support-inquiries-item:hover {
    background: #f8fafc;
}
.support-chat-pane {
    margin-top: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    height: 100%;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 12px;
}
.support-chat-header {
    flex-shrink: 0;
    padding: 14px 18px;
    border-bottom: 1px solid var(--border);
    background: #f9fbfb;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}
.support-chat-body {
    flex: 1;
    overflow-y: auto;
    padding: 18px 20px;
    display: flex;
    flex-direction: column;
    gap: 14px;
    background: #fbfbfb;
}
.support-chat-footer {
    flex-shrink: 0;
    padding: 14px 18px;
    border-top: 1px solid var(--border);
    background: #fff;
}
@media (max-width: 900px) {
    .support-container {
        grid-template-columns: 1fr;
        height: auto;
        min-height: auto;
    }
    .support-inquiries-pane {
        max-height: 380px;
    }
    .support-chat-pane {
        height: 600px;
    }
}
</style>

<main class="dashboard-content">
    <!-- Header Section Card -->
    <div class="section-card" style="margin-bottom:16px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div>
                <h1 style="margin:0; font-size:24px;">Support Messages</h1>
                <div class="small-muted" style="margin-top:4px;">Manage user inquiries, technical reports, and customer assistance messages</div>
            </div>
            <div class="small-muted"><?php echo count($messages); ?> message(s) found</div>
        </div>
    </div>

    <!-- Filter / Search Toolbar (Dropdown aligned with Search Bar base from market.php) -->
    <div class="market-toolbar" style="margin-bottom:16px;">
        <form class="market-filters" method="GET" action="" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; width:100%;">
            <select name="status" onchange="this.form.submit()">
                <option value="All" <?php echo ($status_filter === 'All') ? 'selected' : ''; ?>>All Status</option>
                <option value="Open" <?php echo ($status_filter === 'Open') ? 'selected' : ''; ?>>Open</option>
                <option value="Replied" <?php echo ($status_filter === 'Replied') ? 'selected' : ''; ?>>Replied</option>
                <option value="Concern Resolved" <?php echo ($status_filter === 'Concern Resolved' || $status_filter === 'Resolved') ? 'selected' : ''; ?>>Concern Resolved</option>
            </select>

            <div class="search-input-wrapper">
                <button type="button" class="search-clear-x" onclick="clearSearchAndRefresh(this)" title="Clear and refresh search" aria-label="Clear search" <?php echo empty($search_query) ? 'style="display:none;"' : 'style="display:flex;"'; ?>>&times;</button>
                <input type="text" name="search" placeholder="Search message, sender name, username, or email..."
                       value="<?php echo e($search_query); ?>" oninput="checkSearchClear(this)">
            </div>

            <button type="submit" class="btn btn-primary btn-sm">Search</button>
        </form>
    </div>

    <!-- 2 Column Messaging Layout -->
    <div class="support-container">
        
        <!-- Left: Messages List (Matching messages conversation layout) -->
        <div class="section-card support-inquiries-pane">
            <div style="flex-shrink:0; padding:14px 16px; border-bottom:1px solid var(--border); font-weight:800; font-size:15px; background:#f9fbfb; display:flex; justify-content:space-between; align-items:center;">
                <span>Inquiries (<?php echo count($messages); ?>)</span>
            </div>

            <div class="support-inquiries-list">
                <?php if (empty($messages)) { ?>
                    <div style="padding:30px 16px; text-align:center; color:var(--muted);" class="small-muted">
                        No support messages found in this view.
                    </div>
                <?php } ?>

                <?php foreach ($messages as $m) { 
                    $is_sel = ((int)$m['support_id'] === $support_id);
                    $display_status = format_support_status($m['status']);
                    $status_badge_cls = ($display_status === 'Open') ? 'inactive' : (($display_status === 'Replied') ? 'active' : '');
                    $active_bg = $is_sel ? 'background: rgba(100, 149, 237, 0.15); border-left: 4px solid var(--primary);' : 'border-left: 4px solid transparent;';
                ?>
                    <a href="?support_id=<?php echo (int)$m['support_id']; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search_query); ?>"
                       class="support-inquiries-item"
                       style="<?php echo $active_bg; ?>">
                        
                        <div style="display:flex; gap:10px; align-items:flex-start;">
                            <!-- Clickable Avatar -->
                            <div style="width:38px; height:38px; border-radius:50%; overflow:hidden; background:#eee; flex-shrink:0; cursor:pointer;"
                                 onclick="event.preventDefault(); showUserProfile('<?php echo e(addslashes($m['full_name'])); ?>', '<?php echo e(addslashes($m['username'])); ?>', '<?php echo e($m['user_role']); ?>', '<?php echo e(addslashes($m['email'] ?? '')); ?>', '<?php echo e(addslashes($m['contact_number'] ?? '')); ?>', '<?php echo e(addslashes($m['address'] ?? '')); ?>', '<?php echo !empty($m['profile_image']) ? BASE_URL . '/' . e($m['profile_image']) : ''; ?>', '<?php echo e($m['verification_status']); ?>', '<?php echo e($m['account_status']); ?>', '<?php echo date('M d, Y', strtotime($m['user_joined'])); ?>')">
                                <?php if (!empty($m['profile_image'])) { ?>
                                    <img src="<?php echo BASE_URL . '/' . e($m['profile_image']); ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                                <?php } else { ?>
                                    <div style="display:flex;align-items:center;justify-content:center;height:100%;"><svg width="20" height="20" viewBox="0 0 24 24" fill="#aaa"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg></div>
                                <?php } ?>
                            </div>

                            <div style="flex:1; min-width:0;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2px; gap:6px;">
                                    <span style="font-weight:800; font-size:13.5px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                        <?php echo e($m['full_name']); ?>
                                    </span>
                                    <span class="badge <?php echo $status_badge_cls; ?>" style="font-size:10px; padding:2px 7px; flex-shrink:0;">
                                        <?php echo e($display_status); ?>
                                    </span>
                                </div>

                                <!-- Role badge without the word 'role:' -->
                                <div style="margin-bottom:4px;">
                                    <span style="font-size:11px; font-weight:700; color:var(--primary); background:rgba(100,149,237,0.15); padding:2px 6px; border-radius:4px;">
                                        <?php echo e($m['user_role']); ?>
                                    </span>
                                </div>

                                <!-- Message snippet -->
                                <div style="font-size:13px; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-bottom:4px;">
                                    <?php echo e($m['message']); ?>
                                </div>

                                <!-- Date below the message -->
                                <div class="small-muted" style="font-size:11px;">
                                    <?php echo date('M d, Y h:i A', strtotime($m['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                    </a>
                <?php } ?>
            </div>
        </div>

        <!-- Right: Active Support Message Thread & Direct Reply -->
        <div class="section-card support-chat-pane">
            <?php if ($selected_msg) { 
                $curr_display_status = format_support_status($selected_msg['status']);
                $curr_badge_cls = ($curr_display_status === 'Open') ? 'inactive' : (($curr_display_status === 'Replied') ? 'active' : '');
            ?>
                <!-- Header -->
                <div class="support-chat-header">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <div style="width:44px; height:44px; border-radius:50%; overflow:hidden; background:#eee; cursor:pointer;"
                             onclick="showUserProfile('<?php echo e(addslashes($selected_msg['full_name'])); ?>', '<?php echo e(addslashes($selected_msg['username'])); ?>', '<?php echo e($selected_msg['user_role']); ?>', '<?php echo e(addslashes($selected_msg['email'] ?? '')); ?>', '<?php echo e(addslashes($selected_msg['contact_number'] ?? '')); ?>', '<?php echo e(addslashes($selected_msg['address'] ?? '')); ?>', '<?php echo !empty($selected_msg['profile_image']) ? BASE_URL . '/' . e($selected_msg['profile_image']) : ''; ?>', '<?php echo e($selected_msg['verification_status']); ?>', '<?php echo e($selected_msg['account_status']); ?>', '<?php echo date('M d, Y', strtotime($selected_msg['user_joined'])); ?>')">
                            <?php if (!empty($selected_msg['profile_image'])) { ?>
                                <img src="<?php echo BASE_URL . '/' . e($selected_msg['profile_image']); ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                            <?php } else { ?>
                                <div style="display:flex;align-items:center;justify-content:center;height:100%;"><svg width="22" height="22" viewBox="0 0 24 24" fill="#aaa"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg></div>
                            <?php } ?>
                        </div>
                        <div>
                            <div style="font-weight:800; font-size:16px; cursor:pointer; color:var(--primary);"
                                 onclick="showUserProfile('<?php echo e(addslashes($selected_msg['full_name'])); ?>', '<?php echo e(addslashes($selected_msg['username'])); ?>', '<?php echo e($selected_msg['user_role']); ?>', '<?php echo e(addslashes($selected_msg['email'] ?? '')); ?>', '<?php echo e(addslashes($selected_msg['contact_number'] ?? '')); ?>', '<?php echo e(addslashes($selected_msg['address'] ?? '')); ?>', '<?php echo !empty($selected_msg['profile_image']) ? BASE_URL . '/' . e($selected_msg['profile_image']) : ''; ?>', '<?php echo e($selected_msg['verification_status']); ?>', '<?php echo e($selected_msg['account_status']); ?>', '<?php echo date('M d, Y', strtotime($selected_msg['user_joined'])); ?>')">
                                <?php echo e($selected_msg['full_name']); ?> (@<?php echo e($selected_msg['username']); ?>)
                            </div>
                            <div class="small-muted" style="font-size:12px; margin-top:2px;">
                                <?php echo e($selected_msg['user_role']); ?> &bull; Contact: <?php echo e($selected_msg['contact_number']); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Status (without word 'status:') -->
                    <div>
                        <span class="badge <?php echo $curr_badge_cls; ?>" style="font-size:13px; font-weight:800; padding:6px 14px;">
                            <?php echo e($curr_display_status); ?>
                        </span>
                    </div>
                </div>

                <!-- Conversation Body -->
                <div class="support-chat-body">
                    
                    <!-- Admin Awareness Banner if already replied -->
                    <?php if ($has_admin_replied) { ?>
                        <div style="background:#e8f4fd; border:1px solid #b6e0fe; color:#084298; border-radius:8px; padding:10px 14px; font-size:13px; display:flex; align-items:center; gap:8px;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
                            <span><strong>Admin Awareness Notice:</strong> An administrator has already responded to this inquiry. See response details below.</span>
                        </div>
                    <?php } ?>

                    <!-- Initial User Inquiry -->
                    <div>
                        <div class="small-muted" style="font-size:12px; margin-bottom:6px; font-weight:700;">
                            Inquiry from <?php echo e($selected_msg['full_name']); ?> &bull; <?php echo date('F j, Y h:i A', strtotime($selected_msg['created_at'])); ?>:
                        </div>
                        <div style="background:#fff; padding:16px; border-radius:10px; border:1px solid var(--border); font-size:14px; line-height:1.65; color:var(--text);">
                            <?php echo nl2br(e($selected_msg['message'])); ?>
                        </div>
                    </div>

                    <!-- Thread Replies -->
                    <?php 
                    if (!empty($thread_replies)) {
                        foreach ($thread_replies as $rep) {
                            $is_rep_admin = ($rep['sender_type'] === 'admin');
                    ?>
                        <div>
                            <?php if ($is_rep_admin) { 
                                $adm_role_name = format_admin_role_label($rep['sender_role']);
                            ?>
                                <div style="font-weight:700; font-size:12px; color:var(--primary); margin-bottom:6px;">
                                    Admin Reply (<?php echo e($adm_role_name); ?>) &bull; <?php echo date('M d, Y h:i A', strtotime($rep['created_at'])); ?>:
                                </div>
                                <div style="background:#eef7f7; padding:16px; border-radius:10px; border-left:4px solid var(--primary); font-size:14px; line-height:1.65; color:var(--text);">
                                    <?php echo nl2br(e($rep['message'])); ?>
                                </div>
                            <?php } else { ?>
                                <div class="small-muted" style="font-size:12px; margin-bottom:6px; font-weight:700;">
                                    Follow-up from <?php echo e($rep['sender_name']); ?> &bull; <?php echo date('M d, Y h:i A', strtotime($rep['created_at'])); ?>:
                                </div>
                                <div style="background:#fff; padding:16px; border-radius:10px; border:1px solid var(--border); font-size:14px; line-height:1.65; color:var(--text);">
                                    <?php echo nl2br(e($rep['message'])); ?>
                                </div>
                            <?php } ?>
                        </div>
                    <?php 
                        }
                    } elseif (!empty($selected_msg['admin_reply'])) { 
                        $adm_role_name = format_admin_role_label($selected_msg['replied_by_role'] ?? 'User Admin');
                    ?>
                        <div>
                            <div style="font-weight:700; font-size:12px; color:var(--primary); margin-bottom:6px;">
                                Admin Reply (<?php echo e($adm_role_name); ?>) &bull; <?php echo date('M d, Y h:i A', strtotime($selected_msg['updated_at'])); ?>:
                            </div>
                            <div style="background:#eef7f7; padding:16px; border-radius:10px; border-left:4px solid var(--primary); font-size:14px; line-height:1.65; color:var(--text);">
                                <?php echo nl2br(e($selected_msg['admin_reply'])); ?>
                            </div>
                        </div>
                    <?php } ?>
                </div>

                <!-- Action / Direct Reply Form -->
                <div class="support-chat-footer">
                    <form method="POST" action="<?php echo BASE_URL; ?>/admin/actions/support_reply.php">
                        <input type="hidden" name="support_id" value="<?php echo (int)$selected_msg['support_id']; ?>">
                        
                        <div class="form-group" style="margin-bottom:12px;">
                            <label style="font-size:13px; font-weight:700;">Reply to User</label>
                            <textarea name="admin_reply" rows="3" placeholder="Write your response or follow-up message to the user here..."></textarea>
                        </div>

                        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                            <button type="submit" name="action" value="reply" class="btn btn-primary">
                                Send Reply
                            </button>

                            <?php if ($selected_msg['status'] !== 'Closed' && $selected_msg['status'] !== 'Resolved' && $selected_msg['status'] !== 'Concern Resolved') { ?>
                                <button type="submit" name="action" value="resolve" class="btn btn-secondary" onclick="return confirm('Mark this support inquiry as Concern Resolved?');">
                                    Concern Resolved
                                </button>
                            <?php } ?>
                        </div>
                    </form>
                </div>

            <?php } else { ?>
                <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:var(--muted); text-align:center; padding:30px;">
                    <h3 style="margin:0 0 6px 0;">No Message Selected</h3>
                    <p class="small-muted">Select an inquiry from the left panel to review and reply.</p>
                </div>
            <?php } ?>
        </div>
    </div>
</main>

<!-- User Profile Modal (Reusable from admin) -->
<div class="modal" id="adminUserProfileModal" aria-hidden="true">
  <div class="modal-content" style="max-width:480px;">
    <div class="modal-header">
      <h2>User Information</h2>
      <button type="button" class="modal-close-x" onclick="closeModal('adminUserProfileModal')" aria-label="Close">&times;</button>
    </div>

    <div style="text-align:center; padding:10px 0 16px 0;">
      <div style="width:76px; height:76px; border-radius:999px; overflow:hidden; margin:0 auto 10px auto; border:2px solid var(--primary); background:#eee;" id="modal_uprofile_avatar_box">
        <img id="modal_uprofile_img" src="" alt="" style="width:100%;height:100%;object-fit:cover;display:none;">
        <div id="modal_uprofile_fallback" style="display:flex;align-items:center;justify-content:center;height:100%;">
          <svg width="34" height="34" viewBox="0 0 24 24" fill="#aaa"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
        </div>
      </div>

      <h3 id="modal_uprofile_name" style="font-size:18px; font-weight:900; margin:0;"></h3>
      <div id="modal_uprofile_username" class="small-muted"></div>
      <div style="margin-top:6px;">
        <span id="modal_uprofile_role_badge" class="badge active"></span>
      </div>
    </div>

    <div style="display:flex; flex-direction:column; gap:10px; background:#f9fbfb; border:1px solid var(--border); border-radius:10px; padding:14px;">
      <div style="display:flex; justify-content:space-between; font-size:14px;">
        <span class="small-muted">Email:</span>
        <strong id="modal_uprofile_email"></strong>
      </div>
      <div style="display:flex; justify-content:space-between; font-size:14px;">
        <span class="small-muted">Contact:</span>
        <strong id="modal_uprofile_contact"></strong>
      </div>
      <div style="display:flex; justify-content:space-between; font-size:14px;">
        <span class="small-muted">Address:</span>
        <strong id="modal_uprofile_address"></strong>
      </div>
      <div style="display:flex; justify-content:space-between; font-size:14px;">
        <span class="small-muted">Verification:</span>
        <strong id="modal_uprofile_verification"></strong>
      </div>
      <div style="display:flex; justify-content:space-between; font-size:14px;">
        <span class="small-muted">Account Status:</span>
        <strong id="modal_uprofile_status"></strong>
      </div>
      <div style="display:flex; justify-content:space-between; font-size:14px;">
        <span class="small-muted">Joined:</span>
        <strong id="modal_uprofile_joined"></strong>
      </div>
    </div>
  </div>
</div>

<script>
function showUserProfile(fullname, username, role, email, contact, address, imgUrl, verification, status, joined) {
    document.getElementById('modal_uprofile_name').textContent = fullname;
    document.getElementById('modal_uprofile_username').textContent = '@' + username;
    document.getElementById('modal_uprofile_role_badge').textContent = role;
    document.getElementById('modal_uprofile_email').textContent = email || 'N/A';
    document.getElementById('modal_uprofile_contact').textContent = contact || 'N/A';
    document.getElementById('modal_uprofile_address').textContent = address || 'N/A';
    document.getElementById('modal_uprofile_verification').textContent = verification || 'N/A';
    document.getElementById('modal_uprofile_status').textContent = status || 'N/A';
    document.getElementById('modal_uprofile_joined').textContent = joined || 'N/A';

    var imgEl = document.getElementById('modal_uprofile_img');
    var fallback = document.getElementById('modal_uprofile_fallback');
    if (imgUrl && imgEl) {
        imgEl.src = imgUrl;
        imgEl.style.display = 'block';
        if (fallback) fallback.style.display = 'none';
    } else {
        if (imgEl) imgEl.style.display = 'none';
        if (fallback) fallback.style.display = 'flex';
    }

    openModal('adminUserProfileModal');
}
</script>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
