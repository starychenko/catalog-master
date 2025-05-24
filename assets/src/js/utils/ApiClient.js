/**
 * API Client for WordPress AJAX requests
 * Handles all AJAX communication with the backend
 */
export default class ApiClient {
    constructor(ajaxUrl, nonce) {
        this.ajaxUrl = ajaxUrl;
        this.nonce = nonce;
        this.errorHandlers = [];
        
        console.log('🌐 API Client constructor called with:', {
            ajaxUrl: ajaxUrl,
            nonce: nonce ? nonce.substring(0, 8) + '...' : 'undefined'
        });
        
        if (!ajaxUrl) {
            console.error('❌ API Client: ajaxUrl is undefined!');
        }
    }
    
    /**
     * Add error handler
     */
    onError(handler) {
        this.errorHandlers.push(handler);
    }
    
    /**
     * Trigger error handlers
     */
    triggerError(error) {
        this.errorHandlers.forEach(handler => handler(error));
    }
    
    /**
     * Make AJAX request
     */
    async request(action, data = {}) {
        console.log(`🌐 AJAX Request: ${action}`, data);
        
        const formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', this.nonce);
        
        // Add data to FormData
        Object.keys(data).forEach(key => {
            if (Array.isArray(data[key])) {
                data[key].forEach((item, index) => {
                    if (typeof item === 'object') {
                        Object.keys(item).forEach(subKey => {
                            formData.append(`${key}[${index}][${subKey}]`, item[subKey]);
                        });
                    } else {
                        formData.append(`${key}[${index}]`, item);
                    }
                });
            } else {
                formData.append(key, data[key]);
            }
        });
        
        console.log('📤 FormData contents:');
        for (let pair of formData.entries()) {
            console.log(`  ${pair[0]}: ${pair[1]}`);
        }
        
        try {
            const response = await fetch(this.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });
            
            console.log('📡 Fetch response status:', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            console.log('📥 AJAX Result:', result);
            
            if (!result.success) {
                throw new Error(result.data || 'Unknown error');
            }
            
            return result.data;
        } catch (error) {
            console.error('❌ AJAX Error:', error);
            throw error;
        }
    }
    
    /**
     * Test Google Sheets connection
     */
    async testSheetsConnection(sheetUrl, sheetName) {
        return this.request('catalog_master_test_sheets_connection', {
            sheet_url: sheetUrl,
            sheet_name: sheetName
        });
    }
    
    /**
     * Get Google Sheets headers
     */
    async getSheetsHeaders(sheetUrl, sheetName) {
        return this.request('catalog_master_get_sheets_headers', {
            sheet_url: sheetUrl,
            sheet_name: sheetName
        });
    }
    
    /**
     * Save column mapping
     */
    async saveColumnMapping(catalogId, mappings) {
        return this.request('catalog_master_save_column_mapping', {
            catalog_id: catalogId,
            mappings: mappings
        });
    }
    
    /**
     * Import data from Google Sheets
     */
    async importData(catalogId) {
        return this.request('catalog_master_import_data', {
            catalog_id: catalogId
        });
    }
    
    /**
     * Export data
     */
    async exportData(catalogId, format) {
        return this.request('catalog_master_export', {
            catalog_id: catalogId,
            format: format
        });
    }
    
    /**
     * Get catalog data for DataTable
     */
    async getCatalogData(catalogId, params) {
        return this.request('catalog_master_get_catalog_data', {
            catalog_id: catalogId,
            ...params
        });
    }
    
    /**
     * Delete catalog item
     */
    async deleteItem(itemId) {
        return this.request('catalog_master_delete_item', {
            item_id: itemId
        });
    }
    
    /**
     * Save catalog item
     */
    async saveItem(catalogId, itemData) {
        return this.request('catalog_master_save_item', {
            catalog_id: catalogId,
            ...itemData
        });
    }
} 