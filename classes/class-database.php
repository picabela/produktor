<?php

if (!defined('ABSPATH')) {
    exit;
}

class ProduktorWP_Database {
    
    private $sites_table;
    private $posts_table;
    private $logs_table;
    private $taxonomy_mappings_table;
    
    public function __construct() {
        global $wpdb;
        
        $this->sites_table = $wpdb->prefix . 'produktor_wp_sites';
        $this->posts_table = $wpdb->prefix . 'produktor_wp_posts';
        $this->logs_table = $wpdb->prefix . 'produktor_wp_logs';
        $this->taxonomy_mappings_table = $wpdb->prefix . 'produktor_wp_taxonomy_mappings';
    }
    
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabela stron zewnętrznych
        $sites_sql = "CREATE TABLE {$this->sites_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            url varchar(255) NOT NULL,
            username varchar(255) NOT NULL,
            password varchar(255) NOT NULL,
            status enum('active','inactive','error') DEFAULT 'active',
            last_sync datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY url (url)
        ) $charset_collate;";
        
        // Tabela postów
        $posts_sql = "CREATE TABLE {$this->posts_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            local_post_id int(11) NOT NULL,
            site_id int(11) NOT NULL,
            remote_post_id int(11) DEFAULT NULL,
            status enum('pending','published','error','archived') DEFAULT 'pending',
            published_at datetime DEFAULT NULL,
            error_message text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY local_post_id (local_post_id),
            KEY site_id (site_id),
            FOREIGN KEY (site_id) REFERENCES {$this->sites_table}(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Tabela logów
        $logs_sql = "CREATE TABLE {$this->logs_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            site_id int(11) DEFAULT NULL,
            action varchar(100) NOT NULL,
            message text NOT NULL,
            level enum('info','warning','error') DEFAULT 'info',
            data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY site_id (site_id),
            KEY level (level),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Tabela mapowania taksonomii
        $taxonomy_sql = "CREATE TABLE {$this->taxonomy_mappings_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            site_id int(11) NOT NULL,
            local_taxonomy varchar(100) NOT NULL,
            local_term_id int(11) NOT NULL,
            remote_taxonomy varchar(100) NOT NULL,
            remote_term_id int(11) NOT NULL,
            mapping_type enum('manual','auto') DEFAULT 'auto',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY site_id (site_id),
            KEY local_term_id (local_term_id),
            UNIQUE KEY unique_mapping (site_id, local_taxonomy, local_term_id, remote_taxonomy),
            FOREIGN KEY (site_id) REFERENCES {$this->sites_table}(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sites_sql);
        dbDelta($posts_sql);
        dbDelta($logs_sql);
        dbDelta($taxonomy_sql);
    }
    
    public function get_sites() {
        global $wpdb;
        
        return $wpdb->get_results("SELECT * FROM {$this->sites_table} ORDER BY name ASC");
    }
    
    public function get_site($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->sites_table} WHERE id = %d", $id));
    }
    
    public function add_site($data) {
        global $wpdb;
        
        return $wpdb->insert(
            $this->sites_table,
            array(
                'name' => sanitize_text_field($data['name']),
                'url' => esc_url_raw($data['url']),
                'username' => sanitize_text_field($data['username']),
                'password' => sanitize_text_field($data['password']),
                'status' => 'active'
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
    }
    
    public function update_site($id, $data) {
        global $wpdb;
        
        return $wpdb->update(
            $this->sites_table,
            $data,
            array('id' => $id),
            null,
            array('%d')
        );
    }
    
    public function delete_site($id) {
        global $wpdb;
        
        return $wpdb->delete($this->sites_table, array('id' => $id), array('%d'));
    }
    
    public function add_post_record($local_post_id, $site_id, $remote_post_id = null, $status = 'pending') {
        global $wpdb;
        
        return $wpdb->insert(
            $this->posts_table,
            array(
                'local_post_id' => $local_post_id,
                'site_id' => $site_id,
                'remote_post_id' => $remote_post_id,
                'status' => $status,
                'published_at' => ($status === 'published') ? current_time('mysql') : null
            ),
            array('%d', '%d', '%d', '%s', '%s')
        );
    }
    
    public function update_post_record($id, $data) {
        global $wpdb;
        
        return $wpdb->update($this->posts_table, $data, array('id' => $id), null, array('%d'));
    }
    
    public function get_available_posts() {
        global $wpdb;
        
        $used_posts = $wpdb->get_col("SELECT DISTINCT local_post_id FROM {$this->posts_table} WHERE status != 'archived'");
        
        $exclude = !empty($used_posts) ? "AND ID NOT IN (" . implode(',', array_map('intval', $used_posts)) . ")" : "";
        
        return get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'numberposts' => -1,
            'meta_query' => array(
                array(
                    'key' => '_produktor_wp_exclude',
                    'compare' => 'NOT EXISTS'
                )
            )
        ));
    }
    
    public function add_log($site_id, $action, $message, $level = 'info', $data = null) {
        global $wpdb;
        
        return $wpdb->insert(
            $this->logs_table,
            array(
                'site_id' => $site_id,
                'action' => $action,
                'message' => $message,
                'level' => $level,
                'data' => $data ? json_encode($data) : null
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
    }
    
    public function get_logs($filters = array()) {
        global $wpdb;
        
        $where = array("1=1");
        $params = array();
        
        if (!empty($filters['site_id'])) {
            $where[] = "site_id = %d";
            $params[] = $filters['site_id'];
        }
        
        if (!empty($filters['level'])) {
            $where[] = "level = %s";
            $params[] = $filters['level'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = "created_at >= %s";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "created_at <= %s";
            $params[] = $filters['date_to'];
        }
        
        $limit = !empty($filters['limit']) ? intval($filters['limit']) : 50;
        
        $sql = "SELECT l.*, s.name as site_name 
                FROM {$this->logs_table} l 
                LEFT JOIN {$this->sites_table} s ON l.site_id = s.id 
                WHERE " . implode(' AND ', $where) . " 
                ORDER BY l.created_at DESC 
                LIMIT {$limit}";
        
        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($sql, $params));
        } else {
            return $wpdb->get_results($sql);
        }
    }
}