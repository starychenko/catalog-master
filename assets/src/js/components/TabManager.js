/**
 * Tab Management Component
 * Handles tab navigation and content switching
 */
export default class TabManager {
    constructor(config = {}) {
        this.activeTab = null;
        this.tabs = {};
        this.config = config;
    }
    
    /**
     * Initialize tab manager
     */
    init() {
        this.findTabs();
        this.bindEvents();
        this.initSettingsTab(); // Initialize settings enhancements
        this.activateDefaultTab();
        
        console.log('📑 Tab Manager initialized');
    }
    
    /**
     * Find all tabs on the page
     */
    findTabs() {
        const tabNavs = document.querySelectorAll('.catalog-master-tab-nav a');
        const tabContents = document.querySelectorAll('.catalog-master-tab-content');
        
        tabNavs.forEach(tabNav => {
            const target = tabNav.getAttribute('href');
            const content = document.querySelector(target);
            
            if (content) {
                this.tabs[target] = {
                    nav: tabNav,
                    content: content
                };
            }
        });
    }
    
    /**
     * Bind tab events
     */
    bindEvents() {
        Object.values(this.tabs).forEach(({ nav }) => {
            nav.addEventListener('click', (e) => {
                e.preventDefault();
                const target = nav.getAttribute('href');
                this.activateTab(target);
            });
        });
        
        // Handle browser back/forward
        window.addEventListener('popstate', () => {
            this.handleHashChange();
        });
        
        // Handle direct hash navigation
        this.handleHashChange();
    }
    
    /**
     * Activate specific tab
     */
    activateTab(target) {
        if (!this.tabs[target]) {
            console.warn(`Tab ${target} not found`);
            return;
        }
        
        // Deactivate all tabs
        Object.values(this.tabs).forEach(({ nav, content }) => {
            nav.classList.remove('active');
            content.classList.remove('active');
        });
        
        // Activate target tab
        this.tabs[target].nav.classList.add('active');
        this.tabs[target].content.classList.add('active');
        
        this.activeTab = target;
        
        // Update URL hash without triggering navigation
        if (window.location.hash !== target) {
            window.history.replaceState(null, null, target);
        }
        
        // Trigger tab change event
        this.onTabChange(target);
    }
    
    /**
     * Handle URL hash changes
     */
    handleHashChange() {
        const hash = window.location.hash;
        if (hash && this.tabs[hash]) {
            this.activateTab(hash);
        }
    }
    
    /**
     * Activate default tab (first one or from URL hash)
     */
    activateDefaultTab() {
        const hash = window.location.hash;
        
        if (hash && this.tabs[hash]) {
            this.activateTab(hash);
        } else {
            // Activate first tab
            const firstTab = Object.keys(this.tabs)[0];
            if (firstTab) {
                this.activateTab(firstTab);
            }
        }
    }
    
    /**
     * Tab change callback
     */
    onTabChange(tabId) {
        console.log(`Tab changed to: ${tabId}`);
        
        // Trigger custom event
        const event = new CustomEvent('catalog-master:tab-change', {
            detail: { tabId, manager: this }
        });
        document.dispatchEvent(event);
    }
    
    /**
     * Get current active tab
     */
    getActiveTab() {
        return this.activeTab;
    }
    
    /**
     * Get all tabs
     */
    getAllTabs() {
        return this.tabs;
    }
    
    /**
     * Enable/disable tab
     */
    setTabEnabled(target, enabled) {
        if (!this.tabs[target]) return;
        
        const { nav } = this.tabs[target];
        
        if (enabled) {
            nav.classList.remove('disabled');
            nav.style.pointerEvents = 'auto';
            nav.style.opacity = '1';
        } else {
            nav.classList.add('disabled');
            nav.style.pointerEvents = 'none';
            nav.style.opacity = '0.5';
        }
    }
    
    /**
     * Add badge to tab
     */
    addTabBadge(target, text, className = 'badge') {
        if (!this.tabs[target]) return;
        
        const { nav } = this.tabs[target];
        
        // Remove existing badge
        const existingBadge = nav.querySelector('.tab-badge');
        if (existingBadge) {
            existingBadge.remove();
        }
        
        // Add new badge
        const badge = document.createElement('span');
        badge.className = `tab-badge ${className}`;
        badge.textContent = text;
        nav.appendChild(badge);
    }
    
    /**
     * Remove badge from tab
     */
    removeTabBadge(target) {
        if (!this.tabs[target]) return;
        
        const { nav } = this.tabs[target];
        const badge = nav.querySelector('.tab-badge');
        if (badge) {
            badge.remove();
        }
    }
    
    /**
     * Destroy tab manager
     */
    destroy() {
        // Remove event listeners
        Object.values(this.tabs).forEach(({ nav }) => {
            nav.replaceWith(nav.cloneNode(true));
        });
        
        window.removeEventListener('popstate', this.handleHashChange);
        
        this.tabs = {};
        this.activeTab = null;
    }

    initSettingsTab() {
        // Test connection button
        const testBtn = document.getElementById('test-sheets-connection');
        if (testBtn) {
            testBtn.addEventListener('click', this.testConnection.bind(this));
        }

        // Real-time URL validation
        const urlInput = document.getElementById('google_sheet_url');
        if (urlInput) {
            urlInput.addEventListener('input', this.validateUrl.bind(this));
            urlInput.addEventListener('blur', this.validateUrl.bind(this));
        }

        // Auto-save indicators
        const form = document.getElementById('catalog-settings-form');
        if (form) {
            this.addChangeListeners(form);
        }
    }

    async testConnection() {
        const testBtn = document.getElementById('test-sheets-connection');
        const urlInput = document.getElementById('google_sheet_url');
        const sheetNameInput = document.getElementById('sheet_name');
        const resultDiv = document.getElementById('connection-test-result');
        
        if (!testBtn || !urlInput || !resultDiv) return;

        const url = urlInput.value.trim();
        const sheetName = sheetNameInput ? sheetNameInput.value.trim() || 'Sheet1' : 'Sheet1';

        if (!url) {
            this.showConnectionResult('error', 'Будь ласка, введіть URL Google Sheets');
            return;
        }

        // Add loading state
        testBtn.classList.add('loading');
        testBtn.disabled = true;
        this.showConnectionResult('loading', 'Перевіряємо підключення...');

        try {
            const response = await fetch(this.config.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'catalog_master_test_sheets_connection',
                    nonce: this.config.nonce,
                    sheet_url: url,
                    sheet_name: sheetName
                })
            });

            const data = await response.json();

            if (data.success) {
                const message = `✅ Підключення успішне! Знайдено ${data.data.row_count} рядків з ${data.data.headers.length} стовпцями.`;
                this.showConnectionResult('success', message);
                this.updateConnectionStatus(true);
            } else {
                this.showConnectionResult('error', `❌ Помилка: ${data.data}`);
                this.updateConnectionStatus(false);
            }
        } catch (error) {
            console.error('Connection test error:', error);
            this.showConnectionResult('error', '❌ Помилка мережі при перевірці підключення');
            this.updateConnectionStatus(false);
        } finally {
            // Remove loading state
            testBtn.classList.remove('loading');
            testBtn.disabled = false;
        }
    }

    showConnectionResult(type, message) {
        const resultDiv = document.getElementById('connection-test-result');
        if (!resultDiv) return;

        resultDiv.className = `connection-status-message ${type}`;
        resultDiv.textContent = message;
        resultDiv.style.display = 'block';

        // Auto-hide success/error messages after 5 seconds
        if (type !== 'loading') {
            setTimeout(() => {
                resultDiv.style.display = 'none';
            }, 5000);
        }
    }

    updateConnectionStatus(isConnected) {
        const statusElement = document.getElementById('connection-status');
        if (statusElement) {
            statusElement.textContent = isConnected ? 'Налаштовано' : 'Помилка підключення';
            statusElement.className = `settings-card-value connection-status ${isConnected ? 'connected' : 'error'}`;
        }
    }

    validateUrl() {
        const urlInput = document.getElementById('google_sheet_url');
        if (!urlInput) return;

        const url = urlInput.value.trim();
        
        // Remove existing validation classes
        urlInput.classList.remove('error', 'success');

        if (!url) return;

        // Check if it's a valid Google Sheets URL
        const googleSheetsPatterns = [
            /docs\.google\.com\/spreadsheets\/d\/[a-zA-Z0-9-_]+/,
            /drive\.google\.com\/file\/d\/[a-zA-Z0-9-_]+/
        ];

        const isValidGoogleSheetsUrl = googleSheetsPatterns.some(pattern => pattern.test(url));

        if (isValidGoogleSheetsUrl) {
            urlInput.classList.add('success');
        } else if (url.length > 10) { // Only show error for substantial input
            urlInput.classList.add('error');
        }
    }

    addChangeListeners(form) {
        const inputs = form.querySelectorAll('input, textarea, select');
        let hasChanges = false;

        inputs.forEach(input => {
            const originalValue = input.value;
            
            input.addEventListener('input', () => {
                hasChanges = input.value !== originalValue;
                this.updateSaveButtonState(hasChanges);
            });
        });
    }

    updateSaveButtonState(hasChanges) {
        const saveBtn = document.querySelector('.settings-save-btn');
        if (!saveBtn) return;

        if (hasChanges) {
            saveBtn.classList.add('has-changes');
            if (!saveBtn.dataset.originalText) {
                saveBtn.dataset.originalText = saveBtn.textContent;
            }
            saveBtn.textContent = 'Зберегти зміни';
        } else {
            saveBtn.classList.remove('has-changes');
            if (saveBtn.dataset.originalText) {
                saveBtn.textContent = 'Зберегти налаштування';
            }
        }
    }
} 