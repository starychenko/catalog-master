<?php
/**
 * AJAX handlers class
 */

if (!defined('ABSPATH')) {
    exit;
}

class CatalogMaster_Ajax {
    
    public function __construct() {
        add_action('wp_ajax_catalog_master_test_sheets_connection', array($this, 'test_sheets_connection'));
        add_action('wp_ajax_catalog_master_get_sheets_headers', array($this, 'get_sheets_headers'));
        add_action('wp_ajax_catalog_master_save_column_mapping', array($this, 'save_column_mapping'));
        add_action('wp_ajax_catalog_master_import_data', array($this, 'import_data'));
        add_action('wp_ajax_catalog_master_get_catalog_data', array($this, 'get_catalog_data'));
        add_action('wp_ajax_catalog_master_update_item', array($this, 'update_item'));
        add_action('wp_ajax_catalog_master_delete_item', array($this, 'delete_item'));
        add_action('wp_ajax_catalog_master_add_item', array($this, 'add_item'));
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
        check_ajax_referer('catalog_master_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $catalog_id = intval($_POST['catalog_id']);
        $mappings = $_POST['mappings'];
        
        if (!$catalog_id || !is_array($mappings)) {
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
        
        CatalogMaster_Database::save_column_mapping($catalog_id, $clean_mappings);
        
        wp_send_json_success('Маппінг збережено');
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
        
        if (!$catalog_id) {
            wp_send_json_error('Невірний ID каталогу');
        }
        
        // Get catalog info
        $catalog = CatalogMaster_Database::get_catalog($catalog_id);
        if (!$catalog) {
            wp_send_json_error('Каталог не знайдено');
        }
        
        // Get column mappings
        $mappings = CatalogMaster_Database::get_column_mapping($catalog_id);
        if (empty($mappings)) {
            wp_send_json_error('Спочатку налаштуйте відповідність стовпців');
        }
        
        // Import data
        $result = CatalogMaster_GoogleSheets::import_catalog_data(
            $catalog_id,
            $catalog->google_sheet_url,
            $catalog->sheet_name,
            $mappings
        );
        
        if (isset($result['error'])) {
            wp_send_json_error($result['error']);
        } else {
            wp_send_json_success($result);
        }
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
} 