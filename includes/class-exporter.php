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
        
        CatalogMaster_Logger::info('🔄 Export request received', array(
            'action' => $action,
            'catalog_id' => $catalog_id,
            'format' => $format,
            'url' => $_SERVER['REQUEST_URI'] ?? ''
        ));
        
        if (!current_user_can('manage_options') && $action !== 'feed') {
            CatalogMaster_Logger::error('❌ Export access denied', array(
                'action' => $action,
                'user_can_manage' => current_user_can('manage_options')
            ));
            wp_die('Insufficient permissions');
        }
        
        if (!$catalog_id) {
            CatalogMaster_Logger::error('❌ Export invalid catalog ID', array(
                'catalog_id' => $catalog_id
            ));
            wp_die('Invalid catalog ID');
        }
        
        switch ($action) {
            case 'download':
                CatalogMaster_Logger::info('📥 Processing download export', array(
                    'catalog_id' => $catalog_id,
                    'format' => $format
                ));
                $this->export_download($catalog_id, $format);
                break;
            case 'feed':
                CatalogMaster_Logger::info('📡 Processing feed export', array(
                    'catalog_id' => $catalog_id,
                    'format' => $format
                ));
                $this->export_feed($catalog_id, $format);
                break;
            default:
                CatalogMaster_Logger::error('❌ Invalid export action', array(
                    'action' => $action,
                    'catalog_id' => $catalog_id,
                    'format' => $format
                ));
                wp_die('Invalid action: ' . $action);
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
        
        // Get items count first for validation
        $items_count = CatalogMaster_Database::get_catalog_items_count($catalog_id);
        if ($items_count === 0) {
            wp_die('No items found in catalog for export');
        }
        
        // Check memory limit for large exports
        if ($items_count > 10000) {
            ini_set('memory_limit', '1024M');
            ini_set('max_execution_time', 600);
        }
        
        $items = CatalogMaster_Database::get_catalog_items($catalog_id);
        
        if (empty($items)) {
            wp_die('No items found in catalog');
        }
        
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
        
        if (empty($items)) {
            // Return empty but valid feed instead of error
            $this->send_empty_feed($catalog, $format);
            return;
        }
        
        // Set modern cache headers for feeds
        header('Cache-Control: public, max-age=3600'); // Cache for 1 hour
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', strtotime($catalog->updated_at ?? 'now')) . ' GMT');
        
        switch ($format) {
            case 'csv':
                CatalogMaster_Logger::info('📄 Exporting CSV feed', array('items_count' => count($items)));
                $this->export_csv($catalog, $items, false);
                break;
            case 'excel':
                CatalogMaster_Logger::info('📊 Exporting Excel feed', array('items_count' => count($items)));
                $this->export_excel($catalog, $items, false);
                break;
            case 'json':
                CatalogMaster_Logger::info('🔗 Exporting JSON feed', array('items_count' => count($items)));
                $this->export_json($catalog, $items);
                break;
            case 'xml':
                CatalogMaster_Logger::info('📝 Exporting XML feed', array('items_count' => count($items)));
                $this->export_xml($catalog, $items);
                break;
            default:
                CatalogMaster_Logger::error('❌ Invalid export format in feed', array(
                    'format' => $format,
                    'catalog_id' => $catalog_id,
                    'supported_formats' => ['csv', 'excel', 'json', 'xml']
                ));
                wp_die('Невалідний формат: ' . $format . '. Підтримувані формати: csv, excel, json, xml');
        }
    }
    
    /**
     * Send empty but valid feed
     */
    private function send_empty_feed($catalog, $format) {
        switch ($format) {
            case 'csv':
                header('Content-Type: text/csv; charset=utf-8');
                echo "ID,Product ID,Product Name,Price,Quantity,Image URL,Sort Order,Description,Category ID 1,Category ID 2,Category ID 3,Category Name 1,Category Name 2,Category Name 3,Category Image 1,Category Image 2,Category Image 3,Category Sort 1,Category Sort 2,Category Sort 3\n";
                break;
            case 'excel':
                // For empty excel feed, create minimal XLSX with headers only
                $this->export_excel($catalog, array(), false);
                break;
            case 'json':
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['catalog' => ['id' => $catalog->id, 'name' => $catalog->name], 'items' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                break;
            case 'xml':
                header('Content-Type: text/xml; charset=utf-8');
                echo '<?xml version="1.0" encoding="UTF-8"?><catalog><info><id>' . esc_html($catalog->id) . '</id><name><![CDATA[' . $catalog->name . ']]></name></info><items></items></catalog>';
                break;
        }
        exit;
    }
    
    /**
     * Export to CSV (improved)
     */
    private function export_csv($catalog, $items, $download = false) {
        $filename = sanitize_file_name($catalog->name) . '_' . date('Y-m-d') . '.csv';
        
        if ($download) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        } else {
            header('Content-Type: text/csv; charset=utf-8');
        }
        
        $output = fopen('php://output', 'w');
        
        // Write UTF-8 BOM only for download (Excel compatibility)
        if ($download) {
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        }
        
        // Headers
        $headers = array(
            'ID', 'Product ID', 'Product Name', 'Price', 'Quantity', 'Image URL',
            'Sort Order', 'Description', 'Category ID 1', 'Category ID 2', 'Category ID 3',
            'Category Name 1', 'Category Name 2', 'Category Name 3',
            'Category Image 1', 'Category Image 2', 'Category Image 3',
            'Category Sort 1', 'Category Sort 2', 'Category Sort 3'
        );
        
        fputcsv($output, $headers);
        
        // Data with memory optimization
        foreach ($items as $item) {
            $row = array(
                $item->id ?? '',
                $item->product_id ?? '',
                $item->product_name ?? '',
                $item->product_price ?? '',
                $item->product_qty ?? '',
                $item->product_image_url ?? '',
                $item->product_sort_order ?? '',
                $item->product_description ?? '',
                $item->category_id_1 ?? '',
                $item->category_id_2 ?? '',
                $item->category_id_3 ?? '',
                $item->category_name_1 ?? '',
                $item->category_name_2 ?? '',
                $item->category_name_3 ?? '',
                $item->category_image_1 ?? '',
                $item->category_image_2 ?? '',
                $item->category_image_3 ?? '',
                $item->category_sort_order_1 ?? '',
                $item->category_sort_order_2 ?? '',
                $item->category_sort_order_3 ?? ''
            );
            
            fputcsv($output, $row);
            
            // Free memory periodically for large exports
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export to Real Excel XLSX format
     */
    private function export_excel($catalog, $items, $download = false) {
        $filename = sanitize_file_name($catalog->name) . '_' . date('Y-m-d') . '.xlsx';
        
        if ($download) {
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
        } else {
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }
        
        // Create minimal XLSX file
        $this->create_xlsx_file($catalog, $items);
        exit;
    }
    
    /**
     * Create real XLSX file (minimal implementation)
     */
    private function create_xlsx_file($catalog, $items) {
        // Create temporary directory
        $temp_dir = sys_get_temp_dir() . '/catalog_master_' . uniqid();
        mkdir($temp_dir);
        mkdir($temp_dir . '/_rels');
        mkdir($temp_dir . '/xl');
        mkdir($temp_dir . '/xl/_rels');
        mkdir($temp_dir . '/xl/worksheets');
        
        // Create [Content_Types].xml
        $content_types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>';
        file_put_contents($temp_dir . '/[Content_Types].xml', $content_types);
        
        // Create _rels/.rels
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
        file_put_contents($temp_dir . '/_rels/.rels', $rels);
        
        // Create xl/workbook.xml
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="' . esc_attr($catalog->name) . '" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>';
        file_put_contents($temp_dir . '/xl/workbook.xml', $workbook);
        
        // Create xl/_rels/workbook.xml.rels
        $workbook_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>';
        file_put_contents($temp_dir . '/xl/_rels/workbook.xml.rels', $workbook_rels);
        
        // Create worksheet with data
        $worksheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetData>';
        
        // Headers row
        $worksheet .= '<row r="1">';
        $headers = ['ID', 'Product ID', 'Product Name', 'Price', 'Quantity', 'Image URL', 'Sort Order', 'Description', 
                   'Category ID 1', 'Category ID 2', 'Category ID 3', 'Category Name 1', 'Category Name 2', 'Category Name 3',
                   'Category Image 1', 'Category Image 2', 'Category Image 3', 'Category Sort 1', 'Category Sort 2', 'Category Sort 3'];
        
        for ($i = 0; $i < count($headers); $i++) {
            $col = chr(65 + ($i % 26));
            if ($i >= 26) $col = chr(64 + intval($i / 26)) . $col;
            $worksheet .= '<c r="' . $col . '1" t="inlineStr"><is><t>' . htmlspecialchars($headers[$i]) . '</t></is></c>';
        }
        $worksheet .= '</row>';
        
        // Data rows
        $row_num = 2;
        foreach ($items as $item) {
            $worksheet .= '<row r="' . $row_num . '">';
            
            $data = [
                $item->id ?? '', $item->product_id ?? '', $item->product_name ?? '', $item->product_price ?? '',
                $item->product_qty ?? '', $item->product_image_url ?? '', $item->product_sort_order ?? '',
                $item->product_description ?? '', $item->category_id_1 ?? '', $item->category_id_2 ?? '',
                $item->category_id_3 ?? '', $item->category_name_1 ?? '', $item->category_name_2 ?? '',
                $item->category_name_3 ?? '', $item->category_image_1 ?? '', $item->category_image_2 ?? '',
                $item->category_image_3 ?? '', $item->category_sort_order_1 ?? '', $item->category_sort_order_2 ?? '',
                $item->category_sort_order_3 ?? ''
            ];
            
            for ($i = 0; $i < count($data); $i++) {
                $col = chr(65 + ($i % 26));
                if ($i >= 26) $col = chr(64 + intval($i / 26)) . $col;
                
                $value = htmlspecialchars($data[$i]);
                if (is_numeric($data[$i]) && $i < 4) { // ID, price, qty as numbers
                    $worksheet .= '<c r="' . $col . $row_num . '"><v>' . $value . '</v></c>';
                } else {
                    $worksheet .= '<c r="' . $col . $row_num . '" t="inlineStr"><is><t>' . $value . '</t></is></c>';
                }
            }
            
            $worksheet .= '</row>';
            $row_num++;
        }
        
        $worksheet .= '</sheetData></worksheet>';
        file_put_contents($temp_dir . '/xl/worksheets/sheet1.xml', $worksheet);
        
        // Create ZIP archive
        $zip = new ZipArchive();
        $zip_file = tempnam(sys_get_temp_dir(), 'catalog_master_xlsx');
        
        if ($zip->open($zip_file, ZipArchive::CREATE) === TRUE) {
            $this->add_directory_to_zip($zip, $temp_dir, strlen($temp_dir) + 1);
            $zip->close();
            
            // Output file
            readfile($zip_file);
            
            // Cleanup
            unlink($zip_file);
            $this->remove_directory($temp_dir);
        } else {
            // Fallback to CSV if ZIP creation fails
            $this->remove_directory($temp_dir);
            $csv_filename = sanitize_file_name($catalog->name) . '_' . date('Y-m-d') . '.csv';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $csv_filename . '"');
            $this->export_csv($catalog, $items, true);
        }
    }
    
    /**
     * Add directory to ZIP recursively
     */
    private function add_directory_to_zip($zip, $dir, $base_len) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($files as $file) {
            if (!$file->isDir()) {
                $file_path = $file->getRealPath();
                $relative_path = substr($file_path, $base_len);
                $zip->addFile($file_path, $relative_path);
            }
        }
    }
    
    /**
     * Remove directory recursively
     */
    private function remove_directory($dir) {
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), array('.', '..'));
            foreach ($files as $file) {
                (is_dir("$dir/$file")) ? $this->remove_directory("$dir/$file") : unlink("$dir/$file");
            }
            rmdir($dir);
        }
    }
    
    /**
     * Export to JSON (improved)
     */
    private function export_json($catalog, $items) {
        header('Content-Type: application/json; charset=utf-8');
        
        $data = array(
            'catalog' => array(
                'id' => intval($catalog->id),
                'name' => $catalog->name,
                'description' => $catalog->description,
                'exported_at' => current_time('c'),
                'items_count' => count($items)
            ),
            'items' => array()
        );
        
        foreach ($items as $item) {
            $categories = [];
            
            // Only add non-empty categories
            for ($i = 1; $i <= 3; $i++) {
                $cat_id = $item->{"category_id_$i"} ?? '';
                $cat_name = $item->{"category_name_$i"} ?? '';
                $cat_image = $item->{"category_image_$i"} ?? '';
                $cat_sort = $item->{"category_sort_order_$i"} ?? 0;
                
                if (!empty($cat_id) || !empty($cat_name)) {
                    $categories[] = array(
                        'level' => $i,
                        'id' => $cat_id,
                        'name' => $cat_name,
                        'image' => $cat_image,
                        'sort_order' => intval($cat_sort)
                    );
                }
            }
            
            $data['items'][] = array(
                'id' => intval($item->id ?? 0),
                'product_id' => $item->product_id ?? '',
                'product_name' => $item->product_name ?? '',
                'product_price' => floatval($item->product_price ?? 0),
                'product_qty' => intval($item->product_qty ?? 0),
                'product_image_url' => $item->product_image_url ?? '',
                'product_sort_order' => intval($item->product_sort_order ?? 0),
                'product_description' => $item->product_description ?? '',
                'categories' => $categories
            );
        }
        
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    /**
     * Export to XML (improved)
     */
    private function export_xml($catalog, $items) {
        header('Content-Type: text/xml; charset=utf-8');
        
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<catalog>';
        echo '<info>';
        echo '<id>' . intval($catalog->id) . '</id>';
        echo '<name><![CDATA[' . ($catalog->name ?? '') . ']]></name>';
        echo '<description><![CDATA[' . ($catalog->description ?? '') . ']]></description>';
        echo '<exported_at>' . current_time('c') . '</exported_at>';
        echo '<items_count>' . count($items) . '</items_count>';
        echo '</info>';
        echo '<items>';
        
        foreach ($items as $item) {
            echo '<item>';
            echo '<id>' . intval($item->id ?? 0) . '</id>';
            echo '<product_id><![CDATA[' . ($item->product_id ?? '') . ']]></product_id>';
            echo '<product_name><![CDATA[' . ($item->product_name ?? '') . ']]></product_name>';
            echo '<product_price>' . floatval($item->product_price ?? 0) . '</product_price>';
            echo '<product_qty>' . intval($item->product_qty ?? 0) . '</product_qty>';
            echo '<product_image_url><![CDATA[' . ($item->product_image_url ?? '') . ']]></product_image_url>';
            echo '<product_sort_order>' . intval($item->product_sort_order ?? 0) . '</product_sort_order>';
            echo '<product_description><![CDATA[' . ($item->product_description ?? '') . ']]></product_description>';
            echo '<categories>';
            
            for ($i = 1; $i <= 3; $i++) {
                $cat_id = $item->{"category_id_$i"} ?? '';
                $cat_name = $item->{"category_name_$i"} ?? '';
                $cat_image = $item->{"category_image_$i"} ?? '';
                $cat_sort = $item->{"category_sort_order_$i"} ?? 0;
                
                // Only output non-empty categories
                if (!empty($cat_id) || !empty($cat_name)) {
                    echo '<category level="' . $i . '">';
                    echo '<id><![CDATA[' . $cat_id . ']]></id>';
                    echo '<name><![CDATA[' . $cat_name . ']]></name>';
                    echo '<image><![CDATA[' . $cat_image . ']]></image>';
                    echo '<sort_order>' . intval($cat_sort) . '</sort_order>';
                    echo '</category>';
                }
            }
            
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

// Remove automatic initialization - will be done in main plugin 