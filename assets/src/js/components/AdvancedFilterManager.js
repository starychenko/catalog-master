/**
 * Advanced Filter Manager for Catalog Master
 * Manages complex filtering with multiple conditions
 */

import { showMessage, debounce } from '../utils/helpers.js';

export default class AdvancedFilterManager {
    constructor(apiClient, tableManager) {
        this.api = apiClient;
        this.tableManager = tableManager;
        this.filters = [];
        this.filterId = 0;
        
        // Available filter operators
        this.operators = {
            'eq': { label: '= (дорівнює)', needsValue: true },
            'neq': { label: '<> (не дорівнює)', needsValue: true },
            'gt': { label: '> (більше)', needsValue: true },
            'gte': { label: '>= (більше або дорівнює)', needsValue: true },
            'lt': { label: '< (менше)', needsValue: true },
            'lte': { label: '<= (менше або дорівнює)', needsValue: true },
            'between': { label: 'між', needsValue: true, needsSecondValue: true },
            'is_null': { label: 'пусто', needsValue: false },
            'is_not_null': { label: 'не пусто', needsValue: false },
            'contains': { label: 'містить', needsValue: true },
            'not_contains': { label: 'не містить', needsValue: true }
        };
        
        // Available columns for filtering
        this.columns = {
            'id': { label: 'ID', type: 'number' },
            'product_id': { label: 'ID товару', type: 'text' },
            'product_name': { label: 'Назва товару', type: 'text' },
            'product_price': { label: 'Ціна', type: 'number' },
            'product_qty': { label: 'Кількість', type: 'number' },
            'product_image_url': { label: 'Зображення товару', type: 'text' },
            'product_sort_order': { label: 'Порядок товару', type: 'number' },
            'product_description': { label: 'Опис товару', type: 'text' },
            'category_id_1': { label: 'Категорія 1 ID', type: 'text' },
            'category_name_1': { label: 'Категорія 1', type: 'text' },
            'category_image_1': { label: 'Зображення категорії 1', type: 'text' },
            'category_sort_order_1': { label: 'Порядок категорії 1', type: 'number' },
            'category_id_2': { label: 'Категорія 2 ID', type: 'text' },
            'category_name_2': { label: 'Категорія 2', type: 'text' },
            'category_image_2': { label: 'Зображення категорії 2', type: 'text' },
            'category_sort_order_2': { label: 'Порядок категорії 2', type: 'number' },
            'category_id_3': { label: 'Категорія 3 ID', type: 'text' },
            'category_name_3': { label: 'Категорія 3', type: 'text' },
            'category_image_3': { label: 'Зображення категорії 3', type: 'text' },
            'category_sort_order_3': { label: 'Порядок категорії 3', type: 'number' }
        };
        
        this.init();
    }
    
    init() {
        const created = this.createFilterInterface();
        if (created) {
            this.bindEvents();
            console.log('🔍 AdvancedFilterManager initialized successfully');
        } else {
            // Try again after a short delay (table might be creating)
            setTimeout(() => {
                console.log('🔍 Retrying AdvancedFilterManager initialization...');
                this.retryInit();
            }, 500);
        }
    }
    
    /**
     * Retry initialization after table is created
     */
    retryInit() {
        const created = this.createFilterInterface();
        if (created) {
            this.bindEvents();
            console.log('🔍 AdvancedFilterManager initialized successfully on retry');
        } else {
            // Try one more time after longer delay
            setTimeout(() => {
                console.log('🔍 Final retry for AdvancedFilterManager initialization...');
                const finalCreated = this.createFilterInterface();
                if (finalCreated) {
                    this.bindEvents();
                    console.log('🔍 AdvancedFilterManager initialized successfully on final retry');
                } else {
                    console.warn('🔍 AdvancedFilterManager could not find table container after retries');
                }
            }, 1000);
        }
    }
    
    /**
     * Force re-initialization (called from table manager after table creation)
     */
    forceInit() {
        const created = this.createFilterInterface();
        if (created) {
            this.bindEvents();
            console.log('🔍 AdvancedFilterManager force-initialized after table creation');
            return true;
        }
        return false;
    }
    
    /**
     * Create the filter interface
     */
    createFilterInterface() {
        // First try to find the modern table container
        let container = document.querySelector('#modern-table-container');
        
        // If not found, try legacy selector
        if (!container) {
            container = document.querySelector('.catalog-table-container');
        }
        
        // If still not found, wait and try again
        if (!container) {
            console.log('🔍 Table container not found, will retry after table creation...');
            return false;
        }
        
        // Check if filter container already exists
        if (document.getElementById('advanced-filters')) {
            console.log('🔍 Advanced filters already exist');
            return true;
        }
        
        const filterHTML = `
            <div id="advanced-filters" class="advanced-filters">
                <div class="filter-header">
                    <div class="filter-header-left">
                        <h3>🔍 Розширені фільтри</h3>
                        <button type="button" class="btn btn-link" id="toggle-filters" title="Згорнути/Розгорнути">
                            <span class="toggle-icon">▼</span>
                        </button>
                    </div>
                    <div class="filter-actions">
                        <button type="button" class="btn btn-primary" id="add-filter">
                            ➕ Додати фільтр
                        </button>
                        <button type="button" class="btn btn-secondary" id="clear-filters">
                            🗑️ Очистити все
                        </button>
                        <button type="button" class="btn btn-success" id="apply-filters">
                            ✅ Застосувати
                        </button>
                    </div>
                </div>
                <div class="filters-container" id="filters-container">
                    <div class="no-filters-message">
                        Фільтри не додані. Натисніть "Додати фільтр" для початку роботи.
                    </div>
                </div>
                <div class="filter-summary" id="filter-summary"></div>
            </div>
        `;
        
        // Insert before the table controls or table wrapper
        const tableControls = container.querySelector('.table-controls');
        const tableWrapper = container.querySelector('.table-wrapper');
        
        if (tableControls) {
            // Insert before table controls
            tableControls.insertAdjacentHTML('beforebegin', filterHTML);
            console.log('🔍 Advanced filters created before table controls');
        } else if (tableWrapper) {
            // Insert before table wrapper as fallback
            tableWrapper.insertAdjacentHTML('beforebegin', filterHTML);
            console.log('🔍 Advanced filters created before table wrapper');
        } else {
            // Insert at the beginning of container as last resort
            container.insertAdjacentHTML('afterbegin', filterHTML);
            console.log('🔍 Advanced filters created at container beginning');
        }
        
        return true;
    }
    
    /**
     * Bind event handlers
     */
    bindEvents() {
        // Add filter button
        document.addEventListener('click', (e) => {
            if (e.target.id === 'add-filter') {
                this.addFilter();
            }
            
            if (e.target.id === 'clear-filters') {
                this.clearAllFilters();
            }
            
            if (e.target.id === 'apply-filters') {
                this.applyFilters();
            }
            
            if (e.target.classList.contains('remove-filter')) {
                this.removeFilter(e.target.dataset.filterId);
            }
            
            if (e.target.id === 'toggle-filters' || e.target.closest('#toggle-filters')) {
                this.toggleFilters();
            }
        });
        
        // Filter change events
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('filter-column') || 
                e.target.classList.contains('filter-operator')) {
                this.updateFilterRow(e.target.closest('.filter-row'));
            }
        });
    }
    
    /**
     * Add a new filter row
     */
    addFilter() {
        const filterId = ++this.filterId;
        const container = document.getElementById('filters-container');
        
        // Hide no-filters message
        const noFiltersMsg = container.querySelector('.no-filters-message');
        if (noFiltersMsg) {
            noFiltersMsg.style.display = 'none';
        }
        
        const filterRow = document.createElement('div');
        filterRow.className = 'filter-row';
        filterRow.dataset.filterId = filterId;
        
        filterRow.innerHTML = `
            <div class="filter-row-content">
                <div class="filter-logic">
                    ${this.filters.length > 0 ? 
                        `<select class="filter-logic-select">
                            <option value="AND">І</option>
                            <option value="OR">АБО</option>
                        </select>` : 
                        '<span class="first-filter">ДЕ</span>'
                    }
                </div>
                <div class="filter-column-select">
                    <select class="filter-column" required>
                        <option value="">Оберіть поле...</option>
                        ${Object.entries(this.columns).map(([key, col]) => 
                            `<option value="${key}">${col.label}</option>`
                        ).join('')}
                    </select>
                </div>
                <div class="filter-operator-select">
                    <select class="filter-operator" required>
                        <option value="">Оператор...</option>
                        ${Object.entries(this.operators).map(([key, op]) => 
                            `<option value="${key}">${op.label}</option>`
                        ).join('')}
                    </select>
                </div>
                <div class="filter-value-input">
                    <input type="text" class="filter-value" placeholder="Значення...">
                </div>
                <div class="filter-value2-input" style="display: none;">
                    <span class="between-label">до</span>
                    <input type="text" class="filter-value2" placeholder="Друге значення...">
                </div>
                <div class="filter-actions">
                    <button type="button" class="btn btn-danger btn-sm remove-filter" data-filter-id="${filterId}">
                        ❌
                    </button>
                </div>
            </div>
        `;
        
        container.appendChild(filterRow);
        
        // Add to internal filters array
        this.filters.push({
            id: filterId,
            column: '',
            operator: '',
            value: '',
            value2: '',
            logic: this.filters.length > 0 ? 'AND' : null
        });
        
        this.updateFilterSummary();
    }
    
    /**
     * Remove a filter
     */
    removeFilter(filterId) {
        const filterRow = document.querySelector(`[data-filter-id="${filterId}"]`);
        if (filterRow) {
            filterRow.remove();
        }
        
        // Remove from internal array
        this.filters = this.filters.filter(f => f.id !== parseInt(filterId));
        
        // Update logic for remaining filters
        this.updateFilterLogic();
        this.updateFilterSummary();
        
        // Show no-filters message if no filters left
        if (this.filters.length === 0) {
            const container = document.getElementById('filters-container');
            const noFiltersMsg = container.querySelector('.no-filters-message');
            if (noFiltersMsg) {
                noFiltersMsg.style.display = 'block';
            }
        }
    }
    
    /**
     * Update filter row based on column/operator selection
     */
    updateFilterRow(filterRow) {
        const filterId = parseInt(filterRow.dataset.filterId);
        const columnSelect = filterRow.querySelector('.filter-column');
        const operatorSelect = filterRow.querySelector('.filter-operator');
        const valueInput = filterRow.querySelector('.filter-value-input');
        const value2Input = filterRow.querySelector('.filter-value2-input');
        
        const column = columnSelect.value;
        const operator = operatorSelect.value;
        
        // Update internal filter object
        const filter = this.filters.find(f => f.id === filterId);
        if (filter) {
            filter.column = column;
            filter.operator = operator;
        }
        
        // Update input type based on column type
        if (column && this.columns[column]) {
            const valueField = filterRow.querySelector('.filter-value');
            const columnType = this.columns[column].type;
            
            if (columnType === 'number') {
                valueField.type = 'number';
                valueField.step = 'any';
            } else {
                valueField.type = 'text';
            }
        }
        
        // Show/hide second value for 'between' operator
        if (operator === 'between') {
            value2Input.style.display = 'flex';
        } else {
            value2Input.style.display = 'none';
        }
        
        // Show/hide value inputs for operators that don't need values
        if (operator && this.operators[operator]) {
            const needsValue = this.operators[operator].needsValue;
            valueInput.style.display = needsValue ? 'block' : 'none';
        }
        
        this.updateFilterSummary();
    }
    
    /**
     * Update logic operators for all filters
     */
    updateFilterLogic() {
        const filterRows = document.querySelectorAll('.filter-row');
        filterRows.forEach((row, index) => {
            const logicDiv = row.querySelector('.filter-logic');
            if (index === 0) {
                logicDiv.innerHTML = '<span class="first-filter">ДЕ</span>';
            } else {
                logicDiv.innerHTML = `
                    <select class="filter-logic-select">
                        <option value="AND">І</option>
                        <option value="OR">АБО</option>
                    </select>
                `;
            }
        });
    }
    
    /**
     * Clear all filters
     */
    clearAllFilters() {
        const container = document.getElementById('filters-container');
        container.innerHTML = `
            <div class="no-filters-message">
                Фільтри не додані. Натисніть "Додати фільтр" для початку роботи.
            </div>
        `;
        
        this.filters = [];
        this.filterId = 0;
        this.updateFilterSummary();
        
        // Reset table to show all data
        if (this.tableManager) {
            this.tableManager.clearFilters();
        }
    }
    
    /**
     * Apply filters to the table
     */
    async applyFilters() {
        // Validate and collect filter data
        const validFilters = this.collectFilterData();
        
        if (validFilters.length === 0) {
            showMessage('Додайте хоча б один фільтр для застосування', 'warning');
            return;
        }
        
        // Apply filters to table
        if (this.tableManager) {
            try {
                await this.tableManager.applyAdvancedFilters(validFilters);
                this.updateFilterSummary();
                showMessage(`Застосовано ${validFilters.length} фільтр(ів)`, 'success');
            } catch (error) {
                console.error('Filter application error:', error);
                showMessage('Помилка при застосуванні фільтрів: ' + error.message, 'error');
            }
        }
    }
    
    /**
     * Collect and validate filter data
     */
    collectFilterData() {
        const validFilters = [];
        const filterRows = document.querySelectorAll('.filter-row');
        
        filterRows.forEach((row, index) => {
            const filterId = parseInt(row.dataset.filterId);
            const column = row.querySelector('.filter-column').value;
            const operator = row.querySelector('.filter-operator').value;
            const value = row.querySelector('.filter-value').value;
            const value2 = row.querySelector('.filter-value2').value;
            const logic = index > 0 ? row.querySelector('.filter-logic-select')?.value || 'AND' : null;
            
            // Validate required fields
            if (!column || !operator) {
                return; // Skip invalid filters
            }
            
            // Check if value is required
            const operatorConfig = this.operators[operator];
            if (operatorConfig.needsValue && !value.trim()) {
                return; // Skip filters without required values
            }
            
            if (operatorConfig.needsSecondValue && !value2.trim()) {
                return; // Skip 'between' filters without second value
            }
            
            validFilters.push({
                id: filterId,
                column,
                operator,
                value: value.trim(),
                value2: value2.trim(),
                logic
            });
        });
        
        return validFilters;
    }
    
    /**
     * Update filter summary display
     */
    updateFilterSummary() {
        const summary = document.getElementById('filter-summary');
        if (!summary) return;
        
        const validFilters = this.collectFilterData();
        
        if (validFilters.length === 0) {
            summary.innerHTML = '';
            return;
        }
        
        const summaryText = validFilters.map((filter, index) => {
            const columnLabel = this.columns[filter.column]?.label || filter.column;
            const operatorLabel = this.operators[filter.operator]?.label || filter.operator;
            
            let valueText = '';
            if (filter.operator === 'between') {
                valueText = `${filter.value} та ${filter.value2}`;
            } else if (filter.operator === 'is_null' || filter.operator === 'is_not_null') {
                valueText = '';
            } else {
                valueText = filter.value;
            }
            
            const logicPrefix = index > 0 ? `${filter.logic} ` : '';
            
            return `${logicPrefix}<strong>${columnLabel}</strong> ${operatorLabel} ${valueText}`;
        }).join(' ');
        
        summary.innerHTML = `
            <div class="filter-summary-content">
                <strong>Активні фільтри:</strong> ${summaryText}
            </div>
        `;
    }
    
    /**
     * Toggle filters panel visibility
     */
    toggleFilters() {
        const container = document.getElementById('advanced-filters');
        const filtersContainer = document.getElementById('filters-container');
        const summaryContainer = document.getElementById('filter-summary');
        const toggleIcon = document.querySelector('.toggle-icon');
        
        if (!container || !filtersContainer || !toggleIcon) return;
        
        const isCollapsed = container.classList.contains('collapsed');
        
        if (isCollapsed) {
            // Expand
            container.classList.remove('collapsed');
            filtersContainer.style.display = 'block';
            if (summaryContainer) summaryContainer.style.display = 'block';
            toggleIcon.textContent = '▼';
            console.log('🔍 Advanced filters expanded');
        } else {
            // Collapse
            container.classList.add('collapsed');
            filtersContainer.style.display = 'none';
            if (summaryContainer) summaryContainer.style.display = 'none';
            toggleIcon.textContent = '▶';
            console.log('🔍 Advanced filters collapsed');
        }
    }
} 