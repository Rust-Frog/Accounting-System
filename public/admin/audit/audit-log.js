/**
 * Activity History Page JavaScript
 * Handles audit log listing, filtering, and detail viewing
 * Supports both System Activity (global) and Company Logs (per-company) views
 */

(function () {
    'use strict';

    // State
    let logs = [];
    let companies = [];
    let currentLog = null;
    let currentTab = 'system'; // 'system' or 'company'
    let pagination = { total: 0, limit: 50, offset: 0, has_more: false };

    // DOM Elements
    const elements = {
        auditBody: document.getElementById('auditBody'),
        logCount: document.getElementById('logCount'),
        companySelect: document.getElementById('companySelect'),
        // Stats
        statTotal: document.getElementById('statTotal'),
        statInfo: document.getElementById('statInfo'),
        statWarning: document.getElementById('statWarning'),
        statSecurity: document.getElementById('statSecurity'),
        // Filters
        filterFromDate: document.getElementById('filterFromDate'),
        filterToDate: document.getElementById('filterToDate'),
        filterSeverity: document.getElementById('filterSeverity'),
        filterCategory: document.getElementById('filterCategory'),
        btnApplyFilters: document.getElementById('btnApplyFilters'),
        btnClearFilters: document.getElementById('btnClearFilters'),
        // Pagination
        paginationInfo: document.getElementById('paginationInfo'),
        btnPrevPage: document.getElementById('btnPrevPage'),
        btnNextPage: document.getElementById('btnNextPage'),
        // States
        emptyState: document.getElementById('emptyState'),
        selectCompanyState: document.getElementById('selectCompanyState'),
        statsGrid: document.getElementById('statsGrid'),
        // Modal
        detailModal: document.getElementById('detailModal'),
        btnCloseModal: document.getElementById('btnCloseModal'),
        btnCloseDetail: document.getElementById('btnCloseDetail'),
        detailId: document.getElementById('detailId'),
        detailTimestamp: document.getElementById('detailTimestamp'),
        detailActor: document.getElementById('detailActor'),
        detailIp: document.getElementById('detailIp'),
        detailActivityType: document.getElementById('detailActivityType'),
        detailCategory: document.getElementById('detailCategory'),
        detailEntityType: document.getElementById('detailEntityType'),
        detailEntityId: document.getElementById('detailEntityId'),
        detailSeverity: document.getElementById('detailSeverity'),
        detailAction: document.getElementById('detailAction'),
        changesSection: document.getElementById('changesSection'),
        detailChanges: document.getElementById('detailChanges'),
        detailHash: document.getElementById('detailHash'),
        // Tabs
        tabSystemActivity: document.getElementById('tabSystemActivity'),
        tabCompanyLogs: document.getElementById('tabCompanyLogs'),
        // Note: btnLogout and userName are now handled by sidebar.js
    };

    // Initialize
    async function init() {
        // Note: User info is now handled by sidebar.js
        await loadCompanies();
        setupEventListeners();
        setDefaultDateFilters();

        // Default to System Activity tab
        switchToSystemTab();
    }

    // Switch to System Activity tab
    async function switchToSystemTab() {
        currentTab = 'system';
        elements.tabSystemActivity.classList.add('active');
        elements.tabSystemActivity.style.background = 'var(--color-primary)';
        elements.tabSystemActivity.style.color = 'white';
        elements.tabCompanyLogs.classList.remove('active');
        elements.tabCompanyLogs.style.background = 'transparent';
        elements.tabCompanyLogs.style.color = 'var(--color-text-secondary)';
        document.getElementById('companyFilterGroup').style.display = 'none';
        hideSelectCompanyState();
        pagination.offset = 0;
        await loadSystemActivities();
    }

    // Switch to Company Logs tab
    async function switchToCompanyTab() {
        currentTab = 'company';
        elements.tabCompanyLogs.classList.add('active');
        elements.tabCompanyLogs.style.background = 'var(--color-primary)';
        elements.tabCompanyLogs.style.color = 'white';
        elements.tabSystemActivity.classList.remove('active');
        elements.tabSystemActivity.style.background = 'transparent';
        elements.tabSystemActivity.style.color = 'var(--color-text-secondary)';
        document.getElementById('companyFilterGroup').style.display = 'flex';

        const savedCompanyId = api.getCompanyId();
        if (savedCompanyId && savedCompanyId !== '1') {
            elements.companySelect.value = savedCompanyId;
            pagination.offset = 0;
            await loadAuditLogs();
            await loadStats();
        } else {
            showSelectCompanyState();
        }
    }

    // Load system-wide activities (global)
    async function loadSystemActivities() {
        showLoading();
        try {
            const response = await api.getGlobalActivities(100);
            // Handle null response (auth redirect)
            if (!response) {
                return;
            }
            // Handle both {data: [...]} and {data: {items: [...]}} formats
            const rawData = response.data;
            logs = Array.isArray(rawData) ? rawData : (rawData?.items || rawData?.data || rawData?.activities || []);
            if (!Array.isArray(logs)) logs = [];
            pagination.total = logs.length;
            pagination.has_more = false;
            renderSystemActivities();
            updatePagination();
            // Calculate and show stats from loaded data
            updateSystemStats(logs);
        } catch (error) {
            console.error('Failed to load system activities:', error);
            showError('Failed to load system activities: ' + error.message);
        }
    }

    // Calculate stats from system activities data
    function updateSystemStats(activities) {
        const total = activities.length;
        let info = 0, warning = 0, security = 0;

        activities.forEach(activity => {
            const severity = (activity.severity || 'info').toLowerCase();
            if (severity === 'info') info++;
            else if (severity === 'warning') warning++;
            else if (severity === 'critical' || severity === 'security') security++;
        });

        elements.statTotal.textContent = total;
        elements.statInfo.textContent = info;
        elements.statWarning.textContent = warning;
        elements.statSecurity.textContent = security;
        elements.statsGrid.style.display = 'grid';
    }

    // Render system activities table
    function renderSystemActivities() {
        if (logs.length === 0) {
            showEmptyState();
            updateLogCount(0);
            return;
        }

        hideEmptyState();
        hideSelectCompanyState();
        updateLogCount(logs.length);

        elements.auditBody.innerHTML = logs.map(log => `
            <tr>
                <td>
                    <span class="timestamp">${formatDateTime(log.occurred_at)}</span>
                </td>
                <td>
                    <div class="actor-cell">
                        <span class="actor-name">${escapeHtml(log.actor_username || 'System')}</span>
                        <span class="actor-ip">${escapeHtml(log.actor_ip_address || '-')}</span>
                    </div>
                </td>
                <td>
                    <span class="activity-type-cell">${escapeHtml(log.activity_type)}</span>
                </td>
                <td>
                    <div class="entity-cell">
                        <span class="entity-type">${escapeHtml(log.entity_type)}</span>
                        <span class="entity-id">${escapeHtml(truncateId(log.entity_id))}</span>
                    </div>
                </td>
                <td>
                    <span class="category-badge">-</span>
                </td>
                <td>
                    <span class="severity-badge ${log.severity}">${escapeHtml(log.severity)}</span>
                </td>
                <td>
                    <span class="hash-value" title="${escapeHtml(log.chain_hash || '-')}">
                        ${escapeHtml((log.chain_hash || '-').substring(0, 8))}...
                    </span>
                </td>
            </tr>
        `).join('');
    }

    // Note: loadUserInfo is now handled by sidebar.js
    // Kept as stub for backward compatibility
    async function loadUserInfo() {
        // User info loading moved to sidebar.js
    }

    // Load companies
    async function loadCompanies() {
        try {
            const response = await api.getCompanies();
            companies = response.data || [];
            renderCompanyOptions();
        } catch (error) {
            console.error('Failed to load companies:', error);
        }
    }

    // Render company dropdown options
    function renderCompanyOptions() {
        elements.companySelect.innerHTML = '<option value="">Select Company</option>';
        companies.forEach(company => {
            const option = document.createElement('option');
            option.value = company.id;
            option.textContent = company.name;
            elements.companySelect.appendChild(option);
        });

        // Select current company if set
        const currentCompanyId = api.getCompanyId();
        if (currentCompanyId && currentCompanyId !== '1') {
            elements.companySelect.value = currentCompanyId;
        }
    }

    // Set default date filters (last 30 days)
    function setDefaultDateFilters() {
        const today = new Date();
        const thirtyDaysAgo = new Date(today);
        thirtyDaysAgo.setDate(today.getDate() - 30);

        elements.filterToDate.value = today.toISOString().split('T')[0];
        elements.filterFromDate.value = thirtyDaysAgo.toISOString().split('T')[0];
    }

    // Load audit logs
    async function loadAuditLogs() {
        const companyId = elements.companySelect.value;
        if (!companyId) {
            showSelectCompanyState();
            return;
        }

        showLoading();

        try {
            const options = {
                limit: pagination.limit,
                offset: pagination.offset,
                sort: 'DESC',
            };

            if (elements.filterFromDate.value) {
                options.from_date = elements.filterFromDate.value;
            }
            if (elements.filterToDate.value) {
                options.to_date = elements.filterToDate.value;
            }
            if (elements.filterSeverity.value) {
                options.severity = elements.filterSeverity.value;
            }
            // Note: category filter is handled client-side since API filters by activity_type

            const response = await api.getAuditLogs(options);
            logs = response.data?.logs || [];
            pagination = response.data?.pagination || pagination;

            // Apply category filter client-side
            if (elements.filterCategory.value) {
                logs = logs.filter(log => log.category === elements.filterCategory.value);
            }

            renderLogs();
            updatePagination();
        } catch (error) {
            console.error('Failed to load audit logs:', error);
            showError('Failed to load audit logs: ' + error.message);
        }
    }

    // Load stats
    async function loadStats() {
        const companyId = elements.companySelect.value;
        if (!companyId) return;

        try {
            const response = await api.getAuditLogStats();
            const stats = response.data;

            elements.statTotal.textContent = stats.total_count || 0;
            elements.statInfo.textContent = stats.by_severity?.info || 0;
            elements.statWarning.textContent = stats.by_severity?.warning || 0;
            elements.statSecurity.textContent = stats.by_severity?.security || 0;

            elements.statsGrid.style.display = 'grid';
        } catch (error) {
            console.error('Failed to load stats:', error);
        }
    }

    // Render logs table
    function renderLogs() {
        if (logs.length === 0) {
            showEmptyState();
            updateLogCount(0);
            return;
        }

        hideEmptyState();
        hideSelectCompanyState();
        updateLogCount(pagination.total);

        elements.auditBody.innerHTML = logs.map(log => `
            <tr>
                <td>
                    <span class="timestamp">${formatDateTime(log.occurred_at)}</span>
                </td>
                <td>
                    <div class="actor-cell">
                        <span class="actor-name">${escapeHtml(log.actor?.username || 'System')}</span>
                        <span class="actor-ip">${escapeHtml(log.context?.ip_address || '-')}</span>
                    </div>
                </td>
                <td>
                    <span class="activity-type-cell">${escapeHtml(formatActivityType(log.activity_type))}</span>
                </td>
                <td>
                    <div class="entity-cell">
                        <span class="entity-type">${escapeHtml(log.entity_type)}</span>
                        <span class="entity-id">${escapeHtml(truncateId(log.entity_id))}</span>
                    </div>
                </td>
                <td>
                    <span class="category-badge">${escapeHtml(formatCategory(log.category))}</span>
                </td>
                <td>
                    <span class="severity-badge ${log.severity}">${escapeHtml(log.severity)}</span>
                </td>
                <td>
                    <button class="btn btn-sm btn-secondary" onclick="viewLogDetail('${log.id}')">
                        View
                    </button>
                </td>
            </tr>
        `).join('');
    }

    // View log detail
    window.viewLogDetail = async function (logId) {
        try {
            const response = await api.getAuditLog(logId);
            currentLog = response.data;
            showDetailModal(currentLog);
        } catch (error) {
            console.error('Failed to load log detail:', error);
            showError('Failed to load log details: ' + error.message);
        }
    };

    // Show detail modal
    function showDetailModal(log) {
        elements.detailId.textContent = log.id;
        elements.detailTimestamp.textContent = formatDateTime(log.occurred_at);
        elements.detailActor.textContent = log.actor?.username || 'System';
        elements.detailIp.textContent = log.context?.ip_address || '-';
        elements.detailActivityType.textContent = formatActivityType(log.activity_type);
        elements.detailCategory.textContent = formatCategory(log.category);
        elements.detailEntityType.textContent = log.entity_type;
        elements.detailEntityId.textContent = log.entity_id;
        elements.detailAction.textContent = log.action || '-';

        // Severity with badge
        elements.detailSeverity.innerHTML = `<span class="severity-badge ${log.severity}">${log.severity}</span>`;

        // Render changes
        if (log.changes && log.changes.length > 0) {
            elements.changesSection.style.display = 'block';
            elements.detailChanges.innerHTML = log.changes.map(change => `
                <div class="change-item">
                    <span class="change-field">${escapeHtml(change.field)}</span>
                    <span class="change-arrow">→</span>
                    <span class="change-old">${escapeHtml(formatValue(change.previous_value))}</span>
                    <span class="change-arrow">→</span>
                    <span class="change-new">${escapeHtml(formatValue(change.new_value))}</span>
                </div>
            `).join('');
        } else {
            elements.changesSection.style.display = 'none';
        }

        // Content hash
        elements.detailHash.textContent = log.content_hash || 'Not available';

        elements.detailModal.classList.add('active');
    }

    // Close detail modal
    function closeDetailModal() {
        elements.detailModal.classList.remove('active');
        currentLog = null;
    }

    // Update pagination
    function updatePagination() {
        const start = pagination.offset + 1;
        const end = Math.min(pagination.offset + logs.length, pagination.total);

        elements.paginationInfo.textContent = `Showing ${start}-${end} of ${pagination.total} entries`;
        elements.btnPrevPage.disabled = pagination.offset === 0;
        elements.btnNextPage.disabled = !pagination.has_more;
    }

    // Setup event listeners
    function setupEventListeners() {
        // Tab switching
        elements.tabSystemActivity.addEventListener('click', switchToSystemTab);
        elements.tabCompanyLogs.addEventListener('click', switchToCompanyTab);

        // Company change
        elements.companySelect.addEventListener('change', async () => {
            const companyId = elements.companySelect.value;
            if (companyId) {
                api.setCompanyId(companyId);
                pagination.offset = 0;
                await loadAuditLogs();
                await loadStats();
            } else {
                showSelectCompanyState();
            }
        });

        // Filters
        elements.btnApplyFilters.addEventListener('click', async () => {
            pagination.offset = 0;
            await loadAuditLogs();
        });

        elements.btnClearFilters.addEventListener('click', async () => {
            setDefaultDateFilters();
            elements.filterSeverity.value = '';
            elements.filterCategory.value = '';
            pagination.offset = 0;
            await loadAuditLogs();
        });

        // Pagination
        elements.btnPrevPage.addEventListener('click', async () => {
            if (pagination.offset > 0) {
                pagination.offset = Math.max(0, pagination.offset - pagination.limit);
                await loadAuditLogs();
            }
        });

        elements.btnNextPage.addEventListener('click', async () => {
            if (pagination.has_more) {
                pagination.offset += pagination.limit;
                await loadAuditLogs();
            }
        });

        // Modal
        elements.btnCloseModal.addEventListener('click', closeDetailModal);
        elements.btnCloseDetail.addEventListener('click', closeDetailModal);
        elements.detailModal.querySelector('.modal-backdrop').addEventListener('click', closeDetailModal);

        // Note: Logout is now handled by sidebar.js
    }

    // Helper functions
    function showLoading() {
        elements.auditBody.innerHTML = `
            <tr>
                <td colspan="7">
                    <div class="loading-spinner">Loading audit logs...</div>
                </td>
            </tr>
        `;
        hideEmptyState();
        hideSelectCompanyState();
    }

    function showEmptyState() {
        elements.emptyState.style.display = 'flex';
        document.querySelector('.panel').style.display = 'none';
    }

    function hideEmptyState() {
        elements.emptyState.style.display = 'none';
        document.querySelector('.panel').style.display = 'block';
    }

    function showSelectCompanyState() {
        elements.selectCompanyState.style.display = 'flex';
        document.querySelector('.panel').style.display = 'none';
        elements.statsGrid.style.display = 'none';
        elements.emptyState.style.display = 'none';
        updateLogCount(0);
    }

    function hideSelectCompanyState() {
        elements.selectCompanyState.style.display = 'none';
    }

    function showError(message) {
        elements.auditBody.innerHTML = `
            <tr>
                <td colspan="7" style="color: var(--color-danger); text-align: center;">
                    ${escapeHtml(message)}
                </td>
            </tr>
        `;
    }

    function updateLogCount(count) {
        elements.logCount.textContent = `${count} entries`;
    }

    function formatDateTime(dateStr) {
        // Use shared Utils if available, fallback to local implementation
        if (window.Utils && Utils.formatDateTimeFull) {
            return Utils.formatDateTimeFull(dateStr);
        }
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        return date.toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
    }

    function formatActivityType(type) {
        if (!type) return '-';
        return type.replace(/_/g, ' ').toLowerCase();
    }

    function formatCategory(category) {
        if (!category) return '-';
        return category.replace(/_/g, ' ');
    }

    function truncateId(id) {
        // Use shared Utils if available
        if (window.Utils && Utils.truncateId) {
            return Utils.truncateId(id, 8);
        }
        if (!id) return '-';
        if (id.length <= 12) return id;
        return id.substring(0, 8) + '...';
    }

    function formatValue(value) {
        if (value === null || value === undefined) return 'null';
        if (typeof value === 'object') return JSON.stringify(value);
        return String(value);
    }

    function escapeHtml(text) {
        // Use shared Utils if available
        if (window.Utils && Utils.escapeHtml) {
            return Utils.escapeHtml(text);
        }
        if (text === null || text === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
