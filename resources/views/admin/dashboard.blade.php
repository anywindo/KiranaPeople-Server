@extends('layouts.admin')

@section('title', 'Dashboard HR')
@section('page_title', 'Dashboard Analytics & Monitoring')

@section('content')
    <!--begin::KPI Stats Row-->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6 col-12">
            <div class="small-box text-bg-primary h-100 mb-0 shadow-sm">
                <div class="inner">
                    <h3>{{ $totalEmployees }}</h3>
                    <p class="mb-1">Total Karyawan</p>
                    <small class="opacity-75"><i class="bi bi-person-badge me-1"></i>Terdaftar Aktif</small>
                </div>
                <div class="small-box-icon">
                    <i class="bi bi-people-fill"></i>
                </div>
                <a href="{{ route('admin.employees.index') }}"
                    class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover">
                    Data Karyawan <i class="bi bi-arrow-right-short"></i>
                </a>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 col-12">
            <div class="small-box text-bg-success h-100 mb-0 shadow-sm">
                <div class="inner">
                    <h3>{{ $presentToday }}</h3>
                    <p class="mb-1">Hadir Hari Ini</p>
                    <small class="opacity-75">
                        @if($presentToday > 0)
                            <i class="bi bi-check-circle me-1"></i>{{ $onTimeRate }}% Ketepatan
                        @else
                            <i class="bi bi-clock me-1"></i>Menunggu Presensi
                        @endif
                    </small>
                </div>
                <div class="small-box-icon">
                    <i class="bi bi-person-check-fill"></i>
                </div>
                <a href="{{ route('admin.attendances.index', ['date' => now()->format('Y-m-d')]) }}"
                    class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover">
                    Lihat Presensi <i class="bi bi-arrow-right-short"></i>
                </a>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 col-12">
            <div class="small-box text-bg-warning h-100 mb-0 shadow-sm text-white">
                <div class="inner">
                    <h3>{{ $lateCountToday }}</h3>
                    <p class="mb-1 text-white">Terlambat Hari Ini</p>
                    <small class="opacity-75 text-white"><i class="bi bi-exclamation-triangle me-1"></i>{{ $lateEmployees->count() }} Perlu Perhatian</small>
                </div>
                <div class="small-box-icon">
                    <i class="bi bi-clock-history"></i>
                </div>
                <a href="{{ route('admin.attendances.index', ['date' => now()->format('Y-m-d')]) }}"
                    class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover">
                    Detail Keterlambatan <i class="bi bi-arrow-right-short"></i>
                </a>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 col-12">
            <div class="small-box text-bg-danger h-100 mb-0 shadow-sm">
                <div class="inner">
                    <h3>{{ $absentToday }}</h3>
                    <p class="mb-1">Belum Hadir / Absen</p>
                    <small class="opacity-75"><i class="bi bi-slash-circle me-1"></i>{{ round(($absentToday / max(1, $totalEmployees)) * 100) }}% Dari Total</small>
                </div>
                <div class="small-box-icon">
                    <i class="bi bi-person-x-fill"></i>
                </div>
                <a href="{{ route('admin.absences.index') }}"
                    class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover">
                    Daftar Absen <i class="bi bi-arrow-right-short"></i>
                </a>
            </div>
        </div>
    </div>
    <!--end::KPI Stats Row-->

    <!--begin::Analytics & Monitoring Visual Row-->
    <div class="row g-3 mb-4">
        <!-- 7-Day Attendance Trend Chart -->
        <div class="col-lg-8 col-12">
            <div class="card h-100 shadow-sm">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center w-100">
                        <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                            <i class="bi bi-bar-chart-line-fill text-primary"></i>
                            <span>Tren Kehadiran (7 Hari Terakhir)</span>
                        </h5>
                        <div class="card-tools m-0">
                            <span class="badge text-bg-light border text-muted">Statistik Mingguan</span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div style="position: relative; height: 260px;">
                        <canvas id="attendanceTrendChart"></canvas>
                    </div>
                </div>
                <div class="card-footer bg-body-tertiary small py-2 d-flex justify-content-around text-muted">
                    <div><span class="d-inline-block rounded-circle me-1" style="width: 10px; height: 10px; background-color: #10b981;"></span> Tepat Waktu</div>
                    <div><span class="d-inline-block rounded-circle me-1" style="width: 10px; height: 10px; background-color: #f59e0b;"></span> Terlambat</div>
                    <div><span class="d-inline-block rounded-circle me-1" style="width: 10px; height: 10px; background-color: #ef4444;"></span> Belum Hadir / Absen</div>
                </div>
            </div>
        </div>

        <!-- Today's Punctuality Ratio Donut -->
        <div class="col-lg-4 col-12">
            <div class="card h-100 shadow-sm">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center w-100">
                        <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                            <i class="bi bi-pie-chart-fill text-primary"></i>
                            <span>Ketepatan Hari Ini</span>
                        </h5>
                        <div class="card-tools m-0">
                            @if($presentToday > 0)
                                <span class="badge text-bg-{{ $onTimeRate >= 80 ? 'success' : ($onTimeRate >= 50 ? 'warning' : 'danger') }}">{{ $onTimeRate }}% On-Time</span>
                            @else
                                <span class="badge text-bg-secondary">Belum Ada Presensi</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    <div style="position: relative; height: 190px; width: 100%;">
                        <canvas id="punctualityDonutChart"></canvas>
                    </div>
                    <div class="w-100 mt-3 pt-2 border-top">
                        <div class="d-flex justify-content-between text-muted small mb-1">
                            <span><i class="bi bi-check-circle-fill text-success me-1"></i>Tepat Waktu:</span>
                            <strong class="text-body">{{ $onTimeToday }} orang</strong>
                        </div>
                        <div class="d-flex justify-content-between text-muted small mb-1">
                            <span><i class="bi bi-clock-fill text-warning me-1"></i>Terlambat:</span>
                            <strong class="text-body">{{ $lateCountToday }} orang</strong>
                        </div>
                        <div class="d-flex justify-content-between text-muted small">
                            <span><i class="bi bi-x-circle-fill text-danger me-1"></i>Belum Hadir:</span>
                            <strong class="text-body">{{ $absentToday }} orang</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--end::Analytics & Monitoring Visual Row-->

    <!--begin::Live Radar & Exception Feeds-->
    <div class="row g-3 mb-4">
        <!-- Live Presence Radar -->
        <div class="col-lg-5 col-12">
            <div class="card h-100 shadow-sm">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center w-100">
                        <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                            <span class="spinner-grow spinner-grow-sm text-success" role="status" aria-hidden="true"></span>
                            <span>Aktivitas Hari Ini</span>
                        </h5>
                        <div class="card-tools m-0">
                            <span class="badge text-bg-secondary">{{ $liveActivity->count() }} Aktivitas Baru</span>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0" style="max-height: 290px; overflow-y: auto;">
                    <div class="list-group list-group-flush">
                        @forelse($liveActivity as $activity)
                            @php
                                $type = $activity->event_type?->value ?? $activity->event_type;
                                $typeLabel = match($type) {
                                    'START_SHIFT' => 'Masuk Kerja',
                                    'START_BREAK' => 'Mulai Istirahat',
                                    'END_BREAK' => 'Selesai Istirahat',
                                    'END_SHIFT' => 'Pulang Kerja',
                                    default => $type,
                                };
                                $typeBadge = match($type) {
                                    'START_SHIFT' => 'text-bg-success',
                                    'START_BREAK' => 'text-bg-warning text-white',
                                    'END_BREAK' => 'text-bg-info text-white',
                                    'END_SHIFT' => 'text-bg-primary',
                                    default => 'text-bg-secondary',
                                };
                                $selfieUrl = $activity->selfie_url ?: asset('placeholder.svg');
                            @endphp
                            <div class="list-group-item d-flex align-items-center gap-3 py-2 px-3">
                                <img src="{{ $selfieUrl }}" alt="Selfie" class="rounded-circle border"
                                    style="width: 40px; height: 40px; object-fit: cover;"
                                    onerror="this.onerror=null;this.src='{{ asset('placeholder.svg') }}';">
                                <div class="flex-grow-1 overflow-hidden">
                                    <div class="fw-semibold text-truncate">{{ $activity->attendance->user->name ?? 'Karyawan' }}</div>
                                    <small class="text-muted d-block text-truncate">
                                        <i class="bi bi-geo-alt"></i> {{ $activity->attendance->user->location->name ?? '-' }}
                                    </small>
                                </div>
                                <div class="text-end">
                                    <span class="badge {{ $typeBadge }} mb-1">{{ $typeLabel }}</span>
                                    <div class="small text-muted fw-bold">{{ $activity->timestamp ? \Carbon\Carbon::parse($activity->timestamp)->format('H:i:s') : '-' }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-radar fs-2 text-secondary d-block mb-2"></i>
                                Belum ada aktivitas presensi yang tercatat hari ini.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <!-- Late & Early Exception Tables -->
        <div class="col-lg-7 col-12">
            <div class="card h-100 shadow-sm">
                <div class="card-header p-2">
                    <ul class="nav nav-pills card-header-pills justify-content-center" id="dashboardTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active py-1 px-3 d-flex align-items-center gap-1" id="tab-late-btn" data-bs-toggle="pill"
                                data-bs-target="#tab-late" type="button" role="tab">
                                <i class="bi bi-clock-history text-danger"></i>
                                <span>Terlambat ({{ $lateEmployees->count() }})</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-1 px-3 d-flex align-items-center gap-1" id="tab-early-btn" data-bs-toggle="pill"
                                data-bs-target="#tab-early" type="button" role="tab">
                                <i class="bi bi-box-arrow-left text-warning"></i>
                                <span>Pulang Cepat ({{ $earlyEmployees->count() }})</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-1 px-3 d-flex align-items-center gap-1" id="tab-absent-btn" data-bs-toggle="pill"
                                data-bs-target="#tab-absent" type="button" role="tab">
                                <i class="bi bi-person-x text-secondary"></i>
                                <span>Belum Hadir ({{ $absentEmployees->count() }})</span>
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body p-0">
                    <div class="tab-content" id="dashboardTabsContent">
                        <!-- Late Tab -->
                        <div class="tab-pane fade show active" id="tab-late" role="tabpanel">
                            <div class="table-responsive m-0" style="max-height: 290px; overflow-y: auto;">
                                <table class="table table-hover align-middle mb-0 text-nowrap">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th>Karyawan</th>
                                            <th>Lokasi</th>
                                            <th>Jadwal</th>
                                            <th>Aktual</th>
                                            <th>Keterlambatan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($lateEmployees as $att)
                                            <tr>
                                                <td>
                                                    <div class="fw-bold">{{ $att->user->name ?? '-' }}</div>
                                                    <div class="text-muted small">{{ $att->user->nik ?? '-' }}</div>
                                                </td>
                                                <td>{{ $att->user->location->name ?? '-' }}</td>
                                                <td>{{ $att->scheduled_time }}</td>
                                                <td><span class="text-danger fw-bold">{{ $att->actual_time }}</span></td>
                                                <td><span class="badge text-bg-danger">{{ $att->late_minutes }} Menit</span></td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center py-4 text-muted">
                                                    <i class="bi bi-check-circle-fill text-success fs-3 d-block mb-1"></i>
                                                    Luar biasa! Tidak ada karyawan yang terlambat hari ini.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Early Tab -->
                        <div class="tab-pane fade" id="tab-early" role="tabpanel">
                            <div class="table-responsive m-0" style="max-height: 290px; overflow-y: auto;">
                                <table class="table table-hover align-middle mb-0 text-nowrap">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th>Karyawan</th>
                                            <th>Lokasi</th>
                                            <th>Jadwal Keluar</th>
                                            <th>Keluar Aktual</th>
                                            <th>Pulang Cepat</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($earlyEmployees as $att)
                                            <tr>
                                                <td>
                                                    <div class="fw-bold">{{ $att->user->name ?? '-' }}</div>
                                                    <div class="text-muted small">{{ $att->user->nik ?? '-' }}</div>
                                                </td>
                                                <td>{{ $att->user->location->name ?? '-' }}</td>
                                                <td>{{ $att->scheduled_end_time }}</td>
                                                <td><span class="text-warning fw-bold">{{ $att->actual_end_time }}</span></td>
                                                <td><span class="badge text-bg-warning">{{ $att->early_minutes }} Menit</span></td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center py-4 text-muted">
                                                    <i class="bi bi-emoji-smile text-primary fs-3 d-block mb-1"></i>
                                                    Tidak ada karyawan yang pulang lebih awal hari ini.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Absent Tab -->
                        <div class="tab-pane fade" id="tab-absent" role="tabpanel">
                            <div class="table-responsive m-0" style="max-height: 290px; overflow-y: auto;">
                                <table class="table table-hover align-middle mb-0 text-nowrap">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th>Karyawan</th>
                                            <th>Lokasi</th>
                                            <th>ID Karyawan</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($absentEmployees as $user)
                                            <tr>
                                                <td>
                                                    <div class="fw-bold">{{ $user->name ?? '-' }}</div>
                                                    <div class="text-muted small">{{ $user->nik ?? '-' }}</div>
                                                </td>
                                                <td>{{ $user->location->name ?? '-' }}</td>
                                                <td>{{ $user->employee_id ?? '-' }}</td>
                                                <td><span class="badge text-bg-danger">Belum Hadir</span></td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center py-4 text-muted">
                                                    <i class="bi bi-star-fill text-warning fs-3 d-block mb-1"></i>
                                                    Sempurna! Semua karyawan hadir hari ini.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--end::Live Radar & Exception Feeds-->
@endsection

@push('scripts')
    <script>
        function initDashboardCharts() {
            if (typeof Chart === 'undefined') {
                setTimeout(initDashboardCharts, 80);
                return;
            }

            const isDark = () => document.documentElement.getAttribute('data-bs-theme') === 'dark';
            const getGridColor = () => isDark() ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.06)';
            const getTextColor = () => isDark() ? '#94a3b8' : '#64748b';

            const trendData = @json($trendData);

            // 1. Attendance 7-Day Trend (Bar Chart)
            const trendCtx = document.getElementById('attendanceTrendChart');
            let trendChartInstance = null;
            if (trendCtx) {
                trendChartInstance = new Chart(trendCtx, {
                    type: 'bar',
                    data: {
                        labels: trendData.labels,
                        datasets: [
                            {
                                label: 'Tepat Waktu',
                                data: trendData.on_time,
                                backgroundColor: '#10b981',
                                borderRadius: 4,
                            },
                            {
                                label: 'Terlambat',
                                data: trendData.late,
                                backgroundColor: '#f59e0b',
                                borderRadius: 4,
                            },
                            {
                                label: 'Belum Hadir / Absen',
                                data: trendData.absent,
                                backgroundColor: '#ef4444',
                                borderRadius: 4,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                stacked: true,
                                grid: { display: false },
                                ticks: { color: getTextColor() }
                            },
                            y: {
                                stacked: true,
                                beginAtZero: true,
                                ticks: { precision: 0, color: getTextColor() },
                                grid: { color: getGridColor() }
                            }
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: { mode: 'index', intersect: false }
                        }
                    }
                });
            }

            // 2. Today's Punctuality Donut Chart
            const donutCtx = document.getElementById('punctualityDonutChart');
            let donutChartInstance = null;
            if (donutCtx) {
                const totalAttendanceToday = {{ $onTimeToday }} + {{ $lateCountToday }} + {{ $absentToday }};
                const donutData = totalAttendanceToday > 0 
                    ? [{{ $onTimeToday }}, {{ $lateCountToday }}, {{ $absentToday }}]
                    : [1];
                const donutColors = totalAttendanceToday > 0
                    ? ['#10b981', '#f59e0b', '#ef4444']
                    : ['#94a3b8'];
                const donutLabels = totalAttendanceToday > 0
                    ? ['Tepat Waktu', 'Terlambat', 'Belum Hadir']
                    : ['Belum Ada Karyawan'];

                donutChartInstance = new Chart(donutCtx, {
                    type: 'doughnut',
                    data: {
                        labels: donutLabels,
                        datasets: [{
                            data: donutData,
                            backgroundColor: donutColors,
                            borderWidth: 2,
                            borderColor: isDark() ? '#1e293b' : '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '70%',
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        if (totalAttendanceToday === 0) {
                                            return ' Belum ada data presensi';
                                        }
                                        const label = context.label || '';
                                        const value = context.parsed || 0;
                                        return ` ${label}: ${value} orang`;
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Listen to theme toggle changes to update chart styling dynamically
            const themeToggleBtn = document.getElementById('darkModeToggle');
            if (themeToggleBtn) {
                themeToggleBtn.addEventListener('click', () => {
                    setTimeout(() => {
                        if (trendChartInstance) {
                            trendChartInstance.options.scales.x.ticks.color = getTextColor();
                            trendChartInstance.options.scales.y.ticks.color = getTextColor();
                            trendChartInstance.options.scales.y.grid.color = getGridColor();
                            trendChartInstance.update();
                        }
                        if (donutChartInstance) {
                            donutChartInstance.data.datasets[0].borderColor = isDark() ? '#1e293b' : '#ffffff';
                            donutChartInstance.update();
                        }
                    }, 50);
                });
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initDashboardCharts);
        } else {
            initDashboardCharts();
        }
    </script>
@endpush
