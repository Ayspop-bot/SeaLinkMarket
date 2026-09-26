<?php
/**
 * SeaLink Web Application
 * File: /farmer/messages.php
 * Purpose: Farmer messaging interface with contact search bar, fixed SQL grouping, and clean send icon.
 * Connected To:
 * - /farmer/actions/send_message.php
 * Uses: message_tbl, buyer_tbl, order_tbl
 */

require_once __DIR__ . '/../includes/auth_check.php';
check_access('farmer');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$active_tab = 'messages';
$page_title = "Messages - SeaLink";
require_once __DIR__ . '/../includes/layout_top.php';

$farmer_id = (int)$_SESSION['user_id'];
$buyer_id  = (int)($_GET['buyer_id'] ?? 0);
$has_explicit_buyer = isset($_GET['buyer_id']) && (int)$_GET['buyer_id'] > 0;

// Subquery for contacts and latest message time
$sql = "
SELECT 
    b.buyer_id, 
    b.username, 
    b.full_name, 
    b.profile_image,
    COALESCE(
        (SELECT MAX(sent_at) FROM message_tbl WHERE (farmer_id = ? AND buyer_id = b.buyer_id)),
        '2000-01-01 00:00:00'
    ) AS latest_time,
    (SELECT content FROM message_tbl WHERE (farmer_id = ? AND buyer_id = b.buyer_id) ORDER BY sent_at DESC LIMIT 1) AS last_content
FROM buyer_tbl b
WHERE b.buyer_id IN (
    SELECT DISTINCT buyer_id FROM message_tbl WHERE farmer_id = ?
    UNION
    SELECT DISTINCT buyer_id FROM order_tbl WHERE farmer_id = ?
)
ORDER BY latest_time DESC, b.full_name ASC
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "iiii", $farmer_id, $farmer_id, $farmer_id, $farmer_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$contacts = [];
while ($row = mysqli_fetch_assoc($res)) {
    $contacts[] = $row;
}
mysqli_stmt_close($stmt);

// Auto-select first contact on desktop if none specified
$active_buyer_id = $buyer_id;
if ($active_buyer_id <= 0 && !empty($contacts)) {
    $active_buyer_id = (int)$contacts[0]['buyer_id'];
}

$selected_buyer = null;
$messages = [];

if ($active_buyer_id > 0) {
    // Fetch buyer details
    $stmt = mysqli_prepare($conn, "SELECT buyer_id, username, full_name, profile_image, contact_number, facebook_account FROM buyer_tbl WHERE buyer_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $active_buyer_id);
    mysqli_stmt_execute($stmt);
    $selected_buyer = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    // Fetch conversation messages
    $stmt = mysqli_prepare($conn, "
        SELECT message_id, sender_type, content, sent_at 
        FROM message_tbl 
        WHERE farmer_id = ? AND buyer_id = ? 
        ORDER BY sent_at ASC
    ");
    mysqli_stmt_bind_param($stmt, "ii", $farmer_id, $active_buyer_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $messages[] = $row;
    }
    mysqli_stmt_close($stmt);
}

mysqli_close($conn);
?>

<style>
.farmer-messages-layout {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 16px;
    height: 68vh;
    min-height: 520px;
}
@media (max-width: 900px) {
    .farmer-messages-layout {
        grid-template-columns: 280px 1fr;
        gap: 12px;
    }
}
/* Phone / Mobile TikTok & Messenger style layout */
@media (max-width: 768px) {
    .farmer-messages-layout {
        display: flex;
        flex-direction: column;
        height: auto;
        min-height: 0;
        gap: 0;
    }
    <?php if ($has_explicit_buyer) { ?>
        /* In active chat: hide contacts list, hide top banner, maximize chat height */
        .messages-banner-card {
            display: none !important;
        }
        .chat-contacts-pane {
            display: none !important;
        }
        .chat-thread-pane {
            display: flex !important;
            width: 100% !important;
            height: calc(100vh - 160px);
            min-height: 500px;
            border-radius: 14px;
        }
        .mobile-back-to-contacts {
            display: inline-flex !important;
        }
    <?php } else { ?>
        /* In conversations list: show contacts list, hide chat thread */
        .chat-contacts-pane {
            display: flex !important;
            width: 100% !important;
            height: calc(100vh - 220px);
            min-height: 480px;
            border-radius: 14px;
        }
        .chat-thread-pane {
            display: none !important;
        }
    <?php } ?>
}
</style>

<main class="dashboard-content farmer-dashboard">
    <!-- Header Inside Container -->
    <div class="section-card messages-banner-card" style="margin-bottom:16px;">
        <h1 style="margin:0; font-size:24px;">Direct Messages</h1>
        <div class="small-muted" style="margin-top:4px;">Communicate directly with buyers regarding orders and inquiries</div>
    </div>

    <div class="farmer-messages-layout">
        <!-- Left Column: Conversations List & Search -->
        <div class="panel chat-contacts-pane" style="padding:0; overflow-y:hidden; display:flex; flex-direction:column; border:1px solid var(--border);">
            <div style="padding:14px; border-bottom:1px solid var(--border); font-weight:800; font-size:15px; background:#f9fbfb;">
                Conversations (<?php echo count($contacts); ?>)
            </div>

            <!-- Search input to find conversations -->
            <div style="padding:10px 12px; border-bottom:1px solid var(--border); background:#fff;">
                <input type="text" id="contactSearchInput" placeholder="Search conversations..." onkeyup="filterContacts()"
                       style="width:100%; padding:8px 12px; border:1px solid var(--border); border-radius:8px; font-size:13px;">
            </div>

            <div style="flex:1; overflow-y:auto;" id="contactsListContainer">
                <?php if (empty($contacts)) { ?>
                    <div style="padding:24px 16px; text-align:center; color:var(--muted);" class="small-muted">
                        No conversations yet. When buyers message you or place orders, they will appear here.
                    </div>
                <?php } ?>

                <?php foreach ($contacts as $c) { 
                    $is_active = ((int)$c['buyer_id'] === $active_buyer_id);
                    $bg = $is_active ? 'background: rgba(100, 149, 237, 0.15); border-left: 4px solid var(--primary);' : 'border-left: 4px solid transparent;';
                ?>
                    <a href="<?php echo BASE_URL; ?>/farmer/messages.php?buyer_id=<?php echo (int)$c['buyer_id']; ?>"
                       class="contact-item-row"
                       data-name="<?php echo strtolower(e($c['full_name'] . ' ' . $c['username'])); ?>"
                       style="display:flex; align-items:center; gap:12px; padding:12px 14px; border-bottom:1px solid var(--border); text-decoration:none; color:var(--text); transition:background .15s; <?php echo $bg; ?>">
                        <div style="width:40px; height:40px; border-radius:999px; overflow:hidden; background:#eee; flex-shrink:0; display:flex; align-items:center; justify-content:center;">
                            <?php if (!empty($c['profile_image'])) { ?>
                                <img src="<?php echo BASE_URL . '/' . e($c['profile_image']); ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                            <?php } else { ?>
                                <span style="font-size:12px; color:var(--muted);">User</span>
                            <?php } ?>
                        </div>

                        <div style="flex:1; min-width:0;">
                            <div style="font-weight:700; font-size:14px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                <?php echo e($c['full_name']); ?>
                            </div>
                            <div class="small-muted" style="font-size:12px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px;">
                                <?php echo !empty($c['last_content']) ? e($c['last_content']) : 'Start conversation'; ?>
                            </div>
                        </div>
                    </a>
                <?php } ?>
            </div>
        </div>

        <!-- Right Column: Active Conversation Thread -->
        <div class="panel chat-thread-pane" style="padding:0; display:flex; flex-direction:column; border:1px solid var(--border); overflow:hidden;">
            <?php if ($selected_buyer) { ?>
                <!-- Thread Header -->
                <div style="padding:12px 18px; border-bottom:1px solid var(--border); background:#f9fbfb; display:flex; justify-content:space-between; align-items:center; gap:10px;">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <a href="<?php echo BASE_URL; ?>/farmer/messages.php" class="mobile-back-to-contacts" style="display:none; align-items:center; gap:4px; font-weight:700; font-size:13px; color:var(--primary); text-decoration:none; padding:6px 10px; border-radius:8px; background:rgba(100, 149, 237, 0.12); margin-right:4px;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
                            </svg>
                            <span>Chats</span>
                        </a>
                        <div style="width:40px; height:40px; border-radius:999px; overflow:hidden; background:#eee; flex-shrink:0; display:flex; align-items:center; justify-content:center;">
                            <?php if (!empty($selected_buyer['profile_image'])) { ?>
                                <img src="<?php echo BASE_URL . '/' . e($selected_buyer['profile_image']); ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                            <?php } else { ?>
                                <span style="font-size:12px; color:var(--muted);">User</span>
                            <?php } ?>
                        </div>
                        <div>
                            <div style="font-weight:800; font-size:16px;"><?php echo e($selected_buyer['full_name']); ?></div>
                            <div class="small-muted" style="font-size:12px;">
                                @<?php echo e($selected_buyer['username']); ?> &bull; Contact: <?php echo e($selected_buyer['contact_number'] ?? 'N/A'); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Messages Scroll Area -->
                <div id="messagesThread" style="flex:1; overflow-y:auto; padding:18px; display:flex; flex-direction:column; gap:12px; background:#fbfbfb;">
                    <?php if (empty($messages)) { ?>
                        <div style="text-align:center; padding:30px 10px; color:var(--muted);" class="small-muted">
                            No messages yet. Send a message below to begin communicating.
                        </div>
                    <?php } ?>

                    <?php foreach ($messages as $m) {
                        $is_me = ($m['sender_type'] === 'Farmer');
                    ?>
                        <div style="display:flex; flex-direction:column; max-width:72%; <?php echo $is_me ? 'align-self:flex-end; align-items:flex-end;' : 'align-self:flex-start; align-items:flex-start;'; ?>">
                            <div style="padding:10px 16px; border-radius:16px; font-size:14px; line-height:1.45; <?php echo $is_me ? 'background: var(--primary); color:#fff; border-bottom-right-radius:4px;' : 'background: #fff; color:var(--text); border:1px solid var(--border); border-bottom-left-radius:4px;'; ?>">
                                <?php echo nl2br(e($m['content'])); ?>
                            </div>
                            <div style="font-size:11px; color:var(--muted); margin-top:4px; padding:0 4px;">
                                <?php echo date('M d, h:i A', strtotime($m['sent_at'])); ?>
                            </div>
                        </div>
                    <?php } ?>
                </div>

                <!-- Message Input Bar with Send Icon -->
                <div style="padding:12px 16px; border-top:1px solid var(--border); background:#fff;">
                    <form method="POST" action="<?php echo BASE_URL; ?>/farmer/actions/send_message.php" style="display:flex; gap:10px; align-items:center;">
                        <input type="hidden" name="buyer_id" value="<?php echo (int)$selected_buyer['buyer_id']; ?>">
                        <input type="text" name="content" placeholder="Write a message to <?php echo e($selected_buyer['full_name']); ?>..." required
                               style="flex:1; padding:11px 16px; border-radius:24px; border:1px solid var(--border); background:#fdfdfd; font-size:14px;"
                               autocomplete="off">
                        <button type="submit" class="btn btn-primary" style="border-radius:24px; padding:10px 18px; display:inline-flex; align-items:center; gap:6px;">
                            <span>Send</span>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                            </svg>
                        </button>
                    </form>
                </div>

            <?php } else { ?>
                <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:var(--muted); text-align:center; padding:30px;">
                    <h3 style="margin:0 0 6px 0;">No Conversation Selected</h3>
                    <p class="small-muted">Select a contact on the left to start messaging.</p>
                </div>
            <?php } ?>
        </div>
    </div>
</main>

<script>
function filterContacts() {
    var query = document.getElementById('contactSearchInput').value.toLowerCase().trim();
    var rows = document.querySelectorAll('.contact-item-row');
    rows.forEach(function(row) {
        var name = row.getAttribute('data-name') || '';
        if (name.indexOf(query) > -1) {
            row.style.display = 'flex';
        } else {
            row.style.display = 'none';
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    var thread = document.getElementById('messagesThread');
    if (thread) {
        thread.scrollTop = thread.scrollHeight;
    }
});
</script>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
