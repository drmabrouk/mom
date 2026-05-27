<?php
if (!defined('ABSPATH')) exit;

class TM_DB {
    private $wpdb;
    private $table_records;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_records = $wpdb->prefix . 'time_memory_records';
    }

    public function get_record($id) {
        return $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM $this->table_records WHERE id = %d", $id), ARRAY_A);
    }

    public function insert($data) {
        $result = $this->wpdb->insert($this->table_records, $data);
        return $result ? $this->wpdb->insert_id : false;
    }

    public function update($id, $data) {
        return $this->wpdb->update($this->table_records, $data, array('id' => $id));
    }

    public function delete($id) {
        return $this->wpdb->delete($this->table_records, array('id' => $id));
    }

    public function query($args = array()) {
        $query = "SELECT * FROM $this->table_records WHERE parent_id IS NULL";
        $params = array();

        if (!empty($args['category'])) {
            $query .= " AND category = %s";
            $params[] = $args['category'];
        }

        if (!empty($args['related_id'])) {
            $query .= " AND related_id = %d";
            $params[] = $args['related_id'];
        }

        if (isset($args['is_pinned'])) {
            $query .= " AND is_pinned = %d";
            $params[] = $args['is_pinned'] ? 1 : 0;
        }

        $order = !empty($args['order']) ? $args['order'] : 'DESC';
        $query .= " ORDER BY is_pinned DESC, priority DESC, record_date $order";

        return $this->wpdb->get_results($this->wpdb->prepare($query, $params), ARRAY_A);
    }

    public function federated_search($term) {
        $query = "SELECT * FROM $this->table_records
                  WHERE (record_title LIKE %s OR record_details LIKE %s OR tags LIKE %s)
                  AND parent_id IS NULL
                  ORDER BY category ASC, record_date DESC";
        $like = '%' . $this->wpdb->esc_like($term) . '%';
        return $this->wpdb->get_results($this->wpdb->prepare($query, $like, $like, $like), ARRAY_A);
    }
}
