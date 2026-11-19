@extends('layouts.admin')

@section('title', 'Profil Saya')
@section('page_title', 'Profil Saya')

@section('content')
<div class="row">
    <div class="col-xl-4 col-lg-5 mb-4">
        <div class="card shadow-sm h-100 overflow-hidden border-0" style="min-height: 450px;">
            <div class="position-relative h-100 d-flex flex-column justify-content-end">
                <!-- Background Image -->
                <img src="{{ $user->avatar_url }}" 
                     class="position-absolute top-0 start-0 w-100 h-100" 
                     style="object-fit: cover; z-index: 0;" id="avatarPreview" alt="Profile Photo">
                
                <!-- Gradient Overlay -->
                <div class="position-absolute top-0 start-0 w-100 h-100" 
                     style="background: linear-gradient(to bottom, rgba(0,0,0,0) 40%, rgba(0,0,0,0.85) 100%); z-index: 1;"></div>
                
                <!-- Content -->
                <div class="position-relative p-4 text-start text-white w-100" style="z-index: 2;">
                    <h3 class="fw-bold mb-1 text-white text-shadow-sm">{{ $user->name }}</h3>
                    <p class="text-light mb-1 opacity-75">{{ $user->email }}</p>
                    @if($user->dob || ($user->nik && \App\Models\User::parseDobDateFromNik($user->nik)))
                        <p class="text-light mb-2 opacity-75 small">
                            <i class="bi bi-cake2 me-1"></i>
                            {{ $user->dob ? $user->dob->translatedFormat('d F Y') : \Illuminate\Support\Carbon::parse(\App\Models\User::parseDobDateFromNik($user->nik))->translatedFormat('d F Y') }}
                        </p>
                    @endif
                    <div class="d-flex align-items-start mb-4">
                        <span class="text-light fw-semibold fs-6">
                            {{ $user->roles->pluck('name')->unique()->join(', ') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-8 col-lg-7 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header border-bottom p-4" style="background: transparent;">
                <h4 class="card-title mb-0 fw-bold"><i class="bi bi-person-lines-fill me-2 text-primary"></i> Data Personal & Keamanan</h4>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('admin.profile.update') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="row mb-4">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label class="form-label fw-bold">Nama Lengkap</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required>
                            </div>
                            @error('name')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" required>
                            </div>
                            @error('email')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label class="form-label fw-bold">Employee ID</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                                <input type="text" class="form-control text-muted" value="{{ $user->employee_id ?? '-' }}" readonly disabled>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">NIK (KTP)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-credit-card-2-front"></i></span>
                                <input type="text" class="form-control text-muted" value="{{ $user->nik ?? '-' }}" readonly disabled>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <label class="form-label fw-bold">Tanggal Lahir (DOB)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
                                <input type="date" name="dob" class="form-control @error('dob') is-invalid @enderror" value="{{ old('dob', $user->dob ? $user->dob->format('Y-m-d') : ($user->nik ? (\App\Models\User::parseDobDateFromNik($user->nik) ?? '') : '')) }}">
                            </div>
                            @error('dob')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Lokasi Kerja</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                                <input type="text" class="form-control text-muted" value="{{ $user->location->name ?? '-' }}" readonly disabled>
                            </div>
                        </div>
                        <div class="col-12 mt-2">
                            <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Employee ID, NIK, dan Lokasi Kerja dikelola oleh administrator.</small>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-12">
                            <label class="form-label fw-bold">Foto Profil (Opsional)</label>
                            <input type="file" name="avatar" id="avatar" class="form-control @error('avatar') is-invalid @enderror" accept="image/*" onchange="previewImage(this)">
                            <div class="form-text mt-2"><i class="bi bi-info-circle me-1"></i> Maksimal ukuran file 5MB. Format: JPG, PNG, WEBP.</div>
                            @error('avatar')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <hr class="my-5 opacity-25">
                    
                    <h5 class="fw-bold mb-4 text-secondary"><i class="bi bi-shield-lock me-2"></i>Ubah Password <span class="fs-6 fw-normal text-muted">(Opsional)</span></h5>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label fw-bold">Password Saat Ini</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
                                <input type="password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" placeholder="Masukkan password saat ini jika ingin mengubah password">
                            </div>
                            @error('current_password')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Password Baru</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-key"></i></span>
                                <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="Kosongkan jika tak ingin diubah">
                            </div>
                            @error('password')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold">Konfirmasi Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                                <input type="password" name="password_confirmation" class="form-control" placeholder="Ulangi password baru">
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-2">
                        <button type="submit" class="btn btn-primary px-4 py-2 fw-bold shadow-sm">
                            <i class="bi bi-floppy me-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatarPreview').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
@endpush
@endsection
