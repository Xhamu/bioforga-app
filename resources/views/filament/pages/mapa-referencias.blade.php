<x-filament::page>
    @php
        $markers = $this->markers; // array de puntos
        $referenciaActualId = null; // opcional
    @endphp

    <x-filament::section>
        {{ $this->form }}
    </x-filament::section>

    <x-filament::section>
        <div id="ref-map-wrapper" wire:key="map-wrapper" x-data x-init="$nextTick(() => window.initRefMap?.())"
            data-markers='@json($this->markers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)' style="position: relative;">
            <div id="referencias-map" wire:ignore style="height: 600px; border-radius: 12px; overflow: hidden;"></div>
            <div id="ref-map-empty"
                style="position:absolute; inset:0; display:none; align-items:center; justify-content:center; pointer-events:none;">
                <p class="text-sm text-gray-500 bg-white/70 rounded-md px-3 py-2 shadow">No hay referencias con
                    ubicación GPS para mostrar.</p>
            </div>
        </div>
    </x-filament::section>

    @push('styles')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin>
        <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
        <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
    @endpush

    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin></script>
        <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

        <script>
            window.__refMap = null;
            window.__refCluster = null;
            const DEFAULT_CENTER = [40.0, -3.7];
            const DEFAULT_ZOOM = 7;

            function buildMarker(p) {
                const tipo = p.type === 'proveedor' ? 'Proveedor' : 'Referencia';
                const labelAbrir = tipo === 'Proveedor' ? '📄 Abrir proveedor' : '📄 Abrir referencia';
                const popupHtml = `
      <div style="min-width:220px; font-family:Arial, sans-serif; color:#1f2937;">
        <div style="font-weight:600; margin-bottom:10px; font-size:14px; color:#111827;">${p.titulo ?? ''}</div>
        <div style="display:flex; flex-direction:column; gap:6px;">
          ${p.url ? `<a href="${p.url}" style="display:inline-block; text-align:center; padding:8px 12px; background:${p.color}; color:#fff; font-size:13px; font-weight:600; border-radius:6px; text-decoration:none;" target="_self" rel="noopener">${labelAbrir}</a>` : ''}
          <a href="https://www.google.com/maps?q=${p.lat},${p.lng}" style="display:inline-block; text-align:center; padding:8px 12px; background:#f3f4f6; color:#374151; font-size:13px; font-weight:500; border-radius:6px; text-decoration:none; border:1px solid #e5e7eb;" target="_blank" rel="noopener">🌍 Ver en Google Maps</a>
        </div>
      </div>`;
                const icon = L.divIcon({
                    className: 'custom-marker',
                    html: `<div style="background:${p.color}; width:16px; height:16px; border-radius:50%; border:2px solid white; box-shadow:0 0 2px rgba(0,0,0,0.4);"></div>`,
                    iconSize: [16, 16],
                    iconAnchor: [8, 8],
                });
                return L.marker([Number(p.lat), Number(p.lng)], {
                    icon
                }).bindPopup(popupHtml);
            }

            window.initRefMap = function() {
                const wrapper = document.getElementById('ref-map-wrapper');
                const el = document.getElementById('referencias-map');
                if (!wrapper || !el) return;

                if (window.__refMap && window.__refMap._container !== el) {
                    try {
                        window.__refMap.remove();
                    } catch (e) {}
                    window.__refMap = null;
                    window.__refCluster = null;
                }

                if (!window.__refMap) {
                    const map = L.map(el, {
                        zoomControl: true,
                        scrollWheelZoom: true
                    });
                    window.__refMap = map;

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 16,
                        attribution: '&copy; OpenStreetMap contributors',
                    }).addTo(map);

                    window.__refCluster = L.markerClusterGroup({
                        chunkedLoading: true,
                        chunkDelay: 16,
                        chunkInterval: 200,
                        disableClusteringAtZoom: 16,
                        showCoverageOnHover: false,
                    });
                    window.__refCluster.addTo(map);

                    map.setView(DEFAULT_CENTER, DEFAULT_ZOOM);
                    setTimeout(() => map.invalidateSize(), 80);

                    const observer = new MutationObserver((mutations) => {
                        for (const m of mutations) {
                            if (m.type === 'attributes' && m.attributeName === 'data-markers') {
                                window.updateRefMap?.();
                            }
                        }
                    });
                    observer.observe(wrapper, {
                        attributes: true,
                        attributeFilter: ['data-markers']
                    });
                }

                window.updateRefMap();
            };

            window.updateRefMap = function() {
                const wrapper = document.getElementById('ref-map-wrapper');
                const el = document.getElementById('referencias-map');
                const emptyOverlay = document.getElementById('ref-map-empty');
                if (!wrapper || !el || !window.__refMap || !window.__refCluster) return;

                let puntos = [];
                try {
                    puntos = JSON.parse(wrapper.dataset.markers || '[]');
                } catch (e) {
                    puntos = [];
                }

                window.__refCluster.clearLayers();

                if (Array.isArray(puntos) && puntos.length > 0) {
                    const markers = puntos.map(p => buildMarker(p));
                    markers.forEach(m => window.__refCluster.addLayer(m));

                    const bounds = window.__refCluster.getBounds();
                    if (bounds.isValid()) window.__refMap.fitBounds(bounds, {
                        padding: [24, 24],
                        maxZoom: 14,
                    });
                    else window.__refMap.setView(DEFAULT_CENTER, DEFAULT_ZOOM);

                    if (emptyOverlay) emptyOverlay.style.display = 'none';
                } else {
                    window.__refMap.setView(DEFAULT_CENTER, DEFAULT_ZOOM);
                    if (emptyOverlay) emptyOverlay.style.display = 'flex';
                }

                setTimeout(() => window.__refMap.invalidateSize(), 60);
            };

            document.addEventListener('livewire:load', () => {
                setTimeout(() => window.initRefMap?.(), 80);
            });
        </script>
    @endpush
</x-filament::page>
