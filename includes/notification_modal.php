<?php
/**
 * SeaLink Web Application
 * File: /includes/notification_modal.php
 * Purpose: Notification popup modal (X close button) and Global Logout Confirmation Modal.
 * Connected To: /assets/js/base.js openNotificationModal()
 */
?>
<!-- Notification Details Modal -->
<div class="modal" id="notificationModal" aria-hidden="true">
  <div class="modal-content" style="max-width:480px;">
    <div class="modal-header">
      <h2 id="notifModalTitle">Notification Details</h2>
      <button type="button" class="modal-close-x" aria-label="Close"
        onclick="closeModal('notificationModal')">&times;</button>
    </div>

    <div class="small-muted" id="notifModalMeta" style="margin-top:2px;"></div>
    <div style="margin-top:14px; padding:12px 14px; background:#f9fbfb; border:1px solid var(--border); border-radius:8px; font-size:14px; line-height:1.55; white-space:pre-wrap;" id="notifModalBody"></div>
  </div>
</div>

<!-- Global Logout Confirmation Modal -->
<div class="modal" id="globalLogoutModal" aria-hidden="true">
  <div class="modal-content" style="max-width:420px; text-align:center;">
    <div class="modal-header">
      <h2>Sign Out Confirmation</h2>
      <button type="button" class="modal-close-x" aria-label="Close"
        onclick="closeModal('globalLogoutModal')">&times;</button>
    </div>

    <p style="margin:14px 0 20px 0; color:var(--muted); font-size:14px; line-height:1.5;">
      Are you sure you want to end your current session and sign out of SeaLink?
    </p>

    <div style="display:flex; justify-content:center; gap:12px;">
      <button type="button" class="btn btn-secondary" onclick="closeModal('globalLogoutModal')">Cancel</button>
      <a href="<?php echo BASE_URL; ?>/logout.php" class="btn btn-primary" style="background:#b42318; border-color:#b42318;">Yes, Logout</a>
    </div>
  </div>
</div>

<script>
function toggleProfileDropdown(e) {
  if (e) e.stopPropagation();
  var menu = document.getElementById('profileDropdownMenu');
  var btn  = document.getElementById('profileIconBtn');
  if (!menu) return;

  var isOpen = menu.classList.contains('show');
  if (isOpen) {
    menu.classList.remove('show');
    if (btn) btn.classList.remove('is-active');
  } else {
    menu.classList.add('show');
    if (btn) btn.classList.add('is-active');
  }
}

function openGlobalLogoutModal() {
  var menu = document.getElementById('profileDropdownMenu');
  var btn  = document.getElementById('profileIconBtn');
  if (menu) menu.classList.remove('show');
  if (btn) btn.classList.remove('is-active');
  openModal('globalLogoutModal');
}

// Close dropdown on outside click
document.addEventListener('click', function(e) {
  var container = document.getElementById('profileDropdownContainer');
  var menu = document.getElementById('profileDropdownMenu');
  var btn = document.getElementById('profileIconBtn');
  if (container && !container.contains(e.target)) {
    if (menu) menu.classList.remove('show');
    if (btn) btn.classList.remove('is-active');
  }
});
</script>