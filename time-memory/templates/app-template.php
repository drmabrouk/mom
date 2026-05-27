<?php
/**
 * Template Name: Time Memory App
 * Description: Standalone frontend page for Time Memory plugin.
 */

if (!defined('ABSPATH')) exit;

// Force hide admin bar
add_filter('show_admin_bar', '__return_false');

// Ensure no headers/footers from theme are loaded
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> style="margin-top: 0 !important;">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>ذاكرة الزمن | Frameless View</title>
    <?php wp_head(); ?>
    <style>
        html, body { height: 100%; overflow: hidden; }
        #wpadminbar { display: none !important; }
    </style>
</head>
<body <?php body_class(); ?>>

<div id="tm-app" class="tm-container-fullscreen">
    <header id="tm-global-header">
        <div style="display: flex; align-items: center; gap: 20px;">
            <h1 id="tm-app-logo">ذاكرة الزمن</h1>
            <div id="tm-stats-counter"></div>
        </div>

        <div id="tm-global-search-container">
            <input type="text" id="tm-federated-search" placeholder="🔍 ابحث في كل شيء (أشخاص، ديون، مواقف، ملاحظات)...">
        </div>

        <div id="tm-auth-status"></div>
    </header>

    <div id="tm-layout-body">
        <main id="tm-content">
            <!-- Dynamic Content -->
            <p>جاري تهيئة النظام...</p>
        </main>

        <aside id="tm-recall-sidebar">
            <div id="tm-sidebar-header">
                <h3>استرجاع سياقي</h3>
                <button id="tm-sidebar-close">×</button>
            </div>
            <div id="tm-sidebar-content"></div>
        </aside>
    </div>

    <!-- Passcode Overlay -->
    <div id="tm-passcode-overlay" class="tm-modal-overlay" style="display:none;">
        <div class="tm-modal-box">
            <h3>🔐 تأكيد الهوية</h3>
            <p>أدخل رمز التحقق (10111996) لإتمام العملية الحساسة:</p>
            <input type="password" id="tm-passcode-input" maxlength="8">
            <div style="display:flex; gap:10px; margin-top:20px;">
                <button id="tm-passcode-confirm" class="tm-btn">تأكيد</button>
                <button id="tm-passcode-cancel" class="tm-btn" style="background:white; color:black;">إلغاء</button>
            </div>
        </div>
    </div>

    <!-- Fixed Utility Bar -->
    <div id="tm-utility-bar">
        <button id="tm-zoom-in" class="tm-btn" title="تكبير الخط">A+</button>
        <button id="tm-zoom-out" class="tm-btn" title="تصغير الخط">A-</button>
        <button id="tm-spacing-inc" class="tm-btn" title="زيادة التباعد">↔+</button>
        <button id="tm-spacing-dec" class="tm-btn" title="تقليل التباعد">↔-</button>
        <button id="tm-dark-toggle" class="tm-btn" title="تبديل الوضع الليلي">🌙</button>
    </div>
</div>

<?php wp_footer(); ?>
</body>
</html>
