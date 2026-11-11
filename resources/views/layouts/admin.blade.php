<!DOCTYPE html>
<html lang="id">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>@yield('title', 'Admin Dashboard') | Kirana People</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('layouts.partials.favicons')
    <!--begin::Fonts-->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css"
        crossorigin="anonymous">
    <!--begin::Third Party Plugin(OverlayScrollbars)-->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.3.0/styles/overlayscrollbars.min.css"
        crossorigin="anonymous">
    <!--begin::Third Party Plugin(Bootstrap Icons)-->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.min.css"
        crossorigin="anonymous">
    <!--begin::Required Plugin(AdminLTE)-->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0/dist/css/adminlte.min.css">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
    <style>
        :root {
            --bs-primary: #4f46e5;
            --bs-primary-rgb: 79, 70, 229;
            --bs-success: #10b981;
            --bs-success-rgb: 16, 185, 129;
            --bs-info: #0ea5e9;
            --bs-info-rgb: 14, 165, 233;
            --bs-warning: #f59e0b;
            --bs-warning-rgb: 245, 158, 11;
            --bs-danger: #ef4444;
            --bs-danger-rgb: 239, 68, 68;
            --bs-dark: #0f172a;
            --bs-dark-rgb: 15, 23, 42;
            --bs-body-bg: #f8fafc;
        }

        body {
            background-color: var(--bs-body-bg);
            background-attachment: fixed;
            transition: background-color 0.3s ease;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background-image: url('{{ asset('loginpage2.webp') }}');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            opacity: 0.2;
            z-index: -2;
            pointer-events: none;
        }

        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: linear-gradient(to top, rgba(255, 255, 255, 1) 0%, rgba(255, 255, 255, 0) 100%);
            z-index: -1;
            pointer-events: none;
            transition: background 0.3s ease;
        }

        /* Dark Mode Overrides */
        [data-bs-theme="dark"] body::after {
            background: linear-gradient(to top, rgba(33, 37, 41, 1) 0%, rgba(33, 37, 41, 0) 100%);
        }

        [data-bs-theme="dark"] .app-header {
            background: rgba(33, 37, 41, 0.8) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        [data-bs-theme="dark"] .app-footer {
            background: rgba(33, 37, 41, 0.8) !important;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        [data-bs-theme="dark"] .card {
            background: rgba(33, 37, 41, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow:
                0 8px 12px -3px rgba(0, 0, 0, 0.5),
                inset 0 1px 0 rgba(255, 255, 255, 0.1),
                inset 1px 0 0 rgba(255, 255, 255, 0.05);
        }

        [data-bs-theme="dark"] .card-header {
            background: rgba(255, 255, 255, 0.05);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        [data-bs-theme="dark"] .small-box {
            background: rgba(33, 37, 41, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow:
                0 10px 15px -3px rgba(0, 0, 0, 0.5),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
        }

        [data-bs-theme="dark"] .small-box::before {
            background: linear-gradient(to bottom, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0) 100%);
        }

        /* Pagination Dark Mode */
        [data-bs-theme="dark"] .page-link {
            background-color: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.1);
            color: #f8f9fa;
        }

        [data-bs-theme="dark"] .page-item.active .page-link {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
        }

        [data-bs-theme="dark"] .page-item.disabled .page-link {
            background-color: rgba(0, 0, 0, 0.2);
            border-color: rgba(255, 255, 255, 0.05);
            color: rgba(255, 255, 255, 0.5);
        }

        /* Text Overrides Dark Mode */
        [data-bs-theme="dark"] .app-footer,
        [data-bs-theme="dark"] .app-footer a {
            color: rgba(255, 255, 255, 0.8) !important;
        }

        /* Dropdown Dark Mode */
        [data-bs-theme="dark"] .dropdown-menu {
            background-color: rgba(33, 37, 41, 0.95) !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.5) !important;
        }

        [data-bs-theme="dark"] .dropdown-item {
            color: rgba(255, 255, 255, 0.85) !important;
        }

        [data-bs-theme="dark"] .dropdown-item:hover,
        [data-bs-theme="dark"] .dropdown-item:focus {
            background-color: rgba(255, 255, 255, 0.1) !important;
            color: #ffffff !important;
        }

        [data-bs-theme="dark"] .dropdown-header {
            color: rgba(255, 255, 255, 0.7) !important;
        }

        [data-bs-theme="dark"] .dropdown-divider {
            border-top-color: rgba(255, 255, 255, 0.1) !important;
        }

        /* Notification Dropdown Styling */
        .dropdown-menu-notifications {
            min-width: 320px;
            max-width: 380px;
            max-height: 480px;
            overflow-y: auto;
        }

        .notification-header {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .notification-item-unread {
            background-color: rgba(13, 110, 253, 0.05);
            color: #212529 !important;
            transition: background-color 0.15s ease;
        }

        .notification-item-unread:hover,
        .notification-item-unread:focus {
            background-color: rgba(13, 110, 253, 0.12) !important;
            color: #0d6efd !important;
        }

        .notification-item-read {
            background-color: transparent;
            color: #6c757d !important;
            transition: background-color 0.15s ease;
        }

        .notification-item-read:hover,
        .notification-item-read:focus {
            background-color: rgba(0, 0, 0, 0.04) !important;
        }

        /* Dark Mode Notification Overrides */
        [data-bs-theme="dark"] .dropdown-menu-notifications {
            background-color: #1e2227 !important;
            border-color: rgba(255, 255, 255, 0.12) !important;
        }

        [data-bs-theme="dark"] .notification-header {
            color: rgba(255, 255, 255, 0.7) !important;
            background-color: rgba(255, 255, 255, 0.03) !important;
        }

        [data-bs-theme="dark"] .notification-item-unread {
            background-color: rgba(255, 255, 255, 0.08) !important;
            color: #f1f5f9 !important;
        }

        [data-bs-theme="dark"] .notification-item-unread:hover,
        [data-bs-theme="dark"] .notification-item-unread:focus {
            background-color: rgba(255, 255, 255, 0.16) !important;
            color: #ffffff !important;
        }

        [data-bs-theme="dark"] .notification-item-read {
            background-color: transparent !important;
            color: rgba(255, 255, 255, 0.55) !important;
        }

        [data-bs-theme="dark"] .notification-item-read:hover,
        [data-bs-theme="dark"] .notification-item-read:focus {
            background-color: rgba(255, 255, 255, 0.08) !important;
            color: #ffffff !important;
        }

        [data-bs-theme="dark"] .notification-text {
            color: inherit !important;
        }

        /* Table Dark Mode Overrides */
        [data-bs-theme="dark"] .table thead th {
            background-color: rgba(0, 0, 0, 0.2) !important;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1) !important;
            color: rgba(255, 255, 255, 0.9) !important;
        }

        [data-bs-theme="dark"] .table tbody td {
            border-bottom-color: rgba(255, 255, 255, 0.05) !important;
        }

        .header-title {
            color: rgba(15, 23, 42, 0.85);
            text-shadow: 0 1px 2px rgba(255, 255, 255, 0.8);
        }

        [data-bs-theme="dark"] .header-title {
            color: rgba(255, 255, 255, 0.9) !important;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.8) !important;
        }

        /* Form Inputs Dark Mode Overrides */
        [data-bs-theme="dark"] .form-control,
        [data-bs-theme="dark"] .form-select,
        [data-bs-theme="dark"] .input-group-text {
            background-color: rgba(33, 37, 41, 0.8) !important;
            border-color: rgba(255, 255, 255, 0.2) !important;
            color: #f8f9fa !important;
        }

        [data-bs-theme="dark"] .form-control:focus,
        [data-bs-theme="dark"] .form-select:focus {
            background-color: rgba(33, 37, 41, 1) !important;
            color: #f8f9fa !important;
            border-color: var(--bs-primary) !important;
        }

        [data-bs-theme="dark"] .btn-default {
            background-color: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
            color: #f8f9fa;
        }

        [data-bs-theme="dark"] .btn-default:hover {
            background-color: rgba(255, 255, 255, 0.2);
            color: #fff;
        }

        /* Modal Dark Mode Overrides */
        [data-bs-theme="dark"] .modal-content {
            background-color: var(--bs-dark);
            border-color: rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.85);
        }

        [data-bs-theme="dark"] .modal-header {
            border-bottom-color: rgba(255, 255, 255, 0.1);
        }

        [data-bs-theme="dark"] .modal-footer {
            border-top-color: rgba(255, 255, 255, 0.1);
        }

        [data-bs-theme="dark"] .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }

        [data-bs-theme="dark"] .modal-title {
            color: rgba(255, 255, 255, 0.9);
        }

        .app-header {
            position: sticky;
            top: 0;
            z-index: 1030;
            background: rgba(255, 255, 255, 0.8) !important;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.15);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(0, 0, 0, 0.15);
            box-shadow:
                0 8px 12px -3px rgba(0, 0, 0, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.8),
                inset 1px 0 0 rgba(255, 255, 255, 0.4);
            border-radius: 1.25rem;
            overflow: hidden;
        }

        .card-header {
            border-bottom: 1px solid rgba(0, 0, 0, 0.15);
            background: rgba(255, 255, 255, 0.4);
            font-weight: 600;
        }

        .small-box {
            border-radius: 1.25rem;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(0, 0, 0, 0.15);
            box-shadow:
                0 10px 15px -3px rgba(0, 0, 0, 0.15),
                inset 0 1px 0 rgba(255, 255, 255, 0.5);
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            position: relative;
            overflow: hidden;
        }

        /* Skeuomorphic glossy overlay for small-box */
        .small-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 40%;
            background: linear-gradient(to bottom, rgba(255, 255, 255, 0.4) 0%, rgba(255, 255, 255, 0) 100%);
            pointer-events: none;
        }

        .small-box:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow:
                0 20px 25px -5px rgba(0, 0, 0, 0.2),
                inset 0 1px 0 rgba(255, 255, 255, 0.8);
        }

        .btn {
            border-radius: 0.375rem;
            font-weight: 500;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .table {
            --bs-table-bg: transparent;
        }

        .table> :not(caption)>*>* {
            padding: 1rem;
            border-bottom-color: rgba(0, 0, 0, 0.15);
        }

        .table-hover tbody tr {
            transition: all 0.2s ease;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.3) !important;
            transform: scale(1.01);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .app-footer,
        .sidebar-footer {
            height: 65px;
            display: flex;
            align-items: center;
            padding: 0 1.25rem;
        }

        .app-footer {
            position: sticky;
            bottom: 0;
            z-index: 1030;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-top: 1px solid rgba(0, 0, 0, 0.15);
            justify-content: space-between;
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes subtleFloat {
            0% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-3px);
            }

            100% {
                transform: translateY(0);
            }
        }

        .app-content .row {
            animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            opacity: 0;
        }

        /* Stagger the rows loading */
        .app-content .row:nth-child(1) {
            animation-delay: 0.1s;
        }

        .app-content .row:nth-child(2) {
            animation-delay: 0.2s;
        }

        .app-content .row:nth-child(3) {
            animation-delay: 0.3s;
        }

        .app-content .row:nth-child(4) {
            animation-delay: 0.4s;
        }

        .small-box-icon {
            animation: subtleFloat 3s ease-in-out infinite;
        }
    </style>
    @stack('styles')
</head>

<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <!--begin::App Wrapper-->
    <div class="app-wrapper">

        <!--begin::Header-->
        <nav class="app-header navbar navbar-expand bg-body">
            <div class="container-fluid">
                <!--begin::Start Navbar Links-->
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                            <i class="bi bi-list"></i>
                        </a>
                    </li>
                    <li class="nav-item d-none d-md-flex align-items-center ms-2">
                        <span class="fw-bold fs-5 header-title d-none d-sm-inline">
                            Kirana People - Garment HRIS
                        </span>
                    </li>
                </ul>
                <!--end::Start Navbar Links-->

                <!--begin::End Navbar Links-->
                <ul class="navbar-nav ms-auto">
                    <!--begin::Notifications Dropdown Menu-->
                    @if(auth()->user()->hasRole(['super-admin', 'HR']))
                        <li class="nav-item dropdown">
                            <a class="nav-link" data-bs-toggle="dropdown" href="#">
                                <i class="bi bi-bell"></i>
                                @if(auth()->user()->unreadNotifications->count() > 0)
                                    <span
                                        class="navbar-badge badge text-bg-warning">{{ auth()->user()->unreadNotifications->count() }}</span>
                                @endif
                            </a>
                            <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end dropdown-menu-notifications">
                                <div
                                    class="dropdown-header notification-header d-flex justify-content-between align-items-center py-2 px-3">
                                    <span class="fw-bold">Notifikasi ({{ auth()->user()->unreadNotifications->count() }}
                                        Baru)</span>
                                    @if(auth()->user()->notifications->count() > 0)
                                        <form action="{{ route('admin.notifications.clear', [], false) }}" method="POST"
                                            class="d-inline m-0 p-0" id="clearNotificationsForm">
                                            @csrf
                                            <button type="submit"
                                                class="btn btn-sm btn-link text-decoration-none p-0 m-0 notif-clear-btn"
                                                style="font-size: 0.8rem; color: #ef4444;">
                                                <i class="bi bi-trash"></i> Bersihkan
                                            </button>
                                        </form>
                                    @endif
                                </div>
                                <div class="dropdown-divider m-0"></div>

                                {{-- UNREAD NOTIFICATIONS --}}
                                @forelse(auth()->user()->unreadNotifications->take(5) as $notification)
                                    @php
                                        $notifMessage = $notification->data['message'] ?? 'Notifikasi baru';
                                        $employeeName = null;
                                        if (isset($notification->data['employee_id'])) {
                                            $employeeName = \App\Models\User::find($notification->data['employee_id'])?->name;
                                        }
                                        if (!$employeeName) {
                                            $parts = explode(' terlambat ', $notifMessage);
                                            $employeeName = $parts[0] ?? null;
                                        }
                                        $targetUrl = route('admin.attendances.index', ['search' => trim($employeeName ?? '')], false);
                                    @endphp
                                    <a href="{{ route('admin.notifications.read', ['id' => $notification->id, 'redirect_to' => $targetUrl], false) }}"
                                        class="dropdown-item notification-item-unread d-flex justify-content-between align-items-start py-2 px-3">
                                        <div class="pe-2" style="white-space: normal;">
                                            <i class="bi bi-circle-fill text-primary me-1" style="font-size: 8px;"></i>
                                            <i class="bi bi-exclamation-circle text-warning me-1"></i>
                                            <span class="fw-semibold notification-text">{{ $notifMessage }}</span>
                                        </div>
                                        <span
                                            class="text-secondary fs-7 flex-shrink-0 mt-1 ms-2">{{ $notification->created_at->diffForHumans(null, true, true) }}</span>
                                    </a>
                                    <div class="dropdown-divider m-0"></div>
                                @empty
                                @endforelse

                                {{-- READ NOTIFICATIONS (ARCHIVE) - Last 1 day --}}
                                @php
                                    $readNotifications = auth()->user()->readNotifications()->where('read_at', '>=', now()->subDay())->take(5)->get();
                                @endphp

                                @if($readNotifications->count() > 0)
                                    <div class="dropdown-header notification-header text-start fw-bold py-2 px-3">Riwayat (1
                                        Hari Terakhir)</div>
                                    <div class="dropdown-divider m-0"></div>
                                    @foreach($readNotifications as $notification)
                                        @php
                                            $notifMessage = $notification->data['message'] ?? 'Notifikasi baru';
                                            $employeeName = null;
                                            if (isset($notification->data['employee_id'])) {
                                                $employeeName = \App\Models\User::find($notification->data['employee_id'])?->name;
                                            }
                                            if (!$employeeName) {
                                                $parts = explode(' terlambat ', $notifMessage);
                                                $employeeName = $parts[0] ?? null;
                                            }
                                            $targetUrl = route('admin.attendances.index', ['search' => trim($employeeName ?? '')], false);
                                        @endphp
                                        <a href="{{ $targetUrl }}"
                                            class="dropdown-item notification-item-read d-flex justify-content-between align-items-start py-2 px-3">
                                            <div class="pe-2" style="white-space: normal;">
                                                <i class="bi bi-check2-circle text-success me-1"></i>
                                                <span class="notification-text">{{ $notifMessage }}</span>
                                            </div>
                                            <span
                                                class="text-secondary fs-7 flex-shrink-0 mt-1 ms-2">{{ $notification->created_at->diffForHumans(null, true, true) }}</span>
                                        </a>
                                        <div class="dropdown-divider m-0"></div>
                                    @endforeach
                                @endif

                                @if(auth()->user()->notifications->count() == 0)
                                    <div class="dropdown-item text-center text-muted py-3">Belum ada notifikasi</div>
                                    <div class="dropdown-divider m-0"></div>
                                @endif
                            </div>
                        </li>
                    @endif
                    <!--end::Notifications Dropdown Menu-->
                    <!--begin::Dark Mode Toggle-->
                    <li class="nav-item">
                        <a class="nav-link" href="#" id="darkModeToggle" role="button">
                            <i class="bi bi-moon-stars" id="darkModeIcon"></i>
                        </a>
                    </li>
                    <!--end::Dark Mode Toggle-->

                    <!--begin::User Menu Dropdown-->
                    <li class="nav-item dropdown user-menu">
                        <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">
                            <img src="{{ auth()->user()->avatar_url }}" class="user-image rounded-circle shadow"
                                style="object-fit: cover; width: 2.1rem; height: 2.1rem;" alt="User Image">
                            <span class="d-none d-md-inline">{{ auth()->user()->name }}</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end shadow-sm"
                            style="border-radius: 0.5rem; overflow: hidden; border: 1px solid rgba(0,0,0,0.1);">
                            <li class="user-header position-relative border-bottom-0"
                                style="background: url('{{ asset('loginpage2.webp') }}') center/cover no-repeat !important; color: white; overflow: hidden;">
                                <div
                                    style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(8px); z-index: 0;">
                                </div>
                                <div style="position: relative; z-index: 1;">
                                    <img src="{{ auth()->user()->avatar_url }}"
                                        class="rounded-circle shadow-sm border border-2 border-white mb-2"
                                        style="width: 90px; height: 90px; object-fit: cover;" alt="User Image">
                                    <p class="mb-0 fw-bold fs-5">
                                        {{ auth()->user()->name }}
                                    </p>
                                    <small class="d-block mt-2 opacity-75">
                                        <i class="bi bi-person-badge"></i> {{ auth()->user()->employee_id ?? 'N/A' }}
                                        &bull; {{ auth()->user()->email }}
                                    </small>
                                </div>
                            </li>
                            <li class="user-footer bg-light p-3">
                                <div class="d-flex justify-content-between mb-2 gap-2">
                                    <a href="{{ route('admin.profile.index') }}"
                                        class="btn btn-outline-secondary btn-sm px-3 fw-bold flex-fill">
                                        <i class="bi bi-person-circle me-1"></i> Profil Saya
                                    </a>
                                    <a href="{{ route('role.selection') }}"
                                        class="btn btn-outline-primary btn-sm px-3 fw-bold flex-fill">
                                        <i class="bi bi-arrow-left-right me-1"></i> Ganti Portal
                                    </a>
                                </div>
                                <form method="POST" action="{{ route('logout') }}" class="d-block w-100">
                                    @csrf
                                    <button type="submit" class="btn btn-danger btn-sm fw-bold w-100">
                                        <i class="bi bi-box-arrow-right me-1"></i> Logout
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                    <!--end::User Menu Dropdown-->
                </ul>
                <!--end::End Navbar Links-->
            </div>
        </nav>
        <!--end::Header-->

        <!--begin::Sidebar-->
        @include('layouts.partials.sidebar')
        <!--end::Sidebar-->

        <!--begin::App Main-->
        <main class="app-main">
            <!--begin::App Content Header-->
            <div class="app-content-header">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-sm-6">
                            <h3 class="mb-0">@yield('page_title', 'Dashboard')</h3>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-end">
                                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i
                                            class="bi bi-house-door-fill"></i> Home</a></li>
                                @foreach(request()->segments() as $segment)
                                    @if(strtolower($segment) !== 'admin')
                                        <li class="breadcrumb-item {{ $loop->last ? 'active' : '' }}" {{ $loop->last ? 'aria-current="page"' : '' }}>
                                            {{ ucfirst(str_replace('-', ' ', $segment)) }}
                                        </li>
                                    @endif
                                @endforeach
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
            <!--end::App Content Header-->

            <!--begin::App Content-->
            <div class="app-content">
                <div class="container-fluid">
                    @yield('content')
                </div>
            </div>
            <!--end::App Content-->
        </main>
        <!--end::App Main-->

        <!--begin::Footer-->
        <footer class="app-footer">
            <div>
                <strong>Copyright &copy; {{ date('Y') }} <a href="https://kiranadesainindonesia.com"
                        target="_blank">Kirana Group</a>.</strong> All rights reserved.
            </div>
            <div class="d-none d-sm-inline">
                <a href="javascript:void(0)" onclick="showEasterEgg()" class="text-decoration-none link-secondary"
                    style="cursor: pointer;">Kirana People</a>
            </div>
        </footer>
        <!--end::Footer-->
    </div>
    <!--end::App Wrapper-->

    <!--begin::Third Party Plugin(OverlayScrollbars)-->
    <script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.3.0/browser/overlayscrollbars.browser.es6.min.js"
        crossorigin="anonymous"></script>
    @include('layouts.partials.lightbox')

    <!--begin::Required Plugin(popperjs for Bootstrap 5)-->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
        crossorigin="anonymous"></script>
    <!--begin::Required Plugin(Bootstrap 5)-->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js"
        crossorigin="anonymous"></script>
    <!--begin::Required Plugin(AdminLTE)-->
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0/dist/js/adminlte.min.js"></script>
    <!--begin::SweetAlert2 for Toasts-->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });

        @if(session('success'))
            Toast.fire({
                icon: 'success',
                title: '{{ session('success') }}'
            });
        @endif

        @if(session('error'))
            Toast.fire({
                icon: 'error',
                title: '{{ session('error') }}'
            });
        @endif

        @if($errors->any())
            Toast.fire({
                icon: 'error',
                title: 'Terdapat kesalahan pada input Anda!'
            });
        @endif
    </script>

    <!-- Dark Mode Script -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const htmlTag = document.documentElement;
            const darkModeToggle = document.getElementById('darkModeToggle');
            const darkModeIcon = document.getElementById('darkModeIcon');

            // Check local storage for preference
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                htmlTag.setAttribute('data-bs-theme', 'dark');
                if (darkModeIcon) {
                    darkModeIcon.classList.remove('bi-moon-stars');
                    darkModeIcon.classList.add('bi-sun');
                }
            }

            // Toggle function
            if (darkModeToggle) {
                darkModeToggle.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (htmlTag.getAttribute('data-bs-theme') === 'dark') {
                        htmlTag.removeAttribute('data-bs-theme');
                        localStorage.setItem('theme', 'light');
                        darkModeIcon.classList.remove('bi-sun');
                        darkModeIcon.classList.add('bi-moon-stars');
                    } else {
                        htmlTag.setAttribute('data-bs-theme', 'dark');
                        localStorage.setItem('theme', 'dark');
                        darkModeIcon.classList.remove('bi-moon-stars');
                        darkModeIcon.classList.add('bi-sun');
                    }
                });
            }

            // Clear notifications click handler
            document.addEventListener('click', (e) => {
                const clearBtn = e.target.closest('.notif-clear-btn');
                if (clearBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    const form = clearBtn.closest('form');
                    if (form) {
                        form.submit();
                    }
                }
            });
        });

        function showEasterEgg() {
            Swal.fire({
                title: 'Kirana People',
                html: '<div class="text-start"><p>Sistem Informasi Manajemen Sumber Daya Manusia terpadu dari <b>Kirana People</b>.</p><p>Dikembangkan dengan ❤️ untuk <b>Kirana Group</b>.</p><p class="text-muted small mt-3 mb-0">Versi {{ config('app.version', '1.0.1') }}</p></div>',
                icon: 'info',
                confirmButtonText: 'Keren!',
                confirmButtonColor: '#3085d6',
                backdrop: `
                    rgba(0,0,123,0.4)
                    url("https://sweetalert2.github.io/images/nyan-cat.gif")
                    left top
                    no-repeat
                `
            });
        }
    </script>
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>

    @stack('scripts')
</body>

</html>