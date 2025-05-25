/**
 * Modern Table Manager Component
 * Custom table component without external dependencies
 * Features: AJAX pagination, search, sorting, responsive design
 */
export default class ModernTableManager {
    constructor(api, state) {
        this.api = api;
        this.state = state;
        this.tableContainer = null;
        this.currentPage = 1;
        this.pageSize = 25;
        this.totalItems = 0;
        this.totalPages = 0;
        this.searchQuery = '';
        this.sortColumn = 'product_id';
        this.sortDirection = 'asc';
        this.isLoading = false;
        this.data = [];
        this.activeFilters = []; // Store active advanced filters
        this.advancedFilters = null; // Reference to AdvancedFilterManager
        this.lastPresetOrder = null; // Store preset column order
        this.rowActionsAttached = false; // Track if row actions are already attached
        
        // Column definitions - Full set
        this.allColumns = [
            { key: 'id', title: 'ID', width: '60px', sortable: true, type: 'number', group: 'system' },
            { key: 'product_id', title: 'Product ID', width: '80px', sortable: true, type: 'text', group: 'product' },
            { key: 'product_name', title: 'Назва товару', width: '200px', sortable: true, type: 'text', group: 'product' },
            { key: 'product_price', title: 'Ціна', width: '100px', sortable: true, type: 'currency', group: 'product' },
            { key: 'product_qty', title: 'Кількість', width: '80px', sortable: true, type: 'number', group: 'product' },
            { key: 'product_image_url', title: 'Зображення', width: '100px', sortable: false, type: 'image', group: 'product' },
            { key: 'product_sort_order', title: 'Порядок товару', width: '90px', sortable: true, type: 'number', group: 'product' },
            { key: 'product_description', title: 'Опис', width: '200px', sortable: false, type: 'text', group: 'product' },
            
            { key: 'category_id_1', title: 'Cat ID 1', width: '80px', sortable: true, type: 'text', group: 'category1' },
            { key: 'category_name_1', title: 'Категорія 1', width: '150px', sortable: true, type: 'text', group: 'category1' },
            { key: 'category_image_1', title: 'Зобр. Кат. 1', width: '90px', sortable: false, type: 'image', group: 'category1' },
            { key: 'category_sort_order_1', title: 'Пор. Кат. 1', width: '90px', sortable: true, type: 'number', group: 'category1' },
            
            { key: 'category_id_2', title: 'Cat ID 2', width: '80px', sortable: true, type: 'text', group: 'category2' },
            { key: 'category_name_2', title: 'Категорія 2', width: '150px', sortable: true, type: 'text', group: 'category2' },
            { key: 'category_image_2', title: 'Зобр. Кат. 2', width: '90px', sortable: false, type: 'image', group: 'category2' },
            { key: 'category_sort_order_2', title: 'Пор. Кат. 2', width: '90px', sortable: true, type: 'number', group: 'category2' },
            
            { key: 'category_id_3', title: 'Cat ID 3', width: '80px', sortable: true, type: 'text', group: 'category3' },
            { key: 'category_name_3', title: 'Категорія 3', width: '150px', sortable: true, type: 'text', group: 'category3' },
            { key: 'category_image_3', title: 'Зобр. Кат. 3', width: '90px', sortable: false, type: 'image', group: 'category3' },
            { key: 'category_sort_order_3', title: 'Пор. Кат. 3', width: '90px', sortable: true, type: 'number', group: 'category3' },
            
            { key: 'actions', title: 'Дії', width: '80px', sortable: false, type: 'actions', group: 'system' }
        ];
        
        // Default visible columns (compact view)
        this.defaultVisibleColumns = [
            'id', 'actions', 'product_id', 'product_name', 'product_price', 'product_qty', 
            'product_image_url', 'category_name_1', 'category_name_2', 'category_name_3'
        ];
        
        // Set initial columns to default visible in the specified order
        this.columns = this.defaultVisibleColumns.map(key => 
            this.allColumns.find(col => col.key === key)
        ).filter(col => col !== undefined);
    }
    
    init() {
        console.log('📊 Modern Table Manager initialized');
        this.createTableStructure();
        this.bindEvents();
        this.loadData();
    }
    
    /**
     * Create table HTML structure
     */
    createTableStructure() {
        const catalogId = this.getCatalogId();
        const container = document.getElementById('catalog-items-table');
        
        if (!container) {
            console.error('📊 Table container not found');
            return;
        }
        
        // Reset row actions flag since we're creating a new table structure
        this.rowActionsAttached = false;
        
        // Replace old table with modern container
        container.outerHTML = `
            <div id="modern-table-container" class="modern-table-container" data-catalog-id="${catalogId}">
                <!-- Table Controls -->
                <div class="table-controls">
                    <div class="table-controls-left">
                        <div class="items-per-page">
                            <label>Показати:</label>
                            <select id="page-size-select">
                                <option value="10">10</option>
                                <option value="25" selected>25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                            <span>записів</span>
                        </div>
                    </div>
                    
                    <div class="table-controls-right">
                        <button type="button" id="column-settings" class="btn btn-secondary" title="Налаштування стовпців">
                            ⚙️ Стовпці
                        </button>
                        <div class="search-container">
                            <input type="text" id="table-search" placeholder="Пошук..." />
                            <button type="button" id="clear-search" title="Очистити">✕</button>
                        </div>
                    </div>
                </div>
                
                <!-- Table Wrapper -->
                <div class="table-wrapper">
                    <div class="table-loading" id="table-loading">
                        <div class="loading-spinner"></div>
                        <span>Завантаження...</span>
                    </div>
                    
                    <table class="modern-table" id="data-table">
                        <thead>
                            <tr>
                                ${this.columns.map(col => `
                                    <th data-column="${col.key}" data-sortable="${col.sortable}" class="${col.sortable ? 'sortable' : ''}" style="width: ${col.width}">
                                        ${col.title}
                                        ${col.sortable ? '<span class="sort-indicator">↕</span>' : ''}
                                    </th>
                                `).join('')}
                            </tr>
                        </thead>
                        <tbody id="table-body">
                            <!-- Data will be loaded here -->
                        </tbody>
                    </table>
                    
                    <div class="table-empty" id="table-empty" style="display: none;">
                        <div class="empty-icon">📊</div>
                        <h3>Немає даних</h3>
                        <p>Імпортуйте дані з Google Sheets щоб вони з'явилися тут</p>
                    </div>
                </div>
                
                <!-- Pagination -->
                <div class="table-pagination" id="table-pagination">
                    <div class="pagination-info">
                        <span id="pagination-info">Показано 0-0 з 0 записів</span>
                    </div>
                    
                    <div class="pagination-controls">
                        <button type="button" id="first-page" disabled>⟪</button>
                        <button type="button" id="prev-page" disabled>⟨</button>
                        <span class="page-numbers" id="page-numbers"></span>
                        <button type="button" id="next-page" disabled>⟩</button>
                        <button type="button" id="last-page" disabled>⟫</button>
                    </div>
                </div>
            </div>
        `;
        
        this.tableContainer = document.getElementById('modern-table-container');
        console.log('📊 Modern table structure created');
        
        // Try to initialize advanced filters now that table structure exists
        if (this.advancedFilters) {
            setTimeout(() => {
                this.advancedFilters.forceInit();
            }, 100);
        }
    }
    
    /**
     * Bind event handlers
     */
    bindEvents() {
        if (!this.tableContainer) return;
        
        // Search
        const searchInput = document.getElementById('table-search');
        const clearSearch = document.getElementById('clear-search');
        
        if (searchInput) {
            searchInput.addEventListener('input', this.debounce((e) => {
                this.searchQuery = e.target.value;
                this.currentPage = 1;
                this.loadData();
            }, 300));
        }
        
        if (clearSearch) {
            clearSearch.addEventListener('click', () => {
                searchInput.value = '';
                this.searchQuery = '';
                this.currentPage = 1;
                this.loadData();
            });
        }
        
        // Page size
        const pageSizeSelect = document.getElementById('page-size-select');
        if (pageSizeSelect) {
            pageSizeSelect.addEventListener('change', (e) => {
                this.pageSize = parseInt(e.target.value);
                this.currentPage = 1;
                this.loadData();
            });
        }
        
        // Column settings
        const columnSettings = document.getElementById('column-settings');
        if (columnSettings) {
            columnSettings.addEventListener('click', () => {
                this.showColumnSettings();
            });
        }
        
        // Sorting
        this.bindSortingEvents();
        
        // Scroll detection for sticky header shadow
        const tableWrapper = document.querySelector('.table-wrapper');
        if (tableWrapper) {
            tableWrapper.addEventListener('scroll', () => {
                if (tableWrapper.scrollTop > 0) {
                    tableWrapper.classList.add('scrolled');
                } else {
                    tableWrapper.classList.remove('scrolled');
                }
            });
            
            console.log('📌 Sticky header scroll detection enabled');
        }
        
        // Pagination
        this.bindPaginationEvents();
    }
    
    /**
     * Bind pagination events
     */
    bindPaginationEvents() {
        const firstPage = document.getElementById('first-page');
        const prevPage = document.getElementById('prev-page');
        const nextPage = document.getElementById('next-page');
        const lastPage = document.getElementById('last-page');
        
        if (firstPage) firstPage.addEventListener('click', () => this.goToPage(1));
        if (prevPage) prevPage.addEventListener('click', () => this.goToPage(this.currentPage - 1));
        if (nextPage) nextPage.addEventListener('click', () => this.goToPage(this.currentPage + 1));
        if (lastPage) lastPage.addEventListener('click', () => this.goToPage(this.totalPages));
    }
    
    /**
     * Load data from API
     */
    async loadData() {
        if (this.isLoading) return;
        
        this.isLoading = true;
        this.showLoading(true);
        
        const catalogId = this.getCatalogId();
        if (!catalogId) {
            console.error('📊 No catalog ID found');
            this.showLoading(false);
            return;
        }
        
        try {
            const params = {
                catalog_id: catalogId,
                page: this.currentPage,
                page_size: this.pageSize,
                search: this.searchQuery,
                sort_column: this.sortColumn,
                sort_direction: this.sortDirection,
                filters: this.activeFilters // Add advanced filters
            };
            
            console.log('📊 Loading table data:', params);
            const response = await this.api.getCatalogData(catalogId, params);
            
            this.data = response.data || [];
            this.totalItems = response.total || 0;
            this.totalPages = Math.ceil(this.totalItems / this.pageSize);
            
            this.renderTable();
            this.updatePagination();
            
            console.log(`📊 Loaded ${this.data.length} items (${this.totalItems} total)`);
            
        } catch (error) {
            console.error('📊 Error loading table data:', error);
            this.showError('Помилка завантаження даних');
        } finally {
            this.isLoading = false;
            this.showLoading(false);
        }
    }
    
    /**
     * Render table rows
     */
    renderTable() {
        const tbody = document.getElementById('table-body');
        const emptyDiv = document.getElementById('table-empty');
        
        if (!tbody) return;
        
        if (this.data.length === 0) {
            tbody.innerHTML = '';
            if (emptyDiv) emptyDiv.style.display = 'block';
            return;
        }
        
        if (emptyDiv) emptyDiv.style.display = 'none';
        
        tbody.innerHTML = this.data.map(item => `
            <tr data-id="${item.id}">
                ${this.columns.map(col => {
                    const editableColumns = [
                        'product_id', 'product_name', 'product_price', 'product_qty', 'product_sort_order', 'product_description',
                        'category_id_1', 'category_id_2', 'category_id_3', 
                        'category_name_1', 'category_name_2', 'category_name_3',
                        'category_sort_order_1', 'category_sort_order_2', 'category_sort_order_3'
                    ];
                    const isEditable = editableColumns.includes(col.key);
                    return `
                        <td data-column="${col.key}" class="column-${col.type}${isEditable ? ' editable' : ''}" ${isEditable ? 'title="Подвійний клік для редагування"' : ''}>
                            ${this.renderCell(item, col)}
                        </td>
                    `;
                }).join('')}
            </tr>
        `).join('');
        
        // Bind row actions
        this.bindRowActions();
    }
    
    /**
     * Render individual cell content
     */
    renderCell(item, column) {
        const value = item[column.key] || '';
        
        switch (column.type) {
            case 'currency':
                return value ? `${parseFloat(value).toFixed(2)} грн` : '';
                
            case 'image':
                return value ? `<img src="${value}" alt="Image" class="table-image" onerror="this.style.display='none'">` : '';
                
            case 'actions':
                return `
                    <div class="table-actions">
                        <button type="button" class="btn-delete" data-id="${item.id}" title="Видалити">
                            🗑️
                        </button>
                    </div>
                `;
                
            default:
                return this.truncateText(value, 50);
        }
    }
    
    /**
     * Bind row action events
     */
    bindRowActions() {
        // Only attach events once to prevent multiple handlers
        if (this.rowActionsAttached) {
            return;
        }
        
        const tbody = document.getElementById('table-body');
        if (!tbody) return;
        
        tbody.addEventListener('click', (e) => {
            const deleteBtn = e.target.closest('.btn-delete');
            
            if (deleteBtn) {
                const itemId = deleteBtn.getAttribute('data-id');
                this.deleteItem(itemId);
            }
        });
        
        // Click on image cells for image upload
        tbody.addEventListener('click', (e) => {
            const cell = e.target.closest('td');
            if (!cell) return;
            
            const column = cell.getAttribute('data-column');
            const row = cell.closest('tr');
            const itemId = row.getAttribute('data-id');
            
            // Check if this is an image column
            const imageColumns = ['product_image_url', 'category_image_1', 'category_image_2', 'category_image_3'];
            if (imageColumns.includes(column)) {
                e.preventDefault();
                this.showImageUploadDialog(itemId, column, cell);
                return;
            }
        });
        
        // Double-click for inline editing
        tbody.addEventListener('dblclick', (e) => {
            const cell = e.target.closest('td');
            if (!cell) return;
            
            const column = cell.getAttribute('data-column');
            const row = cell.closest('tr');
            const itemId = row.getAttribute('data-id');
            
            // Skip actions column and image columns (they have their own click handler)
            const imageColumns = ['product_image_url', 'category_image_1', 'category_image_2', 'category_image_3'];
            if (column === 'actions' || imageColumns.includes(column) || column === 'id') {
                return;
            }
            
            // Don't start edit if already editing
            if (cell.classList.contains('editing')) {
                return;
            }
            
            this.startInlineEdit(cell, itemId, column);
        });
        
        // Mark as attached
        this.rowActionsAttached = true;
        console.log('📊 Row actions attached once');
    }
    
    /**
     * Update pagination controls
     */
    updatePagination() {
        // Update info
        const start = this.totalItems === 0 ? 0 : (this.currentPage - 1) * this.pageSize + 1;
        const end = Math.min(this.currentPage * this.pageSize, this.totalItems);
        
        const infoElement = document.getElementById('pagination-info');
        if (infoElement) {
            infoElement.textContent = `Показано ${start}-${end} з ${this.totalItems} записів`;
        }
        
        // Update buttons
        const firstPage = document.getElementById('first-page');
        const prevPage = document.getElementById('prev-page');
        const nextPage = document.getElementById('next-page');
        const lastPage = document.getElementById('last-page');
        
        if (firstPage) firstPage.disabled = this.currentPage <= 1;
        if (prevPage) prevPage.disabled = this.currentPage <= 1;
        if (nextPage) nextPage.disabled = this.currentPage >= this.totalPages;
        if (lastPage) lastPage.disabled = this.currentPage >= this.totalPages;
        
        // Update page numbers
        this.updatePageNumbers();
    }
    
    /**
     * Update page number buttons
     */
    updatePageNumbers() {
        const pageNumbers = document.getElementById('page-numbers');
        if (!pageNumbers) return;
        
        const startPage = Math.max(1, this.currentPage - 2);
        const endPage = Math.min(this.totalPages, this.currentPage + 2);
        
        let html = '';
        
        for (let i = startPage; i <= endPage; i++) {
            html += `
                <button type="button" class="page-number ${i === this.currentPage ? 'active' : ''}" data-page="${i}">
                    ${i}
                </button>
            `;
        }
        
        pageNumbers.innerHTML = html;
        
        // Bind page number clicks
        pageNumbers.addEventListener('click', (e) => {
            const pageBtn = e.target.closest('.page-number');
            if (pageBtn) {
                const page = parseInt(pageBtn.getAttribute('data-page'));
                this.goToPage(page);
            }
        });
    }
    
    /**
     * Go to specific page
     */
    goToPage(page) {
        if (page < 1 || page > this.totalPages || page === this.currentPage) return;
        
        this.currentPage = page;
        this.loadData();
    }
    
    /**
     * Update sort indicators
     */
    updateSortIndicators() {
        const headers = document.querySelectorAll('th[data-sortable="true"]');
        headers.forEach(th => {
            const indicator = th.querySelector('.sort-indicator');
            const column = th.getAttribute('data-column');
            
            if (indicator) {
                if (column === this.sortColumn) {
                    indicator.textContent = this.sortDirection === 'asc' ? '↑' : '↓';
                    indicator.className = 'sort-indicator active';
                } else {
                    indicator.textContent = '↕';
                    indicator.className = 'sort-indicator';
                }
            }
        });
    }
    
    /**
     * Show/hide loading state
     */
    showLoading(show) {
        const loading = document.getElementById('table-loading');
        if (loading) {
            loading.style.display = show ? 'flex' : 'none';
        }
    }
    
    /**
     * Show error message
     */
    showError(message) {
        const tbody = document.getElementById('table-body');
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="${this.columns.length}" class="table-error">
                        <div class="error-icon">⚠️</div>
                        <div class="error-message">${message}</div>
                    </td>
                </tr>
            `;
        }
    }
    
    /**
     * Utility functions
     */
    getCatalogId() {
        // First check if we have the old table element (before replacement)
        const oldTable = document.getElementById('catalog-items-table');
        if (oldTable && oldTable.getAttribute('data-catalog-id')) {
            return oldTable.getAttribute('data-catalog-id');
        }
        
        // Then check the new container (after replacement)
        const container = this.tableContainer || document.getElementById('modern-table-container');
        if (container && container.getAttribute('data-catalog-id')) {
            return container.getAttribute('data-catalog-id');
        }
        
        // Fallback: try to get from URL
        const urlParams = new URLSearchParams(window.location.search);
        const catalogId = urlParams.get('id');
        if (catalogId) {
            return catalogId;
        }
        
        console.error('📊 Could not determine catalog ID');
        return null;
    }
    
    truncateText(text, maxLength) {
        if (!text || text.length <= maxLength) return text;
        return text.substring(0, maxLength) + '...';
    }
    
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    /**
     * Public methods
     */
    reload() {
        console.log('📊 Reloading table data');
        this.loadData();
    }
    

    
    async deleteItem(itemId) {
        if (!confirm('Ви впевнені, що хочете видалити цей запис?')) return;
        
        try {
            await this.api.deleteItem(itemId);
            this.loadData(); // Reload after delete
            console.log('📊 Item deleted:', itemId);
        } catch (error) {
            console.error('📊 Error deleting item:', error);
        }
    }
    
    /**
     * Handle state changes
     */
    onStateChange(key, value) {
        if (key === 'dataRefresh' && value === true) {
            this.reload();
        }
    }
    
    /**
     * Set reference to AdvancedFilterManager
     */
    setAdvancedFilters(advancedFilters) {
        this.advancedFilters = advancedFilters;
        console.log('📊 ModernTableManager received AdvancedFilters reference');
    }
    
    /**
     * Apply advanced filters to the table
     */
    async applyAdvancedFilters(filters) {
        this.activeFilters = filters;
        this.currentPage = 1; // Reset to first page
        await this.loadData();
    }
    
    /**
     * Clear all filters
     */
    async clearFilters() {
        this.activeFilters = [];
        this.currentPage = 1;
        await this.loadData();
    }
    
    /**
     * Show column settings modal
     */
    showColumnSettings() {
        // Remove existing modal if present
        const existingModal = document.getElementById('column-settings-modal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Create modal
        const modal = document.createElement('div');
        modal.id = 'column-settings-modal';
        modal.className = 'column-settings-modal';
        
        // Group columns by category
        const groups = {
            system: { title: 'Системні', columns: [] },
            product: { title: 'Товар', columns: [] },
            category1: { title: 'Категорія 1', columns: [] },
            category2: { title: 'Категорія 2', columns: [] },
            category3: { title: 'Категорія 3', columns: [] }
        };
        
        this.allColumns.forEach(col => {
            if (col.group && groups[col.group]) {
                groups[col.group].columns.push(col);
            }
        });
        
        let modalContent = `
            <div class="column-settings-content">
                <div class="column-settings-header">
                    <h3>Налаштування стовпців</h3>
                    <button type="button" class="close-modal">&times;</button>
                </div>
                <div class="column-settings-body">
                    <div class="preset-buttons">
                        <button type="button" class="preset-btn" data-preset="compact">📱 Компактний</button>
                        <button type="button" class="preset-btn" data-preset="full">📊 Повний</button>
                        <button type="button" class="preset-btn" data-preset="product">📦 Тільки товари</button>
                    </div>
                    <div class="column-groups">
        `;
        
        Object.entries(groups).forEach(([groupKey, group]) => {
            if (group.columns.length > 0) {
                modalContent += `
                    <div class="column-group">
                        <h4>${group.title}</h4>
                        <div class="column-checkboxes">
                `;
                
                group.columns.forEach(col => {
                    const isVisible = this.columns.some(c => c.key === col.key);
                    modalContent += `
                        <label class="column-checkbox">
                            <input type="checkbox" value="${col.key}" ${isVisible ? 'checked' : ''}>
                            <span>${col.title}</span>
                        </label>
                    `;
                });
                
                modalContent += `
                        </div>
                    </div>
                `;
            }
        });
        
        modalContent += `
                    </div>
                </div>
                <div class="column-settings-footer">
                    <button type="button" class="btn btn-secondary cancel-btn">Скасувати</button>
                    <button type="button" class="btn btn-primary apply-btn">Застосувати</button>
                </div>
            </div>
        `;
        
        modal.innerHTML = modalContent;
        document.body.appendChild(modal);
        
        // Bind events
        modal.querySelector('.close-modal').addEventListener('click', () => modal.remove());
        modal.querySelector('.cancel-btn').addEventListener('click', () => modal.remove());
        modal.querySelector('.apply-btn').addEventListener('click', () => this.applyColumnSettings(modal));
        
        // Preset buttons
        modal.querySelectorAll('.preset-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const preset = e.target.dataset.preset;
                this.applyColumnPreset(modal, preset);
            });
        });
        
        // Close on outside click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });
    }
    
    /**
     * Apply column preset
     */
    applyColumnPreset(modal, preset) {
        const checkboxes = modal.querySelectorAll('input[type="checkbox"]');
        
        let visibleColumns = [];
        switch (preset) {
            case 'compact':
                visibleColumns = ['id', 'actions', 'product_id', 'product_name', 'product_price', 'product_qty', 'product_image_url', 'category_name_1', 'category_name_2', 'category_name_3'];
                break;
            case 'full':
                visibleColumns = this.allColumns.map(col => col.key);
                break;
            case 'product':
                visibleColumns = ['id', 'product_id', 'product_name', 'product_price', 'product_qty', 
                               'product_image_url', 'product_sort_order', 'product_description', 'actions'];
                break;
        }
        
        checkboxes.forEach(checkbox => {
            checkbox.checked = visibleColumns.includes(checkbox.value);
        });
        
        // Store preset order for later use
        this.lastPresetOrder = visibleColumns;
    }
    
    /**
     * Apply column settings
     */
    applyColumnSettings(modal) {
        const checkboxes = modal.querySelectorAll('input[type="checkbox"]:checked');
        const selectedColumns = Array.from(checkboxes).map(cb => cb.value);
        
        // Update visible columns with correct order
        if (this.lastPresetOrder && this.lastPresetOrder.length > 0) {
            // Use preset order if available
            this.columns = this.lastPresetOrder
                .filter(key => selectedColumns.includes(key))
                .map(key => this.allColumns.find(col => col.key === key))
                .filter(col => col !== undefined);
            
            // Add any newly selected columns that weren't in the preset
            const remainingColumns = selectedColumns.filter(key => !this.lastPresetOrder.includes(key));
            remainingColumns.forEach(key => {
                const col = this.allColumns.find(c => c.key === key);
                if (col) this.columns.push(col);
            });
        } else {
            // Use default order from allColumns
            this.columns = this.allColumns.filter(col => selectedColumns.includes(col.key));
        }
        
        // Clear preset order after use
        this.lastPresetOrder = null;
        
        // Update table headers and structure
        this.updateTableStructure();
        
        // Reload data with new columns
        this.loadData();
        
        // Close modal
        modal.remove();
        
        console.log('📊 Updated visible columns:', selectedColumns);
    }
    
    /**
     * Update table structure without recreating the entire container
     */
    updateTableStructure() {
        const table = document.getElementById('data-table');
        if (!table) return;
        
        // Update table headers
        const thead = table.querySelector('thead tr');
        if (thead) {
            thead.innerHTML = this.columns.map(col => `
                <th data-column="${col.key}" data-sortable="${col.sortable}" class="${col.sortable ? 'sortable' : ''}" style="width: ${col.width}">
                    ${col.title}
                    ${col.sortable ? '<span class="sort-indicator">↕</span>' : ''}
                </th>
            `).join('');
            
            console.log('📊 Updated table headers for', this.columns.length, 'columns');
        }
        
        // Clear tbody - will be refilled by loadData()
        const tbody = document.getElementById('table-body');
        if (tbody) {
            tbody.innerHTML = '';
        }
        
        // Update sort indicators if we have current sorting
        this.updateSortIndicators();
        
        // Re-bind sorting events for new headers
        this.bindSortingEvents();
    }
    
    /**
     * Bind sorting events specifically for table headers
     */
    bindSortingEvents() {
        const table = document.getElementById('data-table');
        if (!table) return;
        
        // Remove existing sorting listeners (if any)
        const existingHandler = table._sortingHandler;
        if (existingHandler) {
            table.removeEventListener('click', existingHandler);
        }
        
        // Create and bind new sorting handler
        const sortingHandler = (e) => {
            const th = e.target.closest('th[data-sortable="true"]');
            if (th) {
                const column = th.getAttribute('data-column');
                if (column === this.sortColumn) {
                    this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    this.sortColumn = column;
                    this.sortDirection = 'asc';
                }
                this.updateSortIndicators();
                this.loadData();
            }
        };
        
        table.addEventListener('click', sortingHandler);
        table._sortingHandler = sortingHandler; // Store reference for cleanup
        
        console.log('📊 Sorting events re-bound to updated headers');
    }
    
    /**
     * Start inline editing of a cell
     */
    startInlineEdit(cell, itemId, column) {
        // Check if column is editable
        const editableColumns = [
            'product_id', 'product_name', 'product_price', 'product_qty', 'product_sort_order', 'product_description',
            'category_id_1', 'category_id_2', 'category_id_3', 
            'category_name_1', 'category_name_2', 'category_name_3',
            'category_sort_order_1', 'category_sort_order_2', 'category_sort_order_3'
        ];
        if (!editableColumns.includes(column)) {
            return;
        }
        
        // Prevent multiple edits
        if (cell.querySelector('.inline-edit-input')) {
            return;
        }
        
        const currentValue = this.getCellValue(itemId, column);
        const columnConfig = this.columns.find(col => col.key === column);
        
        // Create input element
        const input = document.createElement('input');
        input.className = 'inline-edit-input';
        input.value = currentValue;
        input.dataset.itemId = itemId;
        input.dataset.column = column;
        input.dataset.originalValue = currentValue;
        
        // Set input type based on column type
        if (columnConfig.type === 'number' || columnConfig.type === 'currency') {
            input.type = 'number';
            input.step = 'any';
        } else {
            input.type = 'text';
        }
        
        // Store original content
        const originalContent = cell.innerHTML;
        cell.dataset.originalContent = originalContent;
        
        // Replace cell content with input
        cell.innerHTML = '';
        cell.appendChild(input);
        cell.classList.add('editing');
        
        // Focus and select
        input.focus();
        input.select();
        
        // Bind events
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.saveInlineEdit(cell, input);
            } else if (e.key === 'Escape') {
                e.preventDefault();
                this.cancelInlineEdit(cell);
            }
        });
        
        input.addEventListener('blur', () => {
            // Small delay to allow click events to process
            setTimeout(() => {
                if (document.activeElement !== input) {
                    this.saveInlineEdit(cell, input);
                }
            }, 100);
        });
        
        console.log(`📝 Started editing ${column} for item ${itemId}`);
    }
    
    /**
     * Save inline edit changes
     */
    async saveInlineEdit(cell, input) {
        const itemId = input.dataset.itemId;
        const column = input.dataset.column;
        const newValue = input.value.trim();
        const originalValue = input.dataset.originalValue;
        
        // Check if value changed
        if (newValue === originalValue) {
            this.cancelInlineEdit(cell);
            return;
        }
        
        // Show saving state
        input.disabled = true;
        cell.classList.add('saving');
        
        try {
            // Call API to update the item
            const updateData = {};
            updateData[column] = newValue;
            
            await this.api.updateCatalogItem(itemId, updateData);
            
            // Update local data
            const dataItem = this.data.find(item => item.id == itemId);
            if (dataItem) {
                dataItem[column] = newValue;
            }
            
            // Restore cell with new content
            const columnConfig = this.columns.find(col => col.key === column);
            cell.innerHTML = this.renderCell(dataItem, columnConfig);
            cell.classList.remove('editing', 'saving');
            cell.classList.add('updated');
            
            // Remove updated class after animation
            setTimeout(() => {
                cell.classList.remove('updated');
            }, 2000);
            
            console.log(`✅ Updated ${column} for item ${itemId}: ${originalValue} → ${newValue}`);
            
        } catch (error) {
            console.error('📊 Error saving inline edit:', error);
            
            // Show error state
            cell.classList.add('error');
            input.disabled = false;
            input.focus();
            
            // Remove error class after delay
            setTimeout(() => {
                cell.classList.remove('error');
            }, 3000);
            
            alert('Помилка збереження: ' + error.message);
        } finally {
            cell.classList.remove('saving');
        }
    }
    
    /**
     * Cancel inline edit
     */
    cancelInlineEdit(cell) {
        const originalContent = cell.dataset.originalContent;
        if (originalContent) {
            cell.innerHTML = originalContent;
        }
        cell.classList.remove('editing', 'saving', 'error');
        delete cell.dataset.originalContent;
        
        console.log('❌ Cancelled inline edit');
    }
    
    /**
     * Get cell value from data
     */
    getCellValue(itemId, column) {
        const item = this.data.find(item => item.id == itemId);
        if (!item) return '';
        
        const value = item[column] || '';
        
        // For currency, return just the number
        if (column === 'product_price' && value) {
            return parseFloat(value).toString();
        }
        
        return value.toString();
    }
    
    /**
     * Show image upload dialog
     */
    showImageUploadDialog(itemId, column, cell) {
        const catalogId = this.getCatalogId();
        if (!catalogId) {
            console.error('📊 No catalog ID for image upload');
            return;
        }
        
        // Remove existing modal
        const existingModal = document.getElementById('image-upload-modal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Create and show modal
        const modal = this.createImageUploadModal(catalogId, itemId, column, cell);
        document.body.appendChild(modal);
        
        console.log('📊 Image upload dialog shown for:', { itemId, column });
    }
    
    /**
     * Create image upload modal
     */
    createImageUploadModal(catalogId, itemId, column, cell) {
        const modal = document.createElement('div');
        modal.id = 'image-upload-modal';
        modal.className = 'image-upload-modal';
        
        // Determine column title for display
        const columnConfig = this.allColumns.find(col => col.key === column);
        const columnTitle = columnConfig ? columnConfig.title : column;
        
        modal.innerHTML = `
            <div class="image-upload-content">
                <div class="image-upload-header">
                    <h3>Завантаження зображення</h3>
                    <p>Стовпець: <strong>${columnTitle}</strong></p>
                    <button type="button" class="close-modal">&times;</button>
                </div>
                
                <div class="image-upload-body">
                    <div class="upload-zone" id="upload-zone">
                        <div class="upload-icon">📷</div>
                        <div class="upload-text">
                            <p><strong>Перетягніть зображення сюди</strong></p>
                            <p>або <button type="button" class="btn-browse">Оберіть файл</button></p>
                        </div>
                        <input type="file" id="file-input" accept="image/*" style="display: none;">
                    </div>
                    
                    <div class="upload-info">
                        <h4>Важливо:</h4>
                        <ul>
                            <li>✅ Зображення буде автоматично конвертовано в JPG</li>
                            <li>✅ Розмір буде змінено до 1000x1000 пікселів</li>
                            <li>✅ Стара фотографія буде автоматично видалена</li>
                            <li>✅ Підтримувані формати: JPG, PNG, GIF, WebP, BMP</li>
                        </ul>
                    </div>
                    
                    <div class="upload-progress" id="upload-progress" style="display: none;">
                        <div class="progress-bar">
                            <div class="progress-fill" id="progress-fill"></div>
                        </div>
                        <p id="progress-text">Завантаження...</p>
                    </div>
                </div>
                
                <div class="image-upload-footer">
                    <button type="button" class="btn btn-secondary cancel-btn">Скасувати</button>
                </div>
            </div>
        `;
        
        // Bind events
        this.bindImageUploadEvents(modal, catalogId, itemId, column, cell);
        
        return modal;
    }
    
    /**
     * Bind events for image upload modal
     */
    bindImageUploadEvents(modal, catalogId, itemId, column, cell) {
        const fileInput = modal.querySelector('#file-input');
        const uploadZone = modal.querySelector('#upload-zone');
        const browseBtn = modal.querySelector('.btn-browse');
        const closeBtn = modal.querySelector('.close-modal');
        const cancelBtn = modal.querySelector('.cancel-btn');
        
        // Close events
        closeBtn.addEventListener('click', () => modal.remove());
        cancelBtn.addEventListener('click', () => modal.remove());
        
        // Browse button
        browseBtn.addEventListener('click', () => fileInput.click());
        
        // File input change
        fileInput.addEventListener('change', (e) => {
            const files = e.target.files;
            if (files.length > 0) {
                this.handleImageUpload(files[0], catalogId, itemId, column, cell, modal);
            }
        });
        
        // Drag and drop events
        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('drag-over');
        });
        
        uploadZone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            // Only remove drag-over if we're leaving the upload zone entirely
            if (!uploadZone.contains(e.relatedTarget)) {
                uploadZone.classList.remove('drag-over');
            }
        });
        
        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('drag-over');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                // Check if it's an image
                const file = files[0];
                if (file.type.startsWith('image/')) {
                    this.handleImageUpload(file, catalogId, itemId, column, cell, modal);
                } else {
                    alert('Будь ласка, оберіть файл зображення');
                }
            }
        });
        
        // Close on outside click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });
    }
    
    /**
     * Handle image upload
     */
    async handleImageUpload(file, catalogId, itemId, column, cell, modal) {
        console.log('🖼️ Starting image upload:', {
            fileName: file.name,
            fileSize: file.size,
            fileType: file.type
        });
        
        // Validate file
        if (!file.type.startsWith('image/')) {
            alert('Будь ласка, оберіть файл зображення');
            return;
        }
        
        // Check file size (max 50MB)
        if (file.size > 50 * 1024 * 1024) {
            alert('Файл занадто великий. Максимальний розмір: 50MB');
            return;
        }
        
        // Show progress
        const progressDiv = modal.querySelector('#upload-progress');
        const progressFill = modal.querySelector('#progress-fill');
        const progressText = modal.querySelector('#progress-text');
        const uploadZone = modal.querySelector('#upload-zone');
        
        uploadZone.style.display = 'none';
        progressDiv.style.display = 'block';
        progressText.textContent = 'Завантаження та обробка зображення...';
        
        // Animate progress bar (fake progress for user feedback)
        let progress = 0;
        const progressInterval = setInterval(() => {
            progress += Math.random() * 15;
            if (progress > 90) progress = 90;
            progressFill.style.width = progress + '%';
        }, 200);
        
        try {
            const result = await this.api.uploadImage(catalogId, itemId, column, file);
            
            // Complete progress
            clearInterval(progressInterval);
            progressFill.style.width = '100%';
            progressText.textContent = 'Успішно завантажено!';
            
            // Update table cell with new image
            this.updateImageCell(cell, result.image_url, column);
            
            // Update local data
            const dataItem = this.data.find(item => item.id == itemId);
            if (dataItem) {
                dataItem[column] = result.image_url;
            }
            
            console.log('✅ Image uploaded successfully:', result);
            
            // Close modal after short delay
            setTimeout(() => {
                modal.remove();
            }, 1000);
            
        } catch (error) {
            clearInterval(progressInterval);
            
            // Show error
            progressText.textContent = 'Помилка: ' + error.message;
            progressFill.style.width = '0%';
            progressFill.style.backgroundColor = '#dc3545';
            
            console.error('❌ Image upload failed:', error);
            
            // Show upload zone again after delay
            setTimeout(() => {
                uploadZone.style.display = 'block';
                progressDiv.style.display = 'none';
                progressFill.style.backgroundColor = '#007cba';
            }, 3000);
        }
    }
    
    /**
     * Update image cell with new image URL
     */
    updateImageCell(cell, imageUrl, column) {
        const columnConfig = this.allColumns.find(col => col.key === column);
        if (columnConfig && columnConfig.type === 'image') {
            if (imageUrl) {
                cell.innerHTML = `<img src="${imageUrl}" alt="Image" class="table-image" onerror="this.style.display='none'">`;
            } else {
                cell.innerHTML = '';
            }
            
            // Add visual feedback
            cell.classList.add('updated');
            setTimeout(() => {
                cell.classList.remove('updated');
            }, 2000);
        }
    }
    
    /**
     * Cleanup
     */
    destroy() {
        if (this.tableContainer) {
            this.tableContainer.remove();
        }
        
        // Remove any open modals
        const imageModal = document.getElementById('image-upload-modal');
        if (imageModal) {
            imageModal.remove();
        }
        
        // Reset row actions flag
        this.rowActionsAttached = false;
        
        console.log('📊 Modern Table Manager destroyed');
    }
} 