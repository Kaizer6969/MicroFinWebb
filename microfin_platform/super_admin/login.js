// super_admin/login.js
document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('login-form');
    const loader = document.getElementById('loader');
    const themeToggle = document.getElementById('auth-theme-toggle');
    const htmlElement = document.documentElement;
    const themeStorageKey = 'microfin-platform-auth-theme';

    function applyTheme(theme) {
        htmlElement.setAttribute('data-theme', theme);

        if (!themeToggle) {
            return;
        }

        const icon = themeToggle.querySelector('.auth-theme-toggle-icon');
        const label = themeToggle.querySelector('.auth-theme-toggle-label');
        const isDark = theme === 'dark';

        if (icon) {
            icon.textContent = isDark ? 'light_mode' : 'dark_mode';
        }
        if (label) {
            label.textContent = isDark ? 'Light Mode' : 'Dark Mode';
        }

        themeToggle.setAttribute('aria-pressed', isDark ? 'true' : 'false');
        themeToggle.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
    }

    function getInitialTheme() {
        try {
            const storedTheme = localStorage.getItem(themeStorageKey);
            if (storedTheme === 'light' || storedTheme === 'dark') {
                return storedTheme;
            }
        } catch (error) {
            // Ignore storage access issues and fall back to the current DOM theme.
        }

        return htmlElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    }

    applyTheme(getInitialTheme());

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const nextTheme = htmlElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            applyTheme(nextTheme);

            try {
                localStorage.setItem(themeStorageKey, nextTheme);
            } catch (error) {
                // Ignore storage access issues and keep the in-memory UI state.
            }
        });
    }

    if (loginForm) {
        loginForm.addEventListener('submit', () => {
            loader.classList.add('active');
        });
    }
});
