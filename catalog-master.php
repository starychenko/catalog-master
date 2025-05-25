<?php
/**
 * Plugin Name: Catalog Master
 * Plugin URI: https://github.com/starychenko/catalog-master
 * Description: Professional WordPress plugin for managing product catalogs with Google Sheets XLSX integration, batch import system, and automated image processing
 * Version: 1.1.7
 * Author: Yevhenii Starychenko
 * Author URI: https://t.me/evgeniistarychenko
 * Text Domain: catalog-master
 * Domain Path: /languages
 * Requires at least: 5.6
 * Tested up to: 6.3
 * Requires PHP: 7.2
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * 
 * Network: false
 * 
 * @package CatalogMaster
 * @version 1.1.7
 * @author Yevhenii Starychenko
 * @license MIT
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// IDE Helper for WordPress functions (only for development)
if (!defined('ABSPATH') && file_exists(__DIR__ . '/includes/ide-helper.php')) {
    require_once __DIR__ . '/includes/ide-helper.php';
}

// Define plugin constants
define('CATALOG_MASTER_VERSION', '1.1.7');
define('CATALOG_MASTER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CATALOG_MASTER_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CATALOG_MASTER_PLUGIN_FILE', __FILE__);

/**
 * Plugin activation function
 */
function catalog_master_activate() {
    // Load required files for activation
    require_once CATALOG_MASTER_PLUGIN_PATH . 'includes/class-logger.php';
    require_once CATALOG_MASTER_PLUGIN_PATH . 'includes/class-database.php';
    
    // Initialize logger
    CatalogMaster_Logger::init();
    
    // Log activation
    CatalogMaster_Logger::info('Plugin activation started');
    
    // Create database tables
    CatalogMaster_Database::create_tables();
    
    // Log completion
    CatalogMaster_Logger::info('Plugin activation completed successfully');
}

/**
 * Plugin deactivation function  
 */
function catalog_master_deactivate() {
    // Load logger for deactivation logging
    if (file_exists(CATALOG_MASTER_PLUGIN_PATH . 'includes/class-logger.php')) {
        require_once CATALOG_MASTER_PLUGIN_PATH . 'includes/class-logger.php';
        CatalogMaster_Logger::init();
        CatalogMaster_Logger::info('Plugin deactivated');
    }
}

/**
 * Plugin uninstall function
 */
function catalog_master_uninstall() {
    // Load required files for uninstall
    require_once CATALOG_MASTER_PLUGIN_PATH . 'includes/class-logger.php';
    require_once CATALOG_MASTER_PLUGIN_PATH . 'includes/class-database.php';
    
    // Initialize logger
    CatalogMaster_Logger::init();
    CatalogMaster_Logger::info('Plugin uninstall started');
    
    // Drop database tables
    CatalogMaster_Database::drop_tables();
    
    // Clear logs (optional)
    CatalogMaster_Logger::clear_logs();
    
    CatalogMaster_Logger::info('Plugin uninstall completed');
}

// Register activation/deactivation/uninstall hooks
register_activation_hook(__FILE__, 'catalog_master_activate');
register_deactivation_hook(__FILE__, 'catalog_master_deactivate');
register_uninstall_hook(__FILE__, 'catalog_master_uninstall');

// Main plugin class
class CatalogMaster {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    public function init() {
        // Load text domain
        $plugin_basename = plugin_basename(__FILE__);
        $plugin_dir = $plugin_basename ? dirname($plugin_basename) : 'catalog-master';
        load_plugin_textdomain('catalog-master', false, $plugin_dir . '/languages');
        
        // Initialize plugin components
        $this->includes();
        $this->init_hooks();
    }
    
    private function includes() {
        require_once CATALOG_MASTER_PLUGIN_PATH . 'includes/class-logger.php';
        require_once CATALOG_MASTER_PLUGIN_PATH . 'includes/class-database.php';
        require_once CATALOG_MASTER_PLUGIN_PATH . 'includes/class-admin.php';
        require_once CATALOG_MASTER_PLUGIN_PATH . 'includes/class-google-sheets.php';
        require_once CATALOG_MASTER_PLUGIN_PATH . 'includes/class-exporter.php';
        require_once CATALOG_MASTER_PLUGIN_PATH . 'includes/class-ajax.php';
        
        // Initialize logger
        CatalogMaster_Logger::init();
    }
    
    private function init_hooks() {
        // Initialize admin interface
        if (is_admin()) {
            new CatalogMaster_Admin();
        }
        
        // Initialize AJAX handlers
        new CatalogMaster_Ajax();
        
        // Initialize Exporter
        new CatalogMaster_Exporter();
    }
}

// Initialize the plugin
CatalogMaster::get_instance(); 