<?php
/**
 * SeaLink Web Application
 * File: /includes/modal_guest.php
 * Purpose: Reusable Guest Prompt Modal for features requiring authentication (Cart, Orders, Messages, Forum).
 */
?>
<!-- Guest Prompt Modal -->
<div class="modal" id="guestPromptModal" aria-hidden="true" onclick="if (event.target === this) closeGuestModal();">
    <div class="modal-content" style="max-width:440px; text-align:center; padding:24px; position:relative;">
        <button type="button" class="modal-close-x" onclick="closeGuestModal()" aria-label="Close" style="position:absolute; top:12px; right:12px; font-size:22px; line-height:1; background:none; border:none; cursor:pointer; color:var(--muted);">&times;</button>
        <div style="font-size:38px; margin-bottom:8px;"></div>
        <h2 id="guestModalTitle" style="margin:0 0 8px 0; font-size:20px;">Sign in Required</h2>
        <p id="guestModalDesc" style="color:var(--muted); font-size:14px; margin-bottom:20px; line-height:1.5;">
            Please sign in to access this feature, track your orders, and trade directly with Santa Fe aquatic producers.
        </p>
        <div style="display:flex; flex-direction:column; gap:10px;">
            <a id="guestModalLoginBtn" href="<?php echo BASE_URL; ?>/login.php" class="btn btn-primary btn-block">Sign In</a>
            <a href="<?php echo BASE_URL; ?>/register.php" class="btn btn-secondary btn-block">Register New Account</a>
        </div>
        <button type="button" onclick="closeGuestModal()"
          style="margin-top:14px; display:inline-block; font-size:13px; color:var(--muted); background:none; border:none; cursor:pointer; text-decoration:underline;">
            Continue Browsing
        </button>
    </div>
</div>

<script>
if (typeof openGuestPromptModal !== 'function') {
    function openGuestPromptModal(feature) {
        var title = document.getElementById('guestModalTitle');
        var desc = document.getElementById('guestModalDesc');
        var loginBtn = document.getElementById('guestModalLoginBtn');
        
        if (feature === 'Cart') {
            title.innerText = 'Sign in to View Cart';
            desc.innerText = 'Log in or register to add fresh aquatic products to your basket and proceed to checkout.';
            loginBtn.href = '<?php echo BASE_URL; ?>/login.php?redirect=' + encodeURIComponent('/buyer/cart.php');
        } else if (feature === 'Orders') {
            title.innerText = 'Track Your SeaLink Orders';
            desc.innerText = 'Sign in to monitor order confirmation, delivery progress, and rate your seafood purchases.';
            loginBtn.href = '<?php echo BASE_URL; ?>/login.php?redirect=' + encodeURIComponent('/buyer/orders.php');
        } else if (feature === 'Messages') {
            title.innerText = 'Message Aquatic Farmers';
            desc.innerText = 'Sign in to chat directly with Santa Fe fisherfolk and coordinate product inquiries.';
            loginBtn.href = '<?php echo BASE_URL; ?>/login.php?redirect=' + encodeURIComponent('/buyer/messages.php');
        } else if (feature === 'Forum') {
            title.innerText = 'Join Community Forum';
            desc.innerText = 'Sign in or register to participate in Santa Fe fisherfolk discussions, ask farming questions, and share aquaculture techniques.';
            loginBtn.href = '<?php echo BASE_URL; ?>/login.php?redirect=' + encodeURIComponent('/infohub/index.php?tab=forum');
        } else {
            title.innerText = 'Sign in Required';
            desc.innerText = 'Please sign in or create an account to continue.';
            loginBtn.href = '<?php echo BASE_URL; ?>/login.php';
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

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeGuestModal();
        }
    });
}
</script>
