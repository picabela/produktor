<?php

if (!defined('ABSPATH')) {
    exit;
}

class ProduktorWP_PostManager {
    
    private $db;
    private $api_client;
    private $logger;
    private $taxonomy_mapper;
    
    public function __construct() {
        $this->db = new ProduktorWP_Database();
        $this->api_client = new ProduktorWP_APIClient();
        $this->logger = new ProduktorWP_Logger();
        $this->taxonomy_mapper = new ProduktorWP_TaxonomyMapper();
    }
    
    public function bulk_publish($data) {
        $post_ids = array_map('intval', $data['post_ids']);
        $site_id = intval($data['site_id']);
        $date_from = sanitize_text_field($data['date_from']);
        $date_to = sanitize_text_field($data['date_to']);
        $author_id = intval($data['author_id']);
        $duplicate_action = sanitize_text_field($data['duplicate_action']); // 'skip' or 'replace'
        
        if (empty($post_ids) || empty($site_id)) {
            return array(
                'success' => false,
                'message' => __('Nieprawidłowe dane', 'produktor-wp')
            );
        }
        
        $site = $this->db->get_site($site_id);
        if (!$site) {
            return array(
                'success' => false,
                'message' => __('Strona nie została znaleziona', 'produktor-wp')
            );
        }
        
        $results = array(
            'success' => 0,
            'skipped' => 0,
            'errors' => 0,
            'details' => array()
        );
        
        // Oblicz daty publikacji
        $publish_dates = $this->calculate_publish_dates($post_ids, $date_from, $date_to);
        
        foreach ($post_ids as $index => $post_id) {
            $result = $this->publish_single_post($post_id, $site, $publish_dates[$index], $author_id, $duplicate_action);
            
            if ($result['success']) {
                $results['success']++;
            } elseif ($result['skipped']) {
                $results['skipped']++;
            } else {
                $results['errors']++;
            }
            
            $results['details'][] = $result;
        }
        
        $this->logger->log($site_id, 'bulk_publish', sprintf(
            __('Publikacja hurtowa: %d sukces, %d pominięte, %d błędy', 'produktor-wp'),
            $results['success'],
            $results['skipped'],
            $results['errors']
        ));
        
        return array(
            'success' => true,
            'results' => $results
        );
    }
    
    private function publish_single_post($post_id, $site, $publish_date, $author_id, $duplicate_action) {
        $post = get_post($post_id);
        
        if (!$post) {
            return array(
                'success' => false,
                'skipped' => false,
                'message' => __('Post nie został znaleziony', 'produktor-wp'),
                'post_id' => $post_id
            );
        }
        
        // Sprawdź duplikaty
        $duplicate = $this->api_client->check_duplicate_post($site, $post->post_title);
        
        if ($duplicate && $duplicate_action === 'skip') {
            return array(
                'success' => false,
                'skipped' => true,
                'message' => __('Post o tym tytule już istnieje', 'produktor-wp'),
                'post_id' => $post_id,
                'post_title' => $post->post_title
            );
        }
        
        // Przygotuj dane postu
        $post_data = $this->prepare_post_data($post, $publish_date, $author_id);
        
        // Mapuj taksonomie
        $post_data = $this->taxonomy_mapper->map_post_taxonomies($post_data, $post_id, $site->id);
        
        // Publikuj post
        $result = $this->api_client->create_post($site, $post_data);
        
        if ($result['success']) {
            // Zapisz w bazie
            $this->db->add_post_record($post_id, $site->id, $result['post_id'], 'published');
            
            // Oznacz jako używany
            update_post_meta($post_id, '_produktor_wp_published_sites', $site->id);
            
            return array(
                'success' => true,
                'skipped' => false,
                'message' => __('Post opublikowany pomyślnie', 'produktor-wp'),
                'post_id' => $post_id,
                'remote_post_id' => $result['post_id'],
                'post_title' => $post->post_title
            );
        } else {
            // Zapisz błąd
            $record_id = $this->db->add_post_record($post_id, $site->id, null, 'error');
            $this->db->update_post_record($record_id, array('error_message' => $result['message']));
            
            $this->logger->log($site->id, 'publish_error', $result['message'], 'error', array(
                'post_id' => $post_id,
                'post_title' => $post->post_title
            ));
            
            return array(
                'success' => false,
                'skipped' => false,
                'message' => $result['message'],
                'post_id' => $post_id,
                'post_title' => $post->post_title
            );
        }
    }
    
    private function prepare_post_data($post, $publish_date, $author_id = null) {
        $post_data = array(
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'status' => 'publish',
            'date' => $publish_date
        );
        
        if ($author_id) {
            $post_data['author'] = $author_id;
        }
        
        // Dodaj featured image
        $featured_image_id = get_post_thumbnail_id($post->ID);
        if ($featured_image_id) {
            $image_url = wp_get_attachment_url($featured_image_id);
            // TODO: Upload image to remote site
            // $post_data['featured_media'] = $remote_image_id;
        }
        
        return $post_data;
    }
    
    private function calculate_publish_dates($post_ids, $date_from, $date_to) {
        $count = count($post_ids);
        
        if ($count === 1) {
            return array($date_from);
        }
        
        $from_timestamp = strtotime($date_from);
        $to_timestamp = strtotime($date_to);
        
        $interval = ($to_timestamp - $from_timestamp) / ($count - 1);
        
        $dates = array();
        
        for ($i = 0; $i < $count; $i++) {
            $timestamp = $from_timestamp + ($interval * $i);
            $dates[] = date('Y-m-d H:i:s', $timestamp);
        }
        
        return $dates;
    }
    
    public function get_available_posts() {
        return $this->db->get_available_posts();
    }
    
    public function archive_posts($post_ids) {
        foreach ($post_ids as $post_id) {
            update_post_meta($post_id, '_produktor_wp_exclude', true);
        }
        
        return true;
    }
    
    public function get_archived_posts() {
        return get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'numberposts' => -1,
            'meta_query' => array(
                array(
                    'key' => '_produktor_wp_exclude',
                    'value' => true,
                    'compare' => '='
                )
            )
        ));
    }
    
    public function restore_from_archive($post_ids) {
        foreach ($post_ids as $post_id) {
            delete_post_meta($post_id, '_produktor_wp_exclude');
        }
        
        return true;
    }
}