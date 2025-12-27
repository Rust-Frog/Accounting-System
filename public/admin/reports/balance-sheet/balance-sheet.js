/**
 * Balance Sheet Page Controller
 * Handles UI interactions and API calls for balance sheet report generation.
 */

class BalanceSheetController {
    constructor() {
        this.companies = [];
        this.currentData = null;
        this.init();
    }

    async init() {
        // Check authentication first
        const token = localStorage.getItem('auth_token');
        if (!token) {
            window.location.href = '/login.html';
            return;
        }

        this.bindElements();
        this.bindEvents();
        this.setDefaultDate();
        await this.loadUserInfo();
        await this.loadCompanies();
    }

    bindElements() {
        this.companySelect = document.getElementById('companySelect');
        this.asOfDateInput = document.getElementById('asOfDate');
        this.generateBtn = document.getElementById('generateBtn');
        this.btnRefresh = document.getElementById('btnRefresh');
        this.btnPrint = document.getElementById('btnPrint');
        this.generatedAt = document.getElementById('generatedAt');
        this.userName = document.getElementById('userName');
        this.btnLogout = document.getElementById('btnLogout');

        // Summary elements
        this.totalAssetsEl = document.getElementById('totalAssets');
        this.totalLiabilitiesEl = document.getElementById('totalLiabilities');
        this.totalEquityEl = document.getElementById('totalEquity');
        this.balanceStatusEl = document.getElementById('balanceStatus');

        // Content sections
        this.balanceSheetContent = document.getElementById('balanceSheetContent');
        this.emptyState = document.getElementById('emptyState');

        // Table bodies
        this.currentAssetsBody = document.getElementById('currentAssetsBody');
        this.fixedAssetsBody = document.getElementById('fixedAssetsBody');
        this.currentLiabilitiesBody = document.getElementById('currentLiabilitiesBody');
        this.longTermLiabilitiesBody = document.getElementById('longTermLiabilitiesBody');
        this.equityBody = document.getElementById('equityBody');

        // Subtotals
        this.currentAssetsSubtotal = document.getElementById('currentAssetsSubtotal');
        this.fixedAssetsSubtotal = document.getElementById('fixedAssetsSubtotal');
        this.currentLiabilitiesSubtotal = document.getElementById('currentLiabilitiesSubtotal');
        this.longTermLiabilitiesSubtotal = document.getElementById('longTermLiabilitiesSubtotal');
        this.retainedEarningsEl = document.getElementById('retainedEarnings');
        this.equitySubtotal = document.getElementById('equitySubtotal');

        // Section totals
        this.totalAssetsSection = document.getElementById('totalAssetsSection');
        this.totalLiabilitiesSection = document.getElementById('totalLiabilitiesSection');
        this.totalEquitySection = document.getElementById('totalEquitySection');

        // Equation elements
        this.eqAssets = document.getElementById('eqAssets');
        this.eqLiabilities = document.getElementById('eqLiabilities');
        this.eqEquity = document.getElementById('eqEquity');
        this.equationResult = document.getElementById('equationResult');
    }

    bindEvents() {
        this.generateBtn.addEventListener('click', () => this.generateReport());
        if (this.btnRefresh) {
            this.btnRefresh.addEventListener('click', () => this.generateReport());
        }
        this.companySelect.addEventListener('change', () => this.onCompanyChange());
        if (this.btnLogout) {
            this.btnLogout.addEventListener('click', () => this.logout());
        }
        if (this.btnPrint) {
            this.btnPrint.addEventListener('click', () => window.print());
        }
    }

    setDefaultDate() {
        const today = new Date().toISOString().split('T')[0];
        this.asOfDateInput.value = today;
    }

    async loadUserInfo() {
        try {
            const user = await api.get('/auth/me');
            if (user.data && this.userName) {
                this.userName.textContent = user.data.username || 'User';
            }
        } catch (error) {
            console.error('Failed to load user info:', error);
        }
    }

    async loadCompanies() {
        try {
            const response = await api.getActiveCompanies();
            if (!response) {
                // Auth redirect happened
                return;
            }
            this.companies = response.data || [];
            this.renderCompanyOptions();
        } catch (error) {
            console.error('Failed to load companies:', error);
            const msg = error.message?.toLowerCase() || '';
            if (msg.includes('authentication') || msg.includes('session') || msg.includes('token') || msg.includes('expired')) {
                localStorage.removeItem('auth_token');
                window.location.href = '/login.html';
                return;
            }
            this.showError('No companies available');
        }
    }

    renderCompanyOptions() {
        this.companySelect.innerHTML = this.companies.length > 0
            ? this.companies.map(company =>
                `<option value="${company.id}">${company.name}</option>`
            ).join('')
            : '<option value="">No companies available</option>';

        // Restore stored company ID
        const storedId = localStorage.getItem('company_id');
        if (storedId && this.companies.some(c => c.id === storedId)) {
            this.companySelect.value = storedId;
            api.setCompanyId(storedId);
        } else if (this.companies.length > 0) {
            localStorage.setItem('company_id', this.companies[0].id);
            api.setCompanyId(this.companies[0].id);
        }
    }

    onCompanyChange() {
        const companyId = this.companySelect.value;
        localStorage.setItem('company_id', companyId);
        api.setCompanyId(companyId);
        this.clearReport();
    }

    clearReport() {
        this.currentData = null;
        this.balanceSheetContent.style.display = 'none';
        this.emptyState.style.display = 'flex';
        this.generatedAt.textContent = '';

        // Reset summary
        this.totalAssetsEl.textContent = '$0.00';
        this.totalLiabilitiesEl.textContent = '$0.00';
        this.totalEquityEl.textContent = '$0.00';
        this.balanceStatusEl.innerHTML = '';
    }

    async generateReport() {
        const asOfDate = this.asOfDateInput.value || null;

        this.generateBtn.disabled = true;
        this.generateBtn.innerHTML = `
            <svg class="spin" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="16" height="16" fill="currentColor">
                <path d="M8 0a8 8 0 1 1 0 16A8 8 0 0 1 8 0ZM1.5 8a6.5 6.5 0 1 0 13 0 6.5 6.5 0 0 0-13 0Z" opacity=".25"></path>
                <path d="M8 0a8 8 0 0 1 8 8h-1.5A6.5 6.5 0 0 0 8 1.5V0Z"></path>
            </svg>
            Generating...
        `;

        try {
            const response = await api.getBalanceSheet(asOfDate);
            this.currentData = response.data;
            this.renderReport();
        } catch (error) {
            console.error('Failed to generate balance sheet:', error);
            this.showError(error.message || 'Failed to generate balance sheet');
        } finally {
            this.generateBtn.disabled = false;
            this.generateBtn.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="16" height="16" fill="currentColor">
                    <path d="M1.705 8.005a.75.75 0 0 1 .834.656 5.5 5.5 0 0 0 9.592 2.97l-1.204-1.204a.25.25 0 0 1 .177-.427h3.646a.25.25 0 0 1 .25.25v3.646a.25.25 0 0 1-.427.177l-1.38-1.38A7.002 7.002 0 0 1 1.05 8.84a.75.75 0 0 1 .656-.834ZM8 2.5a5.487 5.487 0 0 0-4.131 1.869l1.204 1.204A.25.25 0 0 1 4.896 6H1.25A.25.25 0 0 1 1 5.75V2.104a.25.25 0 0 1 .427-.177l1.38 1.38A7.002 7.002 0 0 1 14.95 7.16a.75.75 0 0 1-1.49.178A5.5 5.5 0 0 0 8 2.5Z"></path>
                </svg>
                Generate
            `;
        }
    }

    renderReport() {
        if (!this.currentData) return;

        const data = this.currentData;

        // Update generated timestamp
        this.generatedAt.textContent = `Generated: ${new Date(data.generated_at).toLocaleString()}`;

        // Update summary card
        const totalAssets = data.assets?.total_cents || 0;
        const totalLiabilities = data.liabilities?.total_cents || 0;
        const totalEquity = data.equity?.total_cents || 0;

        this.totalAssetsEl.textContent = this.formatCurrency(totalAssets);
        this.totalLiabilitiesEl.textContent = this.formatCurrency(totalLiabilities);
        this.totalEquityEl.textContent = this.formatCurrency(totalEquity);

        // Balance status
        const isBalanced = data.is_balanced;
        this.balanceStatusEl.innerHTML = isBalanced
            ? `<span class="balance-badge balanced">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="14" height="14" fill="currentColor">
                    <path d="M8 16A8 8 0 1 1 8 0a8 8 0 0 1 0 16Zm3.78-9.72a.751.751 0 0 0-.018-1.042.751.751 0 0 0-1.042-.018L6.75 9.19 5.28 7.72a.751.751 0 0 0-1.042.018.751.751 0 0 0-.018 1.042l2 2a.75.75 0 0 0 1.06 0Z"></path>
                </svg>
                BALANCED
              </span>`
            : `<span class="balance-badge unbalanced">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="14" height="14" fill="currentColor">
                    <path d="M2.343 13.657A8 8 0 1 1 13.658 2.343 8 8 0 0 1 2.343 13.657ZM6.03 4.97a.751.751 0 0 0-1.042.018.751.751 0 0 0-.018 1.042L6.94 8 4.97 9.97a.749.749 0 0 0 .326 1.275.749.749 0 0 0 .734-.215L8 9.06l1.97 1.97a.749.749 0 0 0 1.275-.326.749.749 0 0 0-.215-.734L9.06 8l1.97-1.97a.749.749 0 0 0-.326-1.275.749.749 0 0 0-.734.215L8 6.94Z"></path>
                </svg>
                UNBALANCED (${this.formatCurrency(data.difference_cents || 0)})
              </span>`;

        // Render Assets
        this.renderAccountRows(this.currentAssetsBody, data.assets?.current || []);
        this.renderAccountRows(this.fixedAssetsBody, data.assets?.fixed || []);
        this.currentAssetsSubtotal.textContent = this.formatCurrency(data.assets?.total_current_cents || 0);
        this.fixedAssetsSubtotal.textContent = this.formatCurrency(data.assets?.total_fixed_cents || 0);
        this.totalAssetsSection.textContent = this.formatCurrency(totalAssets);

        // Render Liabilities
        this.renderAccountRows(this.currentLiabilitiesBody, data.liabilities?.current || []);
        this.renderAccountRows(this.longTermLiabilitiesBody, data.liabilities?.long_term || []);
        this.currentLiabilitiesSubtotal.textContent = this.formatCurrency(data.liabilities?.total_current_cents || 0);
        this.longTermLiabilitiesSubtotal.textContent = this.formatCurrency(data.liabilities?.total_long_term_cents || 0);
        this.totalLiabilitiesSection.textContent = this.formatCurrency(totalLiabilities);

        // Render Equity
        this.renderAccountRows(this.equityBody, data.equity?.accounts || []);
        this.retainedEarningsEl.textContent = this.formatCurrency(data.equity?.retained_earnings_cents || 0);
        this.equitySubtotal.textContent = this.formatCurrency(totalEquity);
        this.totalEquitySection.textContent = this.formatCurrency(totalEquity);

        // Update equation
        this.eqAssets.textContent = this.formatCurrency(totalAssets);
        this.eqLiabilities.textContent = this.formatCurrency(totalLiabilities);
        this.eqEquity.textContent = this.formatCurrency(totalEquity);

        this.equationResult.innerHTML = isBalanced
            ? `<span class="equation-balanced">✓ The accounting equation is balanced</span>`
            : `<span class="equation-unbalanced">✗ The accounting equation is NOT balanced (Difference: ${this.formatCurrency(data.difference_cents || 0)})</span>`;

        // Show content, hide empty state
        this.balanceSheetContent.style.display = 'flex';
        this.emptyState.style.display = 'none';
        if (this.btnPrint) {
            this.btnPrint.style.display = 'inline-flex';
        }
    }

    renderAccountRows(tbody, accounts) {
        if (!accounts || accounts.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="3" class="empty-message">No accounts in this category</td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = accounts.map(account => `
            <tr>
                <td class="col-code"><code>${account.account_code}</code></td>
                <td class="col-name">${account.account_name}</td>
                <td class="col-amount">${this.formatCurrency(account.amount_cents)}</td>
            </tr>
        `).join('');
    }

    formatCurrency(cents) {
        const dollars = Math.abs(cents) / 100;
        const formatted = new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            minimumFractionDigits: 2
        }).format(dollars);
        return cents < 0 ? `(${formatted})` : formatted;
    }

    showError(message) {
        // Simple alert for now - could be enhanced with toast notifications
        alert(message);
    }

    async logout() {
        try {
            await api.post('/auth/logout', {});
        } catch (error) {
            console.error('Logout error:', error);
        } finally {
            localStorage.removeItem('auth_token');
            localStorage.removeItem('company_id');
            window.location.href = '/login.html';
        }
    }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    new BalanceSheetController();
});
