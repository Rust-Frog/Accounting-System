/**
 * Users Page JavaScript
 * Handles user list, approval, and management
 */

(function () {
    'use strict';

    // State
    let users = [];
    let currentUser = null;
    let pendingAction = null;
    let loggedInUserId = null;

    // DOM Elements
    const elements = {
        usersBody: document.getElementById('usersBody'),
        userCount: document.getElementById('userCount'),
        pendingCount: document.getElementById('pendingCount'),
        filterRole: document.getElementById('filterRole'),
        filterStatus: document.getElementById('filterStatus'),
        btnRefresh: document.getElementById('btnRefresh'),
        emptyState: document.getElementById('emptyState'),
        btnLogout: document.getElementById('btnLogout'),
        userName: document.getElementById('userName'),
        // Detail modal
        userDetailModal: document.getElementById('userDetailModal'),
        btnCloseModal: document.getElementById('btnCloseModal'),
        btnCloseDetail: document.getElementById('btnCloseDetail'),
        detailUsername: document.getElementById('detailUsername'),
        detailEmail: document.getElementById('detailEmail'),
        detailRole: document.getElementById('detailRole'),
        detailStatus: document.getElementById('detailStatus'),
        detailActive: document.getElementById('detailActive'),
        detailCompany: document.getElementById('detailCompany'),
        detailLastLogin: document.getElementById('detailLastLogin'),
        detailCreated: document.getElementById('detailCreated'),
        modalActions: document.getElementById('modalActions'),
        // Confirmation modal
        confirmModal: document.getElementById('confirmModal'),
        confirmTitle: document.getElementById('confirmTitle'),
        confirmMessage: document.getElementById('confirmMessage'),
        btnCloseConfirm: document.getElementById('btnCloseConfirm'),
        btnCancelConfirm: document.getElementById('btnCancelConfirm'),
        btnConfirmAction: document.getElementById('btnConfirmAction'),
        // Create User modal
        btnCreateUser: document.getElementById('btnCreateUser'),
        createUserModal: document.getElementById('createUserModal'),
        btnCloseCreateUser: document.getElementById('btnCloseCreateUser'),
        btnCancelCreateUser: document.getElementById('btnCancelCreateUser'),
        createUserForm: document.getElementById('createUserForm'),
        newUsername: document.getElementById('newUsername'),
        newEmail: document.getElementById('newEmail'),
        newPassword: document.getElementById('newPassword'),
        confirmNewPassword: document.getElementById('confirmNewPassword'),
        newCompany: document.getElementById('newCompany'),
    };

    // Initialize
    async function init() {
        await loadUserInfo();
        await loadUsers();
        await loadCompanies();
        setupEventListeners();
    }

    // Load companies for dropdown
    async function loadCompanies() {
        try {
            const response = await api.get('/companies');
            const companies = response.data || [];
            elements.newCompany.innerHTML = '<option value="">Select Company</option>' +
                companies.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
        } catch (error) {
            console.error('Failed to load companies:', error);
        }
    }

    // Load current user info
    async function loadUserInfo() {
        try {
            const response = await api.get('/auth/me');
            if (response.data) {
                elements.userName.textContent = response.data.username || 'User';
                loggedInUserId = response.data.id;
            }
        } catch (error) {
            console.error('Failed to load user info:', error);
        }
    }

    // Load users from API
    async function loadUsers() {
        showLoading();

        try {
            const role = elements.filterRole.value || null;
            const status = elements.filterStatus.value || null;
            const response = await api.getUsers(role, status);
            users = response.data || [];
            renderUsers();
            updatePendingCount();
        } catch (error) {
            console.error('Failed to load users:', error);
            showError('Failed to load users. Please try again.');
        }
    }

    // Render users table
    function renderUsers() {
        if (users.length === 0) {
            showEmptyState();
            updateUserCount(0);
            return;
        }

        hideEmptyState();
        updateUserCount(users.length);

        elements.usersBody.innerHTML = users.map(user => `
            <tr>
                <td><span class="user-name-cell">${escapeHtml(user.username)}</span></td>
                <td><code>${escapeHtml(user.email)}</code></td>
                <td><span class="role-badge role-${user.role}">${capitalizeFirst(user.role)}</span></td>
                <td><span class="status-badge status-${user.status}">${capitalizeFirst(user.status)}</span></td>
                <td>${user.is_active ? '<span class="status-badge status-active">Yes</span>' : '<span class="status-badge status-inactive">No</span>'}</td>
                <td>${user.last_login_at ? formatDate(user.last_login_at) : '<span class="text-muted">Never</span>'}</td>
                <td>${formatDate(user.created_at)}</td>
                <td>
                    <div class="action-buttons">
                        <button class="btn-icon" title="View Details" data-action="view" data-id="${user.id}">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="16" height="16" fill="currentColor">
                                <path d="M8 2c1.981 0 3.671.992 4.933 2.078 1.27 1.091 2.187 2.345 2.637 3.023a1.62 1.62 0 0 1 0 1.798c-.45.678-1.367 1.932-2.637 3.023C11.67 13.008 9.981 14 8 14c-1.981 0-3.671-.992-4.933-2.078C1.797 10.83.88 9.576.43 8.898a1.62 1.62 0 0 1 0-1.798c.45-.677 1.367-1.931 2.637-3.022C4.33 2.992 6.019 2 8 2ZM1.679 7.932a.12.12 0 0 0 0 .136c.411.622 1.241 1.75 2.366 2.717C5.176 11.758 6.527 12.5 8 12.5c1.473 0 2.825-.742 3.955-1.715 1.124-.967 1.954-2.096 2.366-2.717a.12.12 0 0 0 0-.136c-.412-.621-1.242-1.75-2.366-2.717C10.824 4.242 9.473 3.5 8 3.5c-1.473 0-2.824.742-3.955 1.715-1.124.967-1.954 2.096-2.366 2.717ZM8 10a2 2 0 1 1-.001-3.999A2 2 0 0 1 8 10Z"></path>
                            </svg>
                        </button>
                        ${renderActionButtons(user)}
                    </div>
                </td>
            </tr>
        `).join('');
    }

    // Render action buttons based on user state
    function renderActionButtons(user) {
        let buttons = '';

        // Don't show action buttons for the logged-in user (can't modify yourself)
        const isCurrentUser = user.id === loggedInUserId;

        if (user.status === 'pending') {
            buttons += `
                <button class="btn-icon btn-success" title="Approve" data-action="approve" data-id="${user.id}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="16" height="16" fill="currentColor">
                        <path d="M13.78 4.22a.75.75 0 0 1 0 1.06l-7.25 7.25a.75.75 0 0 1-1.06 0L2.22 9.28a.751.751 0 0 1 .018-1.042.751.751 0 0 1 1.042-.018L6 10.94l6.72-6.72a.75.75 0 0 1 1.06 0Z"></path>
                    </svg>
                </button>
                <button class="btn-icon btn-danger" title="Decline" data-action="decline" data-id="${user.id}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="16" height="16" fill="currentColor">
                        <path d="M3.72 3.72a.75.75 0 0 1 1.06 0L8 6.94l3.22-3.22a.749.749 0 0 1 1.275.326.749.749 0 0 1-.215.734L9.06 8l3.22 3.22a.749.749 0 0 1-.326 1.275.749.749 0 0 1-.734-.215L8 9.06l-3.22 3.22a.751.751 0 0 1-1.042-.018.751.751 0 0 1-.018-1.042L6.94 8 3.72 4.78a.75.75 0 0 1 0-1.06Z"></path>
                    </svg>
                </button>
            `;
        }

        if (user.status === 'approved' && !isCurrentUser) {
            if (user.is_active) {
                buttons += `
                    <button class="btn-icon btn-warning" title="Deactivate" data-action="deactivate" data-id="${user.id}">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="16" height="16" fill="currentColor">
                            <path d="M4.47.22A.749.749 0 0 1 5 0h6c.199 0 .389.079.53.22l4.25 4.25c.141.14.22.331.22.53v6a.749.749 0 0 1-.22.53l-4.25 4.25A.749.749 0 0 1 11 16H5a.749.749 0 0 1-.53-.22L.22 11.53A.749.749 0 0 1 0 11V5c0-.199.079-.389.22-.53Zm.84 1.28L1.5 5.31v5.38l3.81 3.81h5.38l3.81-3.81V5.31L10.69 1.5ZM8 4a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 8 4Zm0 8a1 1 0 1 1 0-2 1 1 0 0 1 0 2Z"></path>
                        </svg>
                    </button>
                `;
            } else {
                buttons += `
                    <button class="btn-icon btn-success" title="Activate" data-action="activate" data-id="${user.id}">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="16" height="16" fill="currentColor">
                            <path d="M8 0a8 8 0 1 1 0 16A8 8 0 0 1 8 0ZM1.5 8a6.5 6.5 0 1 0 13 0 6.5 6.5 0 0 0-13 0Zm9.78-2.22-4.5 4.5a.75.75 0 0 1-1.06 0l-2-2a.75.75 0 1 1 1.06-1.06l1.47 1.47 3.97-3.97a.75.75 0 1 1 1.06 1.06Z"></path>
                        </svg>
                    </button>
                `;
            }
        }

        return buttons;
    }

    // Setup event listeners
    function setupEventListeners() {
        // Filter changes
        elements.filterRole.addEventListener('change', loadUsers);
        elements.filterStatus.addEventListener('change', loadUsers);
        elements.btnRefresh.addEventListener('click', loadUsers);

        // Logout
        elements.btnLogout.addEventListener('click', logout);

        // Table actions
        elements.usersBody.addEventListener('click', handleTableAction);

        // Detail modal
        elements.btnCloseModal.addEventListener('click', closeDetailModal);
        elements.btnCloseDetail.addEventListener('click', closeDetailModal);
        elements.userDetailModal.querySelector('.modal-backdrop').addEventListener('click', closeDetailModal);

        // Confirmation modal
        elements.btnCloseConfirm.addEventListener('click', closeConfirmModal);
        elements.btnCancelConfirm.addEventListener('click', closeConfirmModal);
        elements.confirmModal.querySelector('.modal-backdrop').addEventListener('click', closeConfirmModal);
        elements.btnConfirmAction.addEventListener('click', executeConfirmedAction);

        // Create User modal
        elements.btnCreateUser.addEventListener('click', openCreateUserModal);
        elements.btnCloseCreateUser.addEventListener('click', closeCreateUserModal);
        elements.btnCancelCreateUser.addEventListener('click', closeCreateUserModal);
        elements.createUserModal.querySelector('.modal-backdrop').addEventListener('click', closeCreateUserModal);
        elements.createUserForm.addEventListener('submit', handleCreateUser);
    }

    // Handle table action buttons
    async function handleTableAction(e) {
        const button = e.target.closest('[data-action]');
        if (!button) return;

        const action = button.dataset.action;
        const userId = button.dataset.id;
        const user = users.find(u => u.id === userId);

        if (!user) return;

        currentUser = user;

        switch (action) {
            case 'view':
                showUserDetail(user);
                break;
            case 'approve':
                showConfirmation('Approve User', `Are you sure you want to approve ${user.username}?`, 'approve');
                break;
            case 'decline':
                showConfirmation('Decline User', `Are you sure you want to decline ${user.username}? This cannot be undone.`, 'decline');
                break;
            case 'deactivate':
                showConfirmation('Deactivate User', `Are you sure you want to deactivate ${user.username}? They will no longer be able to log in.`, 'deactivate');
                break;
            case 'activate':
                showConfirmation('Activate User', `Are you sure you want to reactivate ${user.username}?`, 'activate');
                break;
        }
    }

    // Show user detail modal
    function showUserDetail(user) {
        elements.detailUsername.textContent = user.username;
        elements.detailEmail.textContent = user.email;
        elements.detailRole.innerHTML = `<span class="role-badge role-${user.role}">${capitalizeFirst(user.role)}</span>`;
        elements.detailStatus.innerHTML = `<span class="status-badge status-${user.status}">${capitalizeFirst(user.status)}</span>`;
        elements.detailActive.textContent = user.is_active ? 'Yes' : 'No';
        elements.detailCompany.textContent = user.company_id || 'None (System Admin)';
        elements.detailLastLogin.textContent = user.last_login_at ? formatDate(user.last_login_at) : 'Never';
        elements.detailCreated.textContent = formatDate(user.created_at);

        // Update modal action buttons
        let actionsHtml = '<button type="button" class="btn btn-secondary" id="btnCloseDetail">Close</button>';

        // Check if viewing the current logged-in user
        const isCurrentUser = user.id === loggedInUserId;

        if (user.status === 'pending') {
            actionsHtml = `
                <button type="button" class="btn btn-secondary" id="btnCloseDetail">Close</button>
                <button type="button" class="btn btn-danger" data-action="decline" data-id="${user.id}">Decline</button>
                <button type="button" class="btn btn-success" data-action="approve" data-id="${user.id}">Approve</button>
            `;
        } else if (user.status === 'approved' && !isCurrentUser) {
            if (user.is_active) {
                actionsHtml = `
                    <button type="button" class="btn btn-secondary" id="btnCloseDetail">Close</button>
                    <button type="button" class="btn btn-warning" data-action="deactivate" data-id="${user.id}">Deactivate</button>
                `;
            } else {
                actionsHtml = `
                    <button type="button" class="btn btn-secondary" id="btnCloseDetail">Close</button>
                    <button type="button" class="btn btn-success" data-action="activate" data-id="${user.id}">Activate</button>
                `;
            }
        }

        elements.modalActions.innerHTML = actionsHtml;

        // Re-bind close button
        const closeBtn = elements.modalActions.querySelector('#btnCloseDetail');
        if (closeBtn) {
            closeBtn.addEventListener('click', closeDetailModal);
        }

        // Bind action buttons in modal
        elements.modalActions.querySelectorAll('[data-action]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const action = e.target.dataset.action;
                const userId = e.target.dataset.id;
                const user = users.find(u => u.id === userId);
                if (user) {
                    currentUser = user;
                    closeDetailModal();
                    handleTableAction(e);
                }
            });
        });

        elements.userDetailModal.classList.add('active');
    }

    // Close detail modal
    function closeDetailModal() {
        elements.userDetailModal.classList.remove('active');
    }

    // Show confirmation modal
    function showConfirmation(title, message, action) {
        elements.confirmTitle.textContent = title;
        elements.confirmMessage.textContent = message;
        pendingAction = action;

        // Update confirm button style
        elements.btnConfirmAction.className = 'btn';
        switch (action) {
            case 'approve':
            case 'activate':
                elements.btnConfirmAction.classList.add('btn-success');
                elements.btnConfirmAction.textContent = 'Confirm';
                break;
            case 'decline':
            case 'deactivate':
                elements.btnConfirmAction.classList.add('btn-danger');
                elements.btnConfirmAction.textContent = 'Confirm';
                break;
            default:
                elements.btnConfirmAction.classList.add('btn-primary');
                elements.btnConfirmAction.textContent = 'Confirm';
        }

        elements.confirmModal.classList.add('active');
    }

    // Close confirmation modal
    function closeConfirmModal() {
        elements.confirmModal.classList.remove('active');
        pendingAction = null;
    }

    // Execute confirmed action
    async function executeConfirmedAction() {
        if (!currentUser || !pendingAction) return;

        const userId = currentUser.id;
        const action = pendingAction;

        closeConfirmModal();

        try {
            let response;
            switch (action) {
                case 'approve':
                    response = await api.approveUser(userId);
                    showSuccess(`User ${currentUser.username} approved successfully`);
                    break;
                case 'decline':
                    response = await api.declineUser(userId);
                    showSuccess(`User ${currentUser.username} declined`);
                    break;
                case 'deactivate':
                    response = await api.deactivateUser(userId);
                    showSuccess(`User ${currentUser.username} deactivated`);
                    break;
                case 'activate':
                    response = await api.activateUser(userId);
                    showSuccess(`User ${currentUser.username} activated`);
                    break;
            }

            // Reload users
            await loadUsers();
        } catch (error) {
            console.error(`Failed to ${action} user:`, error);
            showError(`Failed to ${action} user: ${error.message}`);
        }
    }

    // Update counts
    function updateUserCount(count) {
        elements.userCount.textContent = `${count} User${count !== 1 ? 's' : ''}`;
    }

    function updatePendingCount() {
        const pendingUsers = users.filter(u => u.status === 'pending');
        if (pendingUsers.length > 0) {
            elements.pendingCount.textContent = `${pendingUsers.length} Pending`;
            elements.pendingCount.style.display = 'inline-block';
        } else {
            elements.pendingCount.style.display = 'none';
        }
    }

    // UI Helpers
    function showLoading() {
        elements.usersBody.innerHTML = `
            <tr>
                <td colspan="8">
                    <div class="loading-spinner">Loading users...</div>
                </td>
            </tr>
        `;
    }

    function showEmptyState() {
        elements.emptyState.style.display = 'flex';
        document.getElementById('usersTable').style.display = 'none';
    }

    function hideEmptyState() {
        elements.emptyState.style.display = 'none';
        document.getElementById('usersTable').style.display = 'table';
    }

    function showError(message) {
        // Simple alert for now, could be replaced with toast
        alert(message);
    }

    function showSuccess(message) {
        // Simple alert for now, could be replaced with toast
        console.log('Success:', message);
    }

    function openCreateUserModal() {
        elements.createUserForm.reset();
        elements.createUserModal.classList.add('active');
    }

    function closeCreateUserModal() {
        elements.createUserModal.classList.remove('active');
        elements.createUserForm.reset();
    }

    async function handleCreateUser(e) {
        e.preventDefault();

        // Validate passwords match
        if (elements.newPassword.value !== elements.confirmNewPassword.value) {
            showError('Passwords do not match');
            return;
        }

        const userData = {
            username: elements.newUsername.value.trim(),
            email: elements.newEmail.value.trim(),
            password: elements.newPassword.value,
            role: 'tenant',
            company_id: elements.newCompany.value,
        };

        try {
            const response = await api.post('/users', userData);
            if (response.success) {
                showSuccess(`User ${userData.username} created successfully`);
                closeCreateUserModal();
                await loadUsers();
            } else {
                showError(response.error || 'Failed to create user');
            }
        } catch (error) {
            console.error('Failed to create user:', error);
            showError(error.message || 'Failed to create user');
        }
    }

    // Utility functions
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function capitalizeFirst(str) {
        if (!str) return '';
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    function formatDate(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function logout() {
        localStorage.removeItem('auth_token');
        localStorage.removeItem('auth_expires');
        localStorage.removeItem('user_id');
        window.location.href = '/login.html';
    }

    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', init);
})();
