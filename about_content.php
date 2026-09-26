<?php
/**
 * SeaLink Web Application
 * File: /_about_content.php
 * Purpose: Reusable About Us content block styled with SeaLink clean design system.
 * Connected To: /about.php
 */

$about_address = 'Santa Fe, Romblon, Philippines';
$about_email   = 'sealink@support.com';
$about_contact = '+63 900 000 0000';

if (isset($conn) && $conn) {
    $has_settings = mysqli_query($conn, "SHOW TABLES LIKE 'site_settings_tbl'");
    if ($has_settings && mysqli_num_rows($has_settings) > 0) {
        $settings_res = mysqli_query($conn, "SELECT setting_key, setting_value FROM site_settings_tbl");
        if ($settings_res) {
            while ($s = mysqli_fetch_assoc($settings_res)) {
                if ($s['setting_key'] === 'office_address')   $about_address = $s['setting_value'];
                if ($s['setting_key'] === 'support_email')    $about_email   = $s['setting_value'];
                if ($s['setting_key'] === 'contact_number')   $about_contact = $s['setting_value'];
            }
        }
    }
}
?>

<div style="max-width:920px; margin:0 auto;">
    <!-- Section Heading -->
    <div class="section-card" style="margin-top:0; text-align:center; padding:24px 20px;">
        <h1 style="font-size:26px; font-weight:900; color:var(--primary); margin:0 0 6px 0;">About SeaLink</h1>
        <div class="small-muted">Connecting Aquatic Farmers and Buyers in Santa Fe, Romblon</div>
    </div>

    <!-- Hero Banner Card -->
    <div class="section-card" style="padding:0; overflow:hidden; border-radius:14px; position:relative; background:linear-gradient(135deg, #6495ED 0%, #4682B4 50%, #1B6CA8 100%); min-height:180px; display:flex; align-items:center; justify-content:center; color:#FFFFFF; text-align:center; padding:36px 24px; border:1px solid #4682B4; box-shadow:0 6px 20px rgba(100, 149, 237, 0.35);">
        <div>
            <h2 style="font-family:var(--font-primary); font-size:26px; font-weight:800; letter-spacing:-0.3px; margin:0 0 8px 0; color:#FFFFFF; text-shadow:0 2px 4px rgba(0,0,0,0.2);">
                Empowering Local Coastal Communities
            </h2>
            <div style="font-family:var(--font-secondary); font-size:16px; font-weight:500; color:#F0F8FF; opacity:0.95; max-width:640px; margin:0 auto; line-height:1.5;">
                Fresh aquatic products directly from certified local farmers to your table.
            </div>
        </div>
    </div>

    <!-- Description Paragraph Card -->
    <div class="section-card">
        <p style="font-size:15px; line-height:1.8; color:var(--text); text-align:center; max-width:760px; margin:0 auto; padding:8px 0;">
            SeaLink is designed as an integrated web-based platform that combines a marketplace and an information hub.
            The marketplace enables farmers to directly post and sell their aquatic products to local buyers within their reach,
            improving income opportunities. Meanwhile, the information hub provides content to enhance knowledge about
            aquatic farming, products, and industry standards.
        </p>
    </div>

    <!-- 3 Contact / Info Boxes -->
    <style>
    .about-contact-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
        margin-top: 14px;
    }
    @media (max-width: 768px) {
        .about-contact-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
        .about-card-address {
            grid-column: 1 / -1;
        }
        .about-card-email,
        .about-card-contact {
            grid-column: span 1;
            padding: 20px 10px !important;
        }
        .about-card-email .small-muted,
        .about-card-contact .small-muted {
            font-size: 12px !important;
            word-break: break-all;
        }
    }
    </style>

    <div class="about-contact-grid">
        <!-- Office Address -->
        <div class="section-card about-card-address" style="margin-top:0; text-align:center; padding:24px 16px;">
            <div style="width:48px; height:48px; margin:0 auto 12px auto; background:rgba(100, 149, 237, 0.15); border-radius:50%; display:flex; align-items:center; justify-content:center; color:var(--primary);">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5 14.5 7.62 14.5 9 13.38 11.5 12 11.5z"/>
                </svg>
            </div>
            <div style="font-weight:800; font-size:15px; margin-bottom:6px; color:var(--text);">Office Address</div>
            <div class="small-muted" style="font-size:13px;"><?php echo htmlspecialchars($about_address); ?></div>
        </div>

        <!-- Email -->
        <div class="section-card about-card-email" style="margin-top:0; text-align:center; padding:24px 16px;">
            <div style="width:48px; height:48px; margin:0 auto 12px auto; background:rgba(100, 149, 237, 0.15); border-radius:50%; display:flex; align-items:center; justify-content:center; color:var(--primary);">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                </svg>
            </div>
            <div style="font-weight:800; font-size:15px; margin-bottom:6px; color:var(--text);">Email</div>
            <div class="small-muted" style="font-size:13px;"><?php echo htmlspecialchars($about_email); ?></div>
        </div>

        <!-- Contact Number -->
        <div class="section-card about-card-contact" style="margin-top:0; text-align:center; padding:24px 16px;">
            <div style="width:48px; height:48px; margin:0 auto 12px auto; background:rgba(100, 149, 237, 0.15); border-radius:50%; display:flex; align-items:center; justify-content:center; color:var(--primary);">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1C9.61 21 3 14.39 3 5c0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.24 1.02l-2.21 2.2z"/>
                </svg>
            </div>
            <div style="font-weight:800; font-size:15px; margin-bottom:6px; color:var(--text);">Contact Number</div>
            <div class="small-muted" style="font-size:13px;"><?php echo htmlspecialchars($about_contact); ?></div>
        </div>
    </div>
</div>
