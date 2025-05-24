/**
 * Utility functions for Catalog Master
 */

/**
 * Show message to user
 */
export function showMessage(message, type = 'info') {
    // Remove existing messages
    const existingMessages = document.querySelectorAll('.catalog-master-status');
    existingMessages.forEach(msg => msg.remove());
    
    // Create new message
    const messageDiv = document.createElement('div');
    messageDiv.className = `catalog-master-status ${type}`;
    messageDiv.textContent = message;
    
    // Insert after h1
    const h1 = document.querySelector('.wrap h1');
    if (h1) {
        h1.insertAdjacentElement('afterend', messageDiv);
    } else {
        document.body.insertAdjacentElement('afterbegin', messageDiv);
    }
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        messageDiv.style.transition = 'opacity 0.5s';
        messageDiv.style.opacity = '0';
        setTimeout(() => {
            messageDiv.remove();
        }, 500);
    }, 5000);
    
    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/**
 * Set button loading state
 */
export function setButtonLoading(button, isLoading, originalText = '') {
    if (isLoading) {
        button.disabled = true;
        button.dataset.originalText = button.textContent;
        button.innerHTML = '<span class="catalog-master-spinner"></span>Завантаження...';
    } else {
        button.disabled = false;
        button.textContent = originalText || button.dataset.originalText || 'OK';
        delete button.dataset.originalText;
    }
}

/**
 * Create spinner element
 */
export function createSpinner(size = 'normal') {
    const spinner = document.createElement('span');
    spinner.className = `catalog-master-spinner${size === 'large' ? ' catalog-master-spinner--large' : ''}${size === 'small' ? ' catalog-master-spinner--small' : ''}`;
    return spinner;
}

/**
 * Debounce function for performance
 */
export function debounce(func, wait, immediate = false) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            timeout = null;
            if (!immediate) func.apply(this, args);
        };
        const callNow = immediate && !timeout;
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
        if (callNow) func.apply(this, args);
    };
}

/**
 * Throttle function for performance
 */
export function throttle(func, limit) {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

/**
 * Escape HTML to prevent XSS
 */
export function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Format file size in human readable format
 */
export function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

/**
 * Check if element is in viewport
 */
export function isInViewport(element) {
    const rect = element.getBoundingClientRect();
    return (
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
        rect.right <= (window.innerWidth || document.documentElement.clientWidth)
    );
}

/**
 * Get URL parameters
 */
export function getUrlParams() {
    const params = new URLSearchParams(window.location.search);
    const result = {};
    for (const [key, value] of params) {
        result[key] = value;
    }
    return result;
}

/**
 * Validate email
 */
export function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

/**
 * Generate unique ID
 */
export function generateId(prefix = 'id') {
    return `${prefix}-${Math.random().toString(36).substr(2, 9)}`;
}

/**
 * Copy text to clipboard
 */
export async function copyToClipboard(text) {
    if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text);
    } else {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        document.execCommand('copy');
        textArea.remove();
    }
}

/**
 * Format price for display
 */
export function formatPrice(price, currency = '') {
    const numPrice = parseFloat(price) || 0;
    return numPrice.toFixed(2) + (currency ? ` ${currency}` : '');
}

/**
 * Truncate text to specified length
 */
export function truncateText(text, maxLength = 50) {
    if (!text || text.length <= maxLength) return text;
    return text.substring(0, maxLength) + '...';
}

/**
 * Sanitize HTML to prevent XSS
 */
export function sanitizeHtml(html) {
    const div = document.createElement('div');
    div.textContent = html;
    return div.innerHTML;
}

/**
 * Get URL parameter value
 */
export function getUrlParameter(name) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(name);
}

/**
 * Check if device is mobile
 */
export function isMobile() {
    return window.innerWidth < 768;
}

/**
 * Check if device is tablet
 */
export function isTablet() {
    return window.innerWidth >= 768 && window.innerWidth < 1024;
}

/**
 * Check if device is desktop
 */
export function isDesktop() {
    return window.innerWidth >= 1024;
}

/**
 * Create DOM element with attributes
 */
export function createElement(tag, attributes = {}, children = []) {
    const element = document.createElement(tag);
    
    // Set attributes
    Object.entries(attributes).forEach(([key, value]) => {
        if (key === 'className') {
            element.className = value;
        } else if (key === 'innerHTML') {
            element.innerHTML = value;
        } else if (key === 'textContent') {
            element.textContent = value;
        } else {
            element.setAttribute(key, value);
        }
    });
    
    // Add children
    children.forEach(child => {
        if (typeof child === 'string') {
            element.appendChild(document.createTextNode(child));
        } else if (child instanceof Node) {
            element.appendChild(child);
        }
    });
    
    return element;
}

/**
 * Deep clone object
 */
export function deepClone(obj) {
    if (obj === null || typeof obj !== 'object') return obj;
    if (obj instanceof Date) return new Date(obj.getTime());
    if (obj instanceof Array) return obj.map(item => deepClone(item));
    if (typeof obj === 'object') {
        const clonedObj = {};
        for (const key in obj) {
            if (obj.hasOwnProperty(key)) {
                clonedObj[key] = deepClone(obj[key]);
            }
        }
        return clonedObj;
    }
} 