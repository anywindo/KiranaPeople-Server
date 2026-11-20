# Comprehensive Security Audit Report: TerribleNight (Boily Laravel API & Web)

**Target Codebase:** TerribleNight Backend (`kirana_people` v1.0.5 / Laravel 12.0)  
**Location:** `/Users/arw/Git/TerribleNight`  
**Date of Audit:** November 17, 2026  
**Framework:** Laravel 12.0 (PHP 8.2+) / Sanctum 4.0 / Spatie Permission 6.24 / Bootstrap 5 Blade Dashboard  
**Audit Standards:** OWASP Top 10 (2021/2024), OWASP API Security Top 10 (2023), OWASP ASVS v4.0, WSTG  
**Scope:** Authentication & token lifecycle, authorization & RBAC (BFLA/BOLA), input validation & injection controls, geolocation & anti-spoofing telemetry ingestion, file uploads (Intervention/R2), error handling, security headers, CSRF, and third-party dependencies.

---

## 1. Executive Summary

A comprehensive, defense-in-depth security audit of the **TerribleNight** backend application was conducted. The application serves as the core enterprise workforce and attendance backend supporting the Kirana People Flutter mobile client and an administrative web dashboard.

The application demonstrates strong foundational design in कई areas:
- **Strong Anti-Spoofing & Resilience:** Validates mock GPS flags, detects fake GPS packages, checks root/hooking heuristics, verifies GPS accuracy, checks clock drift, and logs security incidents into `audit_logs`.
- **Robust Geofencing:** Implements server-side Haversine mathematical distance validation against assigned workplace geofence coordinates and radius thresholds.
- **Concurrency & Replay Protections:** Implements atomic Redis/database locks (`Cache::lock`) and `X-Idempotency-Key` response caching to prevent duplicate or race-condition attendance submissions.
- **Secure File Processing:** Converts uploaded attendance selfies to WebP via Intervention Image, scales dimensions down to 800px, and distributes files to Cloudflare R2 object storage.
- **Automated Test Coverage:** 150/150 PHPUnit / Pest tests passing with 685 assertions.

However, several critical and medium-severity authorization flaws and business logic vulnerabilities were discovered that must be remediated prior to enterprise production scaling.

---

## 2. Vulnerability Tracking Matrix

| ID | Title | OWASP Category | CWE | Severity | Status |
| :--- | :--- | :--- | :---: | :---: | :---: |
| **TN-SEC-01** | Broken Function Level Authorization (BFLA) on Team Management API | OWASP API5:2023 | CWE-285 | **High** | ✅ **RESOLVED (COMMENTED OUT)** |
| **TN-SEC-02** | Broken Object Level Authorization (BOLA / IDOR) in Attendance Correction | OWASP API1:2023 | CWE-639 | **High** | ✅ **RESOLVED** |
| **TN-SEC-03** | Unverified Password Change in Web Profile Controllers | OWASP ASVS V2.1.12 | CWE-620 | **Medium** | ✅ **RESOLVED** |
| **TN-SEC-04** | Public Self-Registration Route Left Exposed on Enterprise API | OWASP API2:2023 | CWE-284 | **Medium** | ✅ **RESOLVED (DISABLED)** |
| **TN-SEC-05** | Arbitrary Source File Access & Information Disclosure in `/storage/{path}` | OWASP A01:2021 | CWE-552 | **Medium** | ✅ **RESOLVED** |
| **TN-SEC-06** | User Account Enumeration in Forgot Password Endpoint | OWASP WSTG-IDNT-04 | CWE-204 | **Low** | ✅ **RESOLVED** |
| **TN-SEC-07** | Raw Exception Message Leakage in Production 500 Responses | OWASP API10:2023 | CWE-209 | **Low** | ✅ **RESOLVED** |
| **TN-SEC-08** | Involuntary CSRF Logout via GET Method | OWASP A01:2021 | CWE-352 | **Low** | ✅ **RESOLVED** |
| **TN-SEC-09** | Cloudflare R2 Account Identifier Exposed in Configuration | OWASP A05:2021 | CWE-200 | **Low** | ✅ **RESOLVED** |
| **TN-SEC-10** | 14 Known Vulnerability Advisories in Third-Party Composer Packages | OWASP A06:2021 | CWE-1395 | **Low** | ✅ **RESOLVED** |
| **TN-SEC-11** | Interactive API Documentation (`/docs/api`) Publicly Accessible | OWASP API9:2023 | CWE-200 | **Info** | ✅ **RESOLVED** |

---

## 3. Detailed Findings & Remediation

---

### [TN-SEC-01] Broken Function Level Authorization (BFLA) on Team Management API
* **Category:** OWASP API5:2023 / CWE-285 (Improper Authorization)
* **Severity:** **High**
* **Location:** `routes/api.php` (lines 58-62), `app/Http/Controllers/Api/V1/TeamController.php`, `app/Http/Requests/Team/StoreTeamRequest.php`
* **Description:**
  In `routes/api.php`:
  ```php
  Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
      // Admin/HR Routes
      Route::prefix('admin')->middleware('role:admin|super-admin|HR')->group(function () {
          ...
      });

      // Teams
      Route::post('teams/{team}/members', [TeamController::class, 'addMember']);
      Route::delete('teams/{team}/members/{user}', [TeamController::class, 'removeMember']);
      Route::apiResource('teams', TeamController::class);
  });
  ```
  The entire `/api/v1/teams` resource is placed outside the `role:admin|super-admin|HR` middleware. Furthermore, `StoreTeamRequest` and `UpdateTeamRequest` both define `authorize() => true`, and `TeamService` does not enforce role or ownership checks.
* **Impact:**
  Any authenticated regular employee or basic user can create teams, edit any existing team, delete any organizational team, and arbitrarily add or remove team members across the company.
* **Remediation & Status:**
  ✅ **Resolved:** The Teams feature is currently unused by both the Flutter client and Web dashboard. The exposed routes in `routes/api.php` were commented out to eliminate the attack surface. If developers expand and activate this feature in the future, it should either be placed inside the `role:admin|super-admin|HR` group or guarded by a `TeamPolicy`.

---

### [TN-SEC-02] Broken Object Level Authorization (BOLA / IDOR) in Attendance Correction
* **Category:** OWASP API1:2023 / CWE-639 (Authorization Bypass Through User-Controlled Key)
* **Severity:** **High**
* **Location:** `app/Http/Requests/StoreCorrectionRequest.php` (lines 25-28), `app/Services/AttendanceCorrectionRequestService.php` (lines 21-30)
* **Description:**
  In `StoreCorrectionRequest.php`:
  ```php
  public function rules(): array
  {
      return [
          'attendance_id' => ['required', 'exists:attendances,id'],
          'reason' => ['required', 'string', 'max:1000'],
          'requested_changes' => ['required', 'array'],
      ];
  }
  ```
  And in `AttendanceCorrectionRequestService::submitRequest`:
  ```php
  public function submitRequest(User $employee, int $attendanceId, string $reason, array $requestedChanges): AttendanceCorrectionRequest
  {
      return $this->repository->create([
          'user_id' => $employee->id,
          'attendance_id' => $attendanceId,
          ...
      ]);
  }
  ```
  The endpoint allows an employee to submit a correction request against **any** `attendance_id` in the database without checking whether the attendance record belongs to the authenticated user.
* **Impact:**
  An employee can submit fraudulent modification requests for other workers' attendances. When HR approves the request, another employee's records can be falsified.
* **Remediation & Status:**
  ✅ **Resolved (Defense-in-Depth):**
  1. Updated `StoreCorrectionRequest` to scope `attendance_id` existence to the authenticated user using `Rule::exists('attendances', 'id')->where(fn ($query) => $query->where('user_id', $userId))`.
  2. Hardened `AttendanceCorrectionRequestService::submitRequest` to verify `$employee->attendances()->where('id', $attendanceId)->exists()`, raising a `ValidationException` if an IDOR attempt occurs.
  3. Added comprehensive test coverage in `AttendanceCorrectionServiceTest` and `AttendanceCorrectionApiTest`.

---

### [TN-SEC-03] Unverified Password Change in Web Profile Controllers
* **Category:** OWASP ASVS v4.0 V2.1.12 / CWE-620 (Unverified Password Change)
* **Severity:** **Medium**
* **Location:** `app/Http/Controllers/AttendanceController.php` (lines 205-244), `app/Http/Controllers/Admin/ProfileController.php` (lines 20-56)
* **Description:**
  In `AttendanceController::updateProfile` (Web employee interface):
  ```php
  $validated = $request->validate([
      'password' => 'nullable|string|min:8|confirmed',
  ]);
  if (! empty($validated['password'])) {
      $validated['password'] = Hash::make($validated['password']);
  }
  $user->update($validated);
  ```
  And similarly in `Admin\ProfileController::update` (Admin/Superadmin web profile):
  ```php
  $validated = $request->validate([
      'password' => 'nullable|string|min:8|confirmed',
  ]);
  if (! empty($validated['password'])) {
      $validated['password'] = Hash::make($validated['password']);
  }
  $user->update($validated);
  ```
  While the Mobile API endpoint (`Api/V1/AuthController::changePassword`) strictly requires and checks `current_password` via `Hash::check()`, both Web interfaces omit `current_password` verification completely.
* **Impact:**
  If a user, manager, or superadmin leaves their browser session open, or if session tokens are hijacked, an adversary can change the account's password without knowing the existing password.
* **Remediation & Status:**
  ✅ **Resolved:**
  1. Enforced `'current_password' => ['required_with:password', 'current_password']` in `AttendanceController::updateProfile` and `Admin\ProfileController::update`.
  2. Automatically updates `password_changed_at = now()` and sanitizes `$validated['current_password']` before updating the user model.
  3. Added the "Password Saat Ini" (`current_password`) form input field with error reporting to both `resources/views/attendance/index.blade.php` and `resources/views/admin/profile/index.blade.php`.
  4. Added and verified comprehensive tests in `EmployeeProfileUpdateTest` and `AdminProfileUpdateTest`.

---

### [TN-SEC-04] Public Self-Registration Route Left Exposed on Enterprise API
* **Category:** OWASP API2:2023 / CWE-284 (Improper Access Control)
* **Severity:** **Medium**
* **Location:** `routes/api.php` (line 18), `app/Http/Controllers/Api/V1/AuthController.php` (lines 21-32)
* **Description:**
  `Route::post('register', [AuthController::class, 'register'])` is exposed without restriction. Any anonymous client can call this endpoint to register a new user account with role `user` and receive an active Sanctum token.
* **Impact:**
  In an enterprise employee-management system, accounts should only be provisioned by HR or via administrative invitations. Leaving public registration open allows arbitrary external actors to obtain bearer tokens and chain with **TN-SEC-01** and **TN-SEC-02**.
* **Remediation & Status:**
  ✅ **Resolved (Disabled):**
  1. Disabled and commented out `Route::post('register')` in `routes/api.php` with notes that employee accounts are strictly managed and provisioned by HR / Admin.
  2. Verified via automated test `SanctumTokenExpirationTest::test_public_registration_endpoint_is_disabled` that requests to `POST /api/v1/auth/register` return `404 Not Found` and create no user records.

---

### [TN-SEC-05] Arbitrary Source File Access & Information Disclosure in `/storage/{path}`
* **Category:** OWASP Top 10 A01:2021 / CWE-552 (Files or Directories Accessible to External Parties)
* **Severity:** **Medium**
* **Location:** `routes/web.php` (lines 197-253)
* **Description:**
  In `routes/web.php`:
  ```php
  Route::get('/storage/{path}', function ($path) {
      ...
      // 3. Check in public/
      $publicFile = public_path($path);
      if (file_exists($publicFile) && is_file($publicFile)) {
          return $respondImage(file_get_contents($publicFile), $defaultMime);
      }
  })->where('path', '.*');
  ```
  When a file is not in R2 or `/tmp`, the route falls back to reading `public_path($path)`. Requesting `/storage/index.php` returns the raw PHP source code of `public/index.php` as plain text (with an `image/jpeg` header).
* **Impact:**
  Bypasses PHP execution and exposes any static scripts, configuration files, or build artifacts located in the `public/` folder.
* **Remediation & Status:**
  ✅ **Resolved:**
  1. Removed the arbitrary `public_path($path)` fallback in `routes/web.php`.
  2. Enforced strict path sanitization guarding against `..`, leading slashes, and null bytes.
  3. Added an explicit whitelist for allowed extensions (`webp`, `png`, `jpg`, `jpeg`, `gif`, `svg`, `pdf`); all other extensions (including `.php`, `.env`, `.json`) return HTTP 404 immediately.
  4. Added `X-Content-Type-Options: nosniff` header to prevent MIME-sniffing attacks.
  5. Fallbacks are restricted exclusively to predefined public storage subdirectories (`selfies`, `avatars`, `attendance-selfies`).
  6. Verified via automated tests in `StorageEndpointSecurityTest` (5/5 tests passing).

---

### [TN-SEC-06] User Account Enumeration in Forgot Password Endpoint
* **Category:** OWASP WSTG-IDNT-04 / CWE-204 (Observable Response Discrepancy)
* **Severity:** **Low**
* **Location:** `app/Http/Controllers/Api/V1/AuthController.php` (lines 243-249)
* **Description:**
  ```php
  $sent = $service->sendResetNotification($request->email);
  if (! $sent) {
      return $this->error('Email tidak terdaftar dalam sistem.', 404, [
          'email' => ['Email tidak terdaftar dalam sistem.'],
      ]);
  }
  ```
  The endpoint explicitly distinguishes between registered and non-registered email addresses by returning HTTP 404 versus HTTP 200.
* **Remediation & Status:**
  ✅ **Resolved:**
  1. Updated `AuthController::forgotPassword` (API) and `ForgotPasswordController::sendResetLinkEmail` (Web) to always return an identical success response regardless of whether the email exists.
  2. If the email is registered, the reset OTP is dispatched; if unregistered, no email or token is generated.
  3. Updated and verified `ForgotPasswordTest::test_forgot_password_prevents_user_enumeration_for_unregistered_email` (11/11 tests passing).

---

### [TN-SEC-07] Raw Exception Message Leakage in Production 500 Responses
* **Category:** OWASP API10:2023 / CWE-209 (Generation of Error Message Containing Sensitive Information)
* **Severity:** **Low**
* **Location:** `bootstrap/app.php` (lines 127-133)
* **Description:**
  ```php
  $message = app()->environment('production')
      ? ($e->getMessage() ?: 'Internal server error')
      : $e->getMessage();
  ```
  In production, if `$e->getMessage()` is non-empty (such as database query exceptions containing raw SQL statements, column names, or connection strings), it is directly returned in JSON error responses.
* **Remediation & Status:**
  ✅ **Resolved:**
  1. Updated `bootstrap/app.php` exception handler so that unhandled 500 exceptions in production strictly return the generic message `'Internal server error'`.
  2. Preserves detailed `$e->getMessage()` in local/testing environments for developer debugging.
  3. Added and verified automated test coverage in `ExceptionHandlingSecurityTest` asserting that raw SQL queries or exception details are never leaked in production responses.

---

### [TN-SEC-08] Involuntary CSRF Logout via GET Method
* **Category:** OWASP Top 10 A01:2021 / CWE-352 (Cross-Site Request Forgery)
* **Severity:** **Low**
* **Location:** `routes/web.php` (line 44)
* **Description:**
  `Route::any('/logout', [AuthController::class, 'logout'])->name('logout');` allows unauthenticated GET requests to trigger session termination without CSRF tokens.
* **Remediation & Status:**
  ✅ **Resolved:**
  1. Changed `Route::any('/logout')` to `Route::post('/logout')` in `routes/web.php`.
  2. Confirmed all Blade templates already implement `<form method="POST" action="{{ route('logout') }}">` with `@csrf`.
  3. Added and verified automated test coverage in `CsrfProtectionTest`: `GET /logout` returns `405 Method Not Allowed`, preventing cross-site involuntary logouts.

---

### [TN-SEC-09] Cloudflare R2 Account Identifier Exposed in Configuration
* **Category:** OWASP Top 10 A05:2021 / CWE-200 (Information Disclosure)
* **Severity:** **Low**
* **Status:** ✅ **RESOLVED**
* **Location:** `config/filesystems.php` (line 72), `.env.example` (lines 65-66, 74)
* **Description:**
  The Cloudflare Account ID `d84f9d5770d7659665092bdd2ab2661c` was hardcoded as default fallback in `config/filesystems.php` and `.env.example`.
* **Remediation & Status:**
  ✅ **Resolved:**
  1. Removed the hardcoded fallback endpoint containing the Cloudflare account ID from `config/filesystems.php` (`env('CLOUDFLARE_R2_ENDPOINT', env('AWS_ENDPOINT'))`).
  2. Sanitized `.env.example` by stripping the exposed account identifier and endpoints (`CLOUDFLARE_R2_ENDPOINT=`, `CLOUDFLARE_R2_ACCOUNT_ID=`, and `AWS_ENDPOINT=`).
  3. Real account credentials and endpoints are strictly kept in private `.env` files.

---

### [TN-SEC-10] 14 Known Vulnerability Advisories in Third-Party Packages
* **Category:** OWASP Top 10 A06:2021 / CWE-1395 (Use of Vulnerable Third-Party Component)
* **Severity:** **Low**
* **Status:** ✅ **RESOLVED**
* **Location:** `composer.lock`
* **Description:**
  Prior security scan identified known CVE advisories across third-party Composer packages.
* **Remediation Implemented:**
  Dependencies verified and resolved. Executing `composer audit` confirms 0 vulnerability advisories remaining in the dependency tree (`No security vulnerability advisories found.`).

---

### [TN-SEC-11] Interactive API Documentation (`/docs/api`) Publicly Accessible
* **Category:** OWASP API9:2023 / CWE-200 (Improper Inventory Management)
* **Severity:** **Info**
* **Status:** ✅ **RESOLVED**
* **Location:** `app/Providers/AppServiceProvider.php` (lines 31-37)
* **Description:**
  `Gate::define('viewApiDocs', fn ($user = null) => (bool) env('API_DOCS_ENABLED', true));` defaulted to enabled for public unauthenticated visitors.
* **Remediation Implemented:**
  Restricted API documentation access so that in non-local environments (`production`, `staging`, etc.), access requires an authenticated admin/HR user (`$user->isAdmin()`), while preserving open access for `local` development:
  ```php
  Gate::define('viewApiDocs', function ($user = null) {
      if (! (bool) env('API_DOCS_ENABLED', true)) {
          return false;
      }

      return app()->environment('local') || ($user !== null && $user->isAdmin());
  });
  ```
  Comprehensive feature tests added to `tests/Feature/OpenApiDocumentationTest.php` verifying that guests and regular employees receive 403 Forbidden in non-local environments, while authorized admins and local developers receive 200 OK.

---

## 4. Prioritized Action Plan

1. **Immediate (Sprint 1 - High Priority):**
   - **Fix TN-SEC-01:** Place `teams` routes under `role:admin|super-admin|HR` or attach policies.
   - **Fix TN-SEC-02:** Enforce `$attendance->user_id === $employee->id` on correction requests.
   - **Fix TN-SEC-04:** Disable public `register` API endpoint.

2. **Short-Term (Sprint 2 - Medium Priority):**
   - **Fix TN-SEC-03:** Enforce `current_password` on web profile updates.
   - **Fix TN-SEC-05:** Eliminate `public_path` fallback in `/storage/{path}`.
   - **Fix TN-SEC-10:** Run `composer update` to resolve package advisories.

3. **Defense-in-Depth (Sprint 3 - Low/Info Priority):**
   - Neutralize account enumeration in `forgotPassword` (TN-SEC-06).
   - Sanitize 500 error messages in `bootstrap/app.php` (TN-SEC-07).
   - Convert `/logout` to POST-only (TN-SEC-08).
   - Clean up R2 account hashes (TN-SEC-09) and protect API docs (TN-SEC-11).
