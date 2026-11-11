<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Masuk ke Akun Anda | Kirana People</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    @include('layouts.partials.favicons')

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            --tn-accent: #FF21A4;
            --tn-accent-hover: #e01b8e;
            --tn-accent-shadow: rgba(255, 33, 164, 0.32);
            --tn-text-primary: #1C1C1E;
            --tn-text-secondary: #8E8E93;
            --tn-text-body: #333333;
            --tn-divider: #E5E5EA;
            --tn-input-placeholder: #C7C7CC;
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

        .login-wrapper {
            width: 100%;
            max-width: 400px;
            display: flex;
            flex-direction: column;
            align-items: center;
            background-color: #FFFFFF;
            /* Medium vertical offset towards the top */
            margin-top: -4vh;
            padding: 0 8px;
        }

        .main-content {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Logo */
        .logo-container {
            margin-bottom: 24px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .app-logo {
            width: 102px;
            height: 102px;
            border-radius: 6px;
            object-fit: cover;
            display: block;
        }

        /* Header texts */
        .header-section {
            text-align: center;
            margin-bottom: 28px;
            width: 100%;
        }

        .login-title {
            font-size: 23px;
            font-weight: 700;
            color: var(--tn-text-primary);
            letter-spacing: -0.4px;
            margin-bottom: 8px;
        }

        .login-subtitle {
            font-size: 14.5px;
            font-weight: 400;
            color: var(--tn-text-secondary);
            line-height: 1.35;
        }

        /* Banner alerts */
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

        .alert-success {
            background-color: rgba(16, 185, 129, 0.09);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        /* Form */
        .login-form {
            width: 100%;
        }

        .input-group-underline {
            position: relative;
            margin-bottom: 22px;
            width: 100%;
        }

        .underline-input {
            width: 100%;
            border: none;
            background: transparent;
            font-size: 15.5px;
            color: var(--tn-text-primary);
            padding: 8px 0 10px 0;
            outline: none;
            font-family: inherit;
        }

        .underline-input::placeholder {
            color: var(--tn-input-placeholder);
            opacity: 1;
        }

        .underline-bar {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 1.5px;
            background-color: var(--tn-divider);
            transition: background-color 0.2s ease, height 0.15s ease;
        }

        .input-group-underline:focus-within .underline-bar {
            background-color: var(--tn-accent);
            height: 1.8px;
        }

        .password-toggle-btn {
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-60%);
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

        /* Options Row */
        .options-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 10px;
            margin-bottom: 28px;
            width: 100%;
        }

        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 9px;
            cursor: pointer;
            user-select: none;
        }

        .custom-checkbox-circle {
            width: 19px;
            height: 19px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            background-color: var(--tn-accent);
            color: #FFFFFF;
            font-size: 11px;
            box-shadow: 0 1px 3px rgba(255, 33, 164, 0.2);
        }

        .custom-checkbox-circle.unchecked {
            background-color: transparent;
            border: 1.5px solid #C7C7CC;
            color: transparent;
            box-shadow: none;
        }

        .checkbox-label {
            font-size: 14px;
            font-weight: 400;
            color: var(--tn-text-body);
        }

        .forgot-link {
            font-size: 14px;
            font-weight: 400;
            color: var(--tn-text-body);
            text-decoration: none;
            transition: color 0.15s ease;
        }

        .forgot-link:hover {
            color: var(--tn-accent);
            text-decoration: underline;
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

        /* Version Section */
        .version-section {
            margin-top: 40px;
            margin-bottom: 8px;
            text-align: center;
            font-size: 12.5px;
            color: var(--tn-text-secondary);
            font-weight: 400;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="main-content">
            <!-- App Logo -->
            <div class="logo-container">
                <img src="{{ asset('logoKiranaSquare.jpeg') }}" alt="Kirana Logo" class="app-logo">
            </div>

            <!-- Title & Subtitle -->
            <div class="header-section">
                <h1 class="login-title">Masuk ke Akun Anda</h1>
                <p class="login-subtitle">Silakan masukkan identitas dan kata sandi Anda</p>
            </div>

            <!-- Status / Error Alerts -->
            @if (session('error'))
                <div class="alert-banner alert-error">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @if (session('status'))
                <div class="alert-banner alert-success">
                    <i class="bi bi-check-circle-fill"></i>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert-banner alert-error">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <!-- Form -->
            <form action="{{ url('/login') }}" method="POST" class="login-form" id="loginForm">
                @csrf

                <!-- Identifier Field -->
                <div class="input-group-underline">
                    <input 
                        type="text" 
                        name="email" 
                        id="identifierInput"
                        class="underline-input" 
                        placeholder="Email / ID Karyawan / NIK" 
                        value="{{ old('email', old('login')) }}" 
                        required 
                        autocomplete="username"
                        autocapitalize="none"
                        autofocus
                    >
                    <div class="underline-bar"></div>
                </div>

                <!-- Password Field -->
                <div class="input-group-underline">
                    <input 
                        type="password" 
                        name="password" 
                        id="passwordInput"
                        class="underline-input" 
                        placeholder="Kata Sandi" 
                        required 
                        autocomplete="current-password"
                    >
                    <button type="button" class="password-toggle-btn" id="togglePasswordBtn" title="Tampilkan kata sandi" aria-label="Toggle password visibility">
                        <i class="bi bi-eye-slash" id="togglePasswordIcon"></i>
                    </button>
                    <div class="underline-bar"></div>
                </div>

                <!-- Options Row -->
                <div class="options-row">
                    <label class="checkbox-container" id="rememberContainer">
                        <input type="checkbox" name="remember" id="rememberCheckbox" value="1" style="display: none;" checked>
                        <div class="custom-checkbox-circle" id="checkboxVisual">
                            <i class="bi bi-check-lg"></i>
                        </div>
                        <span class="checkbox-label">Ingat Saya</span>
                    </label>

                    <a href="{{ route('password.request') }}" class="forgot-link">Lupa Kata Sandi?</a>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="submit-btn" id="submitBtn">
                    Masuk
                </button>
            </form>

            <!-- Version Section -->
            <div class="version-section">
                Versi {{ config('app.version', '1.0.1') }}
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const identifierInput = document.getElementById('identifierInput');
            const passwordInput = document.getElementById('passwordInput');
            const submitBtn = document.getElementById('submitBtn');
            const togglePasswordBtn = document.getElementById('togglePasswordBtn');
            const togglePasswordIcon = document.getElementById('togglePasswordIcon');
            const rememberContainer = document.getElementById('rememberContainer');
            const rememberCheckbox = document.getElementById('rememberCheckbox');
            const checkboxVisual = document.getElementById('checkboxVisual');

            // Dynamic Button State
            function updateButtonState() {
                const hasId = identifierInput.value.trim().length > 0;
                const hasPass = passwordInput.value.length > 0;

                if (hasId && hasPass) {
                    submitBtn.classList.add('ready');
                } else {
                    submitBtn.classList.remove('ready');
                }
            }

            identifierInput.addEventListener('input', updateButtonState);
            passwordInput.addEventListener('input', updateButtonState);
            updateButtonState();

            // Toggle Password Visibility
            togglePasswordBtn.addEventListener('click', function () {
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    togglePasswordIcon.classList.remove('bi-eye-slash');
                    togglePasswordIcon.classList.add('bi-eye');
                } else {
                    passwordInput.type = 'password';
                    togglePasswordIcon.classList.remove('bi-eye');
                    togglePasswordIcon.classList.add('bi-eye-slash');
                }
            });

            // Remember Me Toggle
            rememberContainer.addEventListener('click', function (e) {
                e.preventDefault();
                rememberCheckbox.checked = !rememberCheckbox.checked;
                if (rememberCheckbox.checked) {
                    checkboxVisual.classList.remove('unchecked');
                    checkboxVisual.innerHTML = '<i class="bi bi-check-lg"></i>';
                } else {
                    checkboxVisual.classList.add('unchecked');
                    checkboxVisual.innerHTML = '';
                }
            });
        });
    </script>
</body>
</html>