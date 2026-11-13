<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Presensi Web Diperbarui | Kirana People</title>
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
            --tn-text-primary: #1C1C1E;
            --tn-text-secondary: #8E8E93;
            --tn-text-body: #333333;
            --tn-divider: #E5E5EA;
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
            margin-bottom: 32px;
            width: 100%;
        }

        .login-title {
            font-size: 23px;
            font-weight: 700;
            color: var(--tn-text-primary);
            letter-spacing: -0.4px;
            margin-bottom: 10px;
        }

        .login-subtitle {
            font-size: 14.5px;
            font-weight: 400;
            color: var(--tn-text-secondary);
            line-height: 1.45;
        }

        /* Actions */
        .actions-group {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

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
            gap: 8px;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.2s ease;
            background-color: var(--tn-accent);
            color: #FFFFFF;
            box-shadow: 0 8px 18px var(--tn-accent-shadow);
            text-decoration: none;
        }

        .submit-btn:hover {
            background-color: var(--tn-accent-hover);
            transform: translateY(-1px);
        }

        .submit-btn:active {
            transform: scale(0.985);
        }

        .admin-btn {
            width: 100%;
            height: 48px;
            border-radius: 12px;
            border: none;
            background-color: #1C1C1E;
            color: #FFFFFF;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            transition: background-color 0.15s ease, transform 0.08s ease;
        }

        .admin-btn:hover {
            background-color: #000000;
        }

        .admin-btn:active {
            transform: scale(0.985);
        }

        .secondary-btn {
            width: 100%;
            height: 48px;
            border-radius: 12px;
            border: 1px solid var(--tn-divider);
            background-color: #FFFFFF;
            color: var(--tn-text-primary);
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            transition: background-color 0.15s ease, border-color 0.15s ease;
        }

        .secondary-btn:hover {
            background-color: #F9F9F9;
            border-color: #D1D1D6;
        }

        .secondary-btn:active {
            transform: scale(0.985);
        }

        /* Footer */
        .footer-copyright {
            margin-top: 36px;
            font-size: 12.5px;
            color: var(--tn-text-secondary);
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="main-content">
            <!-- App Logo -->
            <div class="logo-container">
                <img src="{{ asset('logoKiranaSquare.jpeg') }}" alt="Kirana People" class="app-logo">
            </div>

            <!-- Header Section -->
            <div class="header-section">
                <h1 class="login-title">Presensi Web Diperbarui</h1>
                <p class="login-subtitle">
                    Fitur presensi melalui peramban web saat ini sedang dalam peningkatan sistem. Silakan gunakan <strong style="color: var(--tn-text-primary); font-weight: 600;">Aplikasi Mobile Kirana</strong> untuk melakukan presensi.
                </p>
            </div>

            <!-- Actions -->
            <div class="actions-group">
                @if(auth()->check() && auth()->user()->isAdmin())
                <a href="{{ route('admin.dashboard') }}" class="admin-btn">
                    <i class="bi bi-speedometer2" style="color: var(--tn-accent);"></i>
                    <span>Buka Admin Dashboard</span>
                </a>
                @endif

                <button type="button" onclick="window.location.reload()" class="submit-btn">
                    <i class="bi bi-arrow-clockwise"></i>
                    <span>Muat Ulang Halaman</span>
                </button>

                @auth
                <form action="{{ route('logout') }}" method="POST" style="width: 100%;">
                    @csrf
                    <button type="submit" class="secondary-btn">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Keluar Akun</span>
                    </button>
                </form>
                @else
                <a href="{{ route('login') }}" class="secondary-btn">
                    <i class="bi bi-arrow-left"></i>
                    <span>Kembali ke Halaman Masuk</span>
                </a>
                @endauth
            </div>

            <!-- Footer -->
            <div class="footer-copyright">
                &copy; {{ date('Y') }} Kirana People. Semua hak cipta dilindungi.
            </div>
        </div>
    </div>
</body>
</html>
