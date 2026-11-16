@extends('layouts.admin')

@section('title', 'Riwayat Absen / Mangkir')
@section('page_title', 'Riwayat Absen / Mangkir')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h3 class="card-title mb-0">Daftar Karyawan Belum Presensi</h3>
                    <div class="d-flex align-items-center ms-auto gap-2">
                        <form action="{{ route('admin.absences.index') }}" method="GET"
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
                                            {{ $loc->name }}
                                        </option>
                                    @endforeach
                                </select>

                                <input type="date" name="date" class="form-control" title="Filter berdasarkan tanggal"
                                    value="{{ $date }}" onchange="this.form.submit()">
                                <input type="text" name="search" class="form-control" placeholder="Cari nama, NIK..."
                                    value="{{ request('search') }}">
                                <button type="submit" class="btn btn-default">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover text-nowrap">
                        <thead>
                            <tr>
                                <th>ID Karyawan</th>
                                <th>Karyawan</th>
                                <th>Email / Telepon</th>
                                <th>Lokasi Kerja</th>
                                <th>Status Kehadiran</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($absentees as $user)
                                <tr>
                                    <td>{{ $user->employee_id ?? '-' }}</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="{{ $user->avatar_url }}" alt="Avatar"
                                                class="rounded-circle object-fit-cover" style="width: 30px; height: 30px;">
                                            <div>
                                                <div class="fw-bold">{{ $user->name ?? '-' }}</div>
                                                <div class="text-muted small">{{ $user->nik ?? '-' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>{{ $user->email ?? '-' }}</div>
                                        <div class="text-muted small">{{ $user->phone ?? '-' }}</div>
                                    </td>
                                    <td>{{ $user->location->name ?? '-' }}</td>
                                    <td>
                                        <span class="badge text-bg-danger">Mangkir / Belum Presensi</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        <i class="bi bi-check-circle fs-2 d-block mb-2 text-success"></i>
                                        Tidak ada karyawan yang mangkir pada tanggal ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer clearfix">
                    {{ $absentees->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection