<?php
/**
 * Template Name: Time Memory App
 * Description: Standalone frontend page for Time Memory plugin.
 */

if (!defined('ABSPATH')) exit;

// Force hide admin bar
add_filter('show_admin_bar', '__return_false');
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> style="margin-top: 0 !important;">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>ذاكرة الزمن | Immersive Vault</title>
    <?php wp_head(); ?>
    <style>
        html, body { height: 100%; overflow: hidden; }
        #wpadminbar { display: none !important; }
    </style>
</head>
<body <?php body_class(); ?>>

<div id="tm-app" class="tm-container-fullscreen">
    <header id="tm-global-header">
        <div class="tm-header-top">
            <h1 id="tm-app-logo">ذاكرة الزمن</h1>
            <div id="tm-stats-counter"></div>
            <div id="tm-auth-status"></div>
        </div>

        <div id="tm-global-search-container">
            <input type="text" id="tm-federated-search" placeholder="🔍 بحث مجهري (أشخاص، ديون، مواقف)...">
        </div>
    </header>

    <div id="tm-layout-body">
        <!-- Floating Navigation Badges -->
        <nav class="tm-nav" id="tm-main-nav"></nav>

        <main id="tm-content">
            <div id="tm-stage">
                <p style="padding: 40px; font-size: 24px;">جاري المصادقة...</p>
            </div>
        </main>

        <!-- Recall Sidebar -->
        <aside id="tm-recall-sidebar">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3 style="margin:0;">رادار التفاعل</h3>
                <button id="tm-sidebar-close" style="background:none; border:0; font-size:30px; cursor:pointer;">×</button>
            </div>
            <div id="tm-sidebar-content"></div>
        </aside>
    </div>

    <!-- Passcode Overlay -->
    <div id="tm-passcode-overlay" class="tm-modal-overlay">
        <div class="tm-modal-box">
            <h3 style="font-size:28px; margin-bottom:20px;">🔐 تأكيد الهوية</h3>
            <p style="font-size:18px; margin-bottom:25px;">أدخل رمز التحقق النهائي للمسح:</p>
            <input type="password" id="tm-passcode-input" maxlength="8" style="text-align:center; font-size:32px; letter-spacing:10px;">
            <div style="display:flex; gap:15px; margin-top:30px;">
                <button id="tm-passcode-confirm" class="tm-btn-main" style="margin:0; flex:1;">تأكيد</button>
                <button id="tm-passcode-cancel" class="tm-btn-main" style="margin:0; flex:1; background:white; color:black;">إلغاء</button>
            </div>
        </div>
    </div>

    <!-- Utility Bar -->
    <div id="tm-utility-bar">
        <button id="tm-zoom-in" class="tm-btn" title="تكبير">A+</button>
        <button id="tm-zoom-out" class="tm-btn" title="تصغير">A-</button>
        <button id="tm-dark-toggle" class="tm-btn" title="الوضع الليلي">🌙</button>
    </div>
</div>

<?php wp_footer(); ?>
</body>
</html>
