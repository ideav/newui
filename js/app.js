// ============================================================
// Internationalization (i18n)
// ============================================================
const I18N = {
    ru: {
        'page.title': 'Главная',
        'nav.dark': 'Темная',
        'nav.light': 'Светлая',
        'nav.login': 'Войти',
        'nav.cabinet': 'Личный кабинет',
        'welcome.title': 'Добро пожаловать!',
        'welcome.subtitle': 'Сервис для работы с вашими данными.',
        'auth.loginTab': 'Вход',
        'auth.registerTab': 'Регистрация',
        'auth.loginTitle': 'Вход',
        'auth.loginSubtitle': 'Войдите в свой аккаунт',
        'auth.registerTitle': 'Регистрация',
        'auth.registerSubtitle': 'Создайте новый аккаунт',
        'auth.email': 'Email адрес',
        'auth.password': 'Пароль',
        'auth.confirmPassword': 'Подтвердите пароль',
        'auth.loginBtn': 'Войти',
        'auth.registerBtn': 'Зарегистрироваться',
        'auth.or': 'или',
        'auth.yandex': 'Яндекс',
        'auth.close': '✕ Закрыть',
        'msg.passwordMismatch': 'Пароли не совпадают',
        'msg.passwordShort': 'Пароль должен содержать минимум 6 символов',
        'msg.loginSuccess': 'Вход выполнен успешно!',
        'msg.registerSuccess': 'Регистрация прошла успешно. Проверьте вашу почту для подтверждения.',
        'msg.loginError': 'Ошибка входа: ',
        'msg.registerError': 'Ошибка регистрации: '
    },
    en: {
        'page.title': 'Home',
        'nav.dark': 'Dark',
        'nav.light': 'Light',
        'nav.login': 'Sign In',
        'nav.cabinet': 'Personal Cabinet',
        'welcome.title': 'Welcome!',
        'welcome.subtitle': 'Service for working with your data.',
        'auth.loginTab': 'Sign In',
        'auth.registerTab': 'Register',
        'auth.loginTitle': 'Sign In',
        'auth.loginSubtitle': 'Sign in to your account',
        'auth.registerTitle': 'Register',
        'auth.registerSubtitle': 'Create a new account',
        'auth.email': 'Email address',
        'auth.password': 'Password',
        'auth.confirmPassword': 'Confirm password',
        'auth.loginBtn': 'Sign In',
        'auth.registerBtn': 'Register',
        'auth.or': 'or',
        'auth.yandex': 'Yandex',
        'auth.close': '✕ Close',
        'msg.passwordMismatch': 'Passwords do not match',
        'msg.passwordShort': 'Password must be at least 6 characters',
        'msg.loginSuccess': 'Signed in successfully!',
        'msg.registerSuccess': 'Registration successful. Please check your email for confirmation.',
        'msg.loginError': 'Login error: ',
        'msg.registerError': 'Registration error: '
    }
};

class I18nManager {
    constructor() {
        this.lang = localStorage.getItem('lang') || 'ru';
    }

    t(key) {
        return (I18N[this.lang] && I18N[this.lang][key]) || (I18N['ru'][key]) || key;
    }

    setLang(lang) {
        if (I18N[lang]) {
            this.lang = lang;
            localStorage.setItem('lang', lang);
            this.applyAll();
        }
    }

    toggleLang() {
        const langs = Object.keys(I18N);
        const idx = langs.indexOf(this.lang);
        this.setLang(langs[(idx + 1) % langs.length]);
    }

    applyAll() {
        document.querySelectorAll('[data-i18n]').forEach(el => {
            const key = el.getAttribute('data-i18n');
            el.textContent = this.t(key);
        });
        document.title = this.t('page.title');
        const langToggle = document.getElementById('lang-toggle');
        if (langToggle) langToggle.textContent = this.lang.toUpperCase();
        const themeToggle = document.getElementById('theme-toggle');
        if (themeToggle) {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            themeToggle.innerHTML = (isDark ? '☀️ ' : '🌙 ') + '<span data-i18n="nav.' + (isDark ? 'light' : 'dark') + '">' + this.t(isDark ? 'nav.light' : 'nav.dark') + '</span>';
        }
    }
}

// ============================================================
// Theme management
// ============================================================
class ThemeManager {
    constructor(i18n) {
        this.i18n = i18n;
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
            const isDark = this.theme === 'dark';
            const labelKey = isDark ? 'nav.light' : 'nav.dark';
            themeToggle.innerHTML = (isDark ? '☀️ ' : '🌙 ') + '<span data-i18n="' + labelKey + '">' + this.i18n.t(labelKey) + '</span>';
        }
    }
}

// ============================================================
// Cookie utilities
// ============================================================
const CookieUtil = {
    get(name) {
        const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : null;
    },
    delete(name) {
        document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/';
    },
    getAllIdb() {
        // Returns array of db names for all idb_* cookies found
        const result = [];
        const cookies = document.cookie.split(';');
        for (const c of cookies) {
            const trimmed = c.trim();
            if (trimmed.startsWith('idb_')) {
                const name = trimmed.split('=')[0].trim();
                const dbName = name.slice(4); // remove 'idb_' prefix
                if (dbName) result.push(dbName);
            }
        }
        return result;
    }
};

// ============================================================
// API Configuration
// ============================================================
class ApiConfig {
    constructor() {
        this.host = localStorage.getItem('apiHost') || window.location.hostname;
        this.yandexClientId = localStorage.getItem('yandexClientId') || '';
    }

    getBaseUrl(db) {
        return 'https://' + this.host + '/' + db;
    }

    hasYandexAuth() {
        return !!(this.yandexClientId && this.yandexClientId.length > 0);
    }
}

// ============================================================
// Token validation
// ============================================================
async function validateToken(host, dbName) {
    // GET https://{host}/{db}/xsrf?JSON
    // Returns { _xsrf, token, user, ... } on success; on failure logs and deletes cookie
    const url = 'https://' + host + '/' + dbName + '/xsrf?JSON';
    try {
        const response = await fetch(url, { method: 'GET', credentials: 'include' });
        if (!response.ok) {
            console.log('[auth] xsrf check failed for ' + dbName + ': HTTP ' + response.status);
            CookieUtil.delete('idb_' + dbName);
            return null;
        }
        const data = await response.json();
        if (!data || !data._xsrf) {
            console.log('[auth] xsrf check: no valid token for ' + dbName, data);
            CookieUtil.delete('idb_' + dbName);
            return null;
        }
        return data;
    } catch (err) {
        console.log('[auth] xsrf check error for ' + dbName + ':', err);
        CookieUtil.delete('idb_' + dbName);
        return null;
    }
}

// ============================================================
// Yandex OAuth
// ============================================================
class YandexAuthManager {
    constructor(apiConfig) {
        this.apiConfig = apiConfig;
        // Yandex redirects to auth.asp on the current host (ideav.ru)
        this.redirectUri = 'https://ideav.ru/auth.asp';
    }

    isEnabled() {
        return this.apiConfig.hasYandexAuth();
    }

    initiateLogin() {
        if (!this.isEnabled()) {
            alert('Yandex OAuth не настроен. Укажите Client ID в настройках.');
            return;
        }
        const params = new URLSearchParams({
            response_type: 'token',
            client_id: this.apiConfig.yandexClientId,
            redirect_uri: this.redirectUri
        });
        window.location.href = 'https://oauth.yandex.ru/authorize?' + params.toString();
    }
}

// ============================================================
// Authentication & UI controller
// ============================================================
class AuthManager {
    constructor(apiConfig, i18n) {
        this.apiConfig = apiConfig;
        this.i18n = i18n;
        this.validDbs = []; // list of db names with valid tokens, ordered (idb_my first)
        this.selectedDb = null;
    }

    async init() {
        const dbNames = CookieUtil.getAllIdb();
        if (dbNames.length === 0) {
            this.showLoginButton();
            return;
        }

        // Sort: idb_my first, then others
        dbNames.sort((a, b) => {
            if (a === 'my') return -1;
            if (b === 'my') return 1;
            return a.localeCompare(b);
        });

        // Validate token for each db
        const host = this.apiConfig.host;
        const validationResults = await Promise.all(
            dbNames.map(async (db) => {
                const data = await validateToken(host, db);
                return { db, valid: !!data };
            })
        );

        this.validDbs = validationResults.filter(r => r.valid).map(r => r.db);

        if (this.validDbs.length === 0) {
            this.showLoginButton();
        } else {
            this.selectedDb = this.validDbs[0];
            this.showDbButton();
        }
    }

    getDbLabel(dbName) {
        if (dbName === 'my') return this.i18n.t('nav.cabinet');
        return dbName;
    }

    showLoginButton() {
        const loginBtn = document.getElementById('login-btn');
        if (loginBtn) {
            loginBtn.textContent = this.i18n.t('nav.login');
            loginBtn.style.display = '';
        }
        const dbWrapper = document.getElementById('db-btn-wrapper');
        if (dbWrapper) dbWrapper.style.display = 'none';
    }

    showDbButton() {
        const loginBtn = document.getElementById('login-btn');
        if (loginBtn) loginBtn.style.display = 'none';

        const dbWrapper = document.getElementById('db-btn-wrapper');
        const dbBtn = document.getElementById('db-btn');
        const dropdownToggle = document.getElementById('db-dropdown-toggle');
        const dropdown = document.getElementById('db-dropdown');

        if (!dbWrapper || !dbBtn) return;

        dbBtn.textContent = this.getDbLabel(this.selectedDb);
        dbWrapper.style.display = '';

        if (this.validDbs.length > 1) {
            dropdownToggle.style.display = '';
            this.renderDropdown(dropdown);
        } else {
            dropdownToggle.style.display = 'none';
            dropdown.style.display = 'none';
        }
    }

    renderDropdown(dropdown) {
        dropdown.innerHTML = '';
        this.validDbs.forEach(db => {
            const item = document.createElement('button');
            item.className = 'db-dropdown-item';
            item.textContent = this.getDbLabel(db);
            if (db === this.selectedDb) item.classList.add('db-dropdown-item-active');
            item.addEventListener('click', async (e) => {
                e.stopPropagation();
                // Re-validate token for selected db before switching
                const host = this.apiConfig.host;
                const data = await validateToken(host, db);
                if (!data) {
                    // Invalid - remove from list and re-render
                    this.validDbs = this.validDbs.filter(d => d !== db);
                    if (this.validDbs.length === 0) {
                        this.showLoginButton();
                    } else {
                        this.selectedDb = this.validDbs[0];
                        this.showDbButton();
                    }
                } else {
                    this.selectedDb = db;
                    this.showDbButton();
                }
                dropdown.style.display = 'none';
            });
            dropdown.appendChild(item);
        });
    }

    navigateToDb() {
        if (this.selectedDb) {
            window.location.href = 'https://' + this.apiConfig.host + '/' + this.selectedDb;
        }
    }

    async login(email, password) {
        const host = this.apiConfig.host;
        // Try each known db or the default 'my'
        // We use the /auth?JSON endpoint as implemented before
        const db = 'my';
        const url = 'https://' + host + '/' + db + '/auth?JSON';
        try {
            const formData = new URLSearchParams();
            formData.append('db', db);
            formData.append('login', email);
            formData.append('pwd', password);

            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString(),
                credentials: 'include'
            });

            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            const data = await response.json();
            if (data.msg && data.msg !== '') {
                return { success: false, message: data.msg };
            }
            return { success: true, message: this.i18n.t('msg.loginSuccess') };
        } catch (err) {
            console.error('[auth] login error:', err);
            return { success: false, message: this.i18n.t('msg.loginError') + err.message };
        }
    }

    async register(email, password) {
        const host = this.apiConfig.host;
        const db = 'my';
        // First get xsrf token
        const xsrfData = await validateToken(host, db);
        const xsrfToken = xsrfData ? (xsrfData._xsrf || '') : '';

        const url = 'https://' + host + '/' + db + '/_m_new/18?up=1&next_act=inform';
        try {
            const formData = new URLSearchParams();
            formData.append('_xsrf', xsrfToken);
            formData.append('t18', email);
            formData.append('t20', password);

            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString(),
                credentials: 'include'
            });

            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            const data = await response.json();
            if (data.error) {
                return { success: false, message: data.error };
            }
            return { success: true, message: data.msg || this.i18n.t('msg.registerSuccess') };
        } catch (err) {
            console.error('[auth] register error:', err);
            return { success: false, message: this.i18n.t('msg.registerError') + err.message };
        }
    }
}

// ============================================================
// App initialization
// ============================================================
class App {
    constructor() {
        this.i18n = new I18nManager();
        this.theme = new ThemeManager(this.i18n);
        this.apiConfig = new ApiConfig();
        this.auth = new AuthManager(this.apiConfig, this.i18n);
        this.yandexAuth = new YandexAuthManager(this.apiConfig);
        window._app = this;
    }

    navigateToDb() {
        this.auth.navigateToDb();
    }

    async init() {
        // Apply i18n
        this.i18n.applyAll();

        // Theme toggle
        const themeToggle = document.getElementById('theme-toggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', () => {
                this.theme.toggleTheme();
                this.i18n.applyAll(); // refresh i18n keys after theme label update
            });
        }

        // Lang toggle
        const langToggle = document.getElementById('lang-toggle');
        if (langToggle) {
            langToggle.addEventListener('click', () => {
                this.i18n.toggleLang();
                // Update login button text if visible
                const loginBtn = document.getElementById('login-btn');
                if (loginBtn && loginBtn.style.display !== 'none') {
                    loginBtn.textContent = this.i18n.t('nav.login');
                }
                // Re-render db button labels
                if (this.auth.selectedDb) {
                    this.auth.showDbButton();
                }
            });
        }

        // Show/hide Yandex OAuth buttons
        if (this.yandexAuth.isEnabled()) {
            const yandexLoginBtn = document.getElementById('yandex-login-btn');
            const yandexRegisterBtn = document.getElementById('yandex-register-btn');
            const yandexDivider = document.getElementById('yandex-divider');
            const yandexRegDivider = document.getElementById('yandex-reg-divider');
            if (yandexLoginBtn) { yandexLoginBtn.style.display = ''; }
            if (yandexRegisterBtn) { yandexRegisterBtn.style.display = ''; }
            if (yandexDivider) { yandexDivider.style.display = ''; }
            if (yandexRegDivider) { yandexRegDivider.style.display = ''; }
        }

        // Yandex button handlers
        const yandexLoginBtn = document.getElementById('yandex-login-btn');
        if (yandexLoginBtn) {
            yandexLoginBtn.addEventListener('click', () => this.yandexAuth.initiateLogin());
        }
        const yandexRegisterBtn = document.getElementById('yandex-register-btn');
        if (yandexRegisterBtn) {
            yandexRegisterBtn.addEventListener('click', () => this.yandexAuth.initiateLogin());
        }

        // Login button: show auth panel
        const loginBtn = document.getElementById('login-btn');
        if (loginBtn) {
            loginBtn.addEventListener('click', () => this.showAuthPanel());
        }

        // Close auth panel
        const closeAuth = document.getElementById('close-auth');
        if (closeAuth) {
            closeAuth.addEventListener('click', (e) => {
                e.preventDefault();
                this.hideAuthPanel();
            });
        }

        // Tab switching
        const tabLogin = document.getElementById('tab-login');
        const tabRegister = document.getElementById('tab-register');
        if (tabLogin) {
            tabLogin.addEventListener('click', () => this.switchTab('login'));
        }
        if (tabRegister) {
            tabRegister.addEventListener('click', () => this.switchTab('register'));
        }

        // Login form
        const loginForm = document.getElementById('login-form');
        if (loginForm) {
            loginForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const email = document.getElementById('login-email').value;
                const password = document.getElementById('login-password').value;
                const result = await this.auth.login(email, password);
                if (result.success) {
                    this.hideAuthPanel();
                    await this.auth.init();
                } else {
                    alert(result.message);
                }
            });
        }

        // Register form
        const registerForm = document.getElementById('register-form');
        if (registerForm) {
            registerForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const email = document.getElementById('reg-email').value;
                const password = document.getElementById('reg-password').value;
                const confirmPassword = document.getElementById('reg-confirm-password').value;

                if (password !== confirmPassword) {
                    alert(this.i18n.t('msg.passwordMismatch'));
                    return;
                }
                if (password.length < 6) {
                    alert(this.i18n.t('msg.passwordShort'));
                    return;
                }

                const result = await this.auth.register(email, password);
                alert(result.message);
                if (result.success) {
                    this.hideAuthPanel();
                }
            });
        }

        // Dropdown toggle
        const dropdownToggle = document.getElementById('db-dropdown-toggle');
        const dropdown = document.getElementById('db-dropdown');
        if (dropdownToggle && dropdown) {
            dropdownToggle.addEventListener('click', (e) => {
                e.stopPropagation();
                const isVisible = dropdown.style.display !== 'none';
                dropdown.style.display = isVisible ? 'none' : '';
            });
            // Close dropdown when clicking outside
            document.addEventListener('click', () => {
                if (dropdown.style.display !== 'none') {
                    dropdown.style.display = 'none';
                }
            });
        }

        // Check auth state from cookies
        await this.auth.init();
    }

    showAuthPanel() {
        const authPanel = document.getElementById('auth-panel');
        if (authPanel) authPanel.style.display = '';
        const welcomeSection = document.getElementById('welcome-section');
        if (welcomeSection) welcomeSection.style.display = 'none';
        this.switchTab('login');
    }

    hideAuthPanel() {
        const authPanel = document.getElementById('auth-panel');
        if (authPanel) authPanel.style.display = 'none';
        const welcomeSection = document.getElementById('welcome-section');
        if (welcomeSection) welcomeSection.style.display = '';
    }

    switchTab(tab) {
        const loginSection = document.getElementById('login-section');
        const registerSection = document.getElementById('register-section');
        const tabLogin = document.getElementById('tab-login');
        const tabRegister = document.getElementById('tab-register');

        if (tab === 'login') {
            if (loginSection) loginSection.style.display = '';
            if (registerSection) registerSection.style.display = 'none';
            if (tabLogin) tabLogin.classList.add('auth-tab-active');
            if (tabRegister) tabRegister.classList.remove('auth-tab-active');
        } else {
            if (loginSection) loginSection.style.display = 'none';
            if (registerSection) registerSection.style.display = '';
            if (tabLogin) tabLogin.classList.remove('auth-tab-active');
            if (tabRegister) tabRegister.classList.add('auth-tab-active');
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const app = new App();
    app.init();
});
