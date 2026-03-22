# MicroFinWebb

MicroFinWebb is a multi-tenant microfinance platform with web administration and mobile app support.

## Repository Overview

- `microfin_platform/` contains the PHP multi-tenant web platform (admin panel, tenant login, backend APIs, templates, and public website).
- `microfin_mobile/` contains the mobile API endpoints and Flutter mobile application project.

## Core Stack

- PHP (backend logic and APIs)
- MySQL (multi-tenant data model)
- JavaScript (UI interactions)
- Flutter (mobile client)

## Getting Started Locally

1. Place the project in your web server directory (for example, `xampp/htdocs`).
2. Create and configure the database using the SQL schema in `microfin_platform/docs/database-schema.txt`.
3. Update database credentials in backend connection files as needed.
4. Open `microfin_platform/` entry points in your local server.

## Notes

- This repository includes both web and mobile components in a single codebase.
- Tenant-specific branding, website content, and access are managed in the platform modules.

## Railway Deployment (Web)

The web app is deployable from this repository root with `railway.toml`.

Required Railway environment variables:

- `DATABASE_URL` (MySQL connection string, example: `mysql://user:pass@host:3306/dbname`)
- `BREVO_API_KEY`
- `BREVO_SENDER_EMAIL`
- `BREVO_SENDER_NAME`

Email sending for web flows now uses Brevo centrally via `microfin_platform/backend/db_connect.php` (OTP, password reset, tenant/admin notifications, and demo acknowledgement).
