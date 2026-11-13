@extends('layouts.admin')

@section('title', 'Riwayat Istirahat')
@section('page_title', 'Riwayat Istirahat')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h3 class="card-title mb-0">Riwayat Istirahat Karyawan</h3>
                    <div class="d-flex align-items-center ms-auto gap-2">
                        <form action="{{ route('admin.breaks.index') }}" method="GET"
                            class="d-flex gap-2 align-items-center">
                            <div class="d-flex align-items-center text-nowrap">
                                <span class="me-2 small text-muted">Show</span>
                                <select name="per_page" class="form-select form-select-sm w-auto"
                                    onchange="this.form.submit()">
                                    <option value="10" {{ request('per_page') == 10 ? 'selected' : '' }}>10</option>
                                    <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                                    <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                                    <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                                </select>
                            </div>

                            <div class="input-group input-group-sm">
                                <select name="location_id" class="form-select" onchange="this.form.submit()">
                                    <option value="">Semua Lokasi</option>
                                    @foreach($locations ?? [] as $loc)
                                        <option value="{{ $loc->id }}" {{ request('location_id') == $loc->id ? 'selected' : '' }}>
                                            {{ $loc->name }}</option>
                                    @endforeach
                                </select>

                                <select name="shift_id" class="form-select" onchange="this.form.submit()">
                                    <option value="">Semua Shift</option>
                                    @foreach($shifts ?? [] as $shift)
                                        <option value="{{ $shift->id }}" {{ request('shift_id') == $shift->id ? 'selected' : '' }}>{{ $shift->shift_name }}</option>
                                    @endforeach
                                </select>

                                <input type="date" name="date" class="form-control" title="Filter berdasarkan tanggal"
                                    value="{{ request('date') }}">
                                <input type="text" name="search" class="form-control" placeholder="Cari nama, NIK..."
                                    value="{{ request('search') }}">
                                <button type="submit" class="btn btn-default">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </form>
                        <button type="button" class="btn btn-success btn-sm text-nowrap" data-bs-toggle="modal"
                            data-bs-target="#exportModal">
                            <i class="bi bi-file-earmark-excel"></i> Export Excel
                        </button>
                    </div>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover text-nowrap">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>ID Karyawan</th>
                                <th>Karyawan</th>
                                <th>Lokasi Kerja</th>
                                <th>Shift</th>
                                <th>Mulai Istirahat</th>
                                <th>Selesai Istirahat</th>
                                <th>Durasi Istirahat</th>
                                <th>Foto</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($attendances as $attendance)
                                @php
                                    $bEval = $attendance->break_evaluation;
                                @endphp
                                <tr onclick='showBreakDetail(@json($bEval['detail_data']))' style="cursor: pointer;"
                                    class="hover-bg-light">
                                    <td>{{ \Carbon\Carbon::parse($attendance->date)->format('d/m/Y') }}</td>
                                    <td>{{ $attendance->user->employee_id ?? '-' }}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div>
                                                <strong>{{ $attendance->user->name }}</strong><br>
                                                <small class="text-muted">{{ $attendance->user->nik }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $attendance->user->location->name ?? '-' }}</td>
                                    <td>{{ $attendance->shift->shift_name ?? '-' }}</td>
                                    <td>
                                        @if ($bEval['start_break'])
                                            <span
                                                class="{{ $bEval['start_color_class'] }} fw-bold">{{ \Carbon\Carbon::parse($bEval['start_break']->timestamp)->format('H:i') }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($bEval['end_break'])
                                            <span
                                                class="{{ $bEval['end_color_class'] }} fw-bold">{{ \Carbon\Carbon::parse($bEval['end_break']->timestamp)->format('H:i') }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">{{ $bEval['duration'] }}</span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            @if($bEval['start_break'])
                                                <img src="{{ $bEval['in_photo'] }}" alt="Mulai Istirahat" class="img-thumbnail p-1"
                                                    style="width: 40px; height: 40px; object-fit: cover;" onerror="this.onerror=null;this.src='{{ asset('placeholder.svg') }}';">
                                            @else
                                                <img src="{{ asset('placeholder.svg') }}" alt="Mulai Istirahat" class="img-thumbnail p-1 opacity-50"
                                                    style="width: 40px; height: 40px; object-fit: cover;">
                                            @endif

                                            @if($bEval['end_break'])
                                                <img src="{{ $bEval['out_photo'] }}" alt="Selesai Istirahat" class="img-thumbnail p-1"
                                                    style="width: 40px; height: 40px; object-fit: cover;" onerror="this.onerror=null;this.src='{{ asset('placeholder.svg') }}';">
                                            @else
                                                <img src="{{ asset('placeholder.svg') }}" alt="Selesai Istirahat"
                                                    class="img-thumbnail p-1 opacity-50"
                                                    style="width: 40px; height: 40px; object-fit: cover;">
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge text-bg-{{ $bEval['break_status_color'] }}">
                                            {{ $bEval['break_status_label'] }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-4">Belum ada riwayat istirahat.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer clearfix">
                    {{ $attendances->links() }}
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Export -->
    <div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('admin.breaks.export') }}" method="GET">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exportModalLabel">Export Data Istirahat</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Pilih Rentang Waktu</label>
                            <select class="form-select" name="export_type" id="export_type" onchange="toggleCustomDate()">
                                <option value="today">Hari Ini</option>
                                <option value="filter">Sesuai Filter Saat Ini</option>
                                <option value="all">Semua Hari</option>
                                <option value="custom">Ditentukan (Custom)</option>
                            </select>
                        </div>

                        <!-- Hidden inputs for filter -->
                        <input type="hidden" name="search" value="{{ request('search') }}">
                        <input type="hidden" name="date" value="{{ request('date') }}">
                        <input type="hidden" name="shift_id" value="{{ request('shift_id') }}">
                        <input type="hidden" name="location_id" value="{{ request('location_id') }}">

                        <div class="row" id="custom_date_container" style="display: none;">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Mulai Tanggal</label>
                                <input type="date" name="start_date" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Sampai Tanggal</label>
                                <input type="date" name="end_date" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success"><i class="bi bi-download"></i> Download Excel</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Detail Break -->
    <div class="modal fade" id="detailBreakModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width: 90vw;">
            <div class="modal-content overflow-hidden border-0 shadow-lg bg-body d-flex flex-column" style="height: 90vh;">
                <div
                    class="modal-header bg-body-tertiary border-bottom px-4 py-3 d-flex justify-content-between align-items-center flex-shrink-0">
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="detailEmployeeName">-</h5>
                        <div class="text-muted small" id="detailDate">-</div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span id="detailShiftStatusBadge"
                            class="badge text-bg-secondary px-3 py-2 rounded-pill fs-6">-</span>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body p-0 flex-grow-1 overflow-hidden">
                    <div class="row g-0 h-100">
                        <!-- Mulai Istirahat Side -->
                        <div class="col-lg-6 border-end border-secondary-subtle d-flex flex-column h-100">
                            <div class="p-3 d-flex justify-content-between align-items-center bg-body flex-shrink-0">
                                <div>
                                    <h6 class="mb-0 fw-bold">Mulai Istirahat Aktual</h6>
                                    <div class="fw-bold fs-4" id="inTime">-</div>
                                </div>
                                <span id="inStatusBadge" class="badge rounded-pill px-3 py-2">-</span>
                            </div>
                            <div class="d-flex justify-content-center align-items-center overflow-hidden bg-light border-bottom border-secondary-subtle"
                                style="flex: 1 1 50%; position: relative;">
                                <img id="inPhoto" src="" class="h-100 object-fit-contain w-100 position-absolute"
                                    alt="Foto Mulai Istirahat">
                            </div>
                            <div style="flex: 1 1 50%; position: relative;">
                                <div id="inMap" class="w-100 h-100 position-absolute"></div>
                            </div>
                            <div
                                class="p-3 bg-body-tertiary border-top border-secondary-subtle d-flex justify-content-between align-items-center flex-shrink-0">
                                <div>
                                    <div class="text-muted small mb-1">Koordinat GPS</div>
                                    <div class="font-monospace small" id="inCoordinates">-</div>
                                </div>
                                <a id="inMapsLink" href="#" target="_blank"
                                    class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-sm">
                                    <i class="bi bi-google"></i> Buka Maps
                                </a>
                            </div>
                        </div>

                        <!-- Selesai Istirahat Side -->
                        <div class="col-lg-6 d-flex flex-column h-100">
                            <div class="p-3 d-flex justify-content-between align-items-center bg-body flex-shrink-0">
                                <div>
                                    <h6 class="mb-0 fw-bold">Selesai Istirahat Aktual</h6>
                                    <div class="fw-bold fs-4" id="outTime">-</div>
                                </div>
                                <span id="outStatusBadge" class="badge rounded-pill px-3 py-2">-</span>
                            </div>
                            <div class="d-flex justify-content-center align-items-center overflow-hidden bg-light border-bottom border-secondary-subtle"
                                style="flex: 1 1 50%; position: relative;">
                                <img id="outPhoto" src="" class="h-100 object-fit-contain w-100 position-absolute"
                                    alt="Foto Selesai Istirahat">
                            </div>
                            <div style="flex: 1 1 50%; position: relative;">
                                <div id="outMap" class="w-100 h-100 position-absolute"></div>
                            </div>
                            <div
                                class="p-3 bg-body-tertiary border-top border-secondary-subtle d-flex justify-content-between align-items-center flex-shrink-0">
                                <div>
                                    <div class="text-muted small mb-1">Koordinat GPS</div>
                                    <div class="font-monospace small" id="outCoordinates">-</div>
                                </div>
                                <a id="outMapsLink" href="#" target="_blank"
                                    class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-sm">
                                    <i class="bi bi-google"></i> Buka Maps
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let inMapInstance = null;
        let inMapMarker = null;
        let outMapInstance = null;
        let outMapMarker = null;

        function showBreakDetail(data) {
            // Header
            document.getElementById('detailEmployeeName').textContent = data.employee;
            document.getElementById('detailDate').textContent = data.date;

            const shiftBadge = document.getElementById('detailShiftStatusBadge');
            shiftBadge.className = `badge text-bg-${data.shiftStatusColor} px-3 py-2 rounded-pill fs-6`;
            shiftBadge.textContent = data.shiftStatusLabel;

            // In
            document.getElementById('inTime').textContent = data.in.time;
            document.getElementById('inCoordinates').textContent = data.in.lat && data.in.lng ? `${data.in.lat}, ${data.in.lng}` : 'Tidak ada data GPS';
            document.getElementById('inPhoto').src = data.in.photo;

            const inBadge = document.getElementById('inStatusBadge');
            inBadge.className = `badge rounded-pill px-3 py-2 text-bg-${data.in.statusColor}`;
            inBadge.textContent = data.in.statusLabel;

            const inMapsLink = document.getElementById('inMapsLink');
            if (data.in.lat && data.in.lng) {
                inMapsLink.href = `https://maps.google.com/?q=${data.in.lat},${data.in.lng}`;
                inMapsLink.style.display = 'inline-block';
            } else {
                inMapsLink.style.display = 'none';
            }

            // Out
            document.getElementById('outTime').textContent = data.out.time;
            document.getElementById('outCoordinates').textContent = data.out.lat && data.out.lng ? `${data.out.lat}, ${data.out.lng}` : 'Tidak ada data GPS';
            document.getElementById('outPhoto').src = data.out.photo;

            const outBadge = document.getElementById('outStatusBadge');
            outBadge.className = `badge rounded-pill px-3 py-2 text-bg-${data.out.statusColor}`;
            outBadge.textContent = data.out.statusLabel;

            const outMapsLink = document.getElementById('outMapsLink');
            if (data.out.lat && data.out.lng) {
                outMapsLink.href = `https://maps.google.com/?q=${data.out.lat},${data.out.lng}`;
                outMapsLink.style.display = 'inline-block';
            } else {
                outMapsLink.style.display = 'none';
            }

            const modal = new bootstrap.Modal(document.getElementById('detailBreakModal'));
            modal.show();

            document.getElementById('detailBreakModal').addEventListener('shown.bs.modal', function () {
                // In Map
                if (data.in.lat && data.in.lng) {
                    if (!inMapInstance) {
                        inMapInstance = L.map('inMap').setView([data.in.lat, data.in.lng], 16);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '&copy; OpenStreetMap'
                        }).addTo(inMapInstance);
                        inMapMarker = L.marker([data.in.lat, data.in.lng]).addTo(inMapInstance);
                    } else {
                        inMapInstance.setView([data.in.lat, data.in.lng], 16);
                        inMapMarker.setLatLng([data.in.lat, data.in.lng]);
                        inMapInstance.invalidateSize();
                    }
                } else {
                    if (inMapInstance) { inMapInstance.remove(); inMapInstance = null; }
                    document.getElementById('inMap').innerHTML = '<div class="d-flex h-100 w-100 align-items-center justify-content-center text-muted"><i class="bi bi-geo-slash fs-1 me-2"></i> Tidak ada data lokasi</div>';
                }

                // Out Map
                if (data.out.lat && data.out.lng) {
                    if (!outMapInstance) {
                        outMapInstance = L.map('outMap').setView([data.out.lat, data.out.lng], 16);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '&copy; OpenStreetMap'
                        }).addTo(outMapInstance);
                        outMapMarker = L.marker([data.out.lat, data.out.lng]).addTo(outMapInstance);
                    } else {
                        outMapInstance.setView([data.out.lat, data.out.lng], 16);
                        outMapMarker.setLatLng([data.out.lat, data.out.lng]);
                        outMapInstance.invalidateSize();
                    }
                } else {
                    if (outMapInstance) { outMapInstance.remove(); outMapInstance = null; }
                    document.getElementById('outMap').innerHTML = '<div class="d-flex h-100 w-100 align-items-center justify-content-center text-muted"><i class="bi bi-geo-slash fs-1 me-2"></i> Tidak ada data lokasi</div>';
                }
            }, { once: true });
        }

        function toggleCustomDate() {
            var type = document.getElementById('export_type').value;
            var container = document.getElementById('custom_date_container');
            if (type === 'custom') {
                container.style.display = 'flex';
            } else {
                container.style.display = 'none';
            }
        }
    </script>
@endsection