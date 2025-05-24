/**
 * Tab Management Component
 * Handles tab navigation and content switching
 */
export default class TabManager {
    constructor() {
        this.activeTab = null;
        this.tabs = {};
    }
    
    /**
     * Initialize tab manager
     */
    init() {
        this.findTabs();
        this.bindEvents();
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
} 