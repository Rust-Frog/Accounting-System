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
            const result = await api.getCompanies();
            let companies = result?.data || [];

            // MOCK: Use mock company if API returns empty (for layout testing)
            if (companies.length === 0) {
                companies = [
                    { id: '1', name: 'Metro Dumaguete College', code: 'MDC' }
                ];
            }

            this.companies = companies;
            this.renderCompanySelector();

            // Auto-select first company if available
            if (this.companies.length > 0) {
                this.selectedCompanyId = this.companies[0].id;
                this.elements.filterCompany.value = this.selectedCompanyId;
                await this.loadAccounts();
                // Check URL params BEFORE loading transactions (sets filter)
                this.checkUrlAction();
                this.loadTransactions();
            } else {
                this.showNoCompanyState();
            }
        } catch (error) {
            console.error('Failed to load companies:', error);

            // MOCK: Fallback to mock on error
            this.companies = [{ id: '1', name: 'Metro Dumaguete College', code: 'MDC' }];
            this.renderCompanySelector();
            this.selectedCompanyId = '1';
            this.elements.filterCompany.value = '1';
            this.checkUrlAction();
            this.loadMockTransactions();
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
        this.elements.emptyState.style.display = 'block';
        this.elements.emptyState.innerHTML = `
            <svg class="empty-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="48" height="48" fill="currentColor"><path d="M1.75 1h12.5c.966 0 1.75.784 1.75 1.75v4c0 .372-.116.717-.314 1 .198.283.314.628.314 1v4a1.75 1.75 0 0 1-1.75 1.75H1.75A1.75 1.75 0 0 1 0 12.75v-4c0-.372.116-.717.314-1a1.739 1.739 0 0 1-.314-1v-4C0 1.784.784 1 1.75 1Z"></path></svg>
            <h3>No Company Selected</h3>
            <p>Please create a company first to manage transactions.</p>
        `;
        document.querySelector('.panel').style.display = 'none';
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
    }

    bindToolbarEvents() {
        this.elements.btnLogout?.addEventListener('click', () => this.logout());
        this.elements.btnRefresh?.addEventListener('click', () => this.loadTransactions());

        // Company selector change
        this.elements.filterCompany?.addEventListener('change', async () => {
            this.selectedCompanyId = this.elements.filterCompany.value;
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

    checkUrlAction() {
        const urlParams = new URLSearchParams(window.location.search);
        const action = urlParams.get('action');
        const status = urlParams.get('status');
        const company = urlParams.get('company');
        const txnId = urlParams.get('txn');

        // Set company filter from URL
        if (company && this.elements.filterCompany) {
            this.elements.filterCompany.value = company;
            this.selectedCompanyId = company;
        }

        // Set status filter from URL
        if (status && this.elements.filterStatus) {
            this.elements.filterStatus.value = status;
        }

        // Store txnId to open modal after transactions load
        if (txnId) {
            this.pendingTxnToOpen = txnId;
        }

        if (action === 'new' && this.selectedCompanyId) {
            // Small delay to let accounts load
            setTimeout(() => this.openNewTransactionModal(), 300);
            // Clean up URL
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

        this.showLoading();

        try {
            const result = await api.getTransactions(this.currentPage, this.pageSize, this.selectedCompanyId);
            let transactions = result?.data || [];
            const meta = result?.meta || {};

            // MOCK: Use mock data if API returns empty
            if (transactions.length === 0) {
                this.loadMockTransactions();
                return;
            }

            this.totalPages = Math.ceil((meta.total || transactions.length) / this.pageSize);
            this.updateTransactionCount(meta.total || transactions.length);
            this.renderTransactions(transactions);
            this.updatePagination();
            this.checkPendingTxnOpen();
        } catch (error) {
            console.error('Failed to load transactions:', error);
            this.loadMockTransactions();
        }
    }

    loadMockTransactions() {
        // MOCK: Sample transactions for Metro Dumaguete College
        const mockTransactions = [
            {
                id: 'TXN-2024-001',
                reference_number: 'JE-2024-001',
                description: 'Tuition Fee Collection - 1st Semester',
                date: '2024-12-18',
                status: 'pending',
                total_amount: 45000.00,
                created_at: '2024-12-18T08:30:00Z'
            },
            {
                id: 'TXN-2024-002',
                reference_number: 'PD-2024-001',
                description: 'Faculty Payroll Disbursement - December',
                date: '2024-12-15',
                status: 'pending',
                total_amount: 128500.00,
                created_at: '2024-12-15T14:00:00Z'
            },
            {
                id: 'TXN-2024-003',
                reference_number: 'UP-2024-001',
                description: 'Utility Payment - DECECO Electric',
                date: '2024-12-10',
                status: 'pending',
                total_amount: 8750.00,
                created_at: '2024-12-10T09:15:00Z'
            },
            {
                id: 'TXN-2024-004',
                reference_number: 'JE-2024-002',
                description: 'Laboratory Equipment Purchase',
                date: '2024-12-08',
                status: 'posted',
                total_amount: 75000.00,
                created_at: '2024-12-08T11:20:00Z'
            },
            {
                id: 'TXN-2024-005',
                reference_number: 'JE-2024-003',
                description: 'Student Activity Fund Allocation',
                date: '2024-12-05',
                status: 'posted',
                total_amount: 25000.00,
                created_at: '2024-12-05T16:45:00Z'
            }
        ];

        // Apply status filter
        const selectedStatus = this.elements.filterStatus?.value;
        let filtered = mockTransactions;
        if (selectedStatus && selectedStatus !== 'all') {
            filtered = mockTransactions.filter(txn => txn.status === selectedStatus);
        }

        this.totalPages = 1;
        this.updateTransactionCount(filtered.length);
        this.renderTransactions(filtered);
        this.updatePagination();
        this.checkPendingTxnOpen();
    }

    showLoading() {
        this.elements.transactionsBody.innerHTML = `
            <tr class="loading-row">
                <td colspan="6">
                    <div class="loading-spinner">Loading transactions...</div>
                </td>
            </tr>
        `;
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
        const amount = this.formatCurrency(txn.total_amount || this.calculateTotal(txn.lines));
        const status = txn.status || 'draft';
        const safeId = this.escapeHtml(txn.id || '');
        const safeStatus = this.escapeHtml(status);

        return `
            <tr class="clickable-row" data-id="${safeId}">
                <td><code>${this.escapeHtml(txn.id?.substring(0, 8) || 'N/A')}...</code></td>
                <td>${this.escapeHtml(date)}</td>
                <td>${this.escapeHtml(txn.description || 'No description')}</td>
                <td style="font-family: var(--font-mono); text-align: right;">${this.escapeHtml(amount)}</td>
                <td><span class="status-badge ${safeStatus}">${safeStatus}</span></td>
                <td>
                    <!-- Actions performed will appear here -->
                </td>
            </tr>
        `;
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
        this.elements.transactionForm.reset();
        this.setDefaultDate();
        this.elements.linesContainer.innerHTML = '';
        this.lineCounter = 0;

        // Add two initial lines
        this.addLine();
        this.addLine();

        this.updateBalanceCheck();
        this.elements.transactionModal.classList.add('active');
    }

    closeModal() {
        this.elements.transactionModal.classList.remove('active');
    }

    addLine() {
        this.lineCounter++;
        const lineHtml = `
            <div class="line-row" data-line="${this.lineCounter}">
                <select name="account_${this.lineCounter}" class="form-select line-account" required>
                    <option value="">Select Account</option>
                    ${this.accounts.map(acc => `
                        <option value="${acc.id}">${this.escapeHtml(acc.code)} - ${this.escapeHtml(acc.name)}</option>
                    `).join('')}
                </select>
                <input type="number" name="debit_${this.lineCounter}" class="form-input line-debit" 
                       placeholder="Debit" step="0.01" min="0">
                <input type="number" name="credit_${this.lineCounter}" class="form-input line-credit" 
                       placeholder="Credit" step="0.01" min="0">
                <button type="button" class="btn-remove-line" data-line="${this.lineCounter}">x</button>
            </div>
        `;

        this.elements.linesContainer.insertAdjacentHTML('beforeend', lineHtml);

        // Bind events for new line
        const lineRow = this.elements.linesContainer.querySelector(`[data-line="${this.lineCounter}"]`);
        lineRow.querySelector('.line-debit').addEventListener('input', () => this.updateBalanceCheck());
        lineRow.querySelector('.line-credit').addEventListener('input', () => this.updateBalanceCheck());
        lineRow.querySelector('.btn-remove-line').addEventListener('click', (e) => {
            lineRow.remove();
            this.updateBalanceCheck();
        });
    }

    updateBalanceCheck() {
        let totalDebits = 0;
        let totalCredits = 0;

        this.elements.linesContainer.querySelectorAll('.line-row').forEach(row => {
            const debit = parseFloat(row.querySelector('.line-debit').value) || 0;
            const credit = parseFloat(row.querySelector('.line-credit').value) || 0;
            totalDebits += debit;
            totalCredits += credit;
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

        const data = {
            date: this.elements.txnDate.value,
            description: document.getElementById('txnDescription').value,
            reference: document.getElementById('txnReference').value || null,
            lines: lines
        };

        this.elements.btnSubmitTransaction.disabled = true;
        this.elements.btnSubmitTransaction.textContent = 'Creating...';

        try {
            await api.createTransaction(data, this.selectedCompanyId);
            this.closeModal();
            this.loadTransactions();
        } catch (error) {
            alert(`Failed to create transaction: ${error.message}`);
        } finally {
            this.elements.btnSubmitTransaction.disabled = false;
            this.elements.btnSubmitTransaction.textContent = 'Create Transaction';
        }
    }

    collectLines() {
        const lines = [];
        this.elements.linesContainer.querySelectorAll('.line-row').forEach(row => {
            const accountId = row.querySelector('.line-account').value;
            const debit = parseFloat(row.querySelector('.line-debit').value) || 0;
            const credit = parseFloat(row.querySelector('.line-credit').value) || 0;

            if (accountId && (debit > 0 || credit > 0)) {
                lines.push({
                    account_id: accountId,
                    debit: debit,
                    credit: credit
                });
            }
        });
        return lines;
    }

    // ========== Transaction Detail ==========

    async viewTransaction(id) {
        try {
            // Try API first, fallback to mock data
            let txn;
            try {
                const result = await api.getTransaction(id, this.selectedCompanyId);
                txn = result?.data;
            } catch {
                // Fallback to mock data
                txn = this.getMockTransaction(id);
            }

            if (!txn) throw new Error('Transaction not found');

            this.currentDetailTxn = txn;
            this.renderTransactionDetail(txn);
            this.elements.detailModal.classList.add('active');
        } catch (error) {
            alert(`Failed to load transaction: ${error.message}`);
        }
    }

    getMockTransaction(id) {
        const mockData = {
            'TXN-2024-001': {
                id: 'TXN-2024-001',
                reference_number: 'JE-2024-001',
                description: 'Tuition Fee Collection - 1st Semester',
                date: '2024-12-18',
                status: 'pending',
                total_amount: 45000.00,
                scenario: 'Receivable Collection',
                scenario_detail: 'Payment received. Increases Cash, decreases Receivable/Revenue recognition.',
                lines: [
                    { account_name: 'Cash in Bank - BDO', debit: 45000, credit: 0 },
                    { account_name: 'Tuition Revenue', debit: 0, credit: 45000 }
                ]
            },
            'TXN-2024-002': {
                id: 'TXN-2024-002',
                reference_number: 'PD-2024-001',
                description: 'Faculty Payroll Disbursement - December',
                date: '2024-12-15',
                status: 'pending',
                total_amount: 128500.00,
                scenario: 'Expense Payment',
                scenario_detail: 'Disbursement for operating expenses. Increases Expense, decreases Cash.',
                lines: [
                    { account_name: 'Salaries Expense - Faculty', debit: 128500, credit: 0 },
                    { account_name: 'Cash in Bank - BDO', debit: 0, credit: 128500 }
                ]
            },
            'TXN-2024-003': {
                id: 'TXN-2024-003',
                reference_number: 'UP-2024-001',
                description: 'Utility Payment - DECECO Electric',
                date: '2024-12-10',
                status: 'pending',
                total_amount: 8750.00,
                scenario: 'Liability Settlement',
                scenario_detail: 'Payment to vendor. Decreases Payable/Cash.',
                lines: [
                    { account_name: 'Utilities Expense', debit: 8750, credit: 0 },
                    { account_name: 'Cash in Bank - BDO', debit: 0, credit: 8750 }
                ]
            },
            'TXN-2024-004': {
                id: 'TXN-2024-004',
                reference_number: 'JE-2024-002',
                description: 'Laboratory Equipment Purchase',
                date: '2024-12-08',
                status: 'posted',
                total_amount: 75000.00,
                scenario: 'Asset Acquisition',
                scenario_detail: 'Capital expenditure. Increases Fixed Asset, decreases Cash.',
                lines: [
                    { account_name: 'Laboratory Equipment', debit: 75000, credit: 0 },
                    { account_name: 'Cash in Bank - BDO', debit: 0, credit: 75000 }
                ]
            },
            'TXN-2024-005': {
                id: 'TXN-2024-005',
                reference_number: 'JE-2024-003',
                description: 'Fund Allocation',
                date: '2024-12-05',
                status: 'posted',
                total_amount: 25000.00,
                scenario: 'Internal Transfer',
                scenario_detail: 'Inter-account transfer. No net effect on total equity.',
                lines: [
                    { account_name: 'Activity Fund', debit: 25000, credit: 0 },
                    { account_name: 'General Fund', debit: 0, credit: 25000 }
                ]
            }
        };
        return mockData[id];
    }

    renderTransactionDetail(txn) {
        const date = new Date(txn.date || txn.created_at).toLocaleDateString();
        const status = txn.status || 'draft';
        const totalDebit = (txn.lines || []).reduce((sum, l) => sum + (l.debit || 0), 0);
        const totalCredit = (txn.lines || []).reduce((sum, l) => sum + (l.credit || 0), 0);

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
                        <span>${date}</span>
                    </div>
                    <div class="detail-item">
                        <label>Status</label>
                        <span><span class="status-badge ${status}">${status}</span></span>
                    </div>
                    <div class="detail-item">
                        <label>Amount</label>
                        <span class="amount-highlight">${this.formatCurrency(txn.total_amount)}</span>
                    </div>
                </div>
            </div>
            <div class="detail-section">
                <h4>Description</h4>
                <p>${this.escapeHtml(txn.description || 'No description')}</p>
            </div>
            ${txn.scenario ? `
            <div class="detail-section scenario-section">
                <div class="scenario-badge">${this.escapeHtml(txn.scenario)}</div>
                <p class="scenario-detail">${this.escapeHtml(txn.scenario_detail || '')}</p>
            </div>
            ` : ''}
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
                        ${(txn.lines || []).map(line => `
                            <tr>
                                <td>${this.escapeHtml(line.account_name || line.account_id)}</td>
                                <td class="amount debit">${line.debit > 0 ? this.formatCurrency(line.debit) : ''}</td>
                                <td class="amount credit">${line.credit > 0 ? this.formatCurrency(line.credit) : ''}</td>
                            </tr>
                        `).join('')}
                        <tr class="totals-row">
                            <td><strong>Total</strong></td>
                            <td class="amount debit"><strong>${this.formatCurrency(totalDebit)}</strong></td>
                            <td class="amount credit"><strong>${this.formatCurrency(totalCredit)}</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            ${status === 'pending' ? `
            <div class="detail-section">
                <h4>Review Decision</h4>
                <div class="form-group">
                    <label for="reviewReason">Reason / Notes</label>
                    <textarea id="reviewReason" class="form-control" rows="3" placeholder="Enter reason for approval or rejection..."></textarea>
                </div>
            </div>
            ` : ''}
        `;

        // Render action buttons based on status
        let actions = '<button class="btn btn-secondary" onclick="transactionsManager.closeDetailModal()">Close</button>';

        if (status === 'pending') {
            actions = `
                <button class="btn btn-secondary" onclick="transactionsManager.closeDetailModal()">Close</button>
                <button class="btn btn-danger" onclick="transactionsManager.rejectTransaction('${txn.id}')">Reject</button>
                <button class="btn btn-success" onclick="transactionsManager.approveTransaction('${txn.id}')">Approve</button>
            `;
        } else if (status === 'approved') {
            actions = `
                <button class="btn btn-secondary" onclick="transactionsManager.closeDetailModal()">Close</button>
                <button class="btn btn-primary" onclick="transactionsManager.postTransaction('${txn.id}')">Post Transaction</button>
            `;
        } else if (status === 'posted') {
            actions = `
                <button class="btn btn-secondary" onclick="transactionsManager.closeDetailModal()">Close</button>
                <button class="btn btn-danger" onclick="transactionsManager.voidTransaction('${txn.id}')">Void Transaction</button>
            `;
        }

        this.elements.detailActions.innerHTML = actions;
    }

    closeDetailModal() {
        this.elements.detailModal.classList.remove('active');
    }

    // ========== Transaction Actions ==========

    rejectTransaction(id) {
        this.showConfirmModal({
            title: 'Reject Transaction',
            message: `Are you sure you want to reject transaction ${id.substring(0, 12)}...?`,
            showInput: true,
            inputRequired: true,
            buttonText: 'Reject',
            buttonClass: 'btn-danger',
            onConfirm: (reason) => {
                // Mock rejection
                console.log(`Transaction ${id} rejected with reason: ${reason}`);
                this.closeConfirmModal();
                this.closeDetailModal();
                this.loadMockTransactions();
            }
        });
    }

    approveTransaction(id) {
        this.showConfirmModal({
            title: 'Approve Transaction',
            message: `Are you sure you want to approve transaction ${id.substring(0, 12)}...?`,
            showInput: true,
            inputRequired: false,
            buttonText: 'Approve',
            buttonClass: 'btn-success',
            onConfirm: (reason) => {
                // Mock approval
                console.log(`Transaction ${id} approved with reason: ${reason || 'No reason provided'}`);
                this.closeConfirmModal();
                this.closeDetailModal();
                this.loadMockTransactions();
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
            this.elements.confirmAmount.textContent = this.formatCurrency(txn.total_amount);
            this.elements.confirmDescription.textContent = txn.description || '-';

            // Generate impact preview
            const isApprove = options.buttonClass === 'btn-success';
            const lines = txn.lines || [];

            let accountsHtml = '';
            lines.forEach(line => {
                const impactType = line.debit > 0 ? 'increase' : 'decrease';
                const impactLabel = line.debit > 0 ? 'DEBIT' : 'CREDIT';
                const amount = line.debit > 0 ? line.debit : line.credit;
                accountsHtml += `
                    <div class="impact-account">
                        <span class="account-name">${this.escapeHtml(line.account_name)}</span>
                        <span class="impact-type ${impactType}">${impactLabel}</span>
                        <span style="font-family: monospace; color: #fff;">${this.formatCurrency(amount)}</span>
                    </div>
                `;
            });
            this.elements.impactAccounts.innerHTML = accountsHtml;

            // Result message
            if (isApprove) {
                this.elements.impactResult.innerHTML = '✓ Upon approval, this transaction will be moved to <strong>Approved</strong> status and ready for posting.';
            } else {
                this.elements.impactResult.innerHTML = '✗ Upon rejection, this transaction will be marked as <strong>Rejected</strong> and no entries will be recorded.';
            }
        }

        this.elements.confirmInputWrapper.style.display = options.showInput ? 'block' : 'none';
        this.elements.confirmReason.value = '';
        this.elements.confirmReason.style.borderColor = '';

        this.elements.btnConfirmAction.textContent = options.buttonText;
        this.elements.btnConfirmAction.className = `btn ${options.buttonClass}`;

        this.elements.confirmModal.classList.add('active');
    }

    closeConfirmModal() {
        this.elements.confirmModal.classList.remove('active');
        this.confirmCallback = null;
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
        if (!confirm('Are you sure you want to post this transaction? This action cannot be undone.')) {
            return;
        }

        try {
            await api.postTransaction(id, this.selectedCompanyId);
            this.closeDetailModal();
            this.loadTransactions();
        } catch (error) {
            alert(`Failed to post transaction: ${error.message}`);
        }
    }

    async voidTransaction(id) {
        if (!confirm('Are you sure you want to void this transaction? This will create reversing entries.')) {
            return;
        }

        try {
            await api.voidTransaction(id, this.selectedCompanyId);
            this.closeDetailModal();
            this.loadTransactions();
        } catch (error) {
            alert(`Failed to void transaction: ${error.message}`);
        }
    }

    // ========== Utilities ==========

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
