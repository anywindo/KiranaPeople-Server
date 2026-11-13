<aside class="app-sidebar">
    <!--begin::Sidebar Brand-->
    <div class="sidebar-brand text-center">
        <a href="{{ route('admin.dashboard') }}" class="brand-link text-center justify-content-center">
            <img src="{{ asset('logoKiranaHitam.png') }}" alt="Kirana Logo" class="brand-image">
            <span class="brand-text fw-light fs-5"><b>Kirana</b> Group</span>
        </a>
    </div>
    <!--end::Sidebar Brand-->

    <!--begin::Sidebar Wrapper-->
    <div class="sidebar-wrapper flex-grow-1" style="padding-bottom: 70px;">
        <nav class="mt-2">
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">

                <li class="nav-item">
                    <a href="{{ route('admin.dashboard') }}"
                        class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-speedometer2"></i>
                        <p>Dashboard</p>
                    </a>
                </li>

                <li class="nav-header">MASTER DATA</li>

                <li class="nav-item">
                    <a href="{{ route('admin.employees.index') }}"
                        class="nav-link {{ request()->routeIs('admin.employees.*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-people-fill"></i>
                        <p>Data Karyawan</p>
                    </a>
                </li>

                {{-- <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="nav-icon bi bi-diagram-3-fill"></i>
                        <p>Departemen / Tim</p>
                    </a>
                </li> --}}

                <li class="nav-item">
                    <a href="{{ route('admin.shifts.index') }}"
                        class="nav-link {{ request()->routeIs('admin.shifts.*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-clock-history"></i>
                        <p>Pengaturan Shift</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('admin.locations.index') }}"
                        class="nav-link {{ request()->routeIs('admin.locations.*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-geo-alt-fill"></i>
                        <p>Lokasi Kerja (GPS)</p>
                    </a>
                </li>


                <li class="nav-header">MANAJEMEN KEHADIRAN</li>

                <li class="nav-item">
                    <a href="{{ route('admin.attendances.index') }}"
                        class="nav-link {{ request()->routeIs('admin.attendances.*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-calendar-check-fill"></i>
                        <p>Riwayat Presensi</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('admin.absences.index') }}"
                        class="nav-link {{ request()->routeIs('admin.absences.*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-person-x-fill"></i>
                        <p>Karyawan Absen</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('admin.breaks.index') }}"
                        class="nav-link {{ request()->routeIs('admin.breaks.*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-cup-hot-fill"></i>
                        <p>Riwayat Istirahat</p>
                    </a>
                </li>

                {{-- <li class="nav-header">PUSAT PENGAJUAN</li>

                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="nav-icon bi bi-envelope-paper-fill"></i>
                        <p>Pengajuan Cuti</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="nav-icon bi bi-briefcase-fill"></i>
                        <p>Lembur & Dinas</p>
                    </a>
                </li> --}}

                <li class="nav-header">PENGATURAN</li>

                <li class="nav-item">
                    <a href="{{ route('admin.profile.index') }}"
                        class="nav-link {{ request()->routeIs('admin.profile.*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-person-fill"></i>
                        <p>Profil Saya</p>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ route('admin.settings.index') }}"
                        class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                        <i class="nav-icon bi bi-gear-fill"></i>
                        <p>Pengaturan</p>
                    </a>
                </li>

                @if(auth()->user()->can('manage-rbac') || auth()->user()->isSuperAdmin())
                    <li class="nav-header">SISTEM</li>

                    @can('manage-rbac')
                        <li class="nav-item">
                            <a href="{{ route('admin.roles.index') }}"
                                class="nav-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}">
                                <i class="nav-icon bi bi-shield-lock-fill"></i>
                                <p>Hak Akses (RBAC)</p>
                            </a>
                        </li>
                    @endcan

                    @if(auth()->user()->isSuperAdmin())
                        <li class="nav-item">
                            <a href="{{ route('admin.audit-logs.index') }}"
                                class="nav-link {{ request()->routeIs('admin.audit-logs.*') ? 'active' : '' }}">
                                <i class="nav-icon bi bi-journal-text"></i>
                                <p>Log Audit CRUD</p>
                            </a>
                        </li>
                    @endif
                @endif

            </ul>
        </nav>
    </div>
    <!--end::Sidebar Wrapper-->

    <!--begin::Sidebar Footer / Logout-->
    <div class="sidebar-footer position-absolute bottom-0 w-100" style="z-index: 10;">
        <a href="#" class="btn btn-danger w-100 text-start text-white"
            onclick="event.preventDefault(); document.getElementById('sidebar-logout-form').submit();">
            <i class="bi bi-box-arrow-right me-2"></i> Logout
        </a>
        <form id="sidebar-logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
            @csrf
        </form>
    </div>
    <!--end::Sidebar Footer-->
</aside>