import { showMessage, setButtonLoading } from '../utils/helpers.js';

/**
 * Import Manager Component
 * Handles data import from Google Sheets
 */
export default class ImportManager {
    constructor(api, state) {
        this.api = api;
        this.state = state;
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
     * Import data from Google Sheets
     */
    async importData(e) {
        e.preventDefault();
        
        const btn = e.target;
        const catalogId = btn.getAttribute('data-catalog-id');
        const originalText = btn.textContent;
        
        setButtonLoading(btn, true, originalText);
        
        // Show progress indicator
        const progressContainer = this.createProgressIndicator();
        btn.parentNode.insertBefore(progressContainer, btn.nextSibling);
        
        // Animate progress
        setTimeout(() => {
            const progressFill = progressContainer.querySelector('.import-progress-fill');
            if (progressFill) {
                progressFill.style.width = '50%';
            }
        }, 500);
        
        try {
            const response = await this.api.importData(catalogId);
            
            // Complete progress animation
            const progressFill = progressContainer.querySelector('.import-progress-fill');
            if (progressFill) {
                progressFill.style.width = '100%';
            }
            
            setTimeout(() => {
                showMessage(response.message, 'success');
                
                // Hide "no data" message if it exists
                const noDataMessage = document.getElementById('no-data-message');
                if (noDataMessage) {
                    noDataMessage.style.display = 'none';
                    console.log('📥 Hidden "no data" message');
                }
                
                // Refresh DataTable if exists and is accessible via global state
                if (this.state.app && this.state.app.components && this.state.app.components.dataTable) {
                    console.log('📥 Reloading DataTable after import');
                    this.state.app.components.dataTable.reload();
                }
                
                // Update global state to trigger data refresh
                if (this.state.app && this.state.app.updateState) {
                    this.state.app.updateState('dataRefresh', true);
                }
                
                // Switch to data tab to show imported data
                const dataTabLink = document.querySelector('a[href="#tab-data"]');
                if (dataTabLink) {
                    setTimeout(() => {
                        console.log('📥 Switching to data tab to show imported data');
                        dataTabLink.click();
                    }, 500);
                }
                
                progressContainer.remove();
            }, 1000);
            
        } catch (error) {
            showMessage('Помилка імпорту', 'error');
            progressContainer.remove();
        } finally {
            setButtonLoading(btn, false, originalText);
        }
    }
    
    /**
     * Create progress indicator element
     */
    createProgressIndicator() {
        const progressContainer = document.createElement('div');
        progressContainer.className = 'import-progress';
        progressContainer.innerHTML = `
            <div>Імпорт даних з Google Sheets...</div>
            <div class="import-progress-bar">
                <div class="import-progress-fill"></div>
            </div>
        `;
        return progressContainer;
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