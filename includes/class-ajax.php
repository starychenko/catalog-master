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
        add_action('wp_ajax_catalog_master_clear_cache', array($this, 'clear_cache'));
        add_action('wp_ajax_catalog_master_upload_image', array($this, 'upload_image'));
        add_action('wp_ajax_catalog_master_cleanup_test_image', array($this, 'cleanup_test_image'));
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
            $filters = isset($_POST['filters']) ? $_POST['filters'] : array();
            
            // Sanitize and validate filters
            $sanitized_filters = $this->sanitize_filters($filters);
            
            // Calculate offset
            $offset = ($page - 1) * $page_size;
            
            // Get items with sorting, search, and filters
            $items = CatalogMaster_Database::get_catalog_items_modern(
                $catalog_id, 
                $page_size, 
                $offset, 
                $search, 
                $sort_column, 
                $sort_direction,
                $sanitized_filters
            );
            
            $total_count = CatalogMaster_Database::get_catalog_items_count($catalog_id, $search, $sanitized_filters);
            
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
                'total_pages' => ceil($total_count / $page_size),
                'applied_filters' => $sanitized_filters
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
        
        // Handle both legacy format (with 'data' array) and new inline editing format
        $data = isset($_POST['data']) ? $_POST['data'] : $_POST;
        
        // Remove system fields that shouldn't be updated
        unset($data['action'], $data['nonce'], $data['item_id']);
        
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
        
        if (empty($update_data)) {
            wp_send_json_error('Немає даних для оновлення');
            return;
        }
        
        $result = $wpdb->update($table, $update_data, array('id' => $item_id));
        
        if ($result !== false) {
            // Get updated item data to return
            $updated_item = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $item_id
            ));
            
            wp_send_json_success(array(
                'message' => 'Запис оновлено',
                'updated_data' => $update_data,
                'item' => $updated_item
            ));
        } else {
            wp_send_json_error('Помилка оновлення: ' . $wpdb->last_error);
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

    /**
     * Clear Google Sheets cache for catalog
     */
    public function clear_cache() {
        check_ajax_referer('catalog_master_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $catalog_id = intval($_POST['catalog_id']);
        
        if (!$catalog_id) {
            wp_send_json_error('Невірний ID каталогу');
            return;
        }
        
        // Clear all cache for this catalog
        $transient_data_key = 'cm_import_data_' . $catalog_id;
        $transient_total_key = 'cm_import_total_' . $catalog_id;
        $transient_img_cache_key = 'cm_import_img_cache_' . $catalog_id;
        
        delete_transient($transient_data_key);
        delete_transient($transient_total_key);
        delete_transient($transient_img_cache_key);
        
        CatalogMaster_Logger::info('🗑️ Cache cleared for catalog', array(
            'catalog_id' => $catalog_id,
            'keys_cleared' => array($transient_data_key, $transient_total_key, $transient_img_cache_key)
        ));
        
        wp_send_json_success(array(
            'message' => 'Кеш очищено! Тепер можна отримати свіжі дані з Google Sheets.'
        ));
    }
    
    /**
     * Sanitize and validate advanced filters
     */
    private function sanitize_filters($filters) {
        if (!is_array($filters)) {
            return array();
        }
        
        $sanitized = array();
        $allowed_columns = array(
            'id', 'product_id', 'product_name', 'product_price', 'product_qty',
            'product_image_url', 'product_sort_order', 'product_description',
            'category_id_1', 'category_name_1', 'category_image_1', 'category_sort_order_1',
            'category_id_2', 'category_name_2', 'category_image_2', 'category_sort_order_2',
            'category_id_3', 'category_name_3', 'category_image_3', 'category_sort_order_3'
        );
        
        $allowed_operators = array(
            'eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'between',
            'is_null', 'is_not_null', 'contains', 'not_contains'
        );
        
        foreach ($filters as $filter) {
            if (!isset($filter['column']) || !isset($filter['operator'])) {
                continue;
            }
            
            $column = sanitize_text_field($filter['column']);
            $operator = sanitize_text_field($filter['operator']);
            
            // Validate column and operator
            if (!in_array($column, $allowed_columns) || !in_array($operator, $allowed_operators)) {
                continue;
            }
            
            $sanitized_filter = array(
                'column' => $column,
                'operator' => $operator,
                'logic' => isset($filter['logic']) ? sanitize_text_field($filter['logic']) : 'AND'
            );
            
            // Sanitize values based on operator
            if (in_array($operator, array('is_null', 'is_not_null'))) {
                // No values needed for these operators
            } elseif ($operator === 'between') {
                $sanitized_filter['value'] = sanitize_text_field($filter['value'] ?? '');
                $sanitized_filter['value2'] = sanitize_text_field($filter['value2'] ?? '');
            } else {
                $sanitized_filter['value'] = sanitize_text_field($filter['value'] ?? '');
            }
            
            $sanitized[] = $sanitized_filter;
        }
        
        return $sanitized;
    }
    
    /**
     * Upload and process image for catalog item
     */
    public function upload_image() {
        check_ajax_referer('catalog_master_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        // Check if file was uploaded
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('Помилка завантаження файлу');
            return;
        }
        
        $catalog_id = intval($_POST['catalog_id']);
        $item_id = intval($_POST['item_id']);
        $column = sanitize_text_field($_POST['column']);
        
        if (!$catalog_id || !$item_id || !$column) {
            wp_send_json_error('Невірні параметри');
            return;
        }
        
        // Validate column type
        $allowed_image_columns = array(
            'product_image_url', 
            'category_image_1', 
            'category_image_2', 
            'category_image_3'
        );
        
        if (!in_array($column, $allowed_image_columns)) {
            wp_send_json_error('Невірний тип стовпця');
            return;
        }
        
        // Get current item data
        global $wpdb;
        $table = $wpdb->prefix . 'catalog_master_items';
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $item_id
        ));
        
        if (!$item) {
            wp_send_json_error('Запис не знайдено');
            return;
        }
        
        // Determine image type and filename base
        if ($column === 'product_image_url') {
            $image_type = 'product';
            $filename_base = !empty($item->product_id) ? sanitize_file_name($item->product_id) : ('product_' . $item_id);
        } else {
            // Category image
            $image_type = 'category';
            $level = substr($column, -1); // Extract level (1, 2, 3)
            
            $category_id_field = 'category_id_' . $level;
            $category_name_field = 'category_name_' . $level;
            
            // Prefer category_id, fallback to category_name with transliteration
            if (!empty($item->$category_id_field)) {
                $filename_base = sanitize_file_name($item->$category_id_field);
            } elseif (!empty($item->$category_name_field)) {
                $filename_base = $this->text_to_filename($item->$category_name_field);
            } else {
                $filename_base = 'category' . $level . '_' . $item_id;
            }
        }
        
        // Check PHP extensions
        $gd_enabled = extension_loaded('gd');
        $imagick_enabled = extension_loaded('imagick');
        
        CatalogMaster_Logger::info('🖼️ Starting image upload', array(
            'catalog_id' => $catalog_id,
            'item_id' => $item_id,
            'column' => $column,
            'image_type' => $image_type,
            'filename_base' => $filename_base,
            'file_name' => $_FILES['image']['name'],
            'file_size' => $_FILES['image']['size'],
            'gd_enabled' => $gd_enabled,
            'imagick_enabled' => $imagick_enabled,
            'php_version' => PHP_VERSION
        ));
        
        // Check if we have any image processing capability
        if (!$gd_enabled && !$imagick_enabled) {
            CatalogMaster_Logger::error('❌ No image processing extensions available');
            wp_send_json_error('Сервер не підтримує обробку зображень (потрібен GD або ImageMagick)');
            return;
        }
        
        try {
            // Delete old image if exists
            $old_image_url = $item->$column;
            if (!empty($old_image_url)) {
                $this->delete_local_image($old_image_url, $catalog_id);
            }
            
            // Process uploaded image
            $new_image_url = $this->process_uploaded_image(
                $_FILES['image']['tmp_name'],
                $catalog_id,
                $filename_base,
                $image_type
            );
            
            if (empty($new_image_url)) {
                wp_send_json_error('Помилка обробки зображення');
                return;
            }
            
            // Update database
            $result = $wpdb->update(
                $table,
                array($column => $new_image_url),
                array('id' => $item_id)
            );
            
            if ($result === false) {
                wp_send_json_error('Помилка оновлення бази даних');
                return;
            }
            
            CatalogMaster_Logger::info('✅ Image uploaded successfully', array(
                'item_id' => $item_id,
                'column' => $column,
                'new_url' => $new_image_url
            ));
            
            wp_send_json_success(array(
                'message' => 'Зображення завантажено успішно',
                'image_url' => $new_image_url,
                'column' => $column
            ));
            
        } catch (Exception $e) {
            CatalogMaster_Logger::error('❌ Image upload failed', array(
                'error' => $e->getMessage(),
                'item_id' => $item_id,
                'column' => $column
            ));
            
            // Try fallback method without resizing if main method fails
            if (strpos($e->getMessage(), 'зміни розміру') !== false || strpos($e->getMessage(), 'редактора зображень') !== false) {
                CatalogMaster_Logger::info('🔄 Trying fallback method without resizing');
                try {
                    $fallback_url = $this->process_uploaded_image_fallback(
                        $_FILES['image']['tmp_name'],
                        $catalog_id,
                        $filename_base,
                        $image_type
                    );
                    
                    if (!empty($fallback_url)) {
                        // Update database with fallback result
                        $result = $wpdb->update(
                            $table,
                            array($column => $fallback_url),
                            array('id' => $item_id)
                        );
                        
                        if ($result !== false) {
                            CatalogMaster_Logger::info('✅ Image uploaded via fallback method', array(
                                'item_id' => $item_id,
                                'column' => $column,
                                'fallback_url' => $fallback_url
                            ));
                            
                            wp_send_json_success(array(
                                'message' => 'Зображення завантажено (без зміни розміру)',
                                'image_url' => $fallback_url,
                                'column' => $column
                            ));
                            return;
                        }
                    }
                } catch (Exception $fallback_error) {
                    CatalogMaster_Logger::error('❌ Fallback method also failed', array(
                        'fallback_error' => $fallback_error->getMessage()
                    ));
                }
            }
            
            wp_send_json_error('Помилка: ' . $e->getMessage());
        }
    }
    
    /**
     * Process uploaded image file (similar to download_and_process_image but for local files)
     */
    private function process_uploaded_image($temp_file_path, $catalog_id, $filename_base, $type = 'product', $target_width = 1000, $target_height = 1000) {
        CatalogMaster_Logger::info('🖼️ Starting image processing', array(
            'temp_file_path' => $temp_file_path,
            'filename_base' => $filename_base,
            'type' => $type,
            'file_exists' => file_exists($temp_file_path),
            'file_size' => file_exists($temp_file_path) ? filesize($temp_file_path) : 'N/A',
            'is_readable' => is_readable($temp_file_path)
        ));
        
        // Validate file exists
        if (!file_exists($temp_file_path)) {
            throw new Exception('Файл не знайдено: ' . $temp_file_path);
        }
        
        // Check if file is readable
        if (!is_readable($temp_file_path)) {
            throw new Exception('Файл недоступний для читання: ' . $temp_file_path);
        }
        
        // Get file size
        $file_size = filesize($temp_file_path);
        if ($file_size === false || $file_size === 0) {
            throw new Exception('Файл порожній або пошкоджений');
        }
        
        CatalogMaster_Logger::info('📊 File validation passed', array(
            'file_size' => $file_size
        ));
        
        // Validate image using getimagesize
        $image_info = getimagesize($temp_file_path);
        if ($image_info === false) {
            // Try to get more information about why it failed
            $mime_type = mime_content_type($temp_file_path);
            CatalogMaster_Logger::error('❌ getimagesize failed', array(
                'file_path' => $temp_file_path,
                'mime_type' => $mime_type,
                'file_size' => $file_size
            ));
            throw new Exception('Невірний формат зображення. MIME тип: ' . $mime_type);
        }
        
        CatalogMaster_Logger::info('📸 Image info retrieved', array(
            'width' => $image_info[0],
            'height' => $image_info[1],
            'mime_type' => $image_info['mime'],
            'channels' => isset($image_info['channels']) ? $image_info['channels'] : 'N/A'
        ));
        
        // Create directory structure
        $upload_dir = wp_upload_dir();
        $base_images_dir = $upload_dir['basedir'] . '/catalog-master-images/catalog-' . $catalog_id . '/';
        $sub_dir = ($type === 'product') ? 'products/' : 'categories/';
        $full_target_dir = $base_images_dir . $sub_dir;
        
        if (!file_exists($full_target_dir)) {
            if (!wp_mkdir_p($full_target_dir)) {
                throw new Exception('Не вдалося створити папку: ' . $full_target_dir);
            }
            CatalogMaster_Logger::info('📁 Created directory: ' . $full_target_dir);
        }
        
        $final_filename = sanitize_file_name($filename_base) . '.jpg';
        $final_file_path = $full_target_dir . $final_filename;
        
        CatalogMaster_Logger::info('🎯 Target file path: ' . $final_file_path);
        
        // Get available image editors
        $available_editors = wp_image_editor_supports();
        CatalogMaster_Logger::info('🔧 Available image editors', $available_editors);
        
        // Process image with WordPress Image Editor
        $image_editor = wp_get_image_editor($temp_file_path);
        
        if (is_wp_error($image_editor)) {
            CatalogMaster_Logger::error('❌ wp_get_image_editor failed', array(
                'error_message' => $image_editor->get_error_message(),
                'error_data' => $image_editor->get_error_data()
            ));
            throw new Exception('Помилка редактора зображень: ' . $image_editor->get_error_message());
        }
        
        CatalogMaster_Logger::info('✅ Image editor created successfully');
        
        // Get current image size
        $current_size = $image_editor->get_size();
        CatalogMaster_Logger::info('📐 Current image size', $current_size);
        
        // Set quality and resize
        $image_editor->set_quality(90);
        CatalogMaster_Logger::info('🎨 Quality set to 90');
        
        // Always resize to target dimensions (1000x1000)
        CatalogMaster_Logger::info('🔄 Resizing image to target size', array(
            'from' => $current_size['width'] . 'x' . $current_size['height'],
            'to' => $target_width . 'x' . $target_height
        ));
        
        $resized = $image_editor->resize($target_width, $target_height, true); // true for crop
        
        if (is_wp_error($resized)) {
            CatalogMaster_Logger::error('❌ Resize failed', array(
                'error_message' => $resized->get_error_message(),
                'error_data' => $resized->get_error_data(),
                'target_width' => $target_width,
                'target_height' => $target_height,
                'current_width' => $current_size['width'],
                'current_height' => $current_size['height']
            ));
            throw new Exception('Помилка зміни розміру: ' . $resized->get_error_message());
        }
        CatalogMaster_Logger::info('✅ Image resized successfully to ' . $target_width . 'x' . $target_height);
        
        // Save as JPG
        $saved = $image_editor->save($final_file_path, 'image/jpeg');
        
        if (is_wp_error($saved)) {
            CatalogMaster_Logger::error('❌ Save failed (WP_Error)', array(
                'error_message' => $saved->get_error_message(),
                'error_data' => $saved->get_error_data(),
                'target_path' => $final_file_path
            ));
            throw new Exception('Помилка збереження: ' . $saved->get_error_message());
        }
        
        if (!$saved || !isset($saved['path']) || !file_exists($saved['path'])) {
            CatalogMaster_Logger::error('❌ Save failed (file not created)', array(
                'saved_result' => $saved,
                'target_path' => $final_file_path,
                'file_exists' => file_exists($final_file_path)
            ));
            throw new Exception('Помилка збереження: файл не створено');
        }
        
        // Verify saved file
        $saved_file_size = filesize($saved['path']);
        if ($saved_file_size === false || $saved_file_size === 0) {
            throw new Exception('Збережений файл порожній');
        }
        
        // Return URL
        $final_url = $upload_dir['baseurl'] . '/catalog-master-images/catalog-' . $catalog_id . '/' . $sub_dir . $final_filename;
        
        CatalogMaster_Logger::info('🎨 Image processed and saved successfully', array(
            'final_url' => $final_url,
            'filename_base' => $filename_base,
            'original_size' => $file_size,
            'processed_size' => $saved_file_size,
            'saved_path' => $saved['path']
        ));
        
        return $final_url;
    }
    
    /**
     * Fallback method for image processing (without resizing)
     */
    private function process_uploaded_image_fallback($temp_file_path, $catalog_id, $filename_base, $type = 'product') {
        CatalogMaster_Logger::info('🔄 Starting fallback image processing', array(
            'temp_file_path' => $temp_file_path,
            'filename_base' => $filename_base,
            'type' => $type
        ));
        
        // Validate file exists
        if (!file_exists($temp_file_path)) {
            throw new Exception('Файл не знайдено для fallback обробки');
        }
        
        // Basic validation
        $image_info = getimagesize($temp_file_path);
        if ($image_info === false) {
            throw new Exception('Fallback: невірний формат зображення');
        }
        
        // Create directory structure
        $upload_dir = wp_upload_dir();
        $base_images_dir = $upload_dir['basedir'] . '/catalog-master-images/catalog-' . $catalog_id . '/';
        $sub_dir = ($type === 'product') ? 'products/' : 'categories/';
        $full_target_dir = $base_images_dir . $sub_dir;
        
        if (!file_exists($full_target_dir)) {
            if (!wp_mkdir_p($full_target_dir)) {
                throw new Exception('Fallback: не вдалося створити папку: ' . $full_target_dir);
            }
        }
        
        // Determine file extension based on mime type
        $extension = 'jpg'; // Default
        switch ($image_info['mime']) {
            case 'image/jpeg':
                $extension = 'jpg';
                break;
            case 'image/png':
                $extension = 'png';
                break;
            case 'image/gif':
                $extension = 'gif';
                break;
            case 'image/webp':
                $extension = 'webp';
                break;
            default:
                $extension = 'jpg';
        }
        
        $final_filename = sanitize_file_name($filename_base) . '.' . $extension;
        $final_file_path = $full_target_dir . $final_filename;
        
        // Simply copy the file
        if (!copy($temp_file_path, $final_file_path)) {
            throw new Exception('Fallback: не вдалося скопіювати файл');
        }
        
        // Verify copied file
        if (!file_exists($final_file_path) || filesize($final_file_path) === 0) {
            throw new Exception('Fallback: скопійований файл порожній або не існує');
        }
        
        // Return URL
        $final_url = $upload_dir['baseurl'] . '/catalog-master-images/catalog-' . $catalog_id . '/' . $sub_dir . $final_filename;
        
        CatalogMaster_Logger::info('✅ Fallback image processing completed', array(
            'final_url' => $final_url,
            'original_mime' => $image_info['mime'],
            'final_extension' => $extension,
            'file_size' => filesize($final_file_path)
        ));
        
        return $final_url;
    }
    
    /**
     * Delete local image file
     */
    private function delete_local_image($image_url, $catalog_id) {
        if (empty($image_url)) {
            return;
        }
        
        // Check if this is a local catalog master image
        if (strpos($image_url, '/catalog-master-images/catalog-' . $catalog_id . '/') === false) {
            return; // Not our image, don't delete
        }
        
        $upload_dir = wp_upload_dir();
        $base_url = $upload_dir['baseurl'] . '/catalog-master-images/catalog-' . $catalog_id . '/';
        
        if (strpos($image_url, $base_url) === 0) {
            $relative_path = substr($image_url, strlen($base_url));
            $file_path = $upload_dir['basedir'] . '/catalog-master-images/catalog-' . $catalog_id . '/' . $relative_path;
            
            if (file_exists($file_path)) {
                if (unlink($file_path)) {
                    CatalogMaster_Logger::info('🗑️ Deleted old image', array(
                        'file_path' => $file_path,
                        'image_url' => $image_url
                    ));
                } else {
                    CatalogMaster_Logger::warning('⚠️ Could not delete old image', array(
                        'file_path' => $file_path
                    ));
                }
            }
        }
    }
    
    /**
     * Convert text to filename-safe format with transliteration
     */
    private function text_to_filename($text) {
        // Transliteration map for Cyrillic and other characters
        $transliteration = array(
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo', 'ж' => 'zh',
            'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o',
            'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts',
            'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh',
            'З' => 'Z', 'И' => 'I', 'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O',
            'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'Ts',
            'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Sch', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '', 'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
            // Ukrainian specific
            'і' => 'i', 'ї' => 'yi', 'є' => 'ye', 'ґ' => 'g',
            'І' => 'I', 'Ї' => 'Yi', 'Є' => 'Ye', 'Ґ' => 'G'
        );
        
        // Apply transliteration
        $text = strtr($text, $transliteration);
        
        // Convert to lowercase
        $text = strtolower($text);
        
        // Replace non-alphanumeric characters with underscore
        $text = preg_replace('/[^a-z0-9]+/', '_', $text);
        
        // Remove leading/trailing underscores
        $text = trim($text, '_');
        
        // Limit length
        $text = substr($text, 0, 50);
        
        // Ensure it's not empty
        if (empty($text)) {
            $text = 'image';
        }
        
        return $text;
    }
    
    /**
     * Cleanup test image file
     */
    public function cleanup_test_image() {
        // Verify nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'cleanup_test_image') || !current_user_can('manage_options')) {
            wp_send_json_error('Invalid permissions');
            return;
        }
        
        $file_path = urldecode($_POST['path']);
        
        // Security check: only allow deletion of test images in uploads directory
        $upload_dir = wp_upload_dir();
        if (strpos($file_path, $upload_dir['basedir']) !== 0 || strpos($file_path, 'test_image_') === false) {
            wp_send_json_error('Invalid file path');
            return;
        }
        
        // Delete the file
        if (file_exists($file_path)) {
            if (unlink($file_path)) {
                wp_send_json_success('Test image cleaned up');
            } else {
                wp_send_json_error('Could not delete test image');
            }
        } else {
            wp_send_json_success('Test image already cleaned up');
        }
    }
} 