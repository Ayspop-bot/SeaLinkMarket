<?php
/**
 * SeaLink Web Application
 * File: /admin/users.php
 * Purpose: Full Admin User Management with subtabs (All, Farmer, Buyer, Admin), detailed tables, Add Admin modal, and verification/status controls.
 * Uses: farmer_tbl, buyer_tbl, admin_tbl, product_tbl, order_tbl, buyer_address_tbl
 */

require_once __DIR__ . '/../includes/auth_check.php';
check_access(['User Admin', 'Content Admin']);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$active_tab = 'users';
$page_title = "User Management - SeaLink Admin";
require_once __DIR__ . '/../includes/layout_top.php';

$admin_role    = $_SESSION['user_role'] ?? '';
$is_user_admin = ($admin_role === 'User Admin');

$search_input  = trim((string)($_GET['search'] ?? ''));
$filter_role   = $_GET['role'] ?? 'All';
$filter_status = $_GET['status'] ?? 'All';

/* ========== FETCH FARMERS WITH STATS ========== */
$farmers = [];
$f_where = "1=1";
$f_params = [];
$f_types = "";
if ($search_input !== '') {
    $f_where .= " AND (f.username LIKE CONCAT('%', ?, '%') OR f.full_name LIKE CONCAT('%', ?, '%') OR f.email LIKE CONCAT('%', ?, '%') OR f.permit_number LIKE CONCAT('%', ?, '%'))";
    $f_params[] = $search_input;
    $f_params[] = $search_input;
    $f_params[] = $search_input;
    $f_params[] = $search_input;
    $f_types .= "ssss";
}
if ($filter_status !== 'All') {
    $f_where .= " AND f.status = ?";
    $f_params[] = $filter_status;
    $f_types .= "s";
}

$f_sql = "
SELECT 
    f.*,
    COALESCE(p.prod_count, 0) AS total_products
FROM farmer_tbl f
LEFT JOIN (
    SELECT farmer_id, COUNT(*) AS prod_count FROM product_tbl GROUP BY farmer_id
) p ON p.farmer_id = f.farmer_id
WHERE {$f_where}
ORDER BY f.created_at DESC
";
$stmt = mysqli_prepare($conn, $f_sql);
if (!empty($f_params)) {
    $bind = [$stmt, $f_types];
    foreach ($f_params as $k => $v) $bind[] = &$f_params[$k];
    call_user_func_array('mysqli_stmt_bind_param', $bind);
}
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) $farmers[] = $row;
mysqli_stmt_close($stmt);

/* ========== FETCH BUYERS WITH STATS ========== */
$buyers = [];
$b_where = "1=1";
$b_params = [];
$b_types = "";
if ($search_input !== '') {
    $b_where .= " AND (b.username LIKE CONCAT('%', ?, '%') OR b.full_name LIKE CONCAT('%', ?, '%') OR b.email LIKE CONCAT('%', ?, '%'))";
    $b_params[] = $search_input;
    $b_params[] = $search_input;
    $b_params[] = $search_input;
    $b_types .= "sss";
}
if ($filter_status !== 'All') {
    $b_where .= " AND b.status = ?";
    $b_params[] = $filter_status;
    $b_types .= "s";
}

$b_sql = "
SELECT 
    b.*,
    COALESCE(o.order_count, 0) AS total_orders,
    CONCAT(addr.street, ', ', addr.municipality, ', ', addr.province) AS default_address
FROM buyer_tbl b
LEFT JOIN (
    SELECT buyer_id, COUNT(*) AS order_count FROM order_tbl GROUP BY buyer_id
) o ON o.buyer_id = b.buyer_id
LEFT JOIN buyer_address_tbl addr ON addr.buyer_id = b.buyer_id AND addr.is_default = 1
WHERE {$b_where}
ORDER BY b.created_at DESC
";
$stmt = mysqli_prepare($conn, $b_sql);
if (!empty($b_params)) {
    $bind = [$stmt, $b_types];
    foreach ($b_params as $k => $v) $bind[] = &$b_params[$k];
    call_user_func_array('mysqli_stmt_bind_param', $bind);
}
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) $buyers[] = $row;
mysqli_stmt_close($stmt);

/* ========== FETCH ADMINS ========== */
$admins = [];
$a_where = "1=1";
$a_params = [];
$a_types = "";
if ($search_input !== '') {
    $a_where .= " AND (username LIKE CONCAT('%', ?, '%') OR full_name LIKE CONCAT('%', ?, '%') OR email LIKE CONCAT('%', ?, '%'))";
    $a_params[] = $search_input;
    $a_params[] = $search_input;
    $a_params[] = $search_input;
    $a_types .= "sss";
}
if ($filter_status !== 'All') {
    $a_where .= " AND status = ?";
    $a_params[] = $filter_status;
    $a_types .= "s";
}

$a_sql = "SELECT * FROM admin_tbl WHERE {$a_where} ORDER BY created_at DESC";
$stmt = mysqli_prepare($conn, $a_sql);
if (!empty($a_params)) {
    $bind = [$stmt, $a_types];
    foreach ($a_params as $k => $v) $bind[] = &$a_params[$k];
    call_user_func_array('mysqli_stmt_bind_param', $bind);
}
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) $admins[] = $row;
mysqli_stmt_close($stmt);

mysqli_close($conn);

$role_pills = [
    'All'    => 'All Users',
    'Farmer' => 'Farmers (' . count($farmers) . ')',
    'Buyer'  => 'Buyers (' . count($buyers) . ')',
    'Admin'  => 'Administrators (' . count($admins) . ')'
];
?>

<main class="dashboard-content">
    <!-- Header Section Card -->
    <div class="section-card" style="margin-bottom:16px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
            <div>
                <h1 style="margin:0; font-size:24px;">User Management</h1>
                <div class="small-muted" style="margin-top:4px;">
                    Monitor, verify, and manage registered Farmers, Buyers, and Platform Administrators
                </div>
            </div>

            <!-- Top Right Add Admin Button -->
            <?php if ($is_user_admin) { ?>
                <button type="button" class="btn btn-primary" onclick="openModal('addAdminModal')">
                    Add Admin
                </button>
            <?php } ?>
        </div>
    </div>

    <!-- Filter / Search Toolbar (Role Dropdown, Status Dropdown, and Search Bar matching products.php) -->
    <div class="market-toolbar" style="margin-bottom:16px;">
        <form method="GET" action="" class="market-filters" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; width:100%;">
            <select name="role" onchange="this.form.submit()">
                <option value="All" <?php echo ($filter_role === 'All') ? 'selected' : ''; ?>>All Users</option>
                <option value="Farmer" <?php echo ($filter_role === 'Farmer') ? 'selected' : ''; ?>>Farmers (<?php echo count($farmers); ?>)</option>
                <option value="Buyer" <?php echo ($filter_role === 'Buyer') ? 'selected' : ''; ?>>Buyers (<?php echo count($buyers); ?>)</option>
                <option value="Admin" <?php echo ($filter_role === 'Admin') ? 'selected' : ''; ?>>Administrators (<?php echo count($admins); ?>)</option>
            </select>

            <select name="status" onchange="this.form.submit()">
                <option value="All" <?php echo ($filter_status === 'All') ? 'selected' : ''; ?>>All Status</option>
                <option value="Active" <?php echo ($filter_status === 'Active') ? 'selected' : ''; ?>>Active</option>
                <option value="Suspended" <?php echo ($filter_status === 'Suspended') ? 'selected' : ''; ?>>Suspended</option>
            </select>

            <div class="search-input-wrapper">
                <button type="button" class="search-clear-x" onclick="clearSearchAndRefresh(this)" title="Clear and refresh search" aria-label="Clear search" <?php echo empty($search_input) ? 'style="display:none;"' : 'style="display:flex;"'; ?>>&times;</button>
                <input type="text" name="search" placeholder="Search name, username, email, or permit number..."
                       value="<?php echo e($search_input); ?>" oninput="checkSearchClear(this)">
            </div>

            <button type="submit" class="btn btn-primary btn-sm">Search</button>
        </form>
    </div>

    <!-- 1. FARMERS TABLE -->
    <?php if ($filter_role === 'All' || $filter_role === 'Farmer') { ?>
        <div class="section-card" style="margin-bottom:16px;">
            <div style="font-weight:800; font-size:16px; margin-bottom:14px; color:var(--text);">
                Registered Farmers (<?php echo count($farmers); ?>)
            </div>

            <?php if (empty($farmers)) { ?>
                <div class="small-muted" style="padding:20px; text-align:center;">No farmers found.</div>
            <?php } else { ?>
                <div class="table-wrap">
                    <table class="table" style="font-size:13px;">
                        <thead>
                            <tr>
                                <th>Profile &amp; Name</th>
                                <th>Email</th>
                                <th>Contact Number</th>
                                <th>Permit No.</th>
                                <th>Address</th>
                                <th style="text-align:center;">Products</th>
                                <th>Registered</th>
                                <th>Verification</th>
                                <th>Status</th>
                                <?php if ($is_user_admin) { ?><th style="text-align:center; min-width:220px; white-space:nowrap;">Actions</th><?php } ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($farmers as $f) { 
                                $is_active = ($f['status'] === 'Active');
                                $is_ver = ($f['verification_status'] === 'Verified');
                            ?>
                                <tr>
                                    <td>
                                        <div style="display:flex; align-items:center; gap:8px; cursor:pointer;"
                                             onclick="showUserProfile('<?php echo e(addslashes($f['full_name'])); ?>', '<?php echo e(addslashes($f['username'])); ?>', 'Farmer', '<?php echo e(addslashes($f['email'])); ?>', '<?php echo e(addslashes($f['contact_number'] ?? '')); ?>', '<?php echo e(addslashes($f['address'] ?? '')); ?>', '<?php echo !empty($f['profile_image']) ? BASE_URL . '/' . e($f['profile_image']) : ''; ?>', '<?php echo e($f['verification_status']); ?>', '<?php echo e($f['status']); ?>', '<?php echo date('M d, Y', strtotime($f['created_at'])); ?>')">
                                            <div style="width:34px; height:34px; border-radius:50%; overflow:hidden; background:#eee; flex-shrink:0;">
                                                <?php if (!empty($f['profile_image'])) { ?>
                                                    <img src="<?php echo BASE_URL . '/' . e($f['profile_image']); ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                                                <?php } else { ?>
                                                    <div style="display:flex;align-items:center;justify-content:center;height:100%;"><svg width="18" height="18" viewBox="0 0 24 24" fill="#aaa"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg></div>
                                                <?php } ?>
                                            </div>
                                            <div>
                                                <strong style="color:var(--primary); text-decoration:underline;"><?php echo e($f['username']); ?></strong>
                                                <div class="small-muted" style="font-size:11px;"><?php echo e($f['full_name']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo e($f['email']); ?></td>
                                    <td><?php echo e($f['contact_number'] ?? '—'); ?></td>
                                    <td><strong><?php echo e($f['permit_number'] ?? '—'); ?></strong></td>
                                    <td><?php echo e($f['address'] ?? 'Santa Fe'); ?></td>
                                    <td style="text-align:center; font-weight:700;"><?php echo (int)$f['total_products']; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($f['created_at'])); ?></td>
                                    <td>
                                        <span class="badge <?php echo $is_ver ? 'active' : 'inactive'; ?>">
                                            <?php echo e($f['verification_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $is_active ? 'active' : 'inactive'; ?>">
                                            <?php echo e($f['status']); ?>
                                        </span>
                                    </td>
                                    <?php if ($is_user_admin) { ?>
                                        <td style="text-align:center; white-space:nowrap;">
                                            <div style="display:inline-flex; gap:6px; align-items:center; flex-wrap:nowrap; justify-content:center;">
                                                <?php if (!$is_ver) { ?>
                                                    <form method="POST" action="<?php echo BASE_URL; ?>/admin/actions/verify_farmer.php" style="display:inline; margin:0;">
                                                        <input type="hidden" name="farmer_id" value="<?php echo (int)$f['farmer_id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-primary">Verify</button>
                                                    </form>
                                                <?php } ?>
                                                <form method="POST" action="<?php echo BASE_URL; ?>/admin/actions/user_status.php" style="display:inline; margin:0;">
                                                    <input type="hidden" name="user_id" value="<?php echo (int)$f['farmer_id']; ?>">
                                                    <input type="hidden" name="type" value="farmer">
                                                    <button type="submit" class="btn btn-sm <?php echo $is_active ? 'btn-outline-primary' : 'btn-primary'; ?>">
                                                        <?php echo $is_active ? 'Suspend' : 'Activate'; ?>
                                                    </button>
                                                </form>
                                                <form method="POST" action="<?php echo BASE_URL; ?>/admin/actions/user_delete.php" style="display:inline; margin:0;"
                                                      onsubmit="return confirm('Permanently delete this farmer account?');">
                                                    <input type="hidden" name="user_id" value="<?php echo (int)$f['farmer_id']; ?>">
                                                    <input type="hidden" name="type" value="farmer">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    <?php } ?>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </div>
    <?php } ?>

    <!-- 2. BUYERS TABLE -->
    <?php if ($filter_role === 'All' || $filter_role === 'Buyer') { ?>
        <div class="section-card" style="margin-bottom:16px;">
            <div style="font-weight:800; font-size:16px; margin-bottom:14px; color:var(--text);">
                Registered Buyers (<?php echo count($buyers); ?>)
            </div>

            <?php if (empty($buyers)) { ?>
                <div class="small-muted" style="padding:20px; text-align:center;">No buyers found.</div>
            <?php } else { ?>
                <div class="table-wrap">
                    <table class="table" style="font-size:13px;">
                        <thead>
                            <tr>
                                <th>Profile &amp; Name</th>
                                <th>Email</th>
                                <th>Contact Number</th>
                                <th>Facebook Account</th>
                                <th>Default Address</th>
                                <th style="text-align:center;">Total Orders</th>
                                <th>Registered</th>
                                <th>Status</th>
                                <?php if ($is_user_admin) { ?><th style="text-align:center; min-width:180px; white-space:nowrap;">Actions</th><?php } ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($buyers as $b) { 
                                $is_active = ($b['status'] === 'Active');
                            ?>
                                <tr>
                                    <td>
                                        <div style="display:flex; align-items:center; gap:8px; cursor:pointer;"
                                             onclick="showUserProfile('<?php echo e(addslashes($b['full_name'])); ?>', '<?php echo e(addslashes($b['username'])); ?>', 'Buyer', '<?php echo e(addslashes($b['email'])); ?>', '<?php echo e(addslashes($b['contact_number'] ?? '')); ?>', '<?php echo e(addslashes($b['default_address'] ?? '')); ?>', '<?php echo !empty($b['profile_image']) ? BASE_URL . '/' . e($b['profile_image']) : ''; ?>', 'N/A', '<?php echo e($b['status']); ?>', '<?php echo date('M d, Y', strtotime($b['created_at'])); ?>')">
                                            <div style="width:34px; height:34px; border-radius:50%; overflow:hidden; background:#eee; flex-shrink:0;">
                                                <?php if (!empty($b['profile_image'])) { ?>
                                                    <img src="<?php echo BASE_URL . '/' . e($b['profile_image']); ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                                                <?php } else { ?>
                                                    <div style="display:flex;align-items:center;justify-content:center;height:100%;"><svg width="18" height="18" viewBox="0 0 24 24" fill="#aaa"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg></div>
                                                <?php } ?>
                                            </div>
                                            <div>
                                                <strong style="color:var(--primary); text-decoration:underline;"><?php echo e($b['full_name']); ?></strong>
                                                <div class="small-muted" style="font-size:11px;">@<?php echo e($b['username']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo e($b['email']); ?></td>
                                    <td><?php echo e($b['contact_number'] ?? '—'); ?></td>
                                    <td><?php echo e($b['facebook_account'] ?? '—'); ?></td>
                                    <td><?php echo e($b['default_address'] ?? 'Santa Fe'); ?></td>
                                    <td style="text-align:center; font-weight:700;"><?php echo (int)$b['total_orders']; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($b['created_at'])); ?></td>
                                    <td>
                                        <span class="badge <?php echo $is_active ? 'active' : 'inactive'; ?>">
                                            <?php echo e($b['status']); ?>
                                        </span>
                                    </td>
                                    <?php if ($is_user_admin) { ?>
                                        <td style="text-align:center; white-space:nowrap;">
                                            <div style="display:inline-flex; gap:6px; align-items:center; flex-wrap:nowrap; justify-content:center;">
                                                <form method="POST" action="<?php echo BASE_URL; ?>/admin/actions/user_status.php" style="display:inline; margin:0;">
                                                    <input type="hidden" name="user_id" value="<?php echo (int)$b['buyer_id']; ?>">
                                                    <input type="hidden" name="type" value="buyer">
                                                    <button type="submit" class="btn btn-sm <?php echo $is_active ? 'btn-outline-primary' : 'btn-primary'; ?>">
                                                        <?php echo $is_active ? 'Suspend' : 'Activate'; ?>
                                                    </button>
                                                </form>
                                                <form method="POST" action="<?php echo BASE_URL; ?>/admin/actions/user_delete.php" style="display:inline; margin:0;"
                                                      onsubmit="return confirm('Permanently delete this buyer account?');">
                                                    <input type="hidden" name="user_id" value="<?php echo (int)$b['buyer_id']; ?>">
                                                    <input type="hidden" name="type" value="buyer">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    <?php } ?>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </div>
    <?php } ?>

    <!-- 3. ADMINISTRATORS TABLE -->
    <?php if ($filter_role === 'All' || $filter_role === 'Admin') { ?>
        <div class="section-card">
            <div style="font-weight:800; font-size:16px; margin-bottom:14px; color:var(--text);">
                Platform Administrators (<?php echo count($admins); ?>)
            </div>

            <?php if (empty($admins)) { ?>
                <div class="small-muted" style="padding:20px; text-align:center;">No administrators found.</div>
            <?php } else { ?>
                <div class="table-wrap">
                    <table class="table" style="font-size:13px;">
                        <thead>
                            <tr>
                                <th>Profile &amp; Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Contact Number</th>
                                <th>Admin Role</th>
                                <th>Created At</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($admins as $ad) { ?>
                                <tr>
                                    <td>
                                        <div style="display:flex; align-items:center; gap:8px;">
                                            <div style="width:34px; height:34px; border-radius:50%; overflow:hidden; background:#eee; flex-shrink:0;">
                                                <?php if (!empty($ad['profile_image'])) { ?>
                                                    <img src="<?php echo BASE_URL . '/' . e($ad['profile_image']); ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                                                <?php } else { ?>
                                                    <div style="display:flex;align-items:center;justify-content:center;height:100%;"><svg width="18" height="18" viewBox="0 0 24 24" fill="#aaa"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg></div>
                                                <?php } ?>
                                            </div>
                                            <strong><?php echo e($ad['full_name']); ?></strong>
                                        </div>
                                    </td>
                                    <td>@<?php echo e($ad['username']); ?></td>
                                    <td><?php echo e($ad['email']); ?></td>
                                    <td><?php echo e($ad['contact_number'] ?? '—'); ?></td>
                                    <td>
                                        <span class="badge active" style="font-weight:700;">
                                            <?php echo e($ad['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($ad['created_at'])); ?></td>
                                    <td><span class="badge active"><?php echo e($ad['status']); ?></span></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </div>
    <?php } ?>
</main>

<!-- ADD ADMIN MODAL -->
<div class="modal" id="addAdminModal" aria-hidden="true">
  <div class="modal-content" style="max-width:540px;">
    <div class="modal-header">
      <h2>Register Administrator</h2>
    </div>

    <form id="addAdminForm" method="POST" action="<?php echo BASE_URL; ?>/admin/actions/admin_add.php">
      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
        <div class="form-group">
          <label>Full Name *</label>
          <input type="text" name="full_name" required placeholder="e.g. Juan Dela Cruz">
        </div>
        <div class="form-group">
          <label>Username *</label>
          <input type="text" name="username" required placeholder="e.g. juandc">
        </div>
        <div class="form-group">
          <label>Email *</label>
          <input type="email" name="email" required placeholder="e.g. admin@sealink.com">
        </div>
        <div class="form-group">
          <label>Contact Number</label>
          <input type="text" name="contact_number" placeholder="e.g. +63 900 000 0000">
        </div>
        <div class="form-group" style="grid-column:span 2;">
          <label>Admin Role *</label>
          <select name="admin_role" required>
            <option value="Content Admin">Content Admin (Manages Marketplace &amp; Information Hub)</option>
            <option value="User Admin">User Admin (Manages Accounts, Verifications &amp; Analytics)</option>
          </select>
        </div>
        <div class="form-group">
          <label>Password *</label>
          <input type="password" name="password" required placeholder="Min. 6 characters">
        </div>
        <div class="form-group">
          <label>Confirm Password *</label>
          <input type="password" name="confirm_password" required placeholder="Re-enter password">
        </div>
      </div>

      <div class="form-actions" style="justify-content:flex-end; margin-top:16px;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('addAdminModal')">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="openModal('confirmAdminAddModal')">Create Admin Account</button>
      </div>
    </form>
  </div>
</div>

<!-- CONFIRM ADD ADMIN MODAL -->
<div class="modal" id="confirmAdminAddModal" aria-hidden="true">
  <div class="modal-content" style="max-width:420px; text-align:center;">
    <div class="modal-header">
      <h2>Confirm Admin Creation</h2>
      <button type="button" class="modal-close-x" onclick="closeModal('confirmAdminAddModal')" aria-label="Close">&times;</button>
    </div>
    <p style="margin:14px 0 20px 0; color:var(--muted); font-size:14px; line-height:1.5;">
      Are you sure you want to create this new administrator account with administrative privileges?
    </p>
    <div style="display:flex; justify-content:center; gap:12px;">
      <button type="button" class="btn btn-secondary" onclick="closeModal('confirmAdminAddModal')">Cancel</button>
      <button type="button" class="btn btn-primary" onclick="submitAddAdminForm()">Yes, Create Admin</button>
    </div>
  </div>
</div>

<!-- USER PROFILE DETAILS MODAL -->
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
function submitAddAdminForm() {
    closeModal('confirmAdminAddModal');
    document.getElementById('addAdminForm').submit();
}

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
