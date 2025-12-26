/**
 * Shared UI Components and Utilities
 * Common functions used across all admin pages
 */

// Create global Utils namespace
window.Utils = (function () {
    'use strict';

    // ========== String Utilities ==========

    /**
     * Escape HTML entities to prevent XSS
     * @param {string} text - Raw text to escape
     * @returns {string} Escaped HTML-safe string
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Capitalize first letter of string
     * @param {string} str - Input string
     * @returns {string} Capitalized string
     */
    function capitalizeFirst(str) {
        if (!str) return '';
        return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
    }

    /**
     * Truncate string with ellipsis
     * @param {string} str - Input string
     * @param {number} maxLength - Maximum length before truncation
     * @returns {string} Truncated string
     */
    function truncate(str, maxLength = 30) {
        if (!str) return '';
        if (str.length <= maxLength) return str;
        return str.substring(0, maxLength) + '...';
    }

    /**
     * Truncate UUID/ID for display
     * @param {string} id - Full ID string
     * @param {number} length - Characters to show (default 8)
     * @returns {string} Truncated ID with ellipsis
     */
    function truncateId(id, length = 8) {
        if (!id) return '-';
        if (id.length <= length) return id;
        return id.substring(0, length) + '...';
    }

    // ========== Date/Time Formatting ==========

    /**
     * Format date for display (short format)
     * @param {string|Date} dateStr - Date string or Date object
     * @returns {string} Formatted date like "Dec 27, 2024"
     */
    function formatDate(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        if (isNaN(date.getTime())) return '-';
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    }

    /**
     * Format date with time
     * @param {string|Date} dateStr - Date string or Date object
     * @returns {string} Formatted datetime like "Dec 27, 2024, 10:30 AM"
     */
    function formatDateTime(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        if (isNaN(date.getTime())) return '-';
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    /**
     * Format date with full time including seconds
     * @param {string|Date} dateStr - Date string or Date object
     * @returns {string} Full datetime with seconds
     */
    function formatDateTimeFull(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        if (isNaN(date.getTime())) return '-';
        return date.toLocaleString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        });
    }

    /**
     * Get relative time (e.g., "2 hours ago")
     * @param {string|Date} dateStr - Date string or Date object
     * @returns {string} Relative time string
     */
    function timeAgo(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        if (isNaN(date.getTime())) return '-';

        const now = new Date();
        const diffMs = now - date;
        const diffSecs = Math.floor(diffMs / 1000);
        const diffMins = Math.floor(diffSecs / 60);
        const diffHours = Math.floor(diffMins / 60);
        const diffDays = Math.floor(diffHours / 24);

        if (diffSecs < 60) return 'Just now';
        if (diffMins < 60) return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`;
        if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
        if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
        return formatDate(dateStr);
    }

    // ========== Number/Currency Formatting ==========

    /**
     * Format number as currency
     * @param {number} amount - Amount to format
     * @param {string} currency - Currency code (default 'USD')
     * @returns {string} Formatted currency string
     */
    function formatCurrency(amount, currency = 'USD') {
        if (amount === null || amount === undefined) return '-';
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency,
        }).format(amount);
    }

    /**
     * Format number with thousands separator
     * @param {number} num - Number to format
     * @returns {string} Formatted number
     */
    function formatNumber(num) {
        if (num === null || num === undefined) return '-';
        return new Intl.NumberFormat('en-US').format(num);
    }

    /**
     * Convert cents to dollars
     * @param {number} cents - Amount in cents
     * @returns {number} Amount in dollars
     */
    function centsToDollars(cents) {
        return (cents || 0) / 100;
    }

    // ========== Status Formatting ==========

    /**
     * Format company status label
     * @param {string} status - Raw status value
     * @returns {string} Display-friendly status label
     */
    function formatCompanyStatus(status) {
        const statusLabels = {
            'pending': 'Pending',
            'active': 'Active',
            'suspended': 'Suspended',
            'deactivated': 'Voided'
        };
        return statusLabels[status] || capitalizeFirst(status);
    }

    /**
     * Format transaction status label
     * @param {string} status - Raw status value
     * @returns {string} Display-friendly status label
     */
    function formatTransactionStatus(status) {
        const statusLabels = {
            'draft': 'Draft',
            'pending': 'Pending',
            'posted': 'Posted',
            'voided': 'Voided'
        };
        return statusLabels[status] || capitalizeFirst(status);
    }

    /**
     * Format user status label
     * @param {string} status - Raw status value
     * @returns {string} Display-friendly status label
     */
    function formatUserStatus(status) {
        const statusLabels = {
            'pending': 'Pending',
            'active': 'Active',
            'inactive': 'Inactive',
            'locked': 'Locked'
        };
        return statusLabels[status] || capitalizeFirst(status);
    }

    // ========== DOM Utilities ==========

    /**
     * Show loading state in a container
     * @param {HTMLElement} container - Container element
     * @param {string} message - Loading message
     * @param {number} colspan - Table colspan if in table
     */
    function showLoading(container, message = 'Loading...', colspan = null) {
        if (colspan) {
            container.innerHTML = `
                <tr>
                    <td colspan="${colspan}">
                        <div class="loading-spinner">${escapeHtml(message)}</div>
                    </td>
                </tr>
            `;
        } else {
            container.innerHTML = `<div class="loading-spinner">${escapeHtml(message)}</div>`;
        }
    }

    /**
     * Show error message in a container
     * @param {HTMLElement} container - Container element
     * @param {string} message - Error message
     * @param {number} colspan - Table colspan if in table
     */
    function showError(container, message, colspan = null) {
        if (colspan) {
            container.innerHTML = `
                <tr>
                    <td colspan="${colspan}" style="text-align: center; color: var(--color-danger);">
                        ${escapeHtml(message)}
                    </td>
                </tr>
            `;
        } else {
            container.innerHTML = `
                <div style="text-align: center; color: var(--color-danger); padding: 2rem;">
                    ${escapeHtml(message)}
                </div>
            `;
        }
    }

    /**
     * Open a modal dialog
     * @param {HTMLElement} modal - Modal element
     */
    function openModal(modal) {
        if (!modal) return;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    /**
     * Close a modal dialog
     * @param {HTMLElement} modal - Modal element
     */
    function closeModal(modal) {
        if (!modal) return;
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }

    // ========== Public API ==========

    return {
        // String utilities
        escapeHtml,
        capitalizeFirst,
        truncate,
        truncateId,

        // Date/time formatting
        formatDate,
        formatDateTime,
        formatDateTimeFull,
        timeAgo,

        // Number/currency formatting
        formatCurrency,
        formatNumber,
        centsToDollars,

        // Status formatting
        formatCompanyStatus,
        formatTransactionStatus,
        formatUserStatus,

        // DOM utilities
        showLoading,
        showError,
        openModal,
        closeModal,
    };
})();

// For backward compatibility, also expose common functions globally
// This allows gradual migration from inline functions
window.escapeHtml = Utils.escapeHtml;
window.formatDate = Utils.formatDate;
window.formatDateTime = Utils.formatDateTime;
window.formatCurrency = Utils.formatCurrency;
window.capitalizeFirst = Utils.capitalizeFirst;
