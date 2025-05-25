/**
 * Catalog Master - Main Application Entry Point
 * Modern WordPress plugin for managing product catalogs with Google Sheets XLSX integration
 * @author Yevhenii Starychenko
 */

import './styles/main.scss';

// Core modules
import CatalogMaster from './js/core/CatalogMaster.js';
import ColumnMappingManager from './js/components/ColumnMappingManager.js';
import TabManager from './js/components/TabManager.js';
import ExportManager from './js/components/ExportManager.js';
import ImportManager from './js/components/ImportManager.js';

// Utils
import { showMessage, debounce } from './js/utils/helpers.js';
import ApiClient from './js/utils/ApiClient.js';

// Initialize application when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Dev mode indicator
    console.log('🚀 Catalog Master v1.2.1 - Vite Edition 2025 UPDATED!');
    
    // Initialize only on Catalog Master admin pages
    if (document.querySelector('.catalog-master-admin')) {
        
        // Get configuration from WordPress localized script
        const config = window.catalog_master_vite_params || {};
        
        console.log('📋 WordPress config received:', {
            ajaxUrl: config.ajax_url,
            nonce: config.nonce
        });
        
        // Initialize application with proper config
        const app = new CatalogMaster({
            version: '1.2.1',
            debug: window.catalogMasterConfig?.debug || false,
            ajax_url: config.ajax_url,
            nonce: config.nonce,
            plugin_url: config.plugin_url
        });
        
        // Initialize the app
        app.init();
        
        // Make globally available for debugging
        window.catalogMaster = app;
    }
});

// Export for global access if needed
window.CatalogMaster = CatalogMaster; 