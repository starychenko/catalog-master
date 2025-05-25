import { showMessage, setButtonLoading } from '../utils/helpers.js';

/**
 * Import Manager Component
 * Handles data import from Google Sheets
 */
export default class ImportManager {
    constructor(api, state) {
        this.api = api;
        this.state = state;
        this.currentCatalogId = null;
        this.totalItemsToProcess = 0;
        this.totalItemsProcessed = 0;
        this.batchSize = 25; // Number of items to process per batch
        this.progressContainer = null;
    }
    
    init() {
        console.log('📥 Import Manager initialized');
        this.bindEvents();
    }
    
    /**
     * Bind event handlers
     */
    bindEvents() {
        const importBtn = document.getElementById('import-data');
        if (importBtn) {
            importBtn.addEventListener('click', (e) => this.importData(e));
        }
    }
    
    /**
     * Start the import process
     */
    async importData(e) {
        e.preventDefault();
        
        const btn = e.target;
        this.currentCatalogId = btn.getAttribute('data-catalog-id');
        const originalText = btn.textContent;
        
        setButtonLoading(btn, true, originalText);
        
        // Show progress indicator
        this.progressContainer = this.createProgressIndicator();
        btn.parentNode.insertBefore(this.progressContainer, btn.nextSibling);
        
        this.totalItemsToProcess = 0;
        this.totalItemsProcessed = 0;
        
        // Start with the first batch
        this.processBatch(0, originalText, btn);
    }

    /**
     * Process a single batch of data
     */
    async processBatch(offset, originalButtonText, buttonElement) {
        if (!this.currentCatalogId) {
            showMessage('Catalog ID not found.', 'error');
            this.cleanupImport(buttonElement, originalButtonText);
            return;
        }

        const isFirstBatch = offset === 0;
        
        try {
            const response = await this.api.importData(
                this.currentCatalogId,
                offset,
                this.batchSize,
                isFirstBatch
            );

            if (isFirstBatch && response.total_items_in_sheet !== undefined) {
                this.totalItemsToProcess = response.total_items_in_sheet;
            }

            this.totalItemsProcessed += response.processed_in_this_batch || 0;
            
            if (response.is_complete) {
                // Import finished
                this.updateProgressIndicator('', true);
                setTimeout(() => {
                    showMessage('Імпорт успішно завершено!', 'success');
                    this.cleanupImport(buttonElement, originalButtonText);
                    this.refreshDataAndSwitchTab();
                }, 1000);
            } else {
                // Update progress without server message (we format our own)
                this.updateProgressIndicator();
                // Process next batch
                this.processBatch(response.next_offset, originalButtonText, buttonElement);
            }
            
        } catch (error) {
            console.error('❌ Import error in ImportManager:', { message: error.message, stack: error.stack, errorObject: error });
            showMessage(`Помилка імпорту: ${error.message || 'Невідома помилка. Див. консоль.'}`, 'error');
            this.updateProgressIndicator(`Помилка: ${error.message}`, false, true);
            this.cleanupImport(buttonElement, originalButtonText);
        }
    }

    /**
     * Update progress indicator element
     */
    updateProgressIndicator(message = '', isComplete = false, isError = false) {
        if (!this.progressContainer) return;

        const progressText = this.progressContainer.querySelector('div:first-child');
        const progressFill = this.progressContainer.querySelector('.import-progress-fill');

        if (progressText) {
            if (this.totalItemsToProcess > 0 && !isError && !isComplete) {
                // Calculate batch info for better UX
                const currentBatch = this.totalItemsProcessed > 0 ? Math.ceil(this.totalItemsProcessed / this.batchSize) : 1;
                const totalBatches = Math.ceil(this.totalItemsToProcess / this.batchSize);
                
                progressText.textContent = `Імпорт: ${this.totalItemsProcessed} / ${this.totalItemsToProcess} товарів (батч ${currentBatch}/${totalBatches})`;
            } else if (isComplete && !isError) {
                progressText.textContent = `✅ Імпорт завершено! Оброблено ${this.totalItemsProcessed} товарів`;
            } else if (isError) {
                progressText.textContent = message;
            } else {
                // Initial state or other cases
                progressText.textContent = message || 'Ініціалізація імпорту...';
            }
        }

        if (progressFill) {
            let percentage = 0;
            if (this.totalItemsToProcess > 0 && !isError) {
                percentage = (this.totalItemsProcessed / this.totalItemsToProcess) * 100;
            }
            if (isComplete && !isError) percentage = 100;
            progressFill.style.width = `${Math.min(percentage, 100)}%`;
        }
    }
    
    /**
     * Create progress indicator element
     */
    createProgressIndicator() {
        const progressContainer = document.createElement('div');
        progressContainer.className = 'import-progress';
        progressContainer.innerHTML = `
            <div>Ініціалізація імпорту...</div>
            <div class="import-progress-bar">
                <div class="import-progress-fill"></div>
            </div>
        `;
        return progressContainer;
    }

    /**
     * Cleanup after import (success or failure)
     */
    cleanupImport(buttonElement, originalButtonText) {
        if (buttonElement) {
            setButtonLoading(buttonElement, false, originalButtonText);
        }
        // Optionally remove progress bar after a delay, or keep it if error
        // For now, let's remove it after 5s if not an error
        setTimeout(() => {
            if (this.progressContainer && !this.progressContainer.querySelector('div:first-child').textContent.toLowerCase().includes('помилка')) {
                 this.progressContainer.remove();
                 this.progressContainer = null;
            }
        }, 5000);
    }

    refreshDataAndSwitchTab() {
        if (this.state.app && this.state.app.components && this.state.app.components.dataTable) {
            this.state.app.components.dataTable.reload();
        }
        
        // Update export tab interface
        this.updateExportTabInterface();
        
        const dataTabLink = document.querySelector('a[href="#tab-data"]');
        if (dataTabLink) {
            setTimeout(() => dataTabLink.click(), 500);
        }
    }

    /**
     * Update export tab interface after import
     */
    async updateExportTabInterface() {
        try {
            const stats = await this.api.getCatalogStats(this.currentCatalogId);
            const itemsCount = stats.items_count || 0;
            
            // Update tab navigation with new count
            const dataTabLink = document.querySelector('a[href="#tab-data"]');
            if (dataTabLink) {
                dataTabLink.textContent = `Перегляд даних (${itemsCount})`;
            }
            
            // Update export tab content
            const exportTabContent = document.getElementById('tab-export');
            if (exportTabContent) {
                const exportCard = exportTabContent.querySelector('.catalog-master-card');
                if (exportCard) {
                    // Update export interface based on items count
                    if (itemsCount > 0) {
                        exportCard.innerHTML = `
                            <h3>Експорт даних</h3>
                            <div class="export-options">
                                <div class="export-option">
                                    <h4>CSV</h4>
                                    <p>Експорт в форматі CSV для використання в Excel та інших програмах</p>
                                    <button type="button" class="button button-primary export-btn" data-catalog-id="${this.currentCatalogId}" data-format="csv">
                                        Експортувати CSV
                                    </button>
                                </div>
                                
                                <div class="export-option">
                                    <h4>Excel</h4>
                                    <p>Експорт в форматі Excel (.xls)</p>
                                    <button type="button" class="button button-primary export-btn" data-catalog-id="${this.currentCatalogId}" data-format="excel">
                                        Експортувати Excel
                                    </button>
                                </div>
                                
                                <div class="export-option">
                                    <h4>JSON Feed</h4>
                                    <p>JSON фід для використання в API та інших системах</p>
                                    <button type="button" class="button button-primary export-btn" data-catalog-id="${this.currentCatalogId}" data-format="json">
                                        Створити JSON Feed
                                    </button>
                                </div>
                                
                                <div class="export-option">
                                    <h4>XML Feed</h4>
                                    <p>XML фід для використання в різних системах</p>
                                    <button type="button" class="button button-primary export-btn" data-catalog-id="${this.currentCatalogId}" data-format="xml">
                                        Створити XML Feed
                                    </button>
                                </div>
                            </div>
                        `;
                    } else {
                        exportCard.innerHTML = `
                            <h3>Експорт даних</h3>
                            <div class="catalog-master-status info">
                                В каталозі немає даних для експорту.
                            </div>
                        `;
                    }
                }
            }
            
            // Update settings overview if exists
            const settingsItemsCount = document.querySelector('.settings-overview-card .settings-card-value');
            if (settingsItemsCount && settingsItemsCount.textContent.match(/^\d/)) {
                settingsItemsCount.textContent = itemsCount.toLocaleString();
            }
            
            console.log('✅ Export tab interface updated with items count:', itemsCount);
            
        } catch (error) {
            console.error('❌ Error updating export interface:', error);
        }
    }
    
    /**
     * Handle state changes
     */
    onStateChange(key, value) {
        // Handle any state changes if needed
        if (key === 'currentCatalogId') {
            console.log('📥 Import Manager: Catalog changed to', value);
        }
    }
    
    /**
     * Destroy and cleanup
     */
    destroy() {
        const importBtn = document.getElementById('import-data');
        if (importBtn) {
            importBtn.replaceWith(importBtn.cloneNode(true));
        }
        
        console.log('📥 Import Manager destroyed');
    }
} 