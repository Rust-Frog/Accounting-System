/**
 * Sidebar Loader Module
 * Dynamically loads and injects the shared sidebar template
 */

window.Sidebar = (function () {
    'use strict';

    // Cache for the loaded template
    let templateCache = null;

    /**
     * Load the sidebar template from the server
     * @returns {Promise<string>} The sidebar HTML template
     */
    async function loadTemplate() {
        if (templateCache) {
            return templateCache;
        }

        try {
            const response = await fetch('/shared/templates/sidebar.html');
            if (!response.ok) {
                throw new Error(`Failed to load sidebar template: ${response.status}`);
            }
            templateCache = await response.text();
            return templateCache;
        } catch (error) {
            console.error('Sidebar: Failed to load template', error);
            throw error;
        }
    }

    /**
     * Detect the current page from the URL
     * @returns {string} The current page identifier
     */
    function detectCurrentPage() {
        const path = window.location.pathname;

        // Map paths to page identifiers
        const pageMap = {
            '/admin/dashboard/': 'dashboard',
            '/admin/transactions/': 'transactions',
            '/admin/accounts/': 'accounts',
            '/admin/journal/': 'journal',
            '/admin/ledger/': 'ledger',
            '/admin/reports/trial-balance/': 'trial-balance',
            '/admin/reports/income-statement/': 'income-statement',
            '/admin/reports/balance-sheet/': 'balance-sheet',
            '/admin/users/': 'users',
            '/admin/companies/': 'companies',
            '/admin/audit/': 'audit',
            '/admin/settings/': 'settings',
        };

        // Find matching page
        for (const [pagePath, pageId] of Object.entries(pageMap)) {
            if (path.startsWith(pagePath)) {
                return pageId;
            }
        }

        // Default fallback
        return 'dashboard';
    }

    /**
     * Highlight the active navigation item
     * @param {HTMLElement} sidebar - The sidebar element
     * @param {string} currentPage - The current page identifier
     */
    function highlightActivePage(sidebar, currentPage) {
        // Remove existing active class
        sidebar.querySelectorAll('.nav-item.active').forEach(item => {
            item.classList.remove('active');
        });

        // Add active class to current page
        const activeItem = sidebar.querySelector(`[data-page="${currentPage}"]`);
        if (activeItem) {
            activeItem.classList.add('active');
        }
    }

    /**
     * Load user info and update the sidebar
     * @param {HTMLElement} sidebar - The sidebar element
     */
    async function loadUserInfo(sidebar) {
        try {
            // Check if api is available
            if (typeof api === 'undefined' || !api.get) {
                console.warn('Sidebar: API not available, using default user info');
                return;
            }

            const response = await api.get('/auth/me');
            const user = response.data;

            if (user) {
                const userName = sidebar.querySelector('#userName');
                const userAvatar = sidebar.querySelector('#userAvatar');
                const userRole = sidebar.querySelector('#userRole');

                if (userName && user.username) {
                    userName.textContent = user.username;
                }
                if (userAvatar && user.username) {
                    userAvatar.textContent = user.username.charAt(0).toUpperCase();
                }
                if (userRole && user.role) {
                    userRole.textContent = formatRole(user.role);
                }
            }
        } catch (error) {
            console.warn('Sidebar: Failed to load user info', error);
        }
    }

    /**
     * Format role for display
     * @param {string} role - Raw role string
     * @returns {string} Formatted role
     */
    function formatRole(role) {
        const roleMap = {
            'admin': 'Administrator',
            'super_admin': 'Super Admin',
            'accountant': 'Accountant',
            'viewer': 'Viewer'
        };
        return roleMap[role] || role;
    }

    /**
     * Setup logout button handler
     * @param {HTMLElement} sidebar - The sidebar element
     */
    function setupLogoutHandler(sidebar) {
        const btnLogout = sidebar.querySelector('#btnLogout');
        if (btnLogout) {
            btnLogout.addEventListener('click', async () => {
                try {
                    if (typeof api !== 'undefined' && api.post) {
                        await api.post('/auth/logout', {});
                    }
                    window.location.href = '/login.html';
                } catch (error) {
                    console.error('Logout failed:', error);
                    // Still redirect on error
                    window.location.href = '/login.html';
                }
            });
        }
    }

    /**
     * Initialize the sidebar
     * @param {string|HTMLElement} container - Container element or selector
     * @param {Object} options - Configuration options
     * @param {string} options.activePage - Override the active page detection
     * @returns {Promise<HTMLElement>} The initialized sidebar element
     */
    async function init(container, options = {}) {
        // Get container element
        const sidebarContainer = typeof container === 'string'
            ? document.querySelector(container)
            : container;

        if (!sidebarContainer) {
            console.error('Sidebar: Container not found');
            return null;
        }

        try {
            // Load and inject template (sanitized for security)
            // Load and inject template (sanitized for security)
            const template = await loadTemplate();

            if (typeof Security !== 'undefined' && Security.safeInnerHTML) {
                Security.safeInnerHTML(sidebarContainer, template);
            } else {
                // Fallback if Security not loaded yet (should not happen in admin app)
                const sanitizedTemplate = typeof DOMPurify !== 'undefined'
                    ? DOMPurify.sanitize(template, { ADD_TAGS: ['svg', 'path'], ADD_ATTR: ['d', 'viewBox', 'fill', 'stroke', 'stroke-width'] })
                    : template;
                sidebarContainer.innerHTML = sanitizedTemplate;
            }

            // Detect or use provided active page
            const currentPage = options.activePage || detectCurrentPage();
            highlightActivePage(sidebarContainer, currentPage);

            // Setup handlers
            setupLogoutHandler(sidebarContainer);

            // Load user info (async, don't block)
            loadUserInfo(sidebarContainer);

            return sidebarContainer;
        } catch (error) {
            console.error('Sidebar: Initialization failed', error);
            // Provide fallback content
            sidebarContainer.innerHTML = `
                <div class="sidebar-header">
                    <div class="logo">
                        <span class="logo-text">ACCT<span class="logo-accent">SYS</span></span>
                    </div>
                </div>
                <nav class="sidebar-nav">
                    <p style="padding: 1rem; color: var(--color-text-muted);">
                        Failed to load navigation
                    </p>
                </nav>
            `;
            return sidebarContainer;
        }
    }

    // Public API
    return {
        init,
        loadTemplate,
        detectCurrentPage,
        highlightActivePage,
    };
})();

// Auto-initialize if sidebar container exists
document.addEventListener('DOMContentLoaded', () => {
    const sidebarContainer = document.querySelector('.sidebar');
    if (sidebarContainer && sidebarContainer.dataset.autoInit !== 'false') {
        // Only auto-init if the sidebar is empty (placeholder)
        if (sidebarContainer.children.length === 0) {
            Sidebar.init(sidebarContainer);
        }
    }
});
