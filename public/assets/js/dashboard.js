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
            const stats = await api.getDashboardStats();
            this.updateStats(stats);
        } catch (error) {
            console.error('Failed to load dashboard data:', error);
        }
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

        // MOCK: Use mock count for layout testing until real approvals exist
        const mockCount = count > 0 ? count : 3;
        const hasRealApprovals = count > 0;

        this.elements.statCompanies.textContent = count;
        const label = document.querySelector('.stat-card:nth-child(3) .stat-title');
        if (label) label.textContent = 'Pending Approvals';

        if (this.elements.statCompaniesChange) {
            this.elements.statCompaniesChange.textContent = count > 0 ? 'Needs attention' : 'All clear';
            this.elements.statCompaniesChange.className = `stat-change ${count > 0 ? 'warning' : 'positive'}`;
        }

        // Update approval badge (using mock for demo)
        if (this.elements.approvalBadge) {
            this.elements.approvalBadge.textContent = mockCount;
            this.elements.approvalBadge.style.display = 'flex';
        }

        // Keep sheen animation on for demo (remove this condition when going live)
        if (this.elements.btnPendingApprovals) {
            this.elements.btnPendingApprovals.classList.add('has-alert');
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
            this.showNotImplemented('Add Company');
        });

        this.elements.btnAddUser?.addEventListener('click', () => {
            this.showNotImplemented('Add User');
        });

        this.elements.btnGenerateReport?.addEventListener('click', () => {
            this.showNotImplemented('Generate Report');
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
