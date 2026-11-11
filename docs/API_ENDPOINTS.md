# API Endpoints Documentation

Base URL: `http://localhost:8000/api/v1` (atau URL domain production dengan prefix `/api/v1`)

---

## 📑 Daftar Isi
- [Konvensi & Format Respons](#konvensi--format-respons)
- [1. Status & Health Check](#1-status--health-check)
- [2. Authentication & Profil Pengguna (`/auth`)](#2-authentication--profil-pengguna-auth)
- [3. Onboarding Password (`/onboarding`)](#3-onboarding-password-onboarding)
- [4. Karyawan Presensi & Koreksi (`/employee`)](#4-karyawan-presensi--koreksi-employee)
- [5. Admin / HR Presensi & Koreksi (`/admin`)](#5-admin--hr-presensi--koreksi-admin)
- [6. Admin / HR Manajemen Karyawan (`/admin/employees`)](#6-admin--hr-manajemen-karyawan-adminemployees)
- [7. Manajemen Tim (`/teams`)](#7-manajemen-tim-teams)

---

## Konvensi & Format Respons

### Headers Umum
- `Accept: application/json`
- `Content-Type: application/json` (atau `multipart/form-data` untuk upload avatar / foto selfie)
- `Authorization: Bearer <token>` (untuk endpoint terproteksi)

### Struktur Respons Sukses (Standard Envelope)
```json
{
  "meta": {
    "success": true,
    "message": "Deskripsi pesan sukses."
  },
  "data": { ... }
}
```

### Struktur Respons Gagal / Validasi (HTTP 422 / 400 / 401)
```json
{
  "meta": {
    "success": false,
    "message": "Pesan error umum."
  },
  "errors": {
    "field_name": [
      "Pesan error spesifik pada field."
    ]
  }
}
```

---

## 1. Status & Health Check

### 1.1 Root API Info
- **URL**: `GET /api`
- **Auth**: Publik
- **Deskripsi**: Menampilkan nama aplikasi, versi API, dan status kesehatan dasar.
- **Respons**:
  ```json
  {
    "name": "KiranaPeople",
    "version": "1.0.1",
    "status": "healthy"
  }
  ```

### 1.2 Deep Health Check
- **URL**: `GET /api/health`
- **Auth**: Publik
- **Deskripsi**: Deep health check untuk load balancer, container orchestrator (Kubernetes, Render, Docker), dan monitoring tools. Mengecek konektivitas database (SQL ping), cache subsystem, dan storage (local & Cloudflare R2). Mengembalikan HTTP 200 jika sehat/degraded, atau HTTP 503 jika database terputus.
- **Respons (HTTP 200)**:
  ```json
  {
    "status": "healthy",
    "name": "KiranaPeople",
    "version": "1.0.1",
    "environment": "production",
    "timestamp": "2026-11-11T11:49:07+07:00",
    "checks": {
      "database": {
        "status": "ok",
        "latency_ms": 1.45,
        "connection": "pgsql"
      },
      "cache": {
        "status": "ok",
        "latency_ms": 0.32,
        "driver": "file"
      },
      "storage": {
        "local": {
          "status": "ok",
          "disk": "public"
        },
        "r2": {
          "status": "ok",
          "latency_ms": 450.21,
          "bucket": "kiranapeople"
        }
      }
    }
  }
  ```

### 1.3 V1 API Info
- **URL**: `GET /api/v1`
- **Auth**: Publik
- **Deskripsi**: Heartbeat versi 1 API.
- **Respons**:
  ```json
  {
    "name": "KiranaPeople",
    "api": "v1",
    "status": "healthy"
  }
  ```

---

## 2. Authentication & Profil Pengguna (`/auth`)

### 2.1 Login
- **URL**: `POST /api/v1/auth/login`
- **Auth**: Publik (Throttled)
- **Deskripsi**: Masuk menggunakan email dan password, mengembalikan token Sanctum.
- **Request Body**:
  ```json
  {
    "email": "user@example.com",
    "password": "password123"
  }
  ```
- **Respons (200 OK)**:
  ```json
  {
    "meta": {
      "success": true,
      "message": "Login successful"
    },
    "data": {
      "user": {
        "id": 1,
        "name": "John Doe",
        "email": "user@example.com",
        "phone": "08123456789",
        "roles": ["Employee"],
        "must_change_password": false
      },
      "token": "1|sanctum_token_string...",
      "token_type": "Bearer",
      "expires_in": null
    }
  }
  ```

### 2.2 Register
- **URL**: `POST /api/v1/auth/register`
- **Auth**: Publik (Throttled)
- **Deskripsi**: Pendaftaran akun baru pengguna.
- **Request Body**:
  ```json
  {
    "name": "John Doe",
    "email": "john@example.com",
    "password": "Password123",
    "password_confirmation": "Password123",
    "phone": "08123456789"
  }
  ```

### 2.3 Lupa Password (Request OTP)
- **URL**: `POST /api/v1/auth/forgot-password`
- **Auth**: Publik (Throttle: 3 request / 15 menit)
- **Deskripsi**: Mengirimkan 6 digit kode OTP reset password ke email pengguna terdaftar.
- **Request Body**:
  ```json
  {
    "email": "user@example.com"
  }
  ```
- **Respons (200 OK)**:
  ```json
  {
    "meta": {
      "success": true,
      "message": "Jika email Anda terdaftar, instruksi reset kata sandi dan kode OTP telah dikirimkan ke email Anda."
    },
    "data": null
  }
  ```

### 2.4 Reset Password (Verify OTP)
- **URL**: `POST /api/v1/auth/reset-password`
- **Auth**: Publik (Throttle: 5 request / 15 menit)
- **Deskripsi**: Mereset kata sandi menggunakan kode OTP 6 digit yang dikirim ke email.
- **Request Body**:
  ```json
  {
    "email": "user@example.com",
    "otp": "123456",
    "password": "NewPassword123",
    "password_confirmation": "NewPassword123"
  }
  ```

### 2.5 Get Profil
- **URL**: `GET /api/v1/auth/profile`
- **Auth**: Bearer Token
- **Deskripsi**: Mengambil data detail profil pengguna yang sedang login (termasuk role & lokasi kerja).
- **Respons (200 OK)**:
  ```json
  {
    "meta": {
      "success": true,
      "message": "Profile retrieved"
    },
    "data": {
      "id": 1,
      "name": "John Doe",
      "email": "user@example.com",
      "phone": "08123456789",
      "avatar_url": "https://storage.url/avatars/avatar.jpg",
      "avatar_base64": null,
      "roles": ["Employee"],
      "location": {
        "id": 1,
        "name": "Kantor Pusat",
        "latitude": -6.2088,
        "longitude": 106.8456,
        "radius": 100
      }
    }
  }
  ```

### 2.6 Update Profil
- **URL**: `PUT /api/v1/auth/profile` atau `POST /api/v1/auth/profile`
- **Auth**: Bearer Token
- **Content-Type**: `multipart/form-data` atau `application/json`
- **Deskripsi**: Memperbarui nomor telepon dan upload foto profil/avatar baru (maks 5MB).
- **Request Parameters**:
  - `phone` (string, opsional)
  - `avatar` (file gambar jpg/png/webp, opsional)

### 2.7 Ubah Password Akun
- **URL**: `POST /api/v1/auth/change-password`
- **Auth**: Bearer Token
- **Deskripsi**: Mengubah kata sandi. Wajib memasukkan kata sandi saat ini (`current_password`) jika bukan dalam status default onboarding.
- **Request Body**:
  ```json
  {
    "current_password": "OldPassword123",
    "password": "NewPassword123",
    "password_confirmation": "NewPassword123"
  }
  ```

### 2.8 Refresh Token
- **URL**: `POST /api/v1/auth/refresh`
- **Auth**: Bearer Token
- **Deskripsi**: Mencabut token saat ini dan menerbitkan token Sanctum baru.

### 2.9 Logout
- **URL**: `POST /api/v1/auth/logout`
- **Auth**: Bearer Token
- **Deskripsi**: Menghapus/mencabut token aktif pengguna.

---

## 3. Onboarding Password (`/onboarding`)

### 3.1 Setup Password Pertama Kali (Onboarding)
- **URL**: `POST /api/v1/onboarding/password`
- **Auth**: Bearer Token
- **Deskripsi**: Digunakan oleh karyawan yang baru dibuatkan akun oleh admin untuk mengganti password default (format NIK/tgl lahir) menjadi password pribadi.
- **Request Body**:
  ```json
  {
    "password": "MySecretPassword123",
    "password_confirmation": "MySecretPassword123"
  }
  ```

---

## 4. Karyawan Presensi & Koreksi (`/employee`)

### 4.1 Status Presensi Hari Ini
- **URL**: `GET /api/v1/employee/attendance/today`
- **Auth**: Bearer Token
- **Deskripsi**: Mengambil status kehadiran hari ini, riwayat event clock-in/out, aksi berikutnya yang diizinkan (`next_allowed_action`), status istirahat aktif (`active_break`), data shift, dan radius geofence lokasi kerja.
- **Respons (200 OK)**:
  ```json
  {
    "meta": {
      "success": true,
      "message": "Status presensi hari ini berhasil dimuat."
    },
    "data": {
      "date": "2026-10-16",
      "status": "EXACT",
      "next_allowed_action": "START_SHIFT",
      "active_break": false,
      "shift": {
        "id": 1,
        "name": "Shift Pagi",
        "start_time": "08:00:00",
        "end_time": "17:00:00",
        "break_start": "12:00:00",
        "break_end": "13:00:00"
      },
      "available_shifts": [ ... ],
      "work_location": {
        "id": 1,
        "name": "Kantor Pusat",
        "latitude": -6.2088,
        "longitude": 106.8456,
        "radius": 100.0
      },
      "events": [
        {
          "id": 101,
          "event_type": "START_SHIFT",
          "timestamp": "2026-10-16 07:58:20",
          "latitude": -6.20881,
          "longitude": 106.84562,
          "selfie_url": "https://..."
        }
      ]
    }
  }
  ```

### 4.2 Riwayat Presensi Karyawan
- **URL**: `GET /api/v1/employee/attendance/history?page=1`
- **Auth**: Bearer Token
- **Deskripsi**: Daftar riwayat kehadiran karyawan yang dipaginasi (15 per halaman), lengkap beserta events dan shift.

### 4.3 Kirim Event Presensi (Clock In / Out / Break)
- **URL**: `POST /api/v1/employee/attendance/events`
- **Auth**: Bearer Token
- **Content-Type**: `multipart/form-data`
- **Deskripsi**: Mencatat aktivitas absensi karyawan. Melakukan validasi radius geofence lokasi kerja secara otomatis.
- **Request Parameters**:
  - `event_type` (string, wajib: `START_SHIFT`, `START_BREAK`, `END_BREAK`, `END_SHIFT`)
  - `latitude` (numeric, wajib)
  - `longitude` (numeric, wajib)
  - `selfie` (file gambar, opsional/wajib sesuai aturan lokasi)
  - `shift_id` (integer, opsional jika sudah ada shift)

### 4.4 Daftar Pengajuan Koreksi Absensi
- **URL**: `GET /api/v1/employee/correction-requests?page=1`
- **Auth**: Bearer Token
- **Deskripsi**: Menampilkan daftar permohonan koreksi absensi yang pernah diajukan oleh karyawan (status: `PENDING`, `APPROVED`, `REJECTED`).

### 4.5 Ajukan Koreksi Absensi
- **URL**: `POST /api/v1/employee/correction-requests`
- **Auth**: Bearer Token
- **Deskripsi**: Mengajukan permohonan perbaikan data absensi (misal: lupa absen atau salah jam).
- **Request Body**:
  ```json
  {
    "attendance_id": 45,
    "reason": "Lupa clock out karena lembur darurat",
    "requested_changes": {
      "clock_out": "19:00:00"
    }
  }
  ```

---

## 5. Admin / HR Presensi & Koreksi (`/admin`)
*Khusus role: `admin`, `super-admin`, atau `HR`*

### 5.1 Monitoring Presensi Semua Karyawan
- **URL**: `GET /api/v1/admin/attendances?per_page=15&page=1`
- **Auth**: Bearer Token + Role Admin/HR
- **Deskripsi**: Menampilkan data log kehadiran seluruh karyawan di perusahaan.

### 5.2 Daftar Seluruh Pengajuan Koreksi
- **URL**: `GET /api/v1/admin/correction-requests?per_page=15&page=1`
- **Auth**: Bearer Token + Role Admin/HR
- **Deskripsi**: Mengambil daftar permohonan koreksi absensi dari semua karyawan yang menunggu persetujuan.

### 5.3 Proses (Setujui / Tolak) Koreksi Absensi
- **URL**: `PUT /api/v1/admin/correction-requests/{id}/process`
- **Auth**: Bearer Token + Role Admin/HR
- **Deskripsi**: Memproses approval permohonan koreksi absensi.
- **Request Body**:
  ```json
  {
    "status": "APPROVED",
    "notes": "Disetujui sesuai konfirmasi atasan langsung."
  }
  ```
  *(Nilai status yang valid: `APPROVED` atau `REJECTED`)*

---

## 6. Admin / HR Manajemen Karyawan (`/admin/employees`)
*Khusus role: `admin`, `super-admin`, atau `HR`*

### 6.1 Daftar Karyawan
- **URL**: `GET /api/v1/admin/employees`
- **Auth**: Bearer Token + Role Admin/HR
- **Deskripsi**: Menampilkan semua akun pengguna yang memiliki role `Employee`.

### 6.2 Buat Karyawan Baru
- **URL**: `POST /api/v1/admin/employees`
- **Auth**: Bearer Token + Role Admin/HR
- **Content-Type**: `multipart/form-data` atau `application/json`
- **Deskripsi**: Mendaftarkan akun karyawan baru, menetapkan lokasi kerja, dan auto-assign role `Employee`.
- **Request Body**:
  ```json
  {
    "employee_id": "EMP-001",
    "name": "Ahmad Dani",
    "email": "ahmad@example.com",
    "nik": "3201123456780001",
    "dob": "1995-05-12",
    "phone": "081234567890",
    "location_id": 1,
    "password": "OptionalPassword123"
  }
  ```
  *(Catatan: Jika `password` dikosongkan, default password otomatis dihitung dari DOB & NIK)*

### 6.3 Detail Karyawan
- **URL**: `GET /api/v1/admin/employees/{employee}`
- **Auth**: Bearer Token + Role Admin/HR
- **Deskripsi**: Mengambil rincian data karyawan berdasarkan ID.

### 6.4 Update Data Karyawan
- **URL**: `PUT /api/v1/admin/employees/{employee}` atau `PATCH`
- **Auth**: Bearer Token + Role Admin/HR
- **Deskripsi**: Memperbarui profil, NIK, tanggal lahir, nomor HP, lokasi kerja, password, atau avatar karyawan.

### 6.5 Hapus Karyawan
- **URL**: `DELETE /api/v1/admin/employees/{employee}`
- **Auth**: Bearer Token + Role Admin/HR
- **Deskripsi**: Menghapus data karyawan dari sistem.

---

## 7. Manajemen Tim (`/teams`)

### 7.1 Daftar Tim
- **URL**: `GET /api/v1/teams?per_page=15`
- **Auth**: Bearer Token
- **Deskripsi**: Mengambil daftar tim yang terdaftar.

### 7.2 Buat Tim Baru
- **URL**: `POST /api/v1/teams`
- **Auth**: Bearer Token
- **Request Body**:
  ```json
  {
    "name": "Mobile Dev Team",
    "description": "Tim pengembang aplikasi Android & iOS",
    "owner_id": 1
  }
  ```

### 7.3 Detail Tim
- **URL**: `GET /api/v1/teams/{team}`
- **Auth**: Bearer Token
- **Deskripsi**: Melihat detail informasi tim dan anggota di dalamnya.

### 7.4 Update Tim
- **URL**: `PUT /api/v1/teams/{team}` atau `PATCH`
- **Auth**: Bearer Token
- **Deskripsi**: Memperbarui nama atau deskripsi tim.

### 7.5 Hapus Tim
- **URL**: `DELETE /api/v1/teams/{team}`
- **Auth**: Bearer Token
- **Deskripsi**: Menghapus tim dari sistem.

### 7.6 Tambah Anggota ke Tim
- **URL**: `POST /api/v1/teams/{team}/members`
- **Auth**: Bearer Token
- **Request Body**:
  ```json
  {
    "user_id": 12
  }
  ```

### 7.7 Hapus Anggota dari Tim
- **URL**: `DELETE /api/v1/teams/{team}/members/{user}`
- **Auth**: Bearer Token
- **Deskripsi**: Mengeluarkan anggota (`user_id`) dari tim yang bersangkutan.
