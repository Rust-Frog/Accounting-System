/**
 * Settings Page Controller
 */

(function () {
    'use strict';

    const api = new ApiClient();
    let currentSettings = null;
    let currentUser = null;
    let otpSetupData = null;

    // ========== Initialization ==========

    async function init() {
        await loadSettings();
        bindEvents();
        applyTheme();
    }

    async function loadSettings() {
        try {
            const response = await api.getSettings();
            if (response?.data) {
                currentUser = response.data.user;
                currentSettings = response.data.settings;
                renderProfile();
                renderSettings();
            }
        } catch (error) {
            console.error('Failed to load settings:', error);
            showToast('Failed to load settings', 'error');
        }
    }

    function renderProfile() {
        if (!currentUser) return;

        const avatar = currentUser.username.charAt(0).toUpperCase();
        document.getElementById('profileAvatar').textContent = avatar;
        document.getElementById('userAvatar').textContent = avatar;
        document.getElementById('profileUsername').textContent = currentUser.username;
        document.getElementById('profileEmail').textContent = currentUser.email;

        // Update profile badge (find the span inside)
        const profileRoleEl = document.getElementById('profileRole');
        const roleSpan = profileRoleEl.querySelector('span');
        if (roleSpan) {
            roleSpan.textContent = currentUser.role.toUpperCase();
        }

        document.getElementById('userName').textContent = currentUser.username;
        document.getElementById('userRole').textContent = currentUser.role === 'admin' ? 'Administrator' : 'User';

        if (currentUser.created_at) {
            const createdDate = new Date(currentUser.created_at).toLocaleDateString();
            document.getElementById('profileCreatedAt').textContent = `Member since ${createdDate}`;
        }

        // Update OTP UI
        updateOtpUI(currentUser.otp_enabled);
    }

    function renderSettings() {
        if (!currentSettings) return;

        // Theme
        document.querySelectorAll('.theme-option').forEach(opt => {
            opt.classList.remove('active');
            if (opt.dataset.theme === currentSettings.theme) {
                opt.classList.add('active');
            }
        });

        // Localization
        document.getElementById('settingTimezone').value = currentSettings.timezone || 'UTC';
        document.getElementById('settingDateFormat').value = currentSettings.date_format || 'YYYY-MM-DD';
        document.getElementById('settingNumberFormat').value = currentSettings.number_format || 'en-US';

        // Notifications
        document.getElementById('settingEmailNotifications').checked = currentSettings.email_notifications;
        document.getElementById('settingBrowserNotifications').checked = currentSettings.browser_notifications;

        // Session
        document.getElementById('settingSessionTimeout').value = currentSettings.session_timeout_minutes || 30;
    }

    function updateOtpUI(enabled) {
        const btn = document.getElementById('btnToggleOtp');
        const status = document.getElementById('otpStatus');
        const backupRow = document.getElementById('backupCodesRow');

        if (enabled) {
            btn.textContent = 'Disable 2FA';
            btn.classList.remove('btn-secondary');
            btn.classList.add('btn-danger');
            status.innerHTML = '<span class="badge badge-success">Enabled</span> Your account is protected with 2FA';
            backupRow.style.display = 'flex';
        } else {
            btn.textContent = 'Enable 2FA';
            btn.classList.remove('btn-danger');
            btn.classList.add('btn-secondary');
            status.textContent = 'Add an extra layer of security to your account';
            backupRow.style.display = 'none';
        }
    }

    // ========== Event Binding ==========

    function bindEvents() {
        // Logout
        document.getElementById('btnLogout').addEventListener('click', handleLogout);

        // Theme selection
        document.querySelectorAll('.theme-option').forEach(opt => {
            opt.addEventListener('click', () => handleThemeChange(opt.dataset.theme));
        });

        // Localization changes
        document.getElementById('settingTimezone').addEventListener('change', handleLocalizationChange);
        document.getElementById('settingDateFormat').addEventListener('change', handleLocalizationChange);
        document.getElementById('settingNumberFormat').addEventListener('change', handleLocalizationChange);

        // Notification toggles
        document.getElementById('settingEmailNotifications').addEventListener('change', handleNotificationChange);
        document.getElementById('settingBrowserNotifications').addEventListener('change', handleNotificationChange);

        // Session timeout
        document.getElementById('settingSessionTimeout').addEventListener('change', handleSessionTimeoutChange);

        // Password change
        document.getElementById('btnChangePassword').addEventListener('click', () => openModal('modalChangePassword'));
        document.getElementById('closeChangePasswordModal').addEventListener('click', () => closeModal('modalChangePassword'));
        document.getElementById('btnCancelChangePassword').addEventListener('click', () => closeModal('modalChangePassword'));
        document.getElementById('formChangePassword').addEventListener('submit', handlePasswordChange);

        // OTP
        document.getElementById('btnToggleOtp').addEventListener('click', handleOtpToggle);
        document.getElementById('closeEnableOtpModal').addEventListener('click', () => closeModal('modalEnableOtp'));
        document.getElementById('btnCancelEnableOtp').addEventListener('click', () => closeModal('modalEnableOtp'));
        document.getElementById('btnVerifyOtp').addEventListener('click', handleOtpVerify);
        document.getElementById('closeDisableOtpModal').addEventListener('click', () => closeModal('modalDisableOtp'));
        document.getElementById('btnCancelDisableOtp').addEventListener('click', () => closeModal('modalDisableOtp'));
        document.getElementById('btnConfirmDisableOtp').addEventListener('click', handleOtpDisable);

        // Backup codes
        document.getElementById('btnRegenerateBackupCodes').addEventListener('click', () => openModal('modalBackupCodes'));
        document.getElementById('closeBackupCodesModal').addEventListener('click', () => closeModal('modalBackupCodes'));
        document.getElementById('btnCancelBackupCodes').addEventListener('click', () => closeModal('modalBackupCodes'));
        document.getElementById('btnConfirmRegenerateBackup').addEventListener('click', handleRegenerateBackupCodes);
    }

    // ========== Handlers ==========

    function handleLogout() {
        localStorage.removeItem('auth_token');
        localStorage.removeItem('auth_expires');
        localStorage.removeItem('user_id');
        window.location.href = '/login.html';
    }

    async function handleThemeChange(theme) {
        try {
            await api.updateTheme(theme);
            document.querySelectorAll('.theme-option').forEach(opt => {
                opt.classList.toggle('active', opt.dataset.theme === theme);
            });
            currentSettings.theme = theme;
            applyTheme();
            showToast('Theme updated', 'success');
        } catch (error) {
            console.error('Failed to update theme:', error);
            showToast('Failed to update theme', 'error');
        }
    }

    async function handleLocalizationChange() {
        try {
            await api.updateLocalization({
                timezone: document.getElementById('settingTimezone').value,
                date_format: document.getElementById('settingDateFormat').value,
                number_format: document.getElementById('settingNumberFormat').value
            });
            showToast('Localization settings updated', 'success');
        } catch (error) {
            console.error('Failed to update localization:', error);
            showToast('Failed to update settings', 'error');
        }
    }

    async function handleNotificationChange() {
        try {
            await api.updateNotifications(
                document.getElementById('settingEmailNotifications').checked,
                document.getElementById('settingBrowserNotifications').checked
            );
            showToast('Notification preferences updated', 'success');
        } catch (error) {
            console.error('Failed to update notifications:', error);
            showToast('Failed to update settings', 'error');
        }
    }

    async function handleSessionTimeoutChange() {
        try {
            const minutes = parseInt(document.getElementById('settingSessionTimeout').value, 10);
            await api.updateSessionTimeout(minutes);
            showToast('Session timeout updated', 'success');
        } catch (error) {
            console.error('Failed to update session timeout:', error);
            showToast('Failed to update settings', 'error');
        }
    }

    async function handlePasswordChange(e) {
        e.preventDefault();

        const currentPwd = document.getElementById('currentPassword').value;
        const newPwd = document.getElementById('newPassword').value;
        const confirmPwd = document.getElementById('confirmPassword').value;

        if (newPwd !== confirmPwd) {
            showToast('Passwords do not match', 'error');
            return;
        }

        try {
            await api.changePassword(currentPwd, newPwd);
            closeModal('modalChangePassword');
            document.getElementById('formChangePassword').reset();
            showToast('Password changed successfully', 'success');
        } catch (error) {
            console.error('Failed to change password:', error);
            showToast(error.message || 'Failed to change password', 'error');
        }
    }

    async function handleOtpToggle() {
        if (currentUser?.otp_enabled) {
            openModal('modalDisableOtp');
        } else {
            await startOtpSetup();
        }
    }

    async function startOtpSetup() {
        try {
            const response = await api.enableOtp();
            if (response?.data) {
                otpSetupData = response.data;

                // Display QR code (if you have a QR library, generate from qr_uri)
                const qrContainer = document.getElementById('otpQrContainer');
                qrContainer.innerHTML = `<img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent(otpSetupData.qr_uri)}" alt="QR Code">`;

                // Display secret
                document.getElementById('otpSecretDisplay').textContent = otpSetupData.secret;

                // Display backup codes
                const codesList = document.getElementById('backupCodesList');
                codesList.innerHTML = otpSetupData.backup_codes.map(code =>
                    `<div class="backup-code">${code}</div>`
                ).join('');
                document.getElementById('backupCodesDisplay').style.display = 'block';

                openModal('modalEnableOtp');
            }
        } catch (error) {
            console.error('Failed to start OTP setup:', error);
            showToast(error.message || 'Failed to enable 2FA', 'error');
        }
    }

    async function handleOtpVerify() {
        const code = document.getElementById('otpVerifyCode').value.trim();
        if (!code || code.length !== 6) {
            showToast('Please enter a valid 6-digit code', 'error');
            return;
        }

        try {
            await api.verifyOtp(code);
            closeModal('modalEnableOtp');
            currentUser.otp_enabled = true;
            updateOtpUI(true);
            showToast('Two-factor authentication enabled!', 'success');
        } catch (error) {
            console.error('Failed to verify OTP:', error);
            showToast(error.message || 'Invalid OTP code', 'error');
        }
    }

    async function handleOtpDisable() {
        const password = document.getElementById('disableOtpPassword').value;
        if (!password) {
            showToast('Please enter your password', 'error');
            return;
        }

        try {
            await api.disableOtp(password);
            closeModal('modalDisableOtp');
            document.getElementById('disableOtpPassword').value = '';
            currentUser.otp_enabled = false;
            updateOtpUI(false);
            showToast('Two-factor authentication disabled', 'success');
        } catch (error) {
            console.error('Failed to disable OTP:', error);
            showToast(error.message || 'Failed to disable 2FA', 'error');
        }
    }

    async function handleRegenerateBackupCodes() {
        const password = document.getElementById('regenerateBackupPassword').value;
        if (!password) {
            showToast('Please enter your password', 'error');
            return;
        }

        try {
            const response = await api.regenerateBackupCodes(password);
            if (response?.data?.backup_codes) {
                const codesList = document.getElementById('newBackupCodesList');
                Security.safeInnerHTML(codesList, response.data.backup_codes.map(code =>
                    `<div class="backup-code">${Security.escapeHtml(code)}</div>`
                ).join(''));
                document.getElementById('newBackupCodesDisplay').style.display = 'block';
                document.getElementById('btnConfirmRegenerateBackup').style.display = 'none';
                showToast('New backup codes generated', 'success');
            }
        } catch (error) {
            console.error('Failed to regenerate backup codes:', error);
            showToast(error.message || 'Failed to regenerate codes', 'error');
        }
    }

    // ========== Theme ==========

    function applyTheme() {
        const theme = currentSettings?.theme || localStorage.getItem('theme') || 'light';

        if (theme === 'system') {
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.setAttribute('data-theme', prefersDark ? 'dark' : 'light');
        } else {
            document.documentElement.setAttribute('data-theme', theme);
        }

        localStorage.setItem('theme', theme);
    }

    // ========== Modal Helpers ==========

    function openModal(id) {
        document.getElementById(id).classList.add('active');
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
    }

    // ========== Toast ==========

    function showToast(message, type = 'info') {
        const existing = document.querySelector('.toast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        Security.safeInnerHTML(toast, Security.escapeHtml(message));
        toast.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 12px 24px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 10000;
            animation: slideIn 0.3s ease;
            background: ${type === 'success' ? 'var(--status-success)' :
                type === 'error' ? 'var(--status-error)' : 'var(--accent-primary)'};
        `;

        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', init);
})();
