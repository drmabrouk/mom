<?php
if (!defined('ABSPATH')) exit;

function tm_update_db_schema() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'time_memory_records';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        category varchar(100) NOT NULL,
        record_title varchar(255) NOT NULL,
        record_details text NOT NULL,
        record_date date NOT NULL,
        image_url text DEFAULT NULL,
        audio_url text DEFAULT NULL,
        related_id mediumint(9) DEFAULT NULL,
        reminder_date datetime DEFAULT NULL,
        reminder_frequency varchar(20) DEFAULT NULL,
        is_encrypted tinyint(1) DEFAULT 0 NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
