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
            this.updateProgressIndicator(response.message);

            if (response.is_complete) {
                // Import finished
                this.updateProgressIndicator('Імпорт завершено!', true);
                setTimeout(() => {
                    showMessage(response.message || 'Імпорт успішно завершено.', 'success');
                    this.cleanupImport(buttonElement, originalButtonText);
                    this.refreshDataAndSwitchTab();
                }, 1000);
            } else {
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
            if (this.totalItemsToProcess > 0 && !isError) {
                progressText.textContent = `Обробка: ${this.totalItemsProcessed} / ${this.totalItemsToProcess}. ${message}`;
            } else {
                progressText.textContent = message;
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
        const dataTabLink = document.querySelector('a[href="#tab-data"]');
        if (dataTabLink) {
            setTimeout(() => dataTabLink.click(), 500);
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