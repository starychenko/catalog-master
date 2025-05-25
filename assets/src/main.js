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
    console.log('🚀 Catalog Master v1.1.5 - Vite Edition 2025 UPDATED!');
    
    // Initialize only on Catalog Master admin pages
    if (document.querySelector('.catalog-master-admin')) {
        const app = new CatalogMaster({
            version: '1.1.5',
            debug: window.catalogMasterConfig?.debug || false
        });
        
        // Make globally available for debugging
        window.catalogMaster = app;
    }
});

// Export for global access if needed
window.CatalogMaster = CatalogMaster; 