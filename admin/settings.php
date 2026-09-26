<?php
/**
 * SeaLink Web Application
 * File: /admin/settings.php
 * Purpose: Site settings for admin with sub-tabs for General Settings and Promote Settings (User Admin only).
 * Connected To:
 * - /admin/actions/save_settings.php
 * - /admin/actions/save_promote_settings.php
 * Uses: site_settings_tbl, promote_settings_tbl
 */

require_once __DIR__ . '/../includes/auth_check.php';
check_access(['Content Admin', 'User Admin']);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$current_role = $_SESSION['user_role'] ?? '';
$is_user_admin = ($current_role === 'User Admin');

// Sub-tab selection: 'general' vs 'promote'
$sub_tab = isset($_GET['tab']) && $_GET['tab'] === 'promote' ? 'promote' : 'general';

if ($sub_tab === 'promote' && !$is_user_admin) {
    set_message('error', 'Access restricted: Only User Administrators can configure Payment & Promotion Settings.');
    redirect_path('/admin/settings.php?tab=general');
}

$active_tab = 'settings';
$page_title = ($sub_tab === 'promote') ? "Promote Settings - SeaLink" : "General Settings - SeaLink";
require_once __DIR__ . '/../includes/layout_top.php';

// Handle General Settings data
if ($sub_tab === 'general') {
    mysqli_query($conn, "
        CREATE TABLE IF NOT EXISTS site_settings_tbl (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT NOT NULL DEFAULT '',
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $defaults = [
        'site_name'      => 'SeaLink',
        'support_email'  => 'sealink@support.com',
        'office_address' => 'Santa Fe, Romblon, Philippines',
        'contact_number' => '+63 900 000 0000',
        'description'    => 'SeaLink is an integrated web-based platform that combines a marketplace and an information hub connecting aquatic farmers and buyers in Santa Fe, Romblon.',
        'drop_off_points'=> "Marketplace, Poblacion Santa Fe\nSanta Fe Municipal Port\nBarangay Taboboan Drop Point",
        'site_logo'      => '',
    ];

    foreach ($defaults as $key => $val) {
        $stmt = mysqli_prepare($conn, "INSERT IGNORE INTO site_settings_tbl (setting_key, setting_value) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, "ss", $key, $val);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    $settings = $defaults;
    $res = mysqli_query($conn, "SELECT setting_key, setting_value FROM site_settings_tbl");
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
} else {
    // Handle Promote Settings data
    $promo_settings = get_promote_settings($conn);
    $promo_packages = get_promotion_packages($conn, false);
}

mysqli_close($conn);
?>

<main class="dashboard-content">
    <!-- Sub-tab Navigation Pill Buttons -->
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:18px;">
        <a href="<?php echo BASE_URL; ?>/admin/settings.php?tab=general" 
           class="btn <?php echo ($sub_tab === 'general') ? 'btn-primary' : 'btn-secondary'; ?>"
           style="font-weight:800; font-size:14px; text-decoration:none; padding:10px 18px; border-radius:10px; display:inline-flex; align-items:center; gap:8px;">
            <span></span> General Settings
        </a>
        
        <?php if ($is_user_admin) { ?>
        <a href="<?php echo BASE_URL; ?>/admin/settings.php?tab=promote" 
           class="btn <?php echo ($sub_tab === 'promote') ? 'btn-primary' : 'btn-secondary'; ?>"
           style="font-weight:800; font-size:14px; text-decoration:none; padding:10px 18px; border-radius:10px; display:inline-flex; align-items:center; gap:8px;">
            <span></span> Promote Settings
        </a>
        <?php } ?>
    </div>

    <?php if ($sub_tab === 'general') { ?>
        <!-- ==================== GENERAL SETTINGS ==================== -->
        <div class="section-card" style="margin-bottom:16px;">
            <h1 style="margin:0; font-size:24px;">General Settings</h1>
            <div class="small-muted" style="margin-top:4px;">Manage platform branding, contact channels, and organization credentials</div>
        </div>

        <!-- Administrative Approval Notice Alert -->
        <div class="alert alert-warning" style="margin-bottom:16px;">
            <strong>⚠️ Administrative Notice:</strong> Any changes made to General Site Settings will affect public contact information across SeaLink and should be coordinated and approved by both User Admin and Content Admin.
        </div>

        <!-- General Settings Form Panel -->
        <div class="section-card">
            <form id="settingsForm" method="POST"
                  action="<?php echo BASE_URL; ?>/admin/actions/save_settings.php"
                  enctype="multipart/form-data">

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                    <!-- Left column -->
                    <div style="display:flex; flex-direction:column; gap:16px;">
                        <div class="form-group">
                            <label>Site Name *</label>
                            <input type="text" name="site_name" value="<?php echo e($settings['site_name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Support Email *</label>
                            <input type="email" name="support_email" value="<?php echo e($settings['support_email']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Office Address *</label>
                            <input type="text" name="office_address" value="<?php echo e($settings['office_address']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Contact Number *</label>
                            <input type="text" name="contact_number" value="<?php echo e($settings['contact_number']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Platform Description</label>
                            <textarea name="description" rows="4" style="resize:vertical;"><?php echo e($settings['description']); ?></textarea>
                        </div>
                    </div>

                    <!-- Right column: Upload Image box -->
                    <div style="display:flex; flex-direction:column; gap:16px;">
                        <label style="font-size:14px; font-weight:700; color:var(--text);">Platform Logo / Banner</label>

                        <div id="imagePreviewBox"
                             style="width:100%; height:200px; border:2px dashed var(--border); border-radius:12px; display:flex; align-items:center; justify-content:center; overflow:hidden; background:#f9fbfb; cursor:pointer;"
                             onclick="document.getElementById('siteLogoInput').click();">
                            <?php if (!empty($settings['site_logo'])) { ?>
                                <img src="<?php echo BASE_URL . '/' . e($settings['site_logo']); ?>"
                                     alt="Site Logo" id="imagePreviewImg"
                                     style="max-width:100%; max-height:100%; object-fit:contain;">
                            <?php } else { ?>
                                <div id="imagePreviewPlaceholder" style="text-align:center; color:var(--muted);">
                                    <svg width="42" height="42" viewBox="0 0 24 24" fill="none" style="margin-bottom:8px;">
                                        <path fill="#aaa" d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>
                                    </svg>
                                    <div style="font-size:13px; font-weight:700;">Click to Upload Logo</div>
                                </div>
                                <img src="" alt="" id="imagePreviewImg" style="display:none; max-width:100%; max-height:100%; object-fit:contain;">
                            <?php } ?>
                        </div>

                        <input type="file" name="site_logo" id="siteLogoInput" accept="image/*" style="display:none;"
                               onchange="previewSiteLogo(this)">
                        <div class="small-muted" style="font-size:12px;">Recommended dimensions: 400×200px (PNG, JPG, or WebP).</div>
                    </div>
                </div>

                <!-- Drop-off Points Configuration (Editable by User Admin) -->
                <div class="form-group" style="margin-top:20px; padding-top:16px; border-top:1px solid var(--border);">
                    <label style="font-weight:800; font-size:14.5px; display:flex; align-items:center; gap:6px;">
                        <span>📍</span> Available Drop-off Locations (One location per line) *
                    </label>
                    <div class="small-muted" style="margin-bottom:8px; font-size:12.5px;">
                        These designated drop-off points in Santa Fe will be presented to buyers whenever they select "Drop-off Point" during checkout.
                    </div>
                    <textarea name="drop_off_points" rows="4" style="resize:vertical; width:100%; font-family:inherit; font-size:13.5px; padding:10px 12px; border:1px solid var(--border); border-radius:8px;" required><?php echo e($settings['drop_off_points'] ?? "Marketplace, Poblacion Santa Fe\nSanta Fe Municipal Port\nBarangay Taboboan Drop Point"); ?></textarea>
                </div>

                <!-- Bottom Save Button with Confirmation Trigger -->
                <div style="display:flex; justify-content:flex-end; margin-top:24px; padding-top:16px; border-top:1px solid var(--border);">
                    <button type="button" class="btn btn-primary" onclick="openModal('confirmSettingsModal')">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>

        <!-- Confirmation Modal Before Saving General Settings -->
        <div class="modal" id="confirmSettingsModal" aria-hidden="true">
          <div class="modal-content" style="max-width:440px; text-align:center;">
            <div class="modal-header">
              <h2>Confirm Settings Update</h2>
              <button type="button" class="modal-close-x" onclick="closeModal('confirmSettingsModal')" aria-label="Close">&times;</button>
            </div>

            <p style="margin:14px 0 20px 0; color:var(--muted); font-size:14px; line-height:1.5;">
              Are you sure you want to save these changes to the general platform settings?
            </p>

            <div style="display:flex; justify-content:center; gap:12px;">
              <button type="button" class="btn btn-secondary" onclick="closeModal('confirmSettingsModal')">Cancel</button>
              <button type="button" class="btn btn-primary" onclick="submitSettingsForm()">Yes, Save Settings</button>
            </div>
          </div>
        </div>

    <?php } else { ?>
        <!-- ==================== PROMOTE SETTINGS ==================== -->
        <div class="section-card" style="margin-bottom:16px;">
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="background:#eff6ff; color:#007dfe; width:44px; height:44px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:22px; border:1px solid #bfdbfe;">
                    💳
                </div>
                <div>
                    <h1 style="margin:0; font-size:24px;">Promote & Payment Settings</h1>
                    <div class="small-muted" style="margin-top:4px;">Configure platform GCash receiving details and pricing rates for farmer product promotions</div>
                </div>
            </div>
        </div>

        <!-- Promote Settings Form Panel: GCash Payment Receiving Details -->
        <div class="section-card" style="margin-bottom:24px;">
            <div style="font-weight:800; font-size:16px; margin-bottom:14px; color:var(--text); display:flex; align-items:center; gap:8px;">
                <span style="background:#007dfe; color:#fff; border-radius:6px; padding:3px 8px; font-size:11px; font-weight:900;">GCASH</span>
                <span>Platform Payment Receiving Credentials</span>
            </div>

            <form id="promoteSettingsForm" method="POST"
                  action="<?php echo BASE_URL; ?>/admin/actions/save_promote_settings.php"
                  enctype="multipart/form-data">

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:24px;">
                    <!-- Left column: GCash Details -->
                    <div style="display:flex; flex-direction:column; gap:16px;">
                        <div class="form-group">
                            <label style="font-weight:700; font-size:13px;">Platform GCash Account Name *</label>
                            <input type="text" name="gcash_name" value="<?php echo e($promo_settings['gcash_name']); ?>" 
                                   placeholder="e.g. SeaLink Admin" required
                                   style="width:100%; font-size:14.5px; padding:10px 12px; border-radius:8px;">
                            <div class="small-muted" style="font-size:11.5px; margin-top:4px;">Displayed to farmers as the verified account holder name.</div>
                        </div>

                        <div class="form-group">
                            <label style="font-weight:700; font-size:13px;">Platform GCash Number *</label>
                            <input type="text" name="gcash_number" value="<?php echo e($promo_settings['gcash_number']); ?>" 
                                   placeholder="e.g. 09123456789" required
                                   style="width:100%; font-size:15px; font-weight:700; font-family:monospace; padding:10px 12px; border-radius:8px;">
                            <div class="small-muted" style="font-size:11.5px; margin-top:4px;">Farmers transfer boost fees to this mobile number.</div>
                        </div>
                    </div>

                    <!-- Right column: Upload GCash QR Code -->
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <label style="font-size:14px; font-weight:700; color:var(--text);">Platform GCash QR Code</label>

                        <div id="qrPreviewBox"
                             style="width:100%; min-height:180px; max-height:220px; border:2px dashed #93c5fd; border-radius:12px; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:12px; background:#f0f9ff; cursor:pointer; text-align:center;"
                             onclick="document.getElementById('gcashQrInput').click();">
                            
                            <?php if (!empty($promo_settings['gcash_qr_image'])) { ?>
                                <img src="<?php echo BASE_URL . '/' . e($promo_settings['gcash_qr_image']); ?>"
                                     alt="GCash QR Code" id="qrPreviewImg"
                                     style="max-width:100%; max-height:160px; object-fit:contain; border-radius:8px; border:1px solid #bfdbfe; box-shadow:0 4px 10px rgba(0,0,0,0.06);">
                                <div id="qrPlaceholder" style="display:none; text-align:center; color:var(--muted);">
                                    <div style="font-size:32px; margin-bottom:4px;">📱</div>
                                    <div style="font-size:13px; font-weight:700;">Click to Upload QR Code</div>
                                </div>
                                <div class="small-muted" style="font-size:11.5px; font-weight:700; color:#0284c7; margin-top:6px;">
                                    Click box to replace current QR Code
                                </div>
                            <?php } else { ?>
                                <div id="qrPlaceholder" style="text-align:center; color:#0369a1;">
                                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:6px;">
                                        <rect x="3" y="3" width="7" height="7"></rect>
                                        <rect x="14" y="3" width="7" height="7"></rect>
                                        <rect x="14" y="14" width="7" height="7"></rect>
                                        <rect x="3" y="14" width="7" height="7"></rect>
                                        <line x1="7" y1="7" x2="7.01" y2="7"></line>
                                        <line x1="17" y1="7" x2="17.01" y2="7"></line>
                                        <line x1="17" y1="17" x2="17.01" y2="17"></line>
                                        <line x1="7" y1="17" x2="7.01" y2="17"></line>
                                    </svg>
                                    <div style="font-size:13px; font-weight:700;">Click to Upload GCash QR Code</div>
                                    <div style="font-size:11px; color:#64748b; margin-top:2px;">Upload screenshot of official GCash QR</div>
                                </div>
                                <img src="" alt="" id="qrPreviewImg" style="display:none; max-width:100%; max-height:160px; object-fit:contain; border-radius:8px; border:1px solid #bfdbfe; box-shadow:0 4px 10px rgba(0,0,0,0.06);">
                            <?php } ?>
                        </div>

                        <input type="file" name="gcash_qr_image" id="gcashQrInput" accept="image/*" style="display:none;"
                               onchange="previewGcashQr(this)">
                        <div class="small-muted" style="font-size:11.5px;">Supported formats: PNG, JPG, WebP (Max 5MB).</div>
                    </div>
                </div>

                <!-- Save GCash Button -->
                <div style="display:flex; justify-content:flex-end; margin-top:20px; padding-top:14px; border-top:1px solid var(--border);">
                    <button type="button" class="btn btn-primary" onclick="openModal('confirmPromoteSettingsModal')"
                            style="font-weight:800; padding:9px 20px; font-size:13.5px;">
                        Save GCash Details
                    </button>
                </div>
            </form>
        </div>

        <!-- ==================== PROMOTION PACKAGES CRUD SECTION ==================== -->
        <div class="section-card">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
                <div>
                    <h2 style="margin:0; font-size:18px; color:var(--text); display:flex; align-items:center; gap:8px;">
                        <span>Manage Promotion Packages</span>
                    </h2>
                    <div class="small-muted" style="margin-top:2px;">
                        Create, edit, and toggle active boost packages available for farmers in real time.
                    </div>
                </div>
                <button type="button" class="btn btn-primary btn-sm" onclick="openModal('addPackageModal')" 
                        style="font-weight:800; padding:8px 16px; border-radius:8px; display:inline-flex; align-items:center; gap:6px;">
                    <span>+</span> <span>Add New Package</span>
                </button>
            </div>

            <div class="table-wrap">
                <table class="table" style="font-size:13.5px;">
                    <thead>
                        <tr>
                            <th>Package Name</th>
                            <th>Duration</th>
                            <th>Price</th>
                            <th style="text-align:center;">Status</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($promo_packages)) { ?>
                            <tr>
                                <td colspan="5" style="text-align:center; padding:28px; color:var(--muted);">
                                    <div style="font-size:24px; margin-bottom:6px;"></div>
                                    <strong>No promotion packages configured.</strong>
                                    <div class="small-muted" style="margin-top:4px;">Click "+ Add New Package" to create the first promotion option.</div>
                                </td>
                            </tr>
                        <?php } else { ?>
                            <?php foreach ($promo_packages as $pkg) { ?>
                                <tr>
                                    <td>
                                        <strong style="color:var(--text); font-size:14px;"><?php echo e($pkg['package_name']); ?></strong>
                                    </td>
                                    <td>
                                        <span style="background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; padding:3px 8px; border-radius:6px; font-weight:700; font-size:12px; display:inline-flex; align-items:center; gap:4px;">
                                            <span><?php echo (int)$pkg['duration_days']; ?> <?php echo $pkg['duration_days'] == 1 ? 'Day' : 'Days'; ?></span>
                                        </span>
                                    </td>
                                    <td>
                                        <strong style="color:#059669; font-size:15px;">₱<?php echo number_format($pkg['price'], 2); ?></strong>
                                    </td>
                                    <td style="text-align:center;">
                                        <?php if ($pkg['is_active'] == 1) { ?>
                                            <span style="background:#ecfdf5; color:#059669; border:1px solid #a7f3d0; padding:3px 10px; border-radius:999px; font-weight:800; font-size:11.5px; display:inline-flex; align-items:center; gap:4px;">
                                                <span>●</span> <span>Active</span>
                                            </span>
                                        <?php } else { ?>
                                            <span style="background:#f1f5f9; color:#64748b; border:1px solid #cbd5e1; padding:3px 10px; border-radius:999px; font-weight:800; font-size:11.5px; display:inline-flex; align-items:center; gap:4px;">
                                                <span>○</span> <span>Inactive</span>
                                            </span>
                                        <?php } ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <div style="display:inline-flex; align-items:center; gap:6px; flex-wrap:wrap; justify-content:center;">
                                            <!-- Toggle Active/Inactive Form -->
                                            <form method="POST" action="<?php echo BASE_URL; ?>/admin/actions/package_toggle.php" style="margin:0;">
                                                <input type="hidden" name="package_id" value="<?php echo $pkg['id']; ?>">
                                                <button type="submit" class="btn btn-sm <?php echo $pkg['is_active'] == 1 ? 'btn-secondary' : 'btn-outline-primary'; ?>" 
                                                        style="padding:4px 10px; font-size:12px; font-weight:700;">
                                                    <?php echo $pkg['is_active'] == 1 ? 'Deactivate' : 'Activate'; ?>
                                                </button>
                                            </form>

                                            <!-- Edit Button -->
                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                    style="padding:4px 10px; font-size:12px; font-weight:700;"
                                                    data-id="<?php echo $pkg['id']; ?>"
                                                    data-name="<?php echo e($pkg['package_name']); ?>"
                                                    data-days="<?php echo (int)$pkg['duration_days']; ?>"
                                                    data-price="<?php echo e(number_format($pkg['price'], 2, '.', '')); ?>"
                                                    onclick="openEditPackageModal(this)">
                                                Edit
                                            </button>

                                            <!-- Delete Button -->
                                            <form method="POST" action="<?php echo BASE_URL; ?>/admin/actions/package_delete.php" style="margin:0;"
                                                  onsubmit="return confirm('Are you sure you want to delete this promotion package?');">
                                                <input type="hidden" name="package_id" value="<?php echo $pkg['id']; ?>">
                                                <button type="submit" class="btn btn-sm" 
                                                        style="padding:4px 8px; font-size:12px; font-weight:700; color:#dc2626; background:#fef2f2; border:1px solid #fecaca; border-radius:6px;">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Confirmation Modal Before Saving GCash Details -->
        <div class="modal" id="confirmPromoteSettingsModal" aria-hidden="true">
          <div class="modal-content" style="max-width:440px; text-align:center;">
            <div class="modal-header">
              <h2>Confirm Payment Settings Update</h2>
              <button type="button" class="modal-close-x" onclick="closeModal('confirmPromoteSettingsModal')" aria-label="Close">&times;</button>
            </div>

            <p style="margin:14px 0 20px 0; color:var(--muted); font-size:14px; line-height:1.5;">
              Are you sure you want to update the platform GCash payment details? Farmers will immediately see the updated information when scanning or copying.
            </p>

            <div style="display:flex; justify-content:center; gap:12px;">
              <button type="button" class="btn btn-secondary" onclick="closeModal('confirmPromoteSettingsModal')">Cancel</button>
              <button type="button" class="btn btn-primary" onclick="submitPromoteSettingsForm()">Yes, Save Details</button>
            </div>
          </div>
        </div>

        <!-- ADD PACKAGE MODAL -->
        <div class="modal" id="addPackageModal" aria-hidden="true">
          <div class="modal-content" style="max-width:480px;">
            <div class="modal-header">
              <h2 style="margin:0; font-size:18px;">Add New Promotion Package</h2>
              <button type="button" class="modal-close-x" onclick="closeModal('addPackageModal')" aria-label="Close">&times;</button>
            </div>

            <form method="POST" action="<?php echo BASE_URL; ?>/admin/actions/package_add.php" style="margin-top:16px;">
              <div class="form-group" style="margin-bottom:14px;">
                <label style="font-weight:700; font-size:13px;">Package Name *</label>
                <input type="text" name="package_name" placeholder="e.g. 1-Day Flash Promo, 3-Day Featured Catch" required
                       style="width:100%; font-size:14px; padding:10px 12px; border-radius:8px;">
                <div class="small-muted" style="font-size:11.5px; margin-top:4px;">Descriptive name shown to farmers in their promotion wizard.</div>
              </div>

              <div style="display:grid; grid-template-columns: 1fr 1fr; gap:14px; margin-bottom:18px;">
                <div class="form-group">
                  <label style="font-weight:700; font-size:13px;">Duration (Days) *</label>
                  <input type="number" min="1" max="365" name="duration_days" value="3" required
                         style="width:100%; font-size:14.5px; font-weight:800; padding:10px 12px; border-radius:8px;">
                  <div class="small-muted" style="font-size:11px; margin-top:4px;">Number of days boosted.</div>
                </div>

                <div class="form-group">
                  <label style="font-weight:700; font-size:13px;">Price (₱) *</label>
                  <input type="number" step="0.01" min="0" name="price" value="50.00" required
                         style="width:100%; font-size:14.5px; font-weight:800; padding:10px 12px; border-radius:8px;">
                  <div class="small-muted" style="font-size:11px; margin-top:4px;">Amount charged via GCash.</div>
                </div>
              </div>

              <div style="display:flex; justify-content:flex-end; gap:10px; border-top:1px solid var(--border); padding-top:14px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addPackageModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="font-weight:800; padding:9px 20px;">Save Package</button>
              </div>
            </form>
          </div>
        </div>

        <!-- EDIT PACKAGE MODAL -->
        <div class="modal" id="editPackageModal" aria-hidden="true">
          <div class="modal-content" style="max-width:480px;">
            <div class="modal-header">
              <h2 style="margin:0; font-size:18px;">Edit Promotion Package</h2>
              <button type="button" class="modal-close-x" onclick="closeModal('editPackageModal')" aria-label="Close">&times;</button>
            </div>

            <form method="POST" action="<?php echo BASE_URL; ?>/admin/actions/package_edit.php" style="margin-top:16px;">
              <input type="hidden" name="package_id" id="edit_pkg_id">

              <div class="form-group" style="margin-bottom:14px;">
                <label style="font-weight:700; font-size:13px;">Package Name *</label>
                <input type="text" name="package_name" id="edit_pkg_name" required
                       style="width:100%; font-size:14px; padding:10px 12px; border-radius:8px;">
              </div>

              <div style="display:grid; grid-template-columns: 1fr 1fr; gap:14px; margin-bottom:18px;">
                <div class="form-group">
                  <label style="font-weight:700; font-size:13px;">Duration (Days) *</label>
                  <input type="number" min="1" max="365" name="duration_days" id="edit_pkg_days" required
                         style="width:100%; font-size:14.5px; font-weight:800; padding:10px 12px; border-radius:8px;">
                </div>

                <div class="form-group">
                  <label style="font-weight:700; font-size:13px;">Price (₱) *</label>
                  <input type="number" step="0.01" min="0" name="price" id="edit_pkg_price" required
                         style="width:100%; font-size:14.5px; font-weight:800; padding:10px 12px; border-radius:8px;">
                </div>
              </div>

              <div style="display:flex; justify-content:flex-end; gap:10px; border-top:1px solid var(--border); padding-top:14px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editPackageModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="font-weight:800; padding:9px 20px;">Update Package</button>
              </div>
            </form>
          </div>
        </div>
    <?php } ?>
</main>

<script>
function previewSiteLogo(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var img = document.getElementById('imagePreviewImg');
            var placeholder = document.getElementById('imagePreviewPlaceholder');
            if (img) {
                img.src = e.target.result;
                img.style.display = 'block';
            }
            if (placeholder) placeholder.style.display = 'none';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function submitSettingsForm() {
    closeModal('confirmSettingsModal');
    document.getElementById('settingsForm').submit();
}

function previewGcashQr(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var img = document.getElementById('qrPreviewImg');
            var placeholder = document.getElementById('qrPlaceholder');
            if (img) {
                img.src = e.target.result;
                img.style.display = 'block';
            }
            if (placeholder) placeholder.style.display = 'none';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function submitPromoteSettingsForm() {
    closeModal('confirmPromoteSettingsModal');
    document.getElementById('promoteSettingsForm').submit();
}

function openEditPackageModal(btn) {
    document.getElementById('edit_pkg_id').value = btn.dataset.id;
    document.getElementById('edit_pkg_name').value = btn.dataset.name;
    document.getElementById('edit_pkg_days').value = btn.dataset.days;
    document.getElementById('edit_pkg_price').value = btn.dataset.price;
    openModal('editPackageModal');
}
</script>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
