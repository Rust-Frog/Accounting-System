/**
 * General Ledger Page JavaScript
 * Handles ledger display with running balances per account
 */

(function () {
    'use strict';

    // State
    let currentCompanyId = null;
    let currentAccountId = null;
    let accounts = [];

    // DOM Elements
    const elements = {
        ledgerBody: document.getElementById('ledgerBody'),
        ledgerTotals: document.getElementById('ledgerTotals'),
        ledgerPanel: document.getElementById('ledgerPanel'),
        entryCount: document.getElementById('entryCount'),
        filterCompany: document.getElementById('filterCompany'),
        filterAccount: document.getElementById('filterAccount'),
        filterStartDate: document.getElementById('filterStartDate'),
        filterEndDate: document.getElementById('filterEndDate'),
        btnApplyFilter: document.getElementById('btnApplyFilter'),
        btnRefresh: document.getElementById('btnRefresh'),
        btnLogout: document.getElementById('btnLogout'),
        userName: document.getElementById('userName'),
        // Summary card
        accountSummary: document.getElementById('accountSummary'),
        summaryCode: document.getElementById('summaryCode'),
        summaryName: document.getElementById('summaryName'),
        summaryType: document.getElementById('summaryType'),
        summaryNormal: document.getElementById('summaryNormal'),
        summaryBalance: document.getElementById('summaryBalance'),
        summaryDebits: document.getElementById('summaryDebits'),
        summaryCredits: document.getElementById('summaryCredits'),
        summaryEntries: document.getElementById('summaryEntries'),
        // Totals
        totalDebits: document.getElementById('totalDebits'),
        totalCredits: document.getElementById('totalCredits'),
        // States
        noAccountState: document.getElementById('noAccountState'),
        emptyState: document.getElementById('emptyState'),
    };

    // Initialize
    async function init() {
        // Check authentication first
        const token = localStorage.getItem('auth_token');
        if (!token) {
            window.location.href = '/login.html';
            return;
        }

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

            const urlParams = new URLSearchParams(window.location.search);
            const urlCompanyId = urlParams.get('company');
            const storedId = localStorage.getItem('company_id');

            let targetId = null;
            if (urlCompanyId && companies.some(c => c.id === urlCompanyId)) {
                targetId = urlCompanyId;
                localStorage.setItem('company_id', targetId);
            } else if (storedId && companies.some(c => c.id === storedId)) {
                targetId = storedId;
            } else if (companies.length > 0) {
                targetId = companies[0].id;
                localStorage.setItem('company_id', targetId);
            }

            currentCompanyId = targetId;
            if (currentCompanyId) {
                elements.filterCompany.value = currentCompanyId;
                await loadAccounts();
            }

            // Check for account in URL
            const urlAccountId = urlParams.get('account');
            if (urlAccountId) {
                currentAccountId = urlAccountId;
                elements.filterAccount.value = urlAccountId;
                await loadLedger();
            } else {
                showNoAccountState();
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

    // Load accounts for selected company
    async function loadAccounts() {
        if (!currentCompanyId) return;

        try {
            const response = await api.get(`/companies/${currentCompanyId}/accounts`);
            accounts = response.data || [];

            // Sort by code
            accounts.sort((a, b) => a.code - b.code);

            Security.safeInnerHTML(elements.filterAccount,
                '<option value="">Select an account...</option>' +
                accounts.map(a => `<option value="${a.id}">${Security.escapeHtml(String(a.code))} - ${Security.escapeHtml(a.name)}</option>`).join('')
            );

            if (currentAccountId) {
                elements.filterAccount.value = currentAccountId;
            }
        } catch (error) {
            console.error('Failed to load accounts:', error);
            elements.filterAccount.innerHTML = '<option value="">Error loading accounts</option>';
        }
    }

    // Load ledger for selected account
    async function loadLedger() {
        if (!currentCompanyId || !currentAccountId) {
            showNoAccountState();
            return;
        }

        showLoading();

        try {
            const startDate = elements.filterStartDate.value || null;
            const endDate = elements.filterEndDate.value || null;

            const response = await api.getLedger(currentAccountId, startDate, endDate);
            const data = response.data;

            if (!data) {
                showEmptyState();
                return;
            }

            // Update summary card
            updateSummaryCard(data.account, data.totals);

            // Render entries
            if (data.entries.length === 0) {
                showEmptyState();
            } else {
                renderLedgerEntries(data.entries);
                updateTotals(data.totals);
                elements.entryCount.textContent = `${data.totals.entry_count} entries`;
            }
        } catch (error) {
            console.error('Failed to load ledger:', error);
            showError(error.message);
        }
    }

    // Update summary card
    function updateSummaryCard(account, totals) {
        elements.accountSummary.style.display = 'flex';
        elements.summaryCode.textContent = account.code;
        elements.summaryName.textContent = account.name;
        elements.summaryType.textContent = capitalizeFirst(account.type);
        elements.summaryType.className = `type-badge type-${account.type}`;
        elements.summaryNormal.textContent = `Normal: ${capitalizeFirst(account.normal_balance)}`;
        elements.summaryBalance.textContent = formatCurrency(account.current_balance_cents / 100);
        elements.summaryDebits.textContent = formatCurrency(totals.debit_cents / 100);
        elements.summaryCredits.textContent = formatCurrency(totals.credit_cents / 100);
        elements.summaryEntries.textContent = totals.entry_count;
    }

    // Render ledger entries
    function renderLedgerEntries(entries) {
        hideAllStates();
        elements.ledgerPanel.style.display = 'block';
        elements.ledgerTotals.style.display = 'table-footer-group';

        const rows = entries.map(entry => `
            <tr class="${entry.entry_type === 'REVERSAL' ? 'reversal-row' : ''}">
                <td class="col-date">${formatDate(entry.date)}</td>
                <td class="col-reference">
                    <code>${escapeHtml(truncateId(entry.reference))}</code>
                </td>
                <td class="col-description">${escapeHtml(entry.description)}</td>
                <td class="col-debit amount">${entry.debit_cents > 0 ? formatCurrency(entry.debit_cents / 100) : ''}</td>
                <td class="col-credit amount">${entry.credit_cents > 0 ? formatCurrency(entry.credit_cents / 100) : ''}</td>
                <td class="col-balance amount ${entry.balance_cents < 0 ? 'negative' : ''}">${formatCurrency(entry.balance_cents / 100)}</td>
            </tr>
        `).join('');

        Security.safeInnerHTML(elements.ledgerBody, rows);
    }

    // Update totals row
    function updateTotals(totals) {
        elements.totalDebits.textContent = formatCurrency(totals.debit_cents / 100);
        elements.totalCredits.textContent = formatCurrency(totals.credit_cents / 100);
    }

    // Event listeners
    function setupEventListeners() {
        elements.filterCompany.addEventListener('change', async (e) => {
            currentCompanyId = e.target.value;
            localStorage.setItem('company_id', currentCompanyId);
            currentAccountId = null;
            elements.filterAccount.value = '';
            await loadAccounts();
            showNoAccountState();
        });

        elements.filterAccount.addEventListener('change', (e) => {
            currentAccountId = e.target.value;
            if (currentAccountId) {
                loadLedger();
            } else {
                showNoAccountState();
            }
        });

        elements.btnApplyFilter.addEventListener('click', () => {
            if (currentAccountId) {
                loadLedger();
            }
        });

        elements.btnRefresh.addEventListener('click', () => {
            if (currentAccountId) {
                loadLedger();
            }
        });

        elements.btnLogout.addEventListener('click', logout);

        // Enter key on date fields
        elements.filterStartDate.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && currentAccountId) loadLedger();
        });
        elements.filterEndDate.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && currentAccountId) loadLedger();
        });
    }

    // UI State functions
    function showLoading() {
        hideAllStates();
        elements.ledgerBody.innerHTML = `
            <tr class="loading-row">
                <td colspan="6">
                    <div class="loading-spinner">Loading ledger entries...</div>
                </td>
            </tr>
        `;
    }

    function showNoAccountState() {
        elements.accountSummary.style.display = 'none';
        elements.ledgerPanel.style.display = 'none';
        elements.ledgerTotals.style.display = 'none';
        elements.noAccountState.style.display = 'block';
        elements.emptyState.style.display = 'none';
        elements.entryCount.textContent = '';
        elements.ledgerBody.innerHTML = '';
    }

    function showEmptyState() {
        elements.ledgerPanel.style.display = 'none';
        elements.noAccountState.style.display = 'none';
        elements.emptyState.style.display = 'block';
        elements.ledgerTotals.style.display = 'none';
        elements.ledgerBody.innerHTML = '';
    }

    function hideAllStates() {
        elements.noAccountState.style.display = 'none';
        elements.emptyState.style.display = 'none';
        elements.ledgerPanel.style.display = 'block';
    }

    function showError(message) {
        elements.ledgerBody.innerHTML = `
            <tr class="error-row">
                <td colspan="6">
                    <div class="error-message">Error: ${escapeHtml(message)}</div>
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

    function formatDate(dateStr) {
        return new Date(dateStr).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    function capitalizeFirst(str) {
        return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
    }

    function truncateId(id) {
        if (!id) return '';
        if (id.length <= 12) return id;
        return id.substring(0, 12) + '...';
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
