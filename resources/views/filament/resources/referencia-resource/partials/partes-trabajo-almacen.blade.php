<x-filament::section>
    @php

        $entradas = $salidas = collect();
        $resumenStock = collect();
        $globalStock = null;
        $trazabilidadPorCarga = collect();

        // Helper certificación
        $mapCertLabel = function (?string $raw): string {
            $raw = trim($raw ?? '');

            if ($raw === '') {
                return '—';
            }

            // Reemplazar guiones bajos por espacios
            $normalized = str_replace('_', ' ', $raw);

            // Colapsar espacios múltiples
            $normalized = preg_replace('/\s+/', ' ', $normalized);

            // Pasar a mayúsculas
            $normalized = mb_strtoupper($normalized, 'UTF-8');

            // Correcciones específicas de typos conocidos
            $normalized = str_replace('SURE INDUESTRIAL', 'SURE INDUSTRIAL', $normalized);
            $normalized = str_replace('SURE FORESAL', 'SURE FORESTAL', $normalized);

            return $normalized;
        };

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

            // === RESUMEN STOCK (igual que PrioridadStock) + GLOBAL FIFO (volumen) ===
            $almacen = \App\Models\AlmacenIntermedio::find($recordId);

            if ($almacen) {
                /** @var \App\Services\StockCalculator $calc */
                $calc = app(\App\Services\StockCalculator::class);
                $agg = $calc->calcular($almacen);

                // Trazabilidad por carga (salidas sin snapshot resueltas por FIFO)
                // Formato esperado: [ carga_id => [ ['certificacion' => '...', 'especie' => '...', 'cantidad' => ...], ... ] ]
                $trazabilidadPorCarga = collect($agg['salidas_detalle_carga'] ?? []);

                // --- Detalle por combinación CERT|ESP (igual que ahora) ---
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
                        'entradas' => (float) ($agg['entradas'][$key] ?? 0),
                        'salidas' => (float) ($agg['salidas'][$key] ?? 0),
                        'ajustes' => (float) ($agg['ajustes'][$key] ?? 0),
                        'disponible' => (float) ($agg['disponible'][$key] ?? 0),
                    ];
                });

                // --- RESUMEN GLOBAL coherente con PrioridadStock ---
                $totalEntradas = (float) collect($agg['entradas'] ?? [])->sum();
                $totalSalidasTotales = (float) ($agg['salidas_total'] ?? 0); // 🔹 TODAS las salidas (con y sin snapshot)
                $totalAjustes = (float) collect($agg['ajustes'] ?? [])->sum();
                $totalDispTrazado = (float) collect($agg['disponible'] ?? [])->sum(); // suma por cert|esp

                $globalStock = (object) [
                    'entradas' => $totalEntradas,
                    'salidas_totales' => $totalSalidasTotales,
                    'ajustes' => $totalAjustes,
                    'disponible_trazado' => $totalDispTrazado,
                    'disponible_real' => $totalEntradas - $totalSalidasTotales + $totalAjustes,
                ];
            }

            /**
             * Mapea un grupo de cargas de un parte (tanto entrada como salida)
             * y construye: fecha, refs, transportista, cliente, cantidad_total
             * y combo de "Certificación / Especie" usando:
             *  - Referencias (tipo_certificacion + producto_especie)
             *  - o snapshot
             *  - o trazabilidad FIFO por carga (salidas sin snapshot)
             */
            $mapParte = function ($cargas) use ($mapEspLabel, $mapCertLabel, $trazabilidadPorCarga) {
                $parte = $cargas->first()->parteTrabajoSuministroTransporte;

                $u = $parte?->usuario;
                $transportista = trim(($u?->name ?? '') . ' ' . ($u?->apellidos ?? '')) ?: '—';

                if ($u?->proveedor_id) {
                    $nombreProveedor = $u->proveedor?->razon_social ?? null;
                    if ($nombreProveedor) {
                        $transportista .= ' (Prov. ' . $nombreProveedor . ')';
                    }
                }

                // === CERTIFICACIÓN + ESPECIE ===
                $combos = collect();

                // 1) Desde referencias vinculadas
                $tieneReferencias = $cargas->pluck('referencia')->filter()->isNotEmpty();

                if ($tieneReferencias) {
                    foreach ($cargas as $c) {
                        $ref = $c->referencia;
                        if (!$ref) {
                            continue;
                        }

                        $certRaw = $ref->tipo_certificacion ?? null;
                        $espRaw = $ref->producto_especie ?? null;

                        $cert = $mapCertLabel($certRaw);
                        $esp = $mapEspLabel($espRaw);

                        // Evitamos meter "— —" si no hay nada
                        if ($cert === '—' && $esp === '—') {
                            continue;
                        }

                        if ($cert === '—') {
                            $cert = '';
                        }
                        if ($esp === '—') {
                            $esp = '';
                        }

                        $texto = trim($cert . ' ' . $esp);
                        if ($texto !== '') {
                            $combos->push($texto);
                        }
                    }
                } else {
                    // 2) Sin referencia: snapshot + trazabilidad FIFO
                    $combosSnap = collect();

                    foreach ($cargas as $c) {
                        // 2.a) Snapshot si existe
                        $snap = $c->asignacion_cert_esp;

                        if (!empty($snap)) {
                            if (is_string($snap)) {
                                $arr = json_decode($snap, true) ?: [];
                            } elseif (is_array($snap)) {
                                $arr = $snap;
                            } else {
                                $arr = [];
                            }

                            foreach ($arr as $a) {
                                $certRaw = $a['certificacion'] ?? null;
                                $espRaw = $a['especie'] ?? null;

                                $cert = $mapCertLabel($certRaw);
                                $esp = $mapEspLabel($espRaw);

                                if ($cert === '—' && $esp === '—') {
                                    continue;
                                }

                                if ($cert === '—') {
                                    $cert = '';
                                }
                                if ($esp === '—') {
                                    $esp = '';
                                }

                                $texto = trim($cert . ' ' . $esp);
                                if ($texto !== '') {
                                    $combosSnap->push($texto);
                                }
                            }
                        }

                        // 2.b) Detalle FIFO por carga (para salidas sin snapshot)
                        if ($trazabilidadPorCarga->isNotEmpty()) {
                            $det = collect($trazabilidadPorCarga->get($c->id, []));
                            foreach ($det as $a) {
                                $certRaw = $a['certificacion'] ?? null;
                                $espRaw = $a['especie'] ?? null;

                                $cert = $mapCertLabel($certRaw);
                                $esp = $mapEspLabel($espRaw);

                                if ($cert === '—' && $esp === '—') {
                                    continue;
                                }

                                if ($cert === '—') {
                                    $cert = '';
                                }
                                if ($esp === '—') {
                                    $esp = '';
                                }

                                $texto = trim($cert . ' ' . $esp);
                                if ($texto !== '') {
                                    $combosSnap->push($texto);
                                }
                            }
                        }
                    }

                    if ($combosSnap->isNotEmpty()) {
                        $combos = $combosSnap;
                    }
                }

                $combos = $combos->unique()->values();
                $certEspTexto = $combos->isNotEmpty() ? $combos->implode(', ') : '—';

                return (object) [
                    'inicio' => $cargas->min('fecha_hora_inicio_carga'),
                    'fin' => $cargas->max('fecha_hora_fin_carga'),
                    'cantidad_total' => (float) $cargas->sum('cantidad'),
                    'cliente' => $parte?->cliente?->razon_social ?? '—',
                    'referencias' => $cargas->pluck('referencia.referencia')->filter()->unique()->values(),
                    'transportista' => $transportista,
                    'cert_esp' => $certEspTexto, // ⬅️ "CERTIFICACIÓN ESPECIE"
                ];
            };

            // === ENTRADAS === (descarga desde referencia → este almacén, sin cliente)
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
                ->sortBy('inicio')
                ->values();

            // === SALIDAS === (desde este almacén → cliente)
            $salidas = \App\Models\CargaTransporte::with($with)
                ->whereNull('deleted_at')
                ->where('almacen_id', $recordId)
                ->whereHas('parteTrabajoSuministroTransporte', fn($q) => $q->whereNotNull('cliente_id'))
                ->get()
                ->groupBy('parte_trabajo_suministro_transporte_id')
                ->map($mapParte)
                ->sortBy('inicio')
                ->values();
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

    {{-- =============== RESUMEN GLOBAL (INCLUYE SALIDAS SIN SNAPSHOT) =============== --}}
    @if ($globalStock)
        <div class="mb-4 rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-900 space-y-1">
            <p><strong>Resumen global (almacén completo):</strong></p>
            <p>Entradas totales: {{ $fmtNum($globalStock->entradas) }} m³</p>
            <p>Salidas totales (todas): {{ $fmtNum($globalStock->salidas_totales) }} m³</p>
            <p>Ajustes: {{ $fmtNum($globalStock->ajustes) }} m³</p>
            <p>Disponible trazado (por combinaciones): {{ $fmtNum($globalStock->disponible_trazado) }} m³</p>
            <p class="font-semibold">
                Disponible real (Entradas - Salidas totales + Ajustes):
                {{ $fmtNum($globalStock->disponible_real) }} m³
            </p>
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
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Certificación / Especie</th>
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
                                {{ $p->cert_esp }}
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
                    <p><span class="font-semibold">Certificación / Especie:</span> {{ $p->cert_esp }}</p>
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
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Certificación / Especie</th>
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
                                {{ $p->cert_esp }}
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
                    <p><span class="font-semibold">Certificación / Especie:</span> {{ $p->cert_esp }}</p>
                    <p><span class="font-semibold">Cantidad:</span>
                        {{ $fmtNum($p->cantidad_total) }} m³</p>
                    <p><span class="font-semibold">Destino:</span> {{ $p->cliente }}</p>
                </div>
            @endforeach
        </div>
    @endif
</x-filament::section>
