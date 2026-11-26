# Kirana People — Backend Server (`v1.0.7`)

**Kirana People Server** is the proprietary enterprise workforce and attendance backend for **Kirana Group (PT Kirana Desain Indonesia)**, powering the Kirana People mobile application and the HR/Admin web dashboard.

> **Confidential & Proprietary:** This repository is internal to Kirana Group. Unauthorized distribution, copying, or external use is strictly prohibited. See [LICENSE](LICENSE).

---

## Core Capabilities

- **Authentication & Onboarding:** Session-based web auth and stateless Laravel Sanctum Bearer tokens with configurable expiration, password complexity enforcement, mandatory first-login onboarding password change, and OTP-based password reset.
- **Role-Based Access Control (RBAC):** Powered by `spatie/laravel-permission` (`super-admin`, `admin`, `HR`, `Employee`) with strict super-admin account protection.
- **Attendance & Geofencing:** Server-side Haversine geofence validation, shift/Friday-break scheduling, sequential state-machine enforcement (`START_SHIFT` → `START_BREAK` → `END_BREAK` → `END_SHIFT`), atomic concurrency locks, and `X-Idempotency-Key` replay protection.
- **Anti-Spoofing & Security Telemetry:** Validates GPS mock flags, clock drift, and client device-integrity signals (`is_rooted`, `is_hooked`, `is_emulator`, `is_debugger`, `fake_gps_app_installed`, `app_signature`), with dedicated `/api/v1/security/incident` forensic logging.
- **Media Processing & Storage:** Compresses attendance selfies and avatars to WebP via Intervention Image and syncs to Cloudflare R2 with local fallback.
- **HR & Admin Dashboard:** Employee management (Excel import/export), shift & location management, attendance/break/absence reporting, correction request approvals, and super-admin audit logs with dual-gate (download + OTP) archive purging.

---

## Architecture Overview

Built on **Laravel 12 (PHP 8.2+)** using a Repository–Service architecture:

```text
app/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/          # HR/Admin web dashboard controllers
│   │   └── Api/V1/         # REST API v1 controllers (Auth, Employee, Admin, SecurityIncident)
│   ├── Middleware/         # SecurityHeaders, SanitizeInput, ForceJsonResponse, EnsurePasswordIsChanged
│   ├── Requests/           # FormRequest validation classes
│   └── Resources/          # API JSON transformers
├── Models/                 # Eloquent models (User, Attendance, AttendanceEvent, Shift, Location, AuditLog)
├── Repositories/           # Data access contracts & Eloquent implementations
├── Services/               # Business logic (AttendanceService, AuthService, FileUploadService, PasswordResetService)
└── Traits/                 # HasAuditLog, HasCaching, ApiResponseTrait
```

---

## Local Development Setup

1. Install PHP and Node dependencies:
   ```bash
   composer install
   npm install && npm run build
   ```
2. Configure environment variables:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Ensure local `.env` uses HTTP settings (`APP_ENV=local`, `APP_DEBUG=true`, `APP_URL=http://127.0.0.1:8000`, `SESSION_SECURE_COOKIE=false`).
3. Run database migrations and seeders:
   ```bash
   php artisan migrate --seed
   ```
4. Start the local server:
   ```bash
   php artisan serve
   ```
5. Run automated tests and static checks:
   ```bash
   php artisan test
   ./vendor/bin/phpstan analyse app
   ./vendor/bin/pint --test
   ```

---

## Security & Documentation

- **API Documentation:** Available at `/docs/api` (restricted to `local` environment or authenticated Admin/HR users when `API_DOCS_ENABLED=true`).
- **Security Audit:** See [SECURITY_AUDIT.md](SECURITY_AUDIT.md) for the full OWASP audit log and remediation history.
- **Environment Hygiene:** Never commit `.env`, `.env.production`, or service credentials.
