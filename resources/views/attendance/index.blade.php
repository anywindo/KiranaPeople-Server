<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Presensi | Kirana People</title>
    @include('layouts.partials.favicons')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
</head>

@php
    $currentHour = (int) now()->format('H');
    if ($currentHour >= 5 && $currentHour < 11) {
        $greeting = 'Selamat Pagi,';
    } elseif ($currentHour >= 11 && $currentHour < 15) {
        $greeting = 'Selamat Siang,';
    } elseif ($currentHour >= 15 && $currentHour < 18) {
        $greeting = 'Selamat Sore,';
    } else {
        $greeting = 'Selamat Malam,';
    }

    $startEvent = $events->first(fn($e) => ($e->event_type?->value ?? $e->event_type) === 'START_SHIFT');
    $endEvent = $events->first(fn($e) => ($e->event_type?->value ?? $e->event_type) === 'END_SHIFT');
    $startBreak = $events->first(fn($e) => ($e->event_type?->value ?? $e->event_type) === 'START_BREAK');
    $endBreak = $events->first(fn($e) => ($e->event_type?->value ?? $e->event_type) === 'END_BREAK');
    $hasActiveBreak = $events->contains(fn($e) => ($e->event_type?->value ?? $e->event_type) === 'START_BREAK') && !$events->contains(fn($e) => ($e->event_type?->value ?? $e->event_type) === 'END_BREAK');

    $displayBreakStart = ($shift && $shift->break_start) ? \Carbon\Carbon::parse($shift->break_start)->format('H:i') : '12:00';
    $displayBreakEnd = ($shift && $shift->break_end) ? \Carbon\Carbon::parse($shift->break_end)->format('H:i') : '13:00';
    $fridayBreakEnabled = \App\Models\Setting::get('friday_break_enabled', true);
    if (now()->isFriday() && $fridayBreakEnabled && $shift && $shift->break_start) {
        $displayBreakStart = \App\Models\Setting::get('friday_break_start', '11:30');
        $displayBreakEnd = \App\Models\Setting::get('friday_break_end', '13:00');
    }

    $activeShiftName = $shift ? $shift->shift_name : 'Morning Shift';
    $activeShiftStart = $shift ? \Carbon\Carbon::parse($shift->default_start_time)->format('H:i') : '08:00';
    $activeShiftEnd = $shift ? \Carbon\Carbon::parse($shift->default_end_time)->format('H:i') : '16:00';
    $activeShiftId = $shift ? $shift->id : 1;
@endphp

<body class="min-h-screen pb-28 antialiased select-none" x-data="{ 
        activeTab: 'work', 
        currentNav: '{{ $activeNav ?? (request()->is('attendance/history*') ? 'history' : (request()->is('attendance/profile*') ? 'profile' : (request()->hasAny(['start_date', 'end_date']) ? 'history' : 'home'))) }}',
        showPreview: false,
        showCamera: false,
        showLogoutModal: false,
        showShiftPicker: false,
        selectedShiftId: {{ $activeShiftId }},
        selectedShiftName: '{{ addslashes($activeShiftName) }}',
        selectedShiftHours: '{{ $activeShiftStart }} - {{ $activeShiftEnd }}',
        selectedShiftStart: '{{ $activeShiftStart }}',
        selectedShiftEnd: '{{ $activeShiftEnd }}',
        previewImage: null,
        currentFormId: null,
        showEditProfileModal: {{ $errors->any() ? 'true' : 'false' }},
        avatarPreview: null,
        previewAvatar(event) {
            const file = event.target.files[0];
            if (file) {
                this.avatarPreview = URL.createObjectURL(file);
            }
        },
        locationData: { lat: null, lng: null, accuracy: null, distance: null },
        locationStatusText: 'Memeriksa lokasi GPS...',
        locationDistanceText: 'Memeriksa jarak...',
        locationValid: false,
        showNotes: false,
        isSubmitting: false,
        get currentMapActionLabel() {
            @if(!$startEvent)
                return 'Ambil Presensi: Masuk Kerja';
            @elseif(!$endEvent)
                @if($hasActiveBreak)
                    return 'Ambil Presensi: Selesai Istirahat';
                @else
                    return 'Ambil Presensi: Pulang Kerja';
                @endif
            @else
                return 'Shift Telah Selesai';
            @endif
        },
        triggerAttendanceFromMap() {
            @if(!$startEvent)
                openCameraUI('attendanceForm');
            @elseif(!$endEvent)
                @if($hasActiveBreak)
                    openCameraUI('breakForm');
                @else
                    openCameraUI('attendanceForm');
                @endif
            @endif
        },
        lightboxOpen: false,
        lightboxImage: '',
        lightboxTitle: '',
        lightboxSubtitle: '',
        openLightbox(imgUrl, title = '', subtitle = '') {
            this.lightboxImage = imgUrl;
            this.lightboxTitle = title;
            this.lightboxSubtitle = subtitle;
            this.lightboxOpen = true;
        },
        closeLightbox() {
            this.lightboxOpen = false;
            this.lightboxImage = '';
        },
        showMapModal: false,
        mapModalTitle: '',
        mapModalSubtitle: '',
        mapModalLat: null,
        mapModalLng: null,
        openMapModal(lat, lng, title = 'Lokasi Presensi', subtitle = '') {
            if (!lat || !lng) return;
            this.mapModalLat = parseFloat(lat);
            this.mapModalLng = parseFloat(lng);
            this.mapModalTitle = title;
            this.mapModalSubtitle = subtitle;
            this.showMapModal = true;
            this.$nextTick(() => {
                setTimeout(() => {
                    initHistoryMap(this.mapModalLat, this.mapModalLng, this.mapModalTitle);
                }, 100);
            });
        },
        closeMapModal() {
            this.showMapModal = false;
            if (window.historyMapInstance) {
                window.historyMapInstance.remove();
                window.historyMapInstance = null;
            }
        },
        initLiveMap() {
            this.$nextTick(() => {
                setTimeout(() => {
                    initLiveAttendanceMap();
                    if (window.liveMapInstance) {
                        window.liveMapInstance.invalidateSize();
                        fitLiveMapBounds();
                    }
                }, 150);
            });
        }
    }"
    x-init="window.alpineApp = $data; $watch('currentNav', val => { if (val === 'map') initLiveMap(); }); window.addEventListener('popstate', () => { if (window.location.pathname.includes('/attendance/history')) { currentNav = 'history'; } else if (window.location.pathname.includes('/attendance/profile')) { currentNav = 'profile'; } else if (window.location.pathname.endsWith('/attendance') || window.location.pathname.endsWith('/attendance/')) { currentNav = 'home'; } })"
    @preview-ready.window="previewImage = $event.detail.image; currentFormId = $event.detail.formId; showPreview = true; setTimeout(() => getLocationAndInitMap(), 50)">

    <!-- Center Container for mobile feel (Home, History, Profile) -->
    <div class="max-w-md mx-auto min-h-screen px-4 pt-3 flex flex-col justify-between" x-show="currentNav !== 'map'">

        <!-- ==================== HOME VIEW ==================== -->
        <div x-show="currentNav === 'home'" x-cloak x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-98" x-transition:enter-end="opacity-100 scale-100">

            <!-- Top Header (Company Name + Logout Button) -->
            <header class="flex justify-between items-center py-2 mb-3">
                <h1 class="text-[20px] font-extrabold tracking-tight text-[#1C1C1E] uppercase">KIRANA GROUP</h1>
                <button type="button" @click="showLogoutModal = true"
                    class="w-10 h-10 rounded-full bg-white shadow-sm border border-gray-100/90 flex items-center justify-center text-gray-500 hover:text-red-500 transition active:scale-92"
                    title="Keluar">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l3 3m0 0l-3 3m3-3H9" />
                    </svg>
                </button>
            </header>

            <!-- Employee Greeting Row -->
            <div class="flex items-center space-x-3.5 mb-4 px-1">
                <img src="{{ $user->avatar_url }}"
                    @click="openLightbox('{{ $user->avatar_url }}', '{{ addslashes($user->name) }}', 'Foto Profil')"
                    class="w-[52px] h-[52px] rounded-full object-cover border border-gray-200/80 shadow-sm cursor-pointer hover:ring-2 hover:ring-[#FF21A4] transition-all flex-shrink-0"
                    alt="{{ $user->name }}" onerror="this.onerror=null;this.src='{{ asset('placeholder.svg') }}';">
                <div class="flex flex-col min-w-0">
                    <span class="text-[13px] font-normal text-[#8E8E93] leading-tight">{{ $greeting }}</span>
                    <span class="text-[17px] font-bold text-[#1C1C1E] tracking-tight leading-snug truncate">{{ $user->name }}</span>
                </div>
            </div>

            <!-- Session Alert Banner -->
            @if(session('success'))
                <div class="mb-3">
                    <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-3.5 py-2.5 rounded-xl text-xs font-medium flex items-center shadow-sm">
                        <i class="fa-solid fa-circle-check text-emerald-500 mr-2 text-sm"></i>
                        <span>{{ session('success') }}</span>
                    </div>
                </div>
            @endif
            @if(session('error'))
                <div class="mb-3">
                    <div class="bg-rose-50 border border-rose-200 text-rose-700 px-3.5 py-2.5 rounded-xl text-xs font-medium flex items-center shadow-sm">
                        <i class="fa-solid fa-circle-exclamation text-rose-500 mr-2 text-sm"></i>
                        <span>{{ session('error') }}</span>
                    </div>
                </div>
            @endif

            <!-- Segment Bar: Capsule (Kerja / Istirahat) -->
            <div class="p-1 bg-[#EEF0F5] rounded-full flex gap-1 mb-4 shadow-inner">
                <button type="button" @click="activeTab = 'work'"
                    :class="activeTab === 'work' ? 'bg-[#FF21A4] text-white shadow-md shadow-pink-500/25' : 'text-[#8E8E93] hover:text-[#1C1C1E]'"
                    class="flex-1 py-2 rounded-full font-bold text-sm flex items-center justify-center gap-2 transition-all duration-200">
                    <i class="fa-solid fa-briefcase text-xs"></i>
                    <span>Kerja</span>
                </button>
                <button type="button" @click="activeTab = 'break'"
                    :class="activeTab === 'break' ? 'bg-[#FF21A4] text-white shadow-md shadow-pink-500/25' : 'text-[#8E8E93] hover:text-[#1C1C1E]'"
                    class="flex-1 py-2 rounded-full font-bold text-sm flex items-center justify-center gap-2 transition-all duration-200">
                    <i class="fa-solid fa-mug-hot text-xs"></i>
                    <span>Istirahat</span>
                </button>
            </div>

            <!-- Main Shift Schedule Card -->
            <div class="tn-card p-5 mb-4">
                <!-- Date Header -->
                <p class="text-[13px] text-[#8E8E93] font-normal mb-3">
                    Hari Ini ({{ now()->locale('id')->translatedFormat('D, d M Y') }})
                </p>

                <!-- Shift Row (Work Tab) -->
                <div x-show="activeTab === 'work'">
                    <div class="bg-[#F8F9FB] border border-[#EEF0F5] rounded-2xl p-3 flex items-center justify-between">
                        <div class="flex items-center space-x-3 min-w-0">
                            <div class="w-9 h-9 rounded-full bg-[#FFF0F7] text-[#FF21A4] flex items-center justify-center flex-shrink-0">
                                <i class="fa-regular fa-clock text-base"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="font-bold text-[14px] text-[#1C1C1E] leading-snug truncate"
                                    x-text="selectedShiftName + ' (' + selectedShiftHours + ')'">
                                    {{ $activeShiftName }} ({{ $activeShiftStart }} - {{ $activeShiftEnd }})
                                </p>
                            </div>
                        </div>
                        @if(!$startEvent)
                            <button type="button" @click="showShiftPicker = true"
                                class="bg-[#FFF0F7] hover:bg-[#FFE2F1] text-[#FF21A4] text-xs font-bold px-3 py-1.5 rounded-full flex items-center gap-1 transition active:scale-95 flex-shrink-0 ml-2">
                                <span>Pilih</span>
                                <i class="fa-solid fa-chevron-right text-[10px]"></i>
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Shift Row (Break Tab) -->
                <div x-show="activeTab === 'break'" style="display: none;">
                    <div class="bg-[#F8F9FB] border border-[#EEF0F5] rounded-2xl p-3 flex items-center justify-between">
                        <div class="flex items-center space-x-3 min-w-0">
                            <div class="w-9 h-9 rounded-full bg-[#FFF0F7] text-[#FF21A4] flex items-center justify-center flex-shrink-0">
                                <i class="fa-solid fa-mug-hot text-sm"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="font-bold text-[14px] text-[#1C1C1E] leading-snug truncate">
                                    Waktu Istirahat [{{ $displayBreakStart }} - {{ $displayBreakEnd }}]
                                </p>
                            </div>
                        </div>
                        <div class="flex-shrink-0 ml-2">
                            @if(!$startBreak)
                                <span class="text-[11px] font-bold text-[#FF21A4] bg-[#FFF0F7] px-2.5 py-1 rounded-full">Belum Mulai</span>
                            @elseif(!$endBreak)
                                <span class="text-[11px] font-bold text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-full">Sedang Istirahat</span>
                            @else
                                <span class="text-[11px] font-bold text-gray-500 bg-gray-100 px-2.5 py-1 rounded-full">Selesai</span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Divider -->
                <div class="border-t border-[#EEF0F5] my-4"></div>

                <!-- Two-Column Timings: Work Mode -->
                <div x-show="activeTab === 'work'" class="flex items-center">
                    <!-- Jam Masuk -->
                    <div class="flex-1 flex items-center space-x-2.5 min-w-0">
                        @if($startEvent && $startEvent->selfie_url && $startEvent->selfie_path !== 'dummy/path.jpg')
                            <img src="{{ $startEvent->selfie_url }}"
                                @click="openLightbox('{{ $startEvent->selfie_url }}', 'Foto Masuk Kerja', '{{ \Carbon\Carbon::parse($startEvent->timestamp)->translatedFormat('d M Y, H:i') }}')"
                                class="w-9 h-9 rounded-full object-cover border border-[#FF21A4]/40 cursor-pointer hover:scale-105 transition flex-shrink-0 shadow-sm"
                                title="Lihat Foto" onerror="this.onerror=null;this.src='{{ asset('placeholder.svg') }}';">
                        @else
                            <div class="w-9 h-9 rounded-full bg-[#FFF0F7] text-[#FF21A4] flex items-center justify-center flex-shrink-0">
                                <i class="fa-solid fa-briefcase text-xs"></i>
                            </div>
                        @endif
                        <div class="min-w-0">
                            <div class="flex items-center space-x-1">
                                <span class="text-[12px] text-[#8E8E93] font-normal truncate">Jam Masuk</span>
                                <span class="text-[11px] font-semibold text-[#FF21A4]" x-text="'(' + selectedShiftStart + ')'">
                                    ({{ $activeShiftStart }})
                                </span>
                            </div>
                            <div class="flex items-center space-x-1 mt-0.5">
                                @if($startEvent)
                                    @php
                                        $actualCheckIn = \Carbon\Carbon::parse($startEvent->timestamp);
                                        $actualCheckInHi = $actualCheckIn->format('H:i');
                                        $ruleEnabled = (bool) \App\Models\Setting::get('attendance_rule_enabled', false);
                                        $ruleMinutes = (int) \App\Models\Setting::get('attendance_rule_minutes', 15);
                                        $shiftStartTime = $activeShiftStart ?? '08:00';
                                        $scheduledCheckIn = \Carbon\Carbon::parse($actualCheckIn->toDateString() . ' ' . $shiftStartTime);
                                        if ($ruleEnabled && $ruleMinutes > 0) {
                                            $cutoff = $scheduledCheckIn->copy()->subMinutes($ruleMinutes);
                                            $isCheckInLate = $actualCheckIn->greaterThanOrEqualTo($cutoff);
                                        } else {
                                            $isCheckInLate = $actualCheckIn->greaterThan($scheduledCheckIn);
                                        }
                                    @endphp
                                    <span class="text-[16px] font-bold {{ $isCheckInLate ? 'text-red-500' : 'text-emerald-600' }} leading-tight">
                                        {{ $actualCheckInHi }}
                                    </span>
                                    <i class="fa-solid fa-circle-check {{ $isCheckInLate ? 'text-red-500' : 'text-emerald-600' }} text-[12px]"></i>
                                @else
                                    <span class="text-[16px] font-bold text-[#8E8E93] leading-tight">--:--</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Jam Pulang -->
                    <div class="flex-1 flex items-center space-x-2.5 pl-3 border-l border-[#EEF0F5] min-w-0">
                        @if($endEvent && $endEvent->selfie_url && $endEvent->selfie_path !== 'dummy/path.jpg')
                            <img src="{{ $endEvent->selfie_url }}"
                                @click="openLightbox('{{ $endEvent->selfie_url }}', 'Foto Pulang Kerja', '{{ \Carbon\Carbon::parse($endEvent->timestamp)->translatedFormat('d M Y, H:i') }}')"
                                class="w-9 h-9 rounded-full object-cover border border-[#FF21A4]/40 cursor-pointer hover:scale-105 transition flex-shrink-0 shadow-sm"
                                title="Lihat Foto" onerror="this.onerror=null;this.src='{{ asset('placeholder.svg') }}';">
                        @else
                            <div class="w-9 h-9 rounded-full bg-[#FFF0F7] text-[#FF21A4] flex items-center justify-center flex-shrink-0">
                                <i class="fa-solid fa-house text-xs"></i>
                            </div>
                        @endif
                        <div class="min-w-0">
                            <div class="flex items-center space-x-1">
                                <span class="text-[12px] text-[#8E8E93] font-normal truncate">Jam Pulang</span>
                                <span class="text-[11px] font-semibold text-[#FF21A4]" x-text="'(' + selectedShiftEnd + ')'">
                                    ({{ $activeShiftEnd }})
                                </span>
                            </div>
                            <div class="flex items-center space-x-1 mt-0.5">
                                @if($endEvent)
                                    <span class="text-[16px] font-bold text-emerald-600 leading-tight">
                                        {{ \Carbon\Carbon::parse($endEvent->timestamp)->format('H:i') }}
                                    </span>
                                    <i class="fa-solid fa-circle-check text-emerald-600 text-[12px]"></i>
                                @else
                                    <span class="text-[16px] font-bold text-[#8E8E93] leading-tight">--:--</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Two-Column Timings: Break Mode -->
                <div x-show="activeTab === 'break'" style="display: none;" class="flex items-center">
                    <!-- Mulai Istirahat -->
                    <div class="flex-1 flex items-center space-x-2.5 min-w-0">
                        @if($startBreak && $startBreak->selfie_url && $startBreak->selfie_path !== 'dummy/path.jpg')
                            <img src="{{ $startBreak->selfie_url }}"
                                @click="openLightbox('{{ $startBreak->selfie_url }}', 'Foto Mulai Istirahat', '{{ \Carbon\Carbon::parse($startBreak->timestamp)->translatedFormat('d M Y, H:i') }}')"
                                class="w-9 h-9 rounded-full object-cover border border-[#FF21A4]/40 cursor-pointer hover:scale-105 transition flex-shrink-0 shadow-sm"
                                title="Lihat Foto" onerror="this.onerror=null;this.src='{{ asset('placeholder.svg') }}';">
                        @else
                            <div class="w-9 h-9 rounded-full bg-[#FFF0F7] text-[#FF21A4] flex items-center justify-center flex-shrink-0">
                                <i class="fa-solid fa-mug-hot text-xs"></i>
                            </div>
                        @endif
                        <div class="min-w-0">
                            <div class="flex items-center space-x-1">
                                <span class="text-[12px] text-[#8E8E93] font-normal truncate">Mulai</span>
                                <span class="text-[11px] font-semibold text-[#FF21A4]">({{ $displayBreakStart }})</span>
                            </div>
                            <div class="flex items-center space-x-1 mt-0.5">
                                @if($startBreak)
                                    @php
                                        $actualStartBreakHi = \Carbon\Carbon::parse($startBreak->timestamp)->format('H:i');
                                        $isStartBreakLate = $actualStartBreakHi < $displayBreakStart || $actualStartBreakHi > $displayBreakEnd;
                                    @endphp
                                    <span class="text-[16px] font-bold {{ $isStartBreakLate ? 'text-red-500' : 'text-emerald-600' }} leading-tight">
                                        {{ $actualStartBreakHi }}
                                    </span>
                                    <i class="fa-solid fa-circle-check {{ $isStartBreakLate ? 'text-red-500' : 'text-emerald-600' }} text-[12px]"></i>
                                @else
                                    <span class="text-[16px] font-bold text-[#8E8E93] leading-tight">--:--</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Selesai Istirahat -->
                    <div class="flex-1 flex items-center space-x-2.5 pl-3 border-l border-[#EEF0F5] min-w-0">
                        @if($endBreak && $endBreak->selfie_url && $endBreak->selfie_path !== 'dummy/path.jpg')
                            <img src="{{ $endBreak->selfie_url }}"
                                @click="openLightbox('{{ $endBreak->selfie_url }}', 'Foto Selesai Istirahat', '{{ \Carbon\Carbon::parse($endBreak->timestamp)->translatedFormat('d M Y, H:i') }}')"
                                class="w-9 h-9 rounded-full object-cover border border-[#FF21A4]/40 cursor-pointer hover:scale-105 transition flex-shrink-0 shadow-sm"
                                title="Lihat Foto" onerror="this.onerror=null;this.src='{{ asset('placeholder.svg') }}';">
                        @else
                            <div class="w-9 h-9 rounded-full bg-[#FFF0F7] text-[#FF21A4] flex items-center justify-center flex-shrink-0">
                                <i class="fa-solid fa-briefcase text-xs"></i>
                            </div>
                        @endif
                        <div class="min-w-0">
                            <div class="flex items-center space-x-1">
                                <span class="text-[12px] text-[#8E8E93] font-normal truncate">Selesai</span>
                                <span class="text-[11px] font-semibold text-[#FF21A4]">({{ $displayBreakEnd }})</span>
                            </div>
                            <div class="flex items-center space-x-1 mt-0.5">
                                @if($endBreak)
                                    @php
                                        $actualEndBreakHi = \Carbon\Carbon::parse($endBreak->timestamp)->format('H:i');
                                        $isEndBreakLate = $actualEndBreakHi < $displayBreakStart || $actualEndBreakHi > $displayBreakEnd;
                                    @endphp
                                    <span class="text-[16px] font-bold {{ $isEndBreakLate ? 'text-red-500' : 'text-emerald-600' }} leading-tight">
                                        {{ $actualEndBreakHi }}
                                    </span>
                                    <i class="fa-solid fa-circle-check {{ $isEndBreakLate ? 'text-red-500' : 'text-emerald-600' }} text-[12px]"></i>
                                @else
                                    <span class="text-[16px] font-bold text-[#8E8E93] leading-tight">--:--</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Button -->
                <div class="mt-5">
                    <!-- Work Mode Action -->
                    <div x-show="activeTab === 'work'">
                        @if(!$startEvent)
                            <form action="{{ route('attendance.store') }}" method="POST" id="attendanceForm" enctype="multipart/form-data">
                                @csrf
                                <input type="hidden" name="latitude" id="lat">
                                <input type="hidden" name="longitude" id="lng">
                                <input type="hidden" name="event_type" value="START_SHIFT">
                                <input type="hidden" name="shift_id" :value="selectedShiftId">
                                <input type="file" name="selfie" id="selfie_input" accept="image/*" capture="user" class="hidden" required>
                                <button type="button" onclick="openCameraUI('attendanceForm')"
                                    class="w-full h-[50px] bg-[#FF21A4] hover:bg-[#E01890] text-white font-bold text-base rounded-[14px] flex items-center justify-center gap-2.5 shadow-[0_4px_16px_rgba(255,33,164,0.35)] transition active:scale-[0.98]">
                                    <i class="fa-solid fa-briefcase text-base"></i>
                                    <span>Masuk Kerja</span>
                                </button>
                            </form>
                        @elseif(!$endEvent)
                            @php
                                $hasActiveBreak = $events->contains(fn($e) => ($e->event_type?->value ?? $e->event_type) === 'START_BREAK') && !$events->contains(fn($e) => ($e->event_type?->value ?? $e->event_type) === 'END_BREAK');
                            @endphp
                            <form action="{{ route('attendance.store') }}" method="POST" id="attendanceForm" enctype="multipart/form-data">
                                @csrf
                                <input type="hidden" name="latitude" id="lat">
                                <input type="hidden" name="longitude" id="lng">
                                <input type="hidden" name="event_type" value="END_SHIFT">
                                <input type="hidden" name="shift_id" :value="selectedShiftId">
                                <input type="file" name="selfie" id="selfie_input" accept="image/*" capture="user" class="hidden" required>
                                @if($hasActiveBreak)
                                    <button type="button" disabled
                                        class="w-full h-[50px] bg-[#E5E7EB] text-[#9CA3AF] font-bold text-sm rounded-[14px] flex flex-col items-center justify-center cursor-not-allowed">
                                        <span>Pulang Kerja</span>
                                        <span class="text-[10px] font-normal">Selesaikan istirahat terlebih dahulu</span>
                                    </button>
                                @else
                                    <button type="button" onclick="openCameraUI('attendanceForm')"
                                        class="w-full h-[50px] bg-[#FF21A4] hover:bg-[#E01890] text-white font-bold text-base rounded-[14px] flex items-center justify-center gap-2.5 shadow-[0_4px_16px_rgba(255,33,164,0.35)] transition active:scale-[0.98]">
                                        <i class="fa-solid fa-house text-base"></i>
                                        <span>Pulang Kerja</span>
                                    </button>
                                @endif
                            </form>
                        @else
                            <button type="button" disabled
                                class="w-full h-[50px] bg-[#E5E7EB] text-[#9CA3AF] font-bold text-base rounded-[14px] flex items-center justify-center cursor-not-allowed">
                                Shift Selesai
                            </button>
                        @endif
                    </div>

                    <!-- Break Mode Action -->
                    <div x-show="activeTab === 'break'" style="display: none;">
                        @php
                            $shiftNotStarted = !$events->contains(fn($e) => ($e->event_type?->value ?? $e->event_type) === 'START_SHIFT');
                            $shiftEnded = $events->contains(fn($e) => ($e->event_type?->value ?? $e->event_type) === 'END_SHIFT');
                        @endphp
                        <form action="{{ route('attendance.store') }}" method="POST" id="breakForm" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="latitude" id="lat2">
                            <input type="hidden" name="longitude" id="lng2">
                            <input type="hidden" name="shift_id" :value="selectedShiftId">
                            <input type="file" name="selfie" id="break_selfie_input" accept="image/*" capture="user" class="hidden" required>

                            @if($shiftEnded)
                                <button type="button" disabled
                                    class="w-full h-[50px] bg-[#E5E7EB] text-[#9CA3AF] font-bold text-sm rounded-[14px] flex flex-col items-center justify-center cursor-not-allowed">
                                    <span>Mulai Istirahat</span>
                                    <span class="text-[10px] font-normal">Shift telah selesai</span>
                                </button>
                            @elseif(!$startBreak)
                                <input type="hidden" name="event_type" value="START_BREAK">
                                @if($shiftNotStarted)
                                    <button type="button" disabled
                                        class="w-full h-[50px] bg-[#E5E7EB] text-[#9CA3AF] font-bold text-sm rounded-[14px] flex flex-col items-center justify-center cursor-not-allowed">
                                        <span>Mulai Istirahat</span>
                                        <span class="text-[10px] font-normal">Mulai shift terlebih dahulu</span>
                                    </button>
                                @else
                                    <button type="button" onclick="openCameraUI('breakForm')"
                                        class="w-full h-[50px] bg-[#FF21A4] hover:bg-[#E01890] text-white font-bold text-base rounded-[14px] flex items-center justify-center gap-2.5 shadow-[0_4px_16px_rgba(255,33,164,0.35)] transition active:scale-[0.98]">
                                        <i class="fa-solid fa-mug-hot text-base"></i>
                                        <span>Mulai Istirahat</span>
                                    </button>
                                @endif
                            @elseif(!$endBreak)
                                <input type="hidden" name="event_type" value="END_BREAK">
                                <button type="button" onclick="openCameraUI('breakForm')"
                                    class="w-full h-[50px] bg-[#FF21A4] hover:bg-[#E01890] text-white font-bold text-base rounded-[14px] flex items-center justify-center gap-2.5 shadow-[0_4px_16px_rgba(255,33,164,0.35)] transition active:scale-[0.98]">
                                    <i class="fa-solid fa-briefcase text-base"></i>
                                    <span>Selesai Istirahat</span>
                                </button>
                            @else
                                <button type="button" disabled
                                    class="w-full h-[50px] bg-[#E5E7EB] text-[#9CA3AF] font-bold text-base rounded-[14px] flex items-center justify-center cursor-not-allowed">
                                    Istirahat Selesai
                                </button>
                            @endif
                        </form>
                    </div>
                </div>
            </div>

            <!-- Activity History Card ("Aktivitas Hari Ini") -->
            <div class="tn-card p-5 mb-8">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="text-base font-bold text-[#1C1C1E]">Aktivitas Hari Ini</h3>
                    <a href="{{ route('attendance.history') }}"
                        @click.prevent="currentNav = 'history'; window.history.pushState({}, '', '{{ route('attendance.history') }}')"
                        class="text-xs font-semibold text-[#FF21A4] hover:underline flex items-center gap-1 transition">
                        <span>Riwayat Lengkap</span>
                        <i class="fa-solid fa-chevron-right text-[10px]"></i>
                    </a>
                </div>

                @if($events->isEmpty())
                    <!-- Exact empty state matching screenshot -->
                    <div class="py-9 flex flex-col items-center justify-center text-center">
                        <div class="relative w-12 h-12 flex items-center justify-center mb-2">
                            <!-- Clock circle icon with badge xmark -->
                            <svg class="w-11 h-11 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="9"></circle>
                                <polyline points="12 7 12 12 15 14"></polyline>
                            </svg>
                            <span class="absolute -bottom-0.5 -right-0.5 bg-white rounded-full p-0.5 shadow-sm">
                                <i class="fa-solid fa-circle-xmark text-gray-400 text-xs"></i>
                            </span>
                        </div>
                        <p class="text-[13px] text-[#8E8E93] font-normal">Belum ada riwayat presensi hari ini.</p>
                    </div>
                @else
                    <div class="divide-y divide-[#EEF0F5]">
                        @foreach($events as $event)
                            @php
                                $type = $event->event_type?->value ?? $event->event_type;
                                $title = match($type) {
                                    'START_SHIFT' => 'Masuk Kerja',
                                    'END_SHIFT' => 'Pulang Kerja',
                                    'START_BREAK' => 'Mulai Istirahat',
                                    'END_BREAK' => 'Selesai Istirahat',
                                    default => $type
                                };
                                $icon = match($type) {
                                    'START_SHIFT' => 'fa-briefcase',
                                    'END_SHIFT' => 'fa-house',
                                    'START_BREAK' => 'fa-mug-hot',
                                    'END_BREAK' => 'fa-briefcase',
                                    default => 'fa-clock'
                                };
                            @endphp
                            <div class="py-3 flex items-center justify-between">
                                <div class="flex items-center space-x-3 min-w-0">
                                    @if($event->selfie_url && $event->selfie_path !== 'dummy/path.jpg')
                                        <img src="{{ $event->selfie_url }}"
                                            @click="openLightbox('{{ $event->selfie_url }}', '{{ $title }}', '{{ \Carbon\Carbon::parse($event->timestamp)->translatedFormat('d M Y, H:i') }}')"
                                            class="w-11 h-11 rounded-full object-cover border border-gray-200 cursor-pointer hover:scale-105 transition flex-shrink-0 shadow-sm"
                                            title="Lihat Foto" onerror="this.onerror=null;this.src='{{ asset('placeholder.svg') }}';">
                                    @else
                                        <div class="w-11 h-11 rounded-full bg-[#FFF0F7] text-[#FF21A4] flex items-center justify-center flex-shrink-0">
                                            <i class="fa-solid {{ $icon }} text-sm"></i>
                                        </div>
                                    @endif
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-[#1C1C1E] truncate">{{ $title }}</p>
                                        <p class="text-xs text-[#8E8E93]">{{ \Carbon\Carbon::parse($event->timestamp)->translatedFormat('d M Y') }}</p>
                                        @if($event->latitude && $event->longitude)
                                            <button type="button"
                                                @click="openMapModal({{ $event->latitude }}, {{ $event->longitude }}, '{{ $title }}', '{{ \Carbon\Carbon::parse($event->timestamp)->translatedFormat('d M Y, H:i') }}')"
                                                class="inline-flex items-center space-x-1 px-2.5 py-0.5 mt-0.5 rounded-full text-[10px] font-semibold bg-[#FFF0F7] text-[#FF21A4] hover:bg-[#FFE2F1] transition active:scale-95">
                                                <i class="fa-solid fa-location-dot text-[9px]"></i>
                                                <span>Titik Lokasi</span>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                                <div class="text-right flex-shrink-0 ml-3">
                                    <p class="text-[15px] font-bold text-[#1C1C1E]">
                                        {{ \Carbon\Carbon::parse($event->timestamp)->format('H:i') }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>

        <!-- ==================== HISTORY VIEW (RIWAYAT) ==================== -->
        <div x-show="currentNav === 'history'" x-cloak x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-98" x-transition:enter-end="opacity-100 scale-100" class="pt-2 pb-8">
            
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-[#1C1C1E]">Riwayat Presensi</h2>
                <button type="button" @click="showFilterSheet = !showFilterSheet"
                    class="px-3 py-1.5 rounded-full text-xs font-bold border border-gray-200 bg-white shadow-sm flex items-center gap-1.5 transition active:scale-95"
                    :class="showFilterSheet ? 'text-[#FF21A4] border-[#FF21A4]' : 'text-[#1C1C1E]'">
                    <i class="fa-solid fa-sliders text-xs"></i>
                    <span>Filter</span>
                </button>
            </div>

            <!-- Filter Panel -->
            <div x-data="{ showFilterSheet: {{ request()->hasAny(['start_date', 'end_date']) ? 'true' : 'false' }} }">
                <div x-show="showFilterSheet" style="display: none;" class="tn-card p-4 mb-4">
                    <form method="GET" action="{{ route('attendance.history') }}" class="space-y-3">
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[11px] font-semibold text-[#8E8E93] mb-1">Dari Tanggal</label>
                                <input type="date" name="start_date" value="{{ request('start_date') }}"
                                    class="w-full bg-[#F6F7FA] border border-gray-200 rounded-xl text-xs text-[#1C1C1E] px-3 py-2 focus:outline-none focus:border-[#FF21A4]">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-[#8E8E93] mb-1">Sampai Tanggal</label>
                                <input type="date" name="end_date" value="{{ request('end_date') }}"
                                    class="w-full bg-[#F6F7FA] border border-gray-200 rounded-xl text-xs text-[#1C1C1E] px-3 py-2 focus:outline-none focus:border-[#FF21A4]">
                            </div>
                        </div>
                        <div class="flex gap-2 pt-1">
                            <button type="submit"
                                class="flex-1 bg-[#FF21A4] text-white py-2 rounded-xl text-xs font-bold shadow-sm hover:bg-[#E01890] transition">
                                Terapkan Filter
                            </button>
                            @if(request()->hasAny(['start_date', 'end_date']))
                                <a href="{{ route('attendance.history') }}"
                                    class="px-4 py-2 bg-gray-100 text-gray-700 rounded-xl text-xs font-semibold hover:bg-gray-200 transition flex items-center justify-center">
                                    Reset
                                </a>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            <!-- Attendance History List -->
            <div class="space-y-3.5">
                @forelse($attendancesHistory as $att)
                    <div class="tn-card p-4">
                        <div class="flex justify-between items-center mb-3">
                            <div>
                                <p class="font-bold text-[14px] text-[#1C1C1E]">
                                    {{ \Carbon\Carbon::parse($att->date)->translatedFormat('l, d M Y') }}
                                </p>
                                <p class="text-[11px] text-[#8E8E93] mt-0.5">
                                    Status: 
                                    <span class="font-bold {{ $att->status === 'PRESENT' ? 'text-emerald-600' : ($att->status === 'ABSENT' ? 'text-rose-600' : 'text-amber-600') }}">
                                        {{ $att->status }}
                                    </span>
                                </p>
                            </div>
                        </div>

                        <!-- Shift Times Box -->
                        <div class="bg-[#F8F9FB] border border-[#EEF0F5] rounded-xl p-3 flex items-center justify-between">
                            <!-- Check In -->
                            <div class="text-center w-1/3 flex flex-col items-center">
                                <span class="text-[10px] text-[#8E8E93] uppercase tracking-wider mb-1">Masuk</span>
                                @php $inEvent = $att->events->where('event_type', \App\Enums\EventType::START_SHIFT)->first() ?? $att->events->where('event_type', 'START_SHIFT')->first(); @endphp
                                @if($inEvent && $inEvent->selfie_url && $inEvent->selfie_path !== 'dummy/path.jpg')
                                    <img src="{{ $inEvent->selfie_url }}"
                                        @click="openLightbox('{{ $inEvent->selfie_url }}', 'Masuk ({{ \Carbon\Carbon::parse($att->date)->translatedFormat('d M Y') }})', '{{ \Carbon\Carbon::parse($inEvent->timestamp)->format('H:i') }} WIB')"
                                        class="w-10 h-10 rounded-full object-cover border-2 border-emerald-500 shadow-sm my-1 cursor-pointer hover:scale-105 transition"
                                        alt="Selfie Masuk">
                                @endif
                                <p class="font-bold text-sm {{ $inEvent ? 'text-[#1C1C1E]' : 'text-gray-400' }}">
                                    {{ $inEvent ? \Carbon\Carbon::parse($inEvent->timestamp)->format('H:i') : '--:--' }}
                                </p>
                                @if($inEvent && $inEvent->latitude && $inEvent->longitude)
                                    <button type="button"
                                        @click="openMapModal({{ $inEvent->latitude }}, {{ $inEvent->longitude }}, 'Masuk ({{ \Carbon\Carbon::parse($att->date)->translatedFormat('d M Y') }})', '{{ \Carbon\Carbon::parse($inEvent->timestamp)->format('H:i') }} WIB')"
                                        class="text-[9px] font-semibold text-[#FF21A4] bg-[#FFF0F7] px-2 py-0.5 rounded-full mt-1 hover:bg-[#FFE2F1] transition">
                                        <i class="fa-solid fa-location-dot"></i> Titik
                                    </button>
                                @endif
                            </div>

                            <div class="text-gray-300">
                                <i class="fa-solid fa-arrow-right text-xs"></i>
                            </div>

                            <!-- Check Out -->
                            <div class="text-center w-1/3 flex flex-col items-center">
                                <span class="text-[10px] text-[#8E8E93] uppercase tracking-wider mb-1">Pulang</span>
                                @php $outEvent = $att->events->where('event_type', \App\Enums\EventType::END_SHIFT)->first() ?? $att->events->where('event_type', 'END_SHIFT')->first(); @endphp
                                @if($outEvent && $outEvent->selfie_url && $outEvent->selfie_path !== 'dummy/path.jpg')
                                    <img src="{{ $outEvent->selfie_url }}"
                                        @click="openLightbox('{{ $outEvent->selfie_url }}', 'Pulang ({{ \Carbon\Carbon::parse($att->date)->translatedFormat('d M Y') }})', '{{ \Carbon\Carbon::parse($outEvent->timestamp)->format('H:i') }} WIB')"
                                        class="w-10 h-10 rounded-full object-cover border-2 border-[#FF21A4] shadow-sm my-1 cursor-pointer hover:scale-105 transition"
                                        alt="Selfie Pulang">
                                @endif
                                <p class="font-bold text-sm {{ $outEvent ? 'text-[#1C1C1E]' : 'text-gray-400' }}">
                                    {{ $outEvent ? \Carbon\Carbon::parse($outEvent->timestamp)->format('H:i') : '--:--' }}
                                </p>
                                @if($outEvent && $outEvent->latitude && $outEvent->longitude)
                                    <button type="button"
                                        @click="openMapModal({{ $outEvent->latitude }}, {{ $outEvent->longitude }}, 'Pulang ({{ \Carbon\Carbon::parse($att->date)->translatedFormat('d M Y') }})', '{{ \Carbon\Carbon::parse($outEvent->timestamp)->format('H:i') }} WIB')"
                                        class="text-[9px] font-semibold text-[#FF21A4] bg-[#FFF0F7] px-2 py-0.5 rounded-full mt-1 hover:bg-[#FFE2F1] transition">
                                        <i class="fa-solid fa-location-dot"></i> Titik
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="tn-card p-10 text-center">
                        <i class="fa-solid fa-calendar-xmark text-3xl text-gray-300 mb-2"></i>
                        <p class="text-sm font-medium text-[#8E8E93]">Belum ada riwayat kehadiran.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- ==================== PROFILE VIEW (PROFIL) ==================== -->
        <div x-show="currentNav === 'profile'" x-cloak x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-98" x-transition:enter-end="opacity-100 scale-100" class="pt-2 pb-8">
            
            <h2 class="text-xl font-bold text-[#1C1C1E] mb-4">Profil Saya</h2>

            <!-- Avatar & Main Info -->
            <div class="tn-card p-6 text-center mb-4">
                <img src="{{ $user->avatar_url }}"
                    class="w-20 h-20 rounded-full object-cover border-2 border-gray-200 mx-auto mb-3 shadow-sm"
                    alt="{{ $user->name }}">
                <h3 class="text-lg font-bold text-[#1C1C1E]">{{ $user->name }}</h3>
                <p class="text-xs text-[#8E8E93] mt-0.5">{{ $user->email }}</p>

                <div class="mt-4 flex justify-center gap-2">
                    <button type="button" @click="showEditProfileModal = true"
                        class="px-4 py-1.5 bg-[#FFF0F7] text-[#FF21A4] rounded-full text-xs font-bold hover:bg-[#FFE2F1] transition active:scale-95 shadow-sm">
                        <i class="fa-solid fa-pen-to-square mr-1"></i> Edit Profil
                    </button>
                    @if($user->isAdmin())
                        <span class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-full text-[10px] font-bold uppercase tracking-wider">
                            Admin/HR
                        </span>
                    @endif
                </div>
            </div>

            <!-- Profile Details List -->
            <div class="tn-card p-4 mb-4 divide-y divide-[#EEF0F5]">
                <div class="py-2.5 flex justify-between items-center text-xs">
                    <span class="text-[#8E8E93]">Nama Lengkap</span>
                    <span class="font-bold text-[#1C1C1E]">{{ $user->name }}</span>
                </div>
                <div class="py-2.5 flex justify-between items-center text-xs">
                    <span class="text-[#8E8E93]">Email</span>
                    <span class="font-semibold text-[#1C1C1E]">{{ $user->email }}</span>
                </div>
                <div class="py-2.5 flex justify-between items-center text-xs">
                    <span class="text-[#8E8E93]">No. Telepon</span>
                    <span class="font-semibold text-[#1C1C1E]">{{ $user->phone ?? '-' }}</span>
                </div>
                <div class="py-2.5 flex justify-between items-center text-xs">
                    <span class="text-[#8E8E93]">ID Karyawan</span>
                    <span class="font-semibold text-[#1C1C1E]">{{ $user->employee_id ?? '-' }}</span>
                </div>
                <div class="py-2.5 flex justify-between items-center text-xs">
                    <span class="text-[#8E8E93]">Lokasi Kerja</span>
                    <span class="font-semibold text-[#1C1C1E]">{{ $user->location->name ?? '-' }}</span>
                </div>
            </div>

            <!-- Sign Out Button -->
            <button type="button" @click="showLogoutModal = true"
                class="w-full bg-white border border-rose-200 text-rose-600 font-bold py-3.5 rounded-2xl shadow-sm hover:bg-rose-50 transition active:scale-98 flex items-center justify-center gap-2">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                <span>Keluar Akun</span>
            </button>
        </div>

    </div>

    <!-- ==================== FULL-BLEED LIVE MAP VIEW (Matching iOS AttendanceMapView.swift) ==================== -->
    <div x-show="currentNav === 'map'" x-cloak class="fixed inset-0 z-10 flex flex-col justify-between overflow-hidden">
        <!-- Fullscreen Leaflet Map Container -->
        <div id="liveAttendanceMap" class="absolute inset-0 w-full h-full z-0 bg-[#E5E9EE]"></div>

        <!-- Top Floating Office Capsule Bar -->
        <div class="relative z-20 pt-4 px-4 max-w-md mx-auto w-full pointer-events-none">
            <div class="bg-white/95 backdrop-blur-md rounded-full shadow-[0_4px_20px_rgba(0,0,0,0.08)] border border-gray-100/80 px-4 py-2.5 flex items-center justify-between pointer-events-auto">
                <div class="flex items-center gap-2.5 min-w-0 pr-2">
                    <div class="w-7 h-7 rounded-full bg-pink-50 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-building text-[#FF21A4] text-xs"></i>
                    </div>
                    <span class="text-[13px] font-bold text-[#1C1C1E] truncate">
                        {{ $user->location->name ?? 'Lokasi Kantor Belum Diatur' }}
                    </span>
                </div>
                <button type="button" @click="refreshLiveMapLocation()"
                    class="w-8 h-8 rounded-full bg-pink-50 hover:bg-pink-100 text-[#FF21A4] flex items-center justify-center transition active:scale-92 shrink-0 shadow-xs"
                    title="Muat Ulang Lokasi">
                    <i class="fa-solid fa-arrow-rotate-right text-xs"></i>
                </button>
            </div>
        </div>

        <!-- Right Floating Map Controls -->
        <div class="absolute right-4 bottom-64 z-20 flex flex-col pointer-events-auto">
            <div class="bg-white/95 backdrop-blur-md rounded-2xl shadow-[0_4px_16px_rgba(0,0,0,0.1)] border border-gray-100/90 flex flex-col overflow-hidden">
                <button type="button" @click="centerOnUserLiveMap()"
                    class="w-11 h-11 flex items-center justify-center text-[#FF21A4] hover:bg-pink-50 transition active:scale-92"
                    title="Pusatkan Lokasi Saya">
                    <i class="fa-solid fa-location-arrow text-[15px]"></i>
                </button>
                <div class="h-[1px] bg-gray-100 w-full"></div>
                <button type="button" @click="fitLiveMapBounds()"
                    class="w-11 h-11 flex items-center justify-center text-[#1C1C1E] hover:bg-gray-50 transition active:scale-92"
                    title="Tampilkan Kantor & Lokasi">
                    <i class="fa-solid fa-up-right-and-down-left-from-center text-[13px]"></i>
                </button>
            </div>
        </div>

        <!-- Bottom Floating Attendance Card -->
        <div class="relative z-20 pb-20 px-4 max-w-md mx-auto w-full pointer-events-none">
            <div class="bg-white/98 backdrop-blur-md rounded-[26px] shadow-[0_8px_30px_rgba(0,0,0,0.12)] border border-gray-100/90 p-4 pointer-events-auto transition-all">
                <!-- Status Row -->
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <span class="relative flex h-3.5 w-3.5 shrink-0">
                            <span :class="locationValid ? 'bg-emerald-400' : 'bg-rose-400'"
                                class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75"></span>
                            <span :class="locationValid ? 'bg-emerald-500' : 'bg-rose-500'"
                                class="relative inline-flex rounded-full h-3.5 w-3.5"></span>
                        </span>
                        <div class="flex flex-col min-w-0">
                            <span class="text-[13px] font-bold leading-tight"
                                :class="locationValid ? 'text-emerald-600' : 'text-rose-600'"
                                x-text="locationValid ? 'Dalam Radius Presensi' : 'Di Luar Radius Presensi'">
                                Dalam Radius Presensi
                            </span>
                            <span class="text-[11px] text-[#8E8E93] leading-tight mt-0.5 truncate"
                                x-text="locationDistanceText">
                                Menghitung jarak...
                            </span>
                        </div>
                    </div>

                    <!-- Rute Button -->
                    <a :href="officeLat && officeLng ? 'https://www.google.com/maps/dir/?api=1&destination=' + officeLat + ',' + officeLng : '#'"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="px-3.5 py-1.5 rounded-full bg-[#FFF0F7] hover:bg-[#FFE0F0] text-[#FF21A4] text-xs font-bold flex items-center gap-1.5 shrink-0 transition active:scale-95 shadow-xs">
                        <i class="fa-solid fa-diamond-turn-right text-[11px]"></i>
                        <span>Rute</span>
                    </a>
                </div>

                <!-- Shift Info Row -->
                <div class="bg-[#F6F7FA] rounded-xl px-3 py-2 flex items-center gap-2 text-xs text-[#1C1C1E] font-medium mb-3">
                    <i class="fa-regular fa-clock text-gray-400 text-xs shrink-0"></i>
                    <span class="truncate">
                        Shift: {{ $shift->shift_name ?? 'OFFICE' }} [{{ $activeShiftStart }} - {{ $activeShiftEnd }}]
                    </span>
                </div>

                <!-- Action Button -->
                <button type="button" @click="triggerAttendanceFromMap()"
                    :disabled="!locationValid && !{{ $endEvent ? 'true' : 'false' }}"
                    class="w-full bg-[#FF21A4] hover:bg-[#E00084] disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold py-3.5 rounded-2xl shadow-sm hover:shadow-md transition active:scale-[0.99] flex items-center justify-center gap-2.5 text-[14px]">
                    <i class="fa-solid fa-camera text-base"></i>
                    <span x-text="currentMapActionLabel">Ambil Presensi: Pulang Kerja</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ==================== FLOATING BOTTOM NAVIGATION BAR ==================== -->
    <nav class="fixed bottom-3 left-4 right-4 max-w-md mx-auto bg-white/95 backdrop-blur-md rounded-full shadow-[0_8px_30px_rgba(0,0,0,0.08)] border border-gray-100/90 px-3 py-2 flex justify-around items-center z-50">
        <!-- Beranda (Home) -->
        <a href="{{ route('attendance.index') }}"
            @click.prevent="currentNav = 'home'; window.history.pushState({}, '', '{{ route('attendance.index') }}')"
            :class="currentNav === 'home' ? 'bg-[#FFF0F7] text-[#FF21A4]' : 'text-[#1C1C1E] hover:text-black'"
            class="flex flex-col items-center justify-center py-1 px-4 rounded-full transition-all duration-200">
            <i class="fa-solid fa-house text-[17px] mb-0.5"></i>
            <span class="text-[10px] font-bold">Beranda</span>
        </a>

        <!-- Peta (Map) -->
        <button type="button"
            @click="currentNav = 'map'; initLiveMap();"
            :class="currentNav === 'map' ? 'bg-[#FFF0F7] text-[#FF21A4]' : 'text-[#1C1C1E] hover:text-black'"
            class="flex flex-col items-center justify-center py-1 px-4 rounded-full transition-all duration-200">
            <i class="fa-solid fa-map text-[17px] mb-0.5"></i>
            <span class="text-[10px] font-bold">Peta</span>
        </button>

        <!-- Riwayat (History) -->
        <a href="{{ route('attendance.history') }}"
            @click.prevent="currentNav = 'history'; window.history.pushState({}, '', '{{ route('attendance.history') }}')"
            :class="currentNav === 'history' ? 'bg-[#FFF0F7] text-[#FF21A4]' : 'text-[#1C1C1E] hover:text-black'"
            class="flex flex-col items-center justify-center py-1 px-4 rounded-full transition-all duration-200">
            <i class="fa-solid fa-clock-rotate-left text-[17px] mb-0.5"></i>
            <span class="text-[10px] font-bold">Riwayat</span>
        </a>

        <!-- Profil (Profile) -->
        <a href="{{ route('attendance.profile') }}"
            @click.prevent="currentNav = 'profile'; window.history.pushState({}, '', '{{ route('attendance.profile') }}')"
            :class="currentNav === 'profile' ? 'bg-[#FFF0F7] text-[#FF21A4]' : 'text-[#1C1C1E] hover:text-black'"
            class="flex flex-col items-center justify-center py-1 px-4 rounded-full transition-all duration-200">
            <i class="fa-solid fa-circle-user text-[17px] mb-0.5"></i>
            <span class="text-[10px] font-bold">Profil</span>
        </a>
    </nav>

    <!-- ==================== MODALS ==================== -->

    <!-- 1. SHIFT PICKER MODAL (Matching ShiftPickerView.swift) -->
    <div x-show="showShiftPicker" x-cloak style="display: none;"
        class="fixed inset-0 z-[140] flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/50 backdrop-blur-sm"
        @keydown.escape.window="showShiftPicker = false"
        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">

        <div class="relative w-full max-w-md bg-white rounded-t-[28px] sm:rounded-[28px] shadow-2xl p-6 flex flex-col max-h-[85vh] overflow-hidden"
            @click.away="showShiftPicker = false"
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full sm:scale-95"
            x-transition:enter-end="translate-y-0 sm:scale-100">

            <!-- Sheet Handle / Header -->
            <div class="w-12 h-1.5 bg-gray-200 rounded-full mx-auto mb-4 sm:hidden"></div>

            <div class="text-center mb-5">
                <div class="w-12 h-12 rounded-full bg-[#FFF0F7] text-[#FF21A4] flex items-center justify-center mx-auto mb-2.5">
                    <i class="fa-solid fa-clock text-xl"></i>
                </div>
                <h3 class="text-lg font-bold text-[#1C1C1E]">Pilih Shift Anda</h3>
                <p class="text-xs text-[#8E8E93] mt-1 px-4">
                    Pilih shift yang sedang Anda jalani hari ini sebelum melakukan presensi masuk.
                </p>
            </div>

            <!-- Shifts List -->
            <div class="overflow-y-auto space-y-2.5 my-2 flex-1 pr-1">
                @foreach($availableShifts as $s)
                    @php
                        $sStart = \Carbon\Carbon::parse($s->default_start_time)->format('H:i');
                        $sEnd = \Carbon\Carbon::parse($s->default_end_time)->format('H:i');
                    @endphp
                    <div @click="selectedShiftId = {{ $s->id }}; selectedShiftName = '{{ addslashes($s->shift_name) }}'; selectedShiftHours = '{{ $sStart }} - {{ $sEnd }}'; selectedShiftStart = '{{ $sStart }}'; selectedShiftEnd = '{{ $sEnd }}'"
                        class="p-4 rounded-2xl border cursor-pointer transition flex items-center justify-between"
                        :class="selectedShiftId === {{ $s->id }} ? 'border-[#FF21A4] bg-[#FFF0F7]/40 ring-1 ring-[#FF21A4]' : 'border-gray-200 bg-white hover:border-gray-300'">
                        <div class="flex items-center space-x-3">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center"
                                :class="selectedShiftId === {{ $s->id }} ? 'bg-[#FF21A4] text-white' : 'bg-gray-100 text-gray-500'">
                                <i class="fa-solid fa-business-time text-sm"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-sm text-[#1C1C1E]">{{ $s->shift_name }}</h4>
                                <p class="text-xs text-[#8E8E93]">{{ $sStart }} - {{ $sEnd }} WIB</p>
                            </div>
                        </div>
                        <div class="w-5 h-5 rounded-full border flex items-center justify-center"
                            :class="selectedShiftId === {{ $s->id }} ? 'border-[#FF21A4] bg-[#FF21A4] text-white' : 'border-gray-300'">
                            <i x-show="selectedShiftId === {{ $s->id }}" class="fa-solid fa-check text-[10px]"></i>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Action Buttons -->
            <div class="pt-4 flex gap-2 border-t border-gray-100 mt-2">
                <button type="button" @click="showShiftPicker = false"
                    class="flex-1 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-sm rounded-xl transition">
                    Batal
                </button>
                <button type="button" @click="showShiftPicker = false"
                    class="flex-1 py-3 bg-[#FF21A4] hover:bg-[#E01890] text-white font-bold text-sm rounded-xl transition shadow-md shadow-pink-500/20">
                    Pilih Shift Ini
                </button>
            </div>
        </div>
    </div>

    <!-- 2. LOGOUT CONFIRMATION MODAL (Matching iOS Alert) -->
    <div x-show="showLogoutModal" x-cloak style="display: none;"
        class="fixed inset-0 z-[150] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
        @keydown.escape.window="showLogoutModal = false">
        <div class="relative w-full max-w-xs bg-white rounded-2xl shadow-2xl p-5 text-center"
            @click.away="showLogoutModal = false">
            <h3 class="text-base font-bold text-[#1C1C1E] mb-1">Keluar Akun</h3>
            <p class="text-xs text-[#8E8E93] mb-5">Apakah Anda yakin ingin keluar dari akun ini?</p>

            <div class="flex gap-2">
                <button type="button" @click="showLogoutModal = false"
                    class="flex-1 py-2.5 rounded-xl border border-gray-200 text-xs font-bold text-gray-700 hover:bg-gray-50 transition">
                    Batal
                </button>
                <form method="POST" action="{{ route('logout') }}" class="flex-1">
                    @csrf
                    <button type="submit"
                        class="w-full py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold transition shadow-sm">
                        Keluar
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- 3. CAMERA MODAL -->
    <div x-show="showCamera" style="display: none;"
        class="fixed inset-0 bg-black z-[160] flex flex-col justify-between">
        <!-- Header -->
        <div class="flex items-center justify-between px-5 py-5 bg-black w-full z-10">
            <h2 class="text-white font-semibold text-lg">Ambil Foto Selfie</h2>
            <button @click="closeCameraUI()" class="text-white p-2">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>

        <!-- Video Stream -->
        <div class="relative w-full aspect-[3/4] bg-black flex-shrink-0">
            <video id="cameraStream" autoplay playsinline
                class="absolute inset-0 w-full h-full object-cover transform -scale-x-100"></video>
            <!-- Headroom Overlay Guide -->
            <img src="{{ asset('Headroom.png') }}"
                class="absolute inset-0 w-full h-full object-contain pointer-events-none z-10 opacity-70">
            <canvas id="cameraCanvas" class="hidden"></canvas>
        </div>

        <!-- Capture Button -->
        <div class="flex-1 flex justify-center items-center bg-black w-full pb-8">
            <button @click="capturePhoto()"
                class="w-20 h-20 bg-white/20 rounded-full border-[6px] border-white flex items-center justify-center active:scale-90 transition">
                <div class="w-16 h-16 bg-white rounded-full"></div>
            </button>
        </div>
    </div>

    <!-- 4. PREVIEW MODAL -->
    <div x-show="showPreview" style="display: none;"
        class="fixed inset-0 bg-[#F6F7FA] z-[160] flex flex-col overflow-y-auto">
        <!-- Header -->
        <div class="flex items-center px-4 py-4 bg-white border-b border-gray-200">
            <button @click="showPreview = false; previewImage = null" class="text-[#1C1C1E] mr-4 p-1">
                <i class="fa-solid fa-arrow-left text-lg"></i>
            </button>
            <h2 class="text-[#1C1C1E] font-bold text-base">Pratinjau Presensi</h2>
        </div>

        <!-- Map Container -->
        <div class="relative w-full h-72 bg-gray-200">
            <div id="previewMap" class="w-full h-full z-0 relative"></div>
            <div class="absolute bottom-2 left-1/2 transform -translate-x-1/2 bg-black/70 text-white text-xs px-3 py-1.5 rounded-full z-[40]"
                x-text="'Akurasi GPS: ' + (locationData.accuracy ? Math.round(locationData.accuracy) + ' m' : '...')">
            </div>
        </div>

        <div class="flex-1 px-5 py-5 flex flex-col items-center text-center max-w-md mx-auto w-full">
            <h3 class="text-base font-bold text-[#1C1C1E]">{{ $user->name }}</h3>
            <p class="text-xs text-[#8E8E93] mb-4">{{ now()->locale('id')->translatedFormat('d F Y, H:i') }} WIB</p>

            <img :src="previewImage" class="w-56 h-72 object-cover rounded-2xl mb-2 border border-gray-200 shadow-md">
            <p class="text-xs text-[#8E8E93] mb-5">Foto Presensi Terverifikasi</p>

            <div class="flex items-center justify-center space-x-3 mb-4">
                <p class="text-xs font-semibold" :class="locationValid ? 'text-emerald-600' : 'text-rose-600'"
                    x-text="locationStatusText"></p>
                <button type="button" @click="getLocationAndInitMap()"
                    class="text-gray-500 hover:text-gray-800 transition bg-white w-7 h-7 flex items-center justify-center rounded-full border border-gray-200 shadow-sm active:scale-95">
                    <i class="fa-solid fa-arrows-rotate text-xs"></i>
                </button>
            </div>

            <button @click="submitAttendance()" :disabled="!locationValid"
                class="w-full bg-[#FF21A4] hover:bg-[#E01890] text-white font-bold py-3.5 rounded-2xl transition active:scale-98 shadow-md shadow-pink-500/25 disabled:opacity-50 disabled:cursor-not-allowed flex justify-center items-center">
                <span x-show="!isSubmitting">Simpan Presensi</span>
                <span x-show="isSubmitting" style="display: none;">
                    <i class="fa-solid fa-spinner fa-spin mr-2"></i> Menyimpan...
                </span>
            </button>
        </div>
    </div>

    <!-- 5. LIGHTBOX MODAL -->
    <div x-show="lightboxOpen" x-cloak style="display: none;"
        class="fixed inset-0 z-[170] flex items-center justify-center p-4 bg-black/90 backdrop-blur-md"
        @keydown.escape.window="closeLightbox()">

        <button type="button" @click="closeLightbox()"
            class="absolute top-4 right-4 z-10 w-10 h-10 rounded-full bg-white/20 text-white flex items-center justify-center transition active:scale-95">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <div class="relative max-w-md w-full flex flex-col items-center" @click.away="closeLightbox()">
            <div x-show="lightboxTitle" class="w-full text-center mb-3">
                <h4 class="text-base font-bold text-white tracking-wide" x-text="lightboxTitle"></h4>
                <p class="text-xs text-gray-400 mt-0.5" x-text="lightboxSubtitle"></p>
            </div>

            <div class="relative overflow-hidden rounded-2xl bg-black shadow-2xl max-h-[75vh] w-full flex items-center justify-center p-1">
                <img :src="lightboxImage" alt="Preview"
                    class="max-w-full max-h-[72vh] w-auto h-auto object-contain rounded-xl shadow-md">
            </div>
        </div>
    </div>

    <!-- 6. MAP PINPOINT MODAL -->
    <div x-show="showMapModal" x-cloak style="display: none;"
        class="fixed inset-0 z-[170] flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm"
        @keydown.escape.window="closeMapModal()">

        <div class="relative max-w-md w-full bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col"
            @click.away="closeMapModal()">

            <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
                <div>
                    <h3 class="text-sm font-bold text-[#1C1C1E] flex items-center gap-1.5">
                        <i class="fa-solid fa-location-dot text-[#FF21A4]"></i>
                        <span x-text="mapModalTitle"></span>
                    </h3>
                    <p class="text-[11px] text-[#8E8E93]" x-text="mapModalSubtitle"></p>
                </div>
                <button type="button" @click="closeMapModal()"
                    class="w-8 h-8 rounded-full bg-gray-100 text-gray-500 hover:text-gray-900 flex items-center justify-center transition">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <div class="relative w-full h-80 bg-gray-100">
                <div id="historyMapContainer" class="w-full h-full z-0"></div>
                <div class="absolute bottom-2 left-3 right-3 flex items-center justify-between pointer-events-none z-[40]">
                    <div class="bg-white/95 backdrop-blur-sm border border-gray-200 text-[#1C1C1E] text-[10px] px-3 py-1.5 rounded-lg shadow">
                        <span class="text-[#8E8E93]">Koordinat: </span>
                        <span class="font-mono font-semibold"
                            x-text="(mapModalLat ? mapModalLat.toFixed(5) : '-') + ', ' + (mapModalLng ? mapModalLng.toFixed(5) : '-')"></span>
                    </div>
                </div>
            </div>

            <div class="p-3.5 bg-gray-50 border-t border-gray-100 flex items-center justify-between gap-3">
                <div class="flex items-center space-x-2 text-xs text-[#8E8E93]">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-[#FF21A4]"></span>
                    <span>Presensi</span>
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-blue-500 ml-1"></span>
                    <span>Kantor</span>
                </div>
                <a :href="'https://maps.google.com/?q=' + mapModalLat + ',' + mapModalLng" target="_blank"
                    class="px-3.5 py-1.5 bg-[#FF21A4] hover:bg-[#E01890] text-white rounded-lg text-xs font-bold flex items-center space-x-1.5 transition active:scale-95 shadow-sm">
                    <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                    <span>Google Maps</span>
                </a>
            </div>
        </div>
    </div>

    <!-- 7. EDIT PROFILE MODAL -->
    <div x-show="showEditProfileModal" x-cloak style="display: none;"
        class="fixed inset-0 z-[170] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
        @keydown.escape.window="showEditProfileModal = false">

        <div class="relative max-w-md w-full bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col"
            @click.away="showEditProfileModal = false">

            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <h3 class="text-base font-bold text-[#1C1C1E] flex items-center gap-2">
                    <i class="fa-solid fa-pen-to-square text-[#FF21A4]"></i>
                    <span>Edit Profil</span>
                </h3>
                <button type="button" @click="showEditProfileModal = false"
                    class="w-8 h-8 rounded-full bg-gray-100 text-gray-500 hover:text-gray-900 flex items-center justify-center transition">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('attendance.profile.update') }}" enctype="multipart/form-data" class="flex flex-col">
                @csrf
                @method('PUT')

                <div class="overflow-y-auto p-5 space-y-4 max-h-[70vh]">
                    <!-- Avatar Selection -->
                    <div class="flex flex-col items-center justify-center pb-2">
                        <div class="relative cursor-pointer group" @click="$refs.avatarInput.click()">
                            <img :src="avatarPreview ? avatarPreview : '{{ $user->avatar_url }}'"
                                class="w-20 h-20 rounded-full border-2 border-gray-200 bg-white object-cover group-hover:border-[#FF21A4] transition shadow-sm">
                            <span class="absolute bottom-0 right-0 bg-[#FF21A4] text-white rounded-full w-6 h-6 flex items-center justify-center shadow text-[10px]">
                                <i class="fa-solid fa-camera"></i>
                            </span>
                        </div>
                        <input type="file" x-ref="avatarInput" name="avatar" class="hidden" accept="image/*"
                            @change="previewAvatar($event)">
                        <button type="button" @click="$refs.avatarInput.click()"
                            class="mt-2 text-xs text-[#FF21A4] hover:underline font-bold">
                            Ganti Foto
                        </button>
                        @error('avatar')
                            <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Phone Number -->
                    <div>
                        <label class="block text-xs font-semibold text-[#8E8E93] mb-1">No. Telepon</label>
                        <input type="text" name="phone" value="{{ old('phone', $user->phone) }}"
                            placeholder="Contoh: 08123456789"
                            class="w-full bg-[#F6F7FA] border border-gray-200 rounded-xl text-sm text-[#1C1C1E] px-3.5 py-2.5 focus:outline-none focus:border-[#FF21A4]">
                        @error('phone')
                            <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="border-t border-gray-100 pt-2">
                        <p class="text-xs font-bold text-[#1C1C1E] uppercase tracking-wider mb-0.5">Ubah Password</p>
                        <p class="text-[11px] text-[#8E8E93] mb-3">Biarkan kosong jika tidak ingin mengganti password</p>

                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-[#8E8E93] mb-1">Password Baru</label>
                                <input type="password" name="password" placeholder="Minimal 8 karakter"
                                    class="w-full bg-[#F6F7FA] border border-gray-200 rounded-xl text-sm text-[#1C1C1E] px-3.5 py-2.5 focus:outline-none focus:border-[#FF21A4]">
                                @error('password')
                                    <p class="text-xs text-rose-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-[#8E8E93] mb-1">Konfirmasi Password Baru</label>
                                <input type="password" name="password_confirmation" placeholder="Ulangi password baru"
                                    class="w-full bg-[#F6F7FA] border border-gray-200 rounded-xl text-sm text-[#1C1C1E] px-3.5 py-2.5 focus:outline-none focus:border-[#FF21A4]">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-4 bg-gray-50 border-t border-gray-100 flex items-center justify-end space-x-2">
                    <button type="button" @click="showEditProfileModal = false"
                        class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-xl text-xs font-bold transition">
                        Batal
                    </button>
                    <button type="submit"
                        class="px-5 py-2 bg-[#FF21A4] hover:bg-[#E01890] text-white rounded-xl text-xs font-bold transition shadow-sm">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==================== JAVASCRIPT ==================== -->
    <script>
        let videoStream = null;
        let currentTargetFormId = null;

        async function refreshCsrfToken() {
            try {
                const response = await fetch('{{ route('csrf.token') }}', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                if (response.ok) {
                    const data = await response.json();
                    if (data.token) {
                        const meta = document.querySelector('meta[name="csrf-token"]');
                        if (meta) meta.setAttribute('content', data.token);
                        document.querySelectorAll('input[name="_token"]').forEach(input => {
                            input.value = data.token;
                        });
                        return { success: true, authenticated: data.authenticated, token: data.token };
                    }
                }
            } catch (err) {
                console.warn('Unable to refresh CSRF token:', err);
            }
            return { success: false };
        }

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') refreshCsrfToken();
        });
        window.addEventListener('focus', () => { refreshCsrfToken(); });
        setInterval(refreshCsrfToken, 10 * 60 * 1000);

        function openCameraUI(formId) {
            currentTargetFormId = formId;
            refreshCsrfToken();

            const alpineData = window.alpineApp;
            alpineData.showCamera = true;

            navigator.mediaDevices.getUserMedia({ video: { facingMode: "user", aspectRatio: { ideal: 0.75 } } })
                .then(function (stream) {
                    videoStream = stream;
                    const video = document.getElementById('cameraStream');
                    video.srcObject = stream;
                    video.play();
                })
                .catch(function (err) {
                    alert("Tidak dapat mengakses kamera: " + err.message);
                    alpineData.showCamera = false;
                });
        }

        function closeCameraUI() {
            if (videoStream) {
                videoStream.getTracks().forEach(track => track.stop());
                videoStream = null;
            }
            window.alpineApp.showCamera = false;
        }

        function capturePhoto() {
            const video = document.getElementById('cameraStream');
            const canvas = document.getElementById('cameraCanvas');
            const context = canvas.getContext('2d');

            const targetRatio = 3 / 4;
            const videoRatio = video.videoWidth / video.videoHeight;

            let sourceWidth = video.videoWidth;
            let sourceHeight = video.videoHeight;
            let offsetX = 0;
            let offsetY = 0;

            if (videoRatio > targetRatio) {
                sourceWidth = video.videoHeight * targetRatio;
                offsetX = (video.videoWidth - sourceWidth) / 2;
            } else {
                sourceHeight = video.videoWidth / targetRatio;
                offsetY = (video.videoHeight - sourceHeight) / 2;
            }

            canvas.width = sourceWidth;
            canvas.height = sourceHeight;

            context.translate(canvas.width, 0);
            context.scale(-1, 1);
            context.drawImage(video, offsetX, offsetY, sourceWidth, sourceHeight, 0, 0, canvas.width, canvas.height);

            function handleBlob(blob) {
                const ext = (blob && blob.type === 'image/webp') ? 'webp' : 'jpg';
                const file = new File([blob], `selfie.${ext}`, { type: blob ? blob.type : 'image/jpeg' });
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);

                let fileInput = null;
                if (currentTargetFormId === 'attendanceForm') {
                    fileInput = document.getElementById('selfie_input');
                } else {
                    fileInput = document.getElementById('break_selfie_input');
                }
                fileInput.files = dataTransfer.files;

                const url = URL.createObjectURL(file);
                window.dispatchEvent(new CustomEvent('preview-ready', {
                    detail: { image: url, formId: currentTargetFormId }
                }));

                closeCameraUI();
            }

            canvas.toBlob(function (blob) {
                if (blob) {
                    handleBlob(blob);
                } else {
                    canvas.toBlob(handleBlob, 'image/jpeg', 0.85);
                }
            }, 'image/webp', 0.85);
        }

        async function submitAttendance() {
            const alpineData = window.alpineApp;
            if (alpineData.isSubmitting) return;
            alpineData.isSubmitting = true;

            const form = document.getElementById(alpineData.currentFormId);
            if (!form) {
                alert('Formulir presensi tidak ditemukan.');
                alpineData.isSubmitting = false;
                return;
            }

            try {
                const status = await refreshCsrfToken();
                if (status.success && status.authenticated === false) {
                    alert('Sesi Anda telah kedaluwarsa. Silakan login kembali.');
                    window.location.href = '{{ route('login') }}';
                    return;
                }
            } catch (err) {
                console.warn('Pre-submit CSRF refresh warning:', err);
            }

            form.submit();
        }

        // Office Geofence Configuration
        const officeLat = {{ $user->location->latitude ?? 'null' }};
        const officeLng = {{ $user->location->longitude ?? 'null' }};
        const officeRadius = {{ $user->location->radius ?? 'null' }};
        const officeName = "{{ addslashes($user->location->name ?? 'Kantor') }}";

        function calculateDistance(lat1, lon1, lat2, lon2) {
            if (lat1 === null || lon1 === null || lat2 === null || lon2 === null) return null;
            const R = 6371e3;
            const φ1 = lat1 * Math.PI / 180;
            const φ2 = lat2 * Math.PI / 180;
            const Δφ = (lat2 - lat1) * Math.PI / 180;
            const Δλ = (lon2 - lon1) * Math.PI / 180;

            const a = Math.sin(Δφ / 2) * Math.sin(Δφ / 2) +
                Math.cos(φ1) * Math.cos(φ2) *
                Math.sin(Δλ / 2) * Math.sin(Δλ / 2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

            return R * c;
        }

        // Preview Map
        let leafletMap = null;
        let mapMarker = null;
        let mapCircle = null;

        function getLocationAndInitMap() {
            const alpineData = window.alpineApp;
            alpineData.locationStatusText = 'Mendeteksi lokasi GPS...';
            alpineData.locationValid = false;

            if ("geolocation" in navigator) {
                navigator.geolocation.getCurrentPosition(function (position) {
                    const currentLat = position.coords.latitude;
                    const currentLng = position.coords.longitude;
                    const accuracy = position.coords.accuracy;

                    alpineData.locationData = {
                        lat: currentLat,
                        lng: currentLng,
                        accuracy: accuracy,
                        distance: null
                    };

                    document.getElementById('lat').value = currentLat;
                    document.getElementById('lng').value = currentLng;
                    document.getElementById('lat2').value = currentLat;
                    document.getElementById('lng2').value = currentLng;

                    setTimeout(() => {
                        if (!leafletMap) {
                            leafletMap = L.map('previewMap').setView([currentLat, currentLng], 16);
                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                attribution: '&copy; OpenStreetMap'
                            }).addTo(leafletMap);

                            const userPinIcon = L.divIcon({
                                className: 'user-pin-icon',
                                html: `<div style="background-color: #FF21A4; width: 32px; height: 32px; border-radius: 50%; border: 3px solid white; box-shadow: 0 4px 12px rgba(255,33,164,0.4); display: flex; align-items: center; justify-content: center; color: white;">
                                        <i class="fa-solid fa-location-dot text-sm"></i>
                                       </div>`,
                                iconSize: [32, 32],
                                iconAnchor: [16, 32]
                            });

                            mapMarker = L.marker([currentLat, currentLng], { icon: userPinIcon }).addTo(leafletMap).bindPopup("Lokasi Anda").openPopup();
                        } else {
                            leafletMap.setView([currentLat, currentLng], 16);
                            mapMarker.setLatLng([currentLat, currentLng]);
                            leafletMap.invalidateSize();
                        }

                        if (officeLat !== null && officeLng !== null) {
                            const distance = calculateDistance(currentLat, currentLng, officeLat, officeLng);
                            alpineData.locationData.distance = distance;

                            if (mapCircle) mapCircle.remove();

                            const isValid = distance <= officeRadius;
                            alpineData.locationValid = isValid;

                            if (isValid) {
                                alpineData.locationStatusText = 'Dalam Radius (' + Math.round(distance) + 'm dari kantor)';
                            } else {
                                alpineData.locationStatusText = 'Di Luar Radius (' + Math.round(distance) + 'm / Maks ' + officeRadius + 'm)';
                            }

                            mapCircle = L.circle([officeLat, officeLng], {
                                color: isValid ? '#10B981' : '#EF4444',
                                fillColor: isValid ? '#10B981' : '#EF4444',
                                fillOpacity: 0.18,
                                radius: officeRadius
                            }).addTo(leafletMap);

                            const officePin = L.divIcon({
                                className: 'office-pin-icon',
                                html: `<div style="background-color: #3b82f6; width: 28px; height: 28px; border-radius: 50%; border: 2.5px solid white; box-shadow: 0 4px 10px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; color: white;">
                                        <i class="fa-solid fa-building text-xs"></i>
                                       </div>`,
                                iconSize: [28, 28],
                                iconAnchor: [14, 28]
                            });
                            L.marker([officeLat, officeLng], { icon: officePin }).addTo(leafletMap).bindPopup(officeName);

                            const bounds = L.latLngBounds([[currentLat, currentLng], [officeLat, officeLng]]);
                            leafletMap.fitBounds(bounds, { padding: [30, 30] });
                        } else {
                            alpineData.locationStatusText = 'Anda tidak memiliki lokasi kerja yang diatur';
                        }
                    }, 100);

                }, function (error) {
                    alpineData.locationStatusText = 'Gagal mendeteksi GPS: ' + error.message;
                }, {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                });
            } else {
                alpineData.locationStatusText = 'Browser tidak mendukung GPS';
            }
        }

        // Live Attendance Map (Peta Tab - Matching iOS AttendanceMapView.swift)
        let liveMapInstance = null;
        let liveUserMarker = null;
        let liveOfficeMarker = null;
        let liveGeofenceCircle = null;
        let currentUserLat = null;
        let currentUserLng = null;

        function initLiveAttendanceMap() {
            const container = document.getElementById('liveAttendanceMap');
            if (!container) return;

            const defaultLat = officeLat || -6.200000;
            const defaultLng = officeLng || 106.816666;

            if (!liveMapInstance) {
                liveMapInstance = L.map('liveAttendanceMap', {
                    zoomControl: false,
                    attributionControl: false
                }).setView([defaultLat, defaultLng], 16);
                window.liveMapInstance = liveMapInstance;

                // OpenStreetMap Open Source Map Tiles
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(liveMapInstance);

                if (officeLat !== null && officeLng !== null) {
                    const officeIcon = L.divIcon({
                        className: 'leaflet-office-pin',
                        html: `<div style="display: flex; flex-direction: column; align-items: center; transform: translate(-50%, -50%); pointer-events: none;">
                            <div style="width: 38px; height: 38px; border-radius: 50%; background: white; box-shadow: 0 4px 14px rgba(0,0,0,0.14); display: flex; align-items: center; justify-content: center; border: 1.5px solid #F0F2F5;">
                                <div style="width: 28px; height: 28px; border-radius: 50%; background: #FF21A4; display: flex; align-items: center; justify-content: center; color: white;">
                                    <i class="fa-solid fa-building" style="font-size: 13px;"></i>
                                </div>
                            </div>
                            <div style="background: white; border: 1px solid #E5E7EB; border-radius: 9999px; padding: 2.5px 10px; margin-top: 3px; font-weight: 700; font-size: 10px; color: #1E222B; white-space: nowrap; box-shadow: 0 2px 6px rgba(0,0,0,0.08);">
                                ${officeName}
                            </div>
                        </div>`,
                        iconSize: [0, 0]
                    });

                    liveOfficeMarker = L.marker([officeLat, officeLng], { icon: officeIcon, zIndexOffset: 100 }).addTo(liveMapInstance);

                    liveGeofenceCircle = L.circle([officeLat, officeLng], {
                        color: '#10B981',
                        weight: 2,
                        fillColor: '#10B981',
                        fillOpacity: 0.18,
                        radius: officeRadius || 100
                    }).addTo(liveMapInstance);
                }
            }

            setTimeout(() => {
                if (window.liveMapInstance) {
                    window.liveMapInstance.invalidateSize(true);
                }
            }, 100);

            refreshLiveMapLocation();
        }

        function refreshLiveMapLocation() {
            if (!liveMapInstance) return;
            const alpineData = window.alpineApp;

            if ("geolocation" in navigator) {
                navigator.geolocation.getCurrentPosition(function (position) {
                    const currentLat = position.coords.latitude;
                    const currentLng = position.coords.longitude;
                    const accuracy = position.coords.accuracy;

                    currentUserLat = currentLat;
                    currentUserLng = currentLng;

                    const userIcon = L.divIcon({
                        className: 'leaflet-user-pin',
                        html: `<div style="display: flex; flex-direction: column; align-items: center; transform: translate(-50%, -100%); pointer-events: none;">
                            <div style="background: #0B57D0; color: white; font-size: 9.5px; font-weight: 700; border-radius: 9999px; padding: 2px 8px; margin-bottom: 2px; white-space: nowrap; box-shadow: 0 2px 6px rgba(11,87,208,0.25); letter-spacing: 0.2px;">
                                Posisi Anda
                            </div>
                            <div style="position: relative; display: flex; flex-direction: column; align-items: center;">
                                <div style="width: 32px; height: 32px; border-radius: 50%; background: #007AFF; border: 2.5px solid white; box-shadow: 0 4px 12px rgba(0,122,255,0.4); display: flex; align-items: center; justify-content: center; color: white;">
                                    <i class="fa-solid fa-person-walking" style="font-size: 14px;"></i>
                                </div>
                                <div style="width: 4px; height: 4px; border-radius: 50%; background: rgba(0,0,0,0.5); margin-top: 1px;"></div>
                            </div>
                        </div>`,
                        iconSize: [0, 0]
                    });

                    if (!liveUserMarker) {
                        liveUserMarker = L.marker([currentLat, currentLng], { icon: userIcon, zIndexOffset: 200 }).addTo(liveMapInstance);
                    } else {
                        liveUserMarker.setLatLng([currentLat, currentLng]);
                    }

                    if (officeLat !== null && officeLng !== null) {
                        const dist = calculateDistance(currentLat, currentLng, officeLat, officeLng);
                        const isValid = dist <= (officeRadius || 100);
                        alpineData.locationValid = isValid;
                        alpineData.locationDistanceText = `${Math.round(dist)} m dari titik kantor`;
                        alpineData.locationStatusText = isValid ? 'Dalam Radius Presensi' : 'Di Luar Radius Presensi';
                        alpineData.locationData = {
                            lat: currentLat,
                            lng: currentLng,
                            accuracy: accuracy,
                            distance: dist
                        };

                        fitLiveMapBounds();
                    } else {
                        liveMapInstance.setView([currentLat, currentLng], 16, { animate: true });
                    }
                }, function (err) {
                    console.warn(err);
                    alpineData.locationDistanceText = 'Gagal mendeteksi lokasi GPS';
                }, { enableHighAccuracy: true });
            }
        }

        function centerOnUserLiveMap() {
            if (currentUserLat && currentUserLng && liveMapInstance) {
                liveMapInstance.setView([currentUserLat, currentUserLng], 17, { animate: true });
            } else {
                refreshLiveMapLocation();
            }
        }

        function fitLiveMapBounds() {
            if (!liveMapInstance) return;
            if (currentUserLat && officeLat) {
                const bounds = L.latLngBounds([[currentUserLat, currentUserLng], [officeLat, officeLng]]);
                liveMapInstance.fitBounds(bounds, { padding: [80, 80], maxZoom: 17, animate: true });
            } else if (officeLat) {
                liveMapInstance.setView([officeLat, officeLng], 16, { animate: true });
            } else if (currentUserLat) {
                liveMapInstance.setView([currentUserLat, currentUserLng], 16, { animate: true });
            }
        }

        // History Pinpoint Map
        window.historyMapInstance = null;
        function initHistoryMap(lat, lng, title) {
            const mapContainer = document.getElementById('historyMapContainer');
            if (!mapContainer) return;

            if (window.historyMapInstance) {
                window.historyMapInstance.remove();
                window.historyMapInstance = null;
            }

            const map = L.map('historyMapContainer').setView([lat, lng], 16);
            window.historyMapInstance = map;

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap',
                maxZoom: 19
            }).addTo(map);

            const userIcon = L.divIcon({
                className: 'custom-pin-marker',
                html: `<div style="background-color: #FF21A4; width: 34px; height: 34px; border-radius: 50%; border: 3px solid white; box-shadow: 0 4px 12px rgba(255,33,164,0.4); display: flex; align-items: center; justify-content: center; color: white;">
                        <i class="fa-solid fa-location-dot text-base"></i>
                       </div>`,
                iconSize: [34, 34],
                iconAnchor: [17, 34],
                popupAnchor: [0, -34]
            });

            const userMarker = L.marker([lat, lng], { icon: userIcon }).addTo(map)
                .bindPopup(`<b>${title}</b><br><span style="color:#666;font-size:11px;">Lat: ${lat.toFixed(5)}, Lng: ${lng.toFixed(5)}</span>`)
                .openPopup();

            if (typeof officeLat === 'number' && typeof officeLng === 'number' && officeLat !== null && officeLng !== null) {
                const officeIcon = L.divIcon({
                    className: 'custom-office-marker',
                    html: `<div style="background-color: #3b82f6; width: 30px; height: 30px; border-radius: 50%; border: 2.5px solid white; box-shadow: 0 4px 10px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; color: white;">
                            <i class="fa-solid fa-building text-xs"></i>
                           </div>`,
                    iconSize: [30, 30],
                    iconAnchor: [15, 30],
                    popupAnchor: [0, -30]
                });

                const officeMarker = L.marker([officeLat, officeLng], { icon: officeIcon }).addTo(map)
                    .bindPopup(`<b>${officeName}</b><br><span style="color:#666;font-size:11px;">Radius: ${officeRadius || 50}m</span>`);

                if (officeRadius) {
                    L.circle([officeLat, officeLng], {
                        color: '#3b82f6',
                        fillColor: '#60a5fa',
                        fillOpacity: 0.18,
                        radius: officeRadius
                    }).addTo(map);
                }

                const dist = calculateDistance(lat, lng, officeLat, officeLng);
                if (dist !== null) {
                    const distRounded = Math.round(dist);
                    userMarker.setPopupContent(`<b>${title}</b><br><span style="font-size:11px;">Jarak ke kantor: <b>${distRounded} m</b></span><br><span style="color:#666;font-size:10px;">${lat.toFixed(5)}, ${lng.toFixed(5)}</span>`);
                }

                const group = new L.featureGroup([userMarker, officeMarker]);
                map.fitBounds(group.getBounds().pad(0.25));
            }

            setTimeout(() => { map.invalidateSize(); }, 250);
        }
    </script>
</body>

</html>