<?php
/**
 * Template Name: Time Memory App
 * Description: Standalone frontend page for Time Memory plugin.
 */

if (!defined('ABSPATH')) exit;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php wp_title(); ?></title>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>

<div id="tm-app" class="tm-container">
    <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; border-bottom: 5px solid var(--border-color); padding-bottom: 20px;">
        <h1 style="margin: 0; font-size: 40px; font-weight: 900;">ذاكرة الزمن</h1>
        <div id="tm-auth-status"></div>
    </header>

    <div style="display: flex; gap: 30px; flex-wrap: wrap;">
        <main id="tm-content" style="flex: 1; min-width: 300px;">
            <!-- Content will be loaded here via AJAX -->
            <p>جاري التحميل...</p>
        </main>

        <aside id="tm-recall-sidebar" style="flex: 0 0 250px; display: none;">
            <h3 style="border-bottom: 3px solid var(--border-color); padding-bottom: 10px;">استرجاع سياقي</h3>
            <div id="tm-sidebar-content"></div>
        </aside>
    </div>

    <!-- Fixed Utility Bar -->
    <div id="tm-utility-bar">
        <button id="tm-zoom-in" class="tm-btn" style="padding: 10px 15px; font-size: 16px;" title="تكبير الخط">A+</button>
        <button id="tm-zoom-out" class="tm-btn" style="padding: 10px 15px; font-size: 16px;" title="تصغير الخط">A-</button>
        <button id="tm-spacing-inc" class="tm-btn" style="padding: 10px 15px; font-size: 16px;" title="زيادة التباعد">↔+</button>
        <button id="tm-spacing-dec" class="tm-btn" style="padding: 10px 15px; font-size: 16px;" title="تقليل التباعد">↔-</button>
        <button id="tm-dark-toggle" class="tm-btn" style="padding: 10px 15px; font-size: 16px;" title="تبديل الوضع الليلي">🌙</button>
    </div>
</div>

<?php wp_footer(); ?>
</body>
</html>
