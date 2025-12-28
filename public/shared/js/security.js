/**
 * Shared Security Utilities
 * Provides XSS protection using DOMPurify
 */

window.Security = (function () {
    'use strict';

    // Check if DOMPurify is available
    const hasDOMPurify = typeof DOMPurify !== 'undefined';

    // DOMPurify config allowing SVG icons
    const PURIFY_CONFIG = {
        ADD_TAGS: ['svg', 'path', 'g', 'circle', 'rect', 'line'],
        ADD_ATTR: ['d', 'viewBox', 'fill', 'stroke', 'stroke-width'],
        ALLOW_DATA_ATTR: true
    };

    /**
     * Sanitize HTML using DOMPurify
     * @param {string} html - HTML to sanitize
     * @returns {string} Sanitized HTML
     */
    function sanitize(html) {
        if (!html) return '';
        if (hasDOMPurify) {
            return DOMPurify.sanitize(html, PURIFY_CONFIG);
        }
        return escapeHtml(html);
    }

    /**
     * Escape HTML entities
     * @param {string} text - Text to escape
     * @returns {string} Escaped text
     */
    function escapeHtml(text) {
        if (text == null) return '';
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    /**
     * Safely set innerHTML with sanitization
     * @param {HTMLElement} el - Target element
     * @param {string} html - HTML content
     */
    function safeInnerHTML(el, html) {
        if (!el) return;

        // If target is a table section, we need to handle tr/td tags carefully
        // DOMPurify or browser innerHTML parsing often strips tr/td if not wrapped in table
        if (['TBODY', 'THEAD', 'TFOOT', 'TABLE'].includes(el.tagName)) {
            // Wrap in table structure to preserve rows/cells
            // We use a temporary wrapper pattern
            const wrapped = `<table><tbody>${html}</tbody></table>`;
            const safeWrapped = sanitize(wrapped);

            // Parse the safe HTML to extract contents
            const temp = document.createElement('div');
            temp.innerHTML = safeWrapped;

            // If we wrapped in tbody, extract from there
            const tbody = temp.querySelector('tbody');
            if (tbody) {
                el.innerHTML = tbody.innerHTML;
                return;
            }
            // Fallback if structure drastically changed (unlikely with valid trs)
        }

        el.innerHTML = sanitize(html);
    }

    return {
        sanitize,
        escapeHtml,
        safeInnerHTML,
        hasDOMPurify
    };
})();

// Global convenience function
window.escapeHtml = Security.escapeHtml;
