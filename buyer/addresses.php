<?php
/**
 * SeaLink Web Application
 * File: /buyer/addresses.php
 * Purpose: Manage buyer delivery addresses with duplicate check, change detection, and confirmation modals.
 * Connected To:
 * - /profile/index.php (Back button target)
 * - /buyer/actions/address_save.php
 * - /buyer/actions/address_default.php
 * - /buyer/actions/address_delete.php
 * Uses: buyer_address_tbl
 */

require_once __DIR__ . '/../includes/auth_check.php';
check_access('buyer');

$hide_nav = true;
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$active_tab = '';
$page_title = "My Delivery Addresses - SeaLink";
require_once __DIR__ . '/../includes/layout_top.php';

$buyer_id = (int)$_SESSION['user_id'];

// Return to previous screen (supporting referrer or return query param)
$return_param = trim((string)($_GET['return'] ?? ''));
if ($return_param !== '' && ($return_param[0] !== '/' || preg_match('/^\s*https?:/i', $return_param))) {
    $return_param = '';
}
$back_url = $return_param ? (BASE_URL . $return_param) : (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST']) !== false ? $_SERVER['HTTP_REFERER'] : BASE_URL . '/profile/index.php');

$stmt = mysqli_prepare($conn, "
    SELECT address_id, street, municipality, province, zip_code, is_default
    FROM buyer_address_tbl
    WHERE buyer_id = ?
    ORDER BY is_default DESC, address_id DESC
");
mysqli_stmt_bind_param($stmt, "i", $buyer_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$addresses = [];
while ($row = mysqli_fetch_assoc($res)) {
    $addresses[] = $row;
}
mysqli_stmt_close($stmt);
mysqli_close($conn);
?>

<main class="dashboard-content buyer-dashboard">
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:20px;">
        <a class="back-arrow" href="<?php echo e($back_url); ?>" onclick="if (document.referrer && document.referrer.indexOf(window.location.host) !== -1) { history.back(); return false; }" aria-label="Back">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path fill="currentColor" d="M15.5 19 8.5 12l7-7 1.5 1.5L11.5 12l5.5 5.5z"/>
            </svg>
        </a>
        <h1 style="margin:0; font-size:24px;">My Delivery Addresses</h1>
    </div>

    <!-- Saved Addresses Container -->
    <div class="section-card" style="background:#fff; border:1px solid var(--border); border-radius:14px; padding:20px; box-shadow:0 2px 8px rgba(0,0,0,0.02);">
        <!-- Container Header with + Add New Address on the right side -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; border-bottom:1px solid var(--border); padding-bottom:12px; flex-wrap:wrap; gap:10px;">
            <div>
                <h2 style="margin:0; font-size:17px; font-weight:800; color:var(--text);">Saved Addresses</h2>
                <div class="small-muted" style="margin-top:2px;">
                    <?php echo count($addresses); ?> address<?php echo count($addresses) !== 1 ? 'es' : ''; ?> recorded
                </div>
            </div>
            <button type="button" class="btn btn-primary" onclick="openAddAddressModal()">
                + Add New Address
            </button>
        </div>

        <?php if (empty($addresses)) { ?>
            <div style="text-align:center; padding:36px 20px;">
                <div style="font-size:36px; margin-bottom:10px;"></div>
                <h3 style="font-size:17px; margin:0 0 6px 0;">No delivery addresses saved yet</h3>
                <p class="small-muted" style="margin:0;">Save your delivery address in Santa Fe or neighboring barangays for quick order checkout.</p>
            </div>
        <?php } else { ?>
            <div style="display:flex; flex-direction:column; gap:12px;">
                <?php foreach ($addresses as $addr) { ?>
                    <div class="panel" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; border:1px solid var(--border); background:#f8fafc; border-radius:12px; padding:14px 16px; margin:0;">
                        <div>
                            <div style="font-weight:800; font-size:15.5px; display:flex; align-items:center; gap:8px;">
                                <?php echo e($addr['street']); ?>
                                <?php if ($addr['is_default']) { ?>
                                    <span class="badge active" style="font-size:11px;">Default Address</span>
                                <?php } ?>
                            </div>
                            <div class="small-muted" style="margin-top:4px;">
                                <?php echo e($addr['municipality']); ?>, <?php echo e($addr['province']); ?> <?php echo e($addr['zip_code']); ?>
                            </div>
                        </div>

                        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    onclick="openEditAddressModal(<?php echo (int)$addr['address_id']; ?>, '<?php echo e(addslashes($addr['street'])); ?>', '<?php echo e(addslashes($addr['municipality'])); ?>', '<?php echo e(addslashes($addr['province'])); ?>', '<?php echo e(addslashes($addr['zip_code'])); ?>')">
                                Edit
                            </button>

                            <button type="button" class="btn btn-sm btn-outline-danger"
                                    onclick="openDeleteAddressModal(<?php echo (int)$addr['address_id']; ?>, '<?php echo e(addslashes($addr['street'] . ', ' . $addr['municipality'])); ?>')">
                                Delete
                            </button>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
</main>

<!-- Add / Edit Address Modal -->
<div class="modal" id="addressModal" aria-hidden="true">
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header">
            <h2 id="addressModalTitle">Add New Address</h2>
        </div>
        <form method="POST" action="<?php echo BASE_URL; ?>/buyer/actions/address_save.php" id="addressForm" onsubmit="return handleAddressFormSubmit(event)">
            <input type="hidden" name="address_id" id="modal_address_id" value="0">

            <div class="form-group">
                <label>Street / Barangay *</label>
                <input type="text" name="street" id="modal_street" required placeholder="e.g. Barangay Poblacion, Purok 2">
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                <div class="form-group">
                    <label>Municipality *</label>
                    <input type="text" name="municipality" id="modal_municipality" value="Santa Fe" required>
                </div>
                <div class="form-group">
                    <label>Province *</label>
                    <input type="text" name="province" id="modal_province" value="Romblon" required>
                </div>
            </div>

            <div class="form-group">
                <label>Zip Code</label>
                <input type="text" name="zip_code" id="modal_zip_code" value="5505">
            </div>

            <div class="form-group" style="display:flex; align-items:center; gap:8px; margin-top:8px;">
                <input type="checkbox" name="is_default" id="modal_is_default" value="1" style="width:auto; cursor:pointer;">
                <label for="modal_is_default" style="margin-bottom:0; font-weight:normal; cursor:pointer;">Set as default delivery address</label>
            </div>

            <div class="form-actions" style="justify-content:flex-end; margin-top:16px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addressModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="saveAddressBtn">Save Address</button>
            </div>
        </form>
    </div>
</div>

<!-- SAVE CONFIRMATION MODAL (English) -->
<div class="modal" id="addressConfirmModal" aria-hidden="true">
  <div class="modal-content" style="max-width:440px;">
    <div class="modal-header">
      <h2>Confirm Address</h2>
    </div>
    <p style="margin-top:10px;">
      Do you want to save this delivery address for your orders?
    </p>
    <div class="form-actions" style="justify-content:flex-end; margin-top:16px;">
      <button type="button" class="btn btn-secondary" onclick="closeModal('addressConfirmModal')">Cancel</button>
      <button type="button" class="btn btn-primary" onclick="proceedSaveAddress()">Yes, Save Address</button>
    </div>
  </div>
</div>

<!-- DUPLICATE ADDRESS WARNING MODAL (English) -->
<div class="modal" id="duplicateAddressModal" aria-hidden="true">
  <div class="modal-content" style="max-width:440px; text-align:center; padding:24px;">
    <div style="font-size:36px; margin-bottom:8px;"></div>
    <h2 style="font-size:18px; margin:0 0 8px 0; color:#b42318;">Address Already Exists</h2>
    <p class="small-muted" style="margin:0 0 16px 0;">
      This delivery address is already recorded in your saved addresses list.
    </p>
    <button type="button" class="btn btn-primary" onclick="closeModal('duplicateAddressModal')">Understood</button>
  </div>
</div>

<!-- NO CHANGES DETECTED MODAL (English) -->
<div class="modal" id="noAddressChangesModal" aria-hidden="true">
  <div class="modal-content" style="max-width:420px; text-align:center; padding:24px;">
    <div style="font-size:36px; margin-bottom:8px;"></div>
    <h2 style="font-size:18px; margin:0 0 8px 0;">No Changes Detected</h2>
    <p class="small-muted" style="margin:0 0 16px 0;">
      No changes were detected in the address details. Please modify at least one field (street, municipality, province, or zip code) to update.
    </p>
    <button type="button" class="btn btn-primary" onclick="closeModal('noAddressChangesModal')">OK, Got It</button>
  </div>
</div>

<!-- DELETE ADDRESS CONFIRMATION MODAL (English) -->
<div class="modal" id="deleteAddressModal" aria-hidden="true">
  <div class="modal-content" style="max-width:460px; border:1px solid #ffcccc;">
    <div class="modal-header" style="border-bottom:1px solid #fee2e2; padding-bottom:10px;">
      <h2 style="color:#b42318;">Delete Address</h2>
    </div>
    <form method="POST" action="<?php echo BASE_URL; ?>/buyer/actions/address_delete.php">
      <input type="hidden" name="address_id" id="delete_modal_address_id" required>
      <div style="padding:14px 0;">
        <p style="margin:0 0 6px 0;">
          Are you sure you want to delete this delivery address:
        </p>
        <strong id="delete_modal_address_name" style="color:var(--text); font-size:15px;"></strong>
      </div>
      <div class="form-actions" style="justify-content:flex-end; border-top:1px solid #fee2e2; padding-top:12px;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('deleteAddressModal')">Cancel</button>
        <button type="submit" class="btn btn-outline-danger" style="background:#b42318; color:#fff;">Confirm Delete</button>
      </div>
    </form>
  </div>
</div>

<script>
var existingAddressesList = <?php echo json_encode(array_map(function($a) {
    return [
        'id' => (int)$a['address_id'],
        'street' => strtolower(trim($a['street'])),
        'municipality' => strtolower(trim($a['municipality'])),
        'province' => strtolower(trim($a['province'])),
        'zip' => strtolower(trim($a['zip_code'] ?? ''))
    ];
}, $addresses)); ?>;

var editAddrOriginal = null;

function openAddAddressModal() {
    document.getElementById('addressModalTitle').textContent = 'Add New Delivery Address';
    document.getElementById('modal_address_id').value = '0';
    document.getElementById('modal_street').value = '';
    document.getElementById('modal_municipality').value = 'Santa Fe';
    document.getElementById('modal_province').value = 'Romblon';
    document.getElementById('modal_zip_code').value = '5505';
    document.getElementById('modal_is_default').checked = false;
    editAddrOriginal = null;
    openModal('addressModal');
}

function openEditAddressModal(id, street, muni, prov, zip) {
    document.getElementById('addressModalTitle').textContent = 'Edit Delivery Address';
    document.getElementById('modal_address_id').value = id;
    document.getElementById('modal_street').value = street;
    document.getElementById('modal_municipality').value = muni;
    document.getElementById('modal_province').value = prov;
    document.getElementById('modal_zip_code').value = zip;
    document.getElementById('modal_is_default').checked = false;

    // Track original values across every field
    editAddrOriginal = {
        id: id,
        street: street.trim(),
        municipality: muni.trim(),
        province: prov.trim(),
        zip: zip.trim()
    };

    openModal('addressModal');
}

function handleAddressFormSubmit(e) {
    e.preventDefault();
    var id = parseInt(document.getElementById('modal_address_id').value) || 0;
    var street = document.getElementById('modal_street').value.trim();
    var muni = document.getElementById('modal_municipality').value.trim();
    var prov = document.getElementById('modal_province').value.trim();
    var zip = document.getElementById('modal_zip_code').value.trim();

    // Validate required fields
    if (!street || !muni || !prov) {
        alert('Please fill in all required fields (Street, Municipality, Province).');
        return false;
    }

    // On edit, check every single field: street, municipality, province, zip code
    if (id > 0 && editAddrOriginal) {
        var isStreetUnchanged = (street.toLowerCase() === editAddrOriginal.street.toLowerCase());
        var isMuniUnchanged   = (muni.toLowerCase() === editAddrOriginal.municipality.toLowerCase());
        var isProvUnchanged   = (prov.toLowerCase() === editAddrOriginal.province.toLowerCase());
        var isZipUnchanged    = (zip.toLowerCase() === editAddrOriginal.zip.toLowerCase());

        var isCompletelyUnchanged = isStreetUnchanged && isMuniUnchanged && isProvUnchanged && isZipUnchanged;

        if (isCompletelyUnchanged) {
            // Show English designated modal when no changes at all
            openModal('noAddressChangesModal');
            return false;
        }
    }

    // Check for duplicate address among other saved addresses
    var isDuplicate = existingAddressesList.some(function(item) {
        return (item.id !== id &&
                item.street === street.toLowerCase() &&
                item.municipality === muni.toLowerCase() &&
                item.province === prov.toLowerCase());
    });

    if (isDuplicate) {
        openModal('duplicateAddressModal');
        return false;
    }

    // Show English confirmation modal when there are changes
    openModal('addressConfirmModal');
    return false;
}

function proceedSaveAddress() {
    closeModal('addressConfirmModal');
    document.getElementById('addressForm').submit();
}

function openDeleteAddressModal(id, addrText) {
    document.getElementById('delete_modal_address_id').value = id;
    document.getElementById('delete_modal_address_name').textContent = addrText;
    openModal('deleteAddressModal');
}
</script>

<?php require_once __DIR__ . '/../includes/layout_bottom.php'; ?>
