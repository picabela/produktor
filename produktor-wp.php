<?php
/**
 * Plugin Name: Produktor WP
 * Plugin URI: https://github.com/bziku/produktor-wp
 * Description: Panel zarządzania wieloma stronami WordPress z możliwością hurtowego dodawania artykułów
 * Version: 1.0.0
 * Author: Bziku
 * License: GPL v2 or later
 * Text Domain: produktor-wp
 * Domain Path: /languages
 */

// Zapobieganie bezpośredniemu dostępowi
if (!defined('ABSPATH')) {
    exit;
}

// Definicje stałych
define('PRODUKTOR_WP_VERSION', '1.0.0');
define('PRODUKTOR_WP_PLUGIN_FILE', __FILE__);
define('PRODUKTOR_WP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PRODUKTOR_WP_PLUGIN_URL', plugin_dir_url(__FILE__));

class ProduktorWP {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Ładowanie tłumaczeń
        load_plugin_textdomain('produktor-wp', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Inicjalizacja klas
        $this->includes();
        
        // Inicjalizacja hooks
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('wp_ajax_produktor_wp_action', array($this, 'ajax_handler'));
    }
    
    private function includes() {
        require_once PRODUKTOR_WP_PLUGIN_DIR . 'classes/class-database.php';
        require_once PRODUKTOR_WP_PLUGIN_DIR . 'classes/class-api-client.php';
        require_once PRODUKTOR_WP_PLUGIN_DIR . 'classes/class-site-manager.php';
        require_once PRODUKTOR_WP_PLUGIN_DIR . 'classes/class-post-manager.php';
        require_once PRODUKTOR_WP_PLUGIN_DIR . 'classes/class-taxonomy-mapper.php';
        require_once PRODUKTOR_WP_PLUGIN_DIR . 'classes/class-logger.php';
        require_once PRODUKTOR_WP_PLUGIN_DIR . 'admin/class-admin.php';
        
        // Inicjalizacja klas
        new ProduktorWP_Database();
        new ProduktorWP_Admin();
    }
    
    public function admin_menu() {
        add_menu_page(
            __('Produktor WP', 'produktor-wp'),
            __('Produktor WP', 'produktor-wp'),
            'manage_options',
            'produktor-wp',
            array($this, 'admin_page'),
            'dashicons-networking',
            30
        );
    }
    
    public function admin_assets($hook) {
        if (strpos($hook, 'produktor-wp') !== false) {
            wp_enqueue_style('produktor-wp-admin', PRODUKTOR_WP_PLUGIN_URL . 'assets/css/admin.css', array(), PRODUKTOR_WP_VERSION);
            wp_enqueue_script('produktor-wp-admin', PRODUKTOR_WP_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'wp-editor'), PRODUKTOR_WP_VERSION, true);
            
            wp_localize_script('produktor-wp-admin', 'produktorWP', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('produktor_wp_nonce'),
                'strings' => array(
                    'confirm_delete' => __('Czy na pewno chcesz usunąć?', 'produktor-wp'),
                    'success' => __('Operacja zakończona sukcesem', 'produktor-wp'),
                    'error' => __('Wystąpił błąd', 'produktor-wp'),
                )
            ));
        }
    }
    
    public function admin_page() {
        $admin = new ProduktorWP_Admin();
        $admin->render_main_page();
    }
    
    public function ajax_handler() {
        check_ajax_referer('produktor_wp_nonce', 'nonce');
        
        $action = sanitize_text_field($_POST['sub_action']);
        
        switch($action) {
            case 'add_site':
                $this->handle_add_site();
                break;
            case 'test_connection':
                $this->handle_test_connection();
                break;
            case 'get_site_stats':
                $this->handle_get_site_stats();
                break;
            case 'bulk_publish':
                $this->handle_bulk_publish();
                break;
            case 'get_logs':
                $this->handle_get_logs();
                break;
            default:
                wp_die('Invalid action');
        }
    }
    
    private function handle_add_site() {
        $site_manager = new ProduktorWP_SiteManager();
        $result = $site_manager->add_site($_POST);
        wp_send_json($result);
    }
    
    private function handle_test_connection() {
        $api_client = new ProduktorWP_APIClient();
        $result = $api_client->test_connection($_POST);
        wp_send_json($result);
    }
    
    private function handle_get_site_stats() {
        $site_manager = new ProduktorWP_SiteManager();
        $result = $site_manager->get_site_stats($_POST['site_id']);
        wp_send_json($result);
    }
    
    private function handle_bulk_publish() {
        $post_manager = new ProduktorWP_PostManager();
        $result = $post_manager->bulk_publish($_POST);
        wp_send_json($result);
    }
    
    private function handle_get_logs() {
        $logger = new ProduktorWP_Logger();
        $result = $logger->get_logs($_POST);
        wp_send_json($result);
    }
    
    public function activate() {
        $database = new ProduktorWP_Database();
        $database->create_tables();
        
        // Ustawienie domyślnych opcji
        update_option('produktor_wp_version', PRODUKTOR_WP_VERSION);
    }
    
    public function deactivate() {
        // Czyszczenie schedulowanych zadań
        wp_clear_scheduled_hook('produktor_wp_cleanup');
    }
}

// Inicjalizacja wtyczki
new ProduktorWP();