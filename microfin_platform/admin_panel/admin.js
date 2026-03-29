document.addEventListener('DOMContentLoaded', () => {
    if ('scrollRestoration' in window.history) {
        window.history.scrollRestoration = 'manual';
    }

    const htmlElement = document.documentElement;

    const companyNameInput = document.getElementById('company-name');
    const companyNameDisplay = document.querySelector('.company-name-display');
    const primaryColorInput = document.getElementById('picker-primary');

    const settingsForm = document.getElementById('settings-form');
    const saveBtn = document.getElementById('save-settings');

    const toggleBooking = document.getElementById('toggle-booking_system');
    const toggleRegistration = document.getElementById('toggle-user_registration');
    const toggleMaintenance = document.getElementById('toggle-maintenance_mode');
    const toggleEmails = document.getElementById('toggle-email_notifications');
    const toggleWebsite = document.getElementById('toggle-public_website_enabled');

    const navItems = Array.from(document.querySelectorAll('.sidebar-nav .nav-item[data-target]'));
    const viewSections = Array.from(document.querySelectorAll('.view-section'));
    const pageTitle = document.getElementById('page-title');
    const tabBtns = Array.from(document.querySelectorAll('.tab-btn'));
    const previewStage = document.getElementById('preview-stage');
    const previewButtons = Array.from(document.querySelectorAll('.preview-btn'));
    const previewScreens = Array.from(document.querySelectorAll('.preview-screen'));
    const logoInput = document.getElementById('logo_file');
    const extractPaletteBtn = document.getElementById('extract-palette-btn');
    const syncBtn = document.getElementById('sync-btn');
    const existingLogoPathInput = document.getElementById('existing-logo-path');
    const setupAlert = document.querySelector('.dashboard-setup-alert');
    const setupAlertToggle = document.querySelector('[data-setup-alert-toggle]');
    const loanProductsForm = document.getElementById('loan-products-form');
    const loanPreviewRoot = document.querySelector('[data-loan-preview]');
    const loanPreviewAmountInput = document.getElementById('loan-preview-amount');
    const loanPreviewTermInput = document.getElementById('loan-preview-term');
    const viewsContainer = document.querySelector('.views-container');
    const receiptPeriodSelect = document.getElementById('receipt-period');
    const receiptPeriodFields = Array.from(document.querySelectorAll('[data-receipt-period-field]'));

    const sectionDefaults = {
        staff: 'staff-list',
        billing: 'billing-overview',
    };

    const sectionRouteMap = {
        dashboard: { sectionId: 'dashboard' },
        staff: { sectionId: 'staff', subTabId: 'staff-list' },
        'staff-list': { sectionId: 'staff', subTabId: 'staff-list' },
        'roles-list': { sectionId: 'staff', subTabId: 'roles-list' },
        loan_products: { sectionId: 'loan_products' },
        credit_settings: { sectionId: 'credit_settings' },
        website: { sectionId: 'website' },
        features: { sectionId: 'features' },
        billing: { sectionId: 'billing', subTabId: 'billing-overview' },
        statements: { sectionId: 'statements' },
        settings: { sectionId: 'settings' },
        personal: { sectionId: 'personal' },
    };

    const billingSubtabMap = {
        payment: 'billing-payment',
        history: 'billing-history',
    };

    function setPageTitleFromNav(item, fallbackTargetId) {
        if (!pageTitle) return;

        if (item) {
            const navTitle = item.getAttribute('data-title');
            const label = item.querySelector('span:nth-child(2)');
            pageTitle.textContent = navTitle || (label ? label.textContent : pageTitle.textContent);
            return;
        }

        const fallbackNav = document.querySelector(`.sidebar-nav .nav-item[data-target="${fallbackTargetId}"]`);
        if (fallbackNav) {
            const navTitle = fallbackNav.getAttribute('data-title');
            const label = fallbackNav.querySelector('span:nth-child(2)');
            pageTitle.textContent = navTitle || (label ? label.textContent : pageTitle.textContent);
        }
    }

    function activateTabInSection(sectionEl, tabId) {
        if (!sectionEl || !tabId) {
            return;
        }

        const scopedTabButtons = Array.from(sectionEl.querySelectorAll('.tab-btn'));
        const scopedTabContents = Array.from(sectionEl.querySelectorAll('.tab-content'));
        if (scopedTabButtons.length === 0 || scopedTabContents.length === 0) {
            return;
        }

        scopedTabButtons.forEach((btn) => {
            btn.classList.toggle('active', btn.getAttribute('data-tab') === tabId);
        });

        scopedTabContents.forEach((content) => {
            content.classList.toggle('active', content.id === tabId);
        });
    }

    function findPreferredNavItem(targetId, subTabId = '') {
        if (subTabId !== '') {
            const exactNav = document.querySelector(`.sidebar-nav .nav-item[data-target="${targetId}"][data-subtab="${subTabId}"]`);
            if (exactNav) {
                return exactNav;
            }
        }

        return document.querySelector(`.sidebar-nav .nav-item[data-target="${targetId}"]:not([data-subtab])`)
            || document.querySelector(`.sidebar-nav .nav-item[data-target="${targetId}"]`);
    }

    function resetWorkspaceScroll() {
        if (viewsContainer) {
            viewsContainer.scrollTop = 0;
        }
        if (document.documentElement) {
            document.documentElement.scrollTop = 0;
        }
        if (document.body) {
            document.body.scrollTop = 0;
        }
        window.scrollTo({ top: 0, left: 0, behavior: 'auto' });
    }

    function isSameAdminPageLink(href) {
        if (!href) {
            return true;
        }
        if (href.startsWith('#')) {
            return true;
        }

        try {
            const targetUrl = new URL(href, window.location.href);
            const currentUrl = new URL(window.location.href);
            return targetUrl.origin === currentUrl.origin
                && targetUrl.pathname === currentUrl.pathname;
        } catch (error) {
            return false;
        }
    }

    function replaceUrlForSection(targetId, subTabId = '', href = '') {
        if (!window.history || typeof window.history.replaceState !== 'function') {
            return;
        }

        if (isSameAdminPageLink(href) && href && !href.startsWith('#')) {
            const targetUrl = new URL(href, window.location.href);
            targetUrl.hash = targetId ? `#${targetId}` : '';
            window.history.replaceState({}, '', targetUrl);
            return;
        }

        const currentUrl = new URL(window.location.href);
        if (targetId === 'dashboard') {
            currentUrl.searchParams.delete('tab');
            currentUrl.searchParams.delete('sub');
        }
        if (subTabId && targetId === 'staff') {
            currentUrl.searchParams.set('tab', subTabId);
        } else if (targetId && targetId !== 'dashboard') {
            currentUrl.searchParams.set('tab', targetId);
            currentUrl.searchParams.delete('sub');
        }
        currentUrl.hash = targetId ? `#${targetId}` : '';
        window.history.replaceState({}, '', currentUrl);
    }

    function activateSection(targetId, options = {}) {
        if (!targetId) {
            return;
        }

        const targetEl = document.getElementById(targetId);
        if (!targetEl) {
            return;
        }

        const requestedSubTabId = options.subTabId || '';
        const effectiveSubTabId = requestedSubTabId || sectionDefaults[targetId] || '';
        const navItem = options.navItem || findPreferredNavItem(targetId, requestedSubTabId);

        navItems.forEach((nav) => nav.classList.toggle('active', nav === navItem));
        viewSections.forEach((section) => section.classList.toggle('active', section.id === targetId));

        if (effectiveSubTabId !== '') {
            activateTabInSection(targetEl, effectiveSubTabId);
        }

        setPageTitleFromNav(navItem, targetId);
        resetWorkspaceScroll();

        replaceUrlForSection(targetId, requestedSubTabId || effectiveSubTabId);
    }

    navItems.forEach(item => {
        item.addEventListener('click', (e) => {
            const targetId = item.getAttribute('data-target');
            if(!targetId) return;
            const subTabId = item.getAttribute('data-subtab');
            const href = item.getAttribute('href') || '';

            if (!isSameAdminPageLink(href)) {
                return;
            }
            
            e.preventDefault();
            activateSection(targetId, { navItem: item, subTabId: subTabId || '' });
            replaceUrlForSection(targetId, subTabId || '', href);
        });
    });

    // Auto-navigate if hash or query params target a specific section/sub-tab
    const urlParams = new URLSearchParams(window.location.search);
    const hashTarget = window.location.hash ? window.location.hash.substring(1) : '';
    const sectionParam = urlParams.get('section') || '';
    const tabParam = urlParams.get('tab') || '';
    const subParam = urlParams.get('sub') || '';

    let initialRoute = null;
    if (hashTarget && document.getElementById(hashTarget)) {
        initialRoute = {
            sectionId: hashTarget,
            subTabId: hashTarget === 'billing' && billingSubtabMap[subParam] ? billingSubtabMap[subParam] : '',
        };
    } else if (sectionParam && document.getElementById(sectionParam)) {
        initialRoute = {
            sectionId: sectionParam,
            subTabId: sectionParam === 'billing' && billingSubtabMap[subParam] ? billingSubtabMap[subParam] : '',
        };
    } else if (tabParam && sectionRouteMap[tabParam]) {
        initialRoute = {
            sectionId: sectionRouteMap[tabParam].sectionId,
            subTabId: tabParam === 'billing' && billingSubtabMap[subParam]
                ? billingSubtabMap[subParam]
                : (sectionRouteMap[tabParam].subTabId || ''),
        };
    } else if (subParam && billingSubtabMap[subParam]) {
        initialRoute = { sectionId: 'billing', subTabId: billingSubtabMap[subParam] };
    }

    if (initialRoute && initialRoute.sectionId) {
        activateSection(initialRoute.sectionId, { subTabId: initialRoute.subTabId || '' });
    }

    window.addEventListener('pageshow', () => {
        resetWorkspaceScroll();
    });

    function syncReceiptPeriodFields() {
        if (!receiptPeriodSelect || receiptPeriodFields.length === 0) {
            return;
        }

        const activePeriod = receiptPeriodSelect.value || 'all';
        receiptPeriodFields.forEach((field) => {
            const targetPeriod = field.getAttribute('data-receipt-period-field');
            const shouldShow = targetPeriod === activePeriod;
            field.classList.toggle('is-hidden', !shouldShow);

            const inputs = field.querySelectorAll('input, select');
            inputs.forEach((input) => {
                input.disabled = !shouldShow;
            });
        });
    }

    if (receiptPeriodSelect) {
        syncReceiptPeriodFields();
        receiptPeriodSelect.addEventListener('change', syncReceiptPeriodFields);
    }

    if (setupAlert && setupAlertToggle) {
        const storageKey = 'tenantAdminSetupAlertMinimized';
        const setupAlertIcon = setupAlertToggle.querySelector('.material-symbols-rounded');

        const applySetupAlertState = (isMinimized) => {
            setupAlert.classList.toggle('is-minimized', isMinimized);
            setupAlertToggle.setAttribute('aria-expanded', isMinimized ? 'false' : 'true');
            setupAlertToggle.setAttribute('title', isMinimized ? 'Expand setup alert' : 'Minimize setup alert');
            if (setupAlertIcon) {
                setupAlertIcon.textContent = isMinimized ? 'open_in_full' : 'remove';
            }
        };

        let isMinimized = false;
        try {
            isMinimized = window.localStorage.getItem(storageKey) === '1';
        } catch (error) {
            isMinimized = false;
        }
        applySetupAlertState(isMinimized);

        setupAlertToggle.addEventListener('click', () => {
            const nextState = !setupAlert.classList.contains('is-minimized');
            applySetupAlertState(nextState);
            try {
                window.localStorage.setItem(storageKey, nextState ? '1' : '0');
            } catch (error) {
                // Ignore storage failures and keep the UI responsive.
            }
        });
    }

    tabBtns.forEach(btn => {
        btn.addEventListener('click', (event) => {
            const href = btn.getAttribute('href') || '';
            if (!isSameAdminPageLink(href)) {
                return;
            }
            event.preventDefault();
            const tabId = btn.getAttribute('data-tab');
            const sectionEl = btn.closest('.view-section');
            activateTabInSection(sectionEl, tabId);
            resetWorkspaceScroll();
            if (sectionEl && sectionEl.id) {
                replaceUrlForSection(sectionEl.id, tabId, href);
            }
        });
    });

    // Role View Swapping Logic
    const roleListItems = document.querySelectorAll('.role-list-item');
    const rolePanels = document.querySelectorAll('.role-permissions-panel');

    roleListItems.forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            
            const roleId = item.getAttribute('data-role-id');
            if (!roleId) return;

            // Update active styling in sidebar
            roleListItems.forEach(i => i.classList.remove('active'));
            item.classList.add('active');

            // Hide all panels, show the selected one
            rolePanels.forEach(panel => {
                panel.style.display = 'none';
            });
            const targetPanel = document.getElementById(`role-panel-${roleId}`);
            if (targetPanel) {
                targetPanel.style.display = 'block';
            }

            // Update URL query string without reloading page
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('role_id', roleId);
            window.history.pushState({}, '', currentUrl);
        });
    });

    const themeToggleBtn = document.getElementById('theme-toggle');

    function applyTheme(theme) {
        htmlElement.setAttribute('data-theme', theme);
        if (themeToggleBtn) {
            const icon = themeToggleBtn.querySelector('span');
            if (icon) {
                icon.textContent = theme === 'light' ? 'dark_mode' : 'light_mode';
            }
        }
    }

    async function persistTheme(theme) {
        try {
            await fetch('../backend/api_theme_preference.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ theme: theme, role: 'tenant' })
            });
        } catch (error) {
            // Ignore persistence errors to keep the toggle responsive.
        }
    }

    if (themeToggleBtn) {
        applyTheme(htmlElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light');
        themeToggleBtn.addEventListener('click', () => {
            const currentTheme = htmlElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            applyTheme(newTheme);
            persistTheme(newTheme);
        });
    }

    function hexToRgb(hex) {
        return {
            r: parseInt(hex.slice(1, 3), 16),
            g: parseInt(hex.slice(3, 5), 16),
            b: parseInt(hex.slice(5, 7), 16)
        };
    }

    function rgbToHex(r, g, b) {
        return '#' + [r, g, b]
            .map((value) => Math.max(0, Math.min(255, Math.round(value))).toString(16).padStart(2, '0'))
            .join('');
    }

    function luminance(hex) {
        const { r, g, b } = hexToRgb(hex);
        const [rs, gs, bs] = [r, g, b].map((channel) => {
            const normalized = channel / 255;
            return normalized <= 0.03928 ? normalized / 12.92 : Math.pow((normalized + 0.055) / 1.055, 2.4);
        });
        return 0.2126 * rs + 0.7152 * gs + 0.0722 * bs;
    }

    function contrastRatio(a, b) {
        const l1 = luminance(a);
        const l2 = luminance(b);
        return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
    }

    function adjustBrightness(hex, pct) {
        const { r, g, b } = hexToRgb(hex);
        const factor = pct / 100;
        if (factor > 0) {
            return rgbToHex(r + (255 - r) * factor, g + (255 - g) * factor, b + (255 - b) * factor);
        }
        const darken = 1 + factor;
        return rgbToHex(r * darken, g * darken, b * darken);
    }

    function setGlobalPrimaryColor(hex) {
        if (!hex || !/^#[0-9a-fA-F]{6}$/.test(hex)) {
            return;
        }

        htmlElement.style.setProperty('--primary-color', hex);
        const { r, g, b } = hexToRgb(hex);
        htmlElement.style.setProperty('--primary-rgb', `${r}, ${g}, ${b}`);
    }

    function wireColorPair(pickerId, inputId, onChange) {
        const picker = document.getElementById(pickerId);
        const input = document.getElementById(inputId);
        if (!picker || !input) {
            return;
        }

        picker.addEventListener('input', () => {
            input.value = picker.value;
            onChange();
        });

        input.addEventListener('input', () => {
            if (/^#[0-9a-fA-F]{6}$/.test(input.value)) {
                picker.value = input.value;
            }
            onChange();
        });
    }

    function setColorField(pickerId, inputId, value) {
        const picker = document.getElementById(pickerId);
        const input = document.getElementById(inputId);
        if (picker) {
            picker.value = value;
        }
        if (input) {
            input.value = value;
        }
    }

    let autoSyncEnabled = false;

    function syncBrandingContrast() {
        const primary = document.getElementById('primary_color')?.value || '#4f46e5';
        const bgBody = document.getElementById('bg_body')?.value || '#f8fafc';
        const bgCard = document.getElementById('bg_card')?.value || '#ffffff';
        const isDarkCard = luminance(bgCard) < 0.18;

        const secondary = isDarkCard ? adjustBrightness(primary, 30) : adjustBrightness(primary, -25);
        const lightText = '#f1f5f9';
        const darkText = '#0f172a';
        const lightMuted = '#94a3b8';
        const darkMuted = '#64748b';
        const textMain = Math.min(contrastRatio(lightText, bgCard), contrastRatio(lightText, bgBody))
            > Math.min(contrastRatio(darkText, bgCard), contrastRatio(darkText, bgBody))
            ? lightText
            : darkText;
        const textMuted = Math.min(contrastRatio(lightMuted, bgCard), contrastRatio(lightMuted, bgBody))
            > Math.min(contrastRatio(darkMuted, bgCard), contrastRatio(darkMuted, bgBody))
            ? lightMuted
            : darkMuted;

        const secondaryInput = document.getElementById('secondary_color');
        if (secondaryInput) {
            secondaryInput.value = secondary;
        }
        setColorField('picker-text-main', 'text_main', textMain);
        setColorField('picker-text-muted', 'text_muted', textMuted);
        updateBrandingPreview();
    }

    function updateBrandingPreview() {
        const primary = document.getElementById('primary_color')?.value || '#4f46e5';
        const secondary = document.getElementById('secondary_color')?.value || '#991b1b';
        const textMain = document.getElementById('text_main')?.value || '#0f172a';
        const textMuted = document.getElementById('text_muted')?.value || '#64748b';
        const bgBody = document.getElementById('bg_body')?.value || '#f8fafc';
        const bgCard = document.getElementById('bg_card')?.value || '#ffffff';
        const borderColor = document.getElementById('border_color')?.value || '#e2e8f0';
        const borderWidth = document.getElementById('card_border_width')?.value || '1';
        const shadowValue = document.getElementById('card_shadow')?.value || 'sm';

        setGlobalPrimaryColor(primary);

        if (previewStage) {
            const shadowMap = {
                none: 'none',
                sm: '0 1px 3px rgba(0,0,0,0.08)',
                md: '0 4px 12px rgba(0,0,0,0.1)',
                lg: '0 8px 24px rgba(0,0,0,0.14)'
            };
            const autoBorder = luminance(bgCard) < 0.18 ? adjustBrightness(bgCard, 18) : adjustBrightness(bgCard, -8);
            const subtleBorder = luminance(bgCard) < 0.18 ? adjustBrightness(bgCard, 10) : adjustBrightness(bgCard, -4);
            const { r, g, b } = hexToRgb(primary);

            previewStage.style.setProperty('--theme-primary', primary);
            previewStage.style.setProperty('--theme-secondary', secondary);
            previewStage.style.setProperty('--theme-text-main', textMain);
            previewStage.style.setProperty('--theme-text-muted', textMuted);
            previewStage.style.setProperty('--theme-bg-body', bgBody);
            previewStage.style.setProperty('--theme-bg-card', bgCard);
            previewStage.style.setProperty('--theme-border-color', borderColor);
            previewStage.style.setProperty('--theme-card-border-width', `${borderWidth}px`);
            previewStage.style.setProperty('--theme-card-shadow', shadowMap[shadowValue] || shadowMap.sm);
            previewStage.style.setProperty('--theme-border', autoBorder);
            previewStage.style.setProperty('--theme-border-subtle', subtleBorder);
            previewStage.style.setProperty('--primary-r', r);
            previewStage.style.setProperty('--primary-g', g);
            previewStage.style.setProperty('--primary-b', b);
            const currentFont = document.getElementById('font_family')?.value;
            if (currentFont) {
                previewStage.style.fontFamily = `'${currentFont}', sans-serif`;
            }
        }

        const borderWidthLabel = document.getElementById('border-width-label');
        if (borderWidthLabel) {
            borderWidthLabel.textContent = `${borderWidth}px`;
        }
    }

    function updateLogoPreview() {
        const logoImages = document.querySelectorAll('.preview-logo-image');
        const iconFallbacks = document.querySelectorAll('.admin-sidebar-logo > .material-symbols-rounded, .staff-sidebar-logo > .material-symbols-rounded');
        const existingLogoPath = existingLogoPathInput?.value?.trim() || '';

        const applyLogo = (source) => {
            logoImages.forEach((img) => {
                img.src = source;
                img.style.display = 'block';
            });
            iconFallbacks.forEach((icon) => {
                icon.style.display = 'none';
            });
        };

        if (logoInput?.files && logoInput.files[0]) {
            const reader = new FileReader();
            reader.onload = (event) => applyLogo(event.target?.result || '');
            reader.readAsDataURL(logoInput.files[0]);
            return;
        }

        if (existingLogoPath !== '') {
            applyLogo(existingLogoPath);
            return;
        }

        logoImages.forEach((img) => {
            img.removeAttribute('src');
            img.style.display = 'none';
        });
        iconFallbacks.forEach((icon) => {
            icon.style.display = 'inline';
        });
    }

    function updateCompanyNamePreview(value) {
        const safeValue = value || 'Company Admin';
        if (companyNameDisplay) {
            companyNameDisplay.textContent = safeValue;
        }
        document.querySelectorAll('.preview-company-name').forEach((el) => {
            el.textContent = safeValue;
        });
    }

    function formatLoanPreviewCurrency(value) {
        const safeValue = Number.isFinite(value) ? value : 0;
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(safeValue);
    }

    function normalizeLoanPreviewNumber(value, fallback = 0) {
        const parsed = parseFloat(value);
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    function normalizeLoanPreviewInteger(value, fallback = 0) {
        const parsed = parseInt(value, 10);
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    function loanPreviewClamp(value, min, max) {
        return Math.min(Math.max(value, min), max);
    }

    function updateLoanProductsPreview() {
        if (!loanProductsForm || !loanPreviewRoot) {
            return;
        }

        const getField = (name) => loanProductsForm.querySelector(`[name="${name}"]`);
        const setPreviewText = (key, value) => {
            loanPreviewRoot.querySelectorAll(`[data-loan-preview-bind="${key}"]`).forEach((element) => {
                element.textContent = value;
            });
        };

        const productName = getField('product_name')?.value.trim() || 'Personal Cash Loan';
        const productType = getField('product_type')?.value || 'Personal Loan';
        const description = getField('description')?.value.trim() || 'Borrowers will see a short description explaining what this loan is best for.';

        const minAmount = Math.max(0, normalizeLoanPreviewNumber(getField('min_amount')?.value, 0));
        const maxAmount = Math.max(minAmount, normalizeLoanPreviewNumber(getField('max_amount')?.value, minAmount));
        const minTerm = Math.max(1, normalizeLoanPreviewInteger(getField('min_term_months')?.value, 1));
        const maxTerm = Math.max(minTerm, normalizeLoanPreviewInteger(getField('max_term_months')?.value, minTerm));

        const interestRate = Math.max(0, normalizeLoanPreviewNumber(getField('interest_rate')?.value, 0));
        const interestType = getField('interest_type')?.value || 'Diminishing';
        const penaltyRate = Math.max(0, normalizeLoanPreviewNumber(getField('penalty_rate')?.value, 0));
        const penaltyType = (getField('penalty_type')?.value || 'Daily').toLowerCase();
        const gracePeriodDays = Math.max(0, normalizeLoanPreviewInteger(getField('grace_period_days')?.value, 0));

        const processingFeeRate = Math.max(0, normalizeLoanPreviewNumber(getField('processing_fee_percentage')?.value, 0));
        const insuranceFeeRate = Math.max(0, normalizeLoanPreviewNumber(getField('insurance_fee_percentage')?.value, 0));
        const serviceCharge = Math.max(0, normalizeLoanPreviewNumber(getField('service_charge')?.value, 0));
        const documentaryStamp = Math.max(0, normalizeLoanPreviewNumber(getField('documentary_stamp')?.value, 0));

        const amountSpan = Math.max(0, maxAmount - minAmount);
        const amountStep = amountSpan >= 1000000 ? 5000 : amountSpan >= 100000 ? 1000 : amountSpan >= 10000 ? 500 : 100;
        const defaultAmount = amountSpan > 0 ? minAmount + (amountSpan / 2) : minAmount;
        const defaultTerm = Math.max(minTerm, Math.round((minTerm + maxTerm) / 2));

        if (loanPreviewAmountInput) {
            loanPreviewAmountInput.min = String(minAmount);
            loanPreviewAmountInput.max = String(maxAmount);
            loanPreviewAmountInput.step = String(amountStep);
            loanPreviewAmountInput.disabled = maxAmount <= minAmount;
        }

        if (loanPreviewTermInput) {
            loanPreviewTermInput.min = String(minTerm);
            loanPreviewTermInput.max = String(maxTerm);
            loanPreviewTermInput.step = '1';
            loanPreviewTermInput.disabled = maxTerm <= minTerm;
        }

        const selectedAmount = loanPreviewAmountInput
            ? loanPreviewClamp(normalizeLoanPreviewNumber(loanPreviewAmountInput.value, defaultAmount), minAmount, maxAmount)
            : defaultAmount;
        const selectedTerm = loanPreviewTermInput
            ? loanPreviewClamp(normalizeLoanPreviewInteger(loanPreviewTermInput.value, defaultTerm), minTerm, maxTerm)
            : defaultTerm;

        if (loanPreviewAmountInput) {
            loanPreviewAmountInput.value = String(selectedAmount);
        }

        if (loanPreviewTermInput) {
            loanPreviewTermInput.value = String(selectedTerm);
        }

        let estimatedInstallment = selectedTerm > 0 ? selectedAmount / selectedTerm : selectedAmount;
        if (interestType === 'Diminishing') {
            const monthlyRate = (interestRate / 100) / 12;
            estimatedInstallment = monthlyRate > 0
                ? (selectedAmount * monthlyRate) / (1 - Math.pow(1 + monthlyRate, -selectedTerm))
                : (selectedAmount / selectedTerm);
        } else {
            const totalInterest = selectedAmount * (interestRate / 100) * (selectedTerm / 12);
            estimatedInstallment = (selectedAmount + totalInterest) / selectedTerm;
        }

        const processingFeeValue = selectedAmount * (processingFeeRate / 100);
        const insuranceFeeValue = selectedAmount * (insuranceFeeRate / 100);
        const totalUpfrontCharges = processingFeeValue + insuranceFeeValue + serviceCharge + documentaryStamp;
        const cashRelease = Math.max(0, selectedAmount - totalUpfrontCharges);
        const totalRepayment = estimatedInstallment * selectedTerm;

        setPreviewText('product-name', productName);
        setPreviewText('product-type', productType);
        setPreviewText('description', description);
        setPreviewText('interest-chip', `${interestRate.toFixed(2)}% ${interestType}`);
        setPreviewText('grace-chip', gracePeriodDays > 0 ? `${gracePeriodDays} day${gracePeriodDays === 1 ? '' : 's'} grace period` : 'No grace period');
        setPreviewText('max-amount', formatLoanPreviewCurrency(maxAmount));
        setPreviewText('term-range', `${minTerm}-${maxTerm} months`);
        setPreviewText('penalty', penaltyRate > 0 ? `${penaltyRate.toFixed(2)}% ${penaltyType}` : 'No late penalty');

        setPreviewText('selected-amount', formatLoanPreviewCurrency(selectedAmount));
        setPreviewText('selected-term', `${selectedTerm} month${selectedTerm === 1 ? '' : 's'}`);
        setPreviewText('min-amount', formatLoanPreviewCurrency(minAmount));
        setPreviewText('max-amount-range', formatLoanPreviewCurrency(maxAmount));
        setPreviewText('min-term', `${minTerm} mo`);
        setPreviewText('max-term', `${maxTerm} mo`);

        setPreviewText('estimated-installment', formatLoanPreviewCurrency(estimatedInstallment));
        setPreviewText('cash-release', formatLoanPreviewCurrency(cashRelease));
        setPreviewText('total-repayment', formatLoanPreviewCurrency(totalRepayment));
        setPreviewText('charges-total', formatLoanPreviewCurrency(totalUpfrontCharges));
        setPreviewText('processing-fee-value', formatLoanPreviewCurrency(processingFeeValue));
        setPreviewText('insurance-fee-value', formatLoanPreviewCurrency(insuranceFeeValue));
        setPreviewText('service-charge-value', formatLoanPreviewCurrency(serviceCharge));
        setPreviewText('doc-stamp-value', formatLoanPreviewCurrency(documentaryStamp));
    }

    function extractPaletteFromLogo() {
        if (!logoInput?.files || !logoInput.files[0] || logoInput.files[0].type === 'image/svg+xml') {
            return;
        }

        const reader = new FileReader();
        reader.onload = (event) => {
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                const size = 64;
                canvas.width = size;
                canvas.height = size;
                ctx.drawImage(img, 0, 0, size, size);
                const data = ctx.getImageData(0, 0, size, size).data;
                const pixels = [];

                for (let i = 0; i < data.length; i += 4) {
                    const [r, g, b, a] = [data[i], data[i + 1], data[i + 2], data[i + 3]];
                    const lum = (0.299 * r) + (0.587 * g) + (0.114 * b);
                    if (a < 128 || lum > 245 || lum < 10) {
                        continue;
                    }
                    pixels.push([r, g, b]);
                }

                if (pixels.length < 5) {
                    return;
                }

                let centroids = pixels.filter((_, index) => index % Math.max(1, Math.floor(pixels.length / 3)) === 0).slice(0, 3);
                for (let iteration = 0; iteration < 10; iteration++) {
                    const clusters = centroids.map(() => []);
                    pixels.forEach((pixel) => {
                        let nearest = 0;
                        let bestDistance = Infinity;
                        centroids.forEach((centroid, idx) => {
                            const distance = (pixel[0] - centroid[0]) ** 2 + (pixel[1] - centroid[1]) ** 2 + (pixel[2] - centroid[2]) ** 2;
                            if (distance < bestDistance) {
                                bestDistance = distance;
                                nearest = idx;
                            }
                        });
                        clusters[nearest].push(pixel);
                    });
                    centroids = clusters.map((cluster, idx) => {
                        if (cluster.length === 0) {
                            return centroids[idx];
                        }
                        const avg = [0, 0, 0];
                        cluster.forEach((pixel) => {
                            avg[0] += pixel[0];
                            avg[1] += pixel[1];
                            avg[2] += pixel[2];
                        });
                        return avg.map((value) => Math.round(value / cluster.length));
                    });
                }

                centroids.sort((a, b) => {
                    const satA = Math.max(...a) === 0 ? 0 : (Math.max(...a) - Math.min(...a)) / Math.max(...a);
                    const satB = Math.max(...b) === 0 ? 0 : (Math.max(...b) - Math.min(...b)) / Math.max(...b);
                    return satB - satA;
                });

                const brandHex = rgbToHex(...centroids[0]);
                setColorField('picker-primary', 'primary_color', brandHex);
                setColorField('picker-border-color', 'border_color', centroids[1] ? rgbToHex(...centroids[1]) : '#e2e8f0');
                setColorField('picker-bg-body', 'bg_body', rgbToHex(...centroids[0].map((value) => value + (255 - value) * 0.92)));
                setColorField('picker-bg-card', 'bg_card', '#ffffff');

                if (autoSyncEnabled) {
                    syncBrandingContrast();
                } else {
                    updateBrandingPreview();
                }
            };
            img.src = event.target?.result || '';
        };
        reader.readAsDataURL(logoInput.files[0]);
    }

    if (companyNameInput) {
        updateCompanyNamePreview(companyNameInput.value);
        companyNameInput.addEventListener('input', (event) => updateCompanyNamePreview(event.target.value));
    }

    if (primaryColorInput) {
        setGlobalPrimaryColor(document.getElementById('primary_color')?.value || primaryColorInput.value);
    }

    wireColorPair('picker-primary', 'primary_color', () => {
        if (autoSyncEnabled) {
            syncBrandingContrast();
        } else {
            updateBrandingPreview();
        }
    });
    wireColorPair('picker-bg-body', 'bg_body', () => autoSyncEnabled ? syncBrandingContrast() : updateBrandingPreview());
    wireColorPair('picker-bg-card', 'bg_card', () => autoSyncEnabled ? syncBrandingContrast() : updateBrandingPreview());
    wireColorPair('picker-text-main', 'text_main', updateBrandingPreview);
    wireColorPair('picker-text-muted', 'text_muted', updateBrandingPreview);
    wireColorPair('picker-border-color', 'border_color', updateBrandingPreview);

    previewButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            const target = btn.getAttribute('data-view');
            previewButtons.forEach((item) => item.classList.toggle('active', item === btn));
            previewScreens.forEach((screen) => {
                screen.classList.toggle('active', screen.getAttribute('data-preview') === target);
            });
        });
    });

    if (logoInput) {
        logoInput.addEventListener('change', () => {
            if (extractPaletteBtn) {
                extractPaletteBtn.style.display = logoInput.files && logoInput.files[0] && logoInput.files[0].type !== 'image/svg+xml'
                    ? 'inline-flex'
                    : 'none';
            }
            updateLogoPreview();
        });
    }

    if (extractPaletteBtn) {
        extractPaletteBtn.addEventListener('click', extractPaletteFromLogo);
    }

    if (syncBtn) {
        syncBtn.addEventListener('click', () => {
            autoSyncEnabled = !autoSyncEnabled;
            syncBtn.classList.toggle('active', autoSyncEnabled);
            syncBtn.innerHTML = autoSyncEnabled
                ? '<span class="material-symbols-rounded">contrast</span> Smart Contrast Sync: On'
                : '<span class="material-symbols-rounded">contrast</span> Smart Contrast Sync: Off';
            if (autoSyncEnabled) {
                syncBrandingContrast();
            }
        });
    }

    document.querySelectorAll('.shadow-opt').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelectorAll('.shadow-opt').forEach((item) => item.classList.remove('active'));
            button.classList.add('active');
            const cardShadowInput = document.getElementById('card_shadow');
            if (cardShadowInput) {
                cardShadowInput.value = button.dataset.shadow || 'sm';
            }
            updateBrandingPreview();
        });
    });

    const borderWidthInput = document.getElementById('card_border_width');
    if (borderWidthInput) {
        borderWidthInput.addEventListener('input', updateBrandingPreview);
    }

    const fontFamilyInput = document.getElementById('font_family');
    if (fontFamilyInput) {
        fontFamilyInput.addEventListener('change', () => {
            if (previewStage) {
                previewStage.style.fontFamily = `'${fontFamilyInput.value}', sans-serif`;
            }
        });
    }

    updateBrandingPreview();
    updateLogoPreview();

    if (loanProductsForm && loanPreviewRoot) {
        const loanPreviewFields = Array.from(loanProductsForm.querySelectorAll(
            '[name="product_name"], [name="product_type"], [name="description"], [name="min_amount"], [name="max_amount"], [name="interest_rate"], [name="interest_type"], [name="min_term_months"], [name="max_term_months"], [name="processing_fee_percentage"], [name="service_charge"], [name="documentary_stamp"], [name="insurance_fee_percentage"], [name="penalty_rate"], [name="penalty_type"], [name="grace_period_days"]'
        ));

        loanPreviewFields.forEach((field) => {
            field.addEventListener('input', updateLoanProductsPreview);
            field.addEventListener('change', updateLoanProductsPreview);
        });

        [loanPreviewAmountInput, loanPreviewTermInput].forEach((field) => {
            if (!field) {
                return;
            }
            field.addEventListener('input', updateLoanProductsPreview);
            field.addEventListener('change', updateLoanProductsPreview);
        });

        updateLoanProductsPreview();
    }

    function syncToggleHiddenFields() {
        const map = [
            { checkbox: toggleBooking, hidden: document.getElementById('hidden-toggle-booking') },
            { checkbox: toggleRegistration, hidden: document.getElementById('hidden-toggle-registration') },
            { checkbox: toggleMaintenance, hidden: document.getElementById('hidden-toggle-maintenance') },
            { checkbox: toggleEmails, hidden: document.getElementById('hidden-toggle-emails') },
            { checkbox: toggleWebsite, hidden: document.getElementById('hidden-toggle-website') }
        ];

        map.forEach((item) => {
            if (!item.checkbox || !item.hidden) {
                return;
            }

            item.hidden.disabled = !item.checkbox.checked;
        });
    }

    [toggleBooking, toggleRegistration, toggleMaintenance, toggleEmails, toggleWebsite].forEach((toggle) => {
        if (toggle) {
            toggle.addEventListener('change', syncToggleHiddenFields);
        }
    });

    syncToggleHiddenFields();

    if (settingsForm && saveBtn) {
        settingsForm.addEventListener('submit', () => {
            syncToggleHiddenFields();
            saveBtn.innerText = 'Saving...';
            saveBtn.style.opacity = '0.8';
        });
    }

    // Role Presets Logic
    const rolePresetSelect = document.getElementById('role-preset');
    const roleNameInput = document.getElementById('create_role_name');
    const createRolePermissions = document.querySelectorAll('#create-role-permissions-container input[type="checkbox"]');

    if (rolePresetSelect && roleNameInput && createRolePermissions.length > 0) {
        const presets = {
            'manager': {
                name: 'Manager',
                perms: ['CREATE_LOAN', 'APPROVE_LOAN', 'VIEW_REPORTS', 'MANAGE_USERS', 'VIEW_KPI', 'EXPORT_DATA']
            },
            'loan_officer': {
                name: 'Loan Officer',
                perms: ['CREATE_LOAN', 'VIEW_LOANS', 'EDIT_LOAN', 'VIEW_CLIENT_DOCS']
            },
            'teller': {
                name: 'Teller',
                perms: ['PROCESS_PAYMENT', 'VIEW_TRANSACTIONS', 'VIEW_CLIENT_BASIC']
            }
        };

        rolePresetSelect.addEventListener('change', (e) => {
            const val = e.target.value;
            
            if (val === 'custom') {
                createRolePermissions.forEach(cb => cb.checked = false);
                return;
            }

            const preset = presets[val];
            if (preset) {
                // Auto-fill name if it's empty or currently matches another preset
                const currentName = roleNameInput.value.trim();
                const isCurrentNameAPreset = Object.values(presets).some(p => p.name === currentName) || currentName === '';
                if (isCurrentNameAPreset) {
                    roleNameInput.value = preset.name;
                }

                // Check boxes
                createRolePermissions.forEach(cb => {
                    cb.checked = preset.perms.includes(cb.value);
                });
            }
        });
    }

});
