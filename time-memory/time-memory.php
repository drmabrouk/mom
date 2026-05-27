<?php
/*
Plugin Name: ذاكرة الزمن - Modular Edition
Description: نظام متطور لإدارة الذاكرة الرقمية (أحداث، شخصيات، أمن، مالية) مع سجل تدقيق مجهري.
Version: 3.1
Author: الذكاء الاصطناعي (Gemini)
*/

if (!defined('ABSPATH')) exit;

define('TM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TM_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load Framework
require_once(TM_PLUGIN_DIR . 'includes/database.php');
require_once(TM_PLUGIN_DIR . 'includes/security.php');
require_once(TM_PLUGIN_DIR . 'includes/class-tm-db.php');
require_once(TM_PLUGIN_DIR . 'includes/class-tm-audit.php');
require_once(TM_PLUGIN_DIR . 'includes/class-tm-auth.php');
require_once(TM_PLUGIN_DIR . 'includes/ajax-handlers.php');
require_once(TM_PLUGIN_DIR . 'includes/page-management.php');

// Global Instances
function tm_db() { static $inst; if (!$inst) $inst = new TM_DB(); return $inst; }
function tm_audit() { static $inst; if (!$inst) $inst = new TM_Audit(); return $inst; }
function tm_auth() { static $inst; if (!$inst) $inst = new TM_Auth(tm_audit()); return $inst; }

register_activation_hook(__FILE__, 'tm_activate_plugin');
function tm_activate_plugin() {
    tm_update_db_schema();
    tm_create_frontend_page();
}

add_action('wp_enqueue_scripts', 'tm_enqueue_assets');
function tm_enqueue_assets() {
    if (is_page('time-memory-app')) {
        wp_enqueue_style('tm-main-style', TM_PLUGIN_URL . 'assets/css/style.css');

        wp_enqueue_script('tm-auth-js', TM_PLUGIN_URL . 'assets/js/auth.js', array('jquery'), '3.1', true);
        wp_enqueue_script('tm-accessibility-js', TM_PLUGIN_URL . 'assets/js/accessibility.js', array('jquery'), '3.1', true);
        wp_enqueue_script('tm-ui-js', TM_PLUGIN_URL . 'assets/js/ui.js', array('jquery', 'tm-auth-js', 'tm-accessibility-js'), '3.1', true);

        wp_localize_script('tm-auth-js', 'tm_ajax_obj', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('tm_nonce')
        ));
    }
}
