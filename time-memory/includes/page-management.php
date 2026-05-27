<?php
if (!defined('ABSPATH')) exit;

function tm_create_frontend_page() {
    $page_title = 'ذاكرة الزمن';
    $page_slug = 'time-memory-app';

    $page_check = get_page_by_path($page_slug);

    if (!isset($page_check->ID)) {
        $page_id = wp_insert_post(array(
            'post_title'   => $page_title,
            'post_content' => '',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_name'    => $page_slug
        ));
    }
}

add_filter('template_include', 'tm_enforce_frontend_template');
function tm_enforce_frontend_template($template) {
    if (is_page('time-memory-app')) {
        $custom_template = plugin_dir_path(dirname(__FILE__)) . 'templates/app-template.php';
        if (file_exists($custom_template)) {
            return $custom_template;
        }
    }
    return $template;
}
