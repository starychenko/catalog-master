<?php
/**
 * Admin interface class
 * 
 * @package CatalogMaster
 * @version 1.0.0
 */

// Suppress IDE warnings for WordPress functions
/** @noinspection PhpUndefinedFunctionInspection */

if (!defined('ABSPATH')) {
    exit;
}

class CatalogMaster_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'handle_form_submissions'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Catalog Master',
            'Catalog Master',
            'manage_options',
            'catalog-master',
            array($this, 'admin_page_catalogs'),
            'dashicons-grid-view',
            30
        );
        
        add_submenu_page(
            'catalog-master',
            'Всі каталоги',
            'Всі каталоги',
            'manage_options',
            'catalog-master',
            array($this, 'admin_page_catalogs')
        );
        
        add_submenu_page(
            'catalog-master',
            'Додати каталог',
            'Додати каталог',
            'manage_options',
            'catalog-master-add',
            array($this, 'admin_page_add_catalog')
        );
        
        add_submenu_page(
            null, // Hidden from menu
            'Редагувати каталог',
            'Редагувати каталог',
            'manage_options',
            'catalog-master-edit',
            array($this, 'admin_page_edit_catalog')
        );
        
        // Add debug/logs page (only visible when debug is enabled)
        if (CatalogMaster_Logger::is_debug_enabled()) {
            add_submenu_page(
                'catalog-master',
                'Логи та дебаг',
                'Логи та дебаг',
                'manage_options',
                'catalog-master-debug',
                array($this, 'admin_page_debug')
            );
        }
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'catalog-master') !== false) {
            // Vite integration
            $vite_dev_server_url = 'http://localhost:5173'; // Default Vite dev server URL
            $vite_base_path_in_dev_server = '/assets/src/'; // Path to source files within Vite's server
            $vite_hot_file = CATALOG_MASTER_PLUGIN_PATH . 'hot'; // File indicating Vite dev server is running
            $vite_manifest_path = CATALOG_MASTER_PLUGIN_PATH . 'assets/dist/.vite/manifest.json'; // Production manifest

            CatalogMaster_Logger::debug('Vite integration check', array(
                'hot_file_exists' => file_exists($vite_hot_file),
                'manifest_exists' => file_exists($vite_manifest_path)
            ));

            if (file_exists($vite_hot_file)) {
                // Development mode: Load from Vite dev server
                wp_enqueue_script('vite-client', $vite_dev_server_url . '/@vite/client', array(), null, array('strategy' => 'defer', 'in_footer' => true));
                wp_enqueue_script(
                    'catalog-master-vite-main-js',
                    $vite_dev_server_url . $vite_base_path_in_dev_server . 'main.js',
                    array(),
                    null,
                    array('strategy' => 'defer', 'in_footer' => true)
                );
                
                CatalogMaster_Logger::info('Vite development mode: Assets loaded from dev server');
            } else {
                // Production mode: Load from built assets
                if (file_exists($vite_manifest_path)) {
                    $manifest = json_decode(file_get_contents($vite_manifest_path), true);
                    CatalogMaster_Logger::debug('Vite manifest loaded', $manifest);
                    
                    if (isset($manifest['assets/src/main.js'])) {
                        $main_entry = $manifest['assets/src/main.js'];
                        
                        // Enqueue main JS (clean filename without hash)
                        wp_enqueue_script(
                            'catalog-master-vite-main-js',
                            CATALOG_MASTER_PLUGIN_URL . 'assets/dist/' . $main_entry['file'],
                            array(),
                            CATALOG_MASTER_VERSION,
                            array('strategy' => 'defer', 'in_footer' => true)
                        );
                        
                        // Enqueue main CSS if exists (clean filename without hash)
                        if (isset($main_entry['css'])) {
                            foreach ($main_entry['css'] as $css_file) {
                                wp_enqueue_style(
                                    'catalog-master-vite-main-css',
                                    CATALOG_MASTER_PLUGIN_URL . 'assets/dist/' . $css_file,
                                    array(),
                                    CATALOG_MASTER_VERSION
                                );
                            }
                        }
                        
                        CatalogMaster_Logger::info('Vite production mode: Assets loaded from manifest (clean filenames)');
                    } else {
                        CatalogMaster_Logger::error('Vite manifest entry not found for assets/src/main.js');
                    }
                } else {
                    CatalogMaster_Logger::error('Vite assets not found. Please run: npm run build');
                    wp_die('Catalog Master: Assets not built. Please contact administrator.');
                }
            }

            // Localize data for Vite version
            $localize_data = array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('catalog_master_nonce'),
                'plugin_url' => CATALOG_MASTER_PLUGIN_URL,
            );
            wp_localize_script('catalog-master-vite-main-js', 'catalog_master_vite_params', $localize_data);
            
            // Ensure Vite enqueued scripts are treated as modules
            add_filter('script_loader_tag', array($this, 'add_type_attribute_to_vite_scripts'), 10, 3);
        }
    }
    
    /**
     * Add type="module" attribute to Vite scripts
     */
    public function add_type_attribute_to_vite_scripts($tag, $handle, $src) {
        if ('catalog-master-vite-main-js' === $handle || 'vite-client' === $handle) {
            // For vite-client, defer might be beneficial. For main.js, it depends on execution timing needs.
            // If main.js needs to run after DOM is ready, defer is fine.
            // If it needs to run earlier or has inline dependencies, defer might not be suitable.
            // WordPress default for 'in_footer' true is effectively defer.
            return '<script type="module" src="' . esc_url($src) . '" id="' . esc_attr($handle) . '-js"'. ($handle === 'vite-client' ? ' defer' : '') .'></script>';
        }
        return $tag;
    }
    
    public function handle_form_submissions() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Handle catalog creation
        if (isset($_POST['action']) && $_POST['action'] === 'create_catalog' && wp_verify_nonce($_POST['_wpnonce'], 'catalog_master_create')) {
            $this->handle_create_catalog();
        }
        
        // Handle catalog update
        if (isset($_POST['action']) && $_POST['action'] === 'update_catalog' && wp_verify_nonce($_POST['_wpnonce'], 'catalog_master_update')) {
            $this->handle_update_catalog();
        }
        
        // Handle catalog deletion
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && wp_verify_nonce($_GET['_wpnonce'], 'catalog_master_delete')) {
            $this->handle_delete_catalog();
        }
    }
    
    private function handle_create_catalog() {
        CatalogMaster_Logger::debug('Starting catalog creation process');
        
        // Log received data
        $post_data = array(
            'name' => $_POST['name'] ?? '',
            'description' => $_POST['description'] ?? '',
            'google_sheet_url' => $_POST['google_sheet_url'] ?? '',
            'sheet_name' => $_POST['sheet_name'] ?? ''
        );
        
        CatalogMaster_Logger::debug('Received POST data', $post_data);
        
        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'description' => sanitize_textarea_field($_POST['description']),
            'google_sheet_url' => esc_url_raw($_POST['google_sheet_url']),
            'sheet_name' => sanitize_text_field($_POST['sheet_name'])
        );
        
        CatalogMaster_Logger::debug('Sanitized data', $data);
        
        $catalog_id = CatalogMaster_Database::create_catalog($data);
        
        if ($catalog_id) {
            CatalogMaster_Logger::info('Catalog creation successful, redirecting to edit page with ID: ' . $catalog_id);
            wp_redirect(admin_url('admin.php?page=catalog-master-edit&id=' . $catalog_id . '&created=1'));
            exit;
        } else {
            CatalogMaster_Logger::error('Catalog creation failed - create_catalog returned false/0');
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Помилка при створенні каталогу. Перевірте логи для деталей.</p></div>';
            });
        }
    }
    
    private function handle_update_catalog() {
        $id = intval($_POST['catalog_id']);
        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'description' => sanitize_textarea_field($_POST['description']),
            'google_sheet_url' => esc_url_raw($_POST['google_sheet_url']),
            'sheet_name' => sanitize_text_field($_POST['sheet_name'])
        );
        
        $result = CatalogMaster_Database::update_catalog($id, $data);
        
        if ($result !== false) {
            wp_redirect(admin_url('admin.php?page=catalog-master-edit&id=' . $id . '&updated=1'));
            exit;
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Помилка при оновленні каталогу.</p></div>';
            });
        }
    }
    
    private function handle_delete_catalog() {
        $id = intval($_GET['id']);
        $result = CatalogMaster_Database::delete_catalog($id);
        
        if ($result) {
            wp_redirect(admin_url('admin.php?page=catalog-master&deleted=1'));
            exit;
        } else {
            wp_redirect(admin_url('admin.php?page=catalog-master&error=1'));
            exit;
        }
    }
    
    public function admin_page_catalogs() {
        // Handle messages
        if (isset($_GET['deleted'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Каталог успішно видалено.</p></div>';
        }
        if (isset($_GET['error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>Виникла помилка.</p></div>';
        }
        
        $catalogs = CatalogMaster_Database::get_catalogs();
        ?>
        <div class="wrap">
            <h1>Каталоги <a href="<?php echo admin_url('admin.php?page=catalog-master-add'); ?>" class="page-title-action">Додати новий</a></h1>
            
            <?php if (empty($catalogs)): ?>
                <div class="notice notice-info">
                    <p>У вас поки що немає каталогів. <a href="<?php echo admin_url('admin.php?page=catalog-master-add'); ?>">Створити перший каталог</a></p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Назва</th>
                            <th>Опис</th>
                            <th>Google Sheets URL</th>
                            <th>Аркуш</th>
                            <th>Створено</th>
                            <th>Дії</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($catalogs as $catalog): ?>
                            <tr>
                                <td><strong><?php echo esc_html($catalog->name); ?></strong></td>
                                <td><?php echo esc_html(wp_trim_words($catalog->description, 10)); ?></td>
                                <td>
                                    <?php if ($catalog->google_sheet_url): ?>
                                        <a href="<?php echo esc_url($catalog->google_sheet_url); ?>" target="_blank">Переглянути</a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($catalog->sheet_name); ?></td>
                                <td><?php echo date_i18n('d.m.Y H:i', strtotime($catalog->created_at)); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=catalog-master-edit&id=' . $catalog->id); ?>" class="button button-small">Редагувати</a>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=catalog-master&action=delete&id=' . $catalog->id), 'catalog_master_delete'); ?>" 
                                       class="button button-small button-link-delete" 
                                       onclick="return confirm('Ви впевнені, що хочете видалити цей каталог?')">Видалити</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function admin_page_add_catalog() {
        ?>
        <div class="wrap">
            <h1>Додати новий каталог</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('catalog_master_create'); ?>
                <input type="hidden" name="action" value="create_catalog">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="name">Назва каталогу *</label></th>
                        <td>
                            <input type="text" id="name" name="name" class="regular-text" required>
                            <p class="description">Введіть назву каталогу</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="description">Опис</label></th>
                        <td>
                            <textarea id="description" name="description" rows="4" class="large-text"></textarea>
                            <p class="description">Опис каталогу (не обов'язково)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="google_sheet_url">URL Google Sheets</label></th>
                        <td>
                            <input type="url" id="google_sheet_url" name="google_sheet_url" class="large-text">
                            <p class="description">Посилання на Google Sheets таблицю</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sheet_name">Назва аркуша</label></th>
                        <td>
                            <input type="text" id="sheet_name" name="sheet_name" class="regular-text" value="Sheet1">
                            <p class="description">Назва аркуша в Google Sheets (за замовчуванням: Sheet1)</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Створити каталог'); ?>
            </form>
        </div>
        <?php
    }
    
    public function admin_page_edit_catalog() {
        $catalog_id = intval($_GET['id']);
        $catalog = CatalogMaster_Database::get_catalog($catalog_id);
        
        if (!$catalog) {
            wp_die('Каталог не знайдено');
        }
        
        // Handle messages
        if (isset($_GET['created'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Каталог успішно створено!</p></div>';
        }
        if (isset($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Каталог успішно оновлено!</p></div>';
        }
        
        $mappings = CatalogMaster_Database::get_column_mapping($catalog_id);
        $items_count = CatalogMaster_Database::get_catalog_items_count($catalog_id);
        ?>
        <div class="wrap catalog-master-admin">
            <h1><?php echo esc_html($catalog->name); ?> <small>(ID: <?php echo $catalog->id; ?>)</small></h1>
            
            <div class="catalog-master-tabs">
                <ul class="catalog-master-tab-nav">
                    <li><a href="#tab-settings" class="active">Налаштування</a></li>
                    <li><a href="#tab-mapping">Відповідність стовпців</a></li>
                    <li><a href="#tab-import">Імпорт даних</a></li>
                    <li><a href="#tab-data">Перегляд даних (<?php echo $items_count; ?>)</a></li>
                    <li><a href="#tab-export">Експорт</a></li>
                </ul>
            </div>
            
            <!-- Settings Tab -->
            <div id="tab-settings" class="catalog-master-tab-content active">
                <div class="catalog-master-card">
                    <h3>Основні налаштування</h3>
                    <form method="post" action="">
                        <?php wp_nonce_field('catalog_master_update'); ?>
                        <input type="hidden" name="action" value="update_catalog">
                        <input type="hidden" name="catalog_id" value="<?php echo $catalog->id; ?>">
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="name">Назва каталогу *</label></th>
                                <td>
                                    <input type="text" id="name" name="name" class="regular-text" value="<?php echo esc_attr($catalog->name); ?>" required>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="description">Опис</label></th>
                                <td>
                                    <textarea id="description" name="description" rows="4" class="large-text"><?php echo esc_textarea($catalog->description); ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="google_sheet_url">URL Google Sheets</label></th>
                                <td>
                                    <input type="url" id="google_sheet_url" name="google_sheet_url" class="large-text" value="<?php echo esc_attr($catalog->google_sheet_url); ?>">
                                    <button type="button" id="test-sheets-connection" class="button button-secondary" style="margin-left: 10px;">Перевірити підключення</button>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="sheet_name">Назва аркуша</label></th>
                                <td>
                                    <input type="text" id="sheet_name" name="sheet_name" class="regular-text" value="<?php echo esc_attr($catalog->sheet_name); ?>">
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button('Оновити каталог'); ?>
                    </form>
                </div>
            </div>
            
            <!-- Column Mapping Tab -->
            <div id="tab-mapping" class="catalog-master-tab-content">
                <div class="catalog-master-card">
                    <h3>Відповідність стовпців</h3>
                    <p>Налаштуйте відповідність між стовпцями Google Sheets та полями каталогу.</p>
                    
                    <?php 
                    $catalog_columns = array(
                        'product_id' => 'Product ID',
                        'product_name' => 'Product Name', 
                        'product_price' => 'Product Price',
                        'product_qty' => 'Product Quantity',
                        'product_image_url' => 'Product Image',
                        'product_sort_order' => 'Product Sort',
                        'product_description' => 'Product Description',
                        'category_id_1' => 'Category ID 1',
                        'category_id_2' => 'Category ID 2', 
                        'category_id_3' => 'Category ID 3',
                        'category_name_1' => 'Category Name 1',
                        'category_name_2' => 'Category Name 2',
                        'category_name_3' => 'Category Name 3',
                        'category_image_1' => 'Category Image 1',
                        'category_image_2' => 'Category Image 2',
                        'category_image_3' => 'Category Image 3',
                        'category_sort_order_1' => 'Category Sort 1',
                        'category_sort_order_2' => 'Category Sort 2',
                        'category_sort_order_3' => 'Category Sort 3'
                    );
                    
                    $mapped_catalog_columns = array();
                    if (!empty($mappings)) {
                        foreach ($mappings as $mapping) {
                            $mapped_catalog_columns[] = $mapping->catalog_column;
                        }
                    }
                    ?>
                    
                    <!-- Column Status Visualization -->
                    <div class="column-status-legend">
                        <div class="column-status-legend-item">
                            <div class="column-status-legend-color mapped"></div>
                            <span>Налаштовано</span>
                        </div>
                        <div class="column-status-legend-item">
                            <div class="column-status-legend-color unmapped"></div>
                            <span>Не налаштовано</span>
                        </div>
                        <div class="column-status-legend-item">
                            <div class="column-status-legend-color available"></div>
                            <span>Доступно</span>
                        </div>
                    </div>
                    
                    <div class="column-status-container column-status-compact">
                        <div class="column-status-section">
                            <h4>📊 Google Sheets</h4>
                            <div class="column-status-summary">
                                <span>Всього: <span class="count" id="google-total-count">0</span></span>
                            </div>
                            <div id="google-columns-status" class="column-status-grid">
                                <div class="column-status-item available">Завантажте заголовки</div>
                            </div>
                        </div>
                        
                        <div class="column-status-section">
                            <h4>🗂️ Поля каталогу</h4>
                            <div class="column-status-summary">
                                <span>Налаштовано: <span class="count mapped-count" id="catalog-mapped-count"><?php echo count($mapped_catalog_columns); ?></span></span>
                                <span>Всього: <span class="count" id="catalog-total-count"><?php echo count($catalog_columns); ?></span></span>
                            </div>
                            <div id="catalog-columns-status" class="column-status-grid">
                                <?php 
                                foreach ($catalog_columns as $column_key => $column_label): 
                                    $status_class = in_array($column_key, $mapped_catalog_columns) ? 'mapped' : 'unmapped';
                                ?>
                                    <div class="column-status-item <?php echo $status_class; ?>" data-column="<?php echo $column_key; ?>" title="<?php echo $column_label; ?>">
                                        <?php echo $column_label; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <button type="button" id="get-sheets-headers" class="button button-secondary" <?php echo empty($catalog->google_sheet_url) ? 'disabled' : ''; ?>>
                            Завантажити заголовки з Google Sheets
                        </button>
                    </div>
                    
                    <!-- Traditional Mapping Configuration -->
                    <h4>Детальні налаштування відповідності</h4>
                    <div class="column-mapping-container">
                        <div class="column-mapping-header">
                            <div>Стовпець Google Sheets</div>
                            <div>Поле каталогу</div>
                            <div>Дії</div>
                        </div>
                        <div id="column-mapping-rows">
                            <?php if (!empty($mappings)): ?>
                                <?php foreach ($mappings as $index => $mapping): ?>
                                    <div class="column-mapping-row">
                                        <select class="column-mapping-select google-column" name="mappings[<?php echo $index; ?>][google_column]">
                                            <option value="">-- Оберіть стовпець --</option>
                                            <option value="<?php echo esc_attr($mapping->google_column); ?>" selected><?php echo esc_html($mapping->google_column); ?></option>
                                        </select>
                                        <select class="column-mapping-select catalog-column" name="mappings[<?php echo $index; ?>][catalog_column]">
                                            <option value="">-- Оберіть поле --</option>
                                            <?php 
                                            foreach ($catalog_columns as $value => $label): 
                                                $selected = $value === $mapping->catalog_column ? 'selected' : '';
                                            ?>
                                                <option value="<?php echo $value; ?>" <?php echo $selected; ?>><?php echo $label; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" class="remove-mapping-btn">Видалити</button>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div style="margin-top: 20px;">
                        <button type="button" id="add-mapping-row" class="button button-secondary">Додати відповідність</button>
                        <button type="button" id="save-column-mapping" class="button button-primary" data-catalog-id="<?php echo $catalog->id; ?>" <?php echo empty($mappings) ? 'disabled' : ''; ?>>
                            Зберегти відповідність
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Import Tab -->
            <div id="tab-import" class="catalog-master-tab-content">
                <div class="catalog-master-card">
                    <h3>Імпорт даних з Google Sheets</h3>
                    <p>Імпортуйте дані з Google Sheets в каталог. Існуючі дані будуть замінені.</p>
                    
                    <!-- Import Content Container - JavaScript can update this entire container -->
                    <div id="import-content-container">
                        <?php if (empty($catalog->google_sheet_url)): ?>
                            <div class="catalog-master-status warning">
                                Спочатку вкажіть URL Google Sheets в налаштуваннях.
                            </div>
                        <?php elseif (empty($mappings)): ?>
                            <div class="catalog-master-status warning">
                                Спочатку налаштуйте відповідність стовпців.
                            </div>
                        <?php else: ?>
                            <div class="catalog-master-status info">
                                <strong>Google Sheets:</strong> <?php echo esc_html($catalog->google_sheet_url); ?><br>
                                <strong>Аркуш:</strong> <?php echo esc_html($catalog->sheet_name); ?><br>
                                <strong>Налаштовано відповідностей:</strong> <?php echo count($mappings); ?>
                            </div>
                            
                            <button type="button" id="import-data" class="button button-primary" data-catalog-id="<?php echo $catalog->id; ?>">
                                Імпортувати дані
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Data Tab -->
            <div id="tab-data" class="catalog-master-tab-content">
                <div class="catalog-master-card">
                    <h3>Дані каталогу</h3>
                    
                    <!-- Always create table element for DataTable initialization -->
                    <table id="catalog-items-table" class="display catalog-items-table" data-catalog-id="<?php echo $catalog->id; ?>">
                        <!-- DataTable will be initialized by JavaScript -->
                    </table>
                    
                    <?php if ($items_count === 0): ?>
                        <!-- Show info message only initially, DataTable will handle empty state -->
                        <div id="no-data-message" class="catalog-master-status info" style="margin-top: 20px;">
                            В каталозі поки немає даних. Імпортуйте дані з Google Sheets.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Export Tab -->
            <div id="tab-export" class="catalog-master-tab-content">
                <div class="catalog-master-card">
                    <h3>Експорт даних</h3>
                    
                    <?php if ($items_count > 0): ?>
                        <div class="export-options">
                            <div class="export-option">
                                <h4>CSV</h4>
                                <p>Експорт в форматі CSV для використання в Excel та інших програмах</p>
                                <button type="button" class="button button-primary export-btn" data-catalog-id="<?php echo $catalog->id; ?>" data-format="csv">
                                    Експортувати CSV
                                </button>
                            </div>
                            
                            <div class="export-option">
                                <h4>Excel</h4>
                                <p>Експорт в форматі Excel (.xls)</p>
                                <button type="button" class="button button-primary export-btn" data-catalog-id="<?php echo $catalog->id; ?>" data-format="excel">
                                    Експортувати Excel
                                </button>
                            </div>
                            
                            <div class="export-option">
                                <h4>JSON Feed</h4>
                                <p>JSON фід для використання в API та інших системах</p>
                                <button type="button" class="button button-primary export-btn" data-catalog-id="<?php echo $catalog->id; ?>" data-format="json">
                                    Створити JSON Feed
                                </button>
                            </div>
                            
                            <div class="export-option">
                                <h4>XML Feed</h4>
                                <p>XML фід для використання в різних системах</p>
                                <button type="button" class="button button-primary export-btn" data-catalog-id="<?php echo $catalog->id; ?>" data-format="xml">
                                    Створити XML Feed
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="catalog-master-status info">
                            В каталозі немає даних для експорту.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function admin_page_debug() {
        // Handle debug actions
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'enable_debug' && wp_verify_nonce($_POST['_wpnonce'], 'catalog_master_debug')) {
                CatalogMaster_Logger::enable_debug();
                echo '<div class="notice notice-success"><p>Режим дебагу увімкнено</p></div>';
            } elseif ($_POST['action'] === 'disable_debug' && wp_verify_nonce($_POST['_wpnonce'], 'catalog_master_debug')) {
                CatalogMaster_Logger::disable_debug();
                echo '<div class="notice notice-success"><p>Режим дебагу вимкнено</p></div>';
            } elseif ($_POST['action'] === 'clear_logs' && wp_verify_nonce($_POST['_wpnonce'], 'catalog_master_debug')) {
                CatalogMaster_Logger::clear_logs();
                echo '<div class="notice notice-success"><p>Логи очищено</p></div>';
            }
        }
        
        $debug_enabled = CatalogMaster_Logger::is_debug_enabled();
        $log_file = CatalogMaster_Logger::get_log_file();
        $recent_logs = CatalogMaster_Logger::get_recent_logs(100);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'catalog_master_catalogs';
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
        
        ?>
        <div class="wrap">
            <h1>Логи та дебаг Catalog Master</h1>
            
            <div class="catalog-master-card">
                <h3>Статус системи</h3>
                <table class="form-table">
                    <tr>
                        <th>Режим дебагу:</th>
                        <td>
                            <strong style="color: <?php echo $debug_enabled ? 'green' : 'red'; ?>">
                                <?php echo $debug_enabled ? 'Увімкнено' : 'Вимкнено'; ?>
                            </strong>
                            
                            <form method="post" style="display: inline; margin-left: 15px;">
                                <?php wp_nonce_field('catalog_master_debug'); ?>
                                <?php if ($debug_enabled): ?>
                                    <input type="hidden" name="action" value="disable_debug">
                                    <button type="submit" class="button button-secondary">Вимкнути дебаг</button>
                                <?php else: ?>
                                    <input type="hidden" name="action" value="enable_debug">
                                    <button type="submit" class="button button-primary">Увімкнути дебаг</button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                    <tr>
                        <th>WordPress Debug:</th>
                        <td>
                            <strong style="color: <?php echo (defined('WP_DEBUG') && WP_DEBUG) ? 'green' : 'red'; ?>">
                                <?php echo (defined('WP_DEBUG') && WP_DEBUG) ? 'Увімкнено' : 'Вимкнено'; ?>
                            </strong>
                        </td>
                    </tr>
                    <tr>
                        <th>Таблиця каталогів:</th>
                        <td>
                            <strong style="color: <?php echo $table_exists ? 'green' : 'red'; ?>">
                                <?php echo $table_exists ? 'Існує' : 'Не існує'; ?>
                            </strong>
                            <?php if ($table_exists): ?>
                                <code><?php echo $table_name; ?></code>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Файл логів:</th>
                        <td>
                            <code><?php echo $log_file; ?></code>
                            <?php if (file_exists($log_file)): ?>
                                <span style="color: green;">(існує, розмір: <?php echo human_readable_bytes(filesize($log_file)); ?>)</span>
                            <?php else: ?>
                                <span style="color: orange;">(не створений)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Права на запис:</th>
                        <td>
                            <?php 
                            $upload_dir = wp_upload_dir();
                            $writable = is_writable($upload_dir['basedir']);
                            ?>
                            <strong style="color: <?php echo $writable ? 'green' : 'red'; ?>">
                                <?php echo $writable ? 'Доступні' : 'Недоступні'; ?>
                            </strong>
                            <code><?php echo $upload_dir['basedir']; ?></code>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="catalog-master-card">
                <h3>Останні логи</h3>
                <div style="margin-bottom: 15px;">
                    <form method="post" style="display: inline;">
                        <?php wp_nonce_field('catalog_master_debug'); ?>
                        <input type="hidden" name="action" value="clear_logs">
                        <button type="submit" class="button button-secondary" onclick="return confirm('Ви впевнені, що хочете очистити всі логи?')">Очистити логи</button>
                    </form>
                    
                    <?php if (file_exists($log_file)): ?>
                        <a href="<?php echo wp_upload_dir()['baseurl'] . '/catalog-master-debug.log'; ?>" target="_blank" class="button button-secondary">Відкрити повний файл логів</a>
                    <?php endif; ?>
                </div>
                
                <div style="background: #f1f1f1; padding: 15px; border-radius: 4px; max-height: 500px; overflow-y: auto;">
                    <pre style="margin: 0; white-space: pre-wrap; font-family: monospace; font-size: 12px;"><?php echo esc_html($recent_logs); ?></pre>
                </div>
            </div>
            
            <div class="catalog-master-card">
                <h3>Тестування</h3>
                <p>Спробуйте створити каталог зараз, щоб побачити детальні логи процесу.</p>
                <a href="<?php echo admin_url('admin.php?page=catalog-master-add'); ?>" class="button button-primary">Створити тестовий каталог</a>
            </div>
            
            <div class="catalog-master-card">
                <h3>Конфігурація PHP</h3>
                <table class="form-table">
                    <tr>
                        <th>allow_url_fopen:</th>
                        <td>
                            <strong style="color: <?php echo ini_get('allow_url_fopen') ? 'green' : 'red'; ?>">
                                <?php echo ini_get('allow_url_fopen') ? 'Увімкнено' : 'Вимкнено'; ?>
                            </strong>
                            <?php if (!ini_get('allow_url_fopen')): ?>
                                <span style="color: red;"> - Потрібно для Google Sheets!</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>file_uploads:</th>
                        <td>
                            <strong style="color: <?php echo ini_get('file_uploads') ? 'green' : 'red'; ?>">
                                <?php echo ini_get('file_uploads') ? 'Увімкнено' : 'Вимкнено'; ?>
                            </strong>
                        </td>
                    </tr>
                    <tr>
                        <th>max_execution_time:</th>
                        <td>
                            <?php 
                            $max_time = ini_get('max_execution_time');
                            $color = $max_time >= 300 ? 'green' : ($max_time >= 120 ? 'orange' : 'red');
                            ?>
                            <strong style="color: <?php echo $color; ?>">
                                <?php echo $max_time; ?> секунд
                            </strong>
                            <?php if ($max_time < 300): ?>
                                <span style="color: orange;"> - Рекомендується мінімум 300 сек</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>memory_limit:</th>
                        <td>
                            <?php 
                            $memory_limit = ini_get('memory_limit');
                            $memory_bytes = $this->parse_size($memory_limit);
                            $recommended_bytes = 512 * 1024 * 1024; // 512MB
                            $color = $memory_bytes >= $recommended_bytes ? 'green' : 'orange';
                            ?>
                            <strong style="color: <?php echo $color; ?>">
                                <?php echo $memory_limit; ?>
                            </strong>
                            <?php if ($memory_bytes < $recommended_bytes): ?>
                                <span style="color: orange;"> - Рекомендується мінімум 512M</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>upload_max_filesize:</th>
                        <td>
                            <strong><?php echo ini_get('upload_max_filesize'); ?></strong>
                        </td>
                    </tr>
                    <tr>
                        <th>post_max_size:</th>
                        <td>
                            <strong><?php echo ini_get('post_max_size'); ?></strong>
                        </td>
                    </tr>
                    <tr>
                        <th>Функції curl:</th>
                        <td>
                            <strong style="color: <?php echo function_exists('curl_init') ? 'green' : 'red'; ?>">
                                <?php echo function_exists('curl_init') ? 'Доступні' : 'Недоступні'; ?>
                            </strong>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <script>
        // Auto-refresh logs every 30 seconds when on debug page
        setTimeout(function() {
            if (window.location.href.includes('catalog-master-debug')) {
                window.location.reload();
            }
        }, 30000);
        </script>
        <?php
    }
    
    /**
     * Parse size string (like "512M") to bytes
     */
    private function parse_size($size) {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);
        
        if ($unit) {
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        } else {
            return round($size);
        }
    }
}

// Helper function for file size formatting
if (!function_exists('human_readable_bytes')) {
    function human_readable_bytes($size, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }
} 