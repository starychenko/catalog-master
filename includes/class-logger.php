<?php
/**
 * Logger class for Catalog Master
 */

if (!defined('ABSPATH')) {
    exit;
}

class CatalogMaster_Logger {
    
    private static $log_file = null;
    private static $debug_mode = null;
    
    /**
     * Initialize logger
     */
    public static function init() {
        $upload_dir = wp_upload_dir();
        self::$log_file = $upload_dir['basedir'] . '/catalog-master-debug.log';
        
        // Enable debug mode if WP_DEBUG is on or plugin debug option is set
        self::$debug_mode = defined('WP_DEBUG') && WP_DEBUG || get_option('catalog_master_debug', false);
    }
    
    /**
     * Log error message
     */
    public static function error($message, $context = array()) {
        self::log('ERROR', $message, $context);
    }
    
    /**
     * Log warning message
     */
    public static function warning($message, $context = array()) {
        self::log('WARNING', $message, $context);
    }
    
    /**
     * Log info message
     */
    public static function info($message, $context = array()) {
        self::log('INFO', $message, $context);
    }
    
    /**
     * Log debug message (only if debug mode is enabled)
     */
    public static function debug($message, $context = array()) {
        if (self::$debug_mode) {
            self::log('DEBUG', $message, $context);
        }
    }
    
    /**
     * Log database error
     */
    public static function db_error($operation, $error_message, $query = '') {
        global $wpdb;
        
        $context = array(
            'operation' => $operation,
            'error' => $error_message,
            'query' => $query,
            'wpdb_last_error' => $wpdb->last_error,
            'wpdb_last_query' => $wpdb->last_query
        );
        
        self::error("Database Error in {$operation}: {$error_message}", $context);
    }
    
    /**
     * Main logging function
     */
    private static function log($level, $message, $context = array()) {
        if (self::$log_file === null) {
            self::init();
        }
        
        $timestamp = current_time('Y-m-d H:i:s');
        $user_id = get_current_user_id();
        $user_data = $user_id ? get_userdata($user_id) : false;
        $user_login = $user_data ? $user_data->user_login : 'guest';
        
        $log_entry = sprintf(
            "[%s] %s [User: %s] %s",
            $timestamp,
            $level,
            $user_login,
            $message
        );
        
        // Add context if provided
        if (!empty($context)) {
            $log_entry .= "\nContext: " . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        
        $log_entry .= "\n" . str_repeat('-', 80) . "\n";
        
        // Write to file
        error_log($log_entry, 3, self::$log_file);
        
        // Also log to WordPress debug log if WP_DEBUG_LOG is enabled
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log("Catalog Master [{$level}]: {$message}");
        }
    }
    
    /**
     * Get log file path
     */
    public static function get_log_file() {
        if (self::$log_file === null) {
            self::init();
        }
        return self::$log_file;
    }
    
    /**
     * Get recent log entries
     */
    public static function get_recent_logs($lines = 50) {
        if (!file_exists(self::get_log_file())) {
            return 'Файл логів не знайдено.';
        }
        
        $log_content = file_get_contents(self::get_log_file());
        $log_lines = explode("\n", $log_content);
        
        return implode("\n", array_slice($log_lines, -$lines));
    }
    
    /**
     * Clear log file
     */
    public static function clear_logs() {
        if (file_exists(self::get_log_file())) {
            return unlink(self::get_log_file());
        }
        return true;
    }
    
    /**
     * Log WordPress database errors
     */
    public static function log_wp_error($wp_error, $operation = '') {
        if (is_wp_error($wp_error)) {
            $context = array(
                'operation' => $operation,
                'error_codes' => $wp_error->get_error_codes(),
                'error_messages' => $wp_error->get_error_messages(),
                'error_data' => $wp_error->get_error_data()
            );
            
            self::error("WordPress Error: " . $wp_error->get_error_message(), $context);
        }
    }
    
    /**
     * Log HTTP request errors
     */
    public static function log_http_error($response, $url = '') {
        if (is_wp_error($response)) {
            $context = array(
                'url' => $url,
                'error_code' => $response->get_error_code(),
                'error_message' => $response->get_error_message()
            );
            
            self::error("HTTP Request Error: " . $response->get_error_message(), $context);
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code >= 400) {
                $context = array(
                    'url' => $url,
                    'response_code' => $response_code,
                    'response_message' => wp_remote_retrieve_response_message($response)
                );
                
                self::error("HTTP Error {$response_code}: " . wp_remote_retrieve_response_message($response), $context);
            }
        }
    }
    
    /**
     * Enable debug mode
     */
    public static function enable_debug() {
        update_option('catalog_master_debug', true);
        self::$debug_mode = true;
        self::info('Debug mode enabled');
    }
    
    /**
     * Disable debug mode
     */
    public static function disable_debug() {
        update_option('catalog_master_debug', false);
        self::$debug_mode = false;
        self::info('Debug mode disabled');
    }
    
    /**
     * Check if debug mode is enabled
     */
    public static function is_debug_enabled() {
        if (self::$debug_mode === null) {
            self::init();
        }
        return self::$debug_mode;
    }
} 