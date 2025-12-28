/**
 * Income Statement Page JavaScript
 * Generates and displays income statement (profit & loss) reports
 */

(function () {
    'use strict';

    // State
    let currentCompanyId = null;
    let currentNetIncomeCents = 0;

    // DOM Elements
    const elements = {
        filterCompany: document.getElementById('filterCompany'),
        filterStartDate: document.getElementById('filterStartDate'),
        filterEndDate: document.getElementById('filterEndDate'),
        btnGenerate: document.getElementById('btnGenerate'),
        btnRefresh: document.getElementById('btnRefresh'),
        btnPrint: document.getElementById('btnPrint'),
        btnClosePeriod: document.getElementById('btnClosePeriod'),
        btnLogout: document.getElementById('btnLogout'),
        userName: document.getElementById('userName'),
        // Summary
        summaryCard: document.getElementById('summaryCard'),
        totalRevenue: document.getElementById('totalRevenue'),
        totalExpenses: document.getElementById('totalExpenses'),
        netIncome: document.getElementById('netIncome'),
        profitLossStatus: document.getElementById('profitLossStatus'),
        generatedAt: document.getElementById('generatedAt'),
        // Content
        incomeStatementContent: document.getElementById('incomeStatementContent'),
        revenueBody: document.getElementById('revenueBody'),
        expensesBody: document.getElementById('expensesBody'),
        revenueSubtotal: document.getElementById('revenueSubtotal'),
        expensesSubtotal: document.getElementById('expensesSubtotal'),
        netIncomeAmount: document.getElementById('netIncomeAmount'),
        // States
        emptyState: document.getElementById('emptyState'),
        // Close Period Modal
        closePeriodModal: document.getElementById('closePeriodModal'),
        periodDates: document.getElementById('periodDates'),
        netIncomeDisplay: document.getElementById('netIncomeDisplay'),
        closeModalX: document.getElementById('closeModalX'),
        cancelClosePeriod: document.getElementById('cancelClosePeriod'),
        confirmClosePeriod: document.getElementById('confirmClosePeriod')
    };

    // Initialize
    async function init() {
        // Check authentication first
        const token = localStorage.getItem('auth_token');
        if (!token) {
            window.location.href = '/login.html';
            return;
        }

        // Set default dates (current year)
        const now = new Date();
        const yearStart = new Date(now.getFullYear(), 0, 1);
        elements.filterStartDate.value = yearStart.toISOString().split('T')[0];
        elements.filterEndDate.value = now.toISOString().split('T')[0];

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

    // Load companies for filter
    async function loadCompanies() {
        try {
            const response = await api.get('/companies?active_only=true');
            if (!response) {
                // Auth redirect happened
                return;
            }
            const companies = response.data || [];

            elements.filterCompany.innerHTML = companies.length > 0
                ? companies.map(c => `<option value="${c.id}">${c.name}</option>`).join('')
                : '<option value="">No companies available</option>';

            const storedId = localStorage.getItem('company_id');
            let targetId = null;

            if (storedId && companies.some(c => c.id === storedId)) {
                targetId = storedId;
            } else if (companies.length > 0) {
                targetId = companies[0].id;
                localStorage.setItem('company_id', targetId);
            }

            currentCompanyId = targetId;
            if (currentCompanyId) {
                elements.filterCompany.value = currentCompanyId;
            }
        } catch (error) {
            console.error('Failed to load companies:', error);
            const msg = error.message?.toLowerCase() || '';
            if (msg.includes('authentication') || msg.includes('session') || msg.includes('token') || msg.includes('expired')) {
                localStorage.removeItem('auth_token');
                window.location.href = '/login.html';
                return;
            }
            elements.filterCompany.innerHTML = '<option value="">No companies available</option>';
        }
    }

    // Generate income statement
    async function generateIncomeStatement() {
        if (!currentCompanyId) {
            showError('Please select a company');
            return;
        }

        showLoading();

        try {
            const startDate = elements.filterStartDate.value || null;
            const endDate = elements.filterEndDate.value || null;
            const response = await api.getIncomeStatement(startDate, endDate);
            const data = response.data;

            if (!data) {
                showEmptyState();
                return;
            }



            // Update summary
            updateSummary(data);

            // Render content
            renderIncomeStatement(data);

            // Show generated timestamp
            elements.generatedAt.textContent = `Generated: ${formatDateTime(data.generated_at)}`;

        } catch (error) {
            console.error('Failed to generate income statement:', error);
            showError(error.message);
        }
    }

    // Update summary card
    function updateSummary(data) {
        elements.summaryCard.style.display = 'grid';
        elements.totalRevenue.textContent = formatCurrency(data.total_revenue_cents / 100);
        elements.totalExpenses.textContent = formatCurrency(data.total_expenses_cents / 100);

        const netIncomeCents = data.net_income_cents;
        elements.netIncome.textContent = formatCurrency(Math.abs(netIncomeCents) / 100);
        elements.netIncome.className = 'summary-value ' + (netIncomeCents >= 0 ? 'profit' : 'loss');

        if (data.is_profit) {
            elements.profitLossStatus.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="14" height="14" fill="currentColor">
                    <path d="M4.53 4.75A.75.75 0 0 1 5.28 4h6.01a.75.75 0 0 1 .75.75v6.01a.75.75 0 0 1-1.5 0v-4.2l-5.26 5.261a.749.749 0 0 1-1.275-.326.749.749 0 0 1 .215-.734L9.48 5.5H5.28a.75.75 0 0 1-.75-.75Z"></path>
                </svg>
                PROFIT`;
            elements.profitLossStatus.className = 'profit-loss-status profit';
        } else if (data.is_loss) {
            elements.profitLossStatus.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="14" height="14" fill="currentColor">
                    <path d="M4.22 4.179a.75.75 0 0 1 1.06 0l5.26 5.26v-4.2a.75.75 0 0 1 1.5 0v6.01a.75.75 0 0 1-.75.75H5.28a.75.75 0 0 1 0-1.5h4.2L4.22 5.239a.75.75 0 0 1 0-1.06Z"></path>
                </svg>
                LOSS`;
            elements.profitLossStatus.className = 'profit-loss-status loss';
        } else {
            elements.profitLossStatus.innerHTML = `BREAK EVEN`;
            elements.profitLossStatus.className = 'profit-loss-status';
        }
    }

    // Render income statement
    function renderIncomeStatement(data) {
        hideAllStates();
        elements.incomeStatementContent.style.display = 'block';

        // Revenue accounts
        if (data.revenue_accounts && data.revenue_accounts.length > 0) {
            Security.safeInnerHTML(elements.revenueBody, data.revenue_accounts.map(acc => `
                <tr>
                    <td class="col-code"><code>${Security.escapeHtml(acc.account_code)}</code></td>
                    <td class="col-name">${Security.escapeHtml(acc.account_name)}</td>
                    <td class="col-amount">${formatCurrency(acc.amount_cents / 100)}</td>
                </tr>
            `).join(''));
        } else {
            elements.revenueBody.innerHTML = `
                <tr><td colspan="3" class="empty-message">No revenue accounts</td></tr>
            `;
        }
        elements.revenueSubtotal.textContent = formatCurrency(data.total_revenue_cents / 100);

        // Expense accounts
        if (data.expense_accounts && data.expense_accounts.length > 0) {
            Security.safeInnerHTML(elements.expensesBody, data.expense_accounts.map(acc => `
                <tr>
                    <td class="col-code"><code>${Security.escapeHtml(acc.account_code)}</code></td>
                    <td class="col-name">${Security.escapeHtml(acc.account_name)}</td>
                    <td class="col-amount">${formatCurrency(acc.amount_cents / 100)}</td>
                </tr>
            `).join(''));
        } else {
            elements.expensesBody.innerHTML = `
                <tr><td colspan="3" class="empty-message">No expense accounts</td></tr>
            `;
        }
        elements.expensesSubtotal.textContent = formatCurrency(data.total_expenses_cents / 100);

        // Net income
        const netIncome = data.net_income_cents / 100;
        elements.netIncomeAmount.textContent = formatCurrency(Math.abs(netIncome));
        elements.netIncomeAmount.className = 'net-income-amount ' + (netIncome >= 0 ? 'profit' : 'loss');

        if (netIncome < 0) {
            elements.netIncomeAmount.textContent = `(${formatCurrency(Math.abs(netIncome))})`;
        }

        // Show print button
        if (elements.btnPrint) {
            elements.btnPrint.style.display = 'inline-flex';
        }

        // Show close period button and store net income
        currentNetIncomeCents = data.net_income_cents;
        if (elements.btnClosePeriod) {
            elements.btnClosePeriod.style.display = 'inline-flex';
        }
    }

    // Event listeners
    function setupEventListeners() {
        elements.filterCompany.addEventListener('change', (e) => {
            currentCompanyId = e.target.value;
            localStorage.setItem('company_id', currentCompanyId);
        });

        elements.btnGenerate.addEventListener('click', generateIncomeStatement);
        elements.btnRefresh.addEventListener('click', generateIncomeStatement);

        elements.btnLogout.addEventListener('click', logout);

        if (elements.btnPrint) {
            elements.btnPrint.addEventListener('click', () => window.print());
        }

        // Close Period Modal
        if (elements.btnClosePeriod) {
            elements.btnClosePeriod.addEventListener('click', openClosePeriodModal);
        }
        if (elements.closeModalX) {
            elements.closeModalX.addEventListener('click', closeClosePeriodModal);
        }
        if (elements.cancelClosePeriod) {
            elements.cancelClosePeriod.addEventListener('click', closeClosePeriodModal);
        }
        if (elements.confirmClosePeriod) {
            elements.confirmClosePeriod.addEventListener('click', submitClosePeriod);
        }
    }

    // UI State functions
    function showLoading() {
        hideAllStates();
        elements.incomeStatementContent.style.display = 'block';
        elements.revenueBody.innerHTML = `
            <tr><td colspan="3" class="loading-message">Loading...</td></tr>
        `;
        elements.expensesBody.innerHTML = `
            <tr><td colspan="3" class="loading-message">Loading...</td></tr>
        `;
    }

    function showEmptyState() {
        elements.summaryCard.style.display = 'none';
        elements.incomeStatementContent.style.display = 'none';
        elements.emptyState.style.display = 'flex';
        elements.generatedAt.textContent = '';
    }

    function hideAllStates() {
        elements.emptyState.style.display = 'none';
    }

    function showError(message) {
        elements.incomeStatementContent.style.display = 'block';
        const errorHtml = `
            <tr><td colspan="3" class="error-message">Error: ${Security.escapeHtml(message)}</td></tr>
        `;
        Security.safeInnerHTML(elements.revenueBody, errorHtml);
        elements.expensesBody.innerHTML = '';
    }

    // Utility functions
    function formatCurrency(amount) {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(amount);
    }

    function formatDateTime(dateStr) {
        return new Date(dateStr).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }



    async function logout() {
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

    // Close Period Modal Functions
    function openClosePeriodModal() {
        const startDate = elements.filterStartDate.value;
        const endDate = elements.filterEndDate.value;

        // Update modal display
        if (elements.periodDates) {
            elements.periodDates.textContent = `${startDate} to ${endDate}`;
        }
        if (elements.netIncomeDisplay) {
            elements.netIncomeDisplay.textContent = formatCurrency(currentNetIncomeCents / 100);
            elements.netIncomeDisplay.className = 'net-income-display ' + (currentNetIncomeCents >= 0 ? 'profit' : 'loss');
        }

        elements.closePeriodModal.style.display = 'flex';
    }

    function closeClosePeriodModal() {
        elements.closePeriodModal.style.display = 'none';
    }

    async function submitClosePeriod() {
        const startDate = elements.filterStartDate.value;
        const endDate = elements.filterEndDate.value;

        elements.confirmClosePeriod.disabled = true;
        elements.confirmClosePeriod.textContent = 'Submitting...';

        try {
            const response = await api.post(`/companies/${currentCompanyId}/period-close`, {
                start_date: startDate,
                end_date: endDate,
                net_income_cents: currentNetIncomeCents
            });

            closeClosePeriodModal();
            alert('Period close request submitted for approval.');

            // Hide the close period button since request is pending
            if (elements.btnClosePeriod) {
                elements.btnClosePeriod.style.display = 'none';
            }
        } catch (error) {
            console.error('Failed to submit period close:', error);
            alert('Error: ' + (error.message || 'Failed to submit period close request'));
        } finally {
            elements.confirmClosePeriod.disabled = false;
            elements.confirmClosePeriod.textContent = 'Submit for Approval';
        }
    }

    // Initialize on DOM load
    document.addEventListener('DOMContentLoaded', init);
})();
