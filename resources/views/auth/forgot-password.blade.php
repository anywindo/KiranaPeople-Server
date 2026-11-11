<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Lupa Kata Sandi? | Kirana People</title>
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
            line-height: 1.4;
            max-width: 340px;
            margin: 0 auto;
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
            margin-bottom: 26px;
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
            margin-bottom: 20px;
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

        /* Back link */
        .back-link-wrapper {
            text-align: center;
            width: 100%;
        }

        .back-link {
            font-size: 14px;
            color: var(--tn-text-body);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: color 0.15s ease;
        }

        .back-link:hover {
            color: var(--tn-accent);
            text-decoration: underline;
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
                <h1 class="login-title">Lupa Kata Sandi?</h1>
                <p class="login-subtitle">Masukkan alamat email Anda untuk menerima tautan dan verifikasi reset kata sandi</p>
            </div>

            <!-- Status / Error Alerts -->
            @if (session('status'))
                <div class="alert-banner alert-success">
                    <i class="bi bi-check-circle-fill"></i>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if (session('error'))
                <div class="alert-banner alert-error">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert-banner alert-error">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <!-- Form -->
            <form action="{{ route('password.email') }}" method="POST" class="login-form" id="forgotForm">
                @csrf

                <!-- Email Field -->
                <div class="input-group-underline">
                    <input 
                        type="email" 
                        name="email" 
                        id="emailInput"
                        class="underline-input" 
                        placeholder="Alamat Email Terdaftar" 
                        value="{{ old('email') }}" 
                        required 
                        autocomplete="email"
                        autocapitalize="none"
                        autofocus
                    >
                    <div class="underline-bar"></div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="submit-btn" id="submitBtn">
                    Kirim Tautan Reset
                </button>

                <!-- Back to Login -->
                <div class="back-link-wrapper">
                    <a href="{{ route('login') }}" class="back-link">
                        <i class="bi bi-arrow-left"></i>
                        Kembali ke Halaman Masuk
                    </a>
                </div>
            </form>

            <!-- Version Section -->
            <div class="version-section">
                Versi {{ config('app.version', '1.0.1') }}
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const emailInput = document.getElementById('emailInput');
            const submitBtn = document.getElementById('submitBtn');

            function updateButtonState() {
                const hasEmail = emailInput.value.trim().length > 0;
                if (hasEmail) {
                    submitBtn.classList.add('ready');
                } else {
                    submitBtn.classList.remove('ready');
                }
            }

            emailInput.addEventListener('input', updateButtonState);
            updateButtonState();
        });
    </script>
</body>
</html>
