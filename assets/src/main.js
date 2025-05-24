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

console.log('🚀 Catalog Master v1.1.0 - Vite Edition 2025 UPDATED!');

// Boot application when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 NEW CODE: Catalog Master Vite Edition initializing...');
    
    // Check if catalog_master_vite_params exists
    if (typeof catalog_master_vite_params !== 'undefined') {
        console.log('✅ NEW CODE: catalog_master_vite_params found:', catalog_master_vite_params);
        
        const catalogMaster = new CatalogMaster(catalog_master_vite_params);
        catalogMaster.init();
        
        // Make it globally accessible for debugging
        window.catalogMaster = catalogMaster;
        
        console.log('✅ NEW CODE: Catalog Master initialized successfully');
    } else {
        console.error('❌ NEW CODE: catalog_master_vite_params not found - check WordPress localization');
    }
});

// Export for global access if needed
window.CatalogMaster = CatalogMaster; 