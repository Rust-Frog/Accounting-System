/**
 * Accounting System - Shared API Client
 * Centralizes API calls with authentication handling
 */

class ApiClient {
    constructor() {
        this.baseUrl = '/api/v1';
    }

    getToken() {
        return localStorage.getItem('auth_token');
    }

    getCompanyId() {
        return localStorage.getItem('company_id') || '1';
    }

    setCompanyId(companyId) {
        localStorage.setItem('company_id', companyId);
    }

    async get(endpoint) {
        return this.request('GET', endpoint);
    }

    async post(endpoint, data) {
        return this.request('POST', endpoint, data);
    }

    async put(endpoint, data) {
        return this.request('PUT', endpoint, data);
    }

    async delete(endpoint) {
        return this.request('DELETE', endpoint);
    }

    async request(method, endpoint, data = null) {
        const token = this.getToken();

        if (!token) {
            this.redirectToLogin();
            return null;
        }

        const options = {
            method,
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        };

        if (data && (method === 'POST' || method === 'PUT')) {
            options.body = JSON.stringify(data);
        }

        try {
            const response = await fetch(`${this.baseUrl}${endpoint}`, options);

            // Handle authentication errors
            if (response.status === 401 || response.status === 403) {
                this.redirectToLogin();
                return null;
            }

            const result = await response.json();

            // Check for auth-related error messages even on other status codes
            if (!response.ok) {
                const errorMsg = (result.error?.message || result.error || 'Request failed').toLowerCase();
                if (errorMsg.includes('authentication') || errorMsg.includes('token') || errorMsg.includes('session') || errorMsg.includes('expired')) {
                    this.redirectToLogin();
                    return null;
                }
                throw new ApiError(
                    result.error?.message || result.error || 'Request failed',
                    response.status,
                    result
                );
            }

            return result;
        } catch (error) {
            if (error instanceof ApiError) {
                throw error;
            }
            throw new ApiError('Network error', 0, { originalError: error.message });
        }
    }

    redirectToLogin() {
        localStorage.removeItem('auth_token');
        localStorage.removeItem('auth_expires');
        localStorage.removeItem('user_id');
        window.location.href = '/login.html';
    }

    // ========== Dashboard APIs ==========

    async getMe() {
        return this.get('/auth/me');
    }

    async getDashboardStats() {
        // Use dedicated backend endpoint for system-wide stats
        try {
            const result = await this.get('/dashboard/stats');
            return {
                transactionCount: result?.data?.todays_transactions || 0,
                pendingApprovals: result?.data?.pending_approvals || 0,
                accountCount: result?.data?.gl_accounts || 0,
                activeSessions: result?.data?.active_sessions || 1
            };
        } catch (error) {
            console.error('Failed to fetch dashboard stats:', error);
            return {
                transactionCount: 0,
                pendingApprovals: 0,
                accountCount: 0,
                activeSessions: 1
            };
        }
    }

    async getGlobalRecentApprovals() {
        return this.get('/dashboard/recent-approvals');
    }

    async getGlobalActivities(limit = 10, offset = 0) {
        return this.get(`/activities?limit=${limit}&offset=${offset}`);
    }

    // ========== Company APIs ==========

    async getCompanies() {
        return this.get('/companies');
    }

    async getCompany(id) {
        return this.get(`/companies/${id}`);
    }

    // ========== Transaction APIs ==========

    async getTransactions(page = 1, limit = 20, companyId = null, status = 'all') {
        const cid = companyId || this.getCompanyId();
        let url = `/companies/${cid}/transactions?page=${page}&limit=${limit}`;
        if (status && status !== 'all') {
            url += `&status=${encodeURIComponent(status)}`;
        }
        return this.get(url);
    }

    async getTransaction(id, companyId = null) {
        const cid = companyId || this.getCompanyId();
        return this.get(`/companies/${cid}/transactions/${id}`);
    }

    async createTransaction(data, companyId = null) {
        const cid = companyId || this.getCompanyId();
        return this.post(`/companies/${cid}/transactions`, data);
    }

    async updateTransaction(id, data, companyId = null) {
        const cid = companyId || this.getCompanyId();
        return this.put(`/companies/${cid}/transactions/${id}`, data);
    }

    async deleteTransaction(id, companyId = null) {
        const cid = companyId || this.getCompanyId();
        return this.delete(`/companies/${cid}/transactions/${id}`);
    }

    async postTransaction(id, companyId = null) {
        const cid = companyId || this.getCompanyId();
        return this.post(`/companies/${cid}/transactions/${id}/post`);
    }

    async voidTransaction(id, companyId = null, reason = 'Voided via UI') {
        const cid = companyId || this.getCompanyId();
        return this.post(`/companies/${cid}/transactions/${id}/void`, { reason });
    }

    // ========== Approval APIs ==========

    async getPendingApprovals(page = 1, limit = 20, companyId = null) {
        const cid = companyId || this.getCompanyId();
        return this.get(`/companies/${cid}/approvals/pending?page=${page}&limit=${limit}`);
    }

    async approveRequest(id, reason = '', companyId = null) {
        const cid = companyId || this.getCompanyId();
        return this.post(`/companies/${cid}/approvals/${id}/approve`, { reason });
    }

    async rejectRequest(id, reason = '', companyId = null) {
        const cid = companyId || this.getCompanyId();
        return this.post(`/companies/${cid}/approvals/${id}/reject`, { reason });
    }

    // ========== Account APIs ==========

    async getAccounts(companyId = null) {
        const cid = companyId || this.getCompanyId();
        return this.get(`/companies/${cid}/accounts`);
    }

    async getAccount(id, companyId = null) {
        const cid = companyId || this.getCompanyId();
        return this.get(`/companies/${cid}/accounts/${id}`);
    }

    async createAccount(data, companyId = null) {
        const cid = companyId || this.getCompanyId();
        return this.post(`/companies/${cid}/accounts`, data);
    }

    async updateAccount(id, data, companyId = null) {
        const cid = companyId || this.getCompanyId();
        return this.put(`/companies/${cid}/accounts/${id}`, data);
    }

    async toggleAccount(id, companyId = null) {
        const cid = companyId || this.getCompanyId();
        return this.post(`/companies/${cid}/accounts/${id}/toggle`);
    }

    async getAccountTransactions(companyId, accountId) {
        const cid = companyId || this.getCompanyId();
        const result = await this.get(`/companies/${cid}/accounts/${accountId}/transactions`);
        return result?.data || [];
    }

    // ========== Report APIs ==========

    async getReports() {
        const companyId = this.getCompanyId();
        return this.get(`/companies/${companyId}/reports`);
    }

    async generateReport(reportType, periodStart, periodEnd) {
        const companyId = this.getCompanyId();
        return this.post(`/companies/${companyId}/reports/generate`, {
            report_type: reportType,
            period_start: periodStart,
            period_end: periodEnd
        });
    }

    // ========== Ledger APIs ==========

    async getLedger(accountId, startDate = null, endDate = null) {
        const companyId = this.getCompanyId();
        let url = `/companies/${companyId}/ledger?account_id=${accountId}`;
        if (startDate) url += `&start_date=${startDate}`;
        if (endDate) url += `&end_date=${endDate}`;
        return this.get(url);
    }

    async getLedgerSummary() {
        const companyId = this.getCompanyId();
        return this.get(`/companies/${companyId}/ledger/summary`);
    }

    // ========== Trial Balance APIs ==========

    async getTrialBalance(asOfDate = null) {
        const companyId = this.getCompanyId();
        let url = `/companies/${companyId}/trial-balance`;
        if (asOfDate) url += `?as_of_date=${asOfDate}`;
        return this.get(url);
    }

    // ========== Income Statement APIs ==========

    async getIncomeStatement(startDate = null, endDate = null) {
        const companyId = this.getCompanyId();
        let url = `/companies/${companyId}/income-statement`;
        const params = [];
        if (startDate) params.push(`start_date=${startDate}`);
        if (endDate) params.push(`end_date=${endDate}`);
        if (params.length > 0) url += `?${params.join('&')}`;
        return this.get(url);
    }

    // ========== Balance Sheet APIs ==========

    async getBalanceSheet(asOfDate = null) {
        const companyId = this.getCompanyId();
        let url = `/companies/${companyId}/balance-sheet`;
        if (asOfDate) url += `?as_of_date=${asOfDate}`;
        return this.get(url);
    }

    // ========== User Management APIs ==========

    async getUsers(role = null, status = null) {
        let url = '/users';
        const params = [];
        if (role) params.push(`role=${encodeURIComponent(role)}`);
        if (status) params.push(`status=${encodeURIComponent(status)}`);
        if (params.length > 0) url += `?${params.join('&')}`;
        return this.get(url);
    }

    async getUser(id) {
        return this.get(`/users/${id}`);
    }

    async approveUser(id) {
        return this.post(`/users/${id}/approve`);
    }

    async declineUser(id) {
        return this.post(`/users/${id}/decline`);
    }

    async deactivateUser(id) {
        return this.post(`/users/${id}/deactivate`);
    }

    async activateUser(id) {
        return this.post(`/users/${id}/activate`);
    }

    // ========== Audit Log APIs ==========

    async getAuditLogs(options = {}) {
        const companyId = this.getCompanyId();
        let url = `/companies/${companyId}/audit-logs`;
        const params = [];
        if (options.limit) params.push(`limit=${options.limit}`);
        if (options.offset) params.push(`offset=${options.offset}`);
        if (options.sort) params.push(`sort=${options.sort}`);
        if (options.from_date) params.push(`from_date=${options.from_date}`);
        if (options.to_date) params.push(`to_date=${options.to_date}`);
        if (options.activity_type) params.push(`activity_type=${encodeURIComponent(options.activity_type)}`);
        if (options.entity_type) params.push(`entity_type=${encodeURIComponent(options.entity_type)}`);
        if (options.severity) params.push(`severity=${encodeURIComponent(options.severity)}`);
        if (params.length > 0) url += `?${params.join('&')}`;
        return this.get(url);
    }

    async getAuditLog(id) {
        const companyId = this.getCompanyId();
        return this.get(`/companies/${companyId}/audit-logs/${id}`);
    }

    async getAuditLogStats() {
        const companyId = this.getCompanyId();
        return this.get(`/companies/${companyId}/audit-logs/stats`);
    }

    // ========== Settings APIs ==========

    async getSettings() {
        return this.get('/settings');
    }

    async updateTheme(theme) {
        return this.put('/settings/theme', { theme });
    }

    async updateLocalization(data) {
        return this.put('/settings/localization', data);
    }

    async updateNotifications(emailNotifications, browserNotifications) {
        return this.put('/settings/notifications', {
            email_notifications: emailNotifications,
            browser_notifications: browserNotifications
        });
    }

    async updateSessionTimeout(minutes) {
        return this.put('/settings/session', { session_timeout_minutes: minutes });
    }

    async changePassword(currentPassword, newPassword) {
        return this.post('/settings/password', {
            current_password: currentPassword,
            new_password: newPassword
        });
    }

    async enableOtp() {
        return this.post('/settings/otp/enable');
    }

    async verifyOtp(otpCode) {
        return this.post('/settings/otp/verify', { otp_code: otpCode });
    }

    async disableOtp(password) {
        return this.post('/settings/otp/disable', { password });
    }

    async regenerateBackupCodes(password) {
        return this.post('/settings/backup-codes/regenerate', { password });
    }
}

/**
 * Custom API Error class
 */
class ApiError extends Error {
    constructor(message, status, data) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.data = data;
    }
}

// Export singleton instance
const api = new ApiClient();
