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
        
        // AJAX handlers
        add_action('wp_ajax_catalog_master_cleanup_test_image', array($this, 'ajax_cleanup_test_image'));
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
        <div class="wrap catalog-master-admin">
            <div class="add-catalog-header">
                <h1>🆕 Створення нового каталогу</h1>
                <p class="add-catalog-subtitle">Створіть новий каталог для управління товарами з Google Sheets</p>
            </div>
            
            <!-- Progress Steps -->
            <div class="creation-progress">
                <div class="progress-step active">
                    <div class="step-number">1</div>
                    <div class="step-info">
                        <div class="step-title">Основна інформація</div>
                        <div class="step-description">Назва та опис каталогу</div>
                    </div>
                </div>
                <div class="progress-separator"></div>
                <div class="progress-step">
                    <div class="step-number">2</div>
                    <div class="step-info">
                        <div class="step-title">Підключення даних</div>
                        <div class="step-description">Google Sheets та налаштування</div>
                    </div>
                </div>
                <div class="progress-separator"></div>
                <div class="progress-step">
                    <div class="step-number">3</div>
                    <div class="step-info">
                        <div class="step-title">Готово</div>
                        <div class="step-description">Каталог створено</div>
                    </div>
                </div>
            </div>

            <form method="post" action="" id="create-catalog-form" class="create-catalog-form">
                <?php wp_nonce_field('catalog_master_create'); ?>
                <input type="hidden" name="action" value="create_catalog">
                
                <!-- Basic Information Section -->
                <div class="settings-section">
                    <div class="settings-section-header">
                        <h3>📝 Основна інформація</h3>
                        <p class="settings-section-description">Введіть назву та опис вашого каталогу для зручної ідентифікації</p>
                    </div>
                    
                    <div class="settings-fields-grid">
                        <div class="settings-field-group">
                            <label for="name" class="settings-field-label">
                                Назва каталогу <span class="label-required">*</span>
                            </label>
                            <div class="settings-field-wrapper">
                                <input type="text" 
                                       id="name" 
                                       name="name" 
                                       class="settings-field-input" 
                                       required
                                       placeholder="Наприклад: Каталог продуктів 2025"
                                       autocomplete="off">
                                <div class="field-hint">
                                    💡 Використовуйте зрозумілу назву, яка допоможе відрізнити цей каталог від інших
                                </div>
                            </div>
                        </div>
                        
                        <div class="settings-field-group full-width">
                            <label for="description" class="settings-field-label">
                                Опис каталогу
                            </label>
                            <div class="settings-field-wrapper">
                                <textarea id="description" 
                                          name="description" 
                                          class="settings-field-textarea" 
                                          rows="3"
                                          placeholder="Детальний опис каталогу, його призначення та особливості (необов'язково)"></textarea>
                                <div class="field-hint">
                                    📄 Опис допоможе вам та іншим користувачам зрозуміти призначення каталогу
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Google Sheets Connection Section -->
                <div class="settings-section">
                    <div class="settings-section-header">
                        <h3>🔗 Підключення до Google Sheets</h3>
                        <p class="settings-section-description">Налаштуйте джерело даних для автоматичного імпорту товарів</p>
                    </div>
                    
                    <!-- Google Sheets Instructions -->
                    <div class="google-sheets-instructions">
                        <div class="instruction-item">
                            <div class="instruction-icon">1️⃣</div>
                            <div class="instruction-content">
                                <strong>Підготуйте Google Sheets:</strong> Переконайтеся, що ваша таблиця містить заголовки стовпців у першому рядку
                            </div>
                        </div>
                        <div class="instruction-item">
                            <div class="instruction-icon">2️⃣</div>
                            <div class="instruction-content">
                                <strong>Налаштуйте доступ:</strong> Зробіть таблицю доступною за посиланням (File → Share → Anyone with the link can view)
                            </div>
                        </div>
                        <div class="instruction-item">
                            <div class="instruction-icon">3️⃣</div>
                            <div class="instruction-content">
                                <strong>Скопіюйте URL:</strong> Вставте звичайне посилання на Google Sheets - плагін автоматично його оброби
                            </div>
                        </div>
                    </div>
                    
                    <div class="settings-fields-grid">
                        <div class="settings-field-group full-width">
                            <label for="google_sheet_url" class="settings-field-label">
                                URL Google Sheets
                            </label>
                            <div class="settings-field-wrapper">
                                <div class="settings-field-with-button">
                                    <input type="url" 
                                           id="google_sheet_url" 
                                           name="google_sheet_url" 
                                           class="settings-field-input" 
                                           placeholder="https://docs.google.com/spreadsheets/d/1ABC...xyz/edit">
                                    <button type="button" 
                                            id="test-sheets-connection-create" 
                                            class="button button-secondary settings-test-btn"
                                            disabled>
                                        🔍 Перевірити
                                    </button>
                                </div>
                                <div class="field-hint">
                                    🔗 Вставте посилання на вашу Google Sheets таблицю. Плагін автоматично конвертує його в XLSX формат
                                </div>
                                <div id="connection-test-result-create" class="connection-status-message" style="display: none;"></div>
                            </div>
                        </div>
                        
                        <div class="settings-field-group">
                            <label for="sheet_name" class="settings-field-label">
                                Назва аркуша
                            </label>
                            <div class="settings-field-wrapper">
                                <input type="text" 
                                       id="sheet_name" 
                                       name="sheet_name" 
                                       class="settings-field-input" 
                                       value="Sheet1"
                                       placeholder="Sheet1">
                                <div class="field-hint">
                                    📋 За замовчуванням: "Sheet1". Змініть, якщо ваші дані знаходяться на іншому аркуші
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Connection Status Preview -->
                    <div class="connection-preview" id="connection-preview" style="display: none;">
                        <h4>📊 Попередній перегляд даних</h4>
                        <div class="preview-content" id="preview-content">
                            <!-- Буде заповнено через JavaScript -->
                        </div>
                    </div>
                </div>

                <!-- Next Steps Information -->
                <div class="settings-section">
                    <div class="settings-section-header">
                        <h3>🚀 Що буде далі?</h3>
                        <p class="settings-section-description">Після створення каталогу ви зможете налаштувати детальні параметри</p>
                    </div>
                    
                    <div class="next-steps-grid">
                        <div class="next-step-item">
                            <div class="next-step-icon">🔄</div>
                            <div class="next-step-content">
                                <h4>Налаштування відповідності стовпців</h4>
                                <p>Ви зможете встановити відповідність між стовпцями Google Sheets та полями каталогу</p>
                            </div>
                        </div>
                        <div class="next-step-item">
                            <div class="next-step-icon">📥</div>
                            <div class="next-step-content">
                                <h4>Імпорт даних</h4>
                                <p>Автоматичне завантаження та обробка даних з вашої Google Sheets таблиці</p>
                            </div>
                        </div>
                        <div class="next-step-item">
                            <div class="next-step-icon">🎨</div>
                            <div class="next-step-content">
                                <h4>Обробка зображень</h4>
                                <p>Зображення будуть автоматично завантажені та оптимізовані до розміру 1000x1000px</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="settings-actions">
                    <div class="settings-actions-primary">
                        <button type="submit" class="button button-primary button-large settings-save-btn" id="create-catalog-btn">
                            ✨ Створити каталог
                        </button>
                    </div>
                    
                    <div class="settings-actions-secondary">
                        <a href="<?php echo admin_url('admin.php?page=catalog-master'); ?>" 
                           class="button button-secondary">
                            ← Повернутися до списку
                        </a>
                        
                        <button type="button" 
                                class="button button-secondary" 
                                id="save-draft-btn"
                                style="display: none;">
                            💾 Зберегти як чернетку
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Additional JavaScript for enhanced UX -->
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const urlInput = document.getElementById('google_sheet_url');
            const testBtn = document.getElementById('test-sheets-connection-create');
            const previewDiv = document.getElementById('connection-preview');
            const previewContent = document.getElementById('preview-content');
            const resultDiv = document.getElementById('connection-test-result-create');
            
            // Enable test button when URL is entered
            urlInput.addEventListener('input', function() {
                testBtn.disabled = !this.value.trim();
            });
            
            // Test connection functionality
            testBtn.addEventListener('click', function() {
                const url = urlInput.value.trim();
                const sheetName = document.getElementById('sheet_name').value.trim() || 'Sheet1';
                
                if (!url) return;
                
                testBtn.disabled = true;
                testBtn.textContent = '⏳ Перевіряємо...';
                resultDiv.style.display = 'none';
                previewDiv.style.display = 'none';
                
                const formData = new FormData();
                formData.append('action', 'catalog_master_test_sheets_connection');
                formData.append('sheet_url', url);
                formData.append('sheet_name', sheetName);
                formData.append('nonce', catalog_master_vite_params.nonce);
                
                fetch(catalog_master_vite_params.ajax_url, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    testBtn.disabled = false;
                    testBtn.textContent = '🔍 Перевірити';
                    
                    if (data.success) {
                        resultDiv.className = 'connection-status-message success';
                        resultDiv.innerHTML = `
                            <div class="status-icon">✅</div>
                            <div class="status-content">
                                <strong>Підключення успішне!</strong><br>
                                Знайдено ${data.data.row_count} рядків з ${data.data.headers.length} стовпцями
                            </div>
                        `;
                        
                        // Show preview
                        previewContent.innerHTML = `
                            <div class="preview-stats">
                                <span class="preview-stat">📊 Рядків: ${data.data.row_count}</span>
                                <span class="preview-stat">📋 Стовпців: ${data.data.headers.length}</span>
                            </div>
                            <div class="preview-headers">
                                <strong>Заголовки стовпців:</strong>
                                ${data.data.headers.map(header => `<span class="header-tag">${header}</span>`).join('')}
                            </div>
                        `;
                        previewDiv.style.display = 'block';
                        
                    } else {
                        resultDiv.className = 'connection-status-message error';
                        resultDiv.innerHTML = `
                            <div class="status-icon">❌</div>
                            <div class="status-content">
                                <strong>Помилка підключення</strong><br>
                                ${data.data || 'Невідома помилка'}
                            </div>
                        `;
                    }
                    
                    resultDiv.style.display = 'block';
                })
                .catch(error => {
                    testBtn.disabled = false;
                    testBtn.textContent = '🔍 Перевірити';
                    
                    resultDiv.className = 'connection-status-message error';
                    resultDiv.innerHTML = `
                        <div class="status-icon">❌</div>
                        <div class="status-content">
                            <strong>Помилка мережі</strong><br>
                            Перевірте інтернет-з'єднання
                        </div>
                    `;
                    resultDiv.style.display = 'block';
                });
            });
        });
        </script>
        
        <style>
        /* Modern Create Catalog Styles */
        .add-catalog-header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px 0;
        }
        
        .add-catalog-header h1 {
            font-size: 2.2em;
            margin: 0 0 10px 0;
            color: #1d2327;
        }
        
        .add-catalog-subtitle {
            font-size: 1.1em;
            color: #646970;
            margin: 0;
        }
        
        /* Progress Steps */
        .creation-progress {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 30px 0 40px 0;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        
        .progress-step {
            display: flex;
            align-items: center;
            gap: 12px;
            opacity: 0.5;
            transition: opacity 0.3s ease;
        }
        
        .progress-step.active {
            opacity: 1;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #0073aa;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 16px;
        }
        
        .progress-step:not(.active) .step-number {
            background: #c3c4c7;
        }
        
        .step-info {
            display: flex;
            flex-direction: column;
        }
        
        .step-title {
            font-weight: 600;
            font-size: 14px;
            color: #1d2327;
        }
        
        .step-description {
            font-size: 12px;
            color: #646970;
        }
        
        .progress-separator {
            width: 60px;
            height: 2px;
            background: #c3c4c7;
            margin: 0 20px;
        }
        
        /* Google Sheets Instructions */
        .google-sheets-instructions {
            background: #fff8e1;
            border: 1px solid #ffcc02;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .instruction-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 15px;
        }
        
        .instruction-item:last-child {
            margin-bottom: 0;
        }
        
        .instruction-icon {
            font-size: 20px;
            line-height: 1;
        }
        
        .instruction-content {
            flex: 1;
            font-size: 14px;
            line-height: 1.5;
        }
        
        /* Connection Preview */
        .connection-preview {
            background: #e7f7ff;
            border: 1px solid #00a0d2;
            border-radius: 6px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .preview-stats {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .preview-stat {
            background: white;
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #c3c4c7;
            font-size: 13px;
            font-weight: 500;
        }
        
        .preview-headers {
            font-size: 14px;
        }
        
        .header-tag {
            display: inline-block;
            background: #0073aa;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            margin: 2px 4px 2px 0;
        }
        
        /* Next Steps */
        .next-steps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .next-step-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 20px;
            background: #f6f7f7;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        
        .next-step-icon {
            font-size: 24px;
            line-height: 1;
        }
        
        .next-step-content h4 {
            margin: 0 0 8px 0;
            font-size: 15px;
            color: #1d2327;
        }
        
        .next-step-content p {
            margin: 0;
            font-size: 13px;
            color: #646970;
            line-height: 1.4;
        }
        
        /* Connection Status Messages */
        .connection-status-message {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 15px;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 14px;
        }
        
        .connection-status-message.success {
            background: #e6ffed;
            border: 1px solid #00a32a;
            color: #00a32a;
        }
        
        .connection-status-message.error {
            background: #ffebee;
            border: 1px solid #d63638;
            color: #d63638;
        }
        
        .status-icon {
            font-size: 18px;
            line-height: 1;
        }
        
        .status-content {
            flex: 1;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .creation-progress {
                flex-direction: column;
                gap: 15px;
            }
            
            .progress-separator {
                width: 2px;
                height: 30px;
                margin: 0;
            }
            
            .next-steps-grid {
                grid-template-columns: 1fr;
            }
            
            .preview-stats {
                flex-direction: column;
                gap: 10px;
            }
        }
        </style>
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
                <!-- Catalog Overview Stats -->
                <div class="settings-overview-grid">
                    <div class="settings-overview-card">
                        <div class="settings-card-content">
                            <h4>Записів в каталозі</h4>
                            <span class="settings-card-value"><?php echo number_format($items_count); ?></span>
                        </div>
                    </div>
                    
                    <div class="settings-overview-card">
                        <div class="settings-card-content">
                            <h4>Налаштувань мапінгу</h4>
                            <span class="settings-card-value"><?php echo count($mappings); ?></span>
                        </div>
                    </div>
                    
                    <div class="settings-overview-card">
                        <div class="settings-card-content">
                            <h4>Дата створення</h4>
                            <span class="settings-card-value"><?php echo date_i18n('d.m.Y', strtotime($catalog->created_at)); ?></span>
                        </div>
                    </div>
                    
                    <div class="settings-overview-card">
                        <div class="settings-card-content">
                            <h4>Статус підключення</h4>
                            <span class="settings-card-value connection-status" id="connection-status">
                                <?php echo !empty($catalog->google_sheet_url) ? 'Налаштовано' : 'Не налаштовано'; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <form method="post" action="" id="catalog-settings-form">
                    <?php wp_nonce_field('catalog_master_update'); ?>
                    <input type="hidden" name="action" value="update_catalog">
                    <input type="hidden" name="catalog_id" value="<?php echo $catalog->id; ?>">
                    
                    <!-- Basic Information Section -->
                    <div class="settings-section">
                        <div class="settings-section-header">
                            <h3>Основна інформація</h3>
                            <p class="settings-section-description">Налаштування назви та опису каталогу</p>
                        </div>
                        
                        <div class="settings-fields-grid">
                            <div class="settings-field-group">
                                <label for="name" class="settings-field-label">
                                    Назва каталогу <span class="label-required">*</span>
                                </label>
                                <div class="settings-field-wrapper">
                                    <input type="text" 
                                           id="name" 
                                           name="name" 
                                           class="settings-field-input" 
                                           value="<?php echo esc_attr($catalog->name); ?>" 
                                           required
                                           placeholder="Введіть назву каталогу">
                                    <div class="field-hint">
                                        Коротка, зрозуміла назва для ідентифікації каталогу
                                    </div>
                                </div>
                            </div>
                            
                            <div class="settings-field-group full-width">
                                <label for="description" class="settings-field-label">
                                    Опис каталогу
                                </label>
                                <div class="settings-field-wrapper">
                                    <textarea id="description" 
                                              name="description" 
                                              class="settings-field-textarea" 
                                              rows="3"
                                              placeholder="Детальний опис каталогу (необов'язково)"><?php echo esc_textarea($catalog->description); ?></textarea>
                                    <div class="field-hint">
                                        Опис допоможе вам та іншим користувачам зрозуміти призначення каталогу
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Google Sheets Connection Section -->
                    <div class="settings-section">
                        <div class="settings-section-header">
                            <h3>Підключення до Google Sheets</h3>
                            <p class="settings-section-description">Налаштування джерела даних для імпорту</p>
                        </div>
                        
                        <div class="settings-fields-grid">
                            <div class="settings-field-group full-width">
                                <label for="google_sheet_url" class="settings-field-label">
                                    URL Google Sheets
                                </label>
                                <div class="settings-field-wrapper">
                                    <div class="settings-field-with-button">
                                        <input type="url" 
                                               id="google_sheet_url" 
                                               name="google_sheet_url" 
                                               class="settings-field-input" 
                                               value="<?php echo esc_attr($catalog->google_sheet_url); ?>"
                                               placeholder="https://docs.google.com/spreadsheets/d/...">
                                        <button type="button" 
                                                id="test-sheets-connection" 
                                                class="button button-secondary settings-test-btn">
                                            Перевірити підключення
                                        </button>
                                    </div>
                                    <div class="field-hint">
                                        Вставте звичайне посилання на Google Sheets — плагін автоматично конвертує його в XLSX формат
                                    </div>
                                    <div id="connection-test-result" class="connection-status-message" style="display: none;"></div>
                                </div>
                            </div>
                            
                            <div class="settings-field-group">
                                <label for="sheet_name" class="settings-field-label">
                                    Назва аркуша
                                </label>
                                <div class="settings-field-wrapper">
                                    <input type="text" 
                                           id="sheet_name" 
                                           name="sheet_name" 
                                           class="settings-field-input" 
                                           value="<?php echo esc_attr($catalog->sheet_name); ?>"
                                           placeholder="Sheet1">
                                    <div class="field-hint">
                                        За замовчуванням: Sheet1. Змініть, якщо ваші дані на іншому аркуші
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Connection Status Indicator -->
                        <div class="connection-status-indicator" id="google-sheets-status">
                            <?php if (!empty($catalog->google_sheet_url)): ?>
                                <span class="status-item status-configured">
                                    <span class="status-text">✓ URL налаштовано</span>
                                </span>
                            <?php else: ?>
                                <span class="status-item status-not-configured">
                                    <span class="status-text">⚠ URL не налаштовано</span>
                                </span>
                            <?php endif; ?>
                            
                            <?php if (!empty($mappings)): ?>
                                <span class="status-item status-configured">
                                    <span class="status-text">✓ Мапінг налаштовано (<?php echo count($mappings); ?> полів)</span>
                                </span>
                            <?php else: ?>
                                <span class="status-item status-warning">
                                    <span class="status-text">⚠ Мапінг не налаштовано</span>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Catalog Information Section -->
                    <div class="settings-section">
                        <div class="settings-section-header">
                            <h3>Інформація про каталог</h3>
                            <p class="settings-section-description">Системна інформація та статистика</p>
                        </div>
                        
                        <div class="settings-info-grid">
                            <div class="settings-info-item">
                                <span class="info-label">ID каталогу:</span>
                                <span class="info-value">
                                    <code><?php echo $catalog->id; ?></code>
                                    <button type="button" class="copy-btn" onclick="navigator.clipboard.writeText('<?php echo $catalog->id; ?>')" title="Копіювати">📋</button>
                                </span>
                            </div>
                            
                            <div class="settings-info-item">
                                <span class="info-label">Створено:</span>
                                <span class="info-value"><?php echo date_i18n('d.m.Y H:i', strtotime($catalog->created_at)); ?></span>
                            </div>
                            
                            <div class="settings-info-item">
                                <span class="info-label">Останнє оновлення:</span>
                                <span class="info-value"><?php echo date_i18n('d.m.Y H:i', strtotime($catalog->updated_at)); ?></span>
                            </div>
                            
                            <div class="settings-info-item">
                                <span class="info-label">Статус:</span>
                                <span class="info-value">
                                    <span class="status-badge status-active">Активний</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="settings-actions">
                        <div class="settings-actions-primary">
                            <button type="submit" class="button button-primary button-large settings-save-btn">
                                Зберегти налаштування
                            </button>
                        </div>
                        
                        <div class="settings-actions-secondary">
                            <a href="<?php echo admin_url('admin.php?page=catalog-master'); ?>" 
                               class="button button-secondary">
                                ← Повернутися до списку
                            </a>
                            
                            <button type="button" 
                                    class="button button-link-delete" 
                                    onclick="if(confirm('Ви впевнені, що хочете видалити цей каталог? Всі дані будуть втрачені!')) { window.location.href='<?php echo wp_nonce_url(admin_url('admin.php?page=catalog-master&action=delete&id=' . $catalog->id), 'catalog_master_delete'); ?>'; }">
                                🗑️ Видалити каталог
                            </button>
                        </div>
                    </div>
                </form>
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
                        <button type="button" id="clear-cache" class="button button-secondary" style="margin-left: 10px;" title="Очищує кеш для отримання свіжих даних з Google Sheets">
                            🗑️ Очистити кеш
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
                                <p>Експорт в форматі Excel (.xlsx)</p>
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
            
            <!-- Image Processing Diagnostics -->
            <div class="catalog-master-card">
                <h3>🖼️ Діагностика обробки зображень</h3>
                <?php $this->render_image_diagnostics(); ?>
            </div>
            
            <!-- Image Upload Testing -->
            <div class="catalog-master-card">
                <h3>🧪 Тестування завантаження зображень</h3>
                <?php $this->render_image_upload_test(); ?>
            </div>
            
            <div class="catalog-master-card">
                <h3>Тестування системи</h3>
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
    
    /**
     * Render image processing diagnostics
     */
    private function render_image_diagnostics() {
        ?>
        <div class="diagnostics-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <h4>📊 Інформація про сервер</h4>
                <table class="form-table">
                    <tr>
                        <th>Версія PHP:</th>
                        <td><strong><?php echo PHP_VERSION; ?></strong></td>
                    </tr>
                    <tr>
                        <th>Операційна система:</th>
                        <td><code><?php echo php_uname('s') . ' ' . php_uname('r'); ?></code></td>
                    </tr>
                    <tr>
                        <th>Memory Limit:</th>
                        <td><strong><?php echo ini_get('memory_limit'); ?></strong></td>
                    </tr>
                    <tr>
                        <th>Max Execution Time:</th>
                        <td><strong><?php echo ini_get('max_execution_time'); ?> секунд</strong></td>
                    </tr>
                </table>
            </div>
            
            <div>
                <h4>📦 Розширення для зображень</h4>
                <table class="form-table">
                    <tr>
                        <th>GD Extension:</th>
                        <td>
                            <?php if (extension_loaded('gd')): ?>
                                <strong style="color: green;">✅ Встановлено</strong>
                                <?php 
                                $gd_info = gd_info();
                                if ($gd_info && is_array($gd_info) && isset($gd_info['GD Version'])) {
                                    echo '<br><small>Версія: ' . $gd_info['GD Version'] . '</small>';
                                }
                                ?>
                            <?php else: ?>
                                <strong style="color: red;">❌ НЕ встановлено</strong>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>ImageMagick Extension:</th>
                        <td>
                            <?php if (extension_loaded('imagick')): ?>
                                <strong style="color: green;">✅ Встановлено</strong>
                                <?php if (class_exists('Imagick')): ?>
                                    <?php 
                                    try {
                                        $imagick = new Imagick();
                                        $version = $imagick->getVersion();
                                        if (is_array($version) && isset($version['versionString'])) {
                                            echo '<br><small>' . $version['versionString'] . '</small>';
                                        }
                                        $imagick->clear();
                                    } catch (Exception $e) {
                                        echo '<br><small>Помилка отримання версії</small>';
                                    }
                                    ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <strong style="color: orange;">⚠️ НЕ встановлено</strong>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Підтримувані формати:</th>
                        <td>
                            <?php
                            $formats = array();
                            if (extension_loaded('gd')) {
                                $gd_info = gd_info();
                                if ($gd_info && is_array($gd_info)) {
                                    if (isset($gd_info['JPEG Support']) && $gd_info['JPEG Support']) $formats[] = 'JPEG';
                                    if (isset($gd_info['PNG Support']) && $gd_info['PNG Support']) $formats[] = 'PNG';
                                    if (isset($gd_info['GIF Create Support']) && $gd_info['GIF Create Support']) $formats[] = 'GIF';
                                    if (isset($gd_info['WebP Support']) && $gd_info['WebP Support']) $formats[] = 'WebP';
                                    if (isset($gd_info['AVIF Support']) && $gd_info['AVIF Support']) $formats[] = 'AVIF';
                                }
                            }
                            echo implode(', ', $formats);
                            ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <h4>🧪 Тестування створення зображень</h4>
        <div class="diagnostics-tests">
            <?php
            // Test GD image creation
            if (extension_loaded('gd')) {
                echo '<div class="test-result">';
                echo '<strong>GD тест:</strong> ';
                try {
                    $test_image = imagecreate(10, 10);
                    $bg_color = imagecolorallocate($test_image, 255, 255, 255);
                    
                    ob_start();
                    imagejpeg($test_image, null, 90);
                    $jpeg_data = ob_get_contents();
                    ob_end_clean();
                    imagedestroy($test_image);
                    
                    if ($jpeg_data !== false && strlen($jpeg_data) > 0) {
                        echo '<span style="color: green;">✅ Створення JPEG працює (' . strlen($jpeg_data) . ' байт)</span>';
                    } else {
                        echo '<span style="color: red;">❌ НЕ може створити JPEG</span>';
                    }
                } catch (Exception $e) {
                    echo '<span style="color: red;">❌ Помилка: ' . esc_html($e->getMessage()) . '</span>';
                }
                echo '</div>';
            }
            
            // Test ImageMagick image creation
            if (extension_loaded('imagick') && class_exists('Imagick')) {
                echo '<div class="test-result">';
                echo '<strong>ImageMagick тест:</strong> ';
                try {
                    $imagick = new Imagick();
                    $imagick->newImage(10, 10, 'white');
                    $imagick->setImageFormat('jpeg');
                    $imagick->setImageCompressionQuality(90);
                    
                    $jpeg_blob = $imagick->getImageBlob();
                    $imagick->clear();
                    
                    if ($jpeg_blob !== false && strlen($jpeg_blob) > 0) {
                        echo '<span style="color: green;">✅ Створення JPEG працює (' . strlen($jpeg_blob) . ' байт)</span>';
                    } else {
                        echo '<span style="color: red;">❌ НЕ може створити JPEG</span>';
                    }
                } catch (Exception $e) {
                    echo '<span style="color: red;">❌ Помилка: ' . esc_html($e->getMessage()) . '</span>';
                }
                echo '</div>';
            }
            
            // Test WordPress Image Editor
            echo '<div class="test-result">';
            echo '<strong>WordPress Image Editor тест:</strong> ';
            
            // Test available image editors correctly
            $editors = array();
            if (class_exists('WP_Image_Editor_GD') && WP_Image_Editor_GD::test()) {
                $editors[] = 'GD';
            }
            if (class_exists('WP_Image_Editor_Imagick') && WP_Image_Editor_Imagick::test()) {
                $editors[] = 'ImageMagick';
            }
            
            if (!empty($editors)) {
                echo '<span style="color: green;">✅ Доступні редактори: ' . implode(', ', $editors) . '</span>';
            } else {
                echo '<span style="color: red;">❌ Немає доступних редакторів</span>';
                
                // Additional debug info
                echo '<br><small>GD тест: ' . (class_exists('WP_Image_Editor_GD') ? 'клас є' : 'немає класу');
                if (class_exists('WP_Image_Editor_GD')) {
                    echo ', test(): ' . (WP_Image_Editor_GD::test() ? 'passed' : 'failed');
                }
                echo '</small>';
                
                echo '<br><small>ImageMagick тест: ' . (class_exists('WP_Image_Editor_Imagick') ? 'клас є' : 'немає класу');
                if (class_exists('WP_Image_Editor_Imagick')) {
                    echo ', test(): ' . (WP_Image_Editor_Imagick::test() ? 'passed' : 'failed');
                }
                echo '</small>';
            }
            echo '</div>';
            ?>
        </div>
        
        <h4>🏁 Висновок</h4>
        <?php
        $can_process_images = extension_loaded('gd') || extension_loaded('imagick');
        
        if ($can_process_images):
            ?>
            <div class="notice notice-success inline">
                <p><strong>✅ Сервер повністю підтримує обробку зображень!</strong></p>
                <?php if (extension_loaded('gd') && extension_loaded('imagick')): ?>
                    <p>🎉 Доступні обидва редактори (GD та ImageMagick)</p>
                <?php elseif (extension_loaded('gd')): ?>
                    <p>📷 Доступний GD редактор</p>
                <?php else: ?>
                    <p>🎨 Доступний ImageMagick редактор</p>
                <?php endif; ?>
                <p><strong>Рекомендація:</strong> Якщо є проблеми з завантаженням зображень, перевірте логи WordPress та права доступу до папок.</p>
            </div>
        <?php else: ?>
            <div class="notice notice-error inline">
                <p><strong>❌ Сервер НЕ підтримує обробку зображень!</strong></p>
                <p>Зверніться до хостинг-провайдера для встановлення GD або ImageMagick розширень.</p>
            </div>
        <?php endif; ?>
        
        <style>
        .diagnostics-grid {
            margin-bottom: 20px;
        }
        .test-result {
            padding: 8px 12px;
            margin: 5px 0;
            background: #f9f9f9;
            border-left: 4px solid #ddd;
            border-radius: 0 4px 4px 0;
        }
        .diagnostics-tests {
            background: #f1f1f1;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
        }
        </style>
        <?php
    }
    
    /**
     * Render image upload testing
     */
    private function render_image_upload_test() {
        // Handle test upload
        if (isset($_POST['test_image_upload']) && isset($_FILES['test_image'])) {
            $this->handle_test_image_upload();
        }
        ?>
        
        <p>Завантажте зображення для тестування всього процесу обробки, який використовується в плагіні:</p>
        
        <form method="post" enctype="multipart/form-data" style="margin-bottom: 20px;">
            <?php wp_nonce_field('catalog_master_debug_image', 'debug_image_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="test_image">Оберіть зображення:</label></th>
                    <td>
                        <input type="file" name="test_image" id="test_image" accept="image/*" required>
                        <p class="description">Підтримувані формати: JPG, PNG, GIF, WebP, BMP, AVIF</p>
                    </td>
                </tr>
                <tr>
                    <th></th>
                    <td>
                        <button type="submit" name="test_image_upload" class="button button-primary">
                            🚀 Тестувати завантаження та обробку
                        </button>
                    </td>
                </tr>
            </table>
        </form>
        
        <div class="test-info">
            <h4>🔍 Що буде протестовано:</h4>
            <ul>
                <li>✅ Завантаження файлу через $_FILES</li>
                <li>✅ Перевірка існування та читання тимчасового файлу</li>
                <li>✅ Функція getimagesize() для аналізу зображення</li>
                <li>✅ WordPress Image Editor (wp_get_image_editor)</li>
                <li>✅ **Примусова** зміна розміру до 100x100 пікселів (тест; в реальній роботі - 1000x1000)</li>
                <li>✅ Збереження в форматі JPEG з якістю 90%</li>
                <li>✅ Перевірка прав доступу до папки uploads</li>
            </ul>
            
            <div class="notice notice-info inline">
                <p><strong>💡 Порада:</strong> Якщо тест пройде успішно, але завантаження в таблиці не працює, проблема може бути в:</p>
                <ul>
                    <li>• Правах доступу до конкретної папки каталогу</li>
                    <li>• Налаштуваннях безпеки WordPress</li>
                    <li>• Конфліктах з іншими плагінами</li>
                </ul>
            </div>
        </div>
        
        <style>
        .test-info {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .test-info ul {
            margin: 10px 0;
        }
        .test-info li {
            margin: 5px 0;
        }
        </style>
        <?php
    }
    
    /**
     * Handle test image upload
     */
    private function handle_test_image_upload() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['debug_image_nonce'], 'catalog_master_debug_image')) {
            echo '<div class="notice notice-error"><p>❌ Помилка безпеки. Спробуйте ще раз.</p></div>';
            return;
        }
        
        $file = $_FILES['test_image'];
        
        echo '<div class="test-results" style="background: #f1f1f1; padding: 20px; border-radius: 4px; margin: 20px 0;">';
        echo '<h4>📊 Результати тестування</h4>';
        
        // Step 1: Basic file info
        echo '<div class="test-step">';
        echo '<h5>1️⃣ Інформація про файл</h5>';
        echo '<ul>';
        echo '<li><strong>Назва:</strong> ' . esc_html($file['name']) . '</li>';
        echo '<li><strong>Розмір:</strong> ' . number_format($file['size']) . ' байт (' . number_format($file['size']/1024, 1) . ' KB)</li>';
        echo '<li><strong>MIME тип:</strong> ' . esc_html($file['type']) . '</li>';
        echo '<li><strong>Тимчасовий файл:</strong> ' . esc_html($file['tmp_name']) . '</li>';
        echo '<li><strong>Помилка завантаження:</strong> ' . ($file['error'] === UPLOAD_ERR_OK ? 'Немає ✅' : 'Код ' . $file['error'] . ' ❌') . '</li>';
        echo '</ul>';
        echo '</div>';
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo '<div class="notice notice-error inline"><p>❌ Файл не завантажився правильно.</p></div>';
            echo '</div>';
            return;
        }
        
        // Step 2: File existence and readability
        echo '<div class="test-step">';
        echo '<h5>2️⃣ Тест доступу до файлу</h5>';
        $file_exists = file_exists($file['tmp_name']);
        $file_readable = is_readable($file['tmp_name']);
        $file_size = $file_exists ? filesize($file['tmp_name']) : 0;
        
        echo '<ul>';
        echo '<li><strong>Файл існує:</strong> ' . ($file_exists ? 'Так ✅' : 'Ні ❌') . '</li>';
        echo '<li><strong>Файл читається:</strong> ' . ($file_readable ? 'Так ✅' : 'Ні ❌') . '</li>';
        echo '<li><strong>Розмір файлу:</strong> ' . number_format($file_size) . ' байт</li>';
        echo '</ul>';
        echo '</div>';
        
        if (!$file_exists || !$file_readable) {
            echo '<div class="notice notice-error inline"><p>❌ Проблеми з доступом до файлу.</p></div>';
            echo '</div>';
            return;
        }
        
        // Step 3: getimagesize test
        echo '<div class="test-step">';
        echo '<h5>3️⃣ Тест getimagesize()</h5>';
        $image_info = getimagesize($file['tmp_name']);
        
        if ($image_info !== false) {
            echo '<p style="color: green;">✅ <strong>Успішно!</strong></p>';
            echo '<ul>';
            echo '<li><strong>Ширина:</strong> ' . $image_info[0] . ' px</li>';
            echo '<li><strong>Висота:</strong> ' . $image_info[1] . ' px</li>';
            echo '<li><strong>MIME тип:</strong> ' . $image_info['mime'] . '</li>';
            if (isset($image_info['channels'])) {
                echo '<li><strong>Канали:</strong> ' . $image_info['channels'] . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p style="color: red;">❌ <strong>НЕ вдалося визначити розмір зображення</strong></p>';
            $mime_type = function_exists('mime_content_type') ? mime_content_type($file['tmp_name']) : 'невідомий';
            echo '<p>MIME тип з mime_content_type(): ' . $mime_type . '</p>';
        }
        echo '</div>';
        
        // Step 4: WordPress Image Editor test
        echo '<div class="test-step">';
        echo '<h5>4️⃣ Тест WordPress Image Editor</h5>';
        
        $image_editor = wp_get_image_editor($file['tmp_name']);
        
        if (is_wp_error($image_editor)) {
            echo '<p style="color: red;">❌ <strong>wp_get_image_editor():</strong> ' . $image_editor->get_error_message() . '</p>';
        } else {
            echo '<p style="color: green;">✅ <strong>wp_get_image_editor():</strong> Успішно створено</p>';
            
            // Get image size
            $size = $image_editor->get_size();
            echo '<p><strong>Розмір з редактора:</strong> ' . $size['width'] . 'x' . $size['height'] . '</p>';
            
            // Test resize (always resize to test dimensions, regardless of current size)
            echo '<p><strong>Тест зміни розміру:</strong> ' . $size['width'] . 'x' . $size['height'] . ' → 100x100 (тестовий розмір; в каталозі буде 1000x1000)</p>';
            $resized = $image_editor->resize(100, 100, true);
            if (is_wp_error($resized)) {
                echo '<p style="color: red;">❌ <strong>Зміна розміру:</strong> ' . $resized->get_error_message() . '</p>';
            } else {
                echo '<p style="color: green;">✅ <strong>Зміна розміру:</strong> Успішно (завжди ресайзимо незалежно від початкового розміру)</p>';
                
                // Test save
                $upload_dir = wp_upload_dir();
                $temp_path = $upload_dir['basedir'] . '/test_image_' . time() . '.jpg';
                $saved = $image_editor->save($temp_path, 'image/jpeg');
                
                if (is_wp_error($saved)) {
                    echo '<p style="color: red;">❌ <strong>Збереження:</strong> ' . $saved->get_error_message() . '</p>';
                } else {
                    echo '<p style="color: green;">✅ <strong>Збереження:</strong> Успішно (' . number_format(filesize($saved['path'])) . ' байт)</p>';
                    
                    // Show image if saved successfully
                    $temp_url = $upload_dir['baseurl'] . '/' . basename($saved['path']);
                    echo '<p><img src="' . esc_url($temp_url) . '" style="max-width: 100px; border: 1px solid #ddd;" alt="Test Image"></p>';
                    
                    // Clean up test file after a delay (via JavaScript)
                    echo '<script>setTimeout(function() { 
                        fetch("' . admin_url('admin-ajax.php') . '", {
                            method: "POST",
                            headers: {"Content-Type": "application/x-www-form-urlencoded"},
                            body: "action=catalog_master_cleanup_test_image&path=' . urlencode($saved['path']) . '&nonce=' . wp_create_nonce('cleanup_test_image') . '"
                        });
                    }, 5000);</script>';
                }
            }
        }
        echo '</div>';
        
        // Step 5: Upload directory test
        echo '<div class="test-step">';
        echo '<h5>5️⃣ Тест папки завантажень</h5>';
        $upload_dir = wp_upload_dir();
        $writable = is_writable($upload_dir['basedir']);
        
        echo '<ul>';
        echo '<li><strong>Папка:</strong> ' . $upload_dir['basedir'] . '</li>';
        echo '<li><strong>URL:</strong> ' . $upload_dir['baseurl'] . '</li>';
        echo '<li><strong>Доступна для запису:</strong> ' . ($writable ? 'Так ✅' : 'Ні ❌') . '</li>';
        echo '</ul>';
        echo '</div>';
        
        // Overall conclusion
        $all_tests_passed = $image_info !== false && !is_wp_error($image_editor) && $writable;
        
        echo '<div class="test-conclusion">';
        if ($all_tests_passed) {
            echo '<div class="notice notice-success inline">';
            echo '<p><strong>🎉 Всі тести пройшли успішно!</strong></p>';
            echo '<p>Ваш сервер повністю підтримує обробку зображень. Якщо в плагіні є проблеми, вони можуть бути пов\'язані з:</p>';
            echo '<ul>';
            echo '<li>• Правами доступу до конкретних папок каталогу</li>';
            echo '<li>• Налаштуваннями безпеки WordPress</li>';
            echo '<li>• Особливостями конкретних файлів зображень</li>';
            echo '</ul>';
            echo '</div>';
        } else {
            echo '<div class="notice notice-error inline">';
            echo '<p><strong>❌ Виявлено проблеми з обробкою зображень</strong></p>';
            echo '<p>Перевірте вищенаведені результати та зверніться до адміністратора сервера.</p>';
            echo '</div>';
        }
        echo '</div>';
        
        echo '</div>'; // .test-results
        
        echo '<style>';
        echo '.test-step { margin: 15px 0; padding: 10px; background: white; border-radius: 4px; border: 1px solid #ddd; }';
        echo '.test-step h5 { margin: 0 0 10px 0; color: #0073aa; }';
        echo '.test-step ul { margin: 5px 0; }';
        echo '.test-step li { margin: 3px 0; }';
        echo '.test-conclusion { margin-top: 20px; }';
        echo '</style>';
    }
    
    /**
     * AJAX handler for cleaning up test images
     */
    public function ajax_cleanup_test_image() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'cleanup_test_image')) {
            wp_die('Security check failed');
        }
        
        // Verify user permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $file_path = sanitize_text_field($_POST['path']);
        
        // Security check: file must be in uploads directory and be a test image
        $upload_dir = wp_upload_dir();
        if (strpos($file_path, $upload_dir['basedir']) !== 0 || strpos($file_path, 'test_image_') === false) {
            wp_die('Invalid file path');
        }
        
        // Delete the file if it exists
        if (file_exists($file_path)) {
            if (unlink($file_path)) {
                wp_send_json_success('Test image cleaned up successfully');
            } else {
                wp_send_json_error('Failed to delete test image');
            }
        } else {
            wp_send_json_success('Test image already cleaned up');
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