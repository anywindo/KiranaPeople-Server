@extends('layouts.admin')

@section('title', 'Tambah Lokasi Kerja (GPS)')
@section('page_title', 'Tambah Lokasi Kerja & Geofence')

@push('styles')
<style>
    .location-map-container {
        position: relative;
        height: 480px;
        width: 100%;
        border-radius: 0.75rem;
        overflow: hidden;
        border: 1px solid var(--card-border-color, #e2e8f0);
    }
    #map {
        height: 100%;
        width: 100%;
    }
    .map-search-bar {
        position: absolute;
        top: 12px;
        left: 50px;
        right: 12px;
        z-index: 1000;
        max-width: 440px;
    }
    .map-quick-actions {
        position: absolute;
        bottom: 12px;
        right: 12px;
        z-index: 1000;
    }
    .preset-chip {
        cursor: pointer;
        transition: all 0.15s ease-in-out;
        font-size: 0.8125rem;
    }
    .preset-chip:hover {
        transform: translateY(-1px);
    }
</style>
@endpush

@section('content')
<div class="row g-3">
    <div class="col-12">
        <form action="{{ route('admin.locations.store') }}" method="POST" id="locationForm">
            @csrf
            <div class="row g-3">
                <!-- Left: Interactive Map -->
                <div class="col-lg-7 col-12">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                                <i class="bi bi-geo-alt-fill text-primary"></i>
                                <span>Peta Interaktif Geofence</span>
                            </h5>
                            <span class="badge text-bg-primary-subtle text-primary border border-primary-subtle">
                                <i class="bi bi-arrows-move me-1"></i>Pin Dapat Digeser (Draggable)
                            </span>
                        </div>
                        <div class="card-body p-3">
                            <div class="location-map-container shadow-sm mb-3">
                                <!-- Search overlay on map -->
                                <div class="map-search-bar">
                                    <div class="input-group input-group-sm shadow-sm">
                                        <span class="input-group-text bg-white border-end-0">
                                            <i class="bi bi-search text-muted"></i>
                                        </span>
                                        <input type="text" id="mapSearchInput" class="form-control border-start-0 border-end-0" 
                                            placeholder="Cari alamat, gedung, atau kota..." autocomplete="off">
                                        <button class="btn btn-primary" type="button" id="btnSearchLocation">
                                            <span id="searchSpinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                            <span id="searchText">Cari</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Quick action buttons on map -->
                                <div class="map-quick-actions d-flex flex-column gap-2">
                                    <button type="button" id="btnCurrentLocation" class="btn btn-light btn-sm shadow-sm border" title="Gunakan Lokasi GPS Saya">
                                        <i class="bi bi-crosshair2 text-danger"></i>
                                    </button>
                                    <button type="button" id="btnCenterMarker" class="btn btn-light btn-sm shadow-sm border" title="Pusatkan Peta ke Marker">
                                        <i class="bi bi-geo-fill text-primary"></i>
                                    </button>
                                </div>

                                <div id="map"></div>
                            </div>

                            <!-- Detected reverse geocoded address badge -->
                            <div class="p-2 px-3 rounded bg-body-tertiary border d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2 text-truncate" style="max-width: 85%;">
                                    <i class="bi bi-geo-alt-fill text-danger flex-shrink-0"></i>
                                    <span class="small text-truncate" id="detectedAddressText">Klik atau geser pin pada peta untuk mendeteksi alamat...</span>
                                </div>
                                <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" id="btnApplyAddressToName">
                                    <small><i class="bi bi-arrow-return-right me-1"></i>Pakai Sebagai Nama</small>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Form Controls & Geofence Intelligence -->
                <div class="col-lg-5 col-12">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-header bg-transparent">
                            <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                                <i class="bi bi-sliders text-primary"></i>
                                <span>Parameter Lokasi & Radius</span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <!-- Office Name -->
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Nama Lokasi Kerja <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="locationName" class="form-control @error('name') is-invalid @enderror" 
                                    value="{{ old('name') }}" placeholder="Contoh: Kantor Pusat, Gudang Batang, dsb." required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <!-- Radius Range & Controls -->
                            <div class="mb-4 p-3 rounded bg-body-tertiary border">
                                <div class="d-flex justify-content-between align-items-baseline mb-2">
                                    <label class="form-label fw-semibold mb-0">Radius Geofence Presensi</label>
                                    <div>
                                        <span class="h4 fw-bold text-primary mb-0" id="radiusBadge">50</span>
                                        <span class="text-muted small">Meter</span>
                                    </div>
                                </div>

                                <!-- Range Slider -->
                                <input type="range" class="form-range mb-2" id="radiusRange" min="10" max="1000" step="5" value="{{ old('radius', 50) }}">

                                <!-- Number input & quick presets -->
                                <div class="input-group input-group-sm mb-3">
                                    <span class="input-group-text">Input Presisi</span>
                                    <input type="number" id="radius" name="radius" class="form-control @error('radius') is-invalid @enderror" 
                                        value="{{ old('radius', 50) }}" min="5" max="5000" required>
                                    <span class="input-group-text">Meter</span>
                                </div>
                                @error('radius')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

                                <!-- Preset Chips -->
                                <div class="d-flex flex-wrap gap-1 mb-3">
                                    <button type="button" class="btn btn-outline-secondary btn-sm preset-chip" data-radius="25">25m <small class="opacity-75">(Ruangan)</small></button>
                                    <button type="button" class="btn btn-outline-primary btn-sm preset-chip" data-radius="50">50m <small class="opacity-75">(Standar)</small></button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm preset-chip" data-radius="100">100m <small class="opacity-75">(Gedung)</small></button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm preset-chip" data-radius="250">250m <small class="opacity-75">(Pabrik)</small></button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm preset-chip" data-radius="500">500m <small class="opacity-75">(Kawasan)</small></button>
                                </div>

                                <!-- Live Coverage Stats -->
                                <div class="row g-2 pt-2 border-top text-muted small">
                                    <div class="col-6">
                                        <i class="bi bi-bounding-box me-1 text-primary"></i> Luas Area:
                                        <div class="fw-bold text-body" id="areaDisplay">~7,854 m²</div>
                                    </div>
                                    <div class="col-6">
                                        <i class="bi bi-person-walking me-1 text-primary"></i> Estimasi Waktu:
                                        <div class="fw-bold text-body" id="walkDisplay">± 35 detik jalan kaki</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Coordinate inputs -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label fw-semibold mb-0">Kordinat GPS (Latitude & Longitude)</label>
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="unlockManualCoord">
                                        <label class="form-check-label small text-muted" for="unlockManualCoord">Ketik Manual</label>
                                    </div>
                                </div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">Lat</span>
                                            <input type="text" id="latitude" name="latitude" class="form-control @error('latitude') is-invalid @enderror" 
                                                value="{{ old('latitude', '-6.208800') }}" readonly required>
                                        </div>
                                        @error('latitude')<div class="text-danger small">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-6">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">Lng</span>
                                            <input type="text" id="longitude" name="longitude" class="form-control @error('longitude') is-invalid @enderror" 
                                                value="{{ old('longitude', '106.845600') }}" readonly required>
                                        </div>
                                        @error('longitude')<div class="text-danger small">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>

                            <!-- Quick Paste Coordinates tool -->
                            <div class="mb-4">
                                <div class="input-group input-group-sm">
                                    <input type="text" id="quickPasteCoord" class="form-control" placeholder="Tempel 'lat, lng' dari Google Maps...">
                                    <button class="btn btn-outline-secondary" type="button" id="btnApplyQuickCoord">Terapkan</button>
                                </div>
                                <small class="text-muted">Format: <code>-6.2088, 106.8456</code></small>
                            </div>
                        </div>

                        <!-- Footer Actions -->
                        <div class="card-footer bg-transparent">
                            <div class="d-flex justify-content-between align-items-center w-100">
                                <a href="{{ route('admin.locations.index') }}" class="btn btn-default">
                                    <i class="bi bi-arrow-left me-1"></i> Batal
                                </a>
                                <button type="submit" class="btn btn-primary px-4 ms-auto">
                                    <i class="bi bi-check-lg me-1"></i> Simpan Lokasi
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. Initial State
    const defaultLat = parseFloat("{{ old('latitude', '-6.208800') }}") || -6.208800;
    const defaultLng = parseFloat("{{ old('longitude', '106.845600') }}") || 106.845600;
    const defaultRadius = parseInt("{{ old('radius', 50) }}") || 50;

    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');
    const radiusInput = document.getElementById('radius');
    const radiusRange = document.getElementById('radiusRange');
    const radiusBadge = document.getElementById('radiusBadge');
    const areaDisplay = document.getElementById('areaDisplay');
    const walkDisplay = document.getElementById('walkDisplay');
    const detectedAddressText = document.getElementById('detectedAddressText');
    const locationNameInput = document.getElementById('locationName');
    const unlockSwitch = document.getElementById('unlockManualCoord');
    let lastDetectedAddress = '';

    // 2. Initialize Leaflet Map
    const map = L.map('map', {
        zoomControl: true,
        attributionControl: true
    }).setView([defaultLat, defaultLng], 16);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);

    setTimeout(() => { map.invalidateSize(); }, 300);

    // 3. Marker & Geofence Circle
    const marker = L.marker([defaultLat, defaultLng], {
        draggable: true,
        autoPan: true
    }).addTo(map);

    const circle = L.circle([defaultLat, defaultLng], {
        color: '#4f46e5',
        fillColor: '#6366f1',
        fillOpacity: 0.18,
        weight: 2,
        dashArray: '4, 4',
        radius: defaultRadius
    }).addTo(map);

    // 4. Update Position & Metrics
    function updateMetrics(radius) {
        radiusBadge.textContent = radius;
        radiusInput.value = radius;
        radiusRange.value = Math.min(radius, 1000);

        // Calculate circular area: PI * r^2
        const area = Math.round(Math.PI * Math.pow(radius, 2));
        areaDisplay.textContent = '~' + area.toLocaleString('id-ID') + ' m²';

        // Walking estimate: average speed ~1.4 m/s
        const walkSeconds = Math.round(radius / 1.4);
        if (walkSeconds < 60) {
            walkDisplay.textContent = `± ${walkSeconds} detik jalan kaki`;
        } else {
            const walkMinutes = Math.round(walkSeconds / 60);
            walkDisplay.textContent = `± ${walkMinutes} menit jalan kaki`;
        }
    }

    function setCoordinates(lat, lng, syncMap = false, shouldReverseGeocode = true) {
        const fixedLat = Number(lat).toFixed(6);
        const fixedLng = Number(lng).toFixed(6);

        latInput.value = fixedLat;
        lngInput.value = fixedLng;

        const latLng = [parseFloat(fixedLat), parseFloat(fixedLng)];
        marker.setLatLng(latLng);
        circle.setLatLng(latLng);

        if (syncMap) {
            map.setView(latLng, Math.max(map.getZoom(), 16));
        }

        if (shouldReverseGeocode) {
            debounceReverseGeocode(fixedLat, fixedLng);
        }
    }

    function setRadius(newRadius) {
        const r = Math.max(5, parseInt(newRadius) || 50);
        circle.setRadius(r);
        updateMetrics(r);
    }

    // 5. Marker Drag Events
    marker.on('drag', function(e) {
        const pos = e.target.getLatLng();
        circle.setLatLng(pos);
        latInput.value = pos.lat.toFixed(6);
        lngInput.value = pos.lng.toFixed(6);
    });

    marker.on('dragend', function(e) {
        const pos = e.target.getLatLng();
        setCoordinates(pos.lat, pos.lng, false, true);
    });

    // 6. Map Click Event
    map.on('click', function(e) {
        setCoordinates(e.latlng.lat, e.latlng.lng, false, true);
    });

    // 7. Radius Inputs Synchronization
    radiusRange.addEventListener('input', function() {
        setRadius(this.value);
    });

    radiusInput.addEventListener('input', function() {
        setRadius(this.value);
    });

    document.querySelectorAll('.preset-chip').forEach(chip => {
        chip.addEventListener('click', function() {
            const r = parseInt(this.getAttribute('data-radius'));
            setRadius(r);
            document.querySelectorAll('.preset-chip').forEach(c => {
                c.classList.remove('btn-outline-primary', 'btn-primary');
                c.classList.add('btn-outline-secondary');
            });
            this.classList.remove('btn-outline-secondary');
            this.classList.add('btn-outline-primary');
        });
    });

    // 8. Reverse Geocoding with Nominatim
    let reverseTimeout = null;
    function debounceReverseGeocode(lat, lng) {
        clearTimeout(reverseTimeout);
        detectedAddressText.textContent = 'Mendeteksi alamat...';
        reverseTimeout = setTimeout(() => {
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`, {
                headers: { 'Accept-Language': 'id' }
            })
            .then(res => res.json())
            .then(data => {
                if (data && data.display_name) {
                    lastDetectedAddress = data.display_name;
                    detectedAddressText.textContent = data.display_name;
                } else {
                    detectedAddressText.textContent = `Kordinat: ${lat}, ${lng}`;
                }
            })
            .catch(() => {
                detectedAddressText.textContent = `Kordinat: ${lat}, ${lng}`;
            });
        }, 400);
    }

    // Use detected address as Office Name button
    const btnApplyAddressToName = document.getElementById('btnApplyAddressToName');
    if (btnApplyAddressToName) {
        btnApplyAddressToName.addEventListener('click', function() {
            if (lastDetectedAddress) {
                const parts = lastDetectedAddress.split(',');
                const cleanName = parts.slice(0, 2).join(',').trim();
                locationNameInput.value = cleanName;
                locationNameInput.focus();
            }
        });
    }

    // 9. Location Search (Geocoding)
    const searchInput = document.getElementById('mapSearchInput');
    const searchBtn = document.getElementById('btnSearchLocation');
    const searchSpinner = document.getElementById('searchSpinner');
    const searchText = document.getElementById('searchText');

    function performSearch() {
        const query = searchInput.value.trim();
        if (!query) return;

        searchSpinner.classList.remove('d-none');
        searchText.textContent = '...';
        searchBtn.disabled = true;

        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1&countrycodes=id`, {
            headers: { 'Accept-Language': 'id' }
        })
        .then(res => res.json())
        .then(results => {
            if (results && results.length > 0) {
                const target = results[0];
                const lat = parseFloat(target.lat);
                const lon = parseFloat(target.lon);
                setCoordinates(lat, lon, true, true);
                if (!locationNameInput.value.trim()) {
                    locationNameInput.value = query;
                }
            } else {
                alert('Lokasi tidak ditemukan. Coba kata kunci yang lebih spesifik.');
            }
        })
        .catch(() => {
            alert('Gagal mencari lokasi. Periksa koneksi internet.');
        })
        .finally(() => {
            searchSpinner.classList.add('d-none');
            searchText.textContent = 'Cari';
            searchBtn.disabled = false;
        });
    }

    searchBtn.addEventListener('click', performSearch);
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            performSearch();
        }
    });

    // 10. Current GPS Location Button
    document.getElementById('btnCurrentLocation').addEventListener('click', function() {
        if ('geolocation' in navigator) {
            const btn = this;
            btn.disabled = true;
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    btn.disabled = false;
                    setCoordinates(position.coords.latitude, position.coords.longitude, true, true);
                },
                function(error) {
                    btn.disabled = false;
                    alert('Gagal mendapatkan lokasi GPS: ' + error.message);
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        } else {
            alert('Browser tidak mendukung geolokasi.');
        }
    });

    // 11. Center Marker Button
    document.getElementById('btnCenterMarker').addEventListener('click', function() {
        map.setView(marker.getLatLng(), Math.max(map.getZoom(), 16));
    });

    // 12. Manual Coordinate Unlock Switch
    unlockSwitch.addEventListener('change', function() {
        latInput.readOnly = !this.checked;
        lngInput.readOnly = !this.checked;
        if (this.checked) {
            latInput.classList.add('bg-white');
            lngInput.classList.add('bg-white');
            latInput.focus();
        } else {
            latInput.classList.remove('bg-white');
            lngInput.classList.remove('bg-white');
        }
    });

    function handleManualInput() {
        const lat = parseFloat(latInput.value);
        const lng = parseFloat(lngInput.value);
        if (!isNaN(lat) && !isNaN(lng)) {
            setCoordinates(lat, lng, true, true);
        }
    }
    latInput.addEventListener('change', handleManualInput);
    lngInput.addEventListener('change', handleManualInput);

    // 13. Quick Paste Coordinates Helper
    document.getElementById('btnApplyQuickCoord').addEventListener('click', function() {
        const val = document.getElementById('quickPasteCoord').value.trim();
        if (!val) return;
        const parts = val.split(/[\s,]+/);
        if (parts.length >= 2) {
            const lat = parseFloat(parts[0]);
            const lng = parseFloat(parts[1]);
            if (!isNaN(lat) && !isNaN(lng)) {
                setCoordinates(lat, lng, true, true);
                document.getElementById('quickPasteCoord').value = '';
                return;
            }
        }
        alert('Format kordinat tidak valid. Gunakan format: -6.2088, 106.8456');
    });

    // Initial calculations & reverse geocode
    updateMetrics(defaultRadius);
    debounceReverseGeocode(defaultLat, defaultLng);
});
</script>
@endpush
