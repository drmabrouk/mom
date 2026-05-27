<?php
if (!defined('ABSPATH')) exit;

// Access Handlers
add_action('wp_ajax_tm_login', 'tm_ajax_login');
add_action('wp_ajax_nopriv_tm_login', 'tm_ajax_login');
function tm_ajax_login() {
    check_ajax_referer('tm_nonce', 'security');
    if (tm_auth()->login($_POST['username'], $_POST['password'])) {
        wp_send_json_success();
    }
    wp_send_json_error(array('message' => 'بيانات خاطئة.'));
}

add_action('wp_ajax_tm_logout', 'tm_ajax_logout');
function tm_ajax_logout() {
    check_ajax_referer('tm_nonce', 'security');
    tm_auth()->logout();
    wp_send_json_success();
}

add_action('wp_ajax_tm_check_auth', 'tm_ajax_check_auth');
add_action('wp_ajax_nopriv_tm_check_auth', 'tm_ajax_check_auth');
function tm_ajax_check_auth() {
    wp_send_json_success(array('authenticated' => tm_auth()->is_authenticated()));
}

// Data Handlers
add_action('wp_ajax_tm_add_record', 'tm_ajax_add_record');
function tm_ajax_add_record() {
    check_ajax_referer('tm_nonce', 'security');
    if (!tm_auth()->is_authenticated()) wp_die();

    $db = tm_db();
    $audit = tm_audit();
    $id = isset($_POST['id']) ? intval($_POST['id']) : null;
    $is_autosave = isset($_POST['is_autosave']) && $_POST['is_autosave'] === 'true';

    $metadata = isset($_POST['metadata']) ? $_POST['metadata'] : array();
    if (is_array($metadata)) {
        $metadata = json_encode($metadata, JSON_UNESCAPED_UNICODE);
    }

    $data = array(
        'category' => sanitize_text_field($_POST['category']),
        'record_title' => sanitize_text_field($_POST['title']),
        'record_details' => sanitize_textarea_field($_POST['details']),
        'record_date' => !empty($_POST['date']) ? sanitize_text_field($_POST['date']) : current_time('mysql'),
        'amount' => isset($_POST['amount']) ? floatval($_POST['amount']) : null,
        'related_id' => !empty($_POST['related_id']) ? intval($_POST['related_id']) : null,
        'priority' => isset($_POST['priority']) ? intval($_POST['priority']) : 0,
        'is_pinned' => isset($_POST['is_pinned']) && $_POST['is_pinned'] === 'true' ? 1 : 0,
        'tags' => sanitize_text_field($_POST['tags']),
        'metadata' => $metadata,
        'is_encrypted' => isset($_POST['is_encrypted']) && $_POST['is_encrypted'] === 'true' ? 1 : 0
    );

    // Automated Contact Linking
    if ($data['category'] === 'phones' && empty($data['related_id'])) {
        global $wpdb;
        $table = $wpdb->prefix . 'time_memory_records';
        $match = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE category = 'characters' AND record_title = %s LIMIT 1", $data['record_title']));
        if ($match) $data['related_id'] = $match;
    }

    if ($data['is_encrypted']) {
        $data['record_details'] = tm_encrypt_data($data['record_details']);
    }

    if ($id) {
        $old_record = $db->get_record($id);
        if ($old_record && !$is_autosave) {
            $history = $old_record;
            unset($history['id']);
            $history['parent_id'] = $id;
            $db->insert($history);

            $diff = $audit->get_diff($old_record, $data);
            $audit->log('DATA', 'UPDATE', "ID: $id", $diff);
        }
        $db->update($id, $data);
        $record_id = $id;
    } else {
        $record_id = $db->insert($data);
        $audit->log('DATA', 'CREATE', array('id' => $record_id, 'cat' => $data['category']));
    }

    wp_send_json_success(array('id' => $record_id));
}

add_action('wp_ajax_tm_settle_finance', 'tm_ajax_settle_finance');
function tm_ajax_settle_finance() {
    check_ajax_referer('tm_nonce', 'security');
    if (!tm_auth()->is_authenticated()) wp_die();

    $id = intval($_POST['id']);
    $db = tm_db();
    $record = $db->get_record($id);

    if ($record) {
        $record['parent_id'] = $id;
        $record['tags'] = 'settled';
        unset($record['id']);
        $db->insert($record); // Move to history as settled

        $db->delete($id); // Clear main row
        tm_audit()->log('FINANCE', 'SETTLEMENT', "ID: $id");
        wp_send_json_success();
    }
    wp_send_json_error();
}

add_action('wp_ajax_tm_get_records', 'tm_ajax_get_records');
function tm_ajax_get_records() {
    check_ajax_referer('tm_nonce', 'security');
    if (!tm_auth()->is_authenticated()) wp_die();

    $args = array(
        'category' => isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '',
        'related_id' => isset($_GET['related_id']) ? intval($_GET['related_id']) : null,
        'is_pinned' => isset($_GET['is_pinned']) ? ($_GET['is_pinned'] === 'true') : null
    );

    $records = tm_db()->query($args);
    foreach ($records as &$r) {
        if ($r['is_encrypted']) $r['record_details'] = tm_decrypt_data($r['record_details']);
    }

    wp_send_json_success(array('records' => $records));
}

add_action('wp_ajax_tm_delete_record', 'tm_ajax_delete_record');
function tm_ajax_delete_record() {
    check_ajax_referer('tm_nonce', 'security');
    if (!tm_auth()->is_authenticated()) wp_die();

    $passcode = $_POST['passcode'];
    if ($passcode !== '10111996') {
        tm_audit()->log('SECURITY', 'ERASE_ATTEMPT_FAILED', "ID: " . $_POST['id']);
        wp_send_json_error(array('message' => 'الرمز خاطئ!'));
    }

    $id = intval($_POST['id']);
    tm_db()->delete($id);
    tm_audit()->log('SECURITY', 'ERASE_SUCCESS', "ID: $id");
    wp_send_json_success();
}

add_action('wp_ajax_tm_federated_search', 'tm_ajax_federated_search');
function tm_ajax_federated_search() {
    check_ajax_referer('tm_nonce', 'security');
    if (!tm_auth()->is_authenticated()) wp_die();

    $query = sanitize_text_field($_GET['query']);
    tm_audit()->log('SEARCH', 'QUERY', $query);

    $results = tm_db()->federated_search($query);
    foreach ($results as &$r) {
        if ($r['is_encrypted']) $r['record_details'] = tm_decrypt_data($r['record_details']);
    }

    wp_send_json_success(array('results' => $results));
}

add_action('wp_ajax_tm_get_stats', 'tm_ajax_get_stats');
function tm_ajax_get_stats() {
    check_ajax_referer('tm_nonce', 'security');
    if (!tm_auth()->is_authenticated()) wp_die();

    global $wpdb;
    $table = $wpdb->prefix . 'time_memory_records';
    $stats = $wpdb->get_results("SELECT category, COUNT(*) as count FROM $table WHERE parent_id IS NULL GROUP BY category", OBJECT_K);
    wp_send_json_success(array('stats' => $stats));
}
