<x-filament::section>
    @php

        $entradas = $salidas = collect();
        $resumenStock = collect();

        // Helper especie
        $mapEspLabel = function (?string $raw): string {
            $raw = strtolower(trim($raw ?? ''));
            return match ($raw) {
                'pino' => 'Pino',
                'eucalipto' => 'Eucalipto',
                'acacia' => 'Acacia',
                'frondosa' => 'Frondosa',
                'otros' => 'Otros',
                default => $raw ? ucfirst($raw) : '—',
            };
        };

        if ($recordId) {
            $with = [
                'parteTrabajoSuministroTransporte.cliente',
                'parteTrabajoSuministroTransporte.usuario',
                'referencia',
            ];

            $mapParte = function ($cargas) use ($mapEspLabel) {
                $parte = $cargas->first()->parteTrabajoSuministroTransporte;

                $u = $parte?->usuario;
                $transportista = trim(($u?->name ?? '') . ' ' . ($u?->apellidos ?? '')) ?: '—';

                if ($u?->proveedor_id) {
                    $nombreProveedor = $u->proveedor?->razon_social ?? null;
                    if ($nombreProveedor) {
                        $transportista .= ' (Prov. ' . $nombreProveedor . ')';
                    }
                }

                // === ESPECIE desde referencia o snapshot ===
                $especies = collect();

                $especiesRef = $cargas->pluck('referencia.producto_especie')->filter();
                if ($especiesRef->isNotEmpty()) {
                    $especies = $especiesRef->map(fn($e) => $mapEspLabel($e))->unique()->values();
                } else {
                    $esSnap = collect();
                    foreach ($cargas as $c) {
                        if (!$c->asignacion_cert_esp) {
                            continue;
                        }
                        foreach (json_decode($c->asignacion_cert_esp, true) ?: [] as $a) {
                            if (!empty($a['especie'])) {
                                $esSnap->push($a['especie']);
                            }
                        }
                    }
                    if ($esSnap->isNotEmpty()) {
                        $especies = $esSnap->map(fn($e) => $mapEspLabel($e))->unique()->values();
                    }
                }

                return (object) [
                    'inicio' => $cargas->min('fecha_hora_inicio_carga'),
                    'fin' => $cargas->max('fecha_hora_fin_carga'),
                    'cantidad_total' => (float) $cargas->sum('cantidad'),
                    'cliente' => $parte?->cliente?->razon_social ?? '—',
                    'referencias' => $cargas->pluck('referencia.referencia')->filter()->unique()->values(),
                    'transportista' => $transportista,
                    'especie' => $especies->implode(', ') ?: '—',
                ];
            };

            // === ENTRADAS ===
            $entradas = \App\Models\CargaTransporte::with($with)
                ->whereNull('deleted_at')
                ->whereHas(
                    'parteTrabajoSuministroTransporte',
                    fn($q) => $q->where('almacen_id', $recordId)->whereNull('cliente_id'),
                )
                ->whereNotNull('referencia_id')
                ->get()
                ->groupBy('parte_trabajo_suministro_transporte_id')
                ->map($mapParte)
                ->values();

            // === SALIDAS ===
            $salidas = \App\Models\CargaTransporte::with($with)
                ->whereNull('deleted_at')
                ->where('almacen_id', $recordId)
                ->whereHas('parteTrabajoSuministroTransporte', fn($q) => $q->whereNotNull('cliente_id'))
                ->get()
                ->groupBy('parte_trabajo_suministro_transporte_id')
                ->map($mapParte)
                ->values();

            // === RESUMEN STOCK ===
            $almacen = \App\Models\AlmacenIntermedio::find($recordId);

            if ($almacen) {
                $calc = app(\App\Services\StockCalculator::class);
                $agg = $calc->calcular($almacen);

                $keys = collect(array_keys($agg['entradas'] ?? []))
                    ->merge(array_keys($agg['salidas'] ?? []))
                    ->merge(array_keys($agg['ajustes'] ?? []))
                    ->merge(array_keys($agg['disponible'] ?? []))
                    ->unique();

                $resumenStock = $keys->map(function ($key) use ($agg) {
                    [$cert, $esp] = explode('|', $key) + [null, null];

                    return (object) [
                        'cert' => $cert ?: '—',
                        'esp' => $esp ?: '—',
                        'entradas' => $agg['entradas'][$key] ?? 0,
                        'salidas' => $agg['salidas'][$key] ?? 0,
                        'ajustes' => $agg['ajustes'][$key] ?? 0,
                        'disponible' => $agg['disponible'][$key] ?? 0,
                    ];
                });
            }
        }

        $fmt = fn($dt) => optional($dt)?->timezone('Europe/Madrid')->format('d/m/Y H:i');
        $fmtNum = fn($n) => number_format((float) $n, 2, ',', '.');

    @endphp

    <x-slot name="heading">Suministros Transporte</x-slot>

    {{-- =============== RESUMEN STOCK (POR CERTIFICACIÓN + ESPECIE) =============== --}}
    @if ($resumenStock->isNotEmpty())
        <div class="mb-4 overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Certificación</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Especie</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-700">Entradas (m³)</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-700">Salidas (m³)</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-700">Ajustes (m³)</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-700">Disponible (m³)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($resumenStock as $row)
                        <tr class="bg-gray-100">
                            <td class="px-4 py-3 text-gray-800">{{ $row->cert }}</td>
                            <td class="px-4 py-3 text-gray-800">{{ $row->esp }}</td>
                            <td class="px-4 py-3 text-right text-gray-800">{{ $fmtNum($row->entradas) }}</td>
                            <td class="px-4 py-3 text-right text-gray-800">{{ $fmtNum($row->salidas) }}</td>
                            <td class="px-4 py-3 text-right text-gray-800">{{ $fmtNum($row->ajustes) }}</td>
                            <td class="px-4 py-3 text-right text-gray-800 font-semibold">
                                {{ $fmtNum($row->disponible) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

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
                                {{ $fmtNum($p->cantidad_total) }}
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
                    <p><span class="font-semibold">Cantidad:</span>
                        {{ $fmtNum($p->cantidad_total) }} m³</p>
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
                                {{ $fmtNum($p->cantidad_total) }}
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
                    <p><span class="font-semibold">Cantidad:</span>
                        {{ $fmtNum($p->cantidad_total) }} m³</p>
                    <p><span class="font-semibold">Destino:</span> {{ $p->cliente }}</p>
                </div>
            @endforeach
        </div>
    @endif
</x-filament::section>
