<?php
/**
 * AJAX handlers class
 */

if (!defined('ABSPATH')) {
    exit;
}

class CatalogMaster_Ajax {
    
    const IMPORT_BATCH_SIZE = 25; // Number of rows to process per batch
    
    public function __construct() {
        add_action('wp_ajax_catalog_master_test_sheets_connection', array($this, 'test_sheets_connection'));
        add_action('wp_ajax_catalog_master_get_sheets_headers', array($this, 'get_sheets_headers'));
        add_action('wp_ajax_catalog_master_save_column_mapping', array($this, 'save_column_mapping'));
        add_action('wp_ajax_catalog_master_import_data', array($this, 'import_data'));
        add_action('wp_ajax_catalog_master_get_catalog_data', array($this, 'get_catalog_data'));
        add_action('wp_ajax_catalog_master_update_item', array($this, 'update_item'));
        add_action('wp_ajax_catalog_master_delete_item', array($this, 'delete_item'));
        add_action('wp_ajax_catalog_master_add_item', array($this, 'add_item'));
        add_action('wp_ajax_catalog_master_get_column_mapping', array($this, 'get_column_mapping'));
        add_action('wp_ajax_catalog_master_get_catalog_stats', array($this, 'get_catalog_stats'));
    }
    
    /**
     * Test Google Sheets connection
     */
    public function test_sheets_connection() {
        check_ajax_referer('catalog_master_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $sheet_url = sanitize_url($_POST['sheet_url']);
        $sheet_name = sanitize_text_field($_POST['sheet_name']);
        
        $result = CatalogMaster_GoogleSheets::import_from_url($sheet_url, $sheet_name);
        
        if (isset($result['error'])) {
            wp_send_json_error($result['error']);
        } else {
            wp_send_json_success(array(
                'message' => 'Підключення успішне',
                'headers' => $result['headers'],
                'row_count' => count($result['data'])
            ));
        }
    }
    
    /**
     * Get headers from Google Sheets
     */
    public function get_sheets_headers() {
        check_ajax_referer('catalog_master_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $sheet_url = sanitize_url($_POST['sheet_url']);
        $sheet_name = sanitize_text_field($_POST['sheet_name']);
        
        $result = CatalogMaster_GoogleSheets::import_from_url($sheet_url, $sheet_name);
        
        if (isset($result['error'])) {
            wp_send_json_error($result['error']);
        } else {
            wp_send_json_success(array(
                'headers' => $result['headers']
            ));
        }
    }
    
    /**
     * Save column mapping
     */
    public function save_column_mapping() {
        CatalogMaster_Logger::info('🔄 Save column mapping AJAX called');
        
        check_ajax_referer('catalog_master_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            CatalogMaster_Logger::error('❌ Insufficient permissions for save column mapping');
            wp_die('Insufficient permissions');
        }
        
        $catalog_id = intval($_POST['catalog_id']);
        $mappings = $_POST['mappings'];
        
        CatalogMaster_Logger::info('📊 Received data', array(
            'catalog_id' => $catalog_id,
            'mappings_raw' => $mappings,
            'post_data' => $_POST
        ));
        
        if (!$catalog_id || !is_array($mappings)) {
            CatalogMaster_Logger::error('❌ Invalid data', array(
                'catalog_id' => $catalog_id,
                'mappings_is_array' => is_array($mappings),
                'mappings_type' => gettype($mappings)
            ));
            wp_send_json_error('Невірні дані');
        }
        
        // Sanitize mappings
        $clean_mappings = array();
        foreach ($mappings as $mapping) {
            if (!empty($mapping['google_column']) && !empty($mapping['catalog_column'])) {
                $clean_mappings[] = array(
                    'google_column' => sanitize_text_field($mapping['google_column']),
                    'catalog_column' => sanitize_text_field($mapping['catalog_column'])
                );
            }
        }
        
        CatalogMaster_Logger::info('✅ Clean mappings prepared', array(
            'clean_mappings' => $clean_mappings,
            'count' => count($clean_mappings)
        ));
        
        try {
            CatalogMaster_Database::save_column_mapping($catalog_id, $clean_mappings);
            CatalogMaster_Logger::info('✅ Column mapping saved successfully');
            
            wp_send_json_success(array(
                'message' => 'Маппінг збережено',
                'saved_count' => count($clean_mappings)
            ));
        } catch (Exception $e) {
            CatalogMaster_Logger::error('❌ Database error saving column mapping', array(
                'error' => $e->getMessage()
            ));
            wp_send_json_error('Помилка збереження в базу даних');
        }
    }
    
    /**
     * Get existing column mapping for catalog
     */
    public function get_column_mapping() {
        check_ajax_referer('catalog_master_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $catalog_id = intval($_POST['catalog_id']);
        
        if (!$catalog_id) {
            wp_send_json_error('Невірний ID каталогу');
        }
        
        $mappings = CatalogMaster_Database::get_column_mapping($catalog_id);
        
        // Format mappings for frontend
        $formatted_mappings = array();
        foreach ($mappings as $mapping) {
            $formatted_mappings[] = array(
                'google_column' => $mapping->google_column,
                'catalog_column' => $mapping->catalog_column
            );
        }
        
        wp_send_json_success(array(
            'mappings' => $formatted_mappings
        ));
    }
    
    /**
     * Import data from Google Sheets
     */
    public function import_data() {
        check_ajax_referer('catalog_master_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $catalog_id = intval($_POST['catalog_id']);
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : self::IMPORT_BATCH_SIZE;
        $is_first_batch = isset($_POST['is_first_batch']) ? boolval($_POST['is_first_batch']) : false;
        
        if (!$catalog_id) {
            wp_send_json_error('Невірний ID каталогу');
        }
        
        $catalog = CatalogMaster_Database::get_catalog($catalog_id);
        if (!$catalog) {
            wp_send_json_error('Каталог не знайдено');
        }
        
        $mappings = CatalogMaster_Database::get_column_mapping($catalog_id);
        if (empty($mappings)) {
            wp_send_json_error('Спочатку налаштуйте відповідність стовпців');
        }

        $transient_data_key = 'cm_import_data_' . $catalog_id;
        $transient_total_key = 'cm_import_total_' . $catalog_id;
        $transient_img_cache_key = 'cm_import_img_cache_' . $catalog_id;

        $all_data_rows = null;
        $headers = null;
        $total_items_in_sheet = 0;
        $processed_category_image_urls_cache = array();

        if ($is_first_batch) {
            CatalogMaster_Logger::info("Import: First batch for catalog {$catalog_id}");
            $import_result = CatalogMaster_GoogleSheets::import_from_url($catalog->google_sheet_url, $catalog->sheet_name);
            if (isset($import_result['error'])) {
                wp_send_json_error($import_result['error']);
                return;
            }
            $headers = $import_result['headers'];
            $all_data_rows = $import_result['data'];
            $total_items_in_sheet = count($all_data_rows);

            set_transient($transient_data_key, array('headers' => $headers, 'rows' => $all_data_rows), HOUR_IN_SECONDS);
            set_transient($transient_total_key, $total_items_in_sheet, HOUR_IN_SECONDS);
            set_transient($transient_img_cache_key, array(), HOUR_IN_SECONDS); // Initialize image cache

            CatalogMaster_Database::clear_catalog_items($catalog_id);
            CatalogMaster_Logger::info("Import: Cleared items for catalog {$catalog_id}. Total items from sheet: {$total_items_in_sheet}");
        } else {
            $cached_data = get_transient($transient_data_key);
            $total_items_in_sheet = get_transient($transient_total_key);
            $processed_category_image_urls_cache = get_transient($transient_img_cache_key) ?: array();

            if ($cached_data === false || $total_items_in_sheet === false) {
                wp_send_json_error('Помилка сесії імпорту. Спробуйте знову.');
                return;
            }
            $headers = $cached_data['headers'];
            $all_data_rows = $cached_data['rows'];
        }

        $current_chunk_of_rows = array_slice($all_data_rows, $offset, $batch_size);

        if (empty($current_chunk_of_rows)) {
            delete_transient($transient_data_key);
            delete_transient($transient_total_key);
            delete_transient($transient_img_cache_key);
            wp_send_json_success(array(
                'message' => 'Імпорт завершено. Оброблено всі рядки.',
                'is_complete' => true,
                'processed_in_this_batch' => 0,
                'total_items_in_sheet' => $total_items_in_sheet,
            ));
            return;
        }

        $processing_result = CatalogMaster_GoogleSheets::process_data_chunk_for_import($current_chunk_of_rows, $headers, $mappings, $catalog_id, $processed_category_image_urls_cache);
        
        CatalogMaster_Database::insert_catalog_items($catalog_id, $processing_result['items_for_db']);
        set_transient($transient_img_cache_key, $processing_result['updated_image_cache'], HOUR_IN_SECONDS); // Save updated image cache

        $next_offset = $offset + count($current_chunk_of_rows); // Use actual count of rows in chunk
        $is_complete = ($next_offset >= $total_items_in_sheet);

        if ($is_complete) {
            delete_transient($transient_data_key);
            delete_transient($transient_total_key);
            delete_transient($transient_img_cache_key);
        }

        wp_send_json_success(array(
            'message' => 'Пакет оброблено: ' . count($processing_result['items_for_db']) . ' записів.',
            'processed_in_this_batch' => count($processing_result['items_for_db']),
            'total_items_in_sheet' => $total_items_in_sheet,
            'is_complete' => $is_complete,
            'next_offset' => $next_offset,
            'current_offset' => $offset, // For debugging/logging on client
            'errors_in_batch' => $processing_result['errors_count']
        ));
    }
    
    /**
     * Get catalog data for DataTable
     */
    public function get_catalog_data() {
        check_ajax_referer('catalog_master_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $catalog_id = intval($_POST['catalog_id']);
        
        // Check if this is the new Modern Table Manager format
        $is_modern_format = isset($_POST['page']) && isset($_POST['page_size']);
        
        if ($is_modern_format) {
            // New Modern Table Manager format
            $page = intval($_POST['page']);
            $page_size = intval($_POST['page_size']);
            $search = sanitize_text_field($_POST['search'] ?? '');
            $sort_column = sanitize_text_field($_POST['sort_column'] ?? 'product_id');
            $sort_direction = sanitize_text_field($_POST['sort_direction'] ?? 'asc');
            
            // Calculate offset
            $offset = ($page - 1) * $page_size;
            
            // Get items with sorting and search
            $items = CatalogMaster_Database::get_catalog_items_modern(
                $catalog_id, 
                $page_size, 
                $offset, 
                $search, 
                $sort_column, 
                $sort_direction
            );
            
            $total_count = CatalogMaster_Database::get_catalog_items_count($catalog_id, $search);
            
            // Format data as objects for Modern Table Manager
            $data = array();
            foreach ($items as $item) {
                $data[] = array(
                    'id' => $item->id,
                    'product_id' => $item->product_id ?? '',
                    'product_name' => $item->product_name ?? '',
                    'product_price' => $item->product_price ?? '',
                    'product_qty' => $item->product_qty ?? '',
                    'product_image_url' => $item->product_image_url ?? '',
                    'product_sort_order' => $item->product_sort_order ?? '',
                    'product_description' => $item->product_description ?? '',
                    'category_id_1' => $item->category_id_1 ?? '',
                    'category_name_1' => $item->category_name_1 ?? '',
                    'category_image_1' => $item->category_image_1 ?? '',
                    'category_sort_order_1' => $item->category_sort_order_1 ?? '',
                    'category_id_2' => $item->category_id_2 ?? '',
                    'category_name_2' => $item->category_name_2 ?? '',
                    'category_image_2' => $item->category_image_2 ?? '',
                    'category_sort_order_2' => $item->category_sort_order_2 ?? '',
                    'category_id_3' => $item->category_id_3 ?? '',
                    'category_name_3' => $item->category_name_3 ?? '',
                    'category_image_3' => $item->category_image_3 ?? '',
                    'category_sort_order_3' => $item->category_sort_order_3 ?? ''
                );
            }
            
            wp_send_json_success(array(
                'data' => $data,
                'total' => $total_count,
                'page' => $page,
                'page_size' => $page_size,
                'total_pages' => ceil($total_count / $page_size)
            ));
            
        } else {
            // Legacy DataTables format
            $start = intval($_POST['start']);
            $length = intval($_POST['length']);
            $search = sanitize_text_field($_POST['search']['value']);
            
            $items = CatalogMaster_Database::get_catalog_items($catalog_id, $length, $start);
            $total_count = CatalogMaster_Database::get_catalog_items_count($catalog_id);
            
            // Format data for DataTable - all fields
            $data = array();
            foreach ($items as $item) {
                $row = array();
                
                // Basic product fields
                $row[] = $item->id ?? '';
                $row[] = esc_html($item->product_id ?? '');
                $row[] = esc_html($item->product_name ?? '');
                $row[] = $item->product_price ? number_format($item->product_price, 2) : '';
                $row[] = intval($item->product_qty ?? 0);
                $row[] = $item->product_image_url ? '<img src="' . esc_url($item->product_image_url) . '" style="max-width:50px;max-height:50px;" loading="lazy">' : '';
                $row[] = intval($item->product_sort_order ?? 0);
                $row[] = esc_html(wp_trim_words($item->product_description ?? '', 8));
                
                // Category 1
                $row[] = esc_html($item->category_id_1 ?? '');
                $row[] = esc_html($item->category_name_1 ?? '');
                $row[] = $item->category_image_1 ? '<img src="' . esc_url($item->category_image_1) . '" style="max-width:40px;max-height:40px;" loading="lazy">' : '';
                $row[] = intval($item->category_sort_order_1 ?? 0);
                
                // Category 2
                $row[] = esc_html($item->category_id_2 ?? '');
                $row[] = esc_html($item->category_name_2 ?? '');
                $row[] = $item->category_image_2 ? '<img src="' . esc_url($item->category_image_2) . '" style="max-width:40px;max-height:40px;" loading="lazy">' : '';
                $row[] = intval($item->category_sort_order_2 ?? 0);
                
                // Category 3
                $row[] = esc_html($item->category_id_3 ?? '');
                $row[] = esc_html($item->category_name_3 ?? '');
                $row[] = $item->category_image_3 ? '<img src="' . esc_url($item->category_image_3) . '" style="max-width:40px;max-height:40px;" loading="lazy">' : '';
                $row[] = intval($item->category_sort_order_3 ?? 0);
                
                // Actions
                $row[] = '<div class="actions-column">' .
                         '<button class="button button-small edit-item" data-id="' . $item->id . '" title="Редагувати">✏️</button> ' .
                         '<button class="button button-small button-link-delete delete-item" data-id="' . $item->id . '" title="Видалити">🗑️</button>' .
                         '</div>';
                
                $data[] = $row;
            }
            
            wp_send_json(array(
                'draw' => intval($_POST['draw']),
                'recordsTotal' => $total_count,
                'recordsFiltered' => $total_count,
                'data' => $data
            ));
        }
    }
    
    /**
     * Update catalog item
     */
    public function update_item() {
        check_ajax_referer('catalog_master_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'catalog_master_items';
        
        $item_id = intval($_POST['item_id']);
        $data = $_POST['data'];
        
        // Sanitize data
        $update_data = array();
        $allowed_fields = array(
            'product_id', 'product_name', 'product_price', 'product_qty',
            'product_image_url', 'product_sort_order', 'product_description',
            'category_id_1', 'category_id_2', 'category_id_3',
            'category_name_1', 'category_name_2', 'category_name_3',
            'category_image_1', 'category_image_2', 'category_image_3',
            'category_sort_order_1', 'category_sort_order_2', 'category_sort_order_3'
        );
        
        foreach ($data as $field => $value) {
            if (in_array($field, $allowed_fields)) {
                if (in_array($field, array('product_price'))) {
                    $update_data[$field] = floatval($value);
                } elseif (in_array($field, array('product_qty', 'product_sort_order', 'category_sort_order_1', 'category_sort_order_2', 'category_sort_order_3'))) {
                    $update_data[$field] = intval($value);
                } else {
                    $update_data[$field] = sanitize_text_field($value);
                }
            }
        }
        
        $result = $wpdb->update($table, $update_data, array('id' => $item_id));
        
        if ($result !== false) {
            wp_send_json_success('Запис оновлено');
        } else {
            wp_send_json_error('Помилка оновлення');
        }
    }
    
    /**
     * Delete catalog item
     */
    public function delete_item() {
        check_ajax_referer('catalog_master_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'catalog_master_items';
        
        $item_id = intval($_POST['item_id']);
        
        $result = $wpdb->delete($table, array('id' => $item_id));
        
        if ($result) {
            wp_send_json_success('Запис видалено');
        } else {
            wp_send_json_error('Помилка видалення');
        }
    }
    
    /**
     * Add new catalog item
     */
    public function add_item() {
        check_ajax_referer('catalog_master_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'catalog_master_items';
        
        $catalog_id = intval($_POST['catalog_id']);
        $data = $_POST['data'];
        
        // Sanitize data
        $insert_data = array('catalog_id' => $catalog_id);
        $allowed_fields = array(
            'product_id', 'product_name', 'product_price', 'product_qty',
            'product_image_url', 'product_sort_order', 'product_description',
            'category_id_1', 'category_id_2', 'category_id_3',
            'category_name_1', 'category_name_2', 'category_name_3',
            'category_image_1', 'category_image_2', 'category_image_3',
            'category_sort_order_1', 'category_sort_order_2', 'category_sort_order_3'
        );
        
        foreach ($data as $field => $value) {
            if (in_array($field, $allowed_fields)) {
                if (in_array($field, array('product_price'))) {
                    $insert_data[$field] = floatval($value);
                } elseif (in_array($field, array('product_qty', 'product_sort_order', 'category_sort_order_1', 'category_sort_order_2', 'category_sort_order_3'))) {
                    $insert_data[$field] = intval($value);
                } else {
                    $insert_data[$field] = sanitize_text_field($value);
                }
            }
        }
        
        $result = $wpdb->insert($table, $insert_data);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => 'Запис додано',
                'item_id' => $wpdb->insert_id
            ));
        } else {
            wp_send_json_error('Помилка додавання');
        }
    }

    /**
     * Get catalog statistics (items count, etc.)
     */
    public function get_catalog_stats() {
        check_ajax_referer('catalog_master_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $catalog_id = intval($_POST['catalog_id']);
        
        if (!$catalog_id) {
            wp_send_json_error('Invalid catalog ID');
            return;
        }
        
        $items_count = CatalogMaster_Database::get_catalog_items_count($catalog_id);
        $mappings_count = count(CatalogMaster_Database::get_column_mapping($catalog_id));
        
        wp_send_json_success(array(
            'items_count' => $items_count,
            'mappings_count' => $mappings_count
        ));
    }
} 