<?php
if (!defined('ABSPATH')) exit;

class TM_Audit {
    private $wpdb;
    private $table_logs;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_logs = $wpdb->prefix . 'time_memory_audit_logs';
    }

    public function log($category, $type, $payload = '', $diff = null, $user = 'ahmed') {
        $data = array(
            'action_category' => $category,
            'action_type'     => $type,
            'client_vector'   => $_SERVER['HTTP_USER_AGENT'] . ' | ' . $_SERVER['REMOTE_ADDR'],
            'payload'         => is_array($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE) : $payload,
            'diff'            => $diff ? json_encode($diff, JSON_UNESCAPED_UNICODE) : null,
            'user_identifier' => $user,
            'timestamp'       => current_time('mysql')
        );
        $this->wpdb->insert($this->table_logs, $data);
    }

    public function get_diff($old, $new) {
        $diff = array();
        foreach ($new as $key => $value) {
            if (isset($old[$key]) && $old[$key] !== $value) {
                $diff[$key] = array('old' => $old[$key], 'new' => $value);
            }
        }
        return $diff;
    }
}
