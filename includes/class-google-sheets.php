<?php
/**
 * Google Sheets integration class
 */

if (!defined('ABSPATH')) {
    exit;
}

class CatalogMaster_GoogleSheets {
    
    /**
     * Get data from Google Sheets XLSX export (improved method)
     */
    public static function import_from_url($sheet_url, $sheet_name = 'Sheet1') {
        // Convert Google Sheets URL to XLSX export URL (better than CSV)
        $xlsx_url = self::convert_to_xlsx_url($sheet_url, $sheet_name);
        
        if (!$xlsx_url) {
            return array('error' => 'Невірний URL Google Sheets');
        }
        
        CatalogMaster_Logger::info('📊 Downloading XLSX from Google Sheets', array(
            'original_url' => $sheet_url,
            'xlsx_url' => $xlsx_url,
            'sheet_name' => $sheet_name
        ));
        
        // Get XLSX data
        $response = wp_remote_get($xlsx_url, array(
            'timeout' => 60, // Increased timeout for larger files
            'headers' => array(
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
            )
        ));
        
        if (is_wp_error($response)) {
            /** @var WP_Error $response */
            CatalogMaster_Logger::error('❌ Failed to download XLSX', array(
                'error' => $response->get_error_message()
            ));
            return array('error' => 'Помилка завантаження: ' . $response->get_error_message());
        }
        
        $xlsx_data = wp_remote_retrieve_body($response);
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code !== 200) {
            CatalogMaster_Logger::error('❌ HTTP error downloading XLSX', array(
                'response_code' => $response_code
            ));
            return array('error' => 'Помилка доступу до таблиці. Код: ' . $response_code);
        }
        
        // Parse XLSX
        $rows = self::parse_xlsx($xlsx_data);
        
        if (empty($rows)) {
            return array('error' => 'Таблиця порожня або недоступна');
        }
        
        CatalogMaster_Logger::info('✅ XLSX parsed successfully', array(
            'headers_count' => count($rows[0]),
            'rows_count' => count($rows) - 1,
            'headers' => $rows[0]
        ));
        
        return array(
            'success' => true,
            'headers' => $rows[0],
            'data' => array_slice($rows, 1)
        );
    }
    
    /**
     * Convert Google Sheets URL to XLSX export URL
     */
    private static function convert_to_xlsx_url($url, $sheet_name) {
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
        
        // Build XLSX export URL - much better than CSV!
        return "https://docs.google.com/spreadsheets/d/{$spreadsheet_id}/export?format=xlsx&gid={$gid}";
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
     * Parse CSV data with proper handling of line breaks and special characters
     */
    private static function parse_csv($csv_data) {
        $rows = array();
        
        // Normalize line endings to \n
        $csv_data = str_replace(array("\r\n", "\r"), "\n", $csv_data);
        
        // Use PHP's built-in CSV parsing instead of manual splitting
        $temp_file = tmpfile();
        if ($temp_file === false) {
            // Fallback to string parsing if tmpfile fails
            return self::parse_csv_string($csv_data);
        }
        
        fwrite($temp_file, $csv_data);
        rewind($temp_file);
        
        while (($row = fgetcsv($temp_file, 0, ',', '"', '\\')) !== false) {
            // Skip completely empty rows
            if (count(array_filter($row, 'strlen')) === 0) {
                continue;
            }
            
            // Clean and sanitize each cell
            $cleaned_row = array();
            foreach ($row as $cell) {
                // Handle line breaks within cells - replace with space for better display
                $cell = str_replace(array("\n", "\r\n", "\r"), ' ', $cell);
                
                // Remove excessive whitespace
                $cell = preg_replace('/\s+/', ' ', trim($cell));
                
                // Basic sanitization while preserving useful characters
                $cell = self::sanitize_csv_cell($cell);
                
                $cleaned_row[] = $cell;
            }
            
            $rows[] = $cleaned_row;
        }
        
        fclose($temp_file);
        
        return $rows;
    }
    
    /**
     * Fallback CSV parsing using string methods (if tmpfile fails)
     */
    private static function parse_csv_string($csv_data) {
        $rows = array();
        $lines = str_getcsv($csv_data, "\n");
        
        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }
            
            $row = str_getcsv($line, ',', '"', '\\');
            
            // Clean each cell
            $cleaned_row = array();
            foreach ($row as $cell) {
                $cell = str_replace(array("\n", "\r\n", "\r"), ' ', $cell);
                $cell = preg_replace('/\s+/', ' ', trim($cell));
                $cell = self::sanitize_csv_cell($cell);
                $cleaned_row[] = $cell;
            }
            
            $rows[] = $cleaned_row;
        }
        
        return $rows;
    }
    
    /**
     * Sanitize individual CSV cell content
     */
    private static function sanitize_csv_cell($content) {
        // Remove null bytes and other control characters (except tabs and spaces)
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content);
        
        // Trim whitespace
        $content = trim($content);
        
        // Convert HTML entities if present
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML401, 'UTF-8');
        
        // Ensure UTF-8 encoding
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'auto');
        }
        
        return $content;
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
     * Process column value based on type with enhanced sanitization
     */
    private static function process_column_value($value, $column_name) {
        // First, ensure we have a string
        $value = (string) $value;
        
        switch ($column_name) {
            case 'product_price':
                // Extract numeric value, handle different decimal separators
                $price = preg_replace('/[^\d.,]/', '', $value);
                $price = str_replace(',', '.', $price);
                return floatval($price);
                
            case 'product_qty':
            case 'product_sort_order':
            case 'category_sort_order_1':
            case 'category_sort_order_2':
            case 'category_sort_order_3':
                // Extract only digits
                $number = preg_replace('/[^\d]/', '', $value);
                return intval($number);
                
            case 'product_image_url':
            case 'category_image_1':
            case 'category_image_2':
            case 'category_image_3':
                // Validate and clean URL
                $url = trim($value);
                if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                    return '';
                }
                return esc_url_raw($url);
                
            case 'product_description':
                // Enhanced processing for descriptions that may contain line breaks
                return self::process_description_field($value);
                
            case 'product_name':
            case 'category_name_1':
            case 'category_name_2':
            case 'category_name_3':
                // Enhanced processing for names - preserve some formatting but clean dangerous content
                return self::process_name_field($value);
                
            case 'product_id':
            case 'category_id_1':
            case 'category_id_2':
            case 'category_id_3':
                // IDs should be clean alphanumeric
                return self::process_id_field($value);
                
            default:
                // Generic text field processing
                return self::process_text_field($value);
        }
    }
    
    /**
     * Process description field with preserved formatting (optimized for XLSX)
     */
    private static function process_description_field($value) {
        if (empty($value)) {
            return '';
        }
        
        // XLSX already preserves line breaks correctly, just convert them for HTML display
        $value = str_replace(array("\r\n", "\r"), "\n", $value); // Normalize to \n
        $value = str_replace("\n", "<br>", $value); // Convert to HTML
        
        // Remove dangerous HTML tags but allow basic formatting
        $allowed_tags = '<br><p><strong><b><em><i><ul><ol><li>';
        $value = strip_tags($value, $allowed_tags);
        
        // Clean up excessive whitespace but preserve intentional line breaks
        $value = preg_replace('/[ \t]+/', ' ', $value); // Collapse spaces/tabs
        $value = preg_replace('/(<br>){3,}/', '<br><br>', $value); // Max 2 consecutive breaks
        
        // Sanitize for database storage
        $value = wp_kses_post($value);
        
        // Limit length to prevent extremely long descriptions
        if (strlen($value) > 5000) {
            $value = substr($value, 0, 5000) . '...';
        }
        
        return trim($value);
    }
    
    /**
     * Process name field (product names, category names) - optimized for XLSX
     */
    private static function process_name_field($value) {
        if (empty($value)) {
            return '';
        }
        
        // XLSX might have intentional line breaks in names - convert to spaces
        $value = str_replace(array("\r\n", "\n", "\r"), ' ', $value);
        
        // Clean up excessive whitespace
        $value = preg_replace('/\s+/', ' ', $value);
        
        // Remove dangerous characters but preserve international characters
        $value = preg_replace('/[<>"\']/', '', $value);
        
        // Sanitize and limit length
        $value = sanitize_text_field($value);
        
        if (strlen($value) > 500) {
            $value = substr($value, 0, 500);
        }
        
        return trim($value);
    }
    
    /**
     * Process ID field (product IDs, category IDs)
     */
    private static function process_id_field($value) {
        if (empty($value)) {
            return '';
        }
        
        // Remove all non-alphanumeric characters except hyphens and underscores
        $value = preg_replace('/[^a-zA-Z0-9_-]/', '', $value);
        
        // Limit length
        if (strlen($value) > 100) {
            $value = substr($value, 0, 100);
        }
        
        return trim($value);
    }
    
    /**
     * Process generic text field
     */
    private static function process_text_field($value) {
        if (empty($value)) {
            return '';
        }
        
        // Remove line breaks
        $value = str_replace(array("\r\n", "\n", "\r"), ' ', $value);
        
        // Clean up excessive whitespace
        $value = preg_replace('/\s+/', ' ', $value);
        
        // Basic sanitization
        $value = sanitize_text_field($value);
        
        // Limit length
        if (strlen($value) > 1000) {
            $value = substr($value, 0, 1000);
        }
        
        return trim($value);
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
     * Import data with image downloading and enhanced error handling
     */
    public static function import_catalog_data($catalog_id, $sheet_url, $sheet_name, $mappings) {
        CatalogMaster_Logger::info('🔄 Starting catalog data import', array(
            'catalog_id' => $catalog_id,
            'sheet_url' => $sheet_url,
            'sheet_name' => $sheet_name,
            'mappings_count' => count($mappings)
        ));
        
        // Get data from Google Sheets
        $import_result = self::import_from_url($sheet_url, $sheet_name);
        
        if (isset($import_result['error'])) {
            CatalogMaster_Logger::error('❌ Failed to fetch data from Google Sheets', array(
                'error' => $import_result['error']
            ));
            return $import_result;
        }
        
        CatalogMaster_Logger::info('✅ Data fetched from Google Sheets', array(
            'headers_count' => count($import_result['headers']),
            'rows_count' => count($import_result['data']),
            'headers' => $import_result['headers']
        ));
        
        // Map data to catalog structure
        $mapped_items = array();
        $headers = $import_result['headers'];
        $skipped_rows = 0;
        $processed_rows = 0;
        
        foreach ($import_result['data'] as $row_index => $row) {
            $item = array();
            $row_errors = array();
            
            foreach ($mappings as $mapping) {
                $google_column = $mapping->google_column;
                $catalog_column = $mapping->catalog_column;
                
                // Find column index by header name
                $column_index = array_search($google_column, $headers);
                
                if ($column_index !== false && isset($row[$column_index])) {
                    $original_value = $row[$column_index];
                    
                    try {
                    // Special handling for images
                    if (($catalog_column === 'product_image_url' || 
                         $catalog_column === 'category_image_1' ||
                         $catalog_column === 'category_image_2' ||
                             $catalog_column === 'category_image_3') && !empty($original_value)) {
                        $product_id = isset($item['product_id']) ? $item['product_id'] : 'product-' . uniqid();
                            $local_image_url = self::download_image($original_value, $catalog_id, $product_id);
                            $item[$catalog_column] = $local_image_url ?: $original_value;
                    } else {
                            $processed_value = self::process_column_value($original_value, $catalog_column);
                            $item[$catalog_column] = $processed_value;
                            
                            // Log potential data issues
                            if (!empty($original_value) && empty($processed_value) && $catalog_column !== 'product_price') {
                                $row_errors[] = "Column '{$google_column}' -> '{$catalog_column}': value was cleaned from '{$original_value}' to empty";
                            }
                        }
                    } catch (Exception $e) {
                        $row_errors[] = "Column '{$google_column}' -> '{$catalog_column}': processing error - " . $e->getMessage();
                        $item[$catalog_column] = self::get_default_value($catalog_column);
                    }
                } else {
                    $item[$catalog_column] = self::get_default_value($catalog_column);
                }
            }
            
            // Log row errors if any
            if (!empty($row_errors)) {
                CatalogMaster_Logger::warning("⚠️ Row {$row_index} processing issues", $row_errors);
            }
            
            // Validate that we have minimum required data
            if (!empty($item) && (!empty($item['product_name']) || !empty($item['product_id']))) {
                $mapped_items[] = $item;
                $processed_rows++;
            } else {
                $skipped_rows++;
                CatalogMaster_Logger::debug("Skipped empty row {$row_index}", $row);
            }
        }
        
        CatalogMaster_Logger::info('📊 Data mapping completed', array(
            'processed_rows' => $processed_rows,
            'skipped_rows' => $skipped_rows,
            'mapped_items' => count($mapped_items)
        ));
        
        if (empty($mapped_items)) {
            $error_msg = 'Не вдалося обробити жодного запису. Перевірте відповідність колонок та дані.';
            CatalogMaster_Logger::error('❌ No items to import', array('error' => $error_msg));
            return array('error' => $error_msg);
        }
        
        // Clear existing items and insert new ones
        try {
            CatalogMaster_Logger::info('🗑️ Clearing existing catalog items');
        CatalogMaster_Database::clear_catalog_items($catalog_id);
            
            CatalogMaster_Logger::info('💾 Inserting new catalog items');
        CatalogMaster_Database::insert_catalog_items($catalog_id, $mapped_items);
            
            CatalogMaster_Logger::info('✅ Import completed successfully', array(
                'imported_count' => count($mapped_items),
                'skipped_count' => $skipped_rows
            ));
            
            $success_message = 'Успішно імпортовано ' . count($mapped_items) . ' записів';
            if ($skipped_rows > 0) {
                $success_message .= ' (пропущено ' . $skipped_rows . ' порожніх рядків)';
            }
        
        return array(
            'success' => true,
            'imported_count' => count($mapped_items),
                'skipped_count' => $skipped_rows,
                'message' => $success_message
            );
            
        } catch (Exception $e) {
            $error_msg = 'Помилка збереження в базу даних: ' . $e->getMessage();
            CatalogMaster_Logger::error('❌ Database save error', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            return array('error' => $error_msg);
        }
    }
    
    /**
     * Parse XLSX data (much better than CSV for preserving formatting)
     */
    private static function parse_xlsx($xlsx_data) {
        // Check if ZIP extension is available
        if (!class_exists('ZipArchive')) {
            CatalogMaster_Logger::error('❌ ZipArchive not available, falling back to CSV');
            // Fallback to CSV if ZIP not available
            return self::fallback_to_csv_parsing($xlsx_data);
        }
        
        // Create temporary file
        $temp_file = tempnam(sys_get_temp_dir(), 'catalog_xlsx_');
        if (!$temp_file || !file_put_contents($temp_file, $xlsx_data)) {
            CatalogMaster_Logger::error('❌ Cannot create temporary XLSX file');
            return array();
        }
        
        $zip = new ZipArchive();
        $result = $zip->open($temp_file);
        
        if ($result !== TRUE) {
            CatalogMaster_Logger::error('❌ Cannot open XLSX file', array('zip_error' => $result));
            unlink($temp_file);
            return array();
        }
        
        try {
            // Read shared strings (for text values)
            $shared_strings = self::read_xlsx_shared_strings($zip);
            
            // Read worksheet data
            $worksheet_data = self::read_xlsx_worksheet($zip, $shared_strings);
            
            $zip->close();
            unlink($temp_file);
            
            CatalogMaster_Logger::info('✅ XLSX parsed successfully', array(
                'rows_found' => count($worksheet_data),
                'shared_strings_count' => count($shared_strings)
            ));
            
            return $worksheet_data;
            
        } catch (Exception $e) {
            CatalogMaster_Logger::error('❌ Error parsing XLSX', array(
                'error' => $e->getMessage()
            ));
            $zip->close();
            unlink($temp_file);
            return array();
        }
    }
    
    /**
     * Read shared strings from XLSX
     */
    private static function read_xlsx_shared_strings($zip) {
        $shared_strings = array();
        $shared_strings_xml = $zip->getFromName('xl/sharedStrings.xml');
        
        if ($shared_strings_xml === false) {
            return $shared_strings;
        }
        
        // Parse shared strings XML
        $xml = simplexml_load_string($shared_strings_xml);
        if ($xml === false) {
            return $shared_strings;
        }
        
        foreach ($xml->si as $si) {
            if (isset($si->t)) {
                $shared_strings[] = (string) $si->t;
            } elseif (isset($si->r)) {
                // Handle rich text
                $text = '';
                foreach ($si->r as $r) {
                    if (isset($r->t)) {
                        $text .= (string) $r->t;
                    }
                }
                $shared_strings[] = $text;
            } else {
                $shared_strings[] = '';
            }
        }
        
        return $shared_strings;
    }
    
    /**
     * Read worksheet data from XLSX
     */
    private static function read_xlsx_worksheet($zip, $shared_strings) {
        $worksheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        
        if ($worksheet_xml === false) {
            throw new Exception('Cannot read worksheet data');
        }
        
        // Parse worksheet XML
        $xml = simplexml_load_string($worksheet_xml);
        if ($xml === false) {
            throw new Exception('Cannot parse worksheet XML');
        }
        
        $rows = array();
        $current_row = 1;
        
        foreach ($xml->sheetData->row as $row) {
            $row_number = (int) $row['r'];
            
            // Fill missing rows with empty arrays
            while ($current_row < $row_number) {
                $rows[] = array();
                $current_row++;
            }
            
            $row_data = array();
            $max_col = 0;
            
            foreach ($row->c as $cell) {
                $cell_reference = (string) $cell['r'];
                $col_index = self::excel_column_to_number($cell_reference) - 1;
                $max_col = max($max_col, $col_index + 1);
                
                // Ensure array is large enough
                while (count($row_data) <= $col_index) {
                    $row_data[] = '';
                }
                
                $value = '';
                
                if (isset($cell['t']) && $cell['t'] == 's') {
                    // Shared string
                    $string_index = (int) $cell->v;
                    if (isset($shared_strings[$string_index])) {
                        $value = $shared_strings[$string_index];
                    }
                } elseif (isset($cell->v)) {
                    // Direct value
                    $value = (string) $cell->v;
                } elseif (isset($cell->is) && isset($cell->is->t)) {
                    // Inline string
                    $value = (string) $cell->is->t;
                }
                
                // Clean and sanitize cell value (preserve line breaks in XLSX!)
                $value = self::sanitize_xlsx_cell($value);
                
                $row_data[$col_index] = $value;
            }
            
            // Fill remaining columns with empty strings
            while (count($row_data) < $max_col) {
                $row_data[] = '';
            }
            
            $rows[] = $row_data;
            $current_row++;
        }
        
        return $rows;
    }
    
    /**
     * Convert Excel column reference to number (A=1, B=2, etc.)
     */
    private static function excel_column_to_number($cell_reference) {
        preg_match('/([A-Z]+)/', $cell_reference, $matches);
        $column = $matches[1];
        
        $number = 0;
        $length = strlen($column);
        
        for ($i = 0; $i < $length; $i++) {
            $number = $number * 26 + (ord($column[$i]) - ord('A') + 1);
        }
        
        return $number;
    }
    
    /**
     * Sanitize XLSX cell content (preserve formatting better than CSV)
     */
    private static function sanitize_xlsx_cell($content) {
        if (empty($content)) {
            return '';
        }
        
        // XLSX preserves line breaks properly, so we can keep them
        // Only remove dangerous control characters
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content);
        
        // Trim whitespace but preserve internal formatting
        $content = trim($content);
        
        // Convert HTML entities if present
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML401, 'UTF-8');
        
        // Ensure UTF-8 encoding
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'auto');
        }
        
        return $content;
    }
    
    /**
     * Fallback to CSV parsing if XLSX parsing fails
     */
    private static function fallback_to_csv_parsing($data) {
        CatalogMaster_Logger::warning('⚠️ Falling back to CSV parsing');
        
        // If this is actually XLSX data, try to get CSV version
        // This is a fallback, should rarely be used
        return self::parse_csv($data);
    }
} 