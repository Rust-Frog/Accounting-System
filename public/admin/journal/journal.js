/**
 * Journal Page JavaScript
 * Handles journal entry list display and detail view
 */

(function () {
    'use strict';

    // State
    let currentPage = 1;
    let totalPages = 1;
    let currentCompanyId = null;
    let currentTypeFilter = '';
    let accountsMap = {}; // Map of account ID to account info

    // DOM Elements
    const elements = {
        journalBody: document.getElementById('journalBody'),
        entryCount: document.getElementById('entryCount'),
        filterCompany: document.getElementById('filterCompany'),
        filterType: document.getElementById('filterType'),
        btnRefresh: document.getElementById('btnRefresh'),
        btnPrevPage: document.getElementById('btnPrevPage'),
        btnNextPage: document.getElementById('btnNextPage'),
        pageInfo: document.getElementById('pageInfo'),
        emptyState: document.getElementById('emptyState'),
        noCompanyState: document.getElementById('noCompanyState'),
        detailModal: document.getElementById('detailModal'),
        detailContent: document.getElementById('detailContent'),
        btnCloseDetail: document.getElementById('btnCloseDetail'),
        btnCloseDetailFooter: document.getElementById('btnCloseDetailFooter'),
        btnLogout: document.getElementById('btnLogout'),
        userName: document.getElementById('userName'),
        chainStatus: document.getElementById('chainStatus'),
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

    // Load companies for filter
    async function loadCompanies() {
        try {
            const response = await api.get('/companies?active_only=true');
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
                await loadAccounts(); // Load accounts for name resolution
            }

            if (currentCompanyId) {
                await loadJournalEntries();
            } else {
                // No companies - show empty state
                showNoCompanyState();
            }
        } catch (error) {
            console.error('Failed to load companies:', error);
            elements.filterCompany.innerHTML = '<option value="">No companies available</option>';
            showNoCompanyState();
        }
    }

    // Load accounts for name resolution
    async function loadAccounts() {
        if (!currentCompanyId) return;

        try {
            const response = await api.get(`/companies/${currentCompanyId}/accounts`);
            const accounts = response.data || [];

            // Build lookup map
            accountsMap = {};
            accounts.forEach(acc => {
                accountsMap[acc.id] = acc;
            });
        } catch (error) {
            console.error('Failed to load accounts:', error);
            accountsMap = {};
        }
    }

    // Get account display name from ID
    function getAccountDisplayName(accountId) {
        const account = accountsMap[accountId];
        if (account) {
            return `${account.code} - ${account.name}`;
        }
        // Fallback to showing truncated ID
        return accountId ? `${accountId.substring(0, 8)}...` : 'Unknown';
    }

    // Load journal entries
    async function loadJournalEntries() {
        if (!currentCompanyId) return;

        showLoading();

        try {
            let url = `/companies/${currentCompanyId}/journal?page=${currentPage}&per_page=20`;
            if (currentTypeFilter) {
                url += `&type=${currentTypeFilter}`;
            }

            const response = await api.get(url);
            const entries = response.data?.data || [];
            const pagination = response.data?.pagination || {};

            totalPages = pagination.total_pages || 1;
            updatePagination(pagination);
            updateEntryCount(pagination.total || 0);

            if (entries.length === 0) {
                showEmptyState();
            } else {
                renderEntries(entries);
            }
        } catch (error) {
            console.error('Failed to load journal entries:', error);
            showError('Failed to load journal entries');
        }
    }

    // Render journal entries
    function renderEntries(entries) {
        hideEmptyState();

        const html = entries.map((entry, index) => `
            <tr>
                <td>
                    ${renderChainIcon(entry, index === entries.length - 1)}
                </td>
                <td>${formatDate(entry.occurred_at)}</td>
                <td>
                    <span class="entry-type ${entry.entry_type.toLowerCase()}">${entry.entry_type}</span>
                </td>
                <td>
                    <span class="txn-link" data-txn-id="${entry.transaction_id}">
                        ${entry.transaction_id.substring(0, 8)}...
                    </span>
                </td>
                <td>
                    <span class="hash-display">${entry.content_hash}</span>
                </td>
                <td style="text-align: center;">
                    <button class="btn-view" data-id="${entry.id}">View</button>
                </td>
            </tr>
        `).join('');

        elements.journalBody.innerHTML = html;

        // Add event listeners for view buttons
        document.querySelectorAll('.btn-view').forEach(btn => {
            btn.addEventListener('click', () => viewEntry(btn.dataset.id));
        });
    }

    // Render chain link icon
    function renderChainIcon(entry, isLast) {
        if (entry.has_chain_link) {
            return `
                <span class="chain-link verified" title="Chain verified">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="14" height="14" fill="currentColor">
                        <path d="M7.775 3.275a.75.75 0 0 0 1.06 1.06l1.25-1.25a2 2 0 1 1 2.83 2.83l-2.5 2.5a2 2 0 0 1-2.83 0 .75.75 0 0 0-1.06 1.06 3.5 3.5 0 0 0 4.95 0l2.5-2.5a3.5 3.5 0 0 0-4.95-4.95l-1.25 1.25Zm-.025 9.45a.75.75 0 0 0-1.06-1.06l-1.25 1.25a2 2 0 0 1-2.83-2.83l2.5-2.5a2 2 0 0 1 2.83 0 .75.75 0 0 0 1.06-1.06 3.5 3.5 0 0 0-4.95 0l-2.5 2.5a3.5 3.5 0 0 0 4.95 4.95l1.25-1.25Z"></path>
                    </svg>
                </span>
            `;
        } else {
            return `
                <span class="chain-link genesis" title="Genesis entry">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="14" height="14" fill="currentColor">
                        <path d="M8 0a8 8 0 1 1 0 16A8 8 0 0 1 8 0ZM1.5 8a6.5 6.5 0 1 0 13 0 6.5 6.5 0 0 0-13 0Zm9.78-2.22-5.5 5.5a.749.749 0 0 1-1.275-.326.749.749 0 0 1 .215-.734l5.5-5.5a.751.751 0 0 1 1.042.018.751.751 0 0 1 .018 1.042Z"></path>
                    </svg>
                </span>
            `;
        }
    }

    // View entry detail
    async function viewEntry(id) {
        try {
            const response = await api.get(`/companies/${currentCompanyId}/journal/${id}`);
            const entry = response.data;
            renderDetailModal(entry);
            openModal(elements.detailModal);
        } catch (error) {
            console.error('Failed to load entry details:', error);
            alert('Failed to load entry details');
        }
    }

    // Render detail modal
    function renderDetailModal(entry) {
        const bookingsHtml = renderBookingsTable(entry.bookings);
        const hashChainHtml = renderHashChain(entry);

        elements.detailContent.innerHTML = `
            <div class="detail-section">
                <h4 class="detail-section-title">Entry Information</h4>
                <div class="detail-grid">
                    <span class="detail-label">Entry ID</span>
                    <span class="detail-value mono">${entry.id}</span>
                    
                    <span class="detail-label">Type</span>
                    <span class="detail-value">
                        <span class="entry-type ${entry.entry_type.toLowerCase()}">${entry.entry_type}</span>
                    </span>
                    
                    <span class="detail-label">Occurred At</span>
                    <span class="detail-value">${formatDateTime(entry.occurred_at)}</span>
                    
                    <span class="detail-label">Transaction ID</span>
                    <span class="detail-value mono">${entry.transaction_id}</span>
                </div>
            </div>

            <div class="detail-section">
                <h4 class="detail-section-title">Journal Bookings</h4>
                ${bookingsHtml}
            </div>

            <div class="detail-section">
                <h4 class="detail-section-title">Hash Chain</h4>
                ${hashChainHtml}
            </div>
        `;
    }

    // Render bookings table
    function renderBookingsTable(bookings) {
        if (!bookings || bookings.length === 0) {
            return '<p style="color: var(--text-secondary);">No booking data available</p>';
        }

        const rows = bookings.map(b => {
            // Handle different field naming conventions from backend
            const accountId = b.account_id || b.accountId || 'Unknown';
            const lineType = b.type || b.line_type || b.lineType || '';
            // Amount is stored in cents in the database
            const amountCents = b.amount || b.amountCents || 0;
            const amount = amountCents / 100;

            const isDebit = lineType === 'debit';
            const isCredit = lineType === 'credit';
            const accountName = getAccountDisplayName(accountId);

            return `
            <tr>
                <td class="account-name">${accountName}</td>
                <td class="amount-debit">${isDebit ? formatCurrency(amount) : ''}</td>
                <td class="amount-credit">${isCredit ? formatCurrency(amount) : ''}</td>
            </tr>
        `;
        }).join('');

        return `
            <table class="bookings-table">
                <thead>
                    <tr>
                        <th>Account</th>
                        <th>Debit</th>
                        <th>Credit</th>
                    </tr>
                </thead>
                <tbody>
                    ${rows}
                </tbody>
            </table>
        `;
    }

    // Render hash chain visualization
    function renderHashChain(entry) {
        const chainVerified = entry.chain_verified && entry.previous_hash;

        let html = `
            <div class="hash-chain-visual">
        `;

        if (entry.previous_hash) {
            html += `
                <div class="hash-block previous">
                    <span class="hash-block-label">Previous Hash</span>
                    <span class="hash-block-value">${entry.previous_hash}</span>
                </div>
                <div class="chain-arrow">↓</div>
            `;
        }

        html += `
            <div class="hash-block">
                <span class="hash-block-label">Content Hash</span>
                <span class="hash-block-value">${entry.content_hash}</span>
            </div>
        `;

        if (entry.chain_hash) {
            html += `
                <div class="chain-arrow">↓</div>
                <div class="hash-block">
                    <span class="hash-block-label">Chain Hash</span>
                    <span class="hash-block-value">${entry.chain_hash}</span>
                </div>
            `;
        }

        html += `</div>`;

        if (!entry.previous_hash) {
            html += `<span class="genesis-tag" style="margin-top: 12px; display: inline-block;">GENESIS ENTRY</span>`;
        } else if (chainVerified) {
            html += `
                <div class="chain-verified-badge" style="margin-top: 16px;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="14" height="14" fill="currentColor">
                        <path d="M13.78 4.22a.75.75 0 0 1 0 1.06l-7.25 7.25a.75.75 0 0 1-1.06 0L2.22 9.28a.75.75 0 0 1 1.06-1.06L6 10.94l6.72-6.72a.75.75 0 0 1 1.06 0Z"></path>
                    </svg>
                    Chain Integrity Verified
                </div>
            `;
        }

        return html;
    }

    // Helper functions
    function formatDate(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function formatDateTime(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
    }

    function formatCurrency(amount) {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(amount);
    }

    function showLoading() {
        elements.journalBody.innerHTML = `
            <tr class="loading-row">
                <td colspan="6">
                    <div class="loading-spinner">Loading journal entries...</div>
                </td>
            </tr>
        `;
        // Hide pagination and chain status during loading
        document.getElementById('pagination').style.display = 'none';
        elements.chainStatus.style.display = 'none';
        elements.entryCount.textContent = '';
        hideEmptyState();
    }

    function showError(message) {
        elements.journalBody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align: center; color: var(--danger);">
                    ${message}
                </td>
            </tr>
        `;
    }

    function showEmptyState() {
        document.querySelector('.panel').style.display = 'none';
        elements.emptyState.style.display = 'flex';
        // Hide chain verified badge when no entries
        elements.chainStatus.style.display = 'none';
    }

    function hideEmptyState() {
        document.querySelector('.panel').style.display = 'block';
        document.getElementById('pagination').style.display = 'flex';
        elements.emptyState.style.display = 'none';
        elements.noCompanyState.style.display = 'none';
        // Show chain verified badge when entries exist
        elements.chainStatus.style.display = 'inline-flex';
    }

    function showNoCompanyState() {
        // Hide the table panel and pagination
        document.querySelector('.panel').style.display = 'none';
        document.getElementById('pagination').style.display = 'none';
        elements.emptyState.style.display = 'none';
        elements.chainStatus.style.display = 'none';
        elements.entryCount.textContent = '';
        // Show the "No Company Selected" state
        elements.noCompanyState.style.display = 'flex';
    }

    function updatePagination(pagination) {
        elements.pageInfo.textContent = `Page ${pagination.page || 1} of ${pagination.total_pages || 1}`;
        elements.btnPrevPage.disabled = (pagination.page || 1) <= 1;
        elements.btnNextPage.disabled = (pagination.page || 1) >= (pagination.total_pages || 1);
    }

    function updateEntryCount(total) {
        elements.entryCount.textContent = `${total} ${total === 1 ? 'entry' : 'entries'}`;
    }

    function openModal(modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }

    // Event listeners
    function setupEventListeners() {
        elements.filterCompany.addEventListener('change', async (e) => {
            currentCompanyId = e.target.value;
            localStorage.setItem('company_id', currentCompanyId);
            currentPage = 1;
            await loadAccounts(); // Reload accounts for new company
            loadJournalEntries();
        });

        elements.filterType.addEventListener('change', (e) => {
            currentTypeFilter = e.target.value;
            currentPage = 1;
            loadJournalEntries();
        });

        elements.btnRefresh.addEventListener('click', () => {
            loadJournalEntries();
        });

        elements.btnPrevPage.addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                loadJournalEntries();
            }
        });

        elements.btnNextPage.addEventListener('click', () => {
            if (currentPage < totalPages) {
                currentPage++;
                loadJournalEntries();
            }
        });

        elements.btnCloseDetail.addEventListener('click', () => {
            closeModal(elements.detailModal);
        });

        elements.btnCloseDetailFooter.addEventListener('click', () => {
            closeModal(elements.detailModal);
        });

        elements.detailModal.querySelector('.modal-backdrop').addEventListener('click', () => {
            closeModal(elements.detailModal);
        });

        elements.btnLogout.addEventListener('click', async () => {
            try {
                await api.post('/auth/logout', {});
                window.location.href = '/login.html';
            } catch (error) {
                console.error('Logout failed:', error);
            }
        });
    }

    // Start
    init();
})();
