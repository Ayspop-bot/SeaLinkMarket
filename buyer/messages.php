<?php
/**
 * SeaLink Web Application
 * File: /buyer/messages.php
 * Purpose: Buyer messaging interface with search bar, fixed SQL grouping, and send icon.
 * Connected To:
 * - /buyer/actions/send_message.php
 * Uses: message_tbl, farmer_tbl, order_tbl
 */

require_once __DIR__ . '/../includes/auth_check.php';
check_access('buyer');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$active_tab = 'messages';
$page_title = "Messages - SeaLink";
require_once __DIR__ . '/../includes/layout_top.php';

$buyer_id  = (int)$_SESSION['user_id'];
$has_explicit_farmer = isset($_GET['farmer_id']) && (int)$_GET['farmer_id'] > 0;
$farmer_id = $has_explicit_farmer ? (int)$_GET['farmer_id'] : 0;

// Subquery for contacts and latest message time
$sql = "
SELECT 
    f.farmer_id, 
    f.username, 
    f.full_name, 
    f.profile_image, 
    f.address,
    COALESCE(
        (SELECT MAX(sent_at) FROM message_tbl WHERE (buyer_id = ? AND farmer_id = f.farmer_id)),
        '2000-01-01 00:00:00'
    ) AS latest_time,
    (SELECT content FROM message_tbl WHERE (buyer_id = ? AND farmer_id = f.farmer_id) ORDER BY sent_at DESC LIMIT 1) AS last_content
FROM farmer_tbl f
WHERE f.farmer_id IN (
    SELECT DISTINCT farmer_id FROM message_tbl WHERE buyer_id = ?
    UNION
    SELECT DISTINCT farmer_id FROM order_tbl WHERE buyer_id = ?
)
ORDER BY latest_time DESC, f.username ASC
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "iiii", $buyer_id, $buyer_id, $buyer_id, $buyer_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$contacts = [];
while ($row = mysqli_fetch_assoc($res)) {
    $contacts[] = $row;
}
mysqli_stmt_close($stmt);

// On desktop, auto-select first contact if none explicitly requested
$active_farmer_id = $farmer_id;
if ($active_farmer_id <= 0 && !empty($contacts)) {
    $active_farmer_id = (int)$contacts[0]['farmer_id'];
}

$selected_farmer = null;
$messages = [];

if ($active_farmer_id > 0) {
    // Fetch farmer details
    $stmt = mysqli_prepare($conn, "SELECT farmer_id, username, full_name, profile_image, address, contact_number FROM farmer_tbl WHERE farmer_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $active_farmer_id);
    mysqli_stmt_execute($stmt);
    $selected_farmer = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    // Fetch messages
    $stmt = mysqli_prepare($conn, "
        SELECT message_id, sender_type, content, sent_at 
        FROM message_tbl 
        WHERE buyer_id = ? AND farmer_id = ? 
        ORDER BY sent_at ASC
    ");
    mysqli_stmt_bind_param($stmt, "ii", $buyer_id, $active_farmer_id);
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
.buyer-messages-layout {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 16px;
    height: 68vh;
    min-height: 520px;
}
@media (max-width: 900px) {
    .buyer-messages-layout {
        grid-template-columns: 280px 1fr;
        gap: 12px;
    }
}
/* Phone / Mobile TikTok & Messenger style layout */
@media (max-width: 768px) {
    .buyer-messages-layout {
        display: flex;
        flex-direction: column;
        height: auto;
        min-height: 0;
        gap: 0;
    }
    <?php if ($has_explicit_farmer) { ?>
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

<main class="dashboard-content buyer-dashboard">
    <!-- Header Inside Container -->
    <div class="section-card messages-banner-card" style="margin-bottom:16px;">
        <h1 style="margin:0; font-size:24px;">Direct Messages</h1>
        <div class="small-muted" style="margin-top:4px;">Inquire and coordinate directly with SeaLink farmers</div>
    </div>

    <div class="buyer-messages-layout">
        <!-- Left Column: Conversations List & Search -->
        <div class="panel chat-contacts-pane" style="padding:0; overflow-y:hidden; display:flex; flex-direction:column; border:1px solid var(--border);">
            <div style="padding:14px; border-bottom:1px solid var(--border); font-weight:800; font-size:15px; background:#f9fbfb;">
                Conversations (<?php echo count($contacts); ?>)
            </div>

            <!-- Search input to find conversations -->
            <div style="padding:10px 12px; border-bottom:1px solid var(--border); background:#fff;">
                <input type="text" id="buyerContactSearchInput" placeholder="Search conversations..." onkeyup="filterBuyerContacts()"
                       style="width:100%; padding:8px 12px; border:1px solid var(--border); border-radius:8px; font-size:13px;">
            </div>

            <div style="flex:1; overflow-y:auto;" id="buyerContactsListContainer">
                <?php if (empty($contacts) && !$selected_farmer) { ?>
                    <div style="padding:24px 16px; text-align:center; color:var(--muted);" class="small-muted">
                        No conversations yet. Start a chat by visiting a product detail page or seller store.
                    </div>
                <?php } ?>

                <?php foreach ($contacts as $c) { 
                    $is_active = ((int)$c['farmer_id'] === $active_farmer_id);
                    $bg = $is_active ? 'background: rgba(100, 149, 237, 0.15); border-left: 4px solid var(--primary);' : 'border-left: 4px solid transparent;';
                ?>
                    <a href="<?php echo BASE_URL; ?>/buyer/messages.php?farmer_id=<?php echo (int)$c['farmer_id']; ?>"
                       class="buyer-contact-row"
                       data-name="<?php echo strtolower(e($c['username'] . ' ' . $c['full_name'])); ?>"
                       style="display:flex; align-items:center; gap:12px; padding:12px 14px; border-bottom:1px solid var(--border); text-decoration:none; color:var(--text); transition:background .15s; <?php echo $bg; ?>">
                        <div style="width:40px; height:40px; border-radius:999px; overflow:hidden; background:#eee; flex-shrink:0; display:flex; align-items:center; justify-content:center;">
                            <?php if (!empty($c['profile_image'])) { ?>
                                <img src="<?php echo BASE_URL . '/' . e($c['profile_image']); ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                            <?php } else { ?>
                                <span style="font-size:12px; color:var(--muted);">Seller</span>
                            <?php } ?>
                        </div>

                        <div style="flex:1; min-width:0;">
                            <div style="font-weight:700; font-size:14px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                <?php echo e($c['username']); ?>
                            </div>
                            <div class="small-muted" style="font-size:12px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px;">
                                <?php echo !empty($c['last_content']) ? e($c['last_content']) : 'Start conversation'; ?>
                            </div>
                        </div>
                    </a>
                <?php } ?>
            </div>
        </div>

        <!-- Right Column: Active Message Thread -->
        <div class="panel chat-thread-pane" style="padding:0; display:flex; flex-direction:column; border:1px solid var(--border); overflow:hidden;">
            <?php if ($selected_farmer) { ?>
                <!-- Thread Header -->
                <div style="padding:12px 18px; border-bottom:1px solid var(--border); background:#f9fbfb; display:flex; justify-content:space-between; align-items:center; gap:10px;">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <a href="<?php echo BASE_URL; ?>/buyer/messages.php" class="mobile-back-to-contacts" style="display:none; align-items:center; gap:4px; font-weight:700; font-size:13px; color:var(--primary); text-decoration:none; padding:6px 10px; border-radius:8px; background:rgba(100, 149, 237, 0.12); margin-right:4px;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
                            </svg>
                            <span>Chats</span>
                        </a>
                        <div style="width:40px; height:40px; border-radius:999px; overflow:hidden; background:#eee; flex-shrink:0; display:flex; align-items:center; justify-content:center;">
                            <?php if (!empty($selected_farmer['profile_image'])) { ?>
                                <img src="<?php echo BASE_URL . '/' . e($selected_farmer['profile_image']); ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                            <?php } else { ?>
                                <span style="font-size:12px; color:var(--muted);">Seller</span>
                            <?php } ?>
                        </div>
                        <div>
                            <div style="font-weight:800; font-size:16px;"><?php echo e($selected_farmer['username']); ?></div>
                            <div class="small-muted" style="font-size:12px;"><?php echo e($selected_farmer['full_name']); ?> &bull; <?php echo e($selected_farmer['address']); ?></div>
                        </div>
                    </div>

                    <a href="<?php echo BASE_URL; ?>/buyer/farmer_store.php?farmer_id=<?php echo (int)$selected_farmer['farmer_id']; ?>" class="btn btn-secondary btn-sm">
                        View Store
                    </a>
                </div>

                <!-- Messages Scroll Area -->
                <div id="buyerMessagesThread" style="flex:1; overflow-y:auto; padding:18px; display:flex; flex-direction:column; gap:12px; background:#fbfbfb;">
                    <?php if (empty($messages)) { ?>
                        <div style="text-align:center; padding:30px 10px; color:var(--muted);" class="small-muted">
                            No messages yet. Send a message below to inquire with this seller.
                        </div>
                    <?php } ?>

                    <?php foreach ($messages as $m) {
                        $is_me = ($m['sender_type'] === 'Buyer');
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
                    <form method="POST" action="<?php echo BASE_URL; ?>/buyer/actions/send_message.php" style="display:flex; gap:10px; align-items:center;">
                        <input type="hidden" name="farmer_id" value="<?php echo (int)$selected_farmer['farmer_id']; ?>">
                        <input type="text" name="content" placeholder="Write a message to <?php echo e($selected_farmer['username']); ?>..." required
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
                    <p class="small-muted">Select a seller on the left to view messages.</p>
                </div>
            <?php } ?>
        </div>
    </div>
</main>

<script>
function filterBuyerContacts() {
    var query = document.getElementById('buyerContactSearchInput').value.toLowerCase().trim();
    var rows = document.querySelectorAll('.buyer-contact-row');
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
    var thread = document.getElementById('buyerMessagesThread');
    if (thread) {
        thread.scrollTop = thread.scrollHeight;
    }
});
</script>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
