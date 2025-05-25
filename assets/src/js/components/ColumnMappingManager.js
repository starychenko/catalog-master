import { showMessage, setButtonLoading } from '../utils/helpers.js';

/**
 * Column Mapping Manager Component
 * Manages column mapping between Google Sheets and catalog fields
 */
export default class ColumnMappingManager {
    constructor(api, state) {
        this.api = api;
        this.state = state;
        this.googleHeaders = [];
        this.catalogColumns = [
            { value: 'product_id', label: 'Product ID' },
            { value: 'product_name', label: 'Product Name' },
            { value: 'product_price', label: 'Product Price' },
            { value: 'product_qty', label: 'Product Quantity' },
            { value: 'product_image_url', label: 'Product Image URL' },
            { value: 'product_sort_order', label: 'Product Sort Order' },
            { value: 'product_description', label: 'Product Description' },
            { value: 'category_id_1', label: 'Category ID 1' },
            { value: 'category_id_2', label: 'Category ID 2' },
            { value: 'category_id_3', label: 'Category ID 3' },
            { value: 'category_name_1', label: 'Category Name 1' },
            { value: 'category_name_2', label: 'Category Name 2' },
            { value: 'category_name_3', label: 'Category Name 3' },
            { value: 'category_image_1', label: 'Category Image 1' },
            { value: 'category_image_2', label: 'Category Image 2' },
            { value: 'category_image_3', label: 'Category Image 3' },
            { value: 'category_sort_order_1', label: 'Category Sort Order 1' },
            { value: 'category_sort_order_2', label: 'Category Sort Order 2' },
            { value: 'category_sort_order_3', label: 'Category Sort Order 3' }
        ];
    }
    
    init() {
        console.log('🗂️ Column Mapping Manager initialized');
        this.bindEvents();
        this.loadExistingData();
    }
    
    /**
     * Bind event handlers
     */
    bindEvents() {
        // Get Google Sheets headers button
        const getSheetsBtn = document.getElementById('get-sheets-headers');
        if (getSheetsBtn) {
            getSheetsBtn.addEventListener('click', (e) => this.getSheetsHeaders(e));
        }
        
        // Test connection button
        const testConnectionBtn = document.getElementById('test-sheets-connection');
        if (testConnectionBtn) {
            testConnectionBtn.addEventListener('click', (e) => this.testSheetsConnection(e));
        }
        
        // Add mapping row button
        const addMappingBtn = document.getElementById('add-mapping-row');
        if (addMappingBtn) {
            addMappingBtn.addEventListener('click', (e) => this.addMappingRow(e));
        }
        
        // Save column mapping button
        const saveMappingBtn = document.getElementById('save-column-mapping');
        if (saveMappingBtn) {
            saveMappingBtn.addEventListener('click', (e) => this.saveColumnMapping(e));
        }
        
        // Delegate events for dynamic elements
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('remove-mapping-btn')) {
                this.removeMappingRow(e);
            }
        });
        
        // Listen for column selection changes - delegate to container
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('column-mapping-select')) {
                if (e.target.classList.contains('google-column')) {
                    // Google column changed - update all Google selects
                    this.updateGoogleColumnSelects();
                    // Also update catalog selects and status
                    this.updateCatalogColumnSelects();
                } else if (e.target.classList.contains('catalog-column')) {
                    // Catalog column changed - update all catalog selects
                    this.updateCatalogColumnSelects();
                    // Also update Google selects
                    this.updateGoogleColumnSelects();
                }
                this.updateCatalogColumnStatus();
            }
        });
    }
    
    /**
     * Test Google Sheets connection
     */
    async testSheetsConnection(e) {
        e.preventDefault();
        
        const sheetUrl = document.getElementById('google_sheet_url')?.value;
        const sheetName = document.getElementById('sheet_name')?.value || 'Sheet1';
        
        if (!sheetUrl) {
            showMessage('Введіть URL Google Sheets', 'error');
            return;
        }
        
        const btn = e.target;
        const originalText = btn.textContent;
        setButtonLoading(btn, true, originalText);
        
        try {
            const response = await this.api.testSheetsConnection(sheetUrl, sheetName);
            showMessage(`${response.message} (Знайдено ${response.row_count} рядків)`, 'success');
            
            if (response.headers) {
                this.googleHeaders = response.headers;
                const getSheetsBtn = document.getElementById('get-sheets-headers');
                if (getSheetsBtn) {
                    getSheetsBtn.disabled = false;
                }
            }
        } catch (error) {
            showMessage('Помилка підключення', 'error');
        } finally {
            setButtonLoading(btn, false, originalText);
        }
    }
    
    /**
     * Get Google Sheets headers
     */
    async getSheetsHeaders(e) {
        e.preventDefault();
        
        const sheetUrl = document.getElementById('google_sheet_url')?.value;
        const sheetName = document.getElementById('sheet_name')?.value || 'Sheet1';
        
        const btn = e.target;
        const originalText = btn.textContent;
        setButtonLoading(btn, true, originalText);
        
        try {
            const response = await this.api.getSheetsHeaders(sheetUrl, sheetName);
            this.googleHeaders = response.headers;
            
            // Check if we have existing mappings before clearing
            const existingRows = document.querySelectorAll('#column-mapping-rows .column-mapping-row');
            const hasExistingMappings = Array.from(existingRows).some(row => {
                const googleSelect = row.querySelector('.google-column');
                const catalogSelect = row.querySelector('.catalog-column');
                return googleSelect?.value || catalogSelect?.value;
            });
            
            // Only populate clean mapping if no existing mappings
            if (!hasExistingMappings || existingRows.length === 0) {
                this.populateColumnMapping();
            } else {
                // Update existing select options with new headers
                this.updateGoogleColumnSelects();
                this.updateCatalogColumnSelects();
            }
            
            this.updateColumnStatus();
            showMessage(`Заголовки завантажено успішно (${response.headers.length} стовпців)`, 'success');
        } catch (error) {
            showMessage('Помилка завантаження заголовків', 'error');
        } finally {
            setButtonLoading(btn, false, originalText);
        }
    }
    
    /**
     * Populate column mapping interface
     */
    populateColumnMapping() {
        const container = document.getElementById('column-mapping-rows');
        if (!container) return;
        
        container.innerHTML = '';
        
        // Start with one empty row instead of all catalog columns
        this.addMappingRowForColumn('', '', 0);
        
        const saveBtn = document.getElementById('save-column-mapping');
        if (saveBtn) {
            saveBtn.disabled = false;
        }
    }
    
    /**
     * Update visual column status
     */
    updateColumnStatus() {
        // Update Google Sheets columns
        const googleContainer = document.getElementById('google-columns-status');
        if (googleContainer) {
            googleContainer.innerHTML = '';
            
            if (this.googleHeaders && this.googleHeaders.length > 0) {
                this.googleHeaders.forEach(header => {
                    const item = document.createElement('div');
                    item.className = 'column-status-item available';
                    item.setAttribute('data-column', header);
                    item.title = header;
                    item.textContent = header;
                    googleContainer.appendChild(item);
                });
                
                const totalCount = document.getElementById('google-total-count');
                if (totalCount) {
                    totalCount.textContent = this.googleHeaders.length;
                }
            } else {
                googleContainer.innerHTML = '<div class="column-status-item available">Завантажте заголовки</div>';
                const totalCount = document.getElementById('google-total-count');
                if (totalCount) {
                    totalCount.textContent = '0';
                }
            }
        }
        
        // Update mapping status for catalog columns
        this.updateCatalogColumnStatus();
    }
    
    /**
     * Update catalog column status based on current mappings
     */
    updateCatalogColumnStatus() {
        console.log('Updating column status...');
        
        const mappedCatalogColumns = {}; // Object to store catalog->google mappings
        const mappedGoogleColumns = []; // Array of used Google columns
        
        // Get currently mapped columns from the form
        const mappingRows = document.querySelectorAll('#column-mapping-rows .column-mapping-row');
        mappingRows.forEach(row => {
            const catalogSelect = row.querySelector('.catalog-column');
            const googleSelect = row.querySelector('.google-column');
            
            const catalogColumn = catalogSelect?.value;
            const googleColumn = googleSelect?.value;
            
            // Update row visual state
            this.updateMappingRowState(row, catalogColumn, googleColumn);
            
            // Only consider it mapped if BOTH catalog and google columns are selected
            if (catalogColumn && googleColumn) {
                mappedCatalogColumns[catalogColumn] = googleColumn;
                mappedGoogleColumns.push(googleColumn);
            }
        });
        
        console.log('Properly mapped catalog columns:', mappedCatalogColumns);
        console.log('Used Google columns:', mappedGoogleColumns);
        
        // Update catalog columns visual status
        let mappedCount = 0;
        const catalogItems = document.querySelectorAll('#catalog-columns-status .column-status-item');
        catalogItems.forEach(item => {
            const column = item.getAttribute('data-column');
            
            // Check if this catalog column has a Google Sheets mapping
            if (mappedCatalogColumns.hasOwnProperty(column)) {
                item.classList.remove('unmapped');
                item.classList.add('mapped');
                // Add visual indicator of which Google column it's mapped to
                item.setAttribute('data-mapped-to', '→ ' + mappedCatalogColumns[column]);
                mappedCount++;
            } else {
                item.classList.remove('mapped');
                item.classList.add('unmapped');
                // Remove mapping indicator
                item.removeAttribute('data-mapped-to');
            }
        });
        
        // Update Google Sheets columns visual status (only if we have them)
        if (this.googleHeaders && this.googleHeaders.length > 0) {
            const googleItems = document.querySelectorAll('#google-columns-status .column-status-item');
            googleItems.forEach(item => {
                const column = item.getAttribute('data-column');
                
                if (mappedGoogleColumns.indexOf(column) !== -1) {
                    item.classList.remove('available');
                    item.classList.add('mapped');
                } else {
                    item.classList.remove('mapped');
                    item.classList.add('available');
                }
            });
        }
        
        // Update counters
        const mappedCountElement = document.getElementById('catalog-mapped-count');
        if (mappedCountElement) {
            mappedCountElement.textContent = mappedCount;
        }
        
        // Simplified animation - only animate newly mapped items
        const mappedItems = document.querySelectorAll('.column-status-item.mapped');
        mappedItems.forEach(item => {
            if (!item.classList.contains('animated')) {
                item.classList.add('animated');
                item.style.transform = 'scale(1.05)';
                setTimeout(() => {
                    item.style.transform = 'scale(1)';
                }, 150);
            }
        });
        
        // Remove animated class from unmapped items
        const unmappedItems = document.querySelectorAll('.column-status-item.unmapped');
        unmappedItems.forEach(item => {
            item.classList.remove('animated');
        });
        
        console.log('Status update complete. Mapped count:', mappedCount);
    }
    
    /**
     * Update individual mapping row visual state
     */
    updateMappingRowState(row, catalogValue, googleValue) {
        // Remove existing state classes
        row.classList.remove('complete', 'incomplete');
        
        if (catalogValue && googleValue) {
            // Both selects have values - complete mapping
            row.classList.add('complete');
        } else if (catalogValue || googleValue) {
            // Only one select has value - incomplete mapping
            row.classList.add('incomplete');
        }
        // If neither has value, no special class needed
    }
    
    /**
     * Add mapping row
     */
    addMappingRow(e) {
        e.preventDefault();
        const rows = document.querySelectorAll('#column-mapping-rows .column-mapping-row');
        const index = rows.length;
        this.addMappingRowForColumn('', '', index);
    }
    
    /**
     * Get currently used Google columns
     */
    getUsedGoogleColumns(excludeRow = null) {
        const usedColumns = [];
        const mappingRows = document.querySelectorAll('#column-mapping-rows .column-mapping-row');
        
        mappingRows.forEach(row => {
            if (excludeRow && row === excludeRow) {
                return; // Skip the row we're currently editing
            }
            
            const googleSelect = row.querySelector('.google-column');
            const googleColumn = googleSelect?.value;
            
            if (googleColumn) {
                usedColumns.push(googleColumn);
            }
        });
        
        return usedColumns;
    }

    /**
     * Update all Google column selects to hide used options
     */
    updateGoogleColumnSelects() {
        const usedGoogleColumns = this.getUsedGoogleColumns();
        
        // Update all Google column selects
        const googleSelects = document.querySelectorAll('.google-column');
        googleSelects.forEach(select => {
            const currentValue = select.value;
            
            // Clear current options
            select.innerHTML = '<option value="">-- Оберіть стовпець --</option>';
            
            // Add options, hiding used ones (except current)
            this.googleHeaders.forEach(header => {
                const isUsed = usedGoogleColumns.includes(header);
                const isCurrent = header === currentValue;
                
                // Show option if it's not used OR if it's the current value
                if (!isUsed || isCurrent) {
                    const option = document.createElement('option');
                    option.value = header;
                    option.textContent = header;
                    if (isCurrent) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                }
            });
        });
    }
    
    /**
     * Update all catalog column selects to hide used options  
     */
    updateCatalogColumnSelects() {
        const usedCatalogColumns = this.getUsedCatalogColumns();
        
        // Update all catalog column selects
        const catalogSelects = document.querySelectorAll('.catalog-column');
        catalogSelects.forEach(select => {
            const currentValue = select.value;
            
            // Clear current options
            select.innerHTML = '<option value="">-- Оберіть поле --</option>';
            
            // Add options, hiding used ones (except current)
            this.catalogColumns.forEach(col => {
                const isUsed = usedCatalogColumns.includes(col.value);
                const isCurrent = col.value === currentValue;
                
                // Show option if it's not used OR if it's the current value
                if (!isUsed || isCurrent) {
                    const option = document.createElement('option');
                    option.value = col.value;
                    option.textContent = col.label;
                    if (isCurrent) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                }
            });
        });
    }

    /**
     * Get currently used catalog columns
     */
    getUsedCatalogColumns(excludeRow = null) {
        const usedColumns = [];
        const mappingRows = document.querySelectorAll('#column-mapping-rows .column-mapping-row');
        
        mappingRows.forEach(row => {
            if (excludeRow && row === excludeRow) {
                return; // Skip the row we're currently editing
            }
            
            const catalogSelect = row.querySelector('.catalog-column');
            const catalogColumn = catalogSelect?.value;
            
            if (catalogColumn) {
                usedColumns.push(catalogColumn);
            }
        });
        
        return usedColumns;
    }
    
    /**
     * Add mapping row for specific column
     */
    addMappingRowForColumn(catalogColumn, googleColumn, index) {
        // Get used columns to exclude from options
        const usedGoogleColumns = this.getUsedGoogleColumns();
        const usedCatalogColumns = this.getUsedCatalogColumns();

        // Google options HTML - exclude used columns unless it's the current value
        let googleOptionsHtml = '<option value="">-- Оберіть стовпець --</option>';
        if (this.googleHeaders.length > 0) {
            this.googleHeaders.forEach(header => {
                const isUsed = usedGoogleColumns.includes(header);
                const isCurrent = header === googleColumn;
                
                // Show option if it's not used OR if it's the current/selected value
                if (!isUsed || isCurrent) {
                    const selected = isCurrent ? 'selected' : '';
                    googleOptionsHtml += `<option value="${header}" ${selected}>${header}</option>`;
                }
            });
        }
        
        // Catalog options HTML - exclude used columns unless it's the current value
        let catalogOptionsHtml = '<option value="">-- Оберіть поле --</option>';
        this.catalogColumns.forEach(col => {
            const isUsed = usedCatalogColumns.includes(col.value);
            const isCurrent = col.value === catalogColumn;
            
            // Show option if it's not used OR if it's the current value
            if (!isUsed || isCurrent) {
                const selected = isCurrent ? 'selected' : '';
                catalogOptionsHtml += `<option value="${col.value}" ${selected}>${col.label}</option>`;
            }
        });
        
        const rowHtml = `
            <div class="column-mapping-row">
                <select class="column-mapping-select google-column" name="mappings[${index}][google_column]">
                    ${googleOptionsHtml}
                </select>
                <select class="column-mapping-select catalog-column" name="mappings[${index}][catalog_column]">
                    ${catalogOptionsHtml}
                </select>
                <button type="button" class="remove-mapping-btn">Видалити</button>
            </div>
        `;
        
        const container = document.getElementById('column-mapping-rows');
        if (container) {
            container.insertAdjacentHTML('beforeend', rowHtml);
        }
        
        // Update status after adding new row
        this.updateCatalogColumnStatus();
    }
    
    /**
     * Remove mapping row
     */
    removeMappingRow(e) {
        e.preventDefault();
        const row = e.target.closest('.column-mapping-row');
        if (row) {
            row.remove();
            // Update both Google and catalog selects after removing
            this.updateGoogleColumnSelects();
            this.updateCatalogColumnSelects();
            this.updateCatalogColumnStatus();
        }
    }
    
    /**
     * Save column mapping
     */
    async saveColumnMapping(e) {
        e.preventDefault();
        console.log('🔄 Save column mapping clicked');
        
        const btn = e.target;
        const catalogId = btn.getAttribute('data-catalog-id');
        console.log('📊 Catalog ID:', catalogId);
        
        if (!catalogId) {
            console.error('❌ No catalog ID found!');
            showMessage('Не вдалося визначити ID каталогу', 'error');
            return;
        }
        
        const mappings = [];
        
        // Collect mappings from form
        const mappingRows = document.querySelectorAll('#column-mapping-rows .column-mapping-row');
        console.log('📋 Found mapping rows:', mappingRows.length);
        
        mappingRows.forEach((row, index) => {
            const googleColumn = row.querySelector('.google-column')?.value;
            const catalogColumn = row.querySelector('.catalog-column')?.value;
            
            console.log(`Row ${index}: Google="${googleColumn}", Catalog="${catalogColumn}"`);
            
            if (googleColumn && catalogColumn) {
                mappings.push({
                    google_column: googleColumn,
                    catalog_column: catalogColumn
                });
            }
        });
        
        console.log('✅ Valid mappings collected:', mappings);
        console.log('📊 Final data to send:', {
            catalogId: catalogId,
            mappings: mappings
        });
        
        if (mappings.length === 0) {
            console.warn('⚠️ No valid mappings found');
            showMessage('Додайте хоча б одну відповідність стовпців', 'warning');
            return;
        }
        
        const originalText = btn.textContent;
        setButtonLoading(btn, true, originalText);
        
        try {
            console.log('🔄 Sending AJAX request...');
            const response = await this.api.saveColumnMapping(catalogId, mappings);
            console.log('✅ AJAX response:', response);
            
            showMessage('Відповідність стовпців збережено', 'success');
            this.updateCatalogColumnStatus();
            
            // Update import tab content after successful save
            this.updateImportTabContent(catalogId, mappings.length);
            
        } catch (error) {
            console.error('❌ Save mapping error:', error);
            showMessage('Помилка збереження: ' + error.message, 'error');
        } finally {
            setButtonLoading(btn, false, originalText);
        }
    }
    
    /**
     * Update import tab content after saving mappings
     */
    updateImportTabContent(catalogId, mappingsCount) {
        console.log('🔄 Updating import tab content...');
        
        const importTab = document.getElementById('tab-import');
        if (!importTab) {
            console.log('❌ Import tab not found');
            return;
        }
        
        // Find the import content container 
        const importContentContainer = document.getElementById('import-content-container');
        if (!importContentContainer) {
            console.log('❌ Import content container not found');
            return;
        }
        
        // Get current Google Sheets URL and sheet name
        const googleSheetUrl = document.getElementById('google_sheet_url')?.value || '';
        const sheetName = document.getElementById('sheet_name')?.value || 'Sheet1';
        
        console.log('✅ Found import content container, updating with new content...');
        
        // Always replace the entire content with the correct structure
        if (!googleSheetUrl) {
            // No Google Sheets URL case
            importContentContainer.innerHTML = `
                <div class="catalog-master-status warning">
                    Спочатку вкажіть URL Google Sheets в налаштуваннях.
                </div>
            `;
            console.log('ℹ️ Updated content: No Google Sheets URL');
        } else if (mappingsCount === 0) {
            // No mappings case  
            importContentContainer.innerHTML = `
                <div class="catalog-master-status warning">
                    Спочатку налаштуйте відповідність стовпців.
                </div>
            `;
            console.log('ℹ️ Updated content: No mappings');
        } else {
            // Has mappings - show import interface
            importContentContainer.innerHTML = `
                <div class="catalog-master-status info">
                    <strong>Google Sheets:</strong> ${googleSheetUrl}<br>
                    <strong>Аркуш:</strong> ${sheetName}<br>
                    <strong>Налаштовано відповідностей:</strong> ${mappingsCount}
                </div>
                
                <button type="button" id="import-data" class="button button-primary" data-catalog-id="${catalogId}">
                    Імпортувати дані
                </button>
            `;
            
            // Re-bind import button event if ImportManager exists
            const newImportBtn = document.getElementById('import-data');
            if (newImportBtn && this.state.app && this.state.app.components && this.state.app.components.import) {
                console.log('🔄 Re-binding import button event...');
                newImportBtn.addEventListener('click', (e) => {
                    this.state.app.components.import.importData(e);
                });
            }
            
            console.log('✅ Updated content: Import interface ready');
        }
        
        console.log('✅ Import tab content updated successfully');
    }
    
    /**
     * Load existing data for the catalog
     */
    async loadExistingData(catalogId) {
        if (!catalogId) {
            catalogId = this.state.currentCatalogId;
        }
        
        if (!catalogId) return;
        
        console.log('🔄 Loading existing column mapping data for catalog:', catalogId);
        
        try {
            // Load existing mappings from database
            const response = await this.api.getColumnMapping(catalogId);
            
            if (response.mappings && response.mappings.length > 0) {
                console.log('✅ Existing mappings found:', response.mappings);
                
                // Clear current mapping rows container
                const container = document.getElementById('column-mapping-rows');
                if (container) {
                    container.innerHTML = '';
                    
                    // Add rows for each existing mapping
                    response.mappings.forEach((mapping, index) => {
                        this.addMappingRowForColumn(mapping.catalog_column, mapping.google_column, index);
                    });
                    
                    // Update status after loading
                    this.updateCatalogColumnStatus();
                    
                    console.log('✅ Existing mappings loaded and displayed');
                }
            } else {
                console.log('ℹ️ No existing mappings found for catalog:', catalogId);
            }
        } catch (error) {
            console.error('❌ Error loading existing mappings:', error);
        }
    }
    
    /**
     * Destroy and cleanup
     */
    destroy() {
        // Remove event listeners
        const elements = [
            'get-sheets-headers',
            'test-sheets-connection', 
            'add-mapping-row',
            'save-column-mapping'
        ];
        
        elements.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.replaceWith(element.cloneNode(true));
            }
        });
        
        console.log('🗂️ Column Mapping Manager destroyed');
    }
} 