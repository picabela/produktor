<?php

if (!defined('ABSPATH')) {
    exit;
}

class ProduktorWP_SiteManager {
    
    private $db;
    private $api_client;
    private $logger;
    
    public function __construct() {
        $this->db = new ProduktorWP_Database();
        $this->api_client = new ProduktorWP_APIClient();
        $this->logger = new ProduktorWP_Logger();
    }
    
    public function add_site($data) {
        // Walidacja danych
        $name = sanitize_text_field($data['name']);
        $url = esc_url_raw($data['url']);
        $username = sanitize_text_field($data['username']);
        $password = sanitize_text_field($data['password']);
        
        if (empty($name) || empty($url) || empty($username) || empty($password)) {
            return array(
                'success' => false,
                'message' => __('Wszystkie pola są wymagane', 'produktor-wp')
            );
        }
        
        // Test połączenia
        $connection_test = $this->api_client->test_connection(array(
            'url' => $url,
            'username' => $username,
            'password' => $password
        ));
        
        if (!$connection_test['success']) {
            return array(
                'success' => false,
                'message' => __('Błąd połączenia: ', 'produktor-wp') . $connection_test['message']
            );
        }
        
        // Dodanie do bazy
        $result = $this->db->add_site(array(
            'name' => $name,
            'url' => $url,
            'username' => $username,
            'password' => $password
        ));
        
        if ($result !== false) {
            $this->logger->log(null, 'add_site', sprintf(__('Dodano nową stronę: %s', 'produktor-wp'), $name));
            
            return array(
                'success' => true,
                'message' => __('Strona została dodana pomyślnie', 'produktor-wp'),
                'site_id' => $result
            );
        } else {
            return array(
                'success' => false,
                'message' => __('Błąd podczas dodawania strony', 'produktor-wp')
            );
        }
    }
    
    public function get_sites_with_stats() {
        $sites = $this->db->get_sites();
        $sites_with_stats = array();
        
        foreach ($sites as $site) {
            $stats = $this->get_site_stats($site->id);
            $sites_with_stats[] = array(
                'id' => $site->id,
                'name' => $site->name,
                'url' => $site->url,
                'status' => $site->status,
                'last_sync' => $site->last_sync,
                'stats' => $stats
            );
        }
        
        return $sites_with_stats;
    }
    
    public function get_site_stats($site_id) {
        $site = $this->db->get_site($site_id);
        
        if (!$site) {
            return array(
                'posts_count' => 0,
                'categories' => array()
            );
        }
        
        $stats = $this->api_client->get_site_info($site);
        
        if ($stats !== false) {
            // Aktualizuj czas ostatniej synchronizacji
            $this->db->update_site($site_id, array(
                'last_sync' => current_time('mysql'),
                'status' => 'active'
            ));
            
            return $stats;
        } else {
            // Oznacz jako błąd
            $this->db->update_site($site_id, array('status' => 'error'));
            
            return array(
                'posts_count' => 0,
                'categories' => array(),
                'error' => true
            );
        }
    }
    
    public function delete_site($site_id) {
        $site = $this->db->get_site($site_id);
        
        if (!$site) {
            return array(
                'success' => false,
                'message' => __('Strona nie została znaleziona', 'produktor-wp')
            );
        }
        
        $result = $this->db->delete_site($site_id);
        
        if ($result !== false) {
            $this->logger->log(null, 'delete_site', sprintf(__('Usunięto stronę: %s', 'produktor-wp'), $site->name));
            
            return array(
                'success' => true,
                'message' => __('Strona została usunięta', 'produktor-wp')
            );
        } else {
            return array(
                'success' => false,
                'message' => __('Błąd podczas usuwania strony', 'produktor-wp')
            );
        }
    }
    
    public function update_site_status($site_id, $status) {
        return $this->db->update_site($site_id, array('status' => $status));
    }
    
    public function sync_all_sites() {
        $sites = $this->db->get_sites();
        $results = array();
        
        foreach ($sites as $site) {
            $stats = $this->get_site_stats($site->id);
            $results[$site->id] = $stats;
        }
        
        return $results;
    }
}