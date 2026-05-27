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
    <header style="display: flex; justify-content: space-between; align-items: center;">
        <h1>ذاكرة الزمن</h1>
        <div id="tm-auth-status"></div>
    </header>

    <main id="tm-content">
        <!-- Content will be loaded here via AJAX -->
        <p>جاري التحميل...</p>
    </main>
</div>

<?php wp_footer(); ?>
</body>
</html>
