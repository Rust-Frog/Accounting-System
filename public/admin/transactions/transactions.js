/**
 * Transactions Page Manager
 * Handles CRUD operations for transactions
 */

class TransactionsManager {
    constructor() {
        this.currentPage = 1;
        this.pageSize = 20;
        this.totalPages = 1;
        this.accounts = [];
        this.companies = [];
        this.selectedCompanyId = null;
        this.lineCounter = 0;
        this.editingTransactionId = null;

        this.elements = {
            userName: document.getElementById('userName'),
            btnLogout: document.getElementById('btnLogout'),
            transactionCount: document.getElementById('transactionCount'),
            filterCompany: document.getElementById('filterCompany'),
            filterStatus: document.getElementById('filterStatus'),
            btnRefresh: document.getElementById('btnRefresh'),
            btnNewTransaction: document.getElementById('btnNewTransaction'),
            transactionsBody: document.getElementById('transactionsBody'),
            emptyState: document.getElementById('emptyState'),
            btnCreateFirst: document.getElementById('btnCreateFirst'),
            // Pagination
            btnPrevPage: document.getElementById('btnPrevPage'),
            btnNextPage: document.getElementById('btnNextPage'),
            pageInfo: document.getElementById('pageInfo'),
            // New Transaction Modal
            transactionModal: document.getElementById('transactionModal'),
            btnCloseModal: document.getElementById('btnCloseModal'),
            btnCancelTransaction: document.getElementById('btnCancelTransaction'),
            transactionForm: document.getElementById('transactionForm'),
            btnAddLine: document.getElementById('btnAddLine'),
            linesContainer: document.getElementById('linesContainer'),
            totalDebits: document.getElementById('totalDebits'),
            totalCredits: document.getElementById('totalCredits'),
            balanceStatus: document.getElementById('balanceStatus'),
            btnSubmitTransaction: document.getElementById('btnSubmitTransaction'),
            txnDate: document.getElementById('txnDate'),
            // Detail Modal
            detailModal: document.getElementById('detailModal'),
            btnCloseDetail: document.getElementById('btnCloseDetail'),
            detailContent: document.getElementById('detailContent'),
            detailActions: document.getElementById('detailActions'),
            // Edge Case Warning Modal
            edgeCaseModal: document.getElementById('edgeCaseModal'),
            edgeCaseFlags: document.getElementById('edgeCaseFlags'),
            edgeCaseCheckbox: document.getElementById('edgeCaseCheckbox'),
            btnEdgeCaseCancel: document.getElementById('btnEdgeCaseCancel'),
            btnEdgeCaseProceed: document.getElementById('btnEdgeCaseProceed'),
            // Confirmation Modal
            confirmModal: document.getElementById('confirmModal'),
            confirmTitle: document.getElementById('confirmTitle'),
            confirmAmount: document.getElementById('confirmAmount'),
            confirmDescription: document.getElementById('confirmDescription'),
            impactAccounts: document.getElementById('impactAccounts'),
            impactResult: document.getElementById('impactResult'),
            confirmInputWrapper: document.getElementById('confirmInputWrapper'),
            confirmReason: document.getElementById('confirmReason'),
            btnConfirmCancel: document.getElementById('btnConfirmCancel'),
            btnConfirmAction: document.getElementById('btnConfirmAction')
        };

        this.init();
    }

    async init() {
        await this.checkAuth();
        await this.loadCompanies();
        this.bindEvents();
        this.setDefaultDate();
    }

    async checkAuth() {
        try {
            const result = await api.getMe();
            if (result?.data?.username) {
                this.elements.userName.textContent = result.data.username;
            }
        } catch (error) {
            if (error.status === 401) {
                window.location.href = '/login.html';
            }
        }
    }

    async loadCompanies() {
        try {
            const result = await api.getActiveCompanies();
            this.companies = result?.data || [];
            this.renderCompanySelector();

            // 1. Identify Target Company (URL > localStorage > default)
            const urlParams = new URLSearchParams(window.location.search);
            const urlCompanyId = urlParams.get('company');
            const storedId = localStorage.getItem('company_id');

            let targetId = null;
            if (urlCompanyId && this.companies.some(c => c.id === urlCompanyId)) {
                targetId = urlCompanyId;
                localStorage.setItem('company_id', targetId); // Sync selection
            } else if (storedId && this.companies.some(c => c.id === storedId)) {
                targetId = storedId;
            } else if (this.companies.length > 0) {
                targetId = this.companies[0].id;
                localStorage.setItem('company_id', targetId);
            }

            this.selectedCompanyId = targetId;

            if (this.selectedCompanyId) {
                this.elements.filterCompany.value = this.selectedCompanyId;
                await this.loadAccounts(); // Load accounts for the correct company
                this.checkUrlAction();     // Handle other URL params (status, txn)
                this.loadTransactions();
            } else {
                this.showNoCompanyState();
            }
        } catch (error) {
            console.error('Failed to load companies:', error);
            this.showNoCompanyState();
        }
    }

    renderCompanySelector() {
        if (this.companies.length === 0) {
            this.elements.filterCompany.innerHTML = '<option value="">No companies available</option>';
            return;
        }

        this.elements.filterCompany.innerHTML = this.companies.map(company =>
            `<option value="${company.id}">${this.escapeHtml(company.name)}</option>`
        ).join('');
    }

    showNoCompanyState() {
        this.elements.transactionsBody.innerHTML = '';
        document.querySelector('.panel').style.display = 'none';
        this.elements.emptyState.innerHTML = `
            <svg class="empty-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="64" height="64" fill="currentColor">
                <path d="M1.75 1h12.5c.966 0 1.75.784 1.75 1.75v4c0 .372-.116.717-.314 1 .198.283.314.628.314 1v4a1.75 1.75 0 0 1-1.75 1.75H1.75A1.75 1.75 0 0 1 0 12.75v-4c0-.372.116-.717.314-1a1.739 1.739 0 0 1-.314-1v-4C0 1.784.784 1 1.75 1ZM1.5 2.75v4c0 .138.112.25.25.25h12.5a.25.25 0 0 0 .25-.25v-4a.25.25 0 0 0-.25-.25H1.75a.25.25 0 0 0-.25.25Zm.25 5.75a.25.25 0 0 0-.25.25v4c0 .138.112.25.25.25h12.5a.25.25 0 0 0 .25-.25v-4a.25.25 0 0 0-.25-.25Z"></path>
            </svg>
            <h3>NO COMPANY SELECTED</h3>
            <p>Please create a company first to manage transactions.</p>
            <a href="/dashboard.html" class="btn btn-primary">Go to Dashboard</a>
        `;
        this.elements.emptyState.style.display = 'flex';
        this.elements.btnNewTransaction.disabled = true;
    }

    async loadAccounts() {
        if (!this.selectedCompanyId) return;

        try {
            const result = await api.getAccounts(this.selectedCompanyId);
            this.accounts = result?.data || [];
        } catch (error) {
            console.error('Failed to load accounts:', error);
            this.accounts = [];
        }
    }

    bindEvents() {
        this.bindToolbarEvents();
        this.bindModalEvents();
        this.bindDetailModalEvents();
        this.bindConfirmModalEvents();
        this.bindEdgeCaseModalEvents();
    }

    bindToolbarEvents() {
        this.elements.btnLogout?.addEventListener('click', () => this.logout());
        this.elements.btnRefresh?.addEventListener('click', () => this.loadTransactions());

        // Company selector change
        this.elements.filterCompany?.addEventListener('change', async () => {
            this.selectedCompanyId = this.elements.filterCompany.value;
            localStorage.setItem('company_id', this.selectedCompanyId);
            this.currentPage = 1;
            await this.loadAccounts();
            this.loadTransactions();
        });

        this.elements.filterStatus?.addEventListener('change', () => {
            this.currentPage = 1;
            this.loadTransactions();
        });
        this.elements.btnPrevPage?.addEventListener('click', () => this.goToPage(this.currentPage - 1));
        this.elements.btnNextPage?.addEventListener('click', () => this.goToPage(this.currentPage + 1));
        this.elements.btnNewTransaction?.addEventListener('click', () => this.openNewTransactionModal());
        this.elements.btnCreateFirst?.addEventListener('click', () => this.openNewTransactionModal());
    }

    bindModalEvents() {
        this.elements.btnCloseModal?.addEventListener('click', () => this.closeModal());
        this.elements.btnCancelTransaction?.addEventListener('click', () => this.closeModal());
        this.elements.transactionModal?.querySelector('.modal-backdrop')?.addEventListener('click', () => this.closeModal());
        this.elements.btnAddLine?.addEventListener('click', () => this.addLine());
        this.elements.transactionForm?.addEventListener('submit', (e) => this.handleSubmit(e));
    }

    bindDetailModalEvents() {
        this.elements.btnCloseDetail?.addEventListener('click', () => this.closeDetailModal());
        this.elements.detailModal?.querySelector('.modal-backdrop')?.addEventListener('click', () => this.closeDetailModal());
    }

    bindConfirmModalEvents() {
        this.elements.btnConfirmCancel?.addEventListener('click', () => this.closeConfirmModal());
        this.elements.btnConfirmAction?.addEventListener('click', () => this.handleConfirmAction());
        this.elements.confirmModal?.querySelector('.modal-backdrop')?.addEventListener('click', () => this.closeConfirmModal());
    }

    bindEdgeCaseModalEvents() {
        this.elements.btnEdgeCaseCancel?.addEventListener('click', () => this.closeEdgeCaseModal());
        this.elements.btnEdgeCaseProceed?.addEventListener('click', () => this.proceedWithEdgeCases());
        this.elements.edgeCaseModal?.querySelector('.modal-backdrop')?.addEventListener('click', () => this.closeEdgeCaseModal());
        this.elements.edgeCaseCheckbox?.addEventListener('change', (e) => {
            if (this.elements.btnEdgeCaseProceed) {
                this.elements.btnEdgeCaseProceed.disabled = !e.target.checked;
            }
        });
    }

    checkUrlAction() {
        const urlParams = new URLSearchParams(window.location.search);
        const action = urlParams.get('action');
        const status = urlParams.get('status');
        const txnId = urlParams.get('txn');

        // Validation constants
        const validStatuses = ['all', 'draft', 'posted', 'voided'];

        // Status filter
        if (status && validStatuses.includes(status) && this.elements.filterStatus) {
            this.elements.filterStatus.value = status;
        }

        // Transaction ID to auto-open
        if (txnId && /^[A-Za-z0-9\-]+$/.test(txnId)) {
            this.pendingTxnToOpen = txnId;
        }

        // Action triggers
        if (action === 'new' && this.selectedCompanyId) {
            setTimeout(() => this.openNewTransactionModal(), 300);
            window.history.replaceState({}, '', '/transactions.html');
        }
    }

    // Called after transactions are loaded to auto-open modal if txn param exists
    checkPendingTxnOpen() {
        if (this.pendingTxnToOpen) {
            setTimeout(() => {
                this.viewTransaction(this.pendingTxnToOpen);
                // Clean up URL
                window.history.replaceState({}, '', `/transactions.html?status=pending`);
                this.pendingTxnToOpen = null;
            }, 300);
        }
    }

    setDefaultDate() {
        if (this.elements.txnDate) {
            const today = new Date().toISOString().split('T')[0];
            this.elements.txnDate.value = today;
        }
    }

    async loadTransactions() {
        if (!this.selectedCompanyId) return;

        // Get status filter
        const statusFilter = this.elements.filterStatus?.value || 'all';

        // Fade out current content instead of clearing (reduces flicker)
        this.elements.transactionsBody.style.opacity = '0.5';
        this.elements.transactionsBody.style.pointerEvents = 'none';

        try {
            const result = await api.getTransactions(this.currentPage, this.pageSize, this.selectedCompanyId, statusFilter);
            // Backend returns flat array directly: {success: true, data: [...]}
            const transactions = result?.data || [];

            this.totalPages = Math.ceil(transactions.length / this.pageSize) || 1;
            this.updateTransactionCount(transactions.length);
            this.renderTransactions(transactions);
            this.updatePagination();
            this.checkPendingTxnOpen();
        } catch (error) {
            console.error('Failed to load transactions:', error);
            this.renderTransactions([]);
            this.updateTransactionCount(0);
        } finally {
            // Fade back in
            this.elements.transactionsBody.style.opacity = '1';
            this.elements.transactionsBody.style.pointerEvents = 'auto';
        }
    }

    showLoading() {
        // Only show loading spinner if the table is empty (first load)
        if (!this.elements.transactionsBody.innerHTML.trim() ||
            this.elements.transactionsBody.querySelector('.loading-row')) {
            this.elements.transactionsBody.innerHTML = `
                <tr class="loading-row">
                    <td colspan="6">
                        <div class="loading-spinner">Loading transactions...</div>
                    </td>
                </tr>
            `;
        }
        this.elements.emptyState.style.display = 'none';
    }

    showError(message) {
        this.elements.transactionsBody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align: center; padding: 2rem; color: var(--danger-color);">
                    ${this.escapeHtml(message)}
                </td>
            </tr>
        `;
    }

    updateTransactionCount(count) {
        this.elements.transactionCount.textContent = `${count} transaction${count !== 1 ? 's' : ''}`;
    }

    renderTransactions(transactions) {
        if (transactions.length === 0) {
            this.elements.transactionsBody.innerHTML = '';
            this.elements.emptyState.style.display = 'block';
            document.querySelector('.panel').style.display = 'none';
            return;
        }

        document.querySelector('.panel').style.display = 'block';
        this.elements.emptyState.style.display = 'none';

        this.elements.transactionsBody.innerHTML = transactions.map(txn => this.renderTransactionRow(txn)).join('');

        // Bind clickable rows
        this.elements.transactionsBody.querySelectorAll('tr[data-id]').forEach(row => {
            row.addEventListener('click', () => this.viewTransaction(row.dataset.id));
        });
    }

    renderTransactionRow(txn) {
        const date = new Date(txn.date || txn.created_at).toLocaleDateString();
        // Backend provides total_amount in dollars - just use it
        const amount = this.formatCurrency(txn.total_amount || this.calculateTotal(txn.lines));
        const status = txn.status || 'draft';
        const safeId = this.escapeHtml(txn.id || '');
        const safeStatus = this.escapeHtml(status);
        const statusClass = this.getStatusClass(status);

        return `
            <tr class="clickable-row" data-id="${safeId}">
                <td><code>${this.escapeHtml(txn.id?.substring(0, 8) || 'N/A')}...</code></td>
                <td>${this.escapeHtml(date)}</td>
                <td>${this.escapeHtml(txn.description || 'No description')}</td>
                <td style="font-family: var(--font-mono);">${this.escapeHtml(amount)}</td>
                <td><span class="status-badge ${statusClass}">${safeStatus}</span></td>
                <td>
                    <div class="action-btns">
                        ${this.renderRowActions(txn)}
                    </div>
                </td>
            </tr>
        `;
    }

    renderRowActions(txn) {
        const id = this.escapeHtml(txn.id);
        const status = (txn.status || 'draft').toLowerCase();

        let actions = `
            <button class="btn-icon view" title="View Details" onclick="event.stopPropagation(); transactionsManager.viewTransaction('${id}')">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                </svg>
            </button>
        `;

        if (status === 'draft') {
            actions += `
                <button class="btn-icon edit" title="Edit" onclick="event.stopPropagation(); transactionsManager.editTransaction('${id}')" style="color: var(--primary-color); border-color: var(--primary-color);">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                </button>
                <button class="btn-icon post" title="Post" onclick="event.stopPropagation(); transactionsManager.postTransaction('${id}')" style="color: var(--success-color); border-color: var(--success-color);">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                </button>
                <button class="btn-icon void" title="Delete" onclick="event.stopPropagation(); transactionsManager.deleteTransaction('${id}')" style="color: var(--danger-color); border-color: var(--danger-color);">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        <line x1="10" y1="11" x2="10" y2="17"></line>
                        <line x1="14" y1="11" x2="14" y2="17"></line>
                    </svg>
                </button>
            `;
        }

        if (status === 'posted') {
            actions += `
                <button class="btn-icon void" title="Void" onclick="event.stopPropagation(); transactionsManager.voidTransaction('${id}')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                    </svg>
                </button>
            `;
        }

        return actions;
    }

    calculateTotal(lines) {
        if (!lines || !Array.isArray(lines)) return 0;
        return lines.reduce((sum, line) => sum + (parseFloat(line.debit) || 0), 0);
    }

    formatCurrency(amount) {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(amount || 0);
    }

    updatePagination() {
        this.elements.pageInfo.textContent = `Page ${this.currentPage} of ${this.totalPages || 1}`;
        this.elements.btnPrevPage.disabled = this.currentPage <= 1;
        this.elements.btnNextPage.disabled = this.currentPage >= this.totalPages;
    }

    goToPage(page) {
        if (page < 1 || page > this.totalPages) return;
        this.currentPage = page;
        this.loadTransactions();
    }

    // ========== New Transaction Modal ==========

    openNewTransactionModal() {
        this.editingTransactionId = null;
        this.elements.transactionForm.reset();
        this.setDefaultDate();
        this.elements.linesContainer.innerHTML = '';
        this.lineCounter = 0;

        // Update modal title and button text for create mode
        const modalTitle = this.elements.transactionModal.querySelector('.modal-header h2');
        if (modalTitle) modalTitle.textContent = 'New Transaction';
        this.elements.btnSubmitTransaction.textContent = 'Create Transaction';

        // Add two initial lines: first as Credit, second as Debit
        this.addLine('credit');
        this.addLine('debit');

        this.updateBalanceCheck();
        this.elements.transactionModal.classList.add('active');
    }

    async editTransaction(id) {
        // Close detail modal first if open
        this.closeDetailModal();

        try {
            const result = await api.getTransaction(id, this.selectedCompanyId);
            const txn = result?.data;

            if (!txn) throw new Error('Transaction not found');

            if (txn.status !== 'draft') {
                alert('Only draft transactions can be edited');
                return;
            }

            this.editingTransactionId = id;
            this.elements.transactionForm.reset();
            this.elements.linesContainer.innerHTML = '';
            this.lineCounter = 0;

            // Update modal title and button text for edit mode
            const modalTitle = this.elements.transactionModal.querySelector('.modal-header h2');
            if (modalTitle) modalTitle.textContent = 'Edit Transaction';
            this.elements.btnSubmitTransaction.textContent = 'Update Transaction';

            // Populate form fields
            this.elements.txnDate.value = txn.date || '';
            document.getElementById('txnDescription').value = txn.description || '';
            document.getElementById('txnReference').value = txn.reference_number || '';

            // Populate lines
            if (txn.lines && txn.lines.length > 0) {
                for (const line of txn.lines) {
                    this.addLine();
                    const lineRow = this.elements.linesContainer.querySelector(`[data-line="${this.lineCounter}"]`);
                    if (lineRow) {
                        lineRow.querySelector('.line-account').value = line.account_id;
                        const type = line.debit > 0 ? 'debit' : 'credit';
                        lineRow.querySelector('.line-type').value = type;

                        // Update toggle UI
                        const toggle = lineRow.querySelector('.type-toggle');
                        toggle.querySelectorAll('.toggle-option').forEach(opt => {
                            opt.classList.toggle('active', opt.dataset.value === type);
                        });

                        lineRow.querySelector('.line-amount').value = line.debit > 0 ? line.debit : line.credit;
                    }
                }
            } else {
                // Add two empty lines as fallback
                this.addLine();
                this.addLine();
            }

            this.updateBalanceCheck();
            this.elements.transactionModal.classList.add('active');
        } catch (error) {
            alert(`Failed to load transaction: ${error.message}`);
        }
    }

    closeModal() {
        this.elements.transactionModal.classList.remove('active');
    }

    addLine(defaultType = 'debit') {
        this.lineCounter++;
        const isCredit = defaultType === 'credit';
        const lineHtml = `
            <div class="line-row" data-line="${this.lineCounter}">
                <select name="account_${this.lineCounter}" class="form-select line-account" required>
                    <option value="">Select Account</option>
                    ${this.accounts.map(acc => `
                        <option value="${acc.id}">${this.escapeHtml(acc.code)} - ${this.escapeHtml(acc.name)}</option>
                    `).join('')}
                </select>
                
                <div class="type-toggle-wrapper">
                    <input type="hidden" name="type_${this.lineCounter}" class="line-type" value="${defaultType}">
                    <div class="type-toggle">
                        <div class="toggle-option ${!isCredit ? 'active' : ''}" data-value="debit">Debit</div>
                        <div class="toggle-option ${isCredit ? 'active' : ''}" data-value="credit">Credit</div>
                    </div>
                </div>

                <input type="number" name="amount_${this.lineCounter}" class="form-input line-amount" 
                       placeholder="0.00" step="0.01" min="0" required>
                <button type="button" class="btn-remove-line" data-line="${this.lineCounter}">×</button>
            </div>
        `;

        this.elements.linesContainer.insertAdjacentHTML('beforeend', lineHtml);

        // Bind events for new line
        const lineRow = this.elements.linesContainer.querySelector(`[data-line="${this.lineCounter}"]`);

        // Handle Toggle Click
        const toggle = lineRow.querySelector('.type-toggle');
        const typeInput = lineRow.querySelector('.line-type');

        toggle.addEventListener('click', (e) => {
            const option = e.target.closest('.toggle-option');
            if (option) {
                const newValue = option.dataset.value;
                typeInput.value = newValue;

                // Update UI
                toggle.querySelectorAll('.toggle-option').forEach(opt => {
                    opt.classList.toggle('active', opt === option);
                });

                this.updateBalanceCheck();
            }
        });

        lineRow.querySelector('.line-amount').addEventListener('input', () => this.updateBalanceCheck());
        lineRow.querySelector('.btn-remove-line').addEventListener('click', (e) => {
            lineRow.remove();
            this.updateBalanceCheck();
            this.updateAccountOptions(); // Refresh available accounts after removing line
        });

        // Add change listener for account dropdown to update other dropdowns
        lineRow.querySelector('.line-account').addEventListener('change', () => {
            this.updateAccountOptions();
        });

        // Update all dropdowns to disable already-selected accounts
        this.updateAccountOptions();
    }

    /**
     * Updates all account dropdowns to disable accounts that are already selected
     * in other lines, preventing duplicate account selection.
     */
    updateAccountOptions() {
        // Collect all currently selected account IDs
        const selectedAccounts = new Set();
        this.elements.linesContainer.querySelectorAll('.line-row').forEach(row => {
            const selectedValue = row.querySelector('.line-account').value;
            if (selectedValue) {
                selectedAccounts.add(selectedValue);
            }
        });

        // Update each dropdown to disable accounts selected in OTHER lines
        this.elements.linesContainer.querySelectorAll('.line-row').forEach(row => {
            const select = row.querySelector('.line-account');
            const currentValue = select.value;

            select.querySelectorAll('option').forEach(option => {
                if (option.value && option.value !== currentValue) {
                    // Disable if selected in another line
                    option.disabled = selectedAccounts.has(option.value);
                } else {
                    option.disabled = false;
                }
            });
        });
    }

    updateBalanceCheck() {
        let totalDebits = 0;
        let totalCredits = 0;

        this.elements.linesContainer.querySelectorAll('.line-row').forEach(row => {
            const lineType = row.querySelector('.line-type').value;
            const amount = parseFloat(row.querySelector('.line-amount').value) || 0;

            if (lineType === 'debit') {
                totalDebits += amount;
            } else {
                totalCredits += amount;
            }
        });

        this.elements.totalDebits.textContent = totalDebits.toFixed(2);
        this.elements.totalCredits.textContent = totalCredits.toFixed(2);

        const isBalanced = totalDebits > 0 && totalCredits > 0 && Math.abs(totalDebits - totalCredits) < 0.01;
        this.elements.balanceStatus.textContent = isBalanced ? 'Balanced' : 'Unbalanced';
        this.elements.balanceStatus.className = `balance-status ${isBalanced ? 'balanced' : 'unbalanced'}`;
        this.elements.btnSubmitTransaction.disabled = !isBalanced;
    }

    async handleSubmit(e) {
        e.preventDefault();

        const lines = this.collectLines();
        if (lines.length < 2) {
            alert('At least two lines are required');
            return;
        }

        // Convert lines to validation format (debit_cents/credit_cents)
        const validationLines = lines.map(line => ({
            account_id: line.account_id,
            debit_cents: line.line_type === 'debit' ? line.amount_cents : 0,
            credit_cents: line.line_type === 'credit' ? line.amount_cents : 0
        }));

        const date = this.elements.txnDate.value;
        const description = document.getElementById('txnDescription').value;

        // Pre-validate transaction (includes edge case detection)
        this.elements.btnSubmitTransaction.disabled = true;
        this.elements.btnSubmitTransaction.textContent = 'Validating...';

        try {
            const validation = await api.validateTransaction(validationLines, date, description, this.selectedCompanyId);

            if (!validation.valid) {
                alert('Validation errors:\n\n' + validation.errors.join('\n'));
                this.elements.btnSubmitTransaction.disabled = false;
                this.elements.btnSubmitTransaction.textContent = this.editingTransactionId ? 'Update Transaction' : 'Create Transaction';
                return;
            }

            // Check for edge cases that require approval
            if (validation.edge_cases && validation.edge_cases.requires_approval) {
                this.pendingTransactionData = {
                    date: date,
                    description: description,
                    reference_number: document.getElementById('txnReference').value || null,
                    lines: lines
                };
                this.showEdgeCaseWarningModal(validation.edge_cases);
                return;
            }
        } catch (error) {
            console.error('Validation failed:', error);
            // Continue anyway - backend will catch any errors
        }

        const data = {
            date: this.elements.txnDate.value,
            description: document.getElementById('txnDescription').value,
            reference_number: document.getElementById('txnReference').value || null,
            lines: lines
        };

        const isEditing = !!this.editingTransactionId;
        const actionText = isEditing ? 'Updating...' : 'Creating...';
        const successText = isEditing ? 'Update Transaction' : 'Create Transaction';

        this.elements.btnSubmitTransaction.textContent = actionText;

        try {
            if (isEditing) {
                await api.updateTransaction(this.editingTransactionId, data, this.selectedCompanyId);
            } else {
                await api.createTransaction(data, this.selectedCompanyId);
            }
            this.closeModal();
            this.loadTransactions();
        } catch (error) {
            const action = isEditing ? 'update' : 'create';
            alert(`Failed to ${action} transaction: ${error.message}`);
        } finally {
            this.elements.btnSubmitTransaction.disabled = false;
            this.elements.btnSubmitTransaction.textContent = successText;
        }
    }

    collectLines() {
        const lines = [];
        this.elements.linesContainer.querySelectorAll('.line-row').forEach(row => {
            const accountId = row.querySelector('.line-account').value;
            const lineType = row.querySelector('.line-type').value; // 'debit' or 'credit' (lowercase)
            const amount = parseFloat(row.querySelector('.line-amount').value) || 0;

            if (accountId && amount > 0) {
                // Backend expects: account_id, line_type (lowercase), amount_cents
                const amountCents = Math.round(amount * 100);

                lines.push({
                    account_id: accountId,
                    line_type: lineType,
                    amount_cents: amountCents
                });
            }
        });
        return lines;
    }

    // ========== Transaction Detail ==========

    async viewTransaction(id) {
        try {
            const result = await api.getTransaction(id, this.selectedCompanyId);
            const txn = result?.data;

            if (!txn) throw new Error('Transaction not found');

            this.currentDetailTxn = txn;
            this.renderTransactionDetail(txn);
            this.elements.detailModal.classList.add('active');
        } catch (error) {
            alert(`Failed to load transaction: ${error.message}`);
        }
    }

    renderTransactionDetail(txn) {
        const date = new Date(txn.date || txn.created_at).toLocaleDateString();
        const status = txn.status || 'draft';

        // Calculate totals - handle both backend format (amount_cents, line_type) and frontend format (debit, credit)
        let totalDebit = 0;
        let totalCredit = 0;
        (txn.lines || []).forEach(line => {
            if (line.line_type) {
                // Backend format
                const amount = (line.amount_cents || 0) / 100;
                if (line.line_type === 'debit') totalDebit += amount;
                else totalCredit += amount;
            } else {
                // Frontend format
                totalDebit += (line.debit || 0);
                totalCredit += (line.credit || 0);
            }
        });

        // Pre-escape values for security
        // Backend provides total_amount in dollars - just use it
        const safeDate = this.escapeHtml(date);
        const safeStatus = this.escapeHtml(status);
        const safeAmount = this.escapeHtml(this.formatCurrency(txn.total_amount || totalDebit));

        this.elements.detailContent.innerHTML = `
            <div class="detail-section">
                <h4>Transaction Information</h4>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Reference</label>
                        <span><code>${this.escapeHtml(txn.reference_number || txn.id)}</code></span>
                    </div>
                    <div class="detail-item">
                        <label>Date</label>
                        <span>${safeDate}</span>
                    </div>
                    <div class="detail-item">
                        <label>Status</label>
                        <span><span class="status-badge ${this.getStatusClass(status)}">${safeStatus}</span></span>
                    </div>
                    <div class="detail-item">
                        <label>Amount</label>
                        <span class="amount-highlight">${safeAmount}</span>
                    </div>
                </div>
            </div>
            <div class="detail-section">
                <h4>Description</h4>
                <p>${this.escapeHtml(txn.description || 'No description')}</p>
            </div>
            ${this.renderScenarioSection(txn)}
            ${this.renderJournalLinesTable(txn, totalDebit, totalCredit)}
            ${this.renderReviewSection(status)}
        `;

        this.elements.detailActions.innerHTML = this.renderDetailActions(txn.id, status);
    }

    renderScenarioSection(txn) {
        if (!txn.scenario) return '';
        return `
            <div class="detail-section scenario-section">
                <div class="scenario-badge">${this.escapeHtml(txn.scenario)}</div>
                <p class="scenario-detail">${this.escapeHtml(txn.scenario_detail || '')}</p>
            </div>
        `;
    }

    renderJournalLinesTable(txn, totalDebit, totalCredit) {
        const safeTotalDebit = this.escapeHtml(this.formatCurrency(totalDebit));
        const safeTotalCredit = this.escapeHtml(this.formatCurrency(totalCredit));

        return `
            <div class="detail-section">
                <h4>Journal Lines</h4>
                <table class="lines-table">
                    <thead>
                        <tr>
                            <th>Account</th>
                            <th class="amount">Debit</th>
                            <th class="amount">Credit</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${(txn.lines || []).map(line => {
            // Handle both backend format (amount_cents, line_type) and frontend format (debit, credit)
            let debitAmount = 0;
            let creditAmount = 0;
            if (line.line_type) {
                // Backend format
                const amount = (line.amount_cents || 0) / 100;
                if (line.line_type === 'debit') debitAmount = amount;
                else creditAmount = amount;
            } else {
                // Frontend format
                debitAmount = line.debit || 0;
                creditAmount = line.credit || 0;
            }
            return `
                            <tr>
                                <td>${this.escapeHtml(this.getAccountDisplayName(line.account_id))}</td>
                                <td class="amount debit">${debitAmount > 0 ? this.escapeHtml(this.formatCurrency(debitAmount)) : ''}</td>
                                <td class="amount credit">${creditAmount > 0 ? this.escapeHtml(this.formatCurrency(creditAmount)) : ''}</td>
                            </tr>
                        `;
        }).join('')}
                        <tr class="totals-row">
                            <td><strong>Total</strong></td>
                            <td class="amount debit"><strong>${safeTotalDebit}</strong></td>
                            <td class="amount credit"><strong>${safeTotalCredit}</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        `;
    }

    getAccountDisplayName(accountId) {
        const account = this.accounts.find(a => a.id === accountId);
        if (account) {
            return `${account.code} - ${account.name}`;
        }
        return accountId; // Fallback to ID if not found
    }

    renderReviewSection(status) {
        if (status !== 'pending') return '';
        return `
            <div class="detail-section">
                <h4>Review Decision</h4>
                <div class="form-group">
                    <label for="reviewReason">Reason / Notes</label>
                    <textarea id="reviewReason" class="form-control" rows="3" placeholder="Enter reason for approval or rejection..."></textarea>
                </div>
            </div>
        `;
    }

    renderDetailActions(txnId, status) {
        const safeId = this.escapeHtml(txnId);

        if (status === 'draft') {
            return `
                <button class="btn btn-secondary" onclick="transactionsManager.closeDetailModal()">Close</button>
                <button class="btn btn-danger" onclick="transactionsManager.deleteTransaction('${safeId}')">Delete</button>
                <button class="btn btn-primary" onclick="transactionsManager.editTransaction('${safeId}')">Edit</button>
                <button class="btn btn-success" onclick="transactionsManager.postTransaction('${safeId}')">Post Transaction</button>
            `;
        }
        if (status === 'pending') {
            return `
                <button class="btn btn-secondary" onclick="transactionsManager.closeDetailModal()">Close</button>
                <button class="btn btn-danger" onclick="transactionsManager.rejectTransaction('${safeId}')">Reject</button>
                <button class="btn btn-success" onclick="transactionsManager.approveTransaction('${safeId}')">Approve</button>
            `;
        }
        if (status === 'approved') {
            return `
                <button class="btn btn-secondary" onclick="transactionsManager.closeDetailModal()">Close</button>
                <button class="btn btn-primary" onclick="transactionsManager.postTransaction('${safeId}')">Post Transaction</button>
            `;
        }
        if (status === 'posted') {
            return `
                <button class="btn btn-secondary" onclick="transactionsManager.closeDetailModal()">Close</button>
                <button class="btn btn-danger" onclick="transactionsManager.voidTransaction('${safeId}')">Void Transaction</button>
            `;
        }
        return '<button class="btn btn-secondary" onclick="transactionsManager.closeDetailModal()">Close</button>';
    }

    closeDetailModal() {
        this.elements.detailModal.classList.remove('active');
    }

    // ========== Transaction Actions ==========

    async deleteTransaction(id) {
        // Fetch transaction if not already loaded
        if (!this.currentDetailTxn || this.currentDetailTxn.id !== id) {
            try {
                const result = await api.getTransaction(id, this.selectedCompanyId);
                this.currentDetailTxn = result?.data;
            } catch (error) {
                alert(`Failed to load transaction: ${error.message}`);
                return;
            }
        }

        this.showConfirmModal({
            title: 'Delete Draft Transaction',
            actionType: 'delete',
            showInput: false,
            inputRequired: false,
            buttonText: 'Delete',
            buttonClass: 'btn-danger',
            onConfirm: async () => {
                try {
                    this.elements.btnConfirmAction.disabled = true;
                    this.elements.btnConfirmAction.textContent = 'Deleting...';

                    await api.deleteTransaction(id, this.selectedCompanyId);
                    this.closeConfirmModal();
                    this.closeDetailModal();
                    this.loadTransactions();
                } catch (error) {
                    alert(`Failed to delete transaction: ${error.message}`);
                    this.elements.btnConfirmAction.disabled = false;
                    this.elements.btnConfirmAction.textContent = 'Delete';
                }
            }
        });
    }

    async rejectTransaction(id) {
        // Fetch transaction if not already loaded
        if (!this.currentDetailTxn || this.currentDetailTxn.id !== id) {
            try {
                const result = await api.getTransaction(id, this.selectedCompanyId);
                this.currentDetailTxn = result?.data;
            } catch (error) {
                alert(`Failed to load transaction: ${error.message}`);
                return;
            }
        }

        this.showConfirmModal({
            title: 'Reject Transaction',
            actionType: 'reject',
            showInput: true,
            inputRequired: true,
            buttonText: 'Reject',
            buttonClass: 'btn-danger',
            onConfirm: async (reason) => {
                try {
                    this.elements.btnConfirmAction.disabled = true;
                    this.elements.btnConfirmAction.textContent = 'Rejecting...';

                    // Find approval ID for this transaction
                    const approvals = await api.getPendingApprovals(1, 100, this.selectedCompanyId);
                    const approval = approvals?.data?.find(a => a.entity_id === id);

                    if (approval) {
                        await api.rejectRequest(approval.id, reason, this.selectedCompanyId);
                    } else {
                        throw new Error('Associated approval request not found');
                    }

                    this.closeConfirmModal();
                    this.closeDetailModal();
                    this.loadTransactions();
                } catch (error) {
                    alert(`Failed to reject transaction: ${error.message}`);
                    this.elements.btnConfirmAction.disabled = false;
                    this.elements.btnConfirmAction.textContent = 'Reject';
                }
            }
        });
    }

    async approveTransaction(id) {
        // Fetch transaction if not already loaded
        if (!this.currentDetailTxn || this.currentDetailTxn.id !== id) {
            try {
                const result = await api.getTransaction(id, this.selectedCompanyId);
                this.currentDetailTxn = result?.data;
            } catch (error) {
                alert(`Failed to load transaction: ${error.message}`);
                return;
            }
        }

        this.showConfirmModal({
            title: 'Approve Transaction',
            actionType: 'approve',
            showInput: true,
            inputRequired: false,
            buttonText: 'Approve',
            buttonClass: 'btn-success',
            onConfirm: async (reason) => {
                try {
                    this.elements.btnConfirmAction.disabled = true;
                    this.elements.btnConfirmAction.textContent = 'Approving...';

                    // Find approval ID for this transaction
                    const approvals = await api.getPendingApprovals(1, 100, this.selectedCompanyId);
                    const approval = approvals?.data?.find(a => a.entity_id === id);

                    if (approval) {
                        await api.approveRequest(approval.id, reason, this.selectedCompanyId);
                    } else {
                        throw new Error('Associated approval request not found');
                    }

                    this.closeConfirmModal();
                    this.closeDetailModal();
                    this.loadTransactions();
                } catch (error) {
                    alert(`Failed to approve transaction: ${error.message}`);
                    this.elements.btnConfirmAction.disabled = false;
                    this.elements.btnConfirmAction.textContent = 'Approve';
                }
            }
        });
    }

    showConfirmModal(options) {
        this.confirmCallback = options.onConfirm;
        this.confirmInputRequired = options.inputRequired;

        this.elements.confirmTitle.textContent = options.title;

        // Populate transaction summary
        const txn = this.currentDetailTxn;
        if (txn) {
            // Backend provides total_amount in dollars - just use it
            this.elements.confirmAmount.textContent = this.formatCurrency(txn.total_amount || 0);
            this.elements.confirmDescription.textContent = txn.description || '-';

            // Generate impact preview based on action type
            const actionType = options.actionType || 'approve';
            const lines = txn.lines || [];

            let accountsHtml = '';
            lines.forEach(line => {
                // Handle both backend format (amount_cents, line_type) and frontend format (debit, credit)
                let lineType, amount;
                if (line.line_type) {
                    // Backend format
                    lineType = line.line_type;
                    amount = (line.amount_cents || 0) / 100;
                } else {
                    // Frontend format
                    lineType = line.debit > 0 ? 'debit' : 'credit';
                    amount = line.debit > 0 ? line.debit : line.credit;
                }

                // For void, show reversed entries
                let displayType = lineType;
                if (actionType === 'void') {
                    displayType = lineType === 'debit' ? 'credit' : 'debit';
                }

                const impactType = displayType === 'debit' ? 'increase' : 'decrease';
                const impactLabel = displayType === 'debit' ? 'DEBIT' : 'CREDIT';
                const accountName = this.getAccountDisplayName(line.account_id || line.account_name);

                accountsHtml += `
                    <div class="impact-account">
                        <span class="account-name">${this.escapeHtml(accountName)}</span>
                        <span class="impact-type ${impactType}">${impactLabel}</span>
                        <span style="font-family: monospace; color: #fff;">${this.escapeHtml(this.formatCurrency(amount))}</span>
                    </div>
                `;
            });
            this.elements.impactAccounts.innerHTML = accountsHtml;

            // Result message based on action type
            switch (actionType) {
                case 'delete':
                    this.elements.impactResult.innerHTML = '🗑️ This draft transaction will be <strong>permanently deleted</strong>. This action cannot be undone.';
                    break;
                case 'post':
                    this.elements.impactResult.innerHTML = '✓ This transaction will be <strong>posted to the ledger</strong>. Account balances will be updated and a journal entry will be created.';
                    break;
                case 'void':
                    this.elements.impactResult.innerHTML = '↩️ This transaction will be <strong>voided</strong>. Reversing entries (shown above) will be created to undo the original posting.';
                    break;
                case 'approve':
                    this.elements.impactResult.innerHTML = '✓ Upon approval, this transaction will be moved to <strong>Approved</strong> status and ready for posting.';
                    break;
                case 'reject':
                    this.elements.impactResult.innerHTML = '✗ Upon rejection, this transaction will be marked as <strong>Rejected</strong> and no entries will be recorded.';
                    break;
                default:
                    this.elements.impactResult.innerHTML = '';
            }
        }

        this.elements.confirmInputWrapper.style.display = options.showInput ? 'block' : 'none';
        this.elements.confirmReason.value = '';
        this.elements.confirmReason.style.borderColor = '';

        this.elements.btnConfirmAction.textContent = options.buttonText;
        this.elements.btnConfirmAction.className = `btn ${options.buttonClass}`;
        this.elements.btnConfirmAction.disabled = false;

        this.elements.confirmModal.classList.add('active');
    }

    closeConfirmModal() {
        this.elements.confirmModal.classList.remove('active');
        this.confirmCallback = null;
        // Reset button state
        this.elements.btnConfirmAction.disabled = false;
    }

    handleConfirmAction() {
        const reason = this.elements.confirmReason?.value || '';

        if (this.confirmInputRequired && !reason.trim()) {
            this.elements.confirmReason.focus();
            this.elements.confirmReason.style.borderColor = '#ef4444';
            return;
        }

        if (this.confirmCallback) {
            this.confirmCallback(reason);
        }
    }

    async postTransaction(id) {
        // Fetch transaction if not already loaded
        if (!this.currentDetailTxn || this.currentDetailTxn.id !== id) {
            try {
                const result = await api.getTransaction(id, this.selectedCompanyId);
                this.currentDetailTxn = result?.data;
            } catch (error) {
                alert(`Failed to load transaction: ${error.message}`);
                return;
            }
        }

        this.showConfirmModal({
            title: 'Post Transaction',
            actionType: 'post',
            showInput: false,
            inputRequired: false,
            buttonText: 'Post Transaction',
            buttonClass: 'btn-success',
            onConfirm: async () => {
                try {
                    this.elements.btnConfirmAction.disabled = true;
                    this.elements.btnConfirmAction.textContent = 'Posting...';

                    await api.postTransaction(id, this.selectedCompanyId);
                    this.closeConfirmModal();
                    this.closeDetailModal();
                    this.loadTransactions();
                } catch (error) {
                    alert(`Failed to post transaction: ${error.message}`);
                    this.elements.btnConfirmAction.disabled = false;
                    this.elements.btnConfirmAction.textContent = 'Post Transaction';
                }
            }
        });
    }

    async voidTransaction(id) {
        // Fetch transaction if not already loaded
        if (!this.currentDetailTxn || this.currentDetailTxn.id !== id) {
            try {
                const result = await api.getTransaction(id, this.selectedCompanyId);
                this.currentDetailTxn = result?.data;
            } catch (error) {
                alert(`Failed to load transaction: ${error.message}`);
                return;
            }
        }

        this.showConfirmModal({
            title: 'Void Transaction',
            actionType: 'void',
            showInput: true,
            inputRequired: true,
            buttonText: 'Void Transaction',
            buttonClass: 'btn-danger',
            onConfirm: async (reason) => {
                try {
                    this.elements.btnConfirmAction.disabled = true;
                    this.elements.btnConfirmAction.textContent = 'Voiding...';

                    await api.voidTransaction(id, this.selectedCompanyId, reason);
                    this.closeConfirmModal();
                    this.closeDetailModal();
                    this.loadTransactions();
                } catch (error) {
                    alert(`Failed to void transaction: ${error.message}`);
                    this.elements.btnConfirmAction.disabled = false;
                    this.elements.btnConfirmAction.textContent = 'Void Transaction';
                }
            }
        });
    }

    // ========== Edge Case Warning Modal ==========

    showEdgeCaseWarningModal(edgeCases) {
        // Reset state
        if (this.elements.edgeCaseCheckbox) {
            this.elements.edgeCaseCheckbox.checked = false;
        }
        if (this.elements.btnEdgeCaseProceed) {
            this.elements.btnEdgeCaseProceed.disabled = true;
        }

        // Render flags
        const flags = edgeCases.flags || [];
        if (this.elements.edgeCaseFlags) {
            this.elements.edgeCaseFlags.innerHTML = flags.map(flag => `
                <div class="edge-case-flag">
                    <span class="edge-case-type">${this.getEdgeCaseLabel(flag.type)}</span>
                    <span class="edge-case-description">${this.escapeHtml(flag.description)}</span>
                </div>
            `).join('');
        }

        // Reset submit button
        this.elements.btnSubmitTransaction.disabled = false;
        this.elements.btnSubmitTransaction.textContent = this.editingTransactionId ? 'Update Transaction' : 'Create Transaction';

        // Show modal
        if (this.elements.edgeCaseModal) {
            this.elements.edgeCaseModal.classList.add('active');
        }
    }

    closeEdgeCaseModal() {
        if (this.elements.edgeCaseModal) {
            this.elements.edgeCaseModal.classList.remove('active');
        }
        this.pendingTransactionData = null;
    }

    async proceedWithEdgeCases() {
        if (!this.pendingTransactionData) return;

        this.closeEdgeCaseModal();

        const data = this.pendingTransactionData;
        const isEditing = !!this.editingTransactionId;
        const actionText = isEditing ? 'Updating...' : 'Creating...';
        const successText = isEditing ? 'Update Transaction' : 'Create Transaction';

        this.elements.btnSubmitTransaction.disabled = true;
        this.elements.btnSubmitTransaction.textContent = actionText;

        try {
            if (isEditing) {
                await api.updateTransaction(this.editingTransactionId, data, this.selectedCompanyId);
            } else {
                await api.createTransaction(data, this.selectedCompanyId);
            }
            this.closeModal();
            this.loadTransactions();
        } catch (error) {
            const action = isEditing ? 'update' : 'create';
            alert(`Failed to ${action} transaction: ${error.message}`);
        } finally {
            this.elements.btnSubmitTransaction.disabled = false;
            this.elements.btnSubmitTransaction.textContent = successText;
            this.pendingTransactionData = null;
        }
    }

    getEdgeCaseLabel(type) {
        const labels = {
            'future_dated': '📅 Future Dated',
            'backdated': '⏪ Backdated',
            'large_transaction': '💰 Large Amount',
            'below_threshold': '⚠️ Near Threshold',
            'round_number': '🔢 Round Number',
            'contra_revenue': '🔄 Contra Revenue',
            'contra_expense': '🔄 Contra Expense',
            'asset_writedown': '📉 Asset Write-down',
            'liability_reduction': '💳 Liability Reduction',
            'equity_adjustment': '📊 Equity Adjustment',
            'minimal_description': '📝 Minimal Description',
            'negative_balance': '⚠️ Negative Balance',
            'period_end': '📆 Period End Entry',
            'dormant_account': '💤 Dormant Account',
            'duplicate_transaction': '🔁 Possible Duplicate'
        };
        return labels[type] || `⚠️ ${type.replace(/_/g, ' ')}`;
    }

    // ========== Utilities ==========

    getStatusClass(status) {
        const map = {
            'draft': 'draft',
            'pending': 'pending',
            'approved': 'approved',
            'posted': 'posted',
            'rejected': 'voided', // Map rejected to voided style for now
            'voided': 'voided'
        };
        return map[status.toLowerCase()] || 'draft';
    }

    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    async logout() {
        try {
            await fetch('/api/v1/auth/logout', {
                method: 'POST',
                headers: { 'Authorization': `Bearer ${localStorage.getItem('auth_token')}` }
            });
        } catch (error) {
            console.error('Logout error:', error);
        }

        localStorage.removeItem('auth_token');
        localStorage.removeItem('auth_expires');
        localStorage.removeItem('user_id');
        window.location.href = '/login.html';
    }
}

// Global instance for inline event handlers
let transactionsManager;

document.addEventListener('DOMContentLoaded', () => {
    transactionsManager = new TransactionsManager();
});
