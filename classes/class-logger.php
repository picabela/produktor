<?php

if (!defined('ABSPATH')) {
    exit;
}

class ProduktorWP_Logger {
    
    private $db;
    
    public function __construct() {
        $this->db = new ProduktorWP_Database();
    }
    
    public function log($site_id, $action, $message, $level = 'info', $data = null) {
        return $this->db->add_log($site_id, $action, $message, $level, $data);
    }
    
    public function get_logs($filters = array()) {
        return $this->db->get_logs($filters);
    }
    
    public function info($site_id, $action, $message, $data = null) {
        return $this->log($site_id, $action, $message, 'info', $data);
    }
    
    public function warning($site_id, $action, $message, $data = null) {
        return $this->log($site_id, $action, $message, 'warning', $data);
    }
    
    public function error($site_id, $action, $message, $data = null) {
        return $this->log($site_id, $action, $message, 'error', $data);
    }
    
    public function cleanup_old_logs($days = 30) {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}produktor_wp_logs WHERE created_at < %s",
            $cutoff_date
        ));
    }
    
    public function get_error_count($site_id = null, $days = 7) {
        global $wpdb;
        
        $where = "level = 'error' AND created_at >= %s";
        $params = array(date('Y-m-d H:i:s', strtotime("-{$days} days")));
        
        if ($site_id) {
            $where .= " AND site_id = %d";
            $params[] = $site_id;
        }
        
        $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}produktor_wp_logs WHERE {$where}";
        
        return $wpdb->get_var($wpdb->prepare($sql, $params));
    }
    
    public function get_recent_activities($limit = 10, $site_id = null) {
        global $wpdb;
        
        $where = "1=1";
        $params = array();
        
        if ($site_id) {
            $where .= " AND l.site_id = %d";
            $params[] = $site_id;
        }
        
        $sql = "SELECT l.*, s.name as site_name 
                FROM {$wpdb->prefix}produktor_wp_logs l 
                LEFT JOIN {$wpdb->prefix}produktor_wp_sites s ON l.site_id = s.id 
                WHERE {$where} 
                ORDER BY l.created_at DESC 
                LIMIT %d";
        
        $params[] = $limit;
        
        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }
    
    public function export_logs($filters = array()) {
        $logs = $this->get_logs($filters);
        
        if (empty($logs)) {
            return false;
        }
        
        $csv_data = array();
        $csv_data[] = array('Data', 'Strona', 'Akcja', 'Wiadomość', 'Poziom');
        
        foreach ($logs as $log) {
            $csv_data[] = array(
                $log->created_at,
                $log->site_name ?: 'System',
                $log->action,
                $log->message,
                $log->level
            );
        }
        
        return $csv_data;
    }
}