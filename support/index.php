<?php
/**
 * SeaLink Web Application
 * File: /support/index.php
 * Purpose: Support center for farmers and buyers (FAQs + Contact Admin Message Submission + My Submitted Messages).
 * Connected To:
 * - /support/actions/submit_ticket.php
 * - /support/actions/cancel_ticket.php
 * Uses: admin_support_tbl, admin_tbl
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$active_tab = 'support';
$page_title = "Support Center - SeaLink";
require_once __DIR__ . '/../includes/layout_top.php';

$is_logged_in = is_logged_in();
$role = $_SESSION['user_role'] ?? 'guest';
$user_id = $is_logged_in ? (int)($_SESSION['user_id'] ?? 0) : 0;
$is_farmer = ($role === 'farmer');
$is_buyer  = ($role === 'buyer');

$messages_list = [];
$support_ids = [];
$replies_map = [];

/* Fetch user's submitted messages if logged in */
if ($is_logged_in && ($is_farmer || $is_buyer)) {
    $sql = $is_farmer 
        ? "SELECT s.*, a.full_name AS admin_name FROM admin_support_tbl s LEFT JOIN admin_tbl a ON a.admin_id = s.admin_id WHERE s.farmer_id = ? ORDER BY s.created_at ASC"
        : "SELECT s.*, a.full_name AS admin_name FROM admin_support_tbl s LEFT JOIN admin_tbl a ON a.admin_id = s.admin_id WHERE s.buyer_id = ? ORDER BY s.created_at ASC";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $messages_list[] = $row;
            $support_ids[] = (int)$row['support_id'];
        }
        mysqli_stmt_close($stmt);
    }

    // Fetch follow-up replies
    if (!empty($support_ids)) {
        $ids_str = implode(',', $support_ids);
        $r_res = mysqli_query($conn, "SELECT * FROM admin_support_reply_tbl WHERE support_id IN ($ids_str) ORDER BY created_at ASC");
        if ($r_res) {
            while ($r = mysqli_fetch_assoc($r_res)) {
                $sid = (int)$r['support_id'];
                if (!isset($replies_map[$sid])) {
                    $replies_map[$sid] = [];
                }
                $replies_map[$sid][] = $r;
            }
        }
    }
}
mysqli_close($conn);

function format_user_support_status($st) {
    if ($st === 'Closed' || $st === 'Resolved' || $st === 'Concern Resolved') {
        return 'Concern Resolved';
    }
    if ($st === 'Cancelled' || $st === 'Canceled') {
        return 'Canceled';
    }
    return $st;
}
?>

<style>
.support-main-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
@media (max-width: 860px) {
    .support-main-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }
}
</style>

<main class="dashboard-content <?php echo $is_farmer ? 'farmer-dashboard' : ($is_buyer ? 'buyer-dashboard' : 'guest-dashboard'); ?>">
    <!-- Header Inside Container -->
    <div class="section-card" style="margin-bottom:16px;">
        <h1 style="margin:0; font-size:24px;">Support &amp; Help Center</h1>
        <div class="small-muted" style="margin-top:4px;">Get assistance, ask questions, or send inquiries to our platform administrators</div>
    </div>

    <div class="support-main-grid">
        
        <!-- Left: Frequently Asked Questions -->
        <div class="section-card" style="margin-top:0;">
            <h2 style="font-size:18px; font-weight:800; margin:0 0 14px 0;">Frequently Asked Questions</h2>
            
            <div style="display:flex; flex-direction:column; gap:12px;">
                <details style="padding:12px; background:#f9fbfb; border:1px solid var(--border); border-radius:8px;">
                    <summary style="font-weight:700; cursor:pointer; color:var(--text);">How do I update my profile and store details?</summary>
                    <p class="small-muted" style="margin-top:8px; line-height:1.5;">
                        Click on the profile icon in the top-right corner of the header. From your Profile page, you can review your registered account information and delivery addresses.
                    </p>
                </details>

                <details style="padding:12px; background:#f9fbfb; border:1px solid var(--border); border-radius:8px;">
                    <summary style="font-weight:700; cursor:pointer; color:var(--text);">How does farmer account verification work?</summary>
                    <p class="small-muted" style="margin-top:8px; line-height:1.5;">
                        Our Administrator reviews the business permit number you submitted during registration. Once verified, product posting and management privileges are automatically enabled.
                    </p>
                </details>

                <details style="padding:12px; background:#f9fbfb; border:1px solid var(--border); border-radius:8px;">
                    <summary style="font-weight:700; cursor:pointer; color:var(--text);">What payment methods are supported?</summary>
                    <p class="small-muted" style="margin-top:8px; line-height:1.5;">
                        SeaLink supports Cash on Delivery (COD) and GCash payments with instant receipt upload for verification by sellers and administrators.
                    </p>
                </details>

                <details style="padding:12px; background:#f9fbfb; border:1px solid var(--border); border-radius:8px;">
                    <summary style="font-weight:700; cursor:pointer; color:var(--text);">Can I browse products and info guides without logging in?</summary>
                    <p class="small-muted" style="margin-top:8px; line-height:1.5;">
                        Yes! SeaLink allows anyone to explore fresh catch listings, search Santa Fe categories, check Today's Deals, and read aquaculture articles without an account. You only need to sign in when you want to add items to your cart, place orders, or contact farmers.
                    </p>
                </details>

                <details style="padding:12px; background:#f9fbfb; border:1px solid var(--border); border-radius:8px;">
                    <summary style="font-weight:700; cursor:pointer; color:var(--text);">What is the Platform Sustainability Fee?</summary>
                    <p class="small-muted" style="margin-top:8px; line-height:1.5;">
                        A modest 2% platform sustainability fee is applied on completed sales to fund platform server maintenance and coastal fisherfolk support initiatives in Santa Fe, Romblon.
                    </p>
                </details>
            </div>
        </div>

        <!-- Right: Contact Admin Message Submission -->
        <div class="section-card" style="margin-top:0;">
            <h2 style="font-size:18px; font-weight:800; margin:0 0 14px 0;">Contact Support Admin</h2>
            <?php if ($is_logged_in) { ?>
                <form method="POST" action="<?php echo BASE_URL; ?>/support/actions/submit_ticket.php">
                    <div class="form-group">
                        <label>How can we help you? (Message) *</label>
                        <textarea name="message" rows="6" required placeholder="Describe your inquiry, issue, or concern for our administrators..." style="resize:vertical;"></textarea>
                    </div>
                    
                    <div style="margin-top:14px;">
                        <button class="btn btn-primary btn-block" type="submit">
                            Submit Message
                        </button>
                    </div>
                </form>
            <?php } else { ?>
                <div style="position:relative;">
                    <div class="form-group" style="margin-bottom:12px;">
                        <label style="color:var(--muted);">How can we help you? (Message) *</label>
                        <textarea rows="5" disabled placeholder="Sign in to describe your inquiry, issue, or concern for our administrators..." style="resize:none; background:#f8fafc; color:#94a3b8; cursor:not-allowed; border:1px solid var(--border);"></textarea>
                    </div>
                    
                    <button class="btn btn-primary btn-block" type="button" disabled style="opacity:0.6; cursor:not-allowed; margin-bottom:14px;">
                        Submit Message
                    </button>

                    <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:10px; padding:16px; text-align:center;">
                        <div style="font-size:22px; margin-bottom:4px;"></div>
                        <div style="font-weight:800; font-size:14px; color:#0369a1; margin-bottom:4px;">
                            Login Required to Send Messages
                        </div>
                        <p class="small-muted" style="margin:0 0 12px 0; font-size:13px; line-height:1.4;">
                            Guests are not allowed to send messages or submit concerns unless signed in. Please log in or create an account to contact support administrators.
                        </p>
                        <div style="display:flex; justify-content:center; gap:8px;">
                            <a href="<?php echo BASE_URL; ?>/login.php?redirect=<?php echo urlencode('/support/index.php'); ?>" class="btn btn-primary btn-sm" style="font-weight:700; padding:6px 18px;">
                                Sign In
                            </a>
                            <a href="<?php echo BASE_URL; ?>/register.php" class="btn btn-secondary btn-sm" style="padding:6px 18px;">
                                Register
                            </a>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>

    </div>

    <!-- My Submitted Messages History -->
    <div class="section-card" style="margin-top:20px;">
        <h2 style="font-size:18px; font-weight:800; margin:0 0 14px 0;">My Submitted Messages</h2>

        <?php if (!$is_logged_in) { ?>
            <div style="text-align:center; padding:36px 20px; background:#f8fafc; border:1px dashed var(--border); border-radius:12px;">
                <div style="font-size:32px; margin-bottom:8px;"></div>
                <div style="font-weight:800; font-size:15px; color:var(--text); margin-bottom:4px;">View Your Message History</div>
                <div class="small-muted" style="max-width:440px; margin:0 auto 14px auto; font-size:13px; line-height:1.5;">
                    Sign in to track your previously submitted messages, view progress updates, and read direct responses from platform administrators.
                </div>
                <a href="<?php echo BASE_URL; ?>/login.php?redirect=<?php echo urlencode('/support/index.php'); ?>" class="btn btn-primary btn-sm" style="font-weight:700;">
                    Sign in to View History
                </a>
            </div>
        <?php } elseif (empty($messages_list)) { ?>
            <div class="small-muted" style="text-align:center; padding:30px;">
                You have not submitted any messages yet.
            </div>
        <?php } else { ?>
            <div style="display:flex; flex-direction:column; gap:18px;">
                <?php 
                $reversed = array_reverse($messages_list, true);
                foreach ($reversed as $index => $t) { 
                    $sid = (int)$t['support_id'];
                    $message_num = $index + 1;
                    $status_text = format_user_support_status($t['status']);
                    $status_cls = ($status_text === 'Open') ? 'inactive' : (($status_text === 'Replied' || $status_text === 'Concern Resolved') ? 'active' : '');
                    $is_resolved_or_canceled = in_array($t['status'], ['Closed', 'Resolved', 'Concern Resolved', 'Cancelled', 'Canceled'], true);
                    $ticket_replies = $replies_map[$sid] ?? [];
                ?>
                    <div class="panel" style="border:1px solid var(--border); background:#fff; border-radius:12px; padding:18px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; border-bottom:1px solid var(--border); padding-bottom:12px; margin-bottom:14px;">
                            <div>
                                <strong style="font-size:16px; color:var(--primary);">Message #<?php echo $message_num; ?></strong>
                                <span class="small-muted" style="margin-left:8px;">
                                    &bull; Started on <?php echo date('M d, Y h:i A', strtotime($t['created_at'])); ?>
                                </span>
                            </div>

                            <!-- Status badge -->
                            <span class="badge <?php echo $status_cls; ?>" style="font-weight:700;">
                                <?php echo e($status_text); ?>
                            </span>
                        </div>

                        <!-- Conversation Thread -->
                        <div style="display:flex; flex-direction:column; gap:12px;">
                            <!-- Initial Inquiry -->
                            <div>
                                <div style="font-weight:700; font-size:12px; color:var(--muted); margin-bottom:4px;">
                                    Your Inquiry &bull; <?php echo date('M d, Y h:i A', strtotime($t['created_at'])); ?>:
                                </div>
                                <div style="background:#f9fbfb; padding:12px 14px; border-radius:8px; font-size:14px; line-height:1.5; border:1px solid var(--border);">
                                    <?php echo nl2br(e($t['message'])); ?>
                                </div>
                            </div>

                            <!-- Thread Replies -->
                            <?php 
                            if (!empty($ticket_replies)) {
                                foreach ($ticket_replies as $rep) {
                                    $is_rep_admin = ($rep['sender_type'] === 'admin');
                            ?>
                                <div>
                                    <?php if ($is_rep_admin) { ?>
                                        <div style="font-weight:700; font-size:12px; color:var(--primary); margin-bottom:4px;">
                                            Admin Reply &bull; <?php echo date('M d, Y h:i A', strtotime($rep['created_at'])); ?>:
                                        </div>
                                        <div style="background:#eef7f7; padding:12px 14px; border-left:4px solid var(--primary); border-radius:8px; font-size:14px; line-height:1.5;">
                                            <?php echo nl2br(e($rep['message'])); ?>
                                        </div>
                                    <?php } else { ?>
                                        <div style="font-weight:700; font-size:12px; color:var(--muted); margin-bottom:4px;">
                                            Your Follow-up &bull; <?php echo date('M d, Y h:i A', strtotime($rep['created_at'])); ?>:
                                        </div>
                                        <div style="background:#f9fbfb; padding:12px 14px; border-radius:8px; font-size:14px; line-height:1.5; border:1px solid var(--border);">
                                            <?php echo nl2br(e($rep['message'])); ?>
                                        </div>
                                    <?php } ?>
                                </div>
                            <?php 
                                }
                            } elseif (!empty($t['admin_reply'])) { 
                            ?>
                                <!-- Fallback for single reply -->
                                <div>
                                    <div style="font-weight:700; font-size:12px; color:var(--primary); margin-bottom:4px;">
                                        Admin Reply &bull; <?php echo date('M d, Y h:i A', strtotime($t['updated_at'])); ?>:
                                    </div>
                                    <div style="background:#eef7f7; padding:12px 14px; border-left:4px solid var(--primary); border-radius:8px; font-size:14px; line-height:1.5;">
                                        <?php echo nl2br(e($t['admin_reply'])); ?>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>

                        <!-- User Reply / Follow-up Actions -->
                        <?php if (!$is_resolved_or_canceled) { ?>
                            <div style="margin-top:16px; padding-top:14px; border-top:1px solid var(--border);">
                                <details style="background:#fafafa; border:1px solid var(--border); border-radius:8px; padding:10px 14px;">
                                    <summary style="font-weight:700; font-size:13px; color:var(--primary); cursor:pointer;">
                                        Reply / Follow-up Question
                                    </summary>
                                    <form method="POST" action="<?php echo BASE_URL; ?>/support/actions/reply_ticket.php" style="margin-top:10px;">
                                        <input type="hidden" name="support_id" value="<?php echo $sid; ?>">
                                        <div class="form-group" style="margin-bottom:8px;">
                                            <textarea name="message" rows="3" required placeholder="Type your follow-up response or question here..." style="resize:vertical;"></textarea>
                                        </div>
                                        <div style="display:flex; justify-content:flex-end;">
                                            <button type="submit" class="btn btn-sm btn-primary">Send Follow-up</button>
                                        </div>
                                    </form>
                                </details>
                            </div>
                        <?php } ?>

                        <!-- Cancel action if open -->
                        <?php if ($t['status'] === 'Open') { ?>
                            <div style="display:flex; justify-content:flex-end; margin-top:10px;">
                                <form method="POST" action="<?php echo BASE_URL; ?>/support/actions/cancel_ticket.php" onsubmit="return confirm('Cancel this inquiry message?');">
                                    <input type="hidden" name="support_id" value="<?php echo $sid; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Cancel Inquiry</button>
                                </form>
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
