import ModernTableManager from '../components/ModernTableManager.js';
import ColumnMappingManager from '../components/ColumnMappingManager.js';
import TabManager from '../components/TabManager.js';
import ExportManager from '../components/ExportManager.js';
import ImportManager from '../components/ImportManager.js';
import ApiClient from '../utils/ApiClient.js';
import { showMessage } from '../utils/helpers.js';

/**
 * Main Catalog Master Application Class
 * Manages all components and application state
 */
export default class CatalogMaster {
    constructor(config) {
        this.config = config;
        this.state = {
            currentCatalogId: null,
            googleHeaders: [],
            mappings: [],
            app: null // Will be set to this after initialization
        };
        
        // Initialize API client
        this.api = new ApiClient(config.ajax_url, config.nonce);
        
        console.log('🔗 API Client initialized with:', {
            ajaxUrl: config.ajax_url,
            nonce: config.nonce ? config.nonce.substring(0, 8) + '...' : 'undefined'
        });
        
        // Component managers
        this.components = {};
    }
    
    /**
     * Initialize the application
     */
    init() {
        console.log('🔧 Initializing Catalog Master...');
        
        // Set app reference in state for component communication
        this.state.app = this;
        
        this.initializeComponents();
        this.bindGlobalEvents();
        this.loadInitialData();
        
        console.log('✅ Catalog Master initialized successfully');
    }
    
    /**
     * Initialize all component managers
     */
    initializeComponents() {
        // Initialize tab management with config for settings functionality
        this.components.tabs = new TabManager(this.config);
        
        // Initialize column mapping if on edit page
        if (this.isEditPage()) {
            this.components.columnMapping = new ColumnMappingManager(this.api, this.state);
            this.components.import = new ImportManager(this.api, this.state);
        }
        
        // Initialize modern table if catalog items table exists
        if (document.getElementById('catalog-items-table')) {
            this.components.dataTable = new ModernTableManager(this.api, this.state);
        }
        
        // Initialize export manager
        this.components.export = new ExportManager(this.api);
        
        // Initialize each component
        Object.values(this.components).forEach(component => {
            if (component.init) {
                component.init();
            }
        });
    }
    
    /**
     * Bind global application events
     */
    bindGlobalEvents() {
        // Global error handling
        window.addEventListener('unhandledrejection', (event) => {
            console.error('Unhandled promise rejection:', event.reason);
            showMessage('Виникла неочікувана помилка', 'error');
        });
        
        // Global AJAX error handling
        this.api.onError((error) => {
            console.error('API Error:', error);
            showMessage(error.message || 'Помилка API запиту', 'error');
        });
    }
    
    /**
     * Load initial data for the current page
     */
    loadInitialData() {
        const catalogId = this.getCurrentCatalogId();
        if (catalogId) {
            this.state.currentCatalogId = catalogId;
            this.loadCatalogData(catalogId);
        }
    }
    
    /**
     * Load catalog-specific data
     */
    async loadCatalogData(catalogId) {
        try {
            // Load existing mappings and headers if on edit page
            if (this.isEditPage() && this.components.columnMapping) {
                await this.components.columnMapping.loadExistingData(catalogId);
            }
        } catch (error) {
            console.error('Error loading catalog data:', error);
        }
    }
    
    /**
     * Check if current page is catalog edit page
     */
    isEditPage() {
        return window.location.href.includes('catalog-master-edit');
    }
    
    /**
     * Get current catalog ID from URL or DOM
     */
    getCurrentCatalogId() {
        const urlParams = new URLSearchParams(window.location.search);
        const catalogId = urlParams.get('id');
        
        if (catalogId) {
            return parseInt(catalogId);
        }
        
        // Fallback: check data attribute on table
        const table = document.getElementById('catalog-items-table');
        if (table) {
            return parseInt(table.dataset.catalogId);
        }
        
        return null;
    }
    
    /**
     * Update global state
     */
    updateState(key, value) {
        this.state[key] = value;
        
        // Notify components of state change
        Object.values(this.components).forEach(component => {
            if (component.onStateChange) {
                component.onStateChange(key, value);
            }
        });
    }
    
    /**
     * Get current state
     */
    getState(key = null) {
        return key ? this.state[key] : this.state;
    }
    
    /**
     * Destroy application and cleanup
     */
    destroy() {
        Object.values(this.components).forEach(component => {
            if (component.destroy) {
                component.destroy();
            }
        });
        
        this.components = {};
        this.state = {};
    }
} 