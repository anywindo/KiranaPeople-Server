<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Aktivasi Kata Sandi | KIRANA People</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    @include('layouts.partials.favicons')

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            --tn-accent: #F775BA;
            --tn-accent-hover: #e01b8e;
            --tn-accent-shadow: rgba(255, 33, 164, 0.32);
            --tn-accent-light: rgba(255, 33, 164, 0.12);
            --tn-text-primary: #1C1C1E;
            --tn-text-secondary: #8E8E93;
            --tn-text-body: #333333;
            --tn-divider: #E5E5EA;
            --tn-input-bg: #F2F4F7;
            --tn-input-placeholder: #9CA3AF;
            --tn-btn-disabled-bg: #E2E4E9;
            --tn-btn-disabled-text: #FFFFFF;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Plus Jakarta Sans", "SF Pro Display", "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #FFFFFF;
            color: var(--tn-text-primary);
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px 20px;
            margin: 0;
        }

        .onboarding-wrapper {
            width: 100%;
            max-width: 400px;
            display: flex;
            flex-direction: column;
            align-items: center;
            background-color: #FFFFFF;
            /* Optical centering with medium upward offset */
            margin-top: -3vh;
            padding: 0 8px;
            position: relative;
        }

        /* Key Icon Badge */
        .badge-icon-container {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background-color: var(--tn-accent-light);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }

        .badge-icon {
            font-size: 28px;
            color: var(--tn-accent);
            transform: rotate(-45deg);
        }

        /* Header Text */
        .header-section {
            text-align: center;
            margin-bottom: 24px;
            width: 100%;
        }

        .page-title {
            font-size: 23px;
            font-weight: 700;
            color: var(--tn-text-primary);
            letter-spacing: -0.4px;
            margin-bottom: 8px;
        }

        .page-subtitle {
            font-size: 14px;
            font-weight: 400;
            color: var(--tn-text-secondary);
            line-height: 1.45;
            max-width: 340px;
            margin: 0 auto;
        }

        .employee-badge {
            display: inline-block;
            font-weight: 600;
            color: var(--tn-text-primary);
        }

        /* Alert Banners */
        .alert-banner {
            width: 100%;
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 13.5px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: fadeIn 0.25s ease-out;
        }

        .alert-error {
            background-color: rgba(239, 68, 68, 0.09);
            color: #DC2626;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        /* Form Controls */
        .onboarding-form {
            width: 100%;
        }

        .form-group-custom {
            margin-bottom: 16px;
            width: 100%;
        }

        .form-label-custom {
            display: block;
            font-size: 13.5px;
            font-weight: 600;
            color: #4B5563;
            margin-bottom: 7px;
        }

        .input-box-wrapper {
            position: relative;
            width: 100%;
            background-color: var(--tn-input-bg);
            border: 1.5px solid transparent;
            border-radius: 12px;
            display: flex;
            align-items: center;
            transition: all 0.2s ease;
        }

        .input-box-wrapper:focus-within {
            background-color: #FFFFFF;
            border-color: var(--tn-accent);
            box-shadow: 0 0 0 3px rgba(255, 33, 164, 0.12);
        }

        .custom-input {
            width: 100%;
            border: none;
            background: transparent;
            font-size: 14.5px;
            color: var(--tn-text-primary);
            padding: 13px 44px 13px 14px;
            outline: none;
            font-family: inherit;
        }

        .custom-input::placeholder {
            color: var(--tn-input-placeholder);
            opacity: 1;
        }

        .password-toggle-btn {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--tn-text-secondary);
            font-size: 18px;
            cursor: pointer;
            padding: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.15s ease;
        }

        .password-toggle-btn:hover {
            color: var(--tn-text-primary);
        }

        /* Requirements Checklist */
        .requirements-container {
            margin-top: 18px;
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .rule-row {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13.5px;
            color: var(--tn-text-secondary);
            transition: color 0.2s ease;
        }

        .rule-row.valid {
            color: var(--tn-text-primary);
            font-weight: 500;
        }

        .rule-icon {
            font-size: 16px;
            color: #C7C7CC;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }

        .rule-row.valid .rule-icon {
            color: var(--tn-accent);
        }

        /* Privacy Policy Checkbox */
        .privacy-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 26px;
            cursor: pointer;
            user-select: none;
        }

        .privacy-checkbox-custom {
            width: 18px;
            height: 18px;
            border-radius: 4px;
            border: 1.5px solid #C7C7CC;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 2px;
            transition: all 0.2s ease;
            background-color: transparent;
            color: transparent;
            font-size: 11px;
        }

        .privacy-checkbox-custom.checked {
            background-color: var(--tn-accent);
            border-color: var(--tn-accent);
            color: #FFFFFF;
        }

        .privacy-text {
            font-size: 13px;
            line-height: 1.45;
            color: #4B5563;
        }

        .privacy-link {
            color: var(--tn-accent);
            text-decoration: underline;
            font-weight: 600;
            cursor: pointer;
        }

        .privacy-link:hover {
            color: var(--tn-accent-hover);
        }

        /* Submit Button */
        .submit-btn {
            width: 100%;
            height: 48px;
            border-radius: 12px;
            border: none;
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.2s ease;
            background-color: var(--tn-btn-disabled-bg);
            color: var(--tn-btn-disabled-text);
            box-shadow: none;
        }

        .submit-btn.ready {
            background-color: var(--tn-accent);
            color: #FFFFFF;
            box-shadow: 0 8px 18px var(--tn-accent-shadow);
        }

        .submit-btn.ready:hover {
            background-color: var(--tn-accent-hover);
            transform: translateY(-1px);
            box-shadow: 0 10px 20px var(--tn-accent-shadow);
        }

        .submit-btn.ready:active {
            transform: scale(0.98);
        }

        /* Privacy Policy Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(5px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px;
        }

        .modal-overlay.active {
            display: flex;
            animation: fadeIn 0.2s ease-out;
        }

        .modal-card {
            background: #FFFFFF;
            border-radius: 20px;
            max-width: 480px;
            width: 100%;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            padding: 24px;
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.16);
            animation: scaleUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--tn-divider);
            margin-bottom: 16px;
        }

        .modal-title-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .modal-badge-icon {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background-color: var(--tn-accent-light);
            color: var(--tn-accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .modal-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--tn-text-primary);
            line-height: 1.2;
        }

        .modal-subtitle {
            font-size: 12px;
            color: var(--tn-text-secondary);
            margin-top: 2px;
        }

        .modal-close-btn {
            background: none;
            border: none;
            color: var(--tn-text-secondary);
            font-size: 20px;
            cursor: pointer;
            transition: color 0.15s ease;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close-btn:hover {
            color: var(--tn-text-primary);
        }

        .modal-body {
            overflow-y: auto;
            padding-right: 6px;
            margin-bottom: 18px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .modal-body::-webkit-scrollbar {
            width: 5px;
        }

        .modal-body::-webkit-scrollbar-thumb {
            background-color: #E5E5EA;
            border-radius: 10px;
        }

        .policy-card {
            background-color: #F9FAFB;
            border: 1px solid #EEF0F3;
            border-radius: 14px;
            padding: 14px 16px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .policy-card-header {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .policy-card-icon {
            color: var(--tn-accent);
            font-size: 15px;
            flex-shrink: 0;
        }

        .policy-card-title {
            font-size: 13.5px;
            font-weight: 700;
            color: var(--tn-text-primary);
        }

        .policy-card-content {
            font-size: 12.5px;
            line-height: 1.55;
            color: #4B5563;
        }

        .policy-agreement-box {
            background-color: var(--tn-accent-light);
            border: 1px solid rgba(255, 33, 164, 0.22);
            border-radius: 14px;
            padding: 12px 14px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 12px;
            color: var(--tn-text-primary);
            line-height: 1.45;
        }

        .policy-agreement-box i {
            color: var(--tn-accent);
            font-size: 16px;
            flex-shrink: 0;
            margin-top: 1px;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes scaleUp {
            from { opacity: 0; transform: scale(0.92); }
            to { opacity: 1; transform: scale(1); }
        }
    </style>
</head>
<body>
    <div class="onboarding-wrapper">
        <!-- Key Icon Badge -->
        <div class="badge-icon-container">
            <i class="bi bi-key-fill badge-icon"></i>
        </div>

        <!-- Title & Subtitle -->
        <div class="header-section">
            <h1 class="page-title">Aktivasi Kata Sandi</h1>
            <p class="page-subtitle">
                Halo <span class="employee-badge">{{ $user->name }}</span>
                @if($user->employee_id)
                    (<span class="employee-badge">{{ $user->employee_id }}</span>)
                @endif,
                demi keamanan silakan buat kata sandi baru untuk akun KIRANA People Anda.
            </p>
        </div>

        <!-- Error Alerts -->
        @if ($errors->any())
            <div class="alert-banner alert-error">
                <i class="bi bi-exclamation-circle-fill"></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <!-- Form -->
        <form action="{{ route('onboarding.password.update') }}" method="POST" class="onboarding-form" id="onboardingForm">
            @csrf

            <!-- Kata Sandi Baru -->
            <div class="form-group-custom">
                <label class="form-label-custom" for="passwordInput">Kata Sandi Baru</label>
                <div class="input-box-wrapper">
                    <input 
                        type="password" 
                        name="password" 
                        id="passwordInput" 
                        class="custom-input" 
                        placeholder="Minimal 8 karakter" 
                        required 
                        autofocus
                        autocomplete="new-password"
                    >
                    <button type="button" class="password-toggle-btn" id="togglePasswordBtn" title="Tampilkan kata sandi" aria-label="Toggle password">
                        <i class="bi bi-eye-slash" id="togglePasswordIcon"></i>
                    </button>
                </div>
            </div>

            <!-- Konfirmasi Kata Sandi -->
            <div class="form-group-custom">
                <label class="form-label-custom" for="confirmPasswordInput">Konfirmasi Kata Sandi</label>
                <div class="input-box-wrapper">
                    <input 
                        type="password" 
                        name="password_confirmation" 
                        id="confirmPasswordInput" 
                        class="custom-input" 
                        placeholder="Ketik ulang kata sandi" 
                        required 
                        autocomplete="new-password"
                    >
                    <button type="button" class="password-toggle-btn" id="toggleConfirmBtn" title="Tampilkan kata sandi" aria-label="Toggle confirm password">
                        <i class="bi bi-eye-slash" id="toggleConfirmIcon"></i>
                    </button>
                </div>
            </div>

            <!-- Requirements List -->
            <div class="requirements-container">
                <div class="rule-row" id="ruleLength">
                    <i class="bi bi-circle rule-icon" id="iconLength"></i>
                    <span>Minimal 8 karakter</span>
                </div>
                <div class="rule-row" id="ruleLetters">
                    <i class="bi bi-circle rule-icon" id="iconLetters"></i>
                    <span>Kombinasi huruf besar & huruf kecil</span>
                </div>
                <div class="rule-row" id="ruleNumber">
                    <i class="bi bi-circle rule-icon" id="iconNumber"></i>
                    <span>Mengandung minimal 1 angka</span>
                </div>
                <div class="rule-row" id="ruleMatch">
                    <i class="bi bi-circle rule-icon" id="iconMatch"></i>
                    <span>Konfirmasi kata sandi cocok</span>
                </div>
            </div>

            <!-- Privacy Policy Row -->
            <div class="privacy-row" id="privacyContainer">
                <input type="checkbox" id="privacyCheckbox" style="display: none;" required>
                <div class="privacy-checkbox-custom" id="privacyVisual">
                    <i class="bi bi-check"></i>
                </div>
                <span class="privacy-text">
                    Saya telah membaca dan menyetujui 
                    <span class="privacy-link" id="openPrivacyBtn">Kebijakan Privasi & Ketentuan Layanan <i class="bi bi-box-arrow-up-right" style="font-size: 11px;"></i></span>
                </span>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="submit-btn" id="submitBtn">
                Simpan & Lanjutkan
            </button>
        </form>
    </div>

    <!-- Privacy Policy Modal -->
    <div class="modal-overlay" id="privacyModal">
        <div class="modal-card">
            <div class="modal-header">
                <div class="modal-title-group">
                    <div class="modal-badge-icon">
                        <i class="bi bi-shield-lock-fill"></i>
                    </div>
                    <div>
                        <div class="modal-title">Kebijakan Privasi & Ketentuan</div>
                        <div class="modal-subtitle">KIRANA People • Berlaku Efektif</div>
                    </div>
                </div>
                <button type="button" class="modal-close-btn" id="closePrivacyBtn" aria-label="Tutup">
                    <i class="bi bi-x-circle-fill"></i>
                </button>
            </div>
            <div class="modal-body">
                <!-- Section 1: Lokasi & Geofence -->
                <div class="policy-card">
                    <div class="policy-card-header">
                        <i class="bi bi-geo-alt-fill policy-card-icon"></i>
                        <span class="policy-card-title">1. Pengumpulan Data Lokasi (GPS & Geofence)</span>
                    </div>
                    <div class="policy-card-content">
                        Aplikasi ini memproses koordinat lokasi (latitude dan longitude) perangkat Anda untuk memvalidasi bahwa presensi masuk, istirahat, dan pulang dilakukan di dalam batas radius area kantor kerja yang sah. Data lokasi hanya diambil saat Anda membuka aplikasi dan saat proses presensi berlangsung. Aplikasi secara otomatis menolak lokasi tiruan (Fake GPS / mock location).
                    </div>
                </div>

                <!-- Section 2: Verifikasi Kamera & Foto Wajah -->
                <div class="policy-card">
                    <div class="policy-card-header">
                        <i class="bi bi-camera-fill policy-card-icon"></i>
                        <span class="policy-card-title">2. Verifikasi Foto Wajah & Kamera</span>
                    </div>
                    <div class="policy-card-content">
                        Untuk menjamin keaslian dan mencegah manipulasi presensi (titip absen), aplikasi memerlukan akses kamera depan untuk mengambil foto selfie real-time saat melakukan presensi. Foto diunggah dan disimpan secara terenkripsi pada server perusahaan sebagai bukti kehadiran resmi dan audit tim SDM/HR.
                    </div>
                </div>

                <!-- Section 3: Keamanan Kata Sandi & Akun -->
                <div class="policy-card">
                    <div class="policy-card-header">
                        <i class="bi bi-shield-check policy-card-icon"></i>
                        <span class="policy-card-title">3. Keamanan Kata Sandi & Akun</span>
                    </div>
                    <div class="policy-card-content">
                        Kata sandi yang Anda buat pada tahap aktivasi ini dienkripsi menggunakan standar keamanan industri. Anda bertanggung jawab penuh atas kerahasiaan kata sandi Anda dan dilarang memberikan akses akun kepada pihak lain. Token otentikasi disimpan dengan aman pada sesi terenkripsi perangkat Anda.
                    </div>
                </div>

                <!-- Section 4: Penyimpanan & Kerahasiaan Data -->
                <div class="policy-card">
                    <div class="policy-card-header">
                        <i class="bi bi-hdd-network-fill policy-card-icon"></i>
                        <span class="policy-card-title">4. Penyimpanan & Kerahasiaan Data</span>
                    </div>
                    <div class="policy-card-content">
                        Seluruh data riwayat kehadiran, waktu kerja, dan foto presensi disimpan secara aman di infrastruktur server perusahaan KIRANA GROUP. Data hanya dapat diakses oleh petugas HR dan pimpinan yang berwenang, serta tidak akan pernah dibagikan atau diperjualbelikan kepada pihak ketiga di luar kepentingan operasional perusahaan.
                    </div>
                </div>

                <!-- Section 5: Integritas Presensi & Kepatuhan -->
                <div class="policy-card">
                    <div class="policy-card-header">
                        <i class="bi bi-patch-check-fill policy-card-icon"></i>
                        <span class="policy-card-title">5. Integritas Presensi & Kepatuhan</span>
                    </div>
                    <div class="policy-card-content">
                        Karyawan berkewajiban mencatat kehadiran secara tertib dan berurutan sesuai jam kerja dan jadwal istirahat. Segala bentuk manipulasi data kehadiran, penggunaan emulator, atau kecurangan biometrik akan dikenakan tindakan disipliner sesuai peraturan kepegawaian perusahaan.
                    </div>
                </div>

                <!-- Agreement Notice Box -->
                <div class="policy-agreement-box">
                    <i class="bi bi-hand-thumbs-up-fill"></i>
                    <span>Dengan menggunakan aplikasi ini, Anda menyetujui seluruh ketentuan privasi dan pemrosesan data kehadiran di atas.</span>
                </div>
            </div>
            <button type="button" class="submit-btn ready" id="acceptPrivacyBtn" style="height: 46px; font-size: 15px;">
                Saya Mengerti & Setuju
            </button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const pwdInput = document.getElementById('passwordInput');
            const confirmInput = document.getElementById('confirmPasswordInput');
            const togglePwdBtn = document.getElementById('togglePasswordBtn');
            const toggleConfirmBtn = document.getElementById('toggleConfirmBtn');
            const togglePwdIcon = document.getElementById('togglePasswordIcon');
            const toggleConfirmIcon = document.getElementById('toggleConfirmIcon');

            const ruleLength = document.getElementById('ruleLength');
            const ruleLetters = document.getElementById('ruleLetters');
            const ruleNumber = document.getElementById('ruleNumber');
            const ruleMatch = document.getElementById('ruleMatch');

            const iconLength = document.getElementById('iconLength');
            const iconLetters = document.getElementById('iconLetters');
            const iconNumber = document.getElementById('iconNumber');
            const iconMatch = document.getElementById('iconMatch');

            const privacyContainer = document.getElementById('privacyContainer');
            const privacyCheckbox = document.getElementById('privacyCheckbox');
            const privacyVisual = document.getElementById('privacyVisual');

            const submitBtn = document.getElementById('submitBtn');

            // Password toggles
            togglePwdBtn.addEventListener('click', function () {
                if (pwdInput.type === 'password') {
                    pwdInput.type = 'text';
                    togglePwdIcon.classList.remove('bi-eye-slash');
                    togglePwdIcon.classList.add('bi-eye');
                } else {
                    pwdInput.type = 'password';
                    togglePwdIcon.classList.remove('bi-eye');
                    togglePwdIcon.classList.add('bi-eye-slash');
                }
            });

            toggleConfirmBtn.addEventListener('click', function () {
                if (confirmInput.type === 'password') {
                    confirmInput.type = 'text';
                    toggleConfirmIcon.classList.remove('bi-eye-slash');
                    toggleConfirmIcon.classList.add('bi-eye');
                } else {
                    confirmInput.type = 'password';
                    toggleConfirmIcon.classList.remove('bi-eye');
                    toggleConfirmIcon.classList.add('bi-eye-slash');
                }
            });

            // Validation rules
            function updateRule(elem, iconElem, isValid) {
                if (isValid) {
                    elem.classList.add('valid');
                    iconElem.className = 'bi bi-check-circle-fill rule-icon';
                } else {
                    elem.classList.remove('valid');
                    iconElem.className = 'bi bi-circle rule-icon';
                }
            }

            function validateForm() {
                const val = pwdInput.value;
                const confirmVal = confirmInput.value;

                const hasLength = val.length >= 8;
                const hasLetters = /[a-z]/.test(val) && /[A-Z]/.test(val);
                const hasNumber = /[0-9]/.test(val);
                const hasMatch = val.length > 0 && val === confirmVal;

                updateRule(ruleLength, iconLength, hasLength);
                updateRule(ruleLetters, iconLetters, hasLetters);
                updateRule(ruleNumber, iconNumber, hasNumber);
                updateRule(ruleMatch, iconMatch, hasMatch);

                const isAllValid = hasLength && hasLetters && hasNumber && hasMatch && privacyCheckbox.checked;

                if (isAllValid) {
                    submitBtn.classList.add('ready');
                } else {
                    submitBtn.classList.remove('ready');
                }
            }

            pwdInput.addEventListener('input', validateForm);
            confirmInput.addEventListener('input', validateForm);

            // Privacy checkbox toggle
            privacyContainer.addEventListener('click', function (e) {
                if (e.target.closest('#openPrivacyBtn')) return;
                privacyCheckbox.checked = !privacyCheckbox.checked;
                updatePrivacyVisual();
                validateForm();
            });

            function updatePrivacyVisual() {
                if (privacyCheckbox.checked) {
                    privacyVisual.classList.add('checked');
                } else {
                    privacyVisual.classList.remove('checked');
                }
            }

            // Privacy Modal
            const privacyModal = document.getElementById('privacyModal');
            const openPrivacyBtn = document.getElementById('openPrivacyBtn');
            const closePrivacyBtn = document.getElementById('closePrivacyBtn');
            const acceptPrivacyBtn = document.getElementById('acceptPrivacyBtn');

            function openModal() {
                privacyModal.classList.add('active');
            }

            function closeModal() {
                privacyModal.classList.remove('active');
            }

            openPrivacyBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                openModal();
            });

            closePrivacyBtn.addEventListener('click', closeModal);
            acceptPrivacyBtn.addEventListener('click', function () {
                privacyCheckbox.checked = true;
                updatePrivacyVisual();
                validateForm();
                closeModal();
            });

            privacyModal.addEventListener('click', function (e) {
                if (e.target === privacyModal) {
                    closeModal();
                }
            });

            validateForm();
        });
    </script>
</body>
</html>