<?php
/**
 * Google Sheets integration class
 */

if (!defined('ABSPATH')) {
    exit;
}

class CatalogMaster_GoogleSheets {
    
    /**
     * Get data from Google Sheets CSV export
     */
    public static function import_from_url($sheet_url, $sheet_name = 'Sheet1') {
        // Convert Google Sheets URL to CSV export URL
        $csv_url = self::convert_to_csv_url($sheet_url, $sheet_name);
        
        if (!$csv_url) {
            return array('error' => 'Невірний URL Google Sheets');
        }
        
        // Get CSV data
        $response = wp_remote_get($csv_url, array(
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
            )
        ));
        
        if (is_wp_error($response)) {
            /** @var WP_Error $response */
            return array('error' => 'Помилка завантаження: ' . $response->get_error_message());
        }
        
        $csv_data = wp_remote_retrieve_body($response);
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code !== 200) {
            return array('error' => 'Помилка доступу до таблиці. Код: ' . $response_code);
        }
        
        // Parse CSV
        $rows = self::parse_csv($csv_data);
        
        if (empty($rows)) {
            return array('error' => 'Таблиця порожня або недоступна');
        }
        
        return array(
            'success' => true,
            'headers' => $rows[0],
            'data' => array_slice($rows, 1)
        );
    }
    
    /**
     * Convert Google Sheets URL to CSV export URL
     */
    private static function convert_to_csv_url($url, $sheet_name) {
        // Extract spreadsheet ID from various Google Sheets URL formats
        $patterns = array(
            '/\/spreadsheets\/d\/([a-zA-Z0-9-_]+)/',
            '/\/d\/([a-zA-Z0-9-_]+)/',
            '/key=([a-zA-Z0-9-_]+)/',
            '/id=([a-zA-Z0-9-_]+)/'
        );
        
        $spreadsheet_id = null;
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                $spreadsheet_id = $matches[1];
                break;
            }
        }
        
        if (!$spreadsheet_id) {
            return false;
        }
        
        // Get sheet ID if specific sheet name is provided
        $gid = 0;
        if ($sheet_name !== 'Sheet1') {
            $gid = self::get_sheet_gid($spreadsheet_id, $sheet_name);
        }
        
        // Build CSV export URL
        return "https://docs.google.com/spreadsheets/d/{$spreadsheet_id}/export?format=csv&gid={$gid}";
    }
    
    /**
     * Get sheet GID by name (simplified version)
     */
    private static function get_sheet_gid($spreadsheet_id, $sheet_name) {
        // This is a simplified approach - in a full implementation,
        // you would use Google Sheets API to get the actual GID
        return 0;
    }
    
    /**
     * Parse CSV data
     */
    private static function parse_csv($csv_data) {
        $rows = array();
        $lines = explode("\n", $csv_data);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            $row = str_getcsv($line);
            $rows[] = $row;
        }
        
        return $rows;
    }
    
    /**
     * Map Google Sheets data to catalog structure
     */
    public static function map_data_to_catalog($data, $mappings) {
        $mapped_items = array();
        
        foreach ($data as $row) {
            $item = array();
            
            foreach ($mappings as $mapping) {
                $google_column = $mapping->google_column;
                $catalog_column = $mapping->catalog_column;
                
                // Find column index by header name
                $column_index = array_search($google_column, $data[0] ?? array());
                
                if ($column_index !== false && isset($row[$column_index])) {
                    $value = trim($row[$column_index]);
                    
                    // Process value based on catalog column type
                    $item[$catalog_column] = self::process_column_value($value, $catalog_column);
                } else {
                    $item[$catalog_column] = self::get_default_value($catalog_column);
                }
            }
            
            if (!empty($item)) {
                $mapped_items[] = $item;
            }
        }
        
        return $mapped_items;
    }
    
    /**
     * Process column value based on type
     */
    private static function process_column_value($value, $column_name) {
        switch ($column_name) {
            case 'product_price':
                return floatval(preg_replace('/[^\d.]/', '', $value));
                
            case 'product_qty':
            case 'product_sort_order':
            case 'category_sort_order_1':
            case 'category_sort_order_2':
            case 'category_sort_order_3':
                return intval($value);
                
            case 'product_image_url':
            case 'category_image_1':
            case 'category_image_2':
            case 'category_image_3':
                return esc_url_raw($value);
                
            default:
                return sanitize_text_field($value);
        }
    }
    
    /**
     * Get default value for column
     */
    private static function get_default_value($column_name) {
        switch ($column_name) {
            case 'product_price':
                return 0.00;
                
            case 'product_qty':
            case 'product_sort_order':
            case 'category_sort_order_1':
            case 'category_sort_order_2':
            case 'category_sort_order_3':
                return 0;
                
            default:
                return '';
        }
    }
    
    /**
     * Download and save image locally
     */
    public static function download_image($image_url, $catalog_id, $product_id) {
        if (empty($image_url) || !filter_var($image_url, FILTER_VALIDATE_URL)) {
            return '';
        }
        
        // Get upload directory
        $upload_dir = wp_upload_dir();
        $catalog_images_dir = $upload_dir['basedir'] . '/catalog-master-images';
        
        // Create catalog-specific directory
        $catalog_dir = $catalog_images_dir . '/catalog-' . $catalog_id;
        if (!file_exists($catalog_dir)) {
            wp_mkdir_p($catalog_dir);
        }
        
        // Get image extension
        $image_info = pathinfo(parse_url($image_url, PHP_URL_PATH));
        $extension = isset($image_info['extension']) ? $image_info['extension'] : 'jpg';
        
        // Generate filename
        $filename = 'product-' . sanitize_file_name($product_id) . '-' . time() . '.' . $extension;
        $file_path = $catalog_dir . '/' . $filename;
        
        // Download image
        $response = wp_remote_get($image_url, array(
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
            )
        ));
        
        if (is_wp_error($response)) {
            return '';
        }
        
        $image_data = wp_remote_retrieve_body($response);
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code !== 200 || empty($image_data)) {
            return '';
        }
        
        // Save image
        if (file_put_contents($file_path, $image_data)) {
            // Return relative URL
            $upload_url = $upload_dir['baseurl'];
            return $upload_url . '/catalog-master-images/catalog-' . $catalog_id . '/' . $filename;
        }
        
        return '';
    }
    
    /**
     * Get available sheets from Google Sheets (simplified)
     */
    public static function get_available_sheets($sheet_url) {
        // In a full implementation, this would use Google Sheets API
        // For now, return default sheet
        return array('Sheet1');
    }
    
    /**
     * Import data with image downloading
     */
    public static function import_catalog_data($catalog_id, $sheet_url, $sheet_name, $mappings) {
        // Get data from Google Sheets
        $import_result = self::import_from_url($sheet_url, $sheet_name);
        
        if (isset($import_result['error'])) {
            return $import_result;
        }
        
        // Map data to catalog structure
        $mapped_items = array();
        $headers = $import_result['headers'];
        
        foreach ($import_result['data'] as $row) {
            $item = array();
            
            foreach ($mappings as $mapping) {
                $google_column = $mapping->google_column;
                $catalog_column = $mapping->catalog_column;
                
                // Find column index by header name
                $column_index = array_search($google_column, $headers);
                
                if ($column_index !== false && isset($row[$column_index])) {
                    $value = trim($row[$column_index]);
                    
                    // Special handling for images
                    if (($catalog_column === 'product_image_url' || 
                         $catalog_column === 'category_image_1' ||
                         $catalog_column === 'category_image_2' ||
                         $catalog_column === 'category_image_3') && !empty($value)) {
                        $product_id = isset($item['product_id']) ? $item['product_id'] : 'product-' . uniqid();
                        $local_image_url = self::download_image($value, $catalog_id, $product_id);
                        $item[$catalog_column] = $local_image_url ?: $value;
                    } else {
                        $item[$catalog_column] = self::process_column_value($value, $catalog_column);
                    }
                } else {
                    $item[$catalog_column] = self::get_default_value($catalog_column);
                }
            }
            
            if (!empty($item)) {
                $mapped_items[] = $item;
            }
        }
        
        // Clear existing items and insert new ones
        CatalogMaster_Database::clear_catalog_items($catalog_id);
        CatalogMaster_Database::insert_catalog_items($catalog_id, $mapped_items);
        
        return array(
            'success' => true,
            'imported_count' => count($mapped_items),
            'message' => 'Успішно імпортовано ' . count($mapped_items) . ' записів'
        );
    }
} 