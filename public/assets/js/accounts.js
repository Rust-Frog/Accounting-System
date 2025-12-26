/**
 * Accounting System - Chart of Accounts Manager
 * Handles account list, create, edit, and toggle operations
 */

class AccountsManager {
    constructor() {
        this.accounts = [];
        this.selectedCompanyId = null;
        this.currentFilter = { type: '', status: '' };
        this.editingAccountId = null;

        // Pagination
        this.currentPage = 1;
        this.itemsPerPage = 8;
        this.filteredAccounts = [];

        this.elements = {
            accountsBody: document.getElementById('accountsBody'),
            accountCount: document.getElementById('accountCount'),
            filterCompany: document.getElementById('filterCompany'),
            filterType: document.getElementById('filterType'),
            filterStatus: document.getElementById('filterStatus'),
            emptyState: document.getElementById('emptyState'),
            accountModal: document.getElementById('accountModal'),
            detailModal: document.getElementById('detailModal'),
            accountForm: document.getElementById('accountForm'),
            modalTitle: document.getElementById('modalTitle'),
            btnSubmitAccount: document.getElementById('btnSubmitAccount'),
            accountCode: document.getElementById('accountCode'),
            accountName: document.getElementById('accountName'),
            accountDescription: document.getElementById('accountDescription'),
            typePreview: document.getElementById('typePreview'),
            typePreviewInfo: document.getElementById('typePreviewInfo'),
            normalBalancePreview: document.getElementById('normalBalancePreview'),
            codeHint: document.getElementById('codeHint'),
            nameCharCount: document.getElementById('nameCharCount'),
            descCharCount: document.getElementById('descCharCount'),
            detailContent: document.getElementById('detailContent'),
            detailActions: document.getElementById('detailActions'),
            userName: document.getElementById('userName'),
            toast: document.getElementById('toast'),
            toastMessage: document.getElementById('toastMessage'),
            // Confirmation modal
            confirmModal: document.getElementById('confirmModal'),
            confirmIcon: document.getElementById('confirmIcon'),
            confirmTitle: document.getElementById('confirmTitle'),
            confirmMessage: document.getElementById('confirmMessage'),
            confirmCode: document.getElementById('confirmCode'),
            confirmName: document.getElementById('confirmName'),
            btnConfirmCancel: document.getElementById('btnConfirmCancel'),
            btnConfirmAction: document.getElementById('btnConfirmAction'),
        };

        this.pendingConfirmAction = null;

        this.init();
    }

    async init() {
        this.bindEvents();
        await this.loadUserInfo();
        await this.loadCompanies();
    }

    bindEvents() {
        // Filter events
        this.elements.filterCompany?.addEventListener('change', () => this.onCompanyChange());
        this.elements.filterType?.addEventListener('change', () => this.applyFilters());
        this.elements.filterStatus?.addEventListener('change', () => this.applyFilters());

        // Button events
        document.getElementById('btnNewAccount')?.addEventListener('click', () => this.openCreateModal());
        document.getElementById('btnCreateFirst')?.addEventListener('click', () => this.openCreateModal());
        document.getElementById('btnRefresh')?.addEventListener('click', () => this.loadAccounts());
        document.getElementById('btnLogout')?.addEventListener('click', () => this.logout());

        // Modal events
        document.getElementById('btnCloseModal')?.addEventListener('click', () => this.closeModal());
        document.getElementById('btnCancelAccount')?.addEventListener('click', () => this.closeModal());
        document.getElementById('btnCloseDetail')?.addEventListener('click', () => this.closeDetailModal());

        // Form events
        this.elements.accountForm?.addEventListener('submit', (e) => this.handleSubmit(e));
        this.elements.accountCode?.addEventListener('input', () => this.updateTypePreview());

        // Character counter events
        this.elements.accountName?.addEventListener('input', (e) => this.updateCharCount(e.target, this.elements.nameCharCount, 100));
        this.elements.accountDescription?.addEventListener('input', (e) => this.updateCharCount(e.target, this.elements.descCharCount, 255));

        // Modal backdrop clicks
        this.elements.accountModal?.querySelector('.modal-backdrop')?.addEventListener('click', () => this.closeModal());
        this.elements.detailModal?.querySelector('.modal-backdrop')?.addEventListener('click', () => this.closeDetailModal());

        // Confirmation modal events
        this.elements.btnConfirmCancel?.addEventListener('click', () => this.closeConfirmModal());
        this.elements.btnConfirmAction?.addEventListener('click', () => this.handleConfirmAction());
        this.elements.confirmModal?.querySelector('.modal-backdrop')?.addEventListener('click', () => this.closeConfirmModal());
    }

    updateCharCount(input, countEl, max) {
        if (countEl) {
            countEl.textContent = input.value.length;
        }
    }

    async loadUserInfo() {
        try {
            const result = await api.getMe();
            if (result?.data?.username) {
                this.elements.userName.textContent = result.data.username;
            }
        } catch (error) {
            console.error('Failed to load user info:', error);
        }
    }

    async loadCompanies() {
        try {
            const result = await api.getCompanies();
            const companies = result?.data || [];

            this.elements.filterCompany.innerHTML = '';

            if (companies.length === 0) {
                this.elements.filterCompany.innerHTML = '<option value="">No companies available</option>';
                // Show empty state with prompt to create company
                this.showNoCompanyState();
                return;
            }

            const urlParams = new URLSearchParams(window.location.search);
            const urlCompanyId = urlParams.get('company');
            const storedId = localStorage.getItem('company_id');
            let targetId = null;

            // Priority: URL > localStorage > first company
            if (urlCompanyId && companies.some(c => c.id === urlCompanyId)) {
                targetId = urlCompanyId;
                localStorage.setItem('company_id', targetId);
            } else if (storedId && companies.some(c => c.id === storedId)) {
                targetId = storedId;
            } else if (companies.length > 0) {
                targetId = companies[0].id;
                localStorage.setItem('company_id', targetId);
            }

            companies.forEach((company) => {
                const option = document.createElement('option');
                option.value = company.id;
                option.textContent = company.name;
                if (company.id === targetId) {
                    option.selected = true;
                }
                this.elements.filterCompany.appendChild(option);
            });

            this.selectedCompanyId = targetId;
            if (this.selectedCompanyId) {
                await this.loadAccounts();
            } else {
                this.showNoCompanyState();
            }
        } catch (error) {
            console.error('Failed to load companies:', error);
            this.elements.filterCompany.innerHTML = '<option value="">Error loading companies</option>';
            this.showNoCompanyState();
        }
    }

    showNoCompanyState() {
        // Hide table, show empty state with company-specific message
        const tableContainer = document.querySelector('.table-container');
        if (tableContainer) tableContainer.style.display = 'none';
        document.getElementById('paginationContainer')?.remove();

        this.elements.emptyState.innerHTML = `
            <svg class="empty-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="64" height="64" fill="currentColor">
                <path d="M1.75 1h12.5c.966 0 1.75.784 1.75 1.75v4c0 .372-.116.717-.314 1 .198.283.314.628.314 1v4a1.75 1.75 0 0 1-1.75 1.75H1.75A1.75 1.75 0 0 1 0 12.75v-4c0-.372.116-.717.314-1a1.739 1.739 0 0 1-.314-1v-4C0 1.784.784 1 1.75 1ZM1.5 2.75v4c0 .138.112.25.25.25h12.5a.25.25 0 0 0 .25-.25v-4a.25.25 0 0 0-.25-.25H1.75a.25.25 0 0 0-.25.25Zm.25 5.75a.25.25 0 0 0-.25.25v4c0 .138.112.25.25.25h12.5a.25.25 0 0 0 .25-.25v-4a.25.25 0 0 0-.25-.25Z"></path>
            </svg>
            <h3>NO COMPANY SELECTED</h3>
            <p>Please create a company first to manage your chart of accounts.</p>
            <a href="/dashboard.html" class="btn btn-primary">Go to Dashboard</a>
        `;
        this.elements.emptyState.style.display = 'flex';
        this.updateAccountCount(0);
    }

    onCompanyChange() {
        this.selectedCompanyId = this.elements.filterCompany.value;
        localStorage.setItem('company_id', this.selectedCompanyId);
        this.loadAccounts();
    }

    async loadAccounts() {
        if (!this.selectedCompanyId) return;

        // Fade out for smooth transition
        this.elements.accountsBody.style.opacity = '0.5';
        this.elements.accountsBody.style.pointerEvents = 'none';

        try {
            const result = await api.getAccounts(this.selectedCompanyId);
            this.accounts = result?.data || [];

            this.applyFilters();
        } catch (error) {
            console.error('Failed to load accounts:', error);
            this.renderAccounts([]);
            this.updateAccountCount(0);
        } finally {
            // Fade back in
            this.elements.accountsBody.style.opacity = '1';
            this.elements.accountsBody.style.pointerEvents = 'auto';
        }
    }

    applyFilters() {
        const typeFilter = this.elements.filterType.value;
        const statusFilter = this.elements.filterStatus.value;

        let filtered = [...this.accounts];

        if (typeFilter) {
            filtered = filtered.filter(a => a.type === typeFilter);
        }

        if (statusFilter === 'active') {
            filtered = filtered.filter(a => a.is_active);
        } else if (statusFilter === 'inactive') {
            filtered = filtered.filter(a => !a.is_active);
        }

        this.filteredAccounts = filtered;
        this.currentPage = 1; // Reset to first page on filter change
        this.renderCurrentPage();
        this.updateAccountCount(filtered.length);
    }

    renderCurrentPage() {
        const start = (this.currentPage - 1) * this.itemsPerPage;
        const end = start + this.itemsPerPage;
        const pageAccounts = this.filteredAccounts.slice(start, end);

        this.renderAccounts(pageAccounts);
        this.renderPagination();
    }

    renderAccounts(accounts) {
        const tableContainer = document.querySelector('.table-container');

        if (this.filteredAccounts.length === 0) {
            this.elements.accountsBody.innerHTML = '';
            if (tableContainer) tableContainer.style.display = 'none';
            document.getElementById('paginationContainer')?.remove();

            // Reset empty state to default accounts message
            this.elements.emptyState.innerHTML = `
                <svg class="empty-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="48" height="48" fill="currentColor">
                    <path d="M0 1.75C0 .784.784 0 1.75 0h12.5C15.216 0 16 .784 16 1.75v12.5A1.75 1.75 0 0 1 14.25 16H1.75A1.75 1.75 0 0 1 0 14.25ZM6.5 6.5v8h7.75a.25.25 0 0 0 .25-.25V6.5Zm8-1.5V1.75a.25.25 0 0 0-.25-.25H1.75a.25.25 0 0 0-.25.25V5Z"></path>
                </svg>
                <h3>No Accounts Found</h3>
                <p>Create your first account to get started with your chart of accounts.</p>
                <button class="btn btn-primary" id="btnCreateFirst">Create Account</button>
            `;
            this.elements.emptyState.style.display = 'flex';

            // Re-bind the create button
            document.getElementById('btnCreateFirst')?.addEventListener('click', () => this.openCreateModal());
            return;
        }

        if (tableContainer) tableContainer.style.display = 'block';
        this.elements.emptyState.style.display = 'none';
        this.elements.accountsBody.innerHTML = accounts.map(account => this.renderAccountRow(account)).join('');

        // Bind row action events
        this.bindRowEvents();
    }

    renderAccountRow(account) {
        const typeBadge = this.getTypeBadge(account.type);
        const statusBadge = account.is_active
            ? '<span class="status-badge status-active">Active</span>'
            : '<span class="status-badge status-inactive">Inactive</span>';
        const balanceClass = account.balance < 0 ? 'negative' : '';
        const formattedBalance = this.formatCurrency(account.balance, account.currency);
        const normalBalanceLabel = account.normal_balance === 'debit' ? 'Dr' : 'Cr';

        // Toggle button - disable if balance is non-zero and account is active
        const canDeactivate = account.balance === 0 || !account.is_active;
        const toggleDisabled = !canDeactivate ? 'disabled title="Cannot deactivate account with balance"' : '';
        const toggleLabel = account.is_active ? 'Deactivate' : 'Activate';

        return `
            <tr data-id="${account.id}">
                <td class="code-cell">${account.code}</td>
                <td class="name-cell">
                    <span class="account-name">${this.escapeHtml(account.name)}</span>
                    ${account.description ? `<small class="account-desc">${this.escapeHtml(account.description)}</small>` : ''}
                </td>
                <td>${typeBadge}</td>
                <td><span class="normal-balance normal-${account.normal_balance}">${normalBalanceLabel}</span></td>
                <td class="balance-cell ${balanceClass}">${formattedBalance}</td>
                <td>${statusBadge}</td>
                <td class="actions-cell">
                    <button class="btn btn-sm btn-icon" data-action="view" data-id="${account.id}" title="View Details">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="14" height="14" fill="currentColor">
                            <path d="M8 2c1.981 0 3.671.992 4.933 2.078 1.27 1.091 2.187 2.345 2.637 3.023a1.62 1.62 0 0 1 0 1.798c-.45.678-1.367 1.932-2.637 3.023C11.67 13.008 9.981 14 8 14c-1.981 0-3.671-.992-4.933-2.078C1.797 10.831.88 9.577.43 8.899a1.62 1.62 0 0 1 0-1.798c.45-.678 1.367-1.932 2.637-3.023C4.33 2.992 6.019 2 8 2ZM1.679 7.932a.12.12 0 0 0 0 .136c.411.622 1.241 1.75 2.366 2.717C5.176 11.758 6.527 12.5 8 12.5c1.473 0 2.825-.742 3.955-1.715 1.124-.967 1.954-2.096 2.366-2.717a.12.12 0 0 0 0-.136c-.412-.621-1.242-1.75-2.366-2.717C10.824 4.242 9.473 3.5 8 3.5c-1.473 0-2.824.742-3.955 1.715-1.124.967-1.954 2.096-2.366 2.717ZM8 10a2 2 0 1 1-.001-3.999A2 2 0 0 1 8 10Z"/>
                        </svg>
                    </button>
                    <button class="btn btn-sm btn-icon" data-action="edit" data-id="${account.id}" title="Edit">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="14" height="14" fill="currentColor">
                            <path d="M11.013 1.427a1.75 1.75 0 0 1 2.474 0l1.086 1.086a1.75 1.75 0 0 1 0 2.474l-8.61 8.61c-.21.21-.47.364-.756.445l-3.251.93a.75.75 0 0 1-.927-.928l.929-3.25c.081-.286.235-.547.445-.758l8.61-8.61Zm.176 4.823L9.75 4.81l-6.286 6.287a.253.253 0 0 0-.064.108l-.558 1.953 1.953-.558a.253.253 0 0 0 .108-.064Zm1.238-3.763a.25.25 0 0 0-.354 0L10.811 3.75l1.439 1.44 1.263-1.263a.25.25 0 0 0 0-.354Z"/>
                        </svg>
                    </button>
                    <button class="btn btn-sm btn-icon ${account.is_active ? 'btn-warning' : 'btn-success'}" 
                            data-action="toggle" data-id="${account.id}" ${toggleDisabled} title="${toggleLabel}">
                        ${account.is_active
                ? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="14" height="14" fill="currentColor"><path d="M4.53 4.53a.75.75 0 0 1 1.06 0L8 6.94l2.41-2.41a.75.75 0 1 1 1.06 1.06l-3 3a.75.75 0 0 1-1.06 0l-3-3a.75.75 0 0 1 0-1.06Z"/></svg>'
                : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="14" height="14" fill="currentColor"><path d="M13.78 4.22a.75.75 0 0 1 0 1.06l-7.25 7.25a.75.75 0 0 1-1.06 0L2.22 9.28a.75.75 0 0 1 1.06-1.06L6 10.94l6.72-6.72a.75.75 0 0 1 1.06 0Z"/></svg>'
            }
                    </button>
                </td>
            </tr>
        `;
    }

    bindRowEvents() {
        this.elements.accountsBody.querySelectorAll('[data-action]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const action = btn.dataset.action;
                const id = btn.dataset.id;

                switch (action) {
                    case 'view': this.showAccountDetail(id); break;
                    case 'edit': this.openEditModal(id); break;
                    case 'toggle': this.toggleAccount(id); break;
                }
            });
        });
    }

    getTypeBadge(type) {
        const badges = {
            asset: '<span class="type-badge type-asset">Asset</span>',
            liability: '<span class="type-badge type-liability">Liability</span>',
            equity: '<span class="type-badge type-equity">Equity</span>',
            revenue: '<span class="type-badge type-revenue">Revenue</span>',
            expense: '<span class="type-badge type-expense">Expense</span>',
        };
        return badges[type] || '<span class="type-badge type-unknown">Unknown</span>';
    }

    updateAccountCount(count) {
        this.elements.accountCount.textContent = `${count} account${count !== 1 ? 's' : ''}`;
    }

    renderPagination() {
        const totalPages = Math.ceil(this.filteredAccounts.length / this.itemsPerPage);

        // Remove existing pagination
        document.getElementById('paginationContainer')?.remove();

        if (totalPages <= 1) return; // No pagination needed

        const container = document.createElement('div');
        container.id = 'paginationContainer';
        container.className = 'pagination';

        const start = (this.currentPage - 1) * this.itemsPerPage + 1;
        const end = Math.min(this.currentPage * this.itemsPerPage, this.filteredAccounts.length);

        container.innerHTML = `
            <button class="btn btn-sm btn-secondary" id="btnPrevPage" ${this.currentPage === 1 ? 'disabled' : ''}>
                ← Previous
            </button>
            <span class="page-info">
                Showing ${start}-${end} of ${this.filteredAccounts.length}
            </span>
            <button class="btn btn-sm btn-secondary" id="btnNextPage" ${this.currentPage >= totalPages ? 'disabled' : ''}>
                Next →
            </button>
        `;

        // Insert after table
        const tableContainer = document.querySelector('.table-container');
        tableContainer?.parentElement?.appendChild(container);

        // Bind pagination events
        document.getElementById('btnPrevPage')?.addEventListener('click', () => this.prevPage());
        document.getElementById('btnNextPage')?.addEventListener('click', () => this.nextPage());
    }

    prevPage() {
        if (this.currentPage > 1) {
            this.currentPage--;
            this.renderCurrentPage();
        }
    }

    nextPage() {
        const totalPages = Math.ceil(this.filteredAccounts.length / this.itemsPerPage);
        if (this.currentPage < totalPages) {
            this.currentPage++;
            this.renderCurrentPage();
        }
    }

    // Modal operations
    openCreateModal() {
        this.editingAccountId = null;
        this.elements.modalTitle.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2v20M2 12h20"/>
            </svg>
            New Account
        `;
        this.elements.btnSubmitAccount.textContent = 'Create Account';
        this.elements.accountForm.reset();
        this.elements.accountCode.disabled = false;
        this.elements.accountName.disabled = false;
        this.elements.accountName.classList.remove('readonly');

        // Reset character counters
        if (this.elements.nameCharCount) this.elements.nameCharCount.textContent = '0';
        if (this.elements.descCharCount) this.elements.descCharCount.textContent = '0';

        this.updateTypePreview();
        this.elements.accountModal.classList.add('active');
    }

    openEditModal(accountId) {
        const account = this.accounts.find(a => a.id === accountId);
        if (!account) return;

        this.editingAccountId = accountId;

        // Active accounts have restricted editing
        const isActive = account.is_active;

        if (isActive) {
            this.elements.modalTitle.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
                Edit Account (Limited)
            `;
            this.elements.btnSubmitAccount.textContent = 'Update Description';
        } else {
            this.elements.modalTitle.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
                Edit Account
            `;
            this.elements.btnSubmitAccount.textContent = 'Save Changes';
        }

        this.elements.accountCode.value = account.code;
        this.elements.accountCode.disabled = true; // Can't change code after creation
        this.elements.accountName.value = account.name;
        this.elements.accountName.disabled = isActive; // Lock name for active accounts
        this.elements.accountName.classList.toggle('readonly', isActive);
        this.elements.accountDescription.value = account.description || '';

        // Update character counts
        if (this.elements.nameCharCount) {
            this.elements.nameCharCount.textContent = account.name.length;
        }
        if (this.elements.descCharCount) {
            this.elements.descCharCount.textContent = (account.description || '').length;
        }

        this.updateTypePreview();
        this.elements.accountModal.classList.add('active');
    }

    closeModal() {
        this.elements.accountModal.classList.remove('active');
        this.editingAccountId = null;
    }

    showAccountDetail(accountId) {
        const account = this.accounts.find(a => a.id === accountId);
        if (!account) return;

        const typeBadge = this.getTypeBadge(account.type);
        const statusBadge = account.is_active
            ? '<span class="status-badge status-active">Active</span>'
            : '<span class="status-badge status-inactive">Inactive</span>';

        this.elements.detailContent.innerHTML = `
            <div class="detail-grid">
                <div class="detail-row">
                    <span class="detail-label">Code</span>
                    <span class="detail-value code-value">${account.code}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Name</span>
                    <span class="detail-value">${this.escapeHtml(account.name)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Type</span>
                    <span class="detail-value">${typeBadge}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Normal Balance</span>
                    <span class="detail-value">${account.normal_balance === 'debit' ? 'Debit (Dr)' : 'Credit (Cr)'}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Current Balance</span>
                    <span class="detail-value balance-value">${this.formatCurrency(account.balance, account.currency)}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status</span>
                    <span class="detail-value">${statusBadge}</span>
                </div>
                ${account.description ? `
                <div class="detail-row full-width">
                    <span class="detail-label">Description</span>
                    <span class="detail-value">${this.escapeHtml(account.description)}</span>
                </div>
                ` : ''}
            </div>
            
            <div class="transactions-section">
                <h4 class="section-title">Recent Transactions</h4>
                <div id="accountTransactionsList" class="transactions-list">
                    <div class="loading-text">Loading transactions...</div>
                </div>
            </div>
        `;

        this.elements.detailActions.innerHTML = `
            <button class="btn btn-secondary" onclick="accountsManager.closeDetailModal()">Close</button>
            <button class="btn btn-primary" onclick="accountsManager.openEditModal('${account.id}'); accountsManager.closeDetailModal();">Edit</button>
        `;

        this.elements.detailModal.classList.add('active');

        // Load transactions async
        this.loadAccountTransactions(accountId);
    }

    async loadAccountTransactions(accountId) {
        const listEl = document.getElementById('accountTransactionsList');
        if (!listEl) return;

        try {
            const transactions = await api.getAccountTransactions(this.currentCompanyId, accountId);

            if (!transactions || transactions.length === 0) {
                listEl.innerHTML = '<div class="no-transactions">No transactions found for this account</div>';
                return;
            }

            listEl.innerHTML = transactions.map(t => `
                <div class="transaction-item">
                    <div class="transaction-date">${t.transaction_date}</div>
                    <div class="transaction-desc">${this.escapeHtml(t.description)}</div>
                    <div class="transaction-status status-${t.status}">${t.status}</div>
                    <div class="transaction-amount">${this.formatCurrency(t.amount)}</div>
                </div>
            `).join('');
        } catch (error) {
            listEl.innerHTML = '<div class="error-text">Failed to load transactions</div>';
        }
    }

    closeDetailModal() {
        this.elements.detailModal.classList.remove('active');
    }

    updateTypePreview() {
        const code = parseInt(this.elements.accountCode.value, 10);
        let typeHtml = '<span class="type-badge type-unknown">Enter code to determine type</span>';
        let hint = 'Code determines account type automatically';

        if (!isNaN(code)) {
            if (code >= 1000 && code <= 1999) {
                typeHtml = '<span class="type-badge type-asset">Asset (Debit balance)</span>';
                hint = 'Assets: 1000-1999';
            } else if (code >= 2000 && code <= 2999) {
                typeHtml = '<span class="type-badge type-liability">Liability (Credit balance)</span>';
                hint = 'Liabilities: 2000-2999';
            } else if (code >= 3000 && code <= 3999) {
                typeHtml = '<span class="type-badge type-equity">Equity (Credit balance)</span>';
                hint = 'Equity: 3000-3999';
            } else if (code >= 4000 && code <= 4999) {
                typeHtml = '<span class="type-badge type-revenue">Revenue (Credit balance)</span>';
                hint = 'Revenue: 4000-4999';
            } else if (code >= 5000 && code <= 5999) {
                typeHtml = '<span class="type-badge type-expense">Expense (Debit balance)</span>';
                hint = 'Expenses: 5000-5999';
            } else {
                typeHtml = '<span class="type-badge type-invalid">Invalid code range</span>';
                hint = 'Code must be between 1000-5999';
            }
        }

        this.elements.typePreview.innerHTML = typeHtml;
        this.elements.codeHint.textContent = hint;
    }

    async handleSubmit(e) {
        e.preventDefault();

        const data = {
            name: this.elements.accountName.value.trim(),
            description: this.elements.accountDescription.value.trim() || null,
        };

        try {
            if (this.editingAccountId) {
                // Update existing account
                await api.updateAccount(this.editingAccountId, data, this.selectedCompanyId);
                this.showToast('Account updated successfully', 'success');
            } else {
                // Create new account
                data.code = parseInt(this.elements.accountCode.value, 10);
                await api.createAccount(data, this.selectedCompanyId);
                this.showToast('Account created successfully', 'success');
            }

            this.closeModal();
            await this.loadAccounts();
        } catch (error) {
            console.error('Failed to save account:', error);
            this.showToast(error.message || 'Failed to save account', 'error');
        }
    }

    toggleAccount(accountId) {
        const account = this.accounts.find(a => a.id === accountId);
        if (!account) return;

        const isDeactivating = account.is_active;
        const variant = isDeactivating ? 'warning' : 'success';
        const title = isDeactivating ? 'Deactivate Account' : 'Activate Account';
        const icon = isDeactivating ? '⚠' : '✓';
        const message = isDeactivating
            ? 'This account will be marked as inactive and hidden from transaction forms. You can reactivate it later.'
            : 'This account will be marked as active and available for use in transactions.';
        const buttonClass = isDeactivating ? 'btn-warning' : 'btn-success';
        const buttonText = isDeactivating ? 'Deactivate' : 'Activate';

        this.showConfirmModal({
            variant,
            title,
            icon,
            message,
            accountCode: account.code,
            accountName: account.name,
            buttonClass,
            buttonText,
            onConfirm: async () => {
                try {
                    this.elements.btnConfirmAction.classList.add('loading');
                    await api.toggleAccount(accountId, this.selectedCompanyId);
                    this.closeConfirmModal();
                    this.showToast(`Account ${isDeactivating ? 'deactivated' : 'activated'} successfully`, 'success');
                    await this.loadAccounts();
                } catch (error) {
                    console.error('Failed to toggle account:', error);
                    this.showToast(error.message || 'Failed to update account status', 'error');
                } finally {
                    this.elements.btnConfirmAction.classList.remove('loading');
                }
            }
        });
    }

    showConfirmModal(options) {
        const modal = this.elements.confirmModal;
        modal.className = `confirm-modal confirm-${options.variant}`;
        this.elements.confirmIcon.textContent = options.icon;
        this.elements.confirmTitle.textContent = options.title;
        this.elements.confirmMessage.textContent = options.message;
        this.elements.confirmCode.textContent = options.accountCode;
        this.elements.confirmName.textContent = options.accountName;
        this.elements.btnConfirmAction.className = `btn ${options.buttonClass}`;
        this.elements.btnConfirmAction.textContent = options.buttonText;
        this.pendingConfirmAction = options.onConfirm;
        modal.classList.add('active');
    }

    closeConfirmModal() {
        this.elements.confirmModal.classList.remove('active');
        this.pendingConfirmAction = null;
    }

    handleConfirmAction() {
        if (this.pendingConfirmAction) {
            this.pendingConfirmAction();
        }
    }

    // Utility methods
    formatCurrency(amount, currency = 'PHP') {
        const symbol = currency === 'PHP' ? '₱' : '$';
        const formatted = Math.abs(amount).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        return amount < 0 ? `(${symbol}${formatted})` : `${symbol}${formatted}`;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    showToast(message, type = 'info') {
        this.elements.toast.className = `toast toast-${type} show`;
        this.elements.toastMessage.textContent = message;

        setTimeout(() => {
            this.elements.toast.classList.remove('show');
        }, 3000);
    }

    logout() {
        localStorage.removeItem('auth_token');
        localStorage.removeItem('auth_expires');
        localStorage.removeItem('user_id');
        window.location.href = '/login.html';
    }
}

// Initialize on page load
const accountsManager = new AccountsManager();
