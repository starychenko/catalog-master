<?php
/**
 * Data exporter class
 */

if (!defined('ABSPATH')) {
    exit;
}

class CatalogMaster_Exporter {
    
    public function __construct() {
        add_action('init', array($this, 'handle_export_requests'));
        add_action('wp_ajax_catalog_master_export', array($this, 'ajax_export'));
    }
    
    /**
     * Handle export requests
     */
    public function handle_export_requests() {
        if (!isset($_GET['catalog_master_export'])) {
            return;
        }
        
        $action = sanitize_text_field($_GET['catalog_master_export']);
        $catalog_id = intval($_GET['catalog_id']);
        $format = sanitize_text_field($_GET['format']);
        
        if (!current_user_can('manage_options') && $action !== 'feed') {
            wp_die('Insufficient permissions');
        }
        
        if (!$catalog_id) {
            wp_die('Invalid catalog ID');
        }
        
        switch ($action) {
            case 'download':
                $this->export_download($catalog_id, $format);
                break;
            case 'feed':
                $this->export_feed($catalog_id, $format);
                break;
        }
    }
    
    /**
     * AJAX export handler
     */
    public function ajax_export() {
        check_ajax_referer('catalog_master_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $catalog_id = intval($_POST['catalog_id']);
        $format = sanitize_text_field($_POST['format']);
        
        $download_url = $this->generate_export_url($catalog_id, $format, 'download');
        $feed_url = $this->generate_export_url($catalog_id, $format, 'feed');
        
        wp_send_json_success(array(
            'download_url' => $download_url,
            'feed_url' => $feed_url
        ));
    }
    
    /**
     * Export data for download
     */
    private function export_download($catalog_id, $format) {
        $catalog = CatalogMaster_Database::get_catalog($catalog_id);
        if (!$catalog) {
            wp_die('Catalog not found');
        }
        
        $items = CatalogMaster_Database::get_catalog_items($catalog_id);
        
        switch ($format) {
            case 'csv':
                $this->export_csv($catalog, $items, true);
                break;
            case 'excel':
                $this->export_excel($catalog, $items, true);
                break;
            default:
                wp_die('Invalid format');
        }
    }
    
    /**
     * Export data as feed
     */
    private function export_feed($catalog_id, $format) {
        $catalog = CatalogMaster_Database::get_catalog($catalog_id);
        if (!$catalog) {
            wp_die('Catalog not found');
        }
        
        $items = CatalogMaster_Database::get_catalog_items($catalog_id);
        
        // Set headers for feed
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        switch ($format) {
            case 'csv':
                $this->export_csv($catalog, $items, false);
                break;
            case 'json':
                $this->export_json($catalog, $items);
                break;
            case 'xml':
                $this->export_xml($catalog, $items);
                break;
            default:
                wp_die('Invalid format');
        }
    }
    
    /**
     * Export to CSV
     */
    private function export_csv($catalog, $items, $download = false) {
        $filename = sanitize_file_name($catalog->name) . '_' . date('Y-m-d') . '.csv';
        
        if ($download) {
            header('Content-Type: application/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        } else {
            header('Content-Type: text/csv');
        }
        
        $output = fopen('php://output', 'w');
        
        // Write BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Headers
        $headers = array(
            'ID', 'Product ID', 'Product Name', 'Price', 'Quantity', 'Image URL',
            'Sort Order', 'Description', 'Category ID 1', 'Category ID 2', 'Category ID 3',
            'Category Name 1', 'Category Name 2', 'Category Name 3',
            'Category Image 1', 'Category Image 2', 'Category Image 3',
            'Category Sort 1', 'Category Sort 2', 'Category Sort 3'
        );
        
        fputcsv($output, $headers);
        
        // Data
        foreach ($items as $item) {
            $row = array(
                $item->id,
                $item->product_id,
                $item->product_name,
                $item->product_price,
                $item->product_qty,
                $item->product_image_url,
                $item->product_sort_order,
                $item->product_description,
                $item->category_id_1,
                $item->category_id_2,
                $item->category_id_3,
                $item->category_name_1,
                $item->category_name_2,
                $item->category_name_3,
                $item->category_image_1,
                $item->category_image_2,
                $item->category_image_3,
                $item->category_sort_order_1,
                $item->category_sort_order_2,
                $item->category_sort_order_3
            );
            
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export to Excel (HTML table format)
     */
    private function export_excel($catalog, $items, $download = false) {
        $filename = sanitize_file_name($catalog->name) . '_' . date('Y-m-d') . '.xls';
        
        if ($download) {
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        } else {
            header('Content-Type: application/vnd.ms-excel');
        }
        
        echo '<meta charset="UTF-8">';
        echo '<table border="1">';
        echo '<tr>';
        echo '<th>ID</th>';
        echo '<th>Product ID</th>';
        echo '<th>Product Name</th>';
        echo '<th>Price</th>';
        echo '<th>Quantity</th>';
        echo '<th>Image URL</th>';
        echo '<th>Sort Order</th>';
        echo '<th>Description</th>';
        echo '<th>Category ID 1</th>';
        echo '<th>Category ID 2</th>';
        echo '<th>Category ID 3</th>';
        echo '<th>Category Name 1</th>';
        echo '<th>Category Name 2</th>';
        echo '<th>Category Name 3</th>';
        echo '<th>Category Image 1</th>';
        echo '<th>Category Image 2</th>';
        echo '<th>Category Image 3</th>';
        echo '<th>Category Sort 1</th>';
        echo '<th>Category Sort 2</th>';
        echo '<th>Category Sort 3</th>';
        echo '</tr>';
        
        foreach ($items as $item) {
            echo '<tr>';
            echo '<td>' . esc_html($item->id) . '</td>';
            echo '<td>' . esc_html($item->product_id) . '</td>';
            echo '<td>' . esc_html($item->product_name) . '</td>';
            echo '<td>' . esc_html($item->product_price) . '</td>';
            echo '<td>' . esc_html($item->product_qty) . '</td>';
            echo '<td>' . esc_html($item->product_image_url) . '</td>';
            echo '<td>' . esc_html($item->product_sort_order) . '</td>';
            echo '<td>' . esc_html($item->product_description) . '</td>';
            echo '<td>' . esc_html($item->category_id_1) . '</td>';
            echo '<td>' . esc_html($item->category_id_2) . '</td>';
            echo '<td>' . esc_html($item->category_id_3) . '</td>';
            echo '<td>' . esc_html($item->category_name_1) . '</td>';
            echo '<td>' . esc_html($item->category_name_2) . '</td>';
            echo '<td>' . esc_html($item->category_name_3) . '</td>';
            echo '<td>' . esc_html($item->category_image_1) . '</td>';
            echo '<td>' . esc_html($item->category_image_2) . '</td>';
            echo '<td>' . esc_html($item->category_image_3) . '</td>';
            echo '<td>' . esc_html($item->category_sort_order_1) . '</td>';
            echo '<td>' . esc_html($item->category_sort_order_2) . '</td>';
            echo '<td>' . esc_html($item->category_sort_order_3) . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        exit;
    }
    
    /**
     * Export to JSON
     */
    private function export_json($catalog, $items) {
        header('Content-Type: application/json');
        
        $data = array(
            'catalog' => array(
                'id' => $catalog->id,
                'name' => $catalog->name,
                'description' => $catalog->description,
                'exported_at' => current_time('c')
            ),
            'items' => array()
        );
        
        foreach ($items as $item) {
            $data['items'][] = array(
                'id' => intval($item->id),
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'product_price' => floatval($item->product_price),
                'product_qty' => intval($item->product_qty),
                'product_image_url' => $item->product_image_url,
                'product_sort_order' => intval($item->product_sort_order),
                'product_description' => $item->product_description,
                'categories' => array(
                    array(
                        'id' => $item->category_id_1,
                        'name' => $item->category_name_1,
                        'image' => $item->category_image_1,
                        'sort_order' => intval($item->category_sort_order_1)
                    ),
                    array(
                        'id' => $item->category_id_2,
                        'name' => $item->category_name_2,
                        'image' => $item->category_image_2,
                        'sort_order' => intval($item->category_sort_order_2)
                    ),
                    array(
                        'id' => $item->category_id_3,
                        'name' => $item->category_name_3,
                        'image' => $item->category_image_3,
                        'sort_order' => intval($item->category_sort_order_3)
                    )
                )
            );
        }
        
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * Export to XML
     */
    private function export_xml($catalog, $items) {
        header('Content-Type: text/xml');
        
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<catalog>';
        echo '<info>';
        echo '<id>' . esc_html($catalog->id) . '</id>';
        echo '<name><![CDATA[' . $catalog->name . ']]></name>';
        echo '<description><![CDATA[' . $catalog->description . ']]></description>';
        echo '<exported_at>' . current_time('c') . '</exported_at>';
        echo '</info>';
        echo '<items>';
        
        foreach ($items as $item) {
            echo '<item>';
            echo '<id>' . esc_html($item->id) . '</id>';
            echo '<product_id><![CDATA[' . $item->product_id . ']]></product_id>';
            echo '<product_name><![CDATA[' . $item->product_name . ']]></product_name>';
            echo '<product_price>' . esc_html($item->product_price) . '</product_price>';
            echo '<product_qty>' . esc_html($item->product_qty) . '</product_qty>';
            echo '<product_image_url><![CDATA[' . $item->product_image_url . ']]></product_image_url>';
            echo '<product_sort_order>' . esc_html($item->product_sort_order) . '</product_sort_order>';
            echo '<product_description><![CDATA[' . $item->product_description . ']]></product_description>';
            echo '<categories>';
            echo '<category>';
            echo '<id><![CDATA[' . $item->category_id_1 . ']]></id>';
            echo '<name><![CDATA[' . $item->category_name_1 . ']]></name>';
            echo '<image><![CDATA[' . $item->category_image_1 . ']]></image>';
            echo '<sort_order>' . esc_html($item->category_sort_order_1) . '</sort_order>';
            echo '</category>';
            echo '<category>';
            echo '<id><![CDATA[' . $item->category_id_2 . ']]></id>';
            echo '<name><![CDATA[' . $item->category_name_2 . ']]></name>';
            echo '<image><![CDATA[' . $item->category_image_2 . ']]></image>';
            echo '<sort_order>' . esc_html($item->category_sort_order_2) . '</sort_order>';
            echo '</category>';
            echo '<category>';
            echo '<id><![CDATA[' . $item->category_id_3 . ']]></id>';
            echo '<name><![CDATA[' . $item->category_name_3 . ']]></name>';
            echo '<image><![CDATA[' . $item->category_image_3 . ']]></image>';
            echo '<sort_order>' . esc_html($item->category_sort_order_3) . '</sort_order>';
            echo '</category>';
            echo '</categories>';
            echo '</item>';
        }
        
        echo '</items>';
        echo '</catalog>';
        exit;
    }
    
    /**
     * Generate export URL
     */
    private function generate_export_url($catalog_id, $format, $action) {
        return add_query_arg(array(
            'catalog_master_export' => $action,
            'catalog_id' => $catalog_id,
            'format' => $format
        ), home_url());
    }
}

// Initialize exporter
new CatalogMaster_Exporter(); 