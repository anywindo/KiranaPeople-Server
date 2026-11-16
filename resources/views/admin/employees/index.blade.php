@extends('layouts.admin')

@section('title', 'Data Karyawan')
@section('page_title', 'Manajemen Karyawan')

@section('content')
<div class="row">
    <div class="col-12">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h3 class="card-title mb-0">Daftar Karyawan</h3>
                <div class="d-flex align-items-center ms-auto gap-2">
                    <form action="{{ route('admin.employees.index') }}" method="GET" class="d-flex align-items-center gap-2">
                        <div class="d-flex align-items-center">
                            <span class="text-nowrap me-2 fs-7 text-muted">Show</span>
                            <select name="per_page" class="form-select form-select-sm" onchange="this.form.submit()" style="width: 70px;">
                                <option value="10" {{ request('per_page') == 10 ? 'selected' : '' }}>10</option>
                                <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                                <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                                <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                            </select>
                        </div>
                        <div class="input-group input-group-sm">
                            <input type="text" name="search" class="form-control" placeholder="Cari nama, email, NIK, ID..." value="{{ request('search') }}">
                            <button type="submit" class="btn btn-default">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </form>
                    <a href="{{ route('admin.employees.export') }}" class="btn btn-success btn-sm text-nowrap"><i class="bi bi-file-earmark-excel"></i> Export Excel</a>
                    <a href="{{ route('admin.employees.create') }}" class="btn btn-primary btn-sm text-nowrap"><i class="bi bi-plus"></i> Tambah Karyawan</a>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover text-nowrap">
                    <thead>
                        <tr>
                            <th>Avatar</th>
                            <th>ID / NIK</th>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Lokasi Kerja</th>
                            <th style="width: 150px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employees as $employee)
                        <tr>
                            <td>
                                @if($employee->avatar)
                                    <a href="javascript:void(0)" onclick="showLightbox('{{ $employee->avatar_url }}')" title="Lihat Foto">
                                        <img src="{{ $employee->avatar_url }}" alt="Avatar" class="rounded" style="width: 40px; height: 40px; object-fit: cover;" onerror="this.onerror=null;this.src='{{ asset('placeholder.svg') }}';">
                                    </a>
                                @else
                                    <a href="javascript:void(0)" onclick="showLightbox('{{ asset('placeholder.svg') }}')" title="Tidak ada foto">
                                        <img src="{{ asset('placeholder.svg') }}" alt="Default Avatar" class="rounded" style="width: 40px; height: 40px; object-fit: cover;">
                                    </a>
                                @endif
                            </td>
                            <td>
                                <div>{{ $employee->employee_id }}</div>
                                <div class="text-muted small">{{ $employee->nik ?: '-' }}</div>
                            </td>
                            <td>{{ $employee->name }}</td>
                            <td>{{ $employee->email }}</td>
                            <td>
                                @forelse($employee->roles->unique('name') as $role)
                                    <span class="badge text-bg-primary">{{ $role->name }}</span>
                                @empty
                                    <span class="text-muted small">Tidak ada</span>
                                @endforelse
                            </td>
                            <td>
                                @if($employee->is_active)
                                    <span class="badge text-bg-success">Aktif</span>
                                @else
                                    <span class="badge text-bg-danger">Nonaktif</span>
                                @endif
                            </td>
                            <td>{{ $employee->location ? $employee->location->name : '-' }}</td>
                            <td>
                                @if(! $employee->hasRole('super-admin') || auth()->user()->hasRole('super-admin'))
                                    <a href="{{ route('admin.employees.edit', $employee) }}" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                                @endif
                                @if($employee->id != auth()->id() && ! $employee->hasRole('super-admin'))
                                <form action="{{ route('admin.employees.destroy', $employee) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus karyawan ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center">Belum ada data karyawan.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer clearfix">
                {{ $employees->links() }}
            </div>
        </div>
    </div>
</div>






@endsection
