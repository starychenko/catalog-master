import { showMessage } from '../utils/helpers.js';

/**
 * Export Manager Component
 * Handles data export in various formats
 */
export default class ExportManager {
    constructor(api) {
        this.api = api;
    }
    
    init() {
        console.log('📤 Export Manager initialized');
        this.bindEvents();
    }
    
    /**
     * Bind event handlers
     */
    bindEvents() {
        // Delegate events for export buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('export-btn')) {
                this.exportData(e);
            }
        });
    }
    
    /**
     * Export data in specified format
     */
    async exportData(e) {
        e.preventDefault();
        
        const btn = e.target;
        const catalogId = btn.getAttribute('data-catalog-id');
        const format = btn.getAttribute('data-format');
        
        if (!catalogId || !format) {
            showMessage('Недостатньо параметрів для експорту', 'error');
            return;
        }
        
        try {
            const response = await this.api.exportData(catalogId, format);
            
            // Determine which URL to open based on format
            let urlToOpen;
            let isFileDownload = false;
            
            if (format === 'csv' || format === 'excel') {
                // Files for download - use download URL
                urlToOpen = response.download_url;
                isFileDownload = true;
            } else if (format === 'json' || format === 'xml') {
                // Web feeds - use feed URL
                urlToOpen = response.feed_url;
                isFileDownload = false;
            } else {
                // Fallback - use download URL
                urlToOpen = response.download_url;
                isFileDownload = true;
            }
            
            // Open appropriate URL
            window.open(urlToOpen);
            
            // Show feed URL for reference (always useful)
            this.showExportResults(response, isFileDownload);
            
        } catch (error) {
            showMessage('Помилка експорту', 'error');
        }
    }
    
    /**
     * Show export results with URLs
     */
    showExportResults(response, isFileDownload) {
        // Remove previous status messages of this type
        const existingStatus = document.querySelector('.catalog-master-status.info');
        if (existingStatus) {
            existingStatus.remove();
        }
        
        let feedHtml = '<div class="catalog-master-status info">' +
            '<strong>Feed URL:</strong> <a href="' + response.feed_url + '" target="_blank">' + response.feed_url + '</a>';
        
        // For file downloads, also show download URL
        if (isFileDownload && response.download_url) {
            feedHtml += '<br><strong>Download URL:</strong> <a href="' + response.download_url + '" target="_blank">' + response.download_url + '</a>';
        }
        
        feedHtml += '</div>';
        
        // Insert after export options
        const exportOptions = document.querySelector('.export-options');
        if (exportOptions) {
            exportOptions.insertAdjacentHTML('afterend', feedHtml);
        }
    }
    
    /**
     * Get supported export formats
     */
    getSupportedFormats() {
        return ['csv', 'excel', 'json', 'xml'];
    }
    
    /**
     * Check if format is supported
     */
    isFormatSupported(format) {
        return this.getSupportedFormats().includes(format);
    }
    
    /**
     * Destroy and cleanup
     */
    destroy() {
        // Remove delegated event listeners by replacing document
        // (this is handled globally, no specific cleanup needed for delegates)
        console.log('📤 Export Manager destroyed');
    }
} 