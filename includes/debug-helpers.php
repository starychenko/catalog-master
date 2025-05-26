<?php
/**
 * Функції дебагу для Catalog Master
 * 
 * @package CatalogMaster
 */

// Запобігання прямому доступу
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Логування повідомлень для Catalog Master
 * 
 * @param mixed $message Повідомлення для логування
 * @param string $level Рівень логування (info, warning, error, debug)
 * @param string $context Контекст (назва функції/класу)
 */
function catalog_master_log($message, $level = 'info', $context = '') {
    // Перевіряємо чи увімкнений дебаг
    if (!defined('CATALOG_MASTER_DEBUG') || !CATALOG_MASTER_DEBUG) {
        return;
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $level = strtoupper($level);
    
    // Форматування повідомлення
    $log_message = "[$timestamp] [CATALOG_MASTER] [$level]";
    
    if ($context) {
        $log_message .= " [$context]";
    }
    
    $log_message .= " ";
    
    if (is_array($message) || is_object($message)) {
        $log_message .= print_r($message, true);
    } else {
        $log_message .= $message;
    }
    
    // Логування в WordPress debug.log
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log($log_message);
    }
    
    // Додатково в PHP error log
    if (ini_get('log_errors')) {
        error_log($log_message);
    }
}

/**
 * Логування помилок
 */
function catalog_master_log_error($message, $context = '') {
    catalog_master_log($message, 'error', $context);
}

/**
 * Логування попереджень
 */
function catalog_master_log_warning($message, $context = '') {
    catalog_master_log($message, 'warning', $context);
}

/**
 * Логування інформації
 */
function catalog_master_log_info($message, $context = '') {
    catalog_master_log($message, 'info', $context);
}

/**
 * Логування дебаг інформації
 */
function catalog_master_log_debug($message, $context = '') {
    catalog_master_log($message, 'debug', $context);
}

/**
 * Дамп змінної з логуванням
 */
function catalog_master_dump($variable, $label = '', $context = '') {
    $dump = var_export($variable, true);
    $message = $label ? "$label: $dump" : $dump;
    catalog_master_log($message, 'debug', $context);
}

/**
 * Логування SQL запитів
 */
function catalog_master_log_sql($query, $context = '') {
    global $wpdb;
    
    if (!defined('CATALOG_MASTER_DEBUG') || !CATALOG_MASTER_DEBUG) {
        return;
    }
    
    $message = "SQL Query: $query";
    
    // Додаємо час виконання якщо доступно
    if (isset($wpdb->last_query_time)) {
        $message .= " (Time: {$wpdb->last_query_time}s)";
    }
    
    // Додаємо кількість рядків
    if (isset($wpdb->num_rows)) {
        $message .= " (Rows: {$wpdb->num_rows})";
    }
    
    catalog_master_log($message, 'debug', $context);
}

/**
 * Логування HTTP запитів
 */
function catalog_master_log_http($url, $args = [], $response = null, $context = '') {
    if (!defined('CATALOG_MASTER_DEBUG') || !CATALOG_MASTER_DEBUG) {
        return;
    }
    
    $message = "HTTP Request: $url";
    
    if (!empty($args)) {
        $message .= "\nArgs: " . print_r($args, true);
    }
    
    if ($response) {
        if (is_wp_error($response)) {
            $message .= "\nError: " . $response->get_error_message();
        } else {
            $status = wp_remote_retrieve_response_code($response);
            $message .= "\nStatus: $status";
            
            $body = wp_remote_retrieve_body($response);
            if (strlen($body) > 500) {
                $body = substr($body, 0, 500) . '... (truncated)';
            }
            $message .= "\nBody: $body";
        }
    }
    
    catalog_master_log($message, 'debug', $context);
}

/**
 * Логування помилок Google Sheets API
 */
function catalog_master_log_google_error($error, $context = 'GoogleSheets') {
    $message = "Google Sheets API Error: ";
    
    if (is_array($error) && isset($error['error'])) {
        $message .= $error['error']['message'] ?? 'Unknown error';
        if (isset($error['error']['code'])) {
            $message .= " (Code: {$error['error']['code']})";
        }
    } else {
        $message .= print_r($error, true);
    }
    
    catalog_master_log_error($message, $context);
}

/**
 * Профайлер для вимірювання часу виконання
 */
class CatalogMaster_Profiler {
    private static $timers = [];
    
    /**
     * Початок вимірювання
     */
    public static function start($name) {
        self::$timers[$name] = microtime(true);
        catalog_master_log_debug("Profiler started: $name", 'Profiler');
    }
    
    /**
     * Кінець вимірювання
     */
    public static function end($name) {
        if (!isset(self::$timers[$name])) {
            catalog_master_log_warning("Profiler timer '$name' not found", 'Profiler');
            return 0;
        }
        
        $time = microtime(true) - self::$timers[$name];
        unset(self::$timers[$name]);
        
        catalog_master_log_debug("Profiler ended: $name - " . round($time * 1000, 2) . "ms", 'Profiler');
        
        return $time;
    }
    
    /**
     * Вимірювання з автоматичним логуванням
     */
    public static function measure($name, $callback) {
        self::start($name);
        $result = $callback();
        self::end($name);
        return $result;
    }
}

/**
 * Логування стану плагіна при активації
 */
function catalog_master_log_plugin_state() {
    if (!defined('CATALOG_MASTER_DEBUG') || !CATALOG_MASTER_DEBUG) {
        return;
    }
    
    $state = [
        'PHP Version' => phpversion(),
        'WordPress Version' => get_bloginfo('version'),
        'Plugin Version' => defined('CATALOG_MASTER_VERSION') ? CATALOG_MASTER_VERSION : 'Unknown',
        'Memory Limit' => ini_get('memory_limit'),
        'Max Execution Time' => ini_get('max_execution_time'),
        'Upload Max Size' => ini_get('upload_max_filesize'),
        'Extensions' => [
            'curl' => extension_loaded('curl'),
            'gd' => extension_loaded('gd'),
            'zip' => extension_loaded('zip'),
            'mbstring' => extension_loaded('mbstring'),
        ],
        'WordPress Constants' => [
            'WP_DEBUG' => defined('WP_DEBUG') ? WP_DEBUG : false,
            'WP_DEBUG_LOG' => defined('WP_DEBUG_LOG') ? WP_DEBUG_LOG : false,
            'SAVEQUERIES' => defined('SAVEQUERIES') ? (bool)constant('SAVEQUERIES') : false,
        ]
    ];
    
    catalog_master_log_info("Plugin state:\n" . print_r($state, true), 'PluginState');
}

/**
 * Хук для логування стану при активації плагіна
 */
add_action('catalog_master_activated', 'catalog_master_log_plugin_state');

/**
 * Логування всіх PHP помилок пов'язаних з плагіном
 */
function catalog_master_error_handler($errno, $errstr, $errfile, $errline) {
    // Перевіряємо чи помилка пов'язана з нашим плагіном
    if (strpos($errfile, 'catalog-master') !== false) {
        $error_types = [
            E_ERROR => 'ERROR',
            E_WARNING => 'WARNING', 
            E_PARSE => 'PARSE',
            E_NOTICE => 'NOTICE',
            E_CORE_ERROR => 'CORE_ERROR',
            E_CORE_WARNING => 'CORE_WARNING',
            E_COMPILE_ERROR => 'COMPILE_ERROR',
            E_COMPILE_WARNING => 'COMPILE_WARNING',
            E_USER_ERROR => 'USER_ERROR',
            E_USER_WARNING => 'USER_WARNING',
            E_USER_NOTICE => 'USER_NOTICE',
            E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
            E_DEPRECATED => 'DEPRECATED',
            E_USER_DEPRECATED => 'USER_DEPRECATED',
        ];
        
        // Додаємо E_STRICT тільки якщо він існує (для сумісності з PHP < 8.0)
        if (defined('E_STRICT')) {
            $error_types[constant('E_STRICT')] = 'STRICT';
        }
        
        $error_type = $error_types[$errno] ?? 'UNKNOWN';
        $message = "PHP $error_type: $errstr in $errfile on line $errline";
        
        catalog_master_log($message, 'error', 'PHPError');
    }
    
    // Повертаємо false щоб стандартний обробник також спрацював
    return false;
}

// Встановлюємо обробник помилок тільки якщо дебаг увімкнений
if (defined('CATALOG_MASTER_DEBUG') && CATALOG_MASTER_DEBUG) {
    set_error_handler('catalog_master_error_handler');
} 