/**
 * Dashboard - Control Center Manager
 * Connects to real backend APIs for live data
 */

class DashboardManager {
    constructor() {
        this.elements = {
            userName: document.getElementById('userName'),
            systemTime: document.getElementById('systemTime'),
            btnLogout: document.getElementById('btnLogout'),
            activityList: document.getElementById('activityList'),
            // Stats cards
            statSessions: document.querySelector('.stat-card:nth-child(1) .stat-value'),
            statTransactions: document.querySelector('.stat-card:nth-child(2) .stat-value'),
            statCompanies: document.querySelector('.stat-card:nth-child(3) .stat-value'),
            statOperators: document.querySelector('.stat-card:nth-child(4) .stat-value'),
            // Status change indicators
            statTransactionsChange: document.querySelector('.stat-card:nth-child(2) .stat-change'),
            statCompaniesChange: document.querySelector('.stat-card:nth-child(3) .stat-change'),
            // Quick action buttons
            approvalDropdown: document.getElementById('approvalDropdown'),
            btnPendingApprovals: document.getElementById('btnPendingApprovals'),
            approvalBadge: document.getElementById('approvalBadge'),
            btnAddCompany: document.getElementById('btnAddCompany'),
            btnAddUser: document.getElementById('btnAddUser'),
            btnGenerateReport: document.getElementById('btnGenerateReport')
        };

        this.init();
    }

    async init() {
        await this.checkAuth();
        this.startClock();
        this.bindEvents();
        this.loadDashboardData();
    }

    async checkAuth() {
        const token = localStorage.getItem('auth_token');

        if (!token) {
            window.location.href = '/login.html';
            return;
        }

        try {
            const result = await api.getMe();
            if (result?.data?.username) {
                this.elements.userName.textContent = result.data.username;
            }
        } catch (error) {
            if (error.status === 401) {
                window.location.href = '/login.html';
            }
            console.error('Auth check failed:', error);
        }
    }

    async loadDashboardData() {
        try {
            // Try to restore previous selection or default to first company
            const companies = await api.getCompanies();
            const storedId = localStorage.getItem('company_id');
            const exists = companies?.data?.some(c => c.id === storedId);

            if (exists) {
                this.companyId = storedId;
            } else if (companies?.data?.length > 0) {
                this.companyId = companies.data[0].id;
                localStorage.setItem('company_id', this.companyId);
            }

            const stats = await api.getDashboardStats();
            this.updateStats(stats);

            // Load GLOBAL pending approvals for dropdown (5 most recent)
            await this.loadPendingApprovalsDropdown();
        } catch (error) {
            console.error('Failed to load dashboard data:', error);
        }
    }

    async loadPendingApprovalsDropdown() {
        const approvalsList = document.getElementById('approvalsList');
        if (!approvalsList) return;

        try {
            const result = await api.getGlobalRecentApprovals();
            // Backend returns: {success: true, data: [...]}
            const approvals = result?.data || [];

            if (approvals.length === 0) {
                approvalsList.innerHTML = `
                    <div class="dropdown-empty">
                        <p>No pending approvals</p>
                    </div>
                `;
                return;
            }

            let html = '';

            for (const approval of approvals) {
                const amount = this.formatCurrency(approval.amount_cents / 100);
                const entityId = approval.entity_id || '';
                const reason = approval.reason || 'Pending approval';
                const companyName = approval.company_name ? `(${approval.company_name})` : '';

                html += `
                    <a href="/transactions.html?status=pending&company=${approval.company_id}&txn=${entityId}" class="dropdown-item">
                        <div class="dropdown-item-icon warning">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="14" height="14" fill="currentColor">
                                <path d="M6.457 1.047c.659-1.234 2.427-1.234 3.086 0l6.082 11.378A1.75 1.75 0 0 1 14.082 15H1.918a1.75 1.75 0 0 1-1.543-2.575ZM8 5a.75.75 0 0 0-.75.75v2.5a.75.75 0 0 0 1.5 0v-2.5A.75.75 0 0 0 8 5Zm1 6a1 1 0 1 0-2 0 1 1 0 0 0 2 0Z"></path>
                            </svg>
                        </div>
                        <div class="dropdown-item-content">
                            <span class="dropdown-item-title">${this.escapeHtml(reason)}</span>
                            <span class="dropdown-item-meta">${this.escapeHtml(amount)} ${this.escapeHtml(companyName)}</span>
                        </div>
                    </a>
                `;
            }

            approvalsList.innerHTML = html;
        } catch (error) {
            console.error('Failed to load pending approvals:', error);
            approvalsList.innerHTML = `
                <div class="dropdown-empty">
                    <p>No pending approvals</p>
                </div>
            `;
        }
    }

    formatCurrency(amount) {
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP'
        }).format(amount);
    }

    escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    updateStats(stats) {
        this.updateTransactionStat(stats.transactionCount || 0);
        this.updateApprovalStat(stats.pendingApprovals || 0);
        this.updateAccountStat(stats.accountCount || 0);
    }

    updateTransactionStat(count) {
        if (!this.elements.statTransactions) return;

        this.elements.statTransactions.textContent = count;
        if (this.elements.statTransactionsChange) {
            this.elements.statTransactionsChange.textContent = count > 0 ? 'Active' : 'No activity';
            this.elements.statTransactionsChange.className = `stat-change ${count > 0 ? 'positive' : 'neutral'}`;
        }
    }

    updateApprovalStat(count) {
        if (!this.elements.statCompanies) return;

        this.elements.statCompanies.textContent = count;
        const label = document.querySelector('.stat-card:nth-child(3) .stat-title');
        if (label) label.textContent = 'Pending Approvals';

        if (this.elements.statCompaniesChange) {
            this.elements.statCompaniesChange.textContent = count > 0 ? 'Needs attention' : 'All clear';
            this.elements.statCompaniesChange.className = `stat-change ${count > 0 ? 'warning' : 'positive'}`;
        }

        // Update approval badge with real count
        if (this.elements.approvalBadge) {
            this.elements.approvalBadge.textContent = count;
            this.elements.approvalBadge.style.display = count > 0 ? 'flex' : 'none';
        }

        // Show sheen animation only if there are pending approvals
        if (this.elements.btnPendingApprovals) {
            if (count > 0) {
                this.elements.btnPendingApprovals.classList.add('has-alert');
            } else {
                this.elements.btnPendingApprovals.classList.remove('has-alert');
            }
        }
    }

    updateAccountStat(count) {
        if (!this.elements.statOperators) return;

        this.elements.statOperators.textContent = count;
        const label = document.querySelector('.stat-card:nth-child(4) .stat-title');
        if (label) label.textContent = 'GL Accounts';
    }

    startClock() {
        const updateTime = () => {
            const now = new Date();
            const timeStr = now.toLocaleTimeString('en-US', {
                hour12: false,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            this.elements.systemTime.textContent = timeStr;
        };

        updateTime();
        setInterval(updateTime, 1000);
    }

    bindEvents() {
        // Logout button
        this.elements.btnLogout?.addEventListener('click', () => this.logout());

        // Pending Approvals dropdown toggle
        this.elements.btnPendingApprovals?.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.elements.approvalDropdown?.classList.toggle('open');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!this.elements.approvalDropdown?.contains(e.target)) {
                this.elements.approvalDropdown?.classList.remove('open');
            }
        });

        // Quick action buttons
        this.elements.btnAddCompany?.addEventListener('click', () => {
            window.location.href = '/companies.html';
        });

        this.elements.btnAddUser?.addEventListener('click', () => {
            window.location.href = '/users.html';
        });

        this.elements.btnGenerateReport?.addEventListener('click', () => {
            window.location.href = '/trial-balance.html';
        });
    }

    showNotImplemented(feature) {
        alert(`${feature} will be available in a future update.`);
    }

    async logout() {
        try {
            const token = localStorage.getItem('auth_token');
            await fetch('/api/v1/auth/logout', {
                method: 'POST',
                headers: { 'Authorization': `Bearer ${token}` }
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

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    new DashboardManager();
});
