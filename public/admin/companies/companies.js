/**
 * Companies Page JavaScript
 * Handles company list, creation, and management
 */

(function () {
    'use strict';

    // State
    let companies = [];
    let currentFilter = '';
    let currentCompany = null;
    let pendingAction = null;

    // DOM Elements
    const elements = {
        companiesBody: document.getElementById('companiesBody'),
        companyCount: document.getElementById('companyCount'),
        filterStatus: document.getElementById('filterStatus'),
        btnRefresh: document.getElementById('btnRefresh'),
        btnNewCompany: document.getElementById('btnNewCompany'),
        btnCreateFirst: document.getElementById('btnCreateFirst'),
        emptyState: document.getElementById('emptyState'),
        companyModal: document.getElementById('companyModal'),
        companyForm: document.getElementById('companyForm'),
        btnCloseModal: document.getElementById('btnCloseModal'),
        btnCancelModal: document.getElementById('btnCancelModal'),
        btnSubmit: document.getElementById('btnSubmit'),
        btnLogout: document.getElementById('btnLogout'),
        userName: document.getElementById('userName'),
        // Form fields
        companyName: document.getElementById('companyName'),
        legalName: document.getElementById('legalName'),
        taxId: document.getElementById('taxId'),
        currency: document.getElementById('currency'),
        street: document.getElementById('street'),
        city: document.getElementById('city'),
        state: document.getElementById('state'),
        postalCode: document.getElementById('postalCode'),
        country: document.getElementById('country'),
        // Details modal
        detailsModal: document.getElementById('detailsModal'),
        detailsContent: document.getElementById('detailsContent'),
        detailsFooter: document.getElementById('detailsFooter'),
        btnCloseDetails: document.getElementById('btnCloseDetails'),
        btnCloseDetailsFooter: document.getElementById('btnCloseDetailsFooter'),
        // Confirm modal
        confirmModal: document.getElementById('confirmModal'),
        confirmModalTitle: document.getElementById('confirmModalTitle'),
        confirmMessage: document.getElementById('confirmMessage'),
        reasonGroup: document.getElementById('reasonGroup'),
        actionReason: document.getElementById('actionReason'),
        btnCloseConfirm: document.getElementById('btnCloseConfirm'),
        btnCancelConfirm: document.getElementById('btnCancelConfirm'),
        btnConfirmAction: document.getElementById('btnConfirmAction'),
    };

    // Initialize
    async function init() {
        await loadUserInfo();
        await loadCompanies();
        setupEventListeners();
    }

    // Load user info
    async function loadUserInfo() {
        try {
            const user = await api.get('/auth/me');
            if (user.data) {
                elements.userName.textContent = user.data.username || 'User';
            }
        } catch (error) {
            console.error('Failed to load user info:', error);
        }
    }

    // Load companies from API
    async function loadCompanies() {
        showLoading();

        try {
            const response = await api.get('/companies');
            companies = response.data || [];
            renderCompanies();
        } catch (error) {
            console.error('Failed to load companies:', error);
            showError('Failed to load companies. Please try again.');
        }
    }

    // Render companies table
    function renderCompanies() {
        const filtered = filterCompanies(companies);

        if (filtered.length === 0) {
            showEmptyState();
            updateCompanyCount(0);
            return;
        }

        hideEmptyState();
        updateCompanyCount(filtered.length);

        elements.companiesBody.innerHTML = filtered.map(company => `
            <tr>
                <td><span class="company-name">${escapeHtml(company.name)}</span></td>
                <td>${escapeHtml(company.legal_name)}</td>
                <td><code>${escapeHtml(company.tax_id)}</code></td>
                <td><span class="currency-badge">${company.currency}</span></td>
                <td><span class="status-badge status-${company.status}">${capitalizeFirst(company.status)}</span></td>
                <td>${formatDate(company.created_at)}</td>
                <td>
                    <div class="action-buttons">
                        <button class="btn-icon" title="View Details" data-action="view" data-id="${company.id}">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="16" height="16" fill="currentColor">
                                <path d="M8 2c1.981 0 3.671.992 4.933 2.078 1.27 1.091 2.187 2.345 2.637 3.023a1.62 1.62 0 0 1 0 1.798c-.45.678-1.367 1.932-2.637 3.023C11.67 13.008 9.981 14 8 14c-1.981 0-3.671-.992-4.933-2.078C1.797 10.83.88 9.576.43 8.898a1.62 1.62 0 0 1 0-1.798c.45-.677 1.367-1.931 2.637-3.022C4.33 2.992 6.019 2 8 2ZM1.679 7.932a.12.12 0 0 0 0 .136c.411.622 1.241 1.75 2.366 2.717C5.176 11.758 6.527 12.5 8 12.5c1.473 0 2.825-.742 3.955-1.715 1.124-.967 1.954-2.096 2.366-2.717a.12.12 0 0 0 0-.136c-.412-.621-1.242-1.75-2.366-2.717C10.824 4.242 9.473 3.5 8 3.5c-1.473 0-2.824.742-3.955 1.715-1.124.967-1.954 2.096-2.366 2.717ZM8 10a2 2 0 1 1-.001-3.999A2 2 0 0 1 8 10Z"></path>
                            </svg>
                        </button>
                        ${getStatusActions(company)}
                    </div>
                </td>
            </tr>
        `).join('');
    }

    // Get status action buttons based on company status
    function getStatusActions(company) {
        const actions = [];

        if (company.status === 'pending') {
            actions.push(`
                <button class="btn-icon btn-success" title="Activate" data-action="activate" data-id="${company.id}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="16" height="16" fill="currentColor">
                        <path d="M13.78 4.22a.75.75 0 0 1 0 1.06l-7.25 7.25a.75.75 0 0 1-1.06 0L2.22 9.28a.751.751 0 0 1 .018-1.042.751.751 0 0 1 1.042-.018L6 10.94l6.72-6.72a.75.75 0 0 1 1.06 0Z"></path>
                    </svg>
                </button>
            `);
        }

        if (company.status === 'active') {
            actions.push(`
                <button class="btn-icon btn-warning" title="Suspend" data-action="suspend" data-id="${company.id}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="16" height="16" fill="currentColor">
                        <path d="M4.47.22A.749.749 0 0 1 5 0h6c.199 0 .389.079.53.22l4.25 4.25c.141.14.22.331.22.53v6a.749.749 0 0 1-.22.53l-4.25 4.25A.749.749 0 0 1 11 16H5a.749.749 0 0 1-.53-.22L.22 11.53A.749.749 0 0 1 0 11V5c0-.199.079-.389.22-.53Zm.84 1.28L1.5 5.31v5.38l3.81 3.81h5.38l3.81-3.81V5.31L10.69 1.5ZM8 4a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 8 4Zm0 8a1 1 0 1 1 0-2 1 1 0 0 1 0 2Z"></path>
                    </svg>
                </button>
            `);
            actions.push(`
                <button class="btn-icon btn-danger" title="Deactivate" data-action="deactivate" data-id="${company.id}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="16" height="16" fill="currentColor">
                        <path d="M3.72 3.72a.75.75 0 0 1 1.06 0L8 6.94l3.22-3.22a.749.749 0 0 1 1.275.326.749.749 0 0 1-.215.734L9.06 8l3.22 3.22a.749.749 0 0 1-.326 1.275.749.749 0 0 1-.734-.215L8 9.06l-3.22 3.22a.751.751 0 0 1-1.042-.018.751.751 0 0 1-.018-1.042L6.94 8 3.72 4.78a.75.75 0 0 1 0-1.06Z"></path>
                    </svg>
                </button>
            `);
        }

        if (company.status === 'suspended') {
            actions.push(`
                <button class="btn-icon btn-success" title="Reactivate" data-action="reactivate" data-id="${company.id}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="16" height="16" fill="currentColor">
                        <path d="M5.029 10.438a.75.75 0 0 1-.219 1.033C4.011 12.117 3.5 12.86 3.5 14a.75.75 0 0 1-1.5 0c0-1.506.67-2.683 1.678-3.485.196-.156.403-.301.619-.431a.75.75 0 0 1 1.033.219c.127.246.233.505.318.775ZM8 3a4 4 0 0 0-1.479 7.717l.285.15a.75.75 0 0 1-.612 1.37l-.285-.15A5.5 5.5 0 1 1 8 3Zm0 1.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5Z"></path>
                    </svg>
                </button>
            `);
            actions.push(`
                <button class="btn-icon btn-danger" title="Deactivate" data-action="deactivate" data-id="${company.id}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="16" height="16" fill="currentColor">
                        <path d="M3.72 3.72a.75.75 0 0 1 1.06 0L8 6.94l3.22-3.22a.749.749 0 0 1 1.275.326.749.749 0 0 1-.215.734L9.06 8l3.22 3.22a.749.749 0 0 1-.326 1.275.749.749 0 0 1-.734-.215L8 9.06l-3.22 3.22a.751.751 0 0 1-1.042-.018.751.751 0 0 1-.018-1.042L6.94 8 3.72 4.78a.75.75 0 0 1 0-1.06Z"></path>
                    </svg>
                </button>
            `);
        }

        return actions.join('');
    }

    // Filter companies
    function filterCompanies(companies) {
        if (!currentFilter) return companies;
        return companies.filter(c => c.status === currentFilter);
    }

    // Show loading state
    function showLoading() {
        elements.companiesBody.innerHTML = `
            <tr>
                <td colspan="7">
                    <div class="loading-spinner">Loading companies...</div>
                </td>
            </tr>
        `;
    }

    // Show error state
    function showError(message) {
        elements.companiesBody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align: center; color: var(--danger);">
                    ${message}
                </td>
            </tr>
        `;
    }

    // Show empty state
    function showEmptyState() {
        document.querySelector('.panel').style.display = 'none';
        elements.emptyState.style.display = 'flex';
    }

    // Hide empty state
    function hideEmptyState() {
        document.querySelector('.panel').style.display = 'block';
        elements.emptyState.style.display = 'none';
    }

    // Update company count
    function updateCompanyCount(count) {
        elements.companyCount.textContent = `${count} ${count === 1 ? 'company' : 'companies'}`;
    }

    // Open create modal
    function openModal() {
        elements.companyModal.classList.add('active');
        elements.companyForm.reset();
        elements.country.value = 'US'; // Reset default
        elements.companyName.focus();
    }

    // Close create modal
    function closeModal() {
        elements.companyModal.classList.remove('active');
        elements.companyForm.reset();
    }

    // Open details modal
    async function openDetailsModal(companyId) {
        elements.detailsModal.classList.add('active');
        elements.detailsContent.innerHTML = '<div class="loading-spinner">Loading...</div>';

        try {
            const response = await api.get(`/companies/${companyId}`);
            currentCompany = response.data;
            renderCompanyDetails(currentCompany);
        } catch (error) {
            console.error('Failed to load company details:', error);
            elements.detailsContent.innerHTML = `
                <div style="text-align: center; color: var(--danger);">
                    Failed to load company details. Please try again.
                </div>
            `;
        }
    }

    // Render company details
    function renderCompanyDetails(company) {
        const address = company.address || {};
        const hasAddress = address.street && address.street !== 'Not Provided';

        elements.detailsContent.innerHTML = `
            <div class="details-grid">
                <div class="detail-row">
                    <span class="detail-label">Company Name</span>
                    <span class="detail-value">${escapeHtml(company.name)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Legal Name</span>
                    <span class="detail-value">${escapeHtml(company.legal_name)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Tax ID</span>
                    <span class="detail-value"><code>${escapeHtml(company.tax_id)}</code></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Currency</span>
                    <span class="detail-value"><span class="currency-badge">${company.currency}</span></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status</span>
                    <span class="detail-value"><span class="status-badge status-${company.status}">${capitalizeFirst(company.status)}</span></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Address</span>
                    <span class="detail-value">${hasAddress ? formatAddress(address) : '<em>Not provided</em>'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Created</span>
                    <span class="detail-value">${formatDateTime(company.created_at)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Last Updated</span>
                    <span class="detail-value">${formatDateTime(company.updated_at)}</span>
                </div>
            </div>
        `;

        // Update footer with action buttons
        const actionButtons = getDetailActionButtons(company);
        elements.detailsFooter.innerHTML = `
            ${actionButtons}
            <button type="button" class="btn btn-secondary" id="btnCloseDetailsFooter">Close</button>
        `;

        // Re-attach close event
        document.getElementById('btnCloseDetailsFooter').addEventListener('click', closeDetailsModal);
    }

    // Get action buttons for details modal
    function getDetailActionButtons(company) {
        const buttons = [];

        if (company.status === 'pending') {
            buttons.push('<button class="btn btn-success" data-action="activate">Activate</button>');
        }

        if (company.status === 'active') {
            buttons.push('<button class="btn btn-warning" data-action="suspend">Suspend</button>');
            buttons.push('<button class="btn btn-danger" data-action="deactivate">Deactivate</button>');
        }

        if (company.status === 'suspended') {
            buttons.push('<button class="btn btn-success" data-action="reactivate">Reactivate</button>');
            buttons.push('<button class="btn btn-danger" data-action="deactivate">Deactivate</button>');
        }

        return buttons.join(' ');
    }

    // Format address
    function formatAddress(address) {
        const parts = [];
        if (address.street) parts.push(escapeHtml(address.street));

        const cityState = [];
        if (address.city) cityState.push(escapeHtml(address.city));
        if (address.state) cityState.push(escapeHtml(address.state));
        if (cityState.length > 0) parts.push(cityState.join(', '));

        if (address.postal_code) parts.push(escapeHtml(address.postal_code));
        if (address.country) parts.push(escapeHtml(address.country));

        return parts.join('<br>');
    }

    // Close details modal
    function closeDetailsModal() {
        elements.detailsModal.classList.remove('active');
        currentCompany = null;
    }

    // Open confirm modal
    function openConfirmModal(action, company) {
        pendingAction = { action, companyId: company.id };

        const actionLabels = {
            activate: 'Activate',
            suspend: 'Suspend',
            reactivate: 'Reactivate',
            deactivate: 'Deactivate'
        };

        const actionMessages = {
            activate: `Are you sure you want to activate "${escapeHtml(company.name)}"? This will allow the company to operate.`,
            suspend: `Are you sure you want to suspend "${escapeHtml(company.name)}"? This will temporarily disable the company.`,
            reactivate: `Are you sure you want to reactivate "${escapeHtml(company.name)}"? This will restore the company to active status.`,
            deactivate: `Are you sure you want to permanently deactivate "${escapeHtml(company.name)}"? This action cannot be undone.`
        };

        elements.confirmModalTitle.textContent = `${actionLabels[action]} Company`;
        elements.confirmMessage.textContent = actionMessages[action];

        // Show reason field for suspend and deactivate
        if (action === 'suspend' || action === 'deactivate') {
            elements.reasonGroup.style.display = 'block';
            elements.actionReason.value = '';
        } else {
            elements.reasonGroup.style.display = 'none';
        }

        // Update button style
        if (action === 'activate' || action === 'reactivate') {
            elements.btnConfirmAction.className = 'btn btn-success';
        } else if (action === 'suspend') {
            elements.btnConfirmAction.className = 'btn btn-warning';
        } else {
            elements.btnConfirmAction.className = 'btn btn-danger';
        }

        elements.confirmModal.classList.add('active');
    }

    // Close confirm modal
    function closeConfirmModal() {
        elements.confirmModal.classList.remove('active');
        pendingAction = null;
        elements.actionReason.value = '';
    }

    // Execute status action
    async function executeAction() {
        if (!pendingAction) return;

        const { action, companyId } = pendingAction;
        const reason = elements.actionReason.value.trim();

        elements.btnConfirmAction.disabled = true;
        elements.btnConfirmAction.textContent = 'Processing...';

        try {
            const payload = {};
            if (action === 'suspend' || action === 'deactivate') {
                payload.reason = reason || 'No reason provided';
            }

            await api.post(`/companies/${companyId}/${action}`, payload);

            closeConfirmModal();
            closeDetailsModal();
            await loadCompanies();
        } catch (error) {
            console.error(`Failed to ${action} company:`, error);
            alert(error.message || `Failed to ${action} company. Please try again.`);
        } finally {
            elements.btnConfirmAction.disabled = false;
            elements.btnConfirmAction.textContent = 'Confirm';
        }
    }

    // Handle form submit
    async function handleSubmit(e) {
        e.preventDefault();

        const name = elements.companyName.value.trim();
        const legalName = elements.legalName.value.trim();
        const taxId = elements.taxId.value.trim();
        const currency = elements.currency.value;

        // Validate required fields
        if (!name || !legalName || !taxId) {
            alert('Please fill in all required fields.');
            return;
        }

        // Build payload
        const payload = {
            name,
            legal_name: legalName,
            tax_id: taxId,
            currency,
        };

        // Add address if any field is filled
        const street = elements.street.value.trim();
        const city = elements.city.value.trim();
        const state = elements.state.value.trim();
        const postalCode = elements.postalCode.value.trim();
        const country = elements.country.value.trim();

        if (street || city) {
            payload.address = {
                street: street || 'Not Provided',
                city: city || 'Not Provided',
                state: state || null,
                postal_code: postalCode || null,
                country: country || 'US',
            };
        }

        // Disable submit button
        elements.btnSubmit.disabled = true;
        elements.btnSubmit.textContent = 'Creating...';

        try {
            await api.post('/companies', payload);
            closeModal();
            await loadCompanies();
        } catch (error) {
            console.error('Failed to create company:', error);
            alert(error.message || 'Failed to create company. Please try again.');
        } finally {
            elements.btnSubmit.disabled = false;
            elements.btnSubmit.textContent = 'Create Company';
        }
    }

    // Handle logout
    async function handleLogout() {
        try {
            await api.post('/auth/logout');
            window.location.href = '/login.html';
        } catch (error) {
            console.error('Logout failed:', error);
            window.location.href = '/login.html';
        }
    }

    // Handle table click (event delegation)
    function handleTableClick(e) {
        const button = e.target.closest('[data-action]');
        if (!button) return;

        const action = button.dataset.action;
        const companyId = button.dataset.id;

        if (action === 'view') {
            openDetailsModal(companyId);
            return;
        }

        // Find company for status actions
        const company = companies.find(c => c.id === companyId);
        if (company) {
            openConfirmModal(action, company);
        }
    }

    // Handle details footer click (event delegation)
    function handleDetailsFooterClick(e) {
        const button = e.target.closest('[data-action]');
        if (!button || !currentCompany) return;

        const action = button.dataset.action;
        openConfirmModal(action, currentCompany);
    }

    // Setup event listeners
    function setupEventListeners() {
        // New company buttons
        elements.btnNewCompany.addEventListener('click', openModal);
        elements.btnCreateFirst?.addEventListener('click', openModal);

        // Modal controls
        elements.btnCloseModal.addEventListener('click', closeModal);
        elements.btnCancelModal.addEventListener('click', closeModal);
        elements.companyModal.querySelector('.modal-backdrop').addEventListener('click', closeModal);

        // Form submit
        elements.companyForm.addEventListener('submit', handleSubmit);

        // Filter change
        elements.filterStatus.addEventListener('change', (e) => {
            currentFilter = e.target.value;
            renderCompanies();
        });

        // Refresh
        elements.btnRefresh.addEventListener('click', loadCompanies);

        // Logout
        elements.btnLogout.addEventListener('click', handleLogout);

        // Table action buttons (event delegation)
        elements.companiesBody.addEventListener('click', handleTableClick);

        // Details modal
        elements.btnCloseDetails.addEventListener('click', closeDetailsModal);
        elements.btnCloseDetailsFooter.addEventListener('click', closeDetailsModal);
        elements.detailsModal.querySelector('.modal-backdrop').addEventListener('click', closeDetailsModal);
        elements.detailsFooter.addEventListener('click', handleDetailsFooterClick);

        // Confirm modal
        elements.btnCloseConfirm.addEventListener('click', closeConfirmModal);
        elements.btnCancelConfirm.addEventListener('click', closeConfirmModal);
        elements.confirmModal.querySelector('.modal-backdrop').addEventListener('click', closeConfirmModal);
        elements.btnConfirmAction.addEventListener('click', executeAction);

        // Close modals on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (elements.confirmModal.classList.contains('active')) {
                    closeConfirmModal();
                } else if (elements.detailsModal.classList.contains('active')) {
                    closeDetailsModal();
                } else if (elements.companyModal.classList.contains('active')) {
                    closeModal();
                }
            }
        });
    }

    // Utility functions
    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function capitalizeFirst(str) {
        if (!str) return '';
        return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
    }

    function formatDate(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    }

    function formatDateTime(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
