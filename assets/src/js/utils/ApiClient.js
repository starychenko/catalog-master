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
            const httpResponse = await fetch(this.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });
            
            console.log('📡 Fetch response status:', httpResponse.status);
            
            if (!httpResponse.ok) {
                let errorText = `HTTP error! status: ${httpResponse.status}`;
                try {
                    const responseBody = await httpResponse.text();
                    errorText += ` - ${responseBody.substring(0, 200)}`; // Include part of the response
                } catch (e) {
                    // Ignore if can't read body
                }
                throw new Error(errorText);
            }
            
            let result;
            try {
                result = await httpResponse.json();
            } catch (e) {
                const responseText = await httpResponse.text().catch(() => "Could not read response text.");
                console.error('❌ AJAX Error: Response is not valid JSON.', { status: httpResponse.status, responseTextPreview: responseText.substring(0, 500) });
                throw new Error(`Server returned non-JSON response (status ${httpResponse.status}). Check console for response preview.`);
            }

            console.log('📥 AJAX Result:', result);
            
            // Enhanced logging for specific actions
            if (action === 'catalog_master_import_data' && result.data) {
                const data = result.data;
                console.log('📊 Import Progress:', {
                    processed_in_batch: data.processed_in_this_batch || 0,
                    total_in_sheet: data.total_items_in_sheet || 0,
                    next_offset: data.next_offset || 0,
                    is_complete: data.is_complete || false,
                    message: data.message || ''
                });
            } else if (action === 'catalog_master_get_sheets_headers' && result.data) {
                console.log('📋 Sheets Headers:', {
                    headers_count: result.data.headers ? result.data.headers.length : 0,
                    headers: result.data.headers || []
                });
            } else if (action === 'catalog_master_save_column_mapping' && result.data) {
                console.log('💾 Column Mapping Saved:', {
                    saved_count: result.data.saved_count || 0,
                    message: result.data.message || ''
                });
            } else if (action === 'catalog_master_get_column_mapping' && result.data) {
                console.log('📂 Column Mapping Loaded:', {
                    mappings_count: result.data.mappings ? result.data.mappings.length : 0,
                    mappings: result.data.mappings || []
                });
            }

            if (typeof result !== 'object' || result === null) {
                console.error('❌ AJAX Error: Parsed result is not an object.', { result });
                throw new Error('Invalid response structure from server.');
            }
            
            if (!result.hasOwnProperty('success')) {
                console.error('❌ AJAX Error: Response missing "success" property.', { result });
                throw new Error('Malformed response from server: missing "success" flag.');
            }
            
            if (!result.success) {
                const errorMessage = (typeof result.data === 'string' && result.data) ? result.data : 
                                     (result.data && typeof result.data.message === 'string' && result.data.message) ? result.data.message :
                                     'Unknown API error';
                throw new Error(errorMessage);
            }
            
            return result.data !== undefined ? result.data : {};
        } catch (error) {
            console.error('❌ AJAX Error in request method:', { action, error: error.message, stack: error.stack });
            if (error instanceof Error) {
                throw error;
            } else {
                throw new Error(String(error));
            }
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
     * Get existing column mapping
     */
    async getColumnMapping(catalogId) {
        return this.request('catalog_master_get_column_mapping', {
            catalog_id: catalogId
        });
    }
    
    /**
     * Import data from Google Sheets
     */
    async importData(catalogId, offset, batchSize, isFirstBatch) {
        return this.request('catalog_master_import_data', {
            catalog_id: catalogId,
            offset: offset,
            batch_size: batchSize,
            is_first_batch: isFirstBatch ? 1 : 0 // Send as 1 or 0
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
    
    /**
     * Update catalog item (for inline editing)
     */
    async updateCatalogItem(itemId, updateData) {
        return this.request('catalog_master_update_item', {
            item_id: itemId,
            ...updateData
        });
    }
    
    /**
     * Get catalog statistics (items count, mappings count)
     */
    async getCatalogStats(catalogId) {
        return this.request('catalog_master_get_catalog_stats', {
            catalog_id: catalogId
        });
    }
    
    /**
     * Clear Google Sheets cache for catalog
     */
    async clearCache(catalogId) {
        return this.request('catalog_master_clear_cache', {
            catalog_id: catalogId
        });
    }
} 