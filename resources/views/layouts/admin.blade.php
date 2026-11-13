<!DOCTYPE html>
<html lang="id">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>@yield('title', 'Admin Dashboard') | Kirana People</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('layouts.partials.favicons')
    <script>
        (function () {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-bs-theme', 'dark');
            }
        })();
    </script>
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
            /* Declare supported color scheme for UA controls */
            color-scheme: light dark;

            /* Brand & Accent Tokens */
            --bs-primary: #4f46e5;
            --bs-primary-rgb: 79, 70, 229;
            --kirana-accent: #F775BA;
            --kirana-accent-rgb: 247, 117, 186;

            /* WCAG Compliant Status Palette */
            --bs-success: #10b981;
            --bs-success-rgb: 16, 185, 129;
            --bs-info: #0284c7;
            --bs-info-rgb: 2, 132, 199;
            --bs-warning: #d97706;
            --bs-warning-rgb: 217, 119, 6;
            --bs-danger: #e11d48;
            --bs-danger-rgb: 225, 29, 72;
            --bs-dark: #0f172a;
            --bs-dark-rgb: 15, 23, 42;

            /* Light Surfaces (Slate Tone) */
            --bs-body-bg: #f8fafc;
            --bs-body-color: #0f172a;
            --header-bg: rgba(255, 255, 255, 0.85);
            --header-border-color: #e2e8f0;
            --kirana-sidebar-bg: var(--header-bg);
            --kirana-sidebar-border: var(--header-border-color);
            --card-border-color: #e2e8f0;
        }

        /* Match interactive form controls and scrollbars to modern standard */
        html {
            accent-color: var(--bs-primary);
            scrollbar-color: light-dark(#cbd5e1, #475569) transparent;
            scrollbar-width: thin;
        }

        body {
            background-color: var(--bs-body-bg);
            color: var(--bs-body-color);
            transition: background-color 0.2s ease, color 0.2s ease;
        }

        /* Surface Components - Light Mode Default */
        .card {
            border: 1px solid var(--card-border-color);
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05), 0 1px 2px -1px rgb(0 0 0 / 0.05);
            border-radius: 0.75rem;
            background-color: #ffffff;
            transition: background-color 0.2s ease, border-color 0.2s ease;
        }

        .card-header {
            border-bottom: 1px solid var(--card-border-color);
            background-color: transparent;
            font-weight: 600;
        }

        .card-footer {
            border-top: 1px solid var(--card-border-color);
            background-color: #f8fafc;
        }

        .card-header::after,
        .card-footer::after {
            display: none !important;
        }

        /* Small Box Metric Cards */
        .small-box {
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        .small-box:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.15);
        }

        /* Buttons */
        .btn {
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.15s ease;
        }

        /* Tables & Sticky Headers */
        .table {
            --bs-table-bg: transparent;
        }

        .table> :not(caption)>*>* {
            padding: 0.75rem 1rem;
            vertical-align: middle;
        }

        .table-hover tbody tr {
            transition: background-color 0.15s ease;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.03);
        }

        thead.sticky-top th {
            position: sticky;
            top: 0;
            z-index: 2;
            background-color: #f1f5f9 !important;
            border-bottom: 1px solid var(--card-border-color) !important;
        }

        /* App Header & Footer Bar */
        .app-header {
            position: sticky;
            top: 0;
            z-index: 1030;
            height: 3.5rem !important;
            min-height: 3.5rem !important;
            max-height: 3.5rem !important;
            box-sizing: border-box !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            background: var(--header-bg) !important;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--header-border-color) !important;
            box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.05);
            transition: background 0.2s ease, border-color 0.2s ease;
        }

        .app-header>.container-fluid {
            height: 100%;
            display: flex;
            align-items: center;
        }

        .app-header .navbar-nav {
            display: flex !important;
            align-items: center !important;
        }

        .app-header .navbar-nav .nav-item {
            display: flex !important;
            align-items: center !important;
        }

        .app-header .navbar-nav .nav-link {
            display: flex !important;
            align-items: center !important;
        }

        /* User Menu Profile Dropdown Alignment & Clean Shadow */
        .app-header .user-menu .nav-link,
        .navbar-nav>.user-menu>.nav-link {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            height: 100%;
        }

        .app-header .user-menu .user-image,
        .navbar-nav>.user-menu .user-image {
            float: none !important;
            margin: 0 !important;
            margin-top: 0 !important;
            margin-right: 0 !important;
            line-height: 1 !important;
            width: 2.1rem !important;
            height: 2.1rem !important;
            object-fit: cover !important;
            border-radius: 50% !important;
            box-shadow: none !important;
            filter: none !important;
            display: inline-block !important;
            vertical-align: middle !important;
        }

        .app-header .user-menu span {
            display: inline-flex !important;
            align-items: center !important;
            line-height: 1 !important;
        }

        /* Profile Card & Header Dropdowns Spacing */
        .app-header .user-menu {
            --bs-dropdown-spacer: 0.75rem;
        }

        .app-header .user-menu .dropdown-menu {
            margin-top: 0.75rem !important;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1) !important;
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
            background: var(--header-bg) !important;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-top: 1px solid var(--header-border-color) !important;
            justify-content: space-between;
            transition: background 0.2s ease, border-color 0.2s ease;
        }

        .header-title {
            color: #0f172a;
            font-weight: 600;
        }

        /* Sidebar Styling - Light Mode Default (Matches Header Bar) */
        .app-sidebar {
            background: var(--kirana-sidebar-bg) !important;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-right: 1px solid var(--kirana-sidebar-border) !important;
            box-shadow: none !important;
            transition: margin-left 0.3s cubic-bezier(0.25, 0.8, 0.25, 1),
                        margin-right 0.3s cubic-bezier(0.25, 0.8, 0.25, 1),
                        min-width 0.3s cubic-bezier(0.25, 0.8, 0.25, 1),
                        max-width 0.3s cubic-bezier(0.25, 0.8, 0.25, 1),
                        width 0.3s cubic-bezier(0.25, 0.8, 0.25, 1),
                        transform 0.3s cubic-bezier(0.25, 0.8, 0.25, 1),
                        background 0.2s ease,
                        border-color 0.2s ease !important;
        }

        .app-sidebar .sidebar-brand {
            border-bottom: 1px solid var(--kirana-sidebar-border) !important;
            height: 3.5rem !important;
            min-height: 3.5rem !important;
            max-height: 3.5rem !important;
            box-sizing: border-box !important;
            display: flex;
            align-items: center;
            justify-content: center !important;
            padding: 0 1rem;
            text-align: center;
        }

        .app-sidebar .sidebar-brand .brand-image {
            float: none !important;
            margin: 0 0.5rem 0 0 !important;
            max-height: 32px;
            vertical-align: middle;
            box-shadow: none !important;
        }

        .app-sidebar .sidebar-brand .brand-image.logo-light {
            mix-blend-mode: multiply;
            display: inline-block !important;
        }

        .app-sidebar .sidebar-brand .brand-image.logo-dark {
            display: none !important;
        }

        .app-sidebar .brand-link {
            color: #0f172a !important;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center !important;
            width: 100%;
            transition: color 0.15s ease;
        }

        .app-sidebar .sidebar-wrapper {
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        .app-sidebar .sidebar-wrapper nav {
            width: 100% !important;
            margin-top: 0 !important;
        }

        .app-sidebar .sidebar-menu {
            padding-left: 0 !important;
            padding-right: 0 !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
            width: 100% !important;
        }

        .app-sidebar .sidebar-menu .nav-item {
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .app-sidebar .sidebar-menu .nav-header {
            color: #64748b !important;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            padding: 1.25rem 1.25rem 0.35rem 1.25rem !important;
            width: 100% !important;
        }

        .app-sidebar .sidebar-menu .nav-link {
            color: #334155 !important;
            border-radius: 0 !important;
            margin: 0 !important;
            width: 100% !important;
            padding: 0.65rem 1.25rem !important;
            font-weight: 500;
            display: flex;
            align-items: center;
            transition: all 0.15s ease;
        }

        .app-sidebar .sidebar-menu .nav-link .nav-icon {
            color: #64748b;
            margin-right: 0.75rem;
            font-size: 1.1rem;
            min-width: 1.5rem;
            text-align: center;
            transition: color 0.15s ease;
        }

        .app-sidebar .sidebar-menu .nav-link:hover {
            background-color: rgba(247, 117, 186, 0.12) !important;
            color: #d84594 !important;
            border-radius: 0 !important;
            margin: 0 !important;
            width: 100% !important;
        }

        .app-sidebar .sidebar-menu .nav-link:hover .nav-icon {
            color: #d84594 !important;
        }

        .app-sidebar .sidebar-menu .nav-link.active {
            background-color: var(--kirana-accent, #F775BA) !important;
            color: #ffffff !important;
            border-radius: 0 !important;
            margin: 0 !important;
            width: 100% !important;
            box-shadow: none !important;
        }

        .app-sidebar .sidebar-menu .nav-link.active .nav-icon {
            color: #ffffff !important;
        }

        .sidebar-footer {
            background: var(--kirana-sidebar-bg) !important;
            border-top: 1px solid var(--kirana-sidebar-border) !important;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }

        /* Notification Dropdown Styling */
        .dropdown-menu-notifications {
            min-width: 320px;
            max-width: 380px;
            max-height: 480px;
            overflow-y: auto;
            margin-top: 0.75rem !important;
        }

        .notification-header {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .notification-item-unread {
            background-color: rgba(79, 70, 229, 0.06);
            color: #1e293b !important;
            transition: background-color 0.15s ease;
        }

        .notification-item-unread:hover,
        .notification-item-unread:focus {
            background-color: rgba(79, 70, 229, 0.12) !important;
            color: var(--bs-primary) !important;
        }

        .notification-item-read {
            background-color: transparent;
            color: #64748b !important;
            transition: background-color 0.15s ease;
        }

        .notification-item-read:hover,
        .notification-item-read:focus {
            background-color: rgba(0, 0, 0, 0.04) !important;
        }

        /* ==========================================================================
           Dark Mode Comprehensive Palette & Overrides (Slate & Indigo Identity)
           ========================================================================== */
        [data-bs-theme="dark"] {
            --bs-body-bg: #0b1120;
            --bs-body-color: #f8fafc;
            --bs-tertiary-bg: #0f172a;
            --bs-secondary-bg: #1e293b;
            --bs-border-color: #334155;
            --bs-border-color-translucent: rgba(255, 255, 255, 0.1);
            --kirana-sidebar-bg: #020617;
            --card-border-color: #334155;
            --header-bg: rgba(15, 23, 42, 0.95);
            --header-border-color: #334155;
        }

        /* Dark Mode Header & Footer */
        [data-bs-theme="dark"] .app-header {
            background: rgba(15, 23, 42, 0.95) !important;
            border-bottom: 1px solid #334155 !important;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.3) !important;
        }

        [data-bs-theme="dark"] .app-header .header-title {
            color: #f8fafc !important;
            text-shadow: none !important;
        }

        [data-bs-theme="dark"] .app-header .nav-link {
            color: #cbd5e1 !important;
        }

        [data-bs-theme="dark"] .app-header .nav-link:hover,
        [data-bs-theme="dark"] .app-header .nav-link:focus {
            color: #ffffff !important;
        }

        [data-bs-theme="dark"] .app-header .nav-link .bi {
            color: #cbd5e1 !important;
        }

        [data-bs-theme="dark"] .app-header .nav-link:hover .bi {
            color: #ffffff !important;
        }

        [data-bs-theme="dark"] .app-footer,
        [data-bs-theme="dark"] .app-footer a {
            color: #94a3b8 !important;
            border-top-color: #334155 !important;
        }

        /* Dark Mode Sidebar */
        [data-bs-theme="dark"] .app-sidebar {
            background: #020617 !important;
            border-right: 1px solid #334155 !important;
            box-shadow: none !important;
        }

        [data-bs-theme="dark"] .app-sidebar .sidebar-brand {
            border-bottom: 1px solid #334155 !important;
        }

        [data-bs-theme="dark"] .app-sidebar .sidebar-brand .brand-image {
            mix-blend-mode: normal;
            box-shadow: none !important;
        }

        [data-bs-theme="dark"] .app-sidebar .sidebar-brand .brand-image.logo-light {
            display: none !important;
        }

        [data-bs-theme="dark"] .app-sidebar .sidebar-brand .brand-image.logo-dark {
            display: inline-block !important;
        }

        [data-bs-theme="dark"] .app-sidebar .brand-link {
            color: #f8fafc !important;
        }

        [data-bs-theme="dark"] .app-sidebar .sidebar-wrapper {
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        [data-bs-theme="dark"] .app-sidebar .sidebar-menu .nav-header {
            color: #64748b !important;
        }

        [data-bs-theme="dark"] .app-sidebar .sidebar-menu .nav-link {
            color: #cbd5e1 !important;
            border-radius: 0 !important;
            margin: 0 !important;
            width: 100% !important;
        }

        [data-bs-theme="dark"] .app-sidebar .sidebar-menu .nav-link .nav-icon {
            color: #94a3b8 !important;
        }

        [data-bs-theme="dark"] .app-sidebar .sidebar-menu .nav-link:hover {
            background-color: rgba(247, 117, 186, 0.18) !important;
            color: #F775BA !important;
            border-radius: 0 !important;
            margin: 0 !important;
            width: 100% !important;
        }

        [data-bs-theme="dark"] .app-sidebar .sidebar-menu .nav-link:hover .nav-icon {
            color: #F775BA !important;
        }

        [data-bs-theme="dark"] .app-sidebar .sidebar-menu .nav-link.active {
            background-color: var(--kirana-accent, #F775BA) !important;
            color: #ffffff !important;
            border-radius: 0 !important;
            margin: 0 !important;
            width: 100% !important;
            box-shadow: none !important;
        }

        [data-bs-theme="dark"] .app-sidebar .sidebar-menu .nav-link.active .nav-icon {
            color: #ffffff !important;
        }

        [data-bs-theme="dark"] .sidebar-footer {
            background: #020617 !important;
            border-top: 1px solid #334155 !important;
        }

        /* Dark Mode Surfaces & Cards */
        [data-bs-theme="dark"] .card {
            background-color: #1e293b !important;
            border-color: #334155 !important;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.3), 0 2px 4px -2px rgb(0 0 0 / 0.3) !important;
        }

        [data-bs-theme="dark"] .card-header {
            background-color: rgba(255, 255, 255, 0.02) !important;
            border-bottom-color: #334155 !important;
            color: #f8fafc !important;
        }

        [data-bs-theme="dark"] .card-footer,
        [data-bs-theme="dark"] .card-footer.bg-body-tertiary,
        [data-bs-theme="dark"] .card-footer.bg-light {
            background-color: rgba(15, 23, 42, 0.6) !important;
            border-top-color: #334155 !important;
            color: #cbd5e1 !important;
        }

        [data-bs-theme="dark"] .small-box {
            background-color: #1e293b;
            border: 1px solid #334155;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.3);
        }

        /* Dark Mode Tables */
        [data-bs-theme="dark"] .table {
            --bs-table-bg: transparent;
            --bs-table-color: #f8fafc;
            --bs-table-border-color: #334155;
            color: #f8fafc;
        }

        [data-bs-theme="dark"] .table thead th {
            background-color: #1e293b !important;
            border-bottom: 2px solid #334155 !important;
            color: #f8fafc !important;
        }

        [data-bs-theme="dark"] thead.sticky-top th {
            background-color: #1e293b !important;
            color: #f8fafc !important;
            border-bottom: 1px solid #334155 !important;
        }

        [data-bs-theme="dark"] .table-light,
        [data-bs-theme="dark"] .table thead.table-light th,
        [data-bs-theme="dark"] .table-light th,
        [data-bs-theme="dark"] .table-light td {
            --bs-table-color: #f8fafc !important;
            --bs-table-bg: #1e293b !important;
            --bs-table-border-color: #334155 !important;
            color: #f8fafc !important;
            background-color: #1e293b !important;
            border-color: #334155 !important;
        }

        [data-bs-theme="dark"] .table tbody td {
            border-bottom-color: #334155 !important;
            color: #f8fafc;
        }

        [data-bs-theme="dark"] .table-hover tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.04) !important;
            color: #ffffff !important;
        }

        [data-bs-theme="dark"] .table-responsive {
            border-color: #334155 !important;
        }

        /* Dark Mode Form Inputs & Controls */
        [data-bs-theme="dark"] .form-control,
        [data-bs-theme="dark"] .form-select {
            background-color: #0f172a !important;
            border-color: #334155 !important;
            color: #f8fafc !important;
        }

        [data-bs-theme="dark"] .form-control:focus,
        [data-bs-theme="dark"] .form-select:focus {
            background-color: #0f172a !important;
            border-color: var(--bs-primary) !important;
            color: #ffffff !important;
            box-shadow: 0 0 0 0.25rem rgba(79, 70, 229, 0.25) !important;
        }

        [data-bs-theme="dark"] .form-control::placeholder {
            color: #64748b !important;
            opacity: 1;
        }

        [data-bs-theme="dark"] .input-group-text {
            background-color: #1e293b !important;
            border-color: #334155 !important;
            color: #94a3b8 !important;
        }

        [data-bs-theme="dark"] .form-control:disabled,
        [data-bs-theme="dark"] .form-control[readonly],
        [data-bs-theme="dark"] .form-select:disabled {
            background-color: #1e293b !important;
            border-color: #334155 !important;
            color: #94a3b8 !important;
            opacity: 0.85;
        }

        [data-bs-theme="dark"] .form-check-input {
            background-color: #0f172a;
            border-color: #475569;
        }

        [data-bs-theme="dark"] .form-check-input:checked {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
        }

        [data-bs-theme="dark"] .form-label,
        [data-bs-theme="dark"] .form-check-label {
            color: #e2e8f0;
        }

        [data-bs-theme="dark"] .form-text {
            color: #94a3b8 !important;
        }

        /* Dark Mode Buttons */
        [data-bs-theme="dark"] .btn-default,
        [data-bs-theme="dark"] .btn-outline-secondary {
            background-color: #1e293b !important;
            border-color: #334155 !important;
            color: #cbd5e1 !important;
        }

        [data-bs-theme="dark"] .btn-default:hover,
        [data-bs-theme="dark"] .btn-outline-secondary:hover {
            background-color: #334155 !important;
            border-color: #475569 !important;
            color: #ffffff !important;
        }

        [data-bs-theme="dark"] .btn-light {
            background-color: #334155 !important;
            border-color: #475569 !important;
            color: #f8fafc !important;
        }

        [data-bs-theme="dark"] .btn-light:hover {
            background-color: #475569 !important;
            color: #ffffff !important;
        }

        /* Dark Mode Badges */
        [data-bs-theme="dark"] .badge.text-bg-light,
        [data-bs-theme="dark"] .badge.bg-light {
            background-color: #334155 !important;
            color: #f1f5f9 !important;
            border-color: #475569 !important;
        }

        [data-bs-theme="dark"] .badge.text-bg-secondary,
        [data-bs-theme="dark"] .badge.bg-secondary {
            background-color: #334155 !important;
            color: #cbd5e1 !important;
            border-color: #475569 !important;
        }

        /* Dark Mode Dropdowns & User Profile Menu */
        [data-bs-theme="dark"] .dropdown-menu {
            background-color: #1e293b !important;
            border-color: #334155 !important;
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.5) !important;
        }

        [data-bs-theme="dark"] .dropdown-item {
            color: #e2e8f0 !important;
        }

        [data-bs-theme="dark"] .dropdown-item:hover,
        [data-bs-theme="dark"] .dropdown-item:focus {
            background-color: #334155 !important;
            color: #ffffff !important;
        }

        [data-bs-theme="dark"] .dropdown-header {
            color: #94a3b8 !important;
        }

        [data-bs-theme="dark"] .dropdown-divider {
            border-top-color: #334155 !important;
        }

        [data-bs-theme="dark"] .user-header {
            background-color: #1e293b !important;
            color: #f8fafc !important;
            border-bottom: 1px solid #334155 !important;
        }

        [data-bs-theme="dark"] .user-header p,
        [data-bs-theme="dark"] .user-header .text-dark {
            color: #f8fafc !important;
        }

        [data-bs-theme="dark"] .user-header small,
        [data-bs-theme="dark"] .user-header .text-muted {
            color: #94a3b8 !important;
        }

        [data-bs-theme="dark"] .user-footer.bg-light {
            background-color: #0f172a !important;
            border-top: 1px solid #334155 !important;
        }

        /* Dark Mode Notification Menu */
        [data-bs-theme="dark"] .dropdown-menu-notifications {
            background-color: #1e293b !important;
            border-color: #334155 !important;
        }

        [data-bs-theme="dark"] .notification-header {
            color: #94a3b8 !important;
            background-color: rgba(255, 255, 255, 0.03) !important;
        }

        [data-bs-theme="dark"] .notification-item-unread {
            background-color: rgba(79, 70, 229, 0.15) !important;
            color: #f8fafc !important;
        }

        [data-bs-theme="dark"] .notification-item-unread:hover,
        [data-bs-theme="dark"] .notification-item-unread:focus {
            background-color: rgba(79, 70, 229, 0.25) !important;
            color: #ffffff !important;
        }

        [data-bs-theme="dark"] .notification-item-read {
            background-color: transparent !important;
            color: #94a3b8 !important;
        }

        [data-bs-theme="dark"] .notification-item-read:hover,
        [data-bs-theme="dark"] .notification-item-read:focus {
            background-color: rgba(255, 255, 255, 0.05) !important;
            color: #ffffff !important;
        }

        [data-bs-theme="dark"] .notification-text {
            color: inherit !important;
        }

        /* Dark Mode Modals */
        [data-bs-theme="dark"] .modal-content {
            background-color: #1e293b !important;
            border-color: #334155 !important;
            color: #f8fafc !important;
            box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.5) !important;
        }

        [data-bs-theme="dark"] .modal-header {
            border-bottom-color: #334155 !important;
        }

        [data-bs-theme="dark"] .modal-footer {
            border-top-color: #334155 !important;
            background-color: rgba(15, 23, 42, 0.4) !important;
        }

        [data-bs-theme="dark"] .modal-footer.bg-light {
            background-color: rgba(15, 23, 42, 0.6) !important;
            border-top-color: #334155 !important;
        }

        [data-bs-theme="dark"] .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }

        [data-bs-theme="dark"] .modal-title {
            color: #f8fafc !important;
        }

        /* Dark Mode Nav Pills & Tabs (e.g. Dashboard Exception Tabs) */
        [data-bs-theme="dark"] .nav-pills .nav-link {
            color: #94a3b8;
        }

        [data-bs-theme="dark"] .nav-pills .nav-link:hover {
            color: #f8fafc;
            background-color: rgba(255, 255, 255, 0.05);
        }

        [data-bs-theme="dark"] .nav-pills .nav-link.active {
            background-color: var(--bs-primary) !important;
            color: #ffffff !important;
        }

        [data-bs-theme="dark"] .nav-tabs {
            border-bottom-color: #334155 !important;
        }

        [data-bs-theme="dark"] .nav-tabs .nav-link {
            color: #94a3b8;
        }

        [data-bs-theme="dark"] .nav-tabs .nav-link:hover {
            color: #f8fafc;
            border-color: #334155 #334155 transparent;
        }

        [data-bs-theme="dark"] .nav-tabs .nav-link.active {
            background-color: #1e293b !important;
            border-color: #334155 #334155 #1e293b !important;
            color: #f8fafc !important;
        }

        /* Dark Mode List Groups (e.g. Live Radar Feeds) */
        [data-bs-theme="dark"] .list-group-item {
            background-color: #1e293b !important;
            color: #f8fafc !important;
            border-color: #334155 !important;
        }

        [data-bs-theme="dark"] .list-group-item:hover {
            background-color: #273549 !important;
        }

        /* Dark Mode Pagination */
        [data-bs-theme="dark"] .page-link {
            background-color: #1e293b;
            border-color: #334155;
            color: #f8fafc;
        }

        [data-bs-theme="dark"] .page-item.active .page-link {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
            color: #ffffff;
        }

        [data-bs-theme="dark"] .page-item.disabled .page-link {
            background-color: #0f172a;
            border-color: #334155;
            color: #64748b;
        }

        /* Dark Mode Breadcrumbs */
        [data-bs-theme="dark"] .breadcrumb-item a {
            color: #818cf8 !important;
            text-decoration: none;
        }

        [data-bs-theme="dark"] .breadcrumb-item a:hover {
            color: #a5b4fc !important;
            text-decoration: underline;
        }

        [data-bs-theme="dark"] .breadcrumb-item.active {
            color: #94a3b8 !important;
        }

        [data-bs-theme="dark"] .breadcrumb-item+.breadcrumb-item::before {
            color: #64748b !important;
        }

        /* Dark Mode Leaflet Maps */
        [data-bs-theme="dark"] .leaflet-tile {
            filter: brightness(0.8) invert(1) contrast(3) hue-rotate(200deg) saturate(0.3) brightness(0.7);
        }

        [data-bs-theme="dark"] .leaflet-container {
            background-color: #0b1120 !important;
        }

        [data-bs-theme="dark"] .leaflet-popup-content-wrapper,
        [data-bs-theme="dark"] .leaflet-popup-tip {
            background-color: #1e293b !important;
            color: #f8fafc !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5) !important;
        }

        [data-bs-theme="dark"] .leaflet-bar a {
            background-color: #1e293b !important;
            color: #f8fafc !important;
            border-bottom-color: #334155 !important;
        }

        [data-bs-theme="dark"] .leaflet-bar a:hover {
            background-color: #334155 !important;
            color: #ffffff !important;
        }

        /* Dark Mode Utility Classes & General Contrast */
        [data-bs-theme="dark"] .bg-light {
            background-color: #1e293b !important;
            color: #f8fafc !important;
        }

        [data-bs-theme="dark"] .bg-white {
            background-color: #1e293b !important;
            color: #f8fafc !important;
        }

        [data-bs-theme="dark"] .bg-body {
            background-color: #0b1120 !important;
        }

        [data-bs-theme="dark"] .bg-body-tertiary {
            background-color: #0b1120 !important;
        }

        [data-bs-theme="dark"] .text-dark {
            color: #f8fafc !important;
        }

        [data-bs-theme="dark"] .text-muted {
            color: #94a3b8 !important;
        }

        [data-bs-theme="dark"] .text-secondary {
            color: #cbd5e1 !important;
        }

        [data-bs-theme="dark"] .border,
        [data-bs-theme="dark"] .border-top,
        [data-bs-theme="dark"] .border-bottom,
        [data-bs-theme="dark"] .border-start,
        [data-bs-theme="dark"] .border-end,
        [data-bs-theme="dark"] .border-secondary-subtle {
            border-color: #334155 !important;
        }

        [data-bs-theme="dark"] .img-thumbnail {
            background-color: #0f172a !important;
            border-color: #334155 !important;
        }

        [data-bs-theme="dark"] .progress {
            background-color: #0f172a !important;
        }

        /* Dark Mode Alerts */
        [data-bs-theme="dark"] .alert-success {
            background-color: rgba(16, 185, 129, 0.15) !important;
            border-color: rgba(16, 185, 129, 0.3) !important;
            color: #6ee7b7 !important;
        }

        [data-bs-theme="dark"] .alert-danger {
            background-color: rgba(225, 29, 72, 0.15) !important;
            border-color: rgba(225, 29, 72, 0.3) !important;
            color: #fda4af !important;
        }

        [data-bs-theme="dark"] .alert-warning {
            background-color: rgba(217, 119, 6, 0.15) !important;
            border-color: rgba(217, 119, 6, 0.3) !important;
            color: #fcd34d !important;
        }

        [data-bs-theme="dark"] .alert-info {
            background-color: rgba(2, 132, 199, 0.15) !important;
            border-color: rgba(2, 132, 199, 0.3) !important;
            color: #7dd3fc !important;
        }

        /* Dark Mode SweetAlert2 Dialogs */
        [data-bs-theme="dark"] .swal2-popup {
            background-color: #1e293b !important;
            color: #f8fafc !important;
            border: 1px solid #334155 !important;
        }

        [data-bs-theme="dark"] .swal2-title {
            color: #f8fafc !important;
        }

        [data-bs-theme="dark"] .swal2-html-container {
            color: #cbd5e1 !important;
        }

        /* Animations & Micro-interactions */
        @keyframes dropdownFadeIn {
            from {
                opacity: 0;
                transform: translateY(-8px) scale(0.97);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .dropdown-menu.show {
            animation: dropdownFadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            transform-origin: top right;
        }

        #darkModeIcon {
            transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
            display: inline-block;
        }

        #darkModeToggle:hover #darkModeIcon {
            transform: rotate(20deg) scale(1.18);
        }

        .app-header .bi-bell {
            transition: transform 0.25s ease;
            display: inline-block;
        }

        .app-header .nav-link:hover .bi-bell {
            transform: rotate(15deg) scale(1.12);
        }

        [data-lte-toggle="sidebar"] i {
            transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            display: inline-block;
        }

        [data-lte-toggle="sidebar"]:hover i {
            transform: scale(1.18);
        }

        .app-header .user-menu .user-image {
            transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .app-header .user-menu .nav-link:hover .user-image {
            transform: scale(1.06);
        }

        .app-sidebar .sidebar-menu .nav-link {
            transition: background-color 0.15s ease, color 0.15s ease, padding-left 0.2s ease !important;
        }

        .app-sidebar .sidebar-menu .nav-link:hover {
            padding-left: 1.45rem !important;
        }

        .app-sidebar .sidebar-menu .nav-link .nav-icon {
            transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            display: inline-block;
        }

        .app-sidebar .sidebar-menu .nav-link:hover .nav-icon {
            transform: scale(1.15);
        }

        .btn {
            transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1) !important;
        }

        .btn:hover:not(:disabled) {
            transform: translateY(-1px);
        }

        .btn:active:not(:disabled) {
            transform: translateY(0);
        }

        .modal.fade .modal-dialog {
            transform: scale(0.96) translateY(-10px);
            transition: transform 0.22s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.2s ease;
        }

        .modal.show .modal-dialog {
            transform: scale(1) translateY(0);
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            ::before,
            ::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
                scroll-behavior: auto !important;
            }
        }

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
        <nav class="app-header navbar navbar-expand">
            <div class="container-fluid">
                <!--begin::Start Navbar Links-->
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                            <i class="bi bi-list"></i>
                        </a>
                    </li>
                    <li class="nav-item d-none d-md-flex align-items-center ms-2">
                        <!-- <span class="fw-bold fs-5 header-title d-none d-sm-inline">
                            Kirana People - Garment HRIS
                        </span> -->
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
                        <a href="#" class="nav-link dropdown-toggle d-flex align-items-center" data-bs-toggle="dropdown"
                            data-bs-offset="0, 12">
                            <img src="{{ auth()->user()->avatar_url }}" class="user-image rounded-circle"
                                style="object-fit: cover; width: 2.1rem; height: 2.1rem; box-shadow: none !important;"
                                alt="User Image">
                            <span class="d-none d-md-inline ms-2">{{ auth()->user()->name }}</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end shadow-sm"
                            style="border-radius: 0.5rem; overflow: hidden; border: 1px solid rgba(0,0,0,0.1);">
                            <li class="user-header position-relative border-bottom"
                                style="background-color: #FEFEFE; color: #0f172a; overflow: hidden; border-bottom: 1px solid #e2e8f0 !important;">
                                <div style="position: relative; z-index: 1;">
                                    <img src="{{ auth()->user()->avatar_url }}" class="rounded-circle mb-2"
                                        style="width: 90px; height: 90px; object-fit: cover; box-shadow: none !important;"
                                        alt="User Image">
                                    <p class="mb-0 fw-bold fs-5 text-dark">
                                        {{ auth()->user()->name }}
                                    </p>
                                    <small class="d-block mt-2 text-muted">
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
    <!-- Chart.js UMD -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>

    @stack('scripts')
</body>

</html>