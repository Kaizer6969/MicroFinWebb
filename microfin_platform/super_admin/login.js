// super_admin/login.js
document.addEventListener('DOMContentLoaded', () => {
    const root = document.documentElement;
    const authThemeToggle = document.getElementById('auth-theme-toggle');
    const themeKey = 'microfin_super_admin_theme';
    const loginForm = document.getElementById('login-form');
    const loader = document.getElementById('loader');

    const normalizeTheme = (value) => value === 'dark' ? 'dark' : 'light';

    const updateAuthThemeToggle = (theme) => {
        if (!authThemeToggle) {
            return;
        }

        const nextMode = theme === 'dark' ? 'light' : 'dark';
        const icon = authThemeToggle.querySelector('.auth-theme-icon');
        authThemeToggle.setAttribute('aria-label', `Switch to ${nextMode} mode`);
        authThemeToggle.setAttribute('title', `Switch to ${nextMode} mode`);
        if (icon) {
            icon.textContent = nextMode === 'dark' ? 'dark_mode' : 'light_mode';
            return;
        }

        const label = nextMode === 'dark' ? 'Dark mode' : 'Light mode';
        authThemeToggle.textContent = label;
    };

    const applyAuthTheme = (theme, persist = true) => {
        const resolvedTheme = normalizeTheme(theme);
        root.setAttribute('data-theme', resolvedTheme);
        updateAuthThemeToggle(resolvedTheme);

        if (persist) {
            try {
                localStorage.setItem(themeKey, resolvedTheme);
            } catch (error) {
                console.warn('Unable to store auth theme preference.', error);
            }
        }
    };

    try {
        const storedTheme = localStorage.getItem(themeKey);
        if (storedTheme === 'light' || storedTheme === 'dark') {
            applyAuthTheme(storedTheme, false);
        } else {
            applyAuthTheme(root.getAttribute('data-theme') || 'light', false);
        }
    } catch (error) {
        applyAuthTheme(root.getAttribute('data-theme') || 'light', false);
    }

    if (authThemeToggle) {
        authThemeToggle.addEventListener('click', () => {
            const currentTheme = normalizeTheme(root.getAttribute('data-theme'));
            const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';
            applyAuthTheme(nextTheme);
        });
    }

    if (loginForm) {
        loginForm.addEventListener('submit', () => {
            loader.classList.add('active');
        });
    }
});
