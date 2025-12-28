/**
 * Trial Balance Page JavaScript
 * Generates and displays trial balance reports
 */

(function () {
    'use strict';

    // State
    let currentCompanyId = null;

    // DOM Elements
    const elements = {
        trialBalanceBody: document.getElementById('trialBalanceBody'),
        trialBalanceTotals: document.getElementById('trialBalanceTotals'),
        trialBalancePanel: document.getElementById('trialBalancePanel'),
        filterCompany: document.getElementById('filterCompany'),
        filterAsOfDate: document.getElementById('filterAsOfDate'),
        btnGenerate: document.getElementById('btnGenerate'),
        btnRefresh: document.getElementById('btnRefresh'),
        btnLogout: document.getElementById('btnLogout'),
        userName: document.getElementById('userName'),
        // Summary
        summaryCard: document.getElementById('summaryCard'),
        totalDebits: document.getElementById('totalDebits'),
        totalCredits: document.getElementById('totalCredits'),
        difference: document.getElementById('difference'),
        balanceStatusText: document.getElementById('balanceStatusText'),
        balanceStatus: document.getElementById('balanceStatus'),
        generatedAt: document.getElementById('generatedAt'),
        // Footer totals
        footerTotalDebits: document.getElementById('footerTotalDebits'),
        footerTotalCredits: document.getElementById('footerTotalCredits'),
        // States
        emptyState: document.getElementById('emptyState'),
        // Print
        btnPrint: document.getElementById('btnPrint'),
    };

    // Initialize
    async function init() {
        // Check authentication first
        const token = localStorage.getItem('auth_token');
        if (!token) {
            window.location.href = '/login.html';
            return;
        }

        // Set default date to today
        elements.filterAsOfDate.value = new Date().toISOString().split('T')[0];

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

    // Generate trial balance
    async function generateTrialBalance() {
        if (!currentCompanyId) {
            showError('Please select a company');
            return;
        }

        showLoading();

        try {
            const asOfDate = elements.filterAsOfDate.value || null;
            const response = await api.getTrialBalance(asOfDate);
            const data = response.data;

            if (!data || !data.entries || data.entries.length === 0) {
                showEmptyState();
                return;
            }

            // Update summary
            updateSummary(data);

            // Render table
            renderTrialBalance(data.entries);

            // Update totals
            updateTotals(data.totals, data.is_balanced);

            // Show generated timestamp
            elements.generatedAt.textContent = `Generated: ${formatDateTime(data.generated_at)}`;

            // Show print button
            if (elements.btnPrint) {
                elements.btnPrint.style.display = 'inline-flex';
            }

        } catch (error) {
            console.error('Failed to generate trial balance:', error);
            showError(error.message);
        }
    }

    // Update summary card
    function updateSummary(data) {
        elements.summaryCard.style.display = 'grid';
        elements.totalDebits.textContent = formatCurrency(data.totals.debit_cents / 100);
        elements.totalCredits.textContent = formatCurrency(data.totals.credit_cents / 100);
        elements.difference.textContent = formatCurrency(data.difference_cents / 100);

        if (data.is_balanced) {
            elements.balanceStatusText.textContent = 'BALANCED';
            elements.balanceStatusText.className = 'summary-value balanced';
            elements.balanceStatus.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="14" height="14" fill="currentColor">
                    <path d="M8 0a8 8 0 1 1 0 16A8 8 0 0 1 8 0ZM1.5 8a6.5 6.5 0 1 0 13 0 6.5 6.5 0 0 0-13 0Zm9.78-2.22-4.5 4.5a.75.75 0 0 1-1.06 0l-2-2a.75.75 0 1 1 1.06-1.06l1.47 1.47 3.97-3.97a.75.75 0 1 1 1.06 1.06Z"></path>
                </svg>
                BALANCED`;
            elements.balanceStatus.className = 'balance-status balanced';
        } else {
            elements.balanceStatusText.textContent = 'UNBALANCED';
            elements.balanceStatusText.className = 'summary-value unbalanced';
            elements.balanceStatus.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="14" height="14" fill="currentColor">
                    <path d="M2.343 13.657A8 8 0 1 1 13.658 2.343 8 8 0 0 1 2.343 13.657ZM6.03 4.97a.751.751 0 0 0-1.042.018.751.751 0 0 0-.018 1.042L6.94 8 4.97 9.97a.749.749 0 0 0 .326 1.275.749.749 0 0 0 .734-.215L8 9.06l1.97 1.97a.749.749 0 0 0 1.275-.326.749.749 0 0 0-.215-.734L9.06 8l1.97-1.97a.749.749 0 0 0-.326-1.275.749.749 0 0 0-.734.215L8 6.94Z"></path>
                </svg>
                UNBALANCED`;
            elements.balanceStatus.className = 'balance-status unbalanced';
        }
    }

    // Render trial balance entries
    function renderTrialBalance(entries) {
        hideAllStates();
        elements.trialBalancePanel.style.display = 'block';
        elements.trialBalanceTotals.style.display = 'table-footer-group';

        const rows = entries.map(entry => `
            <tr>
                <td class="col-code"><code>${Security.escapeHtml(entry.account_code)}</code></td>
                <td class="col-name">${Security.escapeHtml(entry.account_name)}</td>
                <td class="col-type"><span class="type-badge type-${entry.account_type}">${capitalizeFirst(entry.account_type)}</span></td>
                <td class="col-debit amount">${entry.debit_cents > 0 ? formatCurrency(entry.debit_cents / 100) : ''}</td>
                <td class="col-credit amount">${entry.credit_cents > 0 ? formatCurrency(entry.credit_cents / 100) : ''}</td>
            </tr>
        `).join('');

        elements.trialBalanceBody.innerHTML = rows;
    }

    // Update totals row
    function updateTotals(totals, isBalanced) {
        elements.footerTotalDebits.textContent = formatCurrency(totals.debit_cents / 100);
        elements.footerTotalCredits.textContent = formatCurrency(totals.credit_cents / 100);

        // Highlight if unbalanced
        const totalsRow = elements.trialBalanceTotals.querySelector('.totals-row');
        if (totalsRow) {
            totalsRow.classList.toggle('unbalanced', !isBalanced);
        }
    }

    // Event listeners
    function setupEventListeners() {
        elements.filterCompany.addEventListener('change', (e) => {
            currentCompanyId = e.target.value;
            localStorage.setItem('company_id', currentCompanyId);
        });

        elements.btnGenerate.addEventListener('click', generateTrialBalance);
        elements.btnRefresh.addEventListener('click', generateTrialBalance);

        elements.filterAsOfDate.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') generateTrialBalance();
        });

        elements.btnLogout.addEventListener('click', logout);

        // Print button
        if (elements.btnPrint) {
            elements.btnPrint.addEventListener('click', () => window.print());
        }
    }

    // UI State functions
    function showLoading() {
        hideAllStates();
        elements.trialBalancePanel.style.display = 'block';
        elements.trialBalanceBody.innerHTML = `
            <tr class="loading-row">
                <td colspan="5">
                    <div class="loading-spinner">Generating trial balance...</div>
                </td>
            </tr>
        `;
    }

    function showEmptyState() {
        elements.summaryCard.style.display = 'none';
        elements.trialBalancePanel.style.display = 'none';
        elements.emptyState.style.display = 'flex';
        elements.generatedAt.textContent = '';
    }

    function hideAllStates() {
        elements.emptyState.style.display = 'none';
    }

    function showError(message) {
        elements.trialBalancePanel.style.display = 'block';
        elements.trialBalanceBody.innerHTML = `
            <tr class="error-row">
                <td colspan="5">
                    <div class="error-message">Error: ${Security.escapeHtml(message)}</div>
                </td>
            </tr>
        `;
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

    function capitalizeFirst(str) {
        return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
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

    // Initialize on DOM load
    document.addEventListener('DOMContentLoaded', init);
})();
