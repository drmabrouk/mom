<?php
/*
Plugin Name: ذاكرة الزمن
Plugin URI:
Description: نسخة مطورة من ذاكرة الزمن، تعمل بشكل كامل من خلال صفحة مستقلة مع ميزات متقدمة (وسائط، أمان، سهولة وصول).
Version: 2.0
Author: الذكاء الاصطناعي (Gemini)
*/

// منع الوصول المباشر
if (!defined('ABSPATH')) exit;

// تعريف الثوابت
define('TM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TM_PLUGIN_URL', plugin_dir_url(__FILE__));

// تضمين الملفات الأساسية
require_once(TM_PLUGIN_DIR . 'includes/database.php');
require_once(TM_PLUGIN_DIR . 'includes/security.php');
require_once(TM_PLUGIN_DIR . 'includes/ajax-handlers.php');
require_once(TM_PLUGIN_DIR . 'includes/page-management.php');

// تفعيل الإضافة
register_activation_hook(__FILE__, 'tm_activate_plugin');
function tm_activate_plugin() {
    tm_update_db_schema();
    tm_create_frontend_page();
}

// إضافة رابط في القائمة الجانبية يوجه للصفحة الأمامية
add_action('admin_menu', 'tm_setup_admin_menu');
function tm_setup_admin_menu() {
    add_menu_page('ذاكرة الزمن', 'ذاكرة الزمن', 'read', 'time-memory-app-link', 'tm_redirect_to_frontend', 'dashicons-book', 6);
}

function tm_redirect_to_frontend() {
    $page_url = home_url('/time-memory-app/');
    echo '<script>window.location.href="' . $page_url . '";</script>';
    exit;
}

// تسجيل السكربتات والستايلات
add_action('wp_enqueue_scripts', 'tm_enqueue_assets');
function tm_enqueue_assets() {
    if (is_page('time-memory-app')) {
        wp_enqueue_style('tm-main-style', TM_PLUGIN_URL . 'assets/css/style.css');

        wp_enqueue_script('tm-auth-js', TM_PLUGIN_URL . 'assets/js/auth.js', array('jquery'), '2.0', true);
        wp_enqueue_script('tm-media-js', TM_PLUGIN_URL . 'assets/js/media.js', array('jquery'), '2.0', true);
        wp_enqueue_script('tm-accessibility-js', TM_PLUGIN_URL . 'assets/js/accessibility.js', array('jquery'), '2.0', true);
        wp_enqueue_script('tm-ui-js', TM_PLUGIN_URL . 'assets/js/ui.js', array('jquery'), '2.0', true);

        wp_localize_script('tm-auth-js', 'tm_ajax_obj', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('tm_nonce')
        ));
    }
}
