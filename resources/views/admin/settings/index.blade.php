@extends('layouts.admin')

@section('page_title', 'Pengaturan')

@section('content')
    <div class="container-fluid">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (isset($errors) && $errors->any())
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> Terdapat kesalahan pada input pengaturan:
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="row">
            <!-- 1. Group: Pengaturan Rule Presensi -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100 shadow-sm border-0 d-flex flex-column">
                    <div class="card-header bg-transparent border-bottom">
                        <h3 class="card-title fw-bold text-dark mb-0">
                            <i class="bi bi-shield-check text-primary me-2"></i> Pengaturan Rule Presensi
                        </h3>
                    </div>
                    <form action="{{ route('admin.settings.update') }}" method="POST"
                        class="d-flex flex-column flex-grow-1">
                        @csrf
                        <input type="hidden" name="setting_group" value="attendance_rule">
                        <div class="card-body flex-grow-1">
                            <div class="form-check form-switch mb-3">
                                <input type="checkbox" class="form-check-input" id="attendance_rule_enabled"
                                    name="attendance_rule_enabled" value="1" {{ $attendanceRuleEnabled ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="attendance_rule_enabled">Aktifkan Batas
                                    Waktu Shift Start</label>
                            </div>
                            <p class="text-muted small mb-3">
                                Jika diaktifkan, karyawan wajib melakukan presensi shift start sebelum batas waktu yang
                                ditentukan.
                            </p>

                            <div class="mb-2">
                                <label for="attendance_rule_minutes" class="form-label fw-semibold">Batas Waktu (Menit
                                    sebelum shift dimulai)</label>
                                <input type="number" name="attendance_rule_minutes" id="attendance_rule_minutes"
                                    class="form-control"
                                    value="{{ old('attendance_rule_minutes', $attendanceRuleMinutes) }}" min="0" required>
                                <div class="form-text text-muted small mt-2">
                                    Contoh: Jika diisi 15 dan shift mulai 08:00, maka karyawan wajib presensi sebelum pukul
                                    07:45. Presensi pada 07:45 atau setelahnya akan ditolak.
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent border-top d-flex justify-content-end py-3">
                            <button type="submit" class="btn btn-primary px-3 py-2 fw-semibold shadow-sm">
                                <i class="bi bi-save me-1"></i> Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- 2. Group: Pengaturan Istirahat Hari Jumat -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100 shadow-sm border-0 d-flex flex-column">
                    <div class="card-header bg-transparent border-bottom">
                        <h3 class="card-title fw-bold text-dark mb-0">
                            <i class="bi bi-clock-history text-info me-2"></i> Pengaturan Istirahat Hari Jumat
                        </h3>
                    </div>
                    <form action="{{ route('admin.settings.update') }}" method="POST"
                        class="d-flex flex-column flex-grow-1">
                        @csrf
                        <input type="hidden" name="setting_group" value="friday_break">
                        <div class="card-body flex-grow-1">
                            <div class="form-check form-switch mb-3">
                                <input type="checkbox" class="form-check-input" id="friday_break_enabled"
                                    name="friday_break_enabled" value="1" {{ $fridayBreakEnabled ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="friday_break_enabled">Aktifkan Jam
                                    Istirahat Khusus Hari Jumat</label>
                            </div>
                            <p class="text-muted small mb-3">
                                Jika diaktifkan, jam istirahat pada hari Jumat akan otomatis menggunakan waktu di bawah ini
                                (menyesuaikan waktu salat Jumat).
                            </p>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="friday_break_start" class="form-label fw-semibold">Jam Mulai
                                        Istirahat</label>
                                    <input type="time" name="friday_break_start" id="friday_break_start"
                                        class="form-control" value="{{ old('friday_break_start', $fridayBreakStart) }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="friday_break_end" class="form-label fw-semibold">Jam Selesai
                                        Istirahat</label>
                                    <input type="time" name="friday_break_end" id="friday_break_end" class="form-control"
                                        value="{{ old('friday_break_end', $fridayBreakEnd) }}">
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent border-top d-flex justify-content-end py-3">
                            <button type="submit" class="btn btn-primary px-3 py-2 fw-semibold shadow-sm">
                                <i class="bi bi-save me-1"></i> Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- 3. Group: Pengecualian Presensi -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100 shadow-sm border-0 d-flex flex-column">
                    <div class="card-header bg-transparent border-bottom">
                        <h3 class="card-title fw-bold text-dark mb-0">
                            <i class="bi bi-person-x text-danger me-2"></i> Pengecualian Presensi
                        </h3>
                    </div>
                    <form action="{{ route('admin.settings.update') }}" method="POST"
                        class="d-flex flex-column flex-grow-1">
                        @csrf
                        <input type="hidden" name="setting_group" value="exclude_roles">
                        <div class="card-body flex-grow-1">
                            <label class="form-label fw-semibold mb-2">Pilih Role yang Dikecualikan</label>
                            <div class="row mb-3">
                                @foreach($roles as $role)
                                    <div class="col-sm-6 mb-2">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="role_{{ $role->id }}"
                                                name="exclude_roles[]" value="{{ $role->name }}" {{ is_array($excludedRoles) && in_array($role->name, $excludedRoles) ? 'checked' : '' }}>
                                            <label class="form-check-label"
                                                for="role_{{ $role->id }}">{{ ucfirst($role->name) }}</label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="form-text text-muted small">
                                Role yang dipilih tidak akan diminta presensi dan tidak akan mendapatkan notifikasi atau
                                masuk dalam daftar "belum presensi".
                            </div>
                        </div>
                        <div class="card-footer bg-transparent border-top d-flex justify-content-end py-3">
                            <button type="submit" class="btn btn-primary px-3 py-2 fw-semibold shadow-sm">
                                <i class="bi bi-save me-1"></i> Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- 4. Group: Import Data Karyawan -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100 shadow-sm border-0 d-flex flex-column">
                    <div class="card-header bg-transparent border-bottom">
                        <h3 class="card-title fw-bold text-dark mb-0">
                            <i class="bi bi-file-earmark-spreadsheet text-success me-2"></i> Import Data Karyawan
                        </h3>
                    </div>
                    <form action="{{ route('admin.employees.import') }}" method="POST" enctype="multipart/form-data"
                        class="d-flex flex-column flex-grow-1">
                        @csrf
                        <div class="card-body flex-grow-1">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Upload File Excel (.xlsx, .xls, .csv)</label>
                                <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv" required>
                                <div class="form-text text-muted small mt-2">
                                    Pastikan format file sesuai dengan template standar agar data karyawan masuk dengan
                                    benar ke dalam sistem.
                                </div>
                            </div>
                            <div class="p-2 bg-light rounded border d-flex align-items-center justify-content-between">
                                <span class="small text-muted"><i class="bi bi-info-circle text-info me-1"></i> Butuh format
                                    template?</span>
                                <a href="{{ route('admin.employees.template') }}"
                                    class="btn btn-warning btn-sm fw-bold shadow-sm">
                                    <i class="bi bi-download me-1"></i> Download Template
                                </a>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent border-top d-flex justify-content-end py-3">
                            <button type="submit" class="btn btn-primary px-3 py-2 fw-semibold shadow-sm">
                                <i class="bi bi-upload me-1"></i> Mulai Import
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection