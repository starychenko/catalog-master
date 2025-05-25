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
        
        // Column definitions
        this.columns = [
            { key: 'product_id', title: 'ID', width: '60px', sortable: true, type: 'number' },
            { key: 'product_name', title: 'Назва товару', width: '200px', sortable: true, type: 'text' },
            { key: 'product_price', title: 'Ціна', width: '100px', sortable: true, type: 'currency' },
            { key: 'product_qty', title: 'Кіл-ть', width: '80px', sortable: true, type: 'number' },
            { key: 'product_image_url', title: 'Зображення', width: '100px', sortable: false, type: 'image' },
            { key: 'product_sort_order', title: 'Порядок', width: '80px', sortable: true, type: 'number' },
            { key: 'product_description', title: 'Опис', width: '200px', sortable: false, type: 'text' },
            { key: 'category_name_1', title: 'Категорія 1', width: '150px', sortable: true, type: 'text' },
            { key: 'category_name_2', title: 'Категорія 2', width: '150px', sortable: true, type: 'text' },
            { key: 'category_name_3', title: 'Категорія 3', width: '150px', sortable: true, type: 'text' },
            { key: 'actions', title: 'Дії', width: '120px', sortable: false, type: 'actions' }
        ];
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
        
        // Sorting
        const table = document.getElementById('data-table');
        if (table) {
            table.addEventListener('click', (e) => {
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
            });
        }
        
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
                ${this.columns.map(col => `
                    <td data-column="${col.key}" class="column-${col.type}">
                        ${this.renderCell(item, col)}
                    </td>
                `).join('')}
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
                        <button type="button" class="btn-edit" data-id="${item.id}" title="Редагувати">
                            ✏️
                        </button>
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
        const tbody = document.getElementById('table-body');
        if (!tbody) return;
        
        tbody.addEventListener('click', (e) => {
            const editBtn = e.target.closest('.btn-edit');
            const deleteBtn = e.target.closest('.btn-delete');
            
            if (editBtn) {
                const itemId = editBtn.getAttribute('data-id');
                this.editItem(itemId);
            }
            
            if (deleteBtn) {
                const itemId = deleteBtn.getAttribute('data-id');
                this.deleteItem(itemId);
            }
        });
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
    
    editItem(itemId) {
        console.log('📊 Edit item:', itemId);
        // TODO: Implement edit functionality
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
     * Cleanup
     */
    destroy() {
        if (this.tableContainer) {
            this.tableContainer.remove();
        }
        console.log('📊 Modern Table Manager destroyed');
    }
} 