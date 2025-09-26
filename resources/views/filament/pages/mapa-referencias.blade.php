<x-filament::page>
    <x-filament::section>
        <x-slot name="heading">Filtros</x-slot>
        {{ $this->form }}
    </x-filament::section>

    {{-- Wrapper SIN wire:ignore (Livewire actualizará estos data-*) --}}
    @php
        $markers = $this->markers; // array de puntos
        $referenciaActualId = null; // opcional
    @endphp

    <x-filament::section>
        <x-slot name="heading">Mapa de referencias</x-slot>

        <div id="ref-map-wrapper" x-data x-init="$nextTick(() => window.initRefMap?.())" data-markers='@json($markers)'
            data-ref-actual='@json($referenciaActualId)' style="position: relative;">
            {{-- Contenedor del mapa con wire:ignore --}}
            <div id="referencias-map" wire:ignore style="height: 550px; border-radius: 12px; overflow: hidden;"></div>

            {{-- Mensaje sutil cuando no hay puntos (overlay, opcional) --}}
            <div id="ref-map-empty"
                style="position:absolute; inset:0; display:none; align-items:center; justify-content:center; pointer-events:none;">
                <p class="text-sm text-gray-500 bg-white/70 rounded-md px-3 py-2 shadow">
                    No hay referencias con ubicación GPS para mostrar.
                </p>
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
            // Variables globales para reutilizar el mismo mapa
            window.__refMap = null;
            window.__refCluster = null;

            // Vista por defecto (Península Ibérica)
            const DEFAULT_CENTER = [40.0, -3.7];
            const DEFAULT_ZOOM = 5;

            function buildMarker(p, isActual, iconDefault, iconResaltado) {
                const popupHtml = `
                    <div style="min-width:220px; font-family:Arial, sans-serif; color:#1f2937;">
                      <div style="font-weight:600; margin-bottom:10px; font-size:14px; color:#111827;">
                        ${p.titulo ?? ''}
                      </div>
                      <div style="display:flex; flex-direction:column; gap:6px;">
                        <a href="${p.url}"
                           style="display:inline-block; text-align:center; padding:8px 12px; background: rgb(var(--primary-500)); color:#111827; font-size:13px; font-weight:600; border-radius:6px; text-decoration:none; box-shadow:0 1px 2px rgba(0,0,0,0.05);"
                           target="_self" rel="noopener">
                           📄 Abrir referencia
                        </a>
                        <a href="https://www.google.com/maps?q=${p.lat},${p.lng}"
                           style="display:inline-block; text-align:center; padding:8px 12px; background:#f3f4f6; color:#374151; font-size:13px; font-weight:500; border-radius:6px; text-decoration:none; border:1px solid #e5e7eb;"
                           target="_blank" rel="noopener">
                           🌍 Ver en Google Maps
                        </a>
                      </div>
                    </div>
                `;

                const m = L.marker([Number(p.lat), Number(p.lng)], {
                    icon: isActual ? iconResaltado : iconDefault,
                    zIndexOffset: isActual ? 1000 : 0,
                }).bindPopup(popupHtml, {
                    autoPanPaddingTopLeft: [12, 12]
                });

                m.on('popupopen', (e) => {
                    const a = e.popup.getElement()?.querySelector('a');
                    if (a) a.addEventListener('click', (ev) => ev.stopPropagation());
                });

                return m;
            }

            // Inicializa el mapa una sola vez
            window.initRefMap = function() {
                const wrapper = document.getElementById('ref-map-wrapper');
                const el = document.getElementById('referencias-map');
                const emptyOverlay = document.getElementById('ref-map-empty');
                if (!wrapper || !el) return;

                // Crea el mapa si no existe
                if (!window.__refMap) {
                    const map = L.map(el, {
                        zoomControl: true,
                        scrollWheelZoom: true
                    });
                    window.__refMap = map;

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; OpenStreetMap contributors',
                    }).addTo(map);

                    // Crea el cluster y lo añade al mapa una única vez
                    window.__refCluster = L.markerClusterGroup({
                        chunkedLoading: true,
                        chunkDelay: 16,
                        chunkInterval: 200,
                        disableClusteringAtZoom: 16,
                        spiderfyOnEveryZoom: false,
                        showCoverageOnHover: false,
                    });
                    window.__refCluster.addTo(map);

                    // Vista inicial por defecto
                    map.setView(DEFAULT_CENTER, DEFAULT_ZOOM);

                    // Ajuste de tamaño por si el contenedor cambia
                    setTimeout(() => map.invalidateSize(), 80);
                }

                // Pintar/actualizar capa con los datos actuales
                window.updateRefMap();
            };

            // Actualiza los marcadores sin destruir el mapa
            window.updateRefMap = function() {
                const wrapper = document.getElementById('ref-map-wrapper');
                const el = document.getElementById('referencias-map');
                const emptyOverlay = document.getElementById('ref-map-empty');
                if (!wrapper || !el || !window.__refMap || !window.__refCluster) return;

                const puntos = JSON.parse(wrapper.dataset.markers || '[]');
                const refActualId = JSON.parse(wrapper.dataset.refActual || 'null');
                const refActualIdNorm = refActualId == null ? null : String(refActualId);

                // Limpia los marcadores previos
                window.__refCluster.clearLayers();

                const iconDefault = new L.Icon({
                    iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
                    shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
                    iconSize: [25, 41],
                    iconAnchor: [12, 41],
                    popupAnchor: [1, -34],
                    shadowSize: [41, 41],
                });

                const iconResaltado = new L.Icon({
                    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png',
                    shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
                    iconSize: [25, 41],
                    iconAnchor: [12, 41],
                    popupAnchor: [1, -34],
                    shadowSize: [41, 41],
                });

                // Añade nuevos marcadores
                if (Array.isArray(puntos) && puntos.length > 0) {
                    const markers = [];
                    puntos.forEach(p => {
                        const isActual = refActualIdNorm !== null && String(p.id) === refActualIdNorm;
                        const m = buildMarker(p, isActual, iconDefault, iconResaltado);
                        markers.push(m);
                    });
                    markers.forEach(m => window.__refCluster.addLayer(m));

                    // Ajusta vista
                    if (puntos.length === 1) {
                        window.__refMap.setView([+puntos[0].lat, +puntos[0].lng], 10);
                    } else {
                        const bounds = window.__refCluster.getBounds();
                        if (bounds.isValid()) {
                            window.__refMap.fitBounds(bounds, {
                                padding: [24, 24]
                            });
                            if (window.__refMap.getZoom() > 6) window.__refMap.setZoom(6);
                        } else {
                            window.__refMap.setView(DEFAULT_CENTER, DEFAULT_ZOOM);
                        }
                    }

                    // Oculta overlay
                    if (emptyOverlay) emptyOverlay.style.display = 'none';
                } else {
                    // Sin puntos: mantenemos el mapa y mostramos overlay
                    window.__refMap.setView(DEFAULT_CENTER, DEFAULT_ZOOM);
                    if (emptyOverlay) emptyOverlay.style.display = 'flex';
                }

                // Por si el panel/section cambia altura
                setTimeout(() => window.__refMap.invalidateSize(), 60);
            };

            // Re-pinta tras cada render de Livewire (cambio de filtros)
            document.addEventListener('livewire:load', () => {
                const rerender = () => {
                    // Actualiza dataset (Livewire lo cambia en el wrapper) y repinta
                    window.updateRefMap?.();
                };

                // Inicial
                setTimeout(() => window.initRefMap?.(), 80);

                // Cada actualización
                Livewire.hook('message.processed', () => setTimeout(rerender, 60));
            });
        </script>
    @endpush
</x-filament::page>
