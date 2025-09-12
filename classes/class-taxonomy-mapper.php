<?php

if (!defined('ABSPATH')) {
    exit;
}

class ProduktorWP_TaxonomyMapper {
    
    private $db;
    private $api_client;
    
    public function __construct() {
        $this->db = new ProduktorWP_Database();
        $this->api_client = new ProduktorWP_APIClient();
    }
    
    public function map_post_taxonomies($post_data, $post_id, $site_id) {
        // Pobierz kategorie i tagi postu
        $categories = wp_get_post_categories($post_id);
        $tags = wp_get_post_tags($post_id);
        
        if (!empty($categories)) {
            $mapped_categories = $this->map_terms($categories, 'category', $site_id);
            if (!empty($mapped_categories)) {
                $post_data['categories'] = $mapped_categories;
            }
        }
        
        if (!empty($tags)) {
            $mapped_tags = $this->map_terms($tags, 'post_tag', $site_id);
            if (!empty($mapped_tags)) {
                $post_data['tags'] = $mapped_tags;
            }
        }
        
        return $post_data;
    }
    
    private function map_terms($term_ids, $taxonomy, $site_id) {
        global $wpdb;
        
        $mapped_ids = array();
        
        foreach ($term_ids as $term_id) {
            // Sprawdź czy jest już mapowanie
            $mapping = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}produktor_wp_taxonomy_mappings 
                 WHERE site_id = %d AND local_taxonomy = %s AND local_term_id = %d",
                $site_id, $taxonomy, $term_id
            ));
            
            if ($mapping) {
                $mapped_ids[] = $mapping->remote_term_id;
            } else {
                // Automatyczne mapowanie
                $remote_id = $this->auto_map_term($term_id, $taxonomy, $site_id);
                if ($remote_id) {
                    $mapped_ids[] = $remote_id;
                }
            }
        }
        
        return $mapped_ids;
    }
    
    private function auto_map_term($term_id, $taxonomy, $site_id) {
        $term = get_term($term_id, $taxonomy);
        
        if (is_wp_error($term) || !$term) {
            return false;
        }
        
        $site = $this->db->get_site($site_id);
        if (!$site) {
            return false;
        }
        
        // Pobierz kategorie ze zdalnej strony
        $remote_categories = $this->api_client->get_categories($site);
        
        // Spróbuj znaleźć po nazwie lub slug
        foreach ($remote_categories as $remote_cat) {
            if ($remote_cat['name'] === $term->name || $remote_cat['slug'] === $term->slug) {
                // Zapisz mapowanie
                $this->save_mapping($site_id, $taxonomy, $term_id, $taxonomy, $remote_cat['id'], 'auto');
                return $remote_cat['id'];
            }
        }
        
        // Jeśli nie znaleziono, utwórz nową kategorię (fallback)
        $new_category = $this->create_remote_category($site, $term);
        if ($new_category) {
            $this->save_mapping($site_id, $taxonomy, $term_id, $taxonomy, $new_category['id'], 'auto');
            return $new_category['id'];
        }
        
        return false;
    }
    
    private function create_remote_category($site, $term) {
        $url = trailingslashit($site->url) . 'wp-json/wp/v2/categories';
        
        $category_data = array(
            'name' => $term->name,
            'slug' => $term->slug,
            'description' => $term->description
        );
        
        $response = wp_remote_post($url, array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($site->username . ':' . $site->password),
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($category_data)
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if (isset($result['id'])) {
            return $result;
        }
        
        return false;
    }
    
    private function save_mapping($site_id, $local_taxonomy, $local_term_id, $remote_taxonomy, $remote_term_id, $type = 'manual') {
        global $wpdb;
        
        return $wpdb->insert(
            $wpdb->prefix . 'produktor_wp_taxonomy_mappings',
            array(
                'site_id' => $site_id,
                'local_taxonomy' => $local_taxonomy,
                'local_term_id' => $local_term_id,
                'remote_taxonomy' => $remote_taxonomy,
                'remote_term_id' => $remote_term_id,
                'mapping_type' => $type
            ),
            array('%d', '%s', '%d', '%s', '%d', '%s')
        );
    }
    
    public function get_mappings($site_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}produktor_wp_taxonomy_mappings WHERE site_id = %d ORDER BY local_taxonomy, local_term_id",
            $site_id
        ));
    }
    
    public function delete_mapping($mapping_id) {
        global $wpdb;
        
        return $wpdb->delete(
            $wpdb->prefix . 'produktor_wp_taxonomy_mappings',
            array('id' => $mapping_id),
            array('%d')
        );
    }
    
    public function update_mapping($mapping_id, $remote_term_id) {
        global $wpdb;
        
        return $wpdb->update(
            $wpdb->prefix . 'produktor_wp_taxonomy_mappings',
            array('remote_term_id' => $remote_term_id, 'mapping_type' => 'manual'),
            array('id' => $mapping_id),
            array('%d', '%s'),
            array('%d')
        );
    }
}