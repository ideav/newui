// API Configuration
class ApiConfig {
    constructor() {
        this.host = localStorage.getItem('apiHost') || '';
        this.db = localStorage.getItem('apiDb') || '';
    }

    setConfig(host, db) {
        this.host = host;
        this.db = db;
        localStorage.setItem('apiHost', host);
        localStorage.setItem('apiDb', db);
    }

    getConfig() {
        return {
            host: this.host,
            db: this.db
        };
    }

    isConfigured() {
        return this.host && this.db;
    }

    getBaseUrl() {
        return `https://${this.host}/${this.db}`;
    }
}

// Theme management
class ThemeManager {
    constructor() {
        this.theme = localStorage.getItem('theme') || 'light';
        this.applyTheme();
    }

    applyTheme() {
        document.documentElement.setAttribute('data-theme', this.theme);
        this.updateThemeButton();
    }

    toggleTheme() {
        this.theme = this.theme === 'light' ? 'dark' : 'light';
        localStorage.setItem('theme', this.theme);
        this.applyTheme();
    }

    updateThemeButton() {
        const themeToggle = document.getElementById('theme-toggle');
        if (themeToggle) {
            themeToggle.textContent = this.theme === 'light' ? '🌙 Темная' : '☀️ Светлая';
        }
    }
}

// Authentication management
class AuthManager {
    constructor(apiConfig) {
        this.apiConfig = apiConfig;
        this.currentUser = this.loadUser();
        this.xsrfToken = localStorage.getItem('xsrfToken') || '';
        this.authToken = localStorage.getItem('authToken') || '';
    }

    loadUser() {
        const userJson = localStorage.getItem('currentUser');
        return userJson ? JSON.parse(userJson) : null;
    }

    saveUser(user) {
        localStorage.setItem('currentUser', JSON.stringify(user));
        this.currentUser = user;
    }

    clearUser() {
        localStorage.removeItem('currentUser');
        localStorage.removeItem('xsrfToken');
        localStorage.removeItem('authToken');
        this.currentUser = null;
        this.xsrfToken = '';
        this.authToken = '';
    }

    isAuthenticated() {
        return this.currentUser !== null && this.currentUser.user !== 'guest';
    }

    async checkAuth() {
        if (!this.apiConfig.isConfigured()) {
            console.log('API not configured, using localStorage mode');
            return { success: false, mode: 'localStorage', message: 'API not configured' };
        }

        try {
            const url = `${this.apiConfig.getBaseUrl()}/xsrf`;
            const response = await fetch(url, {
                method: 'GET',
                credentials: 'include'
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            // Save XSRF token for POST requests
            this.xsrfToken = data._xsrf || '';
            localStorage.setItem('xsrfToken', this.xsrfToken);

            // Save user info
            const user = {
                user: data.user || 'guest',
                role: data.role || 'guest',
                id: data.id || '',
                token: data.token || ''
            };

            this.saveUser(user);

            if (data.token) {
                this.authToken = data.token;
                localStorage.setItem('authToken', data.token);
            }

            return {
                success: true,
                mode: 'api',
                user: user,
                isGuest: user.user === 'guest'
            };
        } catch (error) {
            console.error('Auth check failed:', error);
            return {
                success: false,
                mode: 'localStorage',
                message: error.message
            };
        }
    }

    async register(email, password) {
        if (!this.apiConfig.isConfigured()) {
            return this.registerLocalStorage(email, password);
        }

        try {
            const url = `${this.apiConfig.getBaseUrl()}/_m_new/18?up=1&next_act=inform`;
            const formData = new URLSearchParams();
            formData.append('_xsrf', this.xsrfToken);
            formData.append('t18', email);
            formData.append('t20', password);

            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData.toString(),
                credentials: 'include'
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.error || data.msg) {
                return {
                    success: !data.error,
                    message: data.msg || 'Регистрация прошла успешно. Проверьте вашу почту для подтверждения.',
                    mode: 'api'
                };
            }

            return {
                success: true,
                message: 'Регистрация прошла успешно. Проверьте вашу почту для подтверждения.',
                mode: 'api'
            };
        } catch (error) {
            console.error('Registration failed:', error);
            return {
                success: false,
                message: `Ошибка регистрации: ${error.message}`,
                mode: 'api'
            };
        }
    }

    registerLocalStorage(email, password) {
        // Store pending user (waiting for email confirmation)
        const users = this.getUsers();

        // Check if email already exists
        if (users.some(u => u.email === email)) {
            return { success: false, message: 'Этот email уже зарегистрирован' };
        }

        const confirmationToken = this.generateToken();
        const newUser = {
            email,
            password, // In production, this should be hashed
            emailConfirmed: false,
            confirmationToken,
            createdAt: new Date().toISOString()
        };

        users.push(newUser);
        localStorage.setItem('users', JSON.stringify(users));

        return {
            success: true,
            confirmationToken,
            message: 'Регистрация прошла успешно. Проверьте вашу почту для подтверждения.',
            mode: 'localStorage'
        };
    }

    confirmEmail(token) {
        const users = this.getUsers();
        const user = users.find(u => u.confirmationToken === token);

        if (!user) {
            return { success: false, message: 'Неверный токен подтверждения' };
        }

        user.emailConfirmed = true;
        localStorage.setItem('users', JSON.stringify(users));

        return { success: true, message: 'Email подтвержден успешно!' };
    }

    async login(email, password) {
        if (!this.apiConfig.isConfigured()) {
            return this.loginLocalStorage(email, password);
        }

        try {
            const url = `${this.apiConfig.getBaseUrl()}/auth?JSON`;
            const formData = new URLSearchParams();
            formData.append('db', this.apiConfig.db);
            formData.append('login', email);
            formData.append('pwd', password);

            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData.toString(),
                credentials: 'include'
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.msg && data.msg !== '') {
                return {
                    success: false,
                    message: data.msg,
                    mode: 'api'
                };
            }

            // Save tokens and user info
            this.xsrfToken = data._xsrf || '';
            this.authToken = data.token || '';
            localStorage.setItem('xsrfToken', this.xsrfToken);
            localStorage.setItem('authToken', this.authToken);

            const user = {
                user: email,
                id: data.id || '',
                token: data.token || '',
                role: 'user'
            };

            this.saveUser(user);

            return {
                success: true,
                message: 'Вход выполнен успешно!',
                mode: 'api'
            };
        } catch (error) {
            console.error('Login failed:', error);
            return {
                success: false,
                message: `Ошибка входа: ${error.message}`,
                mode: 'api'
            };
        }
    }

    loginLocalStorage(email, password) {
        const users = this.getUsers();
        const user = users.find(u => u.email === email && u.password === password);

        if (!user) {
            return { success: false, message: 'Неверный email или пароль' };
        }

        if (!user.emailConfirmed) {
            return { success: false, message: 'Пожалуйста, подтвердите ваш email перед входом' };
        }

        this.saveUser(user);
        return { success: true, message: 'Вход выполнен успешно!' };
    }

    logout() {
        this.clearUser();
        window.location.href = 'login.html';
    }

    getUsers() {
        const usersJson = localStorage.getItem('users');
        return usersJson ? JSON.parse(usersJson) : [];
    }

    generateToken() {
        return Math.random().toString(36).substring(2) + Date.now().toString(36);
    }

    getUserInitial() {
        if (this.currentUser) {
            const userField = this.currentUser.user || this.currentUser.email || '';
            if (userField && userField !== 'guest') {
                return userField.charAt(0).toUpperCase();
            }
        }
        return 'G';
    }

    getUserEmail() {
        if (this.currentUser) {
            return this.currentUser.user || this.currentUser.email || 'guest';
        }
        return 'guest';
    }
}

// Initialize theme and auth managers
const themeManager = new ThemeManager();
const apiConfig = new ApiConfig();
const authManager = new AuthManager(apiConfig);

// Theme toggle handler
document.addEventListener('DOMContentLoaded', async () => {
    const themeToggle = document.getElementById('theme-toggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            themeManager.toggleTheme();
        });
    }

    // Check authentication status with API if configured
    if (apiConfig.isConfigured()) {
        await authManager.checkAuth();
    }

    // Update account info in navbar
    const accountAvatar = document.getElementById('account-avatar');
    const accountEmail = document.getElementById('account-email');

    if (accountAvatar && accountEmail && authManager.currentUser) {
        accountAvatar.textContent = authManager.getUserInitial();
        accountEmail.textContent = authManager.getUserEmail();

        // Hide account info if guest user
        const accountInfo = document.querySelector('.account-info');
        if (accountInfo && authManager.currentUser.user === 'guest') {
            accountInfo.style.display = 'none';
        }
    }

    // Logout handler
    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', (e) => {
            e.preventDefault();
            authManager.logout();
        });
    }

    // Registration form handler
    const registerForm = document.getElementById('register-form');
    if (registerForm) {
        registerForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm-password').value;

            if (password !== confirmPassword) {
                alert('Пароли не совпадают');
                return;
            }

            if (password.length < 6) {
                alert('Пароль должен содержать минимум 6 символов');
                return;
            }

            const result = await authManager.register(email, password);

            if (result.success) {
                if (result.mode === 'localStorage' && result.confirmationToken) {
                    // Show confirmation link (in production, this would be sent via email)
                    const confirmationLink = `${window.location.origin}/confirm.html?token=${result.confirmationToken}`;
                    localStorage.setItem('lastConfirmationLink', confirmationLink);
                }
                alert(result.message);

                if (result.mode === 'api') {
                    // For API mode, redirect to login since email will be sent
                    window.location.href = 'login.html';
                } else {
                    window.location.href = 'registration-success.html';
                }
            } else {
                alert(result.message);
            }
        });
    }

    // Login form handler
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;

            const result = await authManager.login(email, password);

            if (result.success) {
                window.location.href = 'index.html';
            } else {
                alert(result.message);
            }
        });
    }

    // Email confirmation handler
    const confirmBtn = document.getElementById('confirm-email-btn');
    if (confirmBtn) {
        const urlParams = new URLSearchParams(window.location.search);
        const token = urlParams.get('token');

        if (token) {
            confirmBtn.addEventListener('click', () => {
                const result = authManager.confirmEmail(token);

                if (result.success) {
                    alert(result.message);
                    window.location.href = 'login.html';
                } else {
                    alert(result.message);
                }
            });
        }
    }

    // Show confirmation link on success page
    const confirmationLinkEl = document.getElementById('confirmation-link');
    if (confirmationLinkEl) {
        const link = localStorage.getItem('lastConfirmationLink');
        if (link) {
            confirmationLinkEl.href = link;
            confirmationLinkEl.textContent = link;
        }
    }

    // Protect authenticated pages
    const protectedPages = ['index.html', '/'];
    const currentPage = window.location.pathname.split('/').pop() || 'index.html';

    if (protectedPages.some(page => currentPage.includes(page) || currentPage === '')) {
        if (!authManager.isAuthenticated() && currentPage !== 'login.html' && currentPage !== 'register.html') {
            const accountInfo = document.querySelector('.account-info');
            if (accountInfo) {
                // Only redirect if we're on the main page and not authenticated
                if (currentPage === 'index.html' || currentPage === '') {
                    window.location.href = 'login.html';
                }
            }
        }
    }
});
