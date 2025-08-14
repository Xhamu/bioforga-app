{{-- raíz ÚNICA --}}
<div wire:ignore x-data x-init="$nextTick(() => window.initRefMap?.())">
    <x-filament::section>
        <x-slot name="heading">Mapa de referencias</x-slot>

        @if (empty($markers) || count($markers) === 0)
            <p class="text-sm text-gray-500">No hay referencias con ubicación GPS para mostrar.</p>
        @else
            <div id="referencias-map" style="height: 480px; border-radius: 12px; overflow: hidden;"></div>
        @endif
    </x-filament::section>
</div>

@push('styles')
    {{-- Leaflet base --}}
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin>
    {{-- MarkerCluster --}}
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
@endpush

@push('scripts')
    {{-- Leaflet base --}}
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin></script>
    {{-- MarkerCluster --}}
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

    <script>
        window.initRefMap = function() {
            const puntos = @json($markers);
            const el = document.getElementById('referencias-map');
            if (!el || !Array.isArray(puntos) || puntos.length === 0) return;

            if (el.dataset.inited === '1') return;
            el.dataset.inited = '1';

            const map = L.map(el, {
                zoomControl: true,
                scrollWheelZoom: true,
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
                updateWhenIdle: true,
                updateWhenZooming: false,
                keepBuffer: 1,
            }).addTo(map);

            // MarkerCluster con chunked loading para rendimiento
            const cluster = L.markerClusterGroup({
                chunkedLoading: true,
                chunkDelay: 16,
                chunkInterval: 200,
                disableClusteringAtZoom: 16,
                spiderfyOnEveryZoom: false,
                showCoverageOnHover: false,
            });

            // Añadimos markers normales con popup ya enlazado
            puntos.forEach(p => {
                const popupHtml = `
                    <div style="min-width:220px">
                        <div style="font-weight:600; margin-bottom:6px;">${p.titulo}</div>
                        <a href="${p.url}" class="text-primary-600 underline" target="_self" rel="noopener">Abrir referencia</a>
                    </div>
                `;
                const m = L.marker([p.lat, p.lng]).bindPopup(popupHtml, {
                    autoPanPaddingTopLeft: [12, 12]
                });
                // Evita que el click en el enlace burbujee al cluster
                m.on('popupopen', (e) => {
                    const a = e.popup.getElement()?.querySelector('a');
                    if (a) a.addEventListener('click', ev => ev.stopPropagation());
                });
                cluster.addLayer(m);
            });

            cluster.addTo(map);

            if (puntos.length === 1) {
                map.setView([puntos[0].lat, puntos[0].lng], 14);
            } else {
                map.fitBounds(cluster.getBounds(), {
                    padding: [24, 24]
                });
            }

            setTimeout(() => map.invalidateSize(), 80);
        };

        document.addEventListener('DOMContentLoaded', window.initRefMap);
        document.addEventListener('livewire:navigated', window.initRefMap);
    </script>
@endpush
