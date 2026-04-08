# Platform Theme Tokens

Scope: `super_admin` only.

Tenant-facing screens in `tenant_login` and `admin_panel` are intentionally unchanged.

## Light Mode: Trust & Growth

- Primary: `--primary-color` `#1f8a5a`
- Secondary accent: `--secondary-color` `#d8a62a`
- Background: `--bg-body` `#f7f3e8`
- Surface: `--bg-card` `#fffdf7`
- Text: `--text-main` `#1f2d25`
- Muted text: `--text-muted` `#65756a`

Usage:

- Use green for primary actions, active navigation, focus states, and dashboard emphasis.
- Use yellow only for highlights, guided cues, premium accents, and warning-adjacent support.
- Keep large surfaces in cream and warm neutrals instead of pure white.

## Dark Mode: Galaxy / Intelligence

- Primary: `--primary-color` `#8d63ff`
- Supporting purple: `--secondary-color` `#b998ff`
- Background: `--bg-body` `#07040d`
- Surface: `--bg-card` `#131022`
- Text: `--text-main` `#f5f2ff`
- Muted text: `--text-muted` `#a79fc4`

Usage:

- Purple stays dominant across navigation, charts, buttons, and elevation.
- Support depth with `--theme-gradient`, `--theme-gradient-soft`, and `--theme-glow`.
- Avoid introducing unrelated accent colors outside semantic states.

## Semantic Colors

- Success: `--success-color` `#1f9d55`
- Warning: `--warning-color` `#d97706`
- Error: `--error-color` `#dc2626`

Semantic families remain the same in both themes. Use the related `--tone-*` tokens for accessible badge, alert, and surface variants.

## Implementation Notes

- Shared theme variables live in `super_admin_theme.css`.
- Auth/onboarding page styling lives in `super_admin_auth.css`.
- Dashboard component styling lives in `super_admin.css`.
- Charts read CSS tokens in `super_admin.js`, so theme switching updates both UI chrome and data visuals.
