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

            // Load recent activity from audit logs
            await this.loadRecentActivity();
        } catch (error) {
            console.error('Failed to load dashboard data:', error);
        }
    }

    /**
     * Activity type to icon mapping
     */
    getActivityIcon(activityType) {
        const icons = {
            // User actions
            'user.login': '<path d="M2 2.75C2 1.784 2.784 1 3.75 1h2.5a.75.75 0 0 1 0 1.5h-2.5a.25.25 0 0 0-.25.25v10.5c0 .138.112.25.25.25h2.5a.75.75 0 0 1 0 1.5h-2.5A1.75 1.75 0 0 1 2 13.25Zm10.44 4.5-1.97-1.97a.749.749 0 0 1 .326-1.275.749.749 0 0 1 .734.215l3.25 3.25a.75.75 0 0 1 0 1.06l-3.25 3.25a.749.749 0 0 1-1.275-.326.749.749 0 0 1 .215-.734l1.97-1.97H6.75a.75.75 0 0 1 0-1.5Z"></path>',
            'user.logout': '<path d="M2 2.75C2 1.784 2.784 1 3.75 1h2.5a.75.75 0 0 1 0 1.5h-2.5a.25.25 0 0 0-.25.25v10.5c0 .138.112.25.25.25h2.5a.75.75 0 0 1 0 1.5h-2.5A1.75 1.75 0 0 1 2 13.25Zm10.44 4.5-1.97-1.97a.749.749 0 0 1 .326-1.275.749.749 0 0 1 .734.215l3.25 3.25a.75.75 0 0 1 0 1.06l-3.25 3.25a.749.749 0 0 1-1.275-.326.749.749 0 0 1 .215-.734l1.97-1.97H6.75a.75.75 0 0 1 0-1.5Z"></path>',
            'user.created': '<path d="M10.561 8.073a6.005 6.005 0 0 1 3.432 5.142.75.75 0 1 1-1.498.07 4.5 4.5 0 0 0-8.99 0 .75.75 0 0 1-1.498-.07 6.004 6.004 0 0 1 3.431-5.142 3.999 3.999 0 1 1 5.123 0ZM10.5 5a2.5 2.5 0 1 0-5 0 2.5 2.5 0 0 0 5 0Z"></path>',
            'user.approved': '<path d="M13.78 4.22a.75.75 0 0 1 0 1.06l-7.25 7.25a.75.75 0 0 1-1.06 0L2.22 9.28a.751.751 0 0 1 .018-1.042.751.751 0 0 1 1.042-.018L6 10.94l6.72-6.72a.75.75 0 0 1 1.06 0Z"></path>',
            // Transaction actions
            'transaction.created': '<path d="M5.22 14.78a.75.75 0 0 0 1.06-1.06L4.56 12h8.69a.75.75 0 0 0 0-1.5H4.56l1.72-1.72a.75.75 0 0 0-1.06-1.06l-3 3a.75.75 0 0 0 0 1.06l3 3Zm5.56-6.5a.75.75 0 1 1-1.06-1.06l1.72-1.72H2.75a.75.75 0 0 1 0-1.5h8.69L9.72 2.28a.75.75 0 0 1 1.06-1.06l3 3a.75.75 0 0 1 0 1.06l-3 3Z"></path>',
            'transaction.posted': '<path d="M13.78 4.22a.75.75 0 0 1 0 1.06l-7.25 7.25a.75.75 0 0 1-1.06 0L2.22 9.28a.751.751 0 0 1 .018-1.042.751.751 0 0 1 1.042-.018L6 10.94l6.72-6.72a.75.75 0 0 1 1.06 0Z"></path>',
            'transaction.voided': '<path d="M3.72 3.72a.75.75 0 0 1 1.06 0L8 6.94l3.22-3.22a.749.749 0 0 1 1.275.326.749.749 0 0 1-.215.734L9.06 8l3.22 3.22a.749.749 0 0 1-.326 1.275.749.749 0 0 1-.734-.215L8 9.06l-3.22 3.22a.751.751 0 0 1-1.042-.018.751.751 0 0 1-.018-1.042L6.94 8 3.72 4.78a.75.75 0 0 1 0-1.06Z"></path>',
            // Approval actions
            'approval.requested': '<path d="M8 0a8 8 0 1 1 0 16A8 8 0 0 1 8 0ZM1.5 8a6.5 6.5 0 1 0 13 0 6.5 6.5 0 0 0-13 0Zm9.78-2.22-4.5 4.5a.75.75 0 0 1-1.06 0l-2-2a.75.75 0 1 1 1.06-1.06l1.47 1.47 3.97-3.97a.75.75 0 1 1 1.06 1.06Z"></path>',
            'approval.approved': '<path d="M8 0a8 8 0 1 1 0 16A8 8 0 0 1 8 0ZM1.5 8a6.5 6.5 0 1 0 13 0 6.5 6.5 0 0 0-13 0Zm9.78-2.22-4.5 4.5a.75.75 0 0 1-1.06 0l-2-2a.75.75 0 1 1 1.06-1.06l1.47 1.47 3.97-3.97a.75.75 0 1 1 1.06 1.06Z"></path>',
            'approval.rejected': '<path d="M2.343 13.657A8 8 0 1 1 13.658 2.343 8 8 0 0 1 2.343 13.657ZM6.03 4.97a.751.751 0 0 0-1.042.018.751.751 0 0 0-.018 1.042L6.94 8 4.97 9.97a.749.749 0 0 0 .326 1.275.749.749 0 0 0 .734-.215L8 9.06l1.97 1.97a.749.749 0 0 0 1.275-.326.749.749 0 0 0-.215-.734L9.06 8l1.97-1.97a.749.749 0 0 0-.326-1.275.749.749 0 0 0-.734.215L8 6.94Z"></path>',
            // Company actions
            'company.created': '<path d="M1.75 1h8.5c.966 0 1.75.784 1.75 1.75v5.5A1.75 1.75 0 0 1 10.25 10H7.061l-2.574 2.573A1.458 1.458 0 0 1 2 11.543V10h-.25A1.75 1.75 0 0 1 0 8.25v-5.5C0 1.784.784 1 1.75 1ZM1.5 2.75v5.5c0 .138.112.25.25.25h1a.75.75 0 0 1 .75.75v2.19l2.72-2.72a.749.749 0 0 1 .53-.22h3.5a.25.25 0 0 0 .25-.25v-5.5a.25.25 0 0 0-.25-.25h-8.5a.25.25 0 0 0-.25.25Z"></path>',
            // Account actions
            'account.created': '<path d="M2 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm3.75-1.5a.75.75 0 0 0 0 1.5h8.5a.75.75 0 0 0 0-1.5h-8.5Zm0 5a.75.75 0 0 0 0 1.5h8.5a.75.75 0 0 0 0-1.5h-8.5Zm0 5a.75.75 0 0 0 0 1.5h8.5a.75.75 0 0 0 0-1.5h-8.5ZM3 8a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm-1 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z"></path>',
            // Settings/Security
            'settings.updated': '<path d="M8 0a8.2 8.2 0 0 1 .701.031C6.444.095 4.07.77 2.3 2.227c-.606.5-1.169 1.072-1.586 1.675C.313 4.539.066 5.261.009 5.986A8 8 0 1 0 8 0Z"></path>',
            'security.alert': '<path d="M6.457 1.047c.659-1.234 2.427-1.234 3.086 0l6.082 11.378A1.75 1.75 0 0 1 14.082 15H1.918a1.75 1.75 0 0 1-1.543-2.575ZM8 5a.75.75 0 0 0-.75.75v2.5a.75.75 0 0 0 1.5 0v-2.5A.75.75 0 0 0 8 5Zm1 6a1 1 0 1 0-2 0 1 1 0 0 0 2 0Z"></path>',
            // System
            'system.initialized': '<path d="M6.5 1.75a.75.75 0 0 1 1.5 0V7h5.75a.75.75 0 0 1 0 1.5H8v5.75a.75.75 0 0 1-1.5 0V8.5H.75a.75.75 0 0 1 0-1.5H6.5V1.75Z"></path>',
        };
        // Default icon (plus sign)
        const defaultIcon = '<path d="M6.5 1.75a.75.75 0 0 1 1.5 0V7h5.75a.75.75 0 0 1 0 1.5H8v5.75a.75.75 0 0 1-1.5 0V8.5H.75a.75.75 0 0 1 0-1.5H6.5V1.75Z"></path>';
        return icons[activityType] || defaultIcon;
    }

    /**
     * Format relative time (e.g., "2 minutes ago")
     */
    formatRelativeTime(dateStr) {
        const date = new Date(dateStr);
        const now = new Date();
        const diffMs = now - date;
        const diffSec = Math.floor(diffMs / 1000);
        const diffMin = Math.floor(diffSec / 60);
        const diffHour = Math.floor(diffMin / 60);
        const diffDay = Math.floor(diffHour / 24);

        if (diffSec < 60) return 'Just now';
        if (diffMin < 60) return `${diffMin} min ago`;
        if (diffHour < 24) return `${diffHour} hour${diffHour > 1 ? 's' : ''} ago`;
        if (diffDay < 7) return `${diffDay} day${diffDay > 1 ? 's' : ''} ago`;
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }

    /**
     * Load recent activity from global audit logs
     */
    async loadRecentActivity() {
        if (!this.elements.activityList) return;

        try {
            // Fetch last 4 activities from global system activities
            const result = await api.getGlobalActivities(4);

            // Handle various response structures
            let logs = [];
            if (result?.data?.items && Array.isArray(result.data.items)) {
                logs = result.data.items;
            } else if (result?.items && Array.isArray(result.items)) {
                logs = result.items;
            } else if (Array.isArray(result?.data)) {
                logs = result.data;
            } else if (Array.isArray(result)) {
                logs = result;
            }

            if (logs.length === 0) {
                this.elements.activityList.innerHTML = `
                    <div class="empty-state">
                        <svg class="empty-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="48" height="48" fill="currentColor">
                            <path d="M0 1.75C0 .784.784 0 1.75 0h12.5C15.216 0 16 .784 16 1.75v12.5A1.75 1.75 0 0 1 14.25 16H1.75A1.75 1.75 0 0 1 0 14.25ZM6.5 6.5v8h7.75a.25.25 0 0 0 .25-.25V6.5Zm8-1.5V1.75a.25.25 0 0 0-.25-.25H1.75a.25.25 0 0 0-.25.25V5Z"></path>
                        </svg>
                        <p>No recent activity</p>
                    </div>
                `;
                return;
            }

            let html = '';
            for (const log of logs) {
                const iconPath = this.getActivityIcon(log.activity_type);
                const description = log.description || log.activity_type || 'Activity';
                const timeAgo = this.formatRelativeTime(log.created_at);

                html += `
                    <div class="activity-item">
                        <svg class="activity-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="16" height="16" fill="currentColor">
                            ${iconPath}
                        </svg>
                        <div class="activity-details">
                            <span class="activity-text">${this.escapeHtml(description)}</span>
                            <span class="activity-time">${timeAgo}</span>
                        </div>
                    </div>
                `;
            }

            this.elements.activityList.innerHTML = html;
        } catch (error) {
            console.error('Failed to load recent activity:', error);
            // Keep existing placeholder content on error
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
            window.location.href = '/admin/companies/';
        });

        this.elements.btnAddUser?.addEventListener('click', () => {
            window.location.href = '/admin/users/';
        });

        this.elements.btnGenerateReport?.addEventListener('click', () => {
            window.location.href = '/admin/reports/trial-balance/';
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
