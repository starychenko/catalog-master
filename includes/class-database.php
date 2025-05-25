<?php
/**
 * Database management class
 */

if (!defined('ABSPATH')) {
    exit;
}

class CatalogMaster_Database {
    
    public static function create_tables() {
        global $wpdb;
        
        // Log the start of table creation
        if (class_exists('CatalogMaster_Logger')) {
            CatalogMaster_Logger::info('Starting database table creation');
        }
        
        $charset_collate = $wpdb->get_charset_collate();
        $tables_created = 0;
        $errors = [];
        
        // Table for catalogs
        $table_catalogs = $wpdb->prefix . 'catalog_master_catalogs';
        $sql_catalogs = "CREATE TABLE $table_catalogs (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            google_sheet_url varchar(500),
            sheet_name varchar(255) DEFAULT 'Sheet1',
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Table for column mapping
        $table_mapping = $wpdb->prefix . 'catalog_master_columns_mapping';
        $sql_mapping = "CREATE TABLE $table_mapping (
            id int(11) NOT NULL AUTO_INCREMENT,
            catalog_id int(11) NOT NULL,
            google_column varchar(255) NOT NULL,
            catalog_column varchar(255) NOT NULL,
            column_order int(11) DEFAULT 0,
            PRIMARY KEY (id),
            KEY catalog_id (catalog_id)
        ) $charset_collate;";
        
        // Table for catalog items
        $table_items = $wpdb->prefix . 'catalog_master_items';
        $sql_items = "CREATE TABLE $table_items (
            id int(11) NOT NULL AUTO_INCREMENT,
            catalog_id int(11) NOT NULL,
            product_id varchar(255),
            product_name varchar(500),
            product_price decimal(10,2),
            product_qty int(11),
            product_image_url varchar(500),
            product_sort_order int(11) DEFAULT 0,
            product_description text,
            category_id_1 varchar(255),
            category_id_2 varchar(255),
            category_id_3 varchar(255),
            category_name_1 varchar(255),
            category_name_2 varchar(255),
            category_name_3 varchar(255),
            category_image_1 varchar(500),
            category_image_2 varchar(500),
            category_image_3 varchar(500),
            category_sort_order_1 int(11) DEFAULT 0,
            category_sort_order_2 int(11) DEFAULT 0,
            category_sort_order_3 int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY catalog_id (catalog_id),
            KEY product_id (product_id)
        ) $charset_collate;";
        
        // Include WordPress upgrade functions
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Create tables and check results
        $tables_to_create = [
            'catalogs' => [$table_catalogs, $sql_catalogs],
            'mapping' => [$table_mapping, $sql_mapping], 
            'items' => [$table_items, $sql_items]
        ];
        
        foreach ($tables_to_create as $type => $table_info) {
            $table_name = $table_info[0];
            $sql = $table_info[1];
            
            // Check if table already exists
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
            
            if (!$table_exists) {
                if (class_exists('CatalogMaster_Logger')) {
                    CatalogMaster_Logger::debug("Creating table: {$table_name}");
                }
                
                $result = dbDelta($sql);
                
                // Verify table was created
                $table_exists_after = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
                
                if ($table_exists_after) {
                    $tables_created++;
                    if (class_exists('CatalogMaster_Logger')) {
                        CatalogMaster_Logger::info("Successfully created table: {$table_name}");
                    }
                } else {
                    $error_msg = "Failed to create table: {$table_name}";
                    if ($wpdb->last_error) {
                        $error_msg .= " - Error: " . $wpdb->last_error;
                    }
                    $errors[] = $error_msg;
                    if (class_exists('CatalogMaster_Logger')) {
                        CatalogMaster_Logger::error($error_msg);
                    }
                }
            } else {
                if (class_exists('CatalogMaster_Logger')) {
                    CatalogMaster_Logger::info("Table already exists: {$table_name}");
                }
                $tables_created++;
            }
        }
        
        // Create upload directory for images
        $upload_dir = wp_upload_dir();
        $catalog_images_dir = $upload_dir['basedir'] . '/catalog-master-images';
        if (!file_exists($catalog_images_dir)) {
            if (wp_mkdir_p($catalog_images_dir)) {
                if (class_exists('CatalogMaster_Logger')) {
                    CatalogMaster_Logger::info("Created images directory: {$catalog_images_dir}");
                }
            } else {
                $error_msg = "Failed to create images directory: {$catalog_images_dir}";
                $errors[] = $error_msg;
                if (class_exists('CatalogMaster_Logger')) {
                    CatalogMaster_Logger::error($error_msg);
                }
            }
        }
        
        // Add .htaccess to protect direct access
        $htaccess_file = $catalog_images_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "Options -Indexes\nDeny from all\n<Files ~ \"\\.(jpg|jpeg|png|gif|webp)$\">\n    Allow from all\n</Files>";
            if (file_put_contents($htaccess_file, $htaccess_content)) {
                if (class_exists('CatalogMaster_Logger')) {
                    CatalogMaster_Logger::info("Created .htaccess security file");
                }
            } else {
                $error_msg = "Failed to create .htaccess security file";
                $errors[] = $error_msg;
                if (class_exists('CatalogMaster_Logger')) {
                    CatalogMaster_Logger::warning($error_msg);
                }
            }
        }
        
        // Final summary
        if (class_exists('CatalogMaster_Logger')) {
            if (empty($errors)) {
                CatalogMaster_Logger::info("Database setup completed successfully. Tables created/verified: {$tables_created}");
            } else {
                CatalogMaster_Logger::error("Database setup completed with errors", ['errors' => $errors, 'tables_created' => $tables_created]);
            }
        }
        
        // Return success/failure
        return empty($errors);
    }
    
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'catalog_master_items',
            $wpdb->prefix . 'catalog_master_columns_mapping',
            $wpdb->prefix . 'catalog_master_catalogs'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        // Remove upload directory
        $upload_dir = wp_upload_dir();
        $catalog_images_dir = $upload_dir['basedir'] . '/catalog-master-images';
        if (file_exists($catalog_images_dir)) {
            self::remove_directory($catalog_images_dir);
        }
    }
    
    private static function remove_directory($dir) {
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), array('.', '..'));
            foreach ($files as $file) {
                (is_dir("$dir/$file")) ? self::remove_directory("$dir/$file") : unlink("$dir/$file");
            }
            rmdir($dir);
        }
    }
    
    public static function get_catalogs() {
        global $wpdb;
        $table = $wpdb->prefix . 'catalog_master_catalogs';
        return $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
    }
    
    public static function get_catalog($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'catalog_master_catalogs';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }
    
    public static function create_catalog($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'catalog_master_catalogs';
        
        CatalogMaster_Logger::debug('Creating catalog with data', $data);
        
        // Validate required fields
        if (empty($data['name'])) {
            CatalogMaster_Logger::error('Cannot create catalog: name is required');
            return false;
        }
        
        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if (!$table_exists) {
            CatalogMaster_Logger::error('Table does not exist: ' . $table);
            return false;
        }
        
        $insert_data = array(
            'name' => sanitize_text_field($data['name']),
            'description' => sanitize_textarea_field($data['description']),
            'google_sheet_url' => esc_url_raw($data['google_sheet_url']),
            'sheet_name' => sanitize_text_field($data['sheet_name'])
        );
        
        CatalogMaster_Logger::debug('Inserting data into table: ' . $table, $insert_data);
        
        $result = $wpdb->insert($table, $insert_data);
        
        if ($result === false) {
            CatalogMaster_Logger::db_error(
                'create_catalog',
                'Failed to insert catalog',
                $wpdb->last_query
            );
            return false;
        }
        
        $insert_id = $wpdb->insert_id;
        
        if ($insert_id) {
            CatalogMaster_Logger::info('Catalog created successfully with ID: ' . $insert_id);
        } else {
            CatalogMaster_Logger::warning('Insert succeeded but no insert_id returned');
        }
        
        return $insert_id;
    }
    
    public static function update_catalog($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'catalog_master_catalogs';
        
        return $wpdb->update(
            $table,
            array(
                'name' => sanitize_text_field($data['name']),
                'description' => sanitize_textarea_field($data['description']),
                'google_sheet_url' => esc_url_raw($data['google_sheet_url']),
                'sheet_name' => sanitize_text_field($data['sheet_name'])
            ),
            array('id' => $id)
        );
    }
    
    public static function delete_catalog($id) {
        global $wpdb;
        
        // Delete items first
        $items_table = $wpdb->prefix . 'catalog_master_items';
        $wpdb->delete($items_table, array('catalog_id' => $id));
        
        // Delete mapping
        $mapping_table = $wpdb->prefix . 'catalog_master_columns_mapping';
        $wpdb->delete($mapping_table, array('catalog_id' => $id));
        
        // Delete catalog
        $catalog_table = $wpdb->prefix . 'catalog_master_catalogs';
        return $wpdb->delete($catalog_table, array('id' => $id));
    }
    
    public static function get_catalog_items($catalog_id, $limit = 0, $offset = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'catalog_master_items';
        
        $sql = $wpdb->prepare("SELECT * FROM $table WHERE catalog_id = %d ORDER BY product_sort_order ASC, id ASC", $catalog_id);
        
        if ($limit > 0) {
            $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $limit, $offset);
        }
        
        return $wpdb->get_results($sql);
    }
    
    public static function get_catalog_items_count($catalog_id, $search = '', $filters = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'catalog_master_items';
        
        $where_clause = "WHERE catalog_id = %d";
        $params = array($catalog_id);
        
        // Add search condition if provided
        if (!empty($search)) {
            $where_clause .= " AND (
                product_name LIKE %s OR 
                product_description LIKE %s OR 
                category_name_1 LIKE %s OR 
                category_name_2 LIKE %s OR 
                category_name_3 LIKE %s OR 
                product_id LIKE %s
            )";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        // Add advanced filters
        if (!empty($filters)) {
            $filter_conditions = self::build_filter_conditions($filters, $params);
            if (!empty($filter_conditions)) {
                $where_clause .= " AND ($filter_conditions)";
            }
        }
        
        $sql = "SELECT COUNT(*) FROM $table $where_clause";
        return $wpdb->get_var($wpdb->prepare($sql, $params));
    }
    
    /**
     * Get catalog items with modern pagination, sorting, search and filters
     */
    public static function get_catalog_items_modern($catalog_id, $limit = 25, $offset = 0, $search = '', $sort_column = 'product_id', $sort_direction = 'asc', $filters = array()) {
        global $wpdb;
        $table = $wpdb->prefix . 'catalog_master_items';
        
        // Validate sort direction
        $sort_direction = strtoupper($sort_direction) === 'DESC' ? 'DESC' : 'ASC';
        
        // Validate sort column (security)
        $allowed_sort_columns = array(
            'id', 'product_id', 'product_name', 'product_price', 'product_qty',
            'product_sort_order', 'category_name_1', 'category_name_2', 'category_name_3'
        );
        
        if (!in_array($sort_column, $allowed_sort_columns)) {
            $sort_column = 'product_id';
        }
        
        $where_clause = "WHERE catalog_id = %d";
        $params = array($catalog_id);
        
        // Add search condition if provided
        if (!empty($search)) {
            $where_clause .= " AND (
                product_name LIKE %s OR 
                product_description LIKE %s OR 
                category_name_1 LIKE %s OR 
                category_name_2 LIKE %s OR 
                category_name_3 LIKE %s OR 
                product_id LIKE %s
            )";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        // Add advanced filters
        if (!empty($filters)) {
            $filter_conditions = self::build_filter_conditions($filters, $params);
            if (!empty($filter_conditions)) {
                $where_clause .= " AND ($filter_conditions)";
            }
        }
        
        // Build the query
        $sql = "SELECT * FROM $table $where_clause ORDER BY $sort_column $sort_direction";
        
        if ($limit > 0) {
            $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $limit, $offset);
        }
        
        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }
    
    public static function get_column_mapping($catalog_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'catalog_master_columns_mapping';
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE catalog_id = %d ORDER BY column_order ASC", $catalog_id));
    }
    
    public static function save_column_mapping($catalog_id, $mappings) {
        global $wpdb;
        $table = $wpdb->prefix . 'catalog_master_columns_mapping';
        
        // Delete existing mappings
        $wpdb->delete($table, array('catalog_id' => $catalog_id));
        
        // Insert new mappings
        foreach ($mappings as $order => $mapping) {
            if (!empty($mapping['google_column']) && !empty($mapping['catalog_column'])) {
                $wpdb->insert(
                    $table,
                    array(
                        'catalog_id' => $catalog_id,
                        'google_column' => sanitize_text_field($mapping['google_column']),
                        'catalog_column' => sanitize_text_field($mapping['catalog_column']),
                        'column_order' => intval($order)
                    )
                );
            }
        }
    }
    
    public static function clear_catalog_items($catalog_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'catalog_master_items';
        return $wpdb->delete($table, array('catalog_id' => $catalog_id));
    }
    
    public static function insert_catalog_items($catalog_id, $items) {
        global $wpdb;
        $table = $wpdb->prefix . 'catalog_master_items';
        
        foreach ($items as $item) {
            $item['catalog_id'] = $catalog_id;
            $wpdb->insert($table, $item);
        }
    }
    
    /**
     * Build SQL filter conditions for advanced filters
     */
    private static function build_filter_conditions($filters, &$params) {
        global $wpdb;
        
        $conditions = array();
        
        foreach ($filters as $index => $filter) {
            $column = $filter['column'];
            $operator = $filter['operator'];
            $value = isset($filter['value']) ? $filter['value'] : '';
            $value2 = isset($filter['value2']) ? $filter['value2'] : '';
            $logic = isset($filter['logic']) && $index > 0 ? $filter['logic'] : '';
            
            $condition = '';
            
            switch ($operator) {
                case 'eq':
                    $condition = "$column = %s";
                    $params[] = $value;
                    break;
                    
                case 'neq':
                    $condition = "$column != %s";
                    $params[] = $value;
                    break;
                    
                case 'gt':
                    $condition = "$column > %s";
                    $params[] = $value;
                    break;
                    
                case 'gte':
                    $condition = "$column >= %s";
                    $params[] = $value;
                    break;
                    
                case 'lt':
                    $condition = "$column < %s";
                    $params[] = $value;
                    break;
                    
                case 'lte':
                    $condition = "$column <= %s";
                    $params[] = $value;
                    break;
                    
                case 'between':
                    $condition = "$column BETWEEN %s AND %s";
                    $params[] = $value;
                    $params[] = $value2;
                    break;
                    
                case 'is_null':
                    $condition = "($column IS NULL OR $column = '')";
                    break;
                    
                case 'is_not_null':
                    $condition = "($column IS NOT NULL AND $column != '')";
                    break;
                    
                case 'contains':
                    $condition = "$column LIKE %s";
                    $params[] = '%' . $wpdb->esc_like($value) . '%';
                    break;
                    
                case 'not_contains':
                    $condition = "$column NOT LIKE %s";
                    $params[] = '%' . $wpdb->esc_like($value) . '%';
                    break;
            }
            
            if (!empty($condition)) {
                // Add logic operator for subsequent conditions
                if ($index > 0 && !empty($logic)) {
                    $conditions[] = $logic . ' ' . $condition;
                } else {
                    $conditions[] = $condition;
                }
            }
        }
        
        return implode(' ', $conditions);
    }
} 