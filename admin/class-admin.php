<?php

if (!defined('ABSPATH')) {
    exit;
}

class ProduktorWP_Admin {
    
    private $db;
    private $site_manager;
    private $post_manager;
    private $logger;
    
    public function __construct() {
        $this->db = new ProduktorWP_Database();
        $this->site_manager = new ProduktorWP_SiteManager();
        $this->post_manager = new ProduktorWP_PostManager();
        $this->logger = new ProduktorWP_Logger();
        
        add_action('admin_init', array($this, 'admin_init'));
    }
    
    public function admin_init() {
        // Dodatkowe hooks dla administratora
    }
    
    public function render_main_page() {
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'dashboard';
        
        ?>
        <div class="wrap produktor-wp-admin">
            <h1 class="wp-heading-inline"><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <nav class="nav-tab-wrapper wp-clearfix">
                <a href="<?php echo admin_url('admin.php?page=produktor-wp&tab=dashboard'); ?>" 
                   class="nav-tab <?php echo $current_tab === 'dashboard' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Dashboard', 'produktor-wp'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=produktor-wp&tab=sites'); ?>" 
                   class="nav-tab <?php echo $current_tab === 'sites' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Strony zewnętrzne', 'produktor-wp'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=produktor-wp&tab=bulk'); ?>" 
                   class="nav-tab <?php echo $current_tab === 'bulk' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Hurtowe dodawanie', 'produktor-wp'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=produktor-wp&tab=taxonomy'); ?>" 
                   class="nav-tab <?php echo $current_tab === 'taxonomy' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Mapowanie taksonomii', 'produktor-wp'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=produktor-wp&tab=logs'); ?>" 
                   class="nav-tab <?php echo $current_tab === 'logs' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Logi i błędy', 'produktor-wp'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=produktor-wp&tab=reports'); ?>" 
                   class="nav-tab <?php echo $current_tab === 'reports' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Raportowanie', 'produktor-wp'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=produktor-wp&tab=archive'); ?>" 
                   class="nav-tab <?php echo $current_tab === 'archive' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Archiwum', 'produktor-wp'); ?>
                </a>
            </nav>
            
            <div class="produktor-wp-content">
                <?php
                switch($current_tab) {
                    case 'dashboard':
                        $this->render_dashboard();
                        break;
                    case 'sites':
                        $this->render_sites_page();
                        break;
                    case 'bulk':
                        $this->render_bulk_page();
                        break;
                    case 'taxonomy':
                        $this->render_taxonomy_page();
                        break;
                    case 'logs':
                        $this->render_logs_page();
                        break;
                    case 'reports':
                        $this->render_reports_page();
                        break;
                    case 'archive':
                        $this->render_archive_page();
                        break;
                    default:
                        $this->render_dashboard();
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    private function render_dashboard() {
        $sites = $this->site_manager->get_sites_with_stats();
        $recent_logs = $this->logger->get_recent_activities(5);
        $error_count = $this->logger->get_error_count();
        ?>
        <div class="produktor-wp-dashboard">
            <div class="dashboard-widgets">
                <div class="widget stats-widget">
                    <h3><?php _e('Statystyki', 'produktor-wp'); ?></h3>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo count($sites); ?></div>
                            <div class="stat-label"><?php _e('Połączonych stron', 'produktor-wp'); ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo count($this->post_manager->get_available_posts()); ?></div>
                            <div class="stat-label"><?php _e('Dostępnych postów', 'produktor-wp'); ?></div>
                        </div>
                        <div class="stat-item error">
                            <div class="stat-number"><?php echo $error_count; ?></div>
                            <div class="stat-label"><?php _e('Błędów (7 dni)', 'produktor-wp'); ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="widget sites-widget">
                    <h3><?php _e('Przegląd stron', 'produktor-wp'); ?></h3>
                    <div class="sites-list">
                        <?php foreach($sites as $site): ?>
                        <div class="site-item status-<?php echo esc_attr($site['status']); ?>">
                            <div class="site-info">
                                <strong><?php echo esc_html($site['name']); ?></strong>
                                <span class="site-url"><?php echo esc_html($site['url']); ?></span>
                            </div>
                            <div class="site-stats">
                                <span class="posts-count"><?php echo $site['stats']['posts_count']; ?> postów</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="widget activity-widget">
                    <h3><?php _e('Ostatnia aktywność', 'produktor-wp'); ?></h3>
                    <div class="activity-list">
                        <?php foreach($recent_logs as $log): ?>
                        <div class="activity-item level-<?php echo esc_attr($log->level); ?>">
                            <div class="activity-time"><?php echo date('H:i', strtotime($log->created_at)); ?></div>
                            <div class="activity-content">
                                <strong><?php echo esc_html($log->site_name ?: 'System'); ?></strong>
                                <span><?php echo esc_html($log->message); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function render_sites_page() {
        $sites = $this->site_manager->get_sites_with_stats();
        ?>
        <div class="produktor-wp-sites">
            <div class="page-header">
                <h2><?php _e('Zarządzanie stronami zewnętrznymi', 'produktor-wp'); ?></h2>
                <button type="button" class="button button-primary" id="add-site-btn">
                    <?php _e('Dodaj nową stronę', 'produktor-wp'); ?>
                </button>
            </div>
            
            <div class="sites-table-container">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Nazwa', 'produktor-wp'); ?></th>
                            <th><?php _e('URL', 'produktor-wp'); ?></th>
                            <th><?php _e('Status', 'produktor-wp'); ?></th>
                            <th><?php _e('Liczba postów', 'produktor-wp'); ?></th>
                            <th><?php _e('Kategorie', 'produktor-wp'); ?></th>
                            <th><?php _e('Ostatnia sync', 'produktor-wp'); ?></th>
                            <th><?php _e('Akcje', 'produktor-wp'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($sites as $site): ?>
                        <tr>
                            <td><strong><?php echo esc_html($site['name']); ?></strong></td>
                            <td><?php echo esc_html($site['url']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr($site['status']); ?>">
                                    <?php echo ucfirst($site['status']); ?>
                                </span>
                            </td>
                            <td><?php echo $site['stats']['posts_count']; ?></td>
                            <td>
                                <select class="categories-select" data-site-id="<?php echo $site['id']; ?>">
                                    <?php foreach($site['stats']['categories'] as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo esc_html($category['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <?php 
                                if($site['last_sync']) {
                                    echo date('Y-m-d H:i', strtotime($site['last_sync']));
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <button type="button" class="button button-small add-post-btn" 
                                        data-site-id="<?php echo $site['id']; ?>">
                                    <?php _e('Dodaj artykuł', 'produktor-wp'); ?>
                                </button>
                                <button type="button" class="button button-small sync-site-btn" 
                                        data-site-id="<?php echo $site['id']; ?>">
                                    <?php _e('Sync', 'produktor-wp'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Modal dodawania strony -->
        <div id="add-site-modal" class="produktor-wp-modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><?php _e('Dodaj nową stronę', 'produktor-wp'); ?></h3>
                    <button type="button" class="modal-close">&times;</button>
                </div>
                <form id="add-site-form">
                    <div class="form-group">
                        <label for="site-name"><?php _e('Nazwa strony', 'produktor-wp'); ?></label>
                        <input type="text" id="site-name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="site-url"><?php _e('URL strony', 'produktor-wp'); ?></label>
                        <input type="url" id="site-url" name="url" required 
                               placeholder="https://example.com">
                    </div>
                    <div class="form-group">
                        <label for="site-username"><?php _e('Nazwa użytkownika aplikacji', 'produktor-wp'); ?></label>
                        <input type="text" id="site-username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="site-password"><?php _e('Hasło aplikacji', 'produktor-wp'); ?></label>
                        <input type="password" id="site-password" name="password" required>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="button" id="test-connection-btn">
                            <?php _e('Testuj połączenie', 'produktor-wp'); ?>
                        </button>
                        <button type="submit" class="button button-primary">
                            <?php _e('Dodaj stronę', 'produktor-wp'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
    
    private function render_bulk_page() {
        $available_posts = $this->post_manager->get_available_posts();
        $sites = $this->db->get_sites();
        ?>
        <div class="produktor-wp-bulk">
            <div class="page-header">
                <h2><?php _e('Hurtowe dodawanie artykułów', 'produktor-wp'); ?></h2>
                <p class="description">
                    <?php _e('Wybierz artykuły i stronę docelową, ustaw daty publikacji i rozpocznij masowe dodawanie.', 'produktor-wp'); ?>
                </p>
            </div>
            
            <div class="bulk-controls">
                <div class="control-group">
                    <label><?php _e('Strona docelowa:', 'produktor-wp'); ?></label>
                    <select id="target-site" required>
                        <option value=""><?php _e('Wybierz stronę...', 'produktor-wp'); ?></option>
                        <?php foreach($sites as $site): ?>
                        <option value="<?php echo $site->id; ?>">
                            <?php echo esc_html($site->name); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="control-group">
                    <label><?php _e('Przedział dat publikacji:', 'produktor-wp'); ?></label>
                    <div class="date-range">
                        <input type="datetime-local" id="date-from" required>
                        <span><?php _e('do', 'produktor-wp'); ?></span>
                        <input type="datetime-local" id="date-to" required>
                    </div>
                </div>
                
                <div class="control-group">
                    <label><?php _e('Autor:', 'produktor-wp'); ?></label>
                    <select id="target-author">
                        <option value=""><?php _e('Domyślny', 'produktor-wp'); ?></option>
                    </select>
                </div>
                
                <div class="control-group">
                    <label><?php _e('Akcja przy duplikatach:', 'produktor-wp'); ?></label>
                    <select id="duplicate-action">
                        <option value="skip"><?php _e('Pomiń', 'produktor-wp'); ?></option>
                        <option value="replace"><?php _e('Zastąp', 'produktor-wp'); ?></option>
                    </select>
                </div>
            </div>
            
            <div class="posts-selection">
                <div class="selection-header">
                    <h3><?php _e('Dostępne artykuły', 'produktor-wp'); ?></h3>
                    <div class="selection-controls">
                        <button type="button" class="button" id="select-all-posts">
                            <?php _e('Zaznacz wszystkie', 'produktor-wp'); ?>
                        </button>
                        <button type="button" class="button" id="clear-selection">
                            <?php _e('Wyczyść zaznaczenie', 'produktor-wp'); ?>
                        </button>
                        <span class="selected-count">0 <?php _e('wybranych', 'produktor-wp'); ?></span>
                    </div>
                </div>
                
                <div class="posts-table-container">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <td class="check-column">
                                    <input type="checkbox" id="select-all-checkbox">
                                </td>
                                <th><?php _e('Tytuł', 'produktor-wp'); ?></th>
                                <th><?php _e('Kategorie', 'produktor-wp'); ?></th>
                                <th><?php _e('Data utworzenia', 'produktor-wp'); ?></th>
                                <th><?php _e('Status', 'produktor-wp'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($available_posts as $post): ?>
                            <tr>
                                <th class="check-column">
                                    <input type="checkbox" name="selected_posts[]" 
                                           value="<?php echo $post->ID; ?>" class="post-checkbox">
                                </th>
                                <td>
                                    <strong><?php echo esc_html($post->post_title); ?></strong>
                                    <div class="post-excerpt">
                                        <?php echo wp_trim_words($post->post_content, 20); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $categories = get_the_category($post->ID);
                                    if($categories) {
                                        echo implode(', ', array_map(function($cat) { return $cat->name; }, $categories));
                                    }
                                    ?>
                                </td>
                                <td><?php echo date('Y-m-d H:i', strtotime($post->post_date)); ?></td>
                                <td>
                                    <span class="status-badge status-available">
                                        <?php _e('Dostępny', 'produktor-wp'); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="bulk-actions">
                <button type="button" class="button button-primary button-large" id="start-bulk-publish">
                    <?php _e('Rozpocznij publikację', 'produktor-wp'); ?>
                </button>
                <div class="progress-container" style="display: none;">
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                    </div>
                    <div class="progress-text">0%</div>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function render_taxonomy_page() {
        // Implementacja strony mapowania taksonomii
        echo '<div class="produktor-wp-taxonomy">';
        echo '<h2>' . __('Mapowanie taksonomii', 'produktor-wp') . '</h2>';
        echo '<p>' . __('Funkcjonalność w rozwoju...', 'produktor-wp') . '</p>';
        echo '</div>';
    }
    
    private function render_logs_page() {
        // Implementacja strony logów
        echo '<div class="produktor-wp-logs">';
        echo '<h2>' . __('Logi i błędy', 'produktor-wp') . '</h2>';
        echo '<p>' . __('Funkcjonalność w rozwoju...', 'produktor-wp') . '</p>';
        echo '</div>';
    }
    
    private function render_reports_page() {
        // Implementacja strony raportów
        echo '<div class="produktor-wp-reports">';
        echo '<h2>' . __('Raportowanie', 'produktor-wp') . '</h2>';
        echo '<p>' . __('Funkcjonalność w rozwoju...', 'produktor-wp') . '</p>';
        echo '</div>';
    }
    
    private function render_archive_page() {
        // Implementacja strony archiwum
        echo '<div class="produktor-wp-archive">';
        echo '<h2>' . __('Archiwum wykorzystanych artykułów', 'produktor-wp') . '</h2>';
        echo '<p>' . __('Funkcjonalność w rozwoju...', 'produktor-wp') . '</p>';
        echo '</div>';
    }
}