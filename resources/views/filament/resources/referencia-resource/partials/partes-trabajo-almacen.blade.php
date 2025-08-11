<x-filament::section>
    @php

        $entradas = $salidas = collect();

        if ($recordId) {
            $with = [
                'parteTrabajoSuministroTransporte.cliente',
                'parteTrabajoSuministroTransporte.usuario',
                'referencia',
            ];

            $mapParte = function ($cargas) {
                $parte   = $cargas->first()->parteTrabajoSuministroTransporte;

                // Transportista + proveedor_id (si tiene)
                $u = $parte?->usuario;
                $transportista = trim(($u?->name ?? '') . ' ' . ($u?->apellidos ?? '')) ?: '—';
                if ($u?->proveedor_id) {
                    $transportista .= ' (Prov. ' . $u->proveedor_id . ')';
                }

                // Especie (tipo_biomasa puede ser string o array)
                $especie = $parte?->tipo_biomasa;
                if (is_array($especie)) {
                    // Si viene como array asociativo, nos quedamos con valores
                    $especie = collect($especie)->values()->implode(', ');
                }
                $especie = $especie ?: '—';

                return (object) [
                    'inicio'         => $cargas->min('fecha_hora_inicio_carga'),
                    'fin'            => $cargas->max('fecha_hora_fin_carga'),
                    'cantidad_total' => (float) $cargas->sum('cantidad'),
                    'cliente'        => $parte?->cliente?->razon_social ?? '—',
                    'referencias'    => $cargas->pluck('referencia.referencia')->filter()->unique()->values(),
                    'transportista'  => $transportista,
                    'especie'        => $especie,
                ];
            };

            // ===== ENTRADAS: desde referencia -> a ESTE almacén (sin cliente) =====
            $entradas = \App\Models\CargaTransporte::with($with)
                ->whereNull('deleted_at')
                ->whereHas('parteTrabajoSuministroTransporte', fn ($q) => $q
                    ->where('almacen_id', $recordId)
                    ->whereNull('cliente_id') // termina en almacén
                )
                ->get()
                ->groupBy('parte_trabajo_suministro_transporte_id')
                ->filter(function ($cargas) {
                    // Empieza en referencia (al menos una con referencia_id) y NO desde almacén
                    $tieneRef = $cargas->contains(fn ($c) => filled($c->referencia_id));
                    $tieneOrigenAlmacen = $cargas->contains(fn ($c) => filled($c->almacen_id));
                    return $tieneRef && ! $tieneOrigenAlmacen;
                })
                ->map($mapParte)
                ->values();

            // ===== SALIDAS: desde ESTE almacén -> a cliente =====
            $salidas = \App\Models\CargaTransporte::with($with)
                ->whereNull('deleted_at')
                ->where('almacen_id', $recordId) // origen: este almacén
                ->whereHas('parteTrabajoSuministroTransporte', fn ($q) => $q
                    ->whereNotNull('cliente_id') // termina en cliente
                )
                ->get()
                ->groupBy('parte_trabajo_suministro_transporte_id')
                ->map($mapParte)
                ->values();
        }

        $fmt = fn ($dt) => optional($dt)?->timezone('Europe/Madrid')->format('d/m/Y H:i');
    @endphp

    <x-slot name="heading">Suministros Transporte</x-slot>

    {{-- ================= ENTRADAS ================= --}}
    <h3 class="mt-2 mb-2 text-sm font-semibold text-gray-700">Entradas (a este almacén)</h3>

    @if ($entradas->isEmpty())
        <p class="text-sm text-gray-500">No hay entradas.</p>
    @else
        <div class="hidden md:block overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Fecha (Inicio - Fin)</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Referencia de origen</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Transportista</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Especie</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Cantidad (m³)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($entradas as $p)
                        <tr class="bg-gray-100">
                            <td class="px-4 py-3 text-gray-800">
                                {{ $fmt($p->inicio) }} - {{ $fmt($p->fin) }}
                            </td>
                            <td class="px-4 py-3 text-gray-800">
                                {{ $p->referencias->implode(', ') ?: 'N/D' }}
                            </td>
                            <td class="px-4 py-3 text-gray-800">
                                {{ $p->transportista }}
                            </td>
                            <td class="px-4 py-3 text-gray-800">
                                {{ $p->especie }}
                            </td>
                            <td class="px-4 py-3 text-gray-800">
                                {{ number_format($p->cantidad_total, 2, ',', '.') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Móvil --}}
        <div class="md:hidden space-y-4 mt-4">
            @foreach ($entradas as $p)
                <div class="rounded-xl border border-gray-300 bg-white shadow-sm p-4">
                    <p><span class="font-semibold">Fecha:</span> {{ $fmt($p->inicio) }} - {{ $fmt($p->fin) }}</p>
                    <p><span class="font-semibold">Referencia:</span> {{ $p->referencias->implode(', ') ?: 'N/D' }}</p>
                    <p><span class="font-semibold">Transportista:</span> {{ $p->transportista }}</p>
                    <p><span class="font-semibold">Especie:</span> {{ $p->especie }}</p>
                    <p><span class="font-semibold">Cantidad:</span> {{ number_format($p->cantidad_total, 2, ',', '.') }} m³</p>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ================= SALIDAS ================= --}}
    <h3 class="mt-6 mb-2 text-sm font-semibold text-gray-700">Salidas (desde este almacén)</h3>

    @if ($salidas->isEmpty())
        <p class="text-sm text-gray-500">No hay salidas.</p>
    @else
        <div class="hidden md:block overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Fecha (Inicio - Fin)</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Transportista</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Especie</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Cantidad (m³)</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Destino</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($salidas as $p)
                        <tr class="bg-gray-100">
                            <td class="px-4 py-3 text-gray-800">
                                {{ $fmt($p->inicio) }} - {{ $fmt($p->fin) }}
                            </td>
                            <td class="px-4 py-3 text-gray-800">
                                {{ $p->transportista }}
                            </td>
                            <td class="px-4 py-3 text-gray-800">
                                {{ $p->especie }}
                            </td>
                            <td class="px-4 py-3 text-gray-800">
                                {{ number_format($p->cantidad_total, 2, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-gray-800">
                                {{ $p->cliente }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Móvil --}}
        <div class="md:hidden space-y-4 mt-4">
            @foreach ($salidas as $p)
                <div class="rounded-xl border border-gray-300 bg-white shadow-sm p-4">
                    <p><span class="font-semibold">Fecha:</span> {{ $fmt($p->inicio) }} - {{ $fmt($p->fin) }}</p>
                    <p><span class="font-semibold">Transportista:</span> {{ $p->transportista }}</p>
                    <p><span class="font-semibold">Especie:</span> {{ $p->especie }}</p>
                    <p><span class="font-semibold">Cantidad:</span> {{ number_format($p->cantidad_total, 2, ',', '.') }} m³</p>
                    <p><span class="font-semibold">Destino:</span> {{ $p->cliente }}</p>
                </div>
            @endforeach
        </div>
    @endif
</x-filament::section>
