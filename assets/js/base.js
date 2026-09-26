/**
 * SeaLink Web Application
 * File: /assets/js/base.js
 * Purpose: Toast auto-hide + modal helpers + product modal fill + notification modal/read tracking.
 */

/* ========== TOAST AUTOHIDE ========== */
document.addEventListener('DOMContentLoaded', function () {
  var toast = document.getElementById('toastMessage');
  if (!toast) return;

  setTimeout(function () { toast.classList.add('hide'); }, 2500);
  setTimeout(function () {
    if (toast && toast.parentNode) toast.parentNode.removeChild(toast);
  }, 3000);
});

/* ========== MODAL HELPERS ========== */
function openModal(id) {
  var el = document.getElementById(id);
  if (el) {
    el.style.display = 'flex';
    el.classList.add('open');
  }
}
function closeModal(id) {
  var el = document.getElementById(id);
  if (el) {
    el.style.display = 'none';
    el.classList.remove('open');
  }
}

/* ========== FARMER: PRODUCT MODAL HELPERS (KEEP WORKING) ========== */
function openEditProduct(btn) {
  if (!btn || btn.disabled) return;

  var m = document.getElementById('editProductModal');
  if (!m) return;

  document.getElementById('edit_product_id').value = btn.dataset.id || '';
  document.getElementById('edit_name').value = btn.dataset.name || '';
  document.getElementById('edit_category_id').value = btn.dataset.categoryId || '';
  document.getElementById('edit_description').value = btn.dataset.description || '';
  document.getElementById('edit_price').value = btn.dataset.price || '';
  document.getElementById('edit_stock').value = btn.dataset.stock || '';
  document.getElementById('edit_status').value = btn.dataset.status || 'Active';

  openModal('editProductModal');
}

function openDeleteProduct(btn) {
  if (!btn || btn.disabled) return;

  document.getElementById('delete_product_id').value = btn.dataset.id || '';
  document.getElementById('delete_product_name').textContent = btn.dataset.name || '';
  openModal('deleteProductModal');
}
/* =========================================================
Notifications: Modal + Read Tracking (Phase 2)
Storage: localStorage (per browser)
========================================================= */
function getNotifContextKey() {
  var ctx = document.getElementById('notifContext');
  if (!ctx) return null;

  var uid = ctx.dataset.userId || '';
  var role = ctx.dataset.role || '';
  if (!uid || !role) return null;

  return 'sealink_read_notifs_' + role + '_' + uid;
}

function getReadNotifsSet() {
  var key = getNotifContextKey();
  if (!key) return new Set();
  try {
    var raw = localStorage.getItem(key);
    var arr = raw ? JSON.parse(raw) : [];
    return new Set(arr);
  } catch (e) { return new Set(); }
}

function saveReadNotifsSet(setObj) {
  var key = getNotifContextKey();
  if (!key) return;
  try { localStorage.setItem(key, JSON.stringify(Array.from(setObj))); } catch (e) {}
}

function applyReadStyles() {
  var readSet = getReadNotifsSet();
  document.querySelectorAll('.notif-item[data-notif-id]').forEach(function (el) {
    var id = el.dataset.notifId || '';
    if (id && readSet.has(id)) el.classList.add('is-read');
  });
}

document.addEventListener('DOMContentLoaded', function () {
  applyReadStyles();
});

function openNotificationModal(btn) {
  if (!btn) return;

  var id = btn.dataset.notifId || '';
  var type = btn.dataset.notifType || 'Notification';
  var title = btn.dataset.notifTitle || 'Notification';
  var body = btn.dataset.notifBody || '';
  var ts = btn.dataset.notifTs || '';

  var t = document.getElementById('notifModalTitle');
  var m = document.getElementById('notifModalMeta');
  var b = document.getElementById('notifModalBody');

  if (t) t.textContent = type + ' • ' + title;
  if (m) m.textContent = ts ? ('Time: ' + ts) : '';
  if (b) b.textContent = body;

  if (id) {
    var readSet = getReadNotifsSet();
    readSet.add(id);
    saveReadNotifsSet(readSet);
    btn.classList.add('is-read');
  }

  openModal('notificationModal');
}

// Optional Phase 3: if toast appears, scroll to top (prevents "pull up" confusion)
if (!window.location.hash) {
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

/* ========== Market auto-submit category (Phase 3) ========== */
document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('marketFilterForm');
  var cat = document.getElementById('marketCategory');
  if (!form || !cat) return;

  cat.addEventListener('change', function () {
    form.submit();
  });
});

/* ========== Search Clear 'X' and Refresh Helper ========== */
function checkSearchClear(input) {
  if (!input) return;
  var wrapper = input.closest('.search-input-wrapper') || input.parentElement;
  var btn = wrapper ? wrapper.querySelector('.search-clear-x') : null;
  if (btn) {
    var hasText = input.value && input.value.trim().length > 0;
    btn.style.display = hasText ? 'flex' : 'none';
  }
}

function clearSearchAndRefresh(btn) {
  var wrapper = btn.closest('.search-input-wrapper') || btn.parentElement;
  var input = wrapper ? wrapper.querySelector('input') : null;
  if (input) {
    input.value = '';
  }
  btn.style.display = 'none';
  var form = btn.closest('form');
  if (form) {
    form.submit();
  } else {
    var url = new URL(window.location.href);
    url.searchParams.delete('search');
    url.searchParams.delete('q');
    window.location.href = url.toString();
  }
}

document.addEventListener('DOMContentLoaded', function () {
  var searchWrappers = document.querySelectorAll('.search-input-wrapper');
  searchWrappers.forEach(function (w) {
    var inp = w.querySelector('input');
    if (inp) {
      checkSearchClear(inp);
      inp.addEventListener('input', function () { checkSearchClear(inp); });
      inp.addEventListener('keyup', function () { checkSearchClear(inp); });
      inp.addEventListener('change', function () { checkSearchClear(inp); });
    }
  });
});

/* ========== GUEST PROMPT MODAL HELPER ========== */
function openGuestPromptModal(feature) {
  var title = document.getElementById('guestModalTitle');
  var desc = document.getElementById('guestModalDesc');
  var loginBtn = document.getElementById('guestModalLoginBtn');
  var baseUrl = window.location.origin + (window.location.pathname.indexOf('/SealinkWeb') !== -1 ? '/SealinkWeb' : '');

  if (title && desc && loginBtn) {
    if (feature === 'Cart') {
      title.innerText = 'Sign in to View Cart';
      desc.innerText = 'Log in or register to add fresh aquatic products to your basket and proceed to checkout.';
      loginBtn.href = baseUrl + '/login.php?redirect=' + encodeURIComponent('/buyer/cart.php');
    } else if (feature === 'Orders') {
      title.innerText = 'Track Your SeaLink Orders';
      desc.innerText = 'Sign in to monitor order confirmation, delivery progress, and rate your seafood purchases.';
      loginBtn.href = baseUrl + '/login.php?redirect=' + encodeURIComponent('/buyer/orders.php');
    } else if (feature === 'Messages') {
      title.innerText = 'Message Aquatic Farmers';
      desc.innerText = 'Sign in to chat directly with Santa Fe fisherfolk and coordinate product inquiries.';
      loginBtn.href = baseUrl + '/login.php?redirect=' + encodeURIComponent('/buyer/messages.php');
    } else if (feature === 'Forum') {
      title.innerText = 'Join Community Forum';
      desc.innerText = 'Sign in or register to participate in Santa Fe fisherfolk discussions, ask farming questions, and share aquaculture techniques.';
      loginBtn.href = baseUrl + '/login.php?redirect=' + encodeURIComponent('/infohub/index.php?tab=forum');
    } else {
      title.innerText = 'Sign in Required';
      desc.innerText = 'Please sign in or create an account to continue.';
      loginBtn.href = baseUrl + '/login.php';
    }
  }

  var modal = document.getElementById('guestPromptModal');
  if (modal) {
    modal.classList.add('open');
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden', 'false');
  }
}

function closeGuestModal() {
  var modal = document.getElementById('guestPromptModal');
  if (modal) {
    modal.classList.remove('open');
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
  }
}