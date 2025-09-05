{{-- raíz ÚNICA --}}
<div wire:ignore x-data x-init="$nextTick(() => window.initRefMap?.())">
    <x-filament::section>
        <x-slot name="heading">Mapa de referencias</x-slot>

        @if (empty($markers) || count($markers) === 0)
            <p class="text-sm text-gray-500">No hay referencias con ubicación GPS para mostrar.</p>
        @else
            <div id="referencias-map" style="height: 800px; border-radius: 12px; overflow: hidden;"></div>
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
            const refActualId = @json($referenciaActualId ?? null);

            const el = document.getElementById('referencias-map');
            if (!el || !Array.isArray(puntos) || puntos.length === 0) return;
            if (el.dataset.inited === '1') return;
            el.dataset.inited = '1';

            const map = L.map(el, {
                zoomControl: true,
                scrollWheelZoom: true
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(map);

            const cluster = L.markerClusterGroup({
                chunkedLoading: true,
                chunkDelay: 16,
                chunkInterval: 200,
                disableClusteringAtZoom: 16,
                spiderfyOnEveryZoom: false,
                showCoverageOnHover: false,
            });

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

            // 👇 Normaliza tipos para evitar fallo número vs string
            const refActualIdNorm = refActualId == null ? null : String(refActualId);

            puntos.forEach((p) => {
                const isActual = refActualIdNorm !== null && String(p.id) === refActualIdNorm;

                const popupHtml = `
                  <div style="min-width:220px; font-family:Arial, sans-serif; color:#1f2937;">
                    <div style="font-weight:600; margin-bottom:10px; font-size:14px; color:#111827;">
                      ${p.titulo}
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


                const m = L.marker([p.lat, p.lng], {
                    icon: isActual ? iconResaltado : iconDefault,
                    zIndexOffset: isActual ? 1000 : 0, // que quede por encima
                }).bindPopup(popupHtml, {
                    autoPanPaddingTopLeft: [12, 12]
                });

                m.on('popupopen', (e) => {
                    const a = e.popup.getElement()?.querySelector('a');
                    if (a) a.addEventListener('click', (ev) => ev.stopPropagation());
                });

                cluster.addLayer(m);
            });

            cluster.addTo(map);

            if (refActualIdNorm) {
                const actual = puntos.find(p => String(p.id) === refActualIdNorm);
                if (actual) {
                    map.setView([actual.lat, actual.lng], 12);
                } else {
                    if (puntos.length === 1) {
                        map.setView([puntos[0].lat, puntos[0].lng], 10);
                    } else {
                        map.fitBounds(cluster.getBounds(), {
                            padding: [24, 24]
                        });
                        if (map.getZoom() > 6) map.setZoom(6);
                    }
                }
            } else {
                if (puntos.length === 1) {
                    map.setView([puntos[0].lat, puntos[0].lng], 10);
                } else {
                    map.fitBounds(cluster.getBounds(), {
                        padding: [24, 24]
                    });
                    if (map.getZoom() > 6) map.setZoom(6);
                }
            }

            setTimeout(() => map.invalidateSize(), 80);
        };
    </script>
@endpush
