@extends('layouts.admin')

@section('title', 'Lokasi Kerja')
@section('page_title', 'Lokasi Kerja (GPS)')

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
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title">Daftar Lokasi Kerja</h3>
                <div class="ms-auto">
                    <a href="{{ route('admin.locations.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus"></i> Tambah Lokasi</a>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover text-nowrap">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama Lokasi</th>
                            <th>Latitude</th>
                            <th>Longitude</th>
                            <th>Radius (meter)</th>
                            <th>Jumlah Pekerja</th>
                            <th style="width: 150px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($locations as $location)
                        <tr>
                            <td>{{ $location->id }}</td>
                            <td>{{ $location->name }}</td>
                            <td>{{ $location->latitude }}</td>
                            <td>{{ $location->longitude }}</td>
                            <td>{{ $location->radius }} m</td>
                            <td>
                                @if($location->users_count > 0)
                                    <a href="{{ route('admin.employees.index', ['location_id' => $location->id]) }}" class="badge text-bg-primary text-decoration-none d-inline-flex align-items-center gap-1" title="Lihat daftar karyawan di lokasi ini">
                                        <i class="bi bi-people-fill"></i> {{ $location->users_count }} Orang
                                    </a>
                                @else
                                    <span class="badge text-bg-secondary d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-people"></i> 0 Orang
                                    </span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.locations.edit', $location) }}" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                                <form action="{{ route('admin.locations.destroy', $location) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus lokasi ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center">Belum ada data lokasi.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer clearfix">
                {{ $locations->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
