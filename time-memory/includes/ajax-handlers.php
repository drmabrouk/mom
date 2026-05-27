<?php
if (!defined('ABSPATH')) exit;

// Login AJAX Handler
add_action('wp_ajax_tm_login', 'tm_ajax_login_handler');
add_action('wp_ajax_nopriv_tm_login', 'tm_ajax_login_handler');

function tm_ajax_login_handler() {
    check_ajax_referer('tm_nonce', 'security');
    $username = sanitize_text_field($_POST['username']);
    $password = sanitize_text_field($_POST['password']);

    if ($username === 'ahmed' && $password === '10111996') {
        $token = bin2hex(random_bytes(32));
        update_option('tm_auth_token', $token);
        setcookie('tm_auth_token', $token, time() + 86400, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        wp_send_json_success(array('message' => __('تم تسجيل الدخول بنجاح.', 'time-memory')));
    } else {
        wp_send_json_error(array('message' => __('بيانات الدخول غير صحيحة.', 'time-memory')));
    }
}

// Logout AJAX Handler
add_action('wp_ajax_tm_logout', 'tm_ajax_logout_handler');
add_action('wp_ajax_nopriv_tm_logout', 'tm_ajax_logout_handler');

function tm_ajax_logout_handler() {
    check_ajax_referer('tm_nonce', 'security');
    delete_option('tm_auth_token');
    setcookie('tm_auth_token', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
    wp_send_json_success(array('message' => __('تم تسجيل الخروج.', 'time-memory')));
}

// Check Authentication Status
add_action('wp_ajax_tm_check_auth', 'tm_ajax_check_auth_handler');
add_action('wp_ajax_nopriv_tm_check_auth', 'tm_ajax_check_auth_handler');

function tm_ajax_check_auth_handler() {
    check_ajax_referer('tm_nonce', 'security');
    if (tm_is_authenticated()) {
        wp_send_json_success(array('authenticated' => true));
    } else {
        wp_send_json_success(array('authenticated' => false));
    }
}

// Check if user is authenticated
function tm_is_authenticated() {
    $token = get_option('tm_auth_token');
    return $token && isset($_COOKIE['tm_auth_token']) && $_COOKIE['tm_auth_token'] === $token;
}

// Add Record AJAX Handler
add_action('wp_ajax_tm_add_record', 'tm_ajax_add_record_handler');
function tm_ajax_add_record_handler() {
    check_ajax_referer('tm_nonce', 'security');
    if (!tm_is_authenticated()) wp_send_json_error(array('message' => __('غير مصرح لك.', 'time-memory')));

    global $wpdb;
    $table_name = $wpdb->prefix . 'time_memory_records';

    $category = sanitize_text_field($_POST['category']);
    $title = sanitize_text_field($_POST['title']);
    $details = sanitize_textarea_field($_POST['details']);
    $date = sanitize_text_field($_POST['date']);
    $related_id = isset($_POST['related_id']) ? intval($_POST['related_id']) : null;
    $reminder_date = !empty($_POST['reminder_date']) ? sanitize_text_field($_POST['reminder_date']) : null;
    $reminder_frequency = !empty($_POST['reminder_frequency']) ? sanitize_text_field($_POST['reminder_frequency']) : null;
    $is_encrypted = isset($_POST['is_encrypted']) && $_POST['is_encrypted'] === 'true' ? 1 : 0;

    if ($is_encrypted) {
        $details = tm_encrypt_data($details);
    }

    $image_url = null;
    if (!empty($_FILES['image']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        $upload = wp_handle_upload($_FILES['image'], array('test_form' => false));
        if (isset($upload['url'])) $image_url = $upload['url'];
    }

    $audio_url = null;
    if (!empty($_FILES['audio']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        $upload = wp_handle_upload($_FILES['audio'], array('test_form' => false));
        if (isset($upload['url'])) $audio_url = $upload['url'];
    }

    $result = $wpdb->insert($table_name, array(
        'category' => $category,
        'record_title' => $title,
        'record_details' => $details,
        'record_date' => $date,
        'image_url' => $image_url,
        'audio_url' => $audio_url,
        'related_id' => $related_id,
        'reminder_date' => $reminder_date,
        'reminder_frequency' => $reminder_frequency,
        'is_encrypted' => $is_encrypted
    ));

    if ($result) {
        wp_send_json_success(array('message' => __('تم حفظ السجل بنجاح.', 'time-memory')));
    } else {
        wp_send_json_error(array('message' => __('فشل في حفظ السجل.', 'time-memory')));
    }
}

// Get Records AJAX Handler
add_action('wp_ajax_tm_get_records', 'tm_ajax_get_records_handler');
function tm_ajax_get_records_handler() {
    check_ajax_referer('tm_nonce', 'security');
    if (!tm_is_authenticated()) wp_send_json_error(array('message' => __('غير مصرح لك.', 'time-memory')));

    global $wpdb;
    $table_name = $wpdb->prefix . 'time_memory_records';
    $category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
    $related_id = isset($_GET['related_id']) ? intval($_GET['related_id']) : null;

    $query = "SELECT * FROM $table_name";
    $where = array();
    $params = array();

    if ($category) {
        $where[] = "category = %s";
        $params[] = $category;
    }
    if ($related_id) {
        $where[] = "related_id = %d";
        $params[] = $related_id;
    }

    if (!empty($where)) {
        $query .= " WHERE " . implode(" AND ", $where);
    }
    $query .= " ORDER BY record_date DESC";

    $records = $wpdb->get_results($wpdb->prepare($query, $params));

    foreach ($records as &$record) {
        if ($record->is_encrypted) {
            $record->record_details = tm_decrypt_data($record->record_details);
        }
    }

    wp_send_json_success(array('records' => $records));
}

// Delete Record AJAX Handler
add_action('wp_ajax_tm_delete_record', 'tm_ajax_delete_record_handler');
function tm_ajax_delete_record_handler() {
    check_ajax_referer('tm_nonce', 'security');
    if (!tm_is_authenticated()) wp_send_json_error(array('message' => __('غير مصرح لك.', 'time-memory')));

    global $wpdb;
    $table_name = $wpdb->prefix . 'time_memory_records';
    $id = intval($_POST['id']);

    $result = $wpdb->delete($table_name, array('id' => $id));

    if ($result) {
        wp_send_json_success(array('message' => __('تم حذف السجل.', 'time-memory')));
    } else {
        wp_send_json_error(array('message' => __('فشل في حذف السجل.', 'time-memory')));
    }
}

// Get Reminders AJAX Handler
add_action('wp_ajax_tm_get_reminders', 'tm_ajax_get_reminders_handler');
function tm_ajax_get_reminders_handler() {
    check_ajax_referer('tm_nonce', 'security');
    if (!tm_is_authenticated()) wp_send_json_error(array('message' => __('غير مصرح لك.', 'time-memory')));

    global $wpdb;
    $table_name = $wpdb->prefix . 'time_memory_records';

    // Get reminders for the next 24 hours
    $records = $wpdb->get_results("SELECT * FROM $table_name WHERE reminder_date IS NOT NULL AND reminder_date <= DATE_ADD(NOW(), INTERVAL 1 DAY) AND reminder_date >= DATE_SUB(NOW(), INTERVAL 1 HOUR) ORDER BY reminder_date ASC");

    foreach ($records as &$record) {
        if ($record->reminder_frequency) {
            $next_date = tm_calculate_next_reminder($record->reminder_date, $record->reminder_frequency);
            if ($next_date) {
                $wpdb->update($table_name, array('reminder_date' => $next_date), array('id' => $record->id));
            }
        }
    }

    wp_send_json_success(array('reminders' => $records));
}

function tm_calculate_next_reminder($current_date, $frequency) {
    $date = new DateTime($current_date);
    switch ($frequency) {
        case 'daily':
            $date->modify('+1 day');
            break;
        case 'weekly':
            $date->modify('+1 week');
            break;
        case 'monthly':
            $date->modify('+1 month');
            break;
        default:
            return null;
    }
    return $date->format('Y-m-d H:i:s');
}
