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
     * Convert Google Sheets URL to XLSX export URL (improved - supports regular sheet URLs)
     */
    private static function convert_to_xlsx_url($url, $sheet_name) {
        CatalogMaster_Logger::info('🔗 Converting URL to XLSX format', array(
            'original_url' => $url,
            'sheet_name' => $sheet_name
        ));
        
        // Extract spreadsheet ID from various Google Sheets URL formats
        $patterns = array(
            // Regular sharing URLs: https://docs.google.com/spreadsheets/d/ID/edit#gid=0
            '/\/spreadsheets\/d\/([a-zA-Z0-9-_]+)\/edit/',
            // Direct spreadsheet URLs: https://docs.google.com/spreadsheets/d/ID/
            '/\/spreadsheets\/d\/([a-zA-Z0-9-_]+)/',
            // Export URLs (already formatted): https://docs.google.com/spreadsheets/d/ID/export
            '/\/d\/([a-zA-Z0-9-_]+)\/export/',
            '/\/d\/([a-zA-Z0-9-_]+)/',
            // Legacy formats
            '/key=([a-zA-Z0-9-_]+)/',
            '/id=([a-zA-Z0-9-_]+)/'
        );
        
        $spreadsheet_id = null;
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                $spreadsheet_id = $matches[1];
                CatalogMaster_Logger::debug('✅ Extracted spreadsheet ID', array(
                    'pattern' => $pattern,
                    'spreadsheet_id' => $spreadsheet_id
                ));
                break;
            }
        }
        
        if (!$spreadsheet_id) {
            CatalogMaster_Logger::error('❌ Could not extract spreadsheet ID from URL', array(
                'url' => $url
            ));
            return false;
        }
        
        // Extract GID from URL if present
        $gid = 0;
        if (preg_match('/[#&]gid=([0-9]+)/', $url, $gid_matches)) {
            $gid = intval($gid_matches[1]);
            CatalogMaster_Logger::debug('✅ Extracted GID from URL', array('gid' => $gid));
        } elseif ($sheet_name !== 'Sheet1') {
            // If specific sheet name provided but no GID in URL, try to get it
            $gid = self::get_sheet_gid($spreadsheet_id, $sheet_name);
        }
        
        // Build XLSX export URL
        $xlsx_url = "https://docs.google.com/spreadsheets/d/{$spreadsheet_id}/export?format=xlsx&gid={$gid}";
        
        CatalogMaster_Logger::info('🎯 URL converted successfully', array(
            'original_url' => $url,
            'xlsx_url' => $xlsx_url,
            'spreadsheet_id' => $spreadsheet_id,
            'gid' => $gid
        ));
        
        return $xlsx_url;
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
     * Get available sheets from Google Sheets (simplified)
     */
    public static function get_available_sheets($sheet_url) {
        // In a full implementation, this would use Google Sheets API
        // For now, return default sheet
        return array('Sheet1');
    }
    
    /**
     * Process a chunk of data for import, including mapping and image downloading.
     *
     * @param array $rows_to_process_in_batch The rows for the current batch.
     * @param array $headers The headers from the Google Sheet.
     * @param array $mappings The column mappings.
     * @param int $catalog_id The ID of the catalog.
     * @param array $category_image_cache Cache for category image URLs to avoid re-downloading.
     * @return array An array containing 'items_for_db', 'updated_image_cache', and 'errors_count'.
     */
    public static function process_data_chunk_for_import($rows_to_process_in_batch, $headers, $mappings, $catalog_id, $category_image_cache) {
        CatalogMaster_Logger::info('🔄 Processing data chunk for import', array(
            'catalog_id' => $catalog_id,
            'rows_in_chunk' => count($rows_to_process_in_batch),
            'mappings_count' => count($mappings),
            'initial_image_cache_size' => count($category_image_cache)
        ));
        
        // Map data to catalog structure
        $items_for_db = array();
        $skipped_rows = 0;
        $processed_rows = 0;
        $errors_count = 0;
        $updated_image_cache = $category_image_cache; // Work with a copy

        foreach ($rows_to_process_in_batch as $row_index => $row) {
            $item = array();
            $row_errors = array();
            
            foreach ($mappings as $mapping) {
                $google_column = $mapping->google_column;
                $catalog_column = $mapping->catalog_column;
                $column_index = array_search($google_column, $headers);
                
                if ($column_index !== false && isset($row[$column_index])) {
                    $original_value = $row[$column_index];
                    
                    if (strpos($catalog_column, 'image_url') !== false || strpos($catalog_column, 'image_') === 0) {
                        if (!empty($original_value)) {
                            $filename_base = '';
                            $image_type = 'category';

                            if ($catalog_column === 'product_image_url') {
                                $image_type = 'product';
                                $filename_base = isset($item['product_id']) && !empty($item['product_id']) ? $item['product_id'] : ('product_' . ($row_index + 1));
                            } else {
                                $level = substr($catalog_column, -1);
                                $category_id_key = 'category_id_' . $level;
                                $filename_base = isset($item[$category_id_key]) && !empty($item[$category_id_key]) ? $item[$category_id_key] : ('category' . $level . '_' . ($row_index + 1));
                            }

                            if ($image_type === 'category') {
                                if (isset($updated_image_cache[$original_value])) {
                                    $item[$catalog_column] = $updated_image_cache[$original_value];
                                } else {
                                    $local_image_url = self::download_and_process_image($original_value, $catalog_id, $filename_base, $image_type);
                                    if (!empty($local_image_url)) {
                                        $updated_image_cache[$original_value] = $local_image_url;
                                    }
                                    $item[$catalog_column] = $local_image_url;
                                }
                            } else { 
                                $item[$catalog_column] = self::download_and_process_image($original_value, $catalog_id, $filename_base, $image_type);
                            }
                        } else {
                            $item[$catalog_column] = ''; 
                        }
                    } else { 
                        try {
                            $processed_value = self::process_column_value($original_value, $catalog_column);
                            $item[$catalog_column] = $processed_value;
                        } catch (Exception $e) {
                            $row_errors[] = "Column '{$google_column}' -> '{$catalog_column}': processing error - " . $e->getMessage();
                            $item[$catalog_column] = self::get_default_value($catalog_column);
                            $errors_count++;
                        }
                    }
                } else {
                    $item[$catalog_column] = self::get_default_value($catalog_column);
                }
            }

            if (!empty($row_errors)) {
                CatalogMaster_Logger::warning("⚠️ Row {$row_index} processing issues", $row_errors);
            }
            
            if (!empty($item) && (!empty($item['product_name']) || !empty($item['product_id']))) {
                $items_for_db[] = $item;
                $processed_rows++;
            } else {
                $skipped_rows++;
                CatalogMaster_Logger::debug("Skipped empty row {$row_index}", $row);
            }
        }

        CatalogMaster_Logger::info('📊 Data chunk processing completed', array(
            'processed_rows' => $processed_rows,
            'skipped_rows' => $skipped_rows,
            'items_for_db_count' => count($items_for_db),
            'errors_in_chunk' => $errors_count,
            'final_image_cache_size' => count($updated_image_cache)
        ));

        return array(
            'items_for_db' => $items_for_db,
            'updated_image_cache' => $updated_image_cache,
            'errors_count' => $errors_count
        );
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

    /**
     * Download, process (resize, convert to JPG), and save image locally.
     * Names product images as product_id.jpg and category images as category_id_X.jpg.
     */
    private static function download_and_process_image($image_url, $catalog_id, $filename_base, $type = 'product', $target_width = 1000, $target_height = 1000) {
        if (empty($image_url) || !filter_var($image_url, FILTER_VALIDATE_URL)) {
            CatalogMaster_Logger::warning("Invalid image URL for {$type} '{$filename_base}': {$image_url}");
            return '';
        }

        // Get upload directory
        $upload_dir = wp_upload_dir();
        $base_images_dir = $upload_dir['basedir'] . '/catalog-master-images/catalog-' . $catalog_id . '/';
        $sub_dir = ($type === 'product') ? 'products/' : 'categories/';
        $full_target_dir = $base_images_dir . $sub_dir;

        if (!file_exists($full_target_dir)) {
            if (!wp_mkdir_p($full_target_dir)) {
                CatalogMaster_Logger::error("Failed to create directory: {$full_target_dir}");
                return '';
            }
        }

        $final_filename = sanitize_file_name($filename_base) . '.jpg';
        $final_file_path = $full_target_dir . $final_filename;

        // Download image to a temporary file
        $temp_file_path = download_url($image_url, 300); // 300 seconds timeout

        if (is_wp_error($temp_file_path)) {
            CatalogMaster_Logger::error("Failed to download image from {$image_url} for {$type} '{$filename_base}'. Error: " . $temp_file_path->get_error_message());
            return '';
        }

        $image_editor = wp_get_image_editor($temp_file_path);

        if (!is_wp_error($image_editor)) {
            $image_editor->set_quality(90); // Standard quality for JPG
            $resized = $image_editor->resize($target_width, $target_height, true); // true for crop

            if (is_wp_error($resized)) {
                CatalogMaster_Logger::error("Failed to resize image {$image_url}. Error: " . $resized->get_error_message());
                @unlink($temp_file_path);
                return '';
            }

            $saved = $image_editor->save($final_file_path, 'image/jpeg');

            if (!is_wp_error($saved) && $saved && isset($saved['path'])) {
                $final_url = $upload_dir['baseurl'] . '/catalog-master-images/catalog-' . $catalog_id . '/' . $sub_dir . $final_filename;
                CatalogMaster_Logger::info("Image processed and saved: {$final_url} from {$image_url}");
                @unlink($temp_file_path);
                return $final_url;
            } else {
                $error_message = is_wp_error($saved) ? $saved->get_error_message() : 'Unknown error saving processed image.';
                CatalogMaster_Logger::error("Failed to save processed image {$final_file_path} from {$image_url}. Error: " . $error_message);
            }
        } else {
            CatalogMaster_Logger::error("Failed to get image editor for {$image_url}. Error: " . $image_editor->get_error_message());
        }

        @unlink($temp_file_path); // Ensure temporary file is deleted
        return ''; // Return empty string if any step fails
    }
} 