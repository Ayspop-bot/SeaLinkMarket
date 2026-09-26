<?php
/**
 * SeaLink Web Application
 * File: /profile/index.php
 * Purpose: User Profile for all roles (Avatar, Details Grid, Edit modal with change detection, Full Notifications link, Logout modal).
 * Uses: farmer_tbl, buyer_tbl, admin_tbl
 */

require_once __DIR__ . '/../includes/auth_check.php';
check_access(['farmer', 'buyer', 'Content Admin', 'User Admin']);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$hide_search = true;
$active_tab  = '';
$page_title  = "My Profile - SeaLink";
require_once __DIR__ . '/../includes/layout_top.php';

/* ========== LOAD USER INFO ========== */
$role    = $_SESSION['user_role'] ?? '';
$user_id = (int)($_SESSION['user_id'] ?? 0);
$user    = null;

if ($role === 'farmer') {
    $stmt = mysqli_prepare($conn, "
        SELECT username, full_name, email, contact_number, facebook_account, address,
               permit_number, verification_status, status, profile_image, created_at
        FROM farmer_tbl WHERE farmer_id = ? LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

if ($role === 'buyer') {
    $stmt = mysqli_prepare($conn, "
        SELECT username, full_name, email, contact_number, facebook_account,
               status, profile_image, created_at
        FROM buyer_tbl WHERE buyer_id = ? LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

if ($role === 'Content Admin' || $role === 'User Admin') {
    $stmt = mysqli_prepare($conn, "
        SELECT role, username, full_name, email, contact_number, address, status, profile_image, created_at
        FROM admin_tbl WHERE admin_id = ? LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

// Fetch unread/total notification count
$notif_count = 0;
if ($role === 'farmer') {
    $n_res = mysqli_query($conn, "SELECT COUNT(*) as c FROM order_tbl WHERE farmer_id = $user_id");
    $notif_count = (int)(mysqli_fetch_assoc($n_res)['c'] ?? 0);
} elseif ($role === 'buyer') {
    $n_res = mysqli_query($conn, "SELECT COUNT(*) as c FROM order_tbl WHERE buyer_id = $user_id");
    $notif_count = (int)(mysqli_fetch_assoc($n_res)['c'] ?? 0);
}

mysqli_close($conn);
?>

<main class="dashboard-content">
    <!-- Profile Card (Avatar Top Center & Grid Fields) -->
    <div class="section-card" style="margin-bottom:16px;">
        <!-- Avatar Top Center -->
        <div style="display:flex; flex-direction:column; align-items:center; margin-bottom:20px;">
            <div style="width:84px; height:84px; border-radius:999px; overflow:hidden; border:3px solid var(--primary); background:#eee; display:flex; align-items:center; justify-content:center; margin-bottom:10px;">
                <?php if (!empty($user['profile_image'])) { ?>
                    <img src="<?php echo BASE_URL . '/' . e($user['profile_image']); ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover;">
                <?php } else { ?>
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="#aaa"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
                <?php } ?>
            </div>
            <h1 style="font-size:22px; font-weight:900; margin:0;"><?php echo e($user['full_name'] ?? 'My Profile'); ?></h1>
            <div class="small-muted" style="margin-top:2px;">@<?php echo e($user['username'] ?? ''); ?> &bull; <?php echo e($role); ?></div>
        </div>

        <!-- 3-Column Profile Fields Grid -->
        <?php if ($user) { ?>
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:14px; margin-bottom:18px;">
                <div class="panel" style="background:#f9fbfb;">
                    <div class="small-muted">Fullname</div>
                    <div style="font-weight:800; margin-top:4px;"><?php echo e($user['full_name'] ?? '—'); ?></div>
                </div>

                <div class="panel" style="background:#f9fbfb;">
                    <div class="small-muted">Password</div>
                    <div style="font-weight:800; margin-top:4px; letter-spacing:0.1em; color:var(--muted);">••••••••</div>
                </div>

                <div class="panel" style="background:#f9fbfb;">
                    <div class="small-muted">Status</div>
                    <div style="font-weight:800; margin-top:4px;">
                        <span class="badge active"><?php echo e($user['status'] ?? 'Active'); ?></span>
                    </div>
                </div>

                <div class="panel" style="background:#f9fbfb;">
                    <div class="small-muted">Username</div>
                    <div style="font-weight:800; margin-top:4px;"><?php echo e($user['username'] ?? '—'); ?></div>
                </div>

                <div class="panel" style="background:#f9fbfb;">
                    <div class="small-muted">Contact Number</div>
                    <div style="font-weight:800; margin-top:4px;"><?php echo e($user['contact_number'] ?? '—'); ?></div>
                </div>

                <div class="panel" style="background:#f9fbfb;">
                    <div class="small-muted">Created At</div>
                    <div style="font-weight:800; margin-top:4px;"><?php echo !empty($user['created_at']) ? date('M d, Y', strtotime($user['created_at'])) : '—'; ?></div>
                </div>

                <div class="panel" style="background:#f9fbfb;">
                    <div class="small-muted">Email</div>
                    <div style="font-weight:800; margin-top:4px;"><?php echo e($user['email'] ?? '—'); ?></div>
                </div>

                <?php if (isset($user['address'])) { ?>
                    <div class="panel" style="background:#f9fbfb;">
                        <div class="small-muted">Address</div>
                        <div style="font-weight:800; margin-top:4px;"><?php echo e($user['address'] ?? '—'); ?></div>
                    </div>
                <?php } ?>

                <?php if ($role === 'farmer') { ?>
                    <div class="panel" style="background:#f9fbfb;">
                        <div class="small-muted">Permit Number</div>
                        <div style="font-weight:800; margin-top:4px;"><?php echo e($user['permit_number'] ?? '—'); ?></div>
                    </div>
                    <div class="panel" style="background:#f9fbfb;">
                        <div class="small-muted">Verification Status</div>
                        <div style="font-weight:800; margin-top:4px;">
                            <span class="badge <?php echo ($user['verification_status'] === 'Verified') ? 'active' : 'inactive'; ?>">
                                <?php echo e($user['verification_status'] ?? 'Pending'); ?>
                            </span>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <!-- Edit Button -->
            <div style="display:flex; justify-content:flex-end;">
                <button class="btn btn-primary" type="button" onclick="openModal('editProfileModal')">Edit Profile</button>
            </div>
        <?php } ?>
    </div>

    <!-- Full Notifications Button Card (Clean, matching Sign Out) -->
    <div class="section-card" style="margin-bottom:16px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div>
                <div style="font-weight:800; font-size:16px;">Notifications Center</div>
                <div class="small-muted">View your full message history, order alerts, and platform announcements</div>
            </div>
            <a class="btn btn-secondary" href="<?php echo BASE_URL; ?>/notifications/index.php">
                View All Notifications
            </a>
        </div>
    </div>

    <!-- Sign Out Card -->
    <div class="section-card">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div>
                <div style="font-weight:800; font-size:16px;">Sign Out</div>
                <div class="small-muted">End your active session securely</div>
            </div>
            <button type="button" class="btn btn-primary" style="background:#b42318; border-color:#b42318;" onclick="openGlobalLogoutModal()">
                Logout
            </button>
        </div>
    </div>
</main>

<!-- Edit Profile Modal -->
<div class="modal" id="editProfileModal" aria-hidden="true">
  <div class="modal-content" style="max-width:560px;">
    <div class="modal-header">
      <h2>Edit Profile Information</h2>
    </div>

    <form id="editProfileForm" method="POST" action="<?php echo BASE_URL; ?>/profile/actions/update_profile.php" enctype="multipart/form-data">
      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:14px;">
        <div class="form-group">
          <label>Full Name *</label>
          <input type="text" name="full_name" id="ep_fullname" value="<?php echo e($user['full_name'] ?? ''); ?>" data-orig="<?php echo e($user['full_name'] ?? ''); ?>" required>
        </div>
        <div class="form-group">
          <label>Username *</label>
          <input type="text" name="username" id="ep_username" value="<?php echo e($user['username'] ?? ''); ?>" data-orig="<?php echo e($user['username'] ?? ''); ?>" required>
        </div>
        <div class="form-group">
          <label>Email *</label>
          <input type="email" name="email" id="ep_email" value="<?php echo e($user['email'] ?? ''); ?>" data-orig="<?php echo e($user['email'] ?? ''); ?>" required>
        </div>
        <div class="form-group">
          <label>Contact Number</label>
          <input type="text" name="contact_number" id="ep_contact" value="<?php echo e($user['contact_number'] ?? ''); ?>" data-orig="<?php echo e($user['contact_number'] ?? ''); ?>">
        </div>
        <?php if (isset($user['address'])) { ?>
        <div class="form-group" style="grid-column:span 2;">
          <label>Address</label>
          <input type="text" name="address" id="ep_address" value="<?php echo e($user['address'] ?? ''); ?>" data-orig="<?php echo e($user['address'] ?? ''); ?>">
        </div>
        <?php } ?>
        <div class="form-group">
          <label>New Password <span class="small-muted">(leave blank to keep)</span></label>
          <input type="password" name="new_password" id="ep_newpass" placeholder="New password">
        </div>
        <div class="form-group">
          <label>Confirm Password</label>
          <input type="password" name="confirm_password" id="ep_confirmpass" placeholder="Confirm password">
        </div>

        <div class="form-group" style="grid-column:span 2;">
          <label>Profile Image (JPG or PNG, max 2MB)</label>
          <input type="file" name="profile_image" id="ep_profile_image" accept="image/jpeg,image/png">
        </div>
      </div>

      <div class="form-actions" style="justify-content:flex-end; margin-top:16px; gap:10px;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('editProfileModal')">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="verifyAndConfirmEdit()">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- No Changes Detected Modal -->
<div class="modal" id="noChangesModal" aria-hidden="true">
  <div class="modal-content" style="max-width:400px; text-align:center;">
    <div class="modal-header">
      <h2>No Changes Detected</h2>
      <button type="button" class="modal-close-x" onclick="closeModal('noChangesModal')" aria-label="Close">&times;</button>
    </div>
    <p style="margin:14px 0 20px 0; color:var(--muted); font-size:14px;">
      You haven't made any modifications to your profile information.
    </p>
    <div style="display:flex; justify-content:center;">
      <button type="button" class="btn btn-primary" onclick="closeModal('noChangesModal')">OK</button>
    </div>
  </div>
</div>

<!-- Confirmation Modal Before Save -->
<div class="modal" id="confirmProfileModal" aria-hidden="true">
  <div class="modal-content" style="max-width:420px; text-align:center;">
    <div class="modal-header">
      <h2>Confirm Profile Changes</h2>
      <button type="button" class="modal-close-x" onclick="closeModal('confirmProfileModal')" aria-label="Close">&times;</button>
    </div>
    <p style="margin:14px 0 20px 0; color:var(--muted); font-size:14px; line-height:1.5;">
      Are you sure you want to save these updates to your account?
    </p>
    <div style="display:flex; justify-content:center; gap:12px;">
      <button type="button" class="btn btn-secondary" onclick="closeModal('confirmProfileModal')">Cancel</button>
      <button type="button" class="btn btn-primary" onclick="submitEditProfileForm()">Yes, Save Updates</button>
    </div>
  </div>
</div>

<script>
function verifyAndConfirmEdit() {
  var fn = document.getElementById('ep_fullname');
  var un = document.getElementById('ep_username');
  var em = document.getElementById('ep_email');
  var cn = document.getElementById('ep_contact');
  var ad = document.getElementById('ep_address');
  var np = document.getElementById('ep_newpass');
  var img = document.getElementById('ep_profile_image');

  var isChanged = false;
  if (fn && fn.value !== fn.getAttribute('data-orig')) isChanged = true;
  if (un && un.value !== un.getAttribute('data-orig')) isChanged = true;
  if (em && em.value !== em.getAttribute('data-orig')) isChanged = true;
  if (cn && cn.value !== cn.getAttribute('data-orig')) isChanged = true;
  if (ad && ad.value !== ad.getAttribute('data-orig')) isChanged = true;
  if (np && np.value.trim() !== '') isChanged = true;
  if (img && img.files && img.files.length > 0) isChanged = true;

  if (!isChanged) {
    openModal('noChangesModal');
    return;
  }

  closeModal('editProfileModal');
  openModal('confirmProfileModal');
}

function submitEditProfileForm() {
  closeModal('confirmProfileModal');
  document.getElementById('editProfileForm').submit();
}
</script>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>