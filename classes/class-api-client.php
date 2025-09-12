<?php

if (!defined('ABSPATH')) {
    exit;
}

class ProduktorWP_APIClient {
    
    private $timeout = 30;
    
    public function test_connection($site_data) {
        $url = trailingslashit(esc_url_raw($site_data['url'])) . 'wp-json/wp/v2/posts';
        
        $response = wp_remote_get($url, array(
            'timeout' => $this->timeout,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($site_data['username'] . ':' . $site_data['password'])
            )
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code === 200) {
            return array(
                'success' => true,
                'message' => __('Połączenie udane', 'produktor-wp')
            );
        } else {
            return array(
                'success' => false,
                'message' => sprintf(__('Błąd połączenia: %d', 'produktor-wp'), $code)
            );
        }
    }
    
    public function get_site_info($site) {
        $url = trailingslashit($site->url) . 'wp-json/wp/v2/posts';
        
        $response = wp_remote_get($url, array(
            'timeout' => $this->timeout,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($site->username . ':' . $site->password)
            )
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $headers = wp_remote_retrieve_headers($response);
        $total_posts = isset($headers['X-WP-Total']) ? $headers['X-WP-Total'] : 0;
        
        // Pobierz kategorie
        $categories = $this->get_categories($site);
        
        return array(
            'posts_count' => $total_posts,
            'categories' => $categories
        );
    }
    
    public function get_categories($site) {
        $url = trailingslashit($site->url) . 'wp-json/wp/v2/categories';
        
        $response = wp_remote_get($url, array(
            'timeout' => $this->timeout,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($site->username . ':' . $site->password)
            )
        ));
        
        if (is_wp_error($response)) {
            return array();
        }
        
        $body = wp_remote_retrieve_body($response);
        $categories = json_decode($body, true);
        
        if (!is_array($categories)) {
            return array();
        }
        
        return array_map(function($cat) {
            return array(
                'id' => $cat['id'],
                'name' => $cat['name'],
                'slug' => $cat['slug']
            );
        }, $categories);
    }
    
    public function create_post($site, $post_data) {
        $url = trailingslashit($site->url) . 'wp-json/wp/v2/posts';
        
        $response = wp_remote_post($url, array(
            'timeout' => $this->timeout,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($site->username . ':' . $site->password),
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($post_data)
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if ($code === 201 && isset($result['id'])) {
            return array(
                'success' => true,
                'post_id' => $result['id'],
                'data' => $result
            );
        } else {
            return array(
                'success' => false,
                'message' => isset($result['message']) ? $result['message'] : __('Błąd publikacji', 'produktor-wp')
            );
        }
    }
    
    public function upload_media($site, $file_path, $filename = null) {
        if (!file_exists($file_path)) {
            return false;
        }
        
        $url = trailingslashit($site->url) . 'wp-json/wp/v2/media';
        
        $boundary = wp_generate_uuid4();
        $body = '';
        
        // Przygotuj multipart body
        $file_content = file_get_contents($file_path);
        $mime_type = wp_check_filetype($file_path)['type'];
        $filename = $filename ?: basename($file_path);
        
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\n";
        $body .= "Content-Type: {$mime_type}\r\n\r\n";
        $body .= $file_content . "\r\n";
        $body .= "--{$boundary}--\r\n";
        
        $response = wp_remote_post($url, array(
            'timeout' => 60,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($site->username . ':' . $site->password),
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ),
            'body' => $body
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $result = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($result['id'])) {
            return $result;
        }
        
        return false;
    }
    
    public function check_duplicate_post($site, $title) {
        $url = trailingslashit($site->url) . 'wp-json/wp/v2/posts';
        
        $response = wp_remote_get(add_query_arg(array(
            'search' => $title,
            'per_page' => 1
        ), $url), array(
            'timeout' => $this->timeout,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($site->username . ':' . $site->password)
            )
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $posts = json_decode($body, true);
        
        if (is_array($posts) && count($posts) > 0) {
            foreach ($posts as $post) {
                if (strtolower($post['title']['rendered']) === strtolower($title)) {
                    return $post;
                }
            }
        }
        
        return false;
    }
    
    public function get_users($site) {
        $url = trailingslashit($site->url) . 'wp-json/wp/v2/users';
        
        $response = wp_remote_get($url, array(
            'timeout' => $this->timeout,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($site->username . ':' . $site->password)
            )
        ));
        
        if (is_wp_error($response)) {
            return array();
        }
        
        $body = wp_remote_retrieve_body($response);
        $users = json_decode($body, true);
        
        if (!is_array($users)) {
            return array();
        }
        
        return array_map(function($user) {
            return array(
                'id' => $user['id'],
                'name' => $user['name'],
                'slug' => $user['slug']
            );
        }, $users);
    }
}