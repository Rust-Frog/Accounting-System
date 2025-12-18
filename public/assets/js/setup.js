/**
 * Accounting System - Setup Flow Controller
 * Handles multi-step admin setup with TOTP integration
 */

class SetupManager {
    constructor() {
        this.currentStep = 1;
        this.otpSecret = null;
        this.apiBase = '/api/v1/setup';

        // SECURITY: Sanitize secret to ensure only valid base32 characters
        this.sanitizeSecret = (secret) => {
            if (typeof secret !== 'string') return '';
            // Base32 alphabet only (A-Z, 2-7)
            return secret.replace(/[^A-Z2-7]/gi, '').toUpperCase();
        };

        this.views = {
            welcome: document.getElementById('view-welcome'),
            totp: document.getElementById('view-totp'),
            form: document.getElementById('view-form'),
            success: document.getElementById('view-success')
        };

        this.elements = {
            stepIndicator: document.getElementById('stepIndicator'),
            headerBadge: document.getElementById('headerBadge'),
            statusBadge: document.getElementById('statusBadge'),
            statusSpinner: document.getElementById('statusSpinner'),
            statusText: document.getElementById('statusText'),
            btnStart: document.getElementById('btnStart'),
            qrCanvas: document.getElementById('qrCanvas'),
            secretCode: document.getElementById('secretCode'),
            btnCopySecret: document.getElementById('btnCopySecret'),
            btnBackToWelcome: document.getElementById('btnBackToWelcome'),
            btnToForm: document.getElementById('btnToForm'),
            setupForm: document.getElementById('setupForm'),
            btnBackToTotp: document.getElementById('btnBackToTotp'),
            formError: document.getElementById('formError')
        };

        this.init();
    }

    async init() {
        this.bindEvents();
        await this.checkStatus();
    }

    bindEvents() {
        this.elements.btnStart.addEventListener('click', () => this.initSetup());
        this.elements.btnCopySecret.addEventListener('click', () => this.copySecret());
        this.elements.btnBackToWelcome.addEventListener('click', () => this.goToStep(1));
        this.elements.btnToForm.addEventListener('click', () => this.goToStep(3));
        this.elements.btnBackToTotp.addEventListener('click', () => this.goToStep(2));
        this.elements.setupForm.addEventListener('submit', (e) => this.handleSubmit(e));
    }

    async checkStatus() {
        try {
            const response = await fetch(`${this.apiBase}/status`);

            // 403 means system is already configured - redirect immediately
            if (response.status === 403) {
                this.elements.headerBadge.textContent = 'Access Denied - Redirecting...';
                this.elements.headerBadge.classList.remove('badge-warning');
                this.elements.headerBadge.classList.add('badge-danger');
                this.elements.statusSpinner.style.display = 'none';
                this.elements.statusText.textContent = 'System Already Secured';
                this.elements.statusBadge.classList.remove('badge-success');
                this.elements.statusBadge.classList.add('badge-danger');

                // Redirect to login
                setTimeout(() => {
                    window.location.href = '/login.html';
                }, 1500);
                return;
            }

            const data = await response.json();
            this.elements.statusSpinner.style.display = 'none';

            if (data.data?.is_setup_required) {
                // System needs setup - allow access
                this.elements.headerBadge.textContent = 'Configuration Required';
                this.elements.statusText.textContent = 'Ready for Configuration';
                this.elements.btnStart.disabled = false;
            } else {
                // Double-check: system already configured
                this.elements.headerBadge.textContent = 'Access Denied - Redirecting...';
                this.elements.headerBadge.classList.remove('badge-warning');
                this.elements.headerBadge.classList.add('badge-danger');
                this.elements.statusText.textContent = 'System Already Secured';
                this.elements.statusBadge.classList.remove('badge-success');
                this.elements.statusBadge.classList.add('badge-danger');

                // Redirect to login
                setTimeout(() => {
                    window.location.href = '/login.html';
                }, 1500);
            }
        } catch (error) {
            this.elements.headerBadge.textContent = 'Connection Error';
            this.elements.headerBadge.classList.remove('badge-warning');
            this.elements.headerBadge.classList.add('badge-danger');
            this.elements.statusSpinner.style.display = 'none';
            this.elements.statusText.textContent = 'Unable to connect';
            this.elements.statusBadge.classList.remove('badge-success');
            this.elements.statusBadge.classList.add('badge-danger');
            console.error('Status check failed:', error);
        }
    }

    async initSetup() {
        this.elements.btnStart.disabled = true;
        this.elements.btnStart.innerHTML = '<span class="spinner"></span> Initializing...';

        try {
            const response = await fetch(`${this.apiBase}/init`, { method: 'POST' });
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'Initialization failed');
            }

            // SECURITY: Sanitize the secret from API response
            this.otpSecret = this.sanitizeSecret(data.data.secret);
            const provisioningUri = data.data.provisioning_uri;

            // Generate QR Code
            if (typeof QRCode !== 'undefined') {
                QRCode.toCanvas(this.elements.qrCanvas, provisioningUri, {
                    width: 200,
                    margin: 2,
                    color: { dark: '#000000', light: '#ffffff' }
                });
            }

            // Display secret with formatting
            this.elements.secretCode.textContent = this.formatSecret(this.otpSecret);

            this.goToStep(2);
        } catch (error) {
            alert('Setup initialization failed: ' + error.message);
            this.elements.btnStart.disabled = false;
            this.elements.btnStart.textContent = 'Get Started';
        }
    }

    formatSecret(secret) {
        // Add spaces every 4 characters for readability
        return secret.match(/.{1,4}/g)?.join(' ') || secret;
    }

    async copySecret() {
        try {
            await navigator.clipboard.writeText(this.otpSecret);
            const originalTitle = this.elements.btnCopySecret.title;
            this.elements.btnCopySecret.title = 'Copied!';
            setTimeout(() => {
                this.elements.btnCopySecret.title = originalTitle;
            }, 2000);
        } catch (error) {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = this.otpSecret;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
        }
    }

    async handleSubmit(event) {
        event.preventDefault();

        const form = event.target;
        const password = form.password.value;
        const confirmPassword = form.confirmPassword.value;

        // Client-side validation
        if (password !== confirmPassword) {
            this.showFormError('Passwords do not match');
            return;
        }

        const submitBtn = document.getElementById('btnSubmit');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner"></span> Creating...';
        this.hideFormError();

        const payload = {
            username: form.username.value,
            email: form.email.value,
            password: password,
            otp_secret: this.otpSecret,
            otp_code: form.otpCode.value
        };

        try {
            const response = await fetch(`${this.apiBase}/complete`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const data = await response.json();

            if (!response.ok) {
                const errorMsg = data.error?.message || data.error || 'Setup failed';
                throw new Error(errorMsg);
            }

            this.goToStep(4);
        } catch (error) {
            this.showFormError(error.message);
            submitBtn.disabled = false;
            submitBtn.textContent = 'Complete Setup';
        }
    }

    showFormError(message) {
        this.elements.formError.textContent = message;
        this.elements.formError.style.display = 'block';
    }

    hideFormError() {
        this.elements.formError.style.display = 'none';
    }

    goToStep(step) {
        this.currentStep = step;

        // Update views
        Object.values(this.views).forEach(view => view.classList.remove('active'));

        switch (step) {
            case 1:
                this.views.welcome.classList.add('active');
                break;
            case 2:
                this.views.totp.classList.add('active');
                break;
            case 3:
                this.views.form.classList.add('active');
                break;
            case 4:
                this.views.success.classList.add('active');
                break;
        }

        // Update step indicator
        const dots = this.elements.stepIndicator.querySelectorAll('.step-dot');
        dots.forEach((dot, index) => {
            dot.classList.remove('active', 'completed');
            if (index + 1 < step) {
                dot.classList.add('completed');
            } else if (index + 1 === step) {
                dot.classList.add('active');
            }
        });
    }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    new SetupManager();
});
