<?php
if (!defined('ABSPATH')) exit;

function tm_update_db_schema() {
    global $wpdb;
    $table_records = $wpdb->prefix . 'time_memory_records';
    $table_logs = $wpdb->prefix . 'time_memory_audit_logs';
    $charset_collate = $wpdb->get_charset_collate();

    // Primary Records Table
    $sql_records = "CREATE TABLE $table_records (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        category varchar(100) NOT NULL,
        record_title varchar(255) NOT NULL,
        record_details longtext NOT NULL,
        record_date datetime NOT NULL,
        image_url text DEFAULT NULL,
        audio_url text DEFAULT NULL,
        related_id mediumint(9) DEFAULT NULL,
        reminder_date datetime DEFAULT NULL,
        reminder_frequency varchar(20) DEFAULT NULL,
        is_encrypted tinyint(1) DEFAULT 0 NOT NULL,
        amount decimal(15,2) DEFAULT NULL,
        parent_id mediumint(9) DEFAULT NULL,
        priority tinyint(1) DEFAULT 0 NOT NULL,
        is_pinned tinyint(1) DEFAULT 0 NOT NULL,
        tags text DEFAULT NULL,
        metadata longtext DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY category (category),
        KEY related_id (related_id),
        KEY parent_id (parent_id)
    ) $charset_collate;";

    // Micro-Event Audit Logging Table
    $sql_logs = "CREATE TABLE $table_logs (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        action_category varchar(50) NOT NULL,
        action_type varchar(50) NOT NULL,
        client_vector text DEFAULT NULL,
        payload longtext DEFAULT NULL,
        diff longtext DEFAULT NULL,
        user_identifier varchar(100) DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY action_category (action_category),
        KEY timestamp (timestamp)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_records);
    dbDelta($sql_logs);
}
