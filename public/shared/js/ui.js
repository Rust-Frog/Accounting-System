/**
 * UI Component Library
 * Reusable UI components for the admin interface
 */

window.UI = (function () {
    'use strict';

    // ========== Modal Component ==========

    /**
     * Open a modal by element or selector
     * @param {HTMLElement|string} modal - Modal element or selector
     */
    function openModal(modal) {
        const el = typeof modal === 'string' ? document.querySelector(modal) : modal;
        if (!el) {
            console.warn('UI.openModal: Modal not found', modal);
            return;
        }
        el.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    /**
     * Close a modal by element or selector
     * @param {HTMLElement|string} modal - Modal element or selector
     */
    function closeModal(modal) {
        const el = typeof modal === 'string' ? document.querySelector(modal) : modal;
        if (!el) return;
        el.classList.remove('active');
        document.body.style.overflow = '';
    }

    /**
     * Close all open modals
     */
    function closeAllModals() {
        document.querySelectorAll('.modal.active').forEach(modal => {
            modal.classList.remove('active');
        });
        document.body.style.overflow = '';
    }

    /**
     * Create and show a dynamic modal
     * @param {Object} options - Modal options
     * @param {string} options.title - Modal title
     * @param {string} options.content - Modal body content (HTML)
     * @param {string} [options.size='md'] - Size: 'sm', 'md', 'lg'
     * @param {Array} [options.buttons] - Footer buttons
     * @param {Function} [options.onClose] - Callback when modal closes
     * @returns {HTMLElement} The modal element
     */
    function createModal(options) {
        const { title, content, size = 'md', buttons = [], onClose } = options;

        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-backdrop"></div>
            <div class="modal-content modal-${size}">
                <div class="modal-header">
                    <h2>${escapeHtml(title)}</h2>
                    <button class="modal-close" type="button">&times;</button>
                </div>
                <div class="modal-body">
                    ${content}
                </div>
                ${buttons.length ? `
                    <div class="modal-footer">
                        ${buttons.map(btn => `
                            <button type="button" class="btn ${btn.class || 'btn-secondary'}" data-action="${btn.action || ''}">
                                ${escapeHtml(btn.text)}
                            </button>
                        `).join('')}
                    </div>
                ` : ''}
            </div>
        `;

        // Event handlers
        const closeBtn = modal.querySelector('.modal-close');
        const backdrop = modal.querySelector('.modal-backdrop');

        const handleClose = () => {
            closeModal(modal);
            if (onClose) onClose();
            setTimeout(() => modal.remove(), 300);
        };

        closeBtn.addEventListener('click', handleClose);
        backdrop.addEventListener('click', handleClose);

        // Button handlers
        buttons.forEach(btn => {
            if (btn.onClick) {
                const btnEl = modal.querySelector(`[data-action="${btn.action}"]`);
                if (btnEl) {
                    btnEl.addEventListener('click', () => {
                        btn.onClick(modal);
                    });
                }
            }
        });

        document.body.appendChild(modal);
        openModal(modal);

        return modal;
    }

    // ========== Toast Notifications ==========

    let toastContainer = null;

    /**
     * Initialize toast container
     */
    function initToastContainer() {
        if (toastContainer) return toastContainer;

        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container';
        toastContainer.setAttribute('aria-live', 'polite');
        document.body.appendChild(toastContainer);

        return toastContainer;
    }

    /**
     * Show a toast notification
     * @param {string} message - Toast message
     * @param {Object} [options] - Toast options
     * @param {string} [options.type='info'] - Type: 'info', 'success', 'warning', 'error'
     * @param {number} [options.duration=4000] - Duration in ms (0 for persistent)
     * @param {string} [options.title] - Optional title
     * @returns {HTMLElement} The toast element
     */
    function toast(message, options = {}) {
        const { type = 'info', duration = 4000, title } = options;

        initToastContainer();

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-icon">${getToastIcon(type)}</div>
            <div class="toast-content">
                ${title ? `<div class="toast-title">${escapeHtml(title)}</div>` : ''}
                <div class="toast-message">${escapeHtml(message)}</div>
            </div>
            <button class="toast-close" type="button">&times;</button>
        `;

        // Close button
        toast.querySelector('.toast-close').addEventListener('click', () => {
            dismissToast(toast);
        });

        toastContainer.appendChild(toast);

        // Trigger animation
        requestAnimationFrame(() => {
            toast.classList.add('toast-visible');
        });

        // Auto-dismiss
        if (duration > 0) {
            setTimeout(() => dismissToast(toast), duration);
        }

        return toast;
    }

    /**
     * Dismiss a toast
     * @param {HTMLElement} toast - Toast element
     */
    function dismissToast(toast) {
        if (!toast) return;
        toast.classList.remove('toast-visible');
        toast.classList.add('toast-hiding');
        setTimeout(() => toast.remove(), 300);
    }

    /**
     * Get icon SVG for toast type
     */
    function getToastIcon(type) {
        const icons = {
            info: '<svg viewBox="0 0 16 16" width="18" height="18" fill="currentColor"><path d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8Zm8-6.5a6.5 6.5 0 1 0 0 13 6.5 6.5 0 0 0 0-13ZM6.5 7.75A.75.75 0 0 1 7.25 7h1a.75.75 0 0 1 .75.75v2.75h.25a.75.75 0 0 1 0 1.5h-2a.75.75 0 0 1 0-1.5h.25v-2h-.25a.75.75 0 0 1-.75-.75ZM8 6a1 1 0 1 1 0-2 1 1 0 0 1 0 2Z"/></svg>',
            success: '<svg viewBox="0 0 16 16" width="18" height="18" fill="currentColor"><path d="M8 16A8 8 0 1 1 8 0a8 8 0 0 1 0 16Zm3.78-9.72a.751.751 0 0 0-.018-1.042.751.751 0 0 0-1.042-.018L6.75 9.19 5.28 7.72a.751.751 0 0 0-1.042.018.751.751 0 0 0-.018 1.042l2 2a.75.75 0 0 0 1.06 0Z"/></svg>',
            warning: '<svg viewBox="0 0 16 16" width="18" height="18" fill="currentColor"><path d="M6.457 1.047c.659-1.234 2.427-1.234 3.086 0l6.082 11.378A1.75 1.75 0 0 1 14.082 15H1.918a1.75 1.75 0 0 1-1.543-2.575Zm1.763.707a.25.25 0 0 0-.44 0L1.698 13.132a.25.25 0 0 0 .22.368h12.164a.25.25 0 0 0 .22-.368Zm.53 3.996v2.5a.75.75 0 0 1-1.5 0v-2.5a.75.75 0 0 1 1.5 0ZM9 11a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z"/></svg>',
            error: '<svg viewBox="0 0 16 16" width="18" height="18" fill="currentColor"><path d="M2.343 13.657A8 8 0 1 1 13.658 2.343 8 8 0 0 1 2.343 13.657ZM6.03 4.97a.751.751 0 0 0-1.042.018.751.751 0 0 0-.018 1.042L6.94 8 4.97 9.97a.749.749 0 0 0 .326 1.275.749.749 0 0 0 .734-.215L8 9.06l1.97 1.97a.749.749 0 0 0 1.275-.326.749.749 0 0 0-.215-.734L9.06 8l1.97-1.97a.749.749 0 0 0-.326-1.275.749.749 0 0 0-.734.215L8 6.94Z"/></svg>'
        };
        return icons[type] || icons.info;
    }

    // Shorthand toast methods
    toast.success = (message, options = {}) => toast(message, { ...options, type: 'success' });
    toast.error = (message, options = {}) => toast(message, { ...options, type: 'error' });
    toast.warning = (message, options = {}) => toast(message, { ...options, type: 'warning' });
    toast.info = (message, options = {}) => toast(message, { ...options, type: 'info' });

    // ========== Confirm Dialog ==========

    /**
     * Show a confirmation dialog
     * @param {string} message - Confirmation message
     * @param {Object} [options] - Dialog options
     * @param {string} [options.title='Confirm'] - Dialog title
     * @param {string} [options.confirmText='Confirm'] - Confirm button text
     * @param {string} [options.cancelText='Cancel'] - Cancel button text
     * @param {string} [options.confirmClass='btn-danger'] - Confirm button class
     * @param {boolean} [options.showInput=false] - Show text input field
     * @param {string} [options.inputLabel] - Label for input field
     * @param {string} [options.inputPlaceholder] - Placeholder for input
     * @returns {Promise<{confirmed: boolean, value?: string}>}
     */
    function confirm(message, options = {}) {
        const {
            title = 'Confirm',
            confirmText = 'Confirm',
            cancelText = 'Cancel',
            confirmClass = 'btn-danger',
            showInput = false,
            inputLabel = 'Reason',
            inputPlaceholder = 'Enter reason...'
        } = options;

        return new Promise((resolve) => {
            const inputHtml = showInput ? `
                <div class="form-group" style="margin-top: 1rem;">
                    <label class="form-label">${escapeHtml(inputLabel)}</label>
                    <textarea class="form-input confirm-input" rows="3" placeholder="${escapeHtml(inputPlaceholder)}"></textarea>
                </div>
            ` : '';

            const modal = createModal({
                title,
                content: `<p>${escapeHtml(message)}</p>${inputHtml}`,
                size: 'sm',
                buttons: [
                    {
                        text: cancelText,
                        class: 'btn-secondary',
                        action: 'cancel',
                        onClick: (m) => {
                            closeModal(m);
                            setTimeout(() => m.remove(), 300);
                            resolve({ confirmed: false });
                        }
                    },
                    {
                        text: confirmText,
                        class: confirmClass,
                        action: 'confirm',
                        onClick: (m) => {
                            const input = m.querySelector('.confirm-input');
                            const value = input ? input.value : undefined;
                            closeModal(m);
                            setTimeout(() => m.remove(), 300);
                            resolve({ confirmed: true, value });
                        }
                    }
                ],
                onClose: () => resolve({ confirmed: false })
            });
        });
    }

    // ========== Alert Dialog ==========

    /**
     * Show an alert dialog
     * @param {string} message - Alert message
     * @param {Object} [options] - Dialog options
     * @param {string} [options.title='Alert'] - Dialog title
     * @param {string} [options.buttonText='OK'] - Button text
     * @returns {Promise<void>}
     */
    function alert(message, options = {}) {
        const { title = 'Alert', buttonText = 'OK' } = options;

        return new Promise((resolve) => {
            const modal = createModal({
                title,
                content: `<p>${escapeHtml(message)}</p>`,
                size: 'sm',
                buttons: [
                    {
                        text: buttonText,
                        class: 'btn-primary',
                        action: 'ok',
                        onClick: (m) => {
                            closeModal(m);
                            setTimeout(() => m.remove(), 300);
                            resolve();
                        }
                    }
                ],
                onClose: () => resolve()
            });
        });
    }

    // ========== Loading Overlay ==========

    let loadingOverlay = null;

    /**
     * Show a loading overlay
     * @param {string} [message='Loading...'] - Loading message
     */
    function showLoading(message = 'Loading...') {
        if (loadingOverlay) return;

        loadingOverlay = document.createElement('div');
        loadingOverlay.className = 'loading-overlay';
        loadingOverlay.innerHTML = `
            <div class="loading-content">
                <div class="loading-spinner-large"></div>
                <p class="loading-text">${escapeHtml(message)}</p>
            </div>
        `;
        document.body.appendChild(loadingOverlay);
        document.body.style.overflow = 'hidden';

        requestAnimationFrame(() => {
            loadingOverlay.classList.add('loading-visible');
        });
    }

    /**
     * Hide the loading overlay
     */
    function hideLoading() {
        if (!loadingOverlay) return;

        loadingOverlay.classList.remove('loading-visible');
        document.body.style.overflow = '';

        setTimeout(() => {
            if (loadingOverlay) {
                loadingOverlay.remove();
                loadingOverlay = null;
            }
        }, 300);
    }

    // ========== Utility Functions ==========

    /**
     * Escape HTML entities
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ========== Public API ==========

    return {
        // Modal
        openModal,
        closeModal,
        closeAllModals,
        createModal,

        // Toast
        toast,

        // Dialogs
        confirm,
        alert,

        // Loading
        showLoading,
        hideLoading,
    };
})();

// Also expose as global functions for backward compatibility
window.openModal = UI.openModal;
window.closeModal = UI.closeModal;
