<x-filament::section>
    @php
        // ===== utilidades que ya tienes arriba =====
        $fmtNum = fn($n) => number_format((float) $n, 2, ',', '.');

        $certCatalog = [
            'sure_induestrial' => 'SURE - Industrial',
            'sure_foresal' => 'SURE - Forestal',
            'sbp' => 'SBP',
            'pefc' => 'PEFC',
            '__none__' => 'Sin certificar',
        ];
        $ordenCerts = ['sure_induestrial', 'sure_foresal', 'sbp', 'pefc', '__none__'];

        $normCertKeyFromRef = function (?string $raw): string {
            $raw = (string) ($raw ?? '');
            $normalize = function (string $s): string {
                $s = mb_strtolower($s, 'UTF-8');
                $s = strtr($s, [
                    'á' => 'a',
                    'é' => 'e',
                    'í' => 'i',
                    'ó' => 'o',
                    'ú' => 'u',
                    'à' => 'a',
                    'è' => 'e',
                    'ì' => 'i',
                    'ò' => 'o',
                    'ù' => 'u',
                    'ä' => 'a',
                    'ë' => 'e',
                    'ï' => 'i',
                    'ö' => 'o',
                    'ü' => 'u',
                    'ñ' => 'n',
                ]);
                return preg_replace('/[\s_\-]+/u', '', $s);
            };
            $n = $normalize($raw);

            $map = [
                'sureinduestrial' => 'sure_induestrial',
                'sureindustrial' => 'sure_induestrial',
                'sureforesal' => 'sure_foresal',
                'sureforestal' => 'sure_foresal',
                'sbp' => 'sbp',
                'pefc' => 'pefc',
            ];
            if (isset($map[$n])) {
                return $map[$n];
            }
            if (str_contains($n, 'industrial') || str_contains($n, 'indust')) {
                return 'sure_induestrial';
            }
            if (str_contains($n, 'forestal') || str_contains($n, 'foresal') || str_contains($n, 'forest')) {
                return 'sure_foresal';
            }
            if ($n === 'sbp') {
                return 'sbp';
            }
            if ($n === 'pefc') {
                return 'pefc';
            }
            return '__none__';
        };

        // Inicializa agregados
        $entradasCertAgg = collect(array_fill_keys(array_keys($certCatalog), 0.0));
        $salidasCertAgg = collect(array_fill_keys(array_keys($certCatalog), 0.0));

        if ($recordId) {
            // ENTRADAS: referencia -> este almacén (sin cliente)
            $cargasEntrada = \App\Models\CargaTransporte::query()
                ->with([
                    'referencia:id,tipo_certificacion',
                    'parteTrabajoSuministroTransporte:id,almacen_id,cliente_id',
                ])
                ->whereNull('deleted_at')
                ->whereNotNull('referencia_id')
                ->whereHas(
                    'parteTrabajoSuministroTransporte',
                    fn($q) => $q->where('almacen_id', $recordId)->whereNull('cliente_id'),
                )
                ->get();

            foreach ($cargasEntrada as $c) {
                $key = $normCertKeyFromRef(optional($c->referencia)->tipo_certificacion);
                $entradasCertAgg[$key] += (float) $c->cantidad;
            }
        }

        $certKeysOrdenadas = collect($ordenCerts);

        // ===== Agregados por ESPECIE =====
        $entradasAgg = collect();
        $salidasAgg = collect();

        if ($recordId) {
            $with = ['parteTrabajoSuministroTransporte.cliente', 'parteTrabajoSuministroTransporte.usuario'];

            $normEspecie = function ($parte) {
                $raw = $parte?->tipo_biomasa;
                if (is_array($raw)) {
                    $raw = collect($raw)->filter()->map(fn($v) => trim((string) $v))->values();
                    if ($raw->count() === 1) {
                        return $raw->first();
                    }
                    return $raw->count() > 1 ? 'Mixto' : 'Sin especificar';
                }
                $raw = trim((string) ($raw ?? ''));
                return $raw !== '' ? $raw : 'Sin especificar';
            };

            $entradasByParte = \App\Models\CargaTransporte::with($with)
                ->whereNull('deleted_at')
                ->whereHas(
                    'parteTrabajoSuministroTransporte',
                    fn($q) => $q->where('almacen_id', $recordId)->whereNull('cliente_id'),
                )
                ->get()
                ->groupBy('parte_trabajo_suministro_transporte_id')
                ->map(function ($cargas) use ($normEspecie) {
                    $parte = $cargas->first()->parteTrabajoSuministroTransporte;
                    return (object) [
                        'especie' => $normEspecie($parte),
                        'cantidad_total' => (float) $cargas->sum('cantidad'),
                    ];
                })
                ->values();

            $salidasByParte = \App\Models\CargaTransporte::with($with)
                ->whereNull('deleted_at')
                ->where('almacen_id', $recordId)
                ->whereHas('parteTrabajoSuministroTransporte', fn($q) => $q->whereNotNull('cliente_id'))
                ->get()
                ->groupBy('parte_trabajo_suministro_transporte_id')
                ->map(function ($cargas) use ($normEspecie) {
                    $parte = $cargas->first()->parteTrabajoSuministroTransporte;
                    return (object) [
                        'especie' => $normEspecie($parte),
                        'cantidad_total' => (float) $cargas->sum('cantidad'),
                    ];
                })
                ->values();

            $entradasAgg = $entradasByParte
                ->groupBy('especie')
                ->map(fn($it) => (float) collect($it)->sum('cantidad_total'));
            $salidasAgg = $salidasByParte
                ->groupBy('especie')
                ->map(fn($it) => (float) collect($it)->sum('cantidad_total'));
        }

        // ======== REGULARIZACIÓN SOLO PARA "CTB BARRANTES" ========
        if ($recordId) {
            $almacen = \App\Models\AlmacenIntermedio::find($recordId);
            if ($almacen && trim((string) $almacen->referencia) === 'CTB BARRANTES') {
                // Toneladas
                $tnForestal = 501.34;
                $tnIndustrial = 11.2;

                // Conversión tn -> m³ (densidad 1 por defecto)
                $m3Forestal = $tnForestal;
                $m3Industrial = $tnIndustrial;

                // Certificación → arranca stock con estos valores
                $entradasCertAgg['sure_foresal'] += $m3Forestal;
                $entradasCertAgg['sure_induestrial'] += $m3Industrial;

                // Especie → todo a una sola categoría: "Regulación"
                $m3Total = $m3Forestal + $m3Industrial;
                $entradasAgg['Regulación'] = (float) ($entradasAgg['Regulación'] ?? 0) + $m3Total;
                //$salidasAgg['Regulación'] = (float) ($salidasAgg['Regulación'] ?? 0) + $m3Total;
            }
        }
        // ==========================================================

        $todasEspecies = $entradasAgg->keys()->merge($salidasAgg->keys())->unique()->sort()->values();
        $totalE = (float) ($entradasAgg->sum() ?? 0);
        $totalS = (float) ($salidasAgg->sum() ?? 0);
        $totalStock = $totalE - $totalS;
    @endphp

    {{-- ======== BLOQUE: Stock por Certificación ======== --}}
    <x-slot name="heading">Stock por Certificación</x-slot>

    {{-- Desktop --}}
    <div class="hidden md:block overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm mb-6">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-700">Certificación</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-700">Entradas (m³)</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-700">Salidas (m³)</th>
                    <th class="px-4 py-3 text-right font-medium text-gray-700">Stock (m³)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($certKeysOrdenadas as $ck)
                    @php
                        $label = $certCatalog[$ck] ?? $ck;
                        $e = (float) ($entradasCertAgg[$ck] ?? 0);
                        $s = (float) ($salidasCertAgg[$ck] ?? 0);
                        $st = $e - $s;
                    @endphp
                    <tr class="bg-gray-100">
                        <td class="px-4 py-3 text-gray-800">{{ $label }}</td>
                        <td class="px-4 py-3 text-right text-gray-800">{{ $fmtNum($e) }}</td>
                        <td class="px-4 py-3 text-right text-gray-800">- Por definir -</td>
                        <td class="px-4 py-3 text-right {{ $st >= 0 ? 'text-green-700' : 'text-red-700' }}">
                            {{ $fmtNum($st) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50">
                @php
                    $totalEcert = (float) $entradasCertAgg->sum();
                    $totalScert = (float) $salidasCertAgg->sum();
                    $totalStcert = $totalEcert - $totalScert;
                @endphp
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-800">Total</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-800">{{ $fmtNum($totalEcert) }}</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-800">{{ $fmtNum($totalScert) }}</th>
                    <th
                        class="px-4 py-3 text-right font-semibold {{ $totalStcert >= 0 ? 'text-green-700' : 'text-red-700' }}">
                        {{ $fmtNum($totalStcert) }}
                    </th>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Móvil --}}
    <div class="md:hidden space-y-4 mb-6">
        @foreach ($certKeysOrdenadas as $ck)
            @php
                $label = $certCatalog[$ck] ?? $ck;
                $e = (float) ($entradasCertAgg[$ck] ?? 0);
                $s = (float) ($salidasCertAgg[$ck] ?? 0);
                $st = $e - $s;
            @endphp
            <div class="rounded-xl border border-gray-300 bg-white shadow-sm p-4">
                <p><span class="font-semibold">Certificación:</span> {{ $label }}</p>
                <p><span class="font-semibold">Entradas:</span> {{ $fmtNum($e) }} m³</p>
                <p><span class="font-semibold">Salidas:</span> {{ $fmtNum($s) }} m³</p>
                <p><span class="font-semibold">Stock:</span>
                    <span class="{{ $st >= 0 ? 'text-green-700' : 'text-red-700' }}">{{ $fmtNum($st) }} m³</span>
                </p>
            </div>
        @endforeach
    </div>

    {{-- ======== BLOQUE: Stock por Especie ======== --}}
    <x-slot name="heading">Stock</x-slot>

    @if ($todasEspecies->isEmpty())
        <p class="text-sm text-gray-500">No hay movimientos de entradas/salidas para calcular stock.</p>
    @else
        {{-- Desktop --}}
        <div class="hidden md:block overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Especie</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-700">Entradas (m³)</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-700">Salidas (m³)</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-700">Stock (m³)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($todasEspecies as $esp)
                        @php
                            $e = (float) ($entradasAgg[$esp] ?? 0);
                            $s = (float) ($salidasAgg[$esp] ?? 0);
                            $stock = $e - $s;
                        @endphp
                        <tr class="bg-gray-100">
                            <td class="px-4 py-3 text-gray-800">{{ $esp }}</td>
                            <td class="px-4 py-3 text-right text-gray-800">{{ $fmtNum($e) }}</td>
                            <td class="px-4 py-3 text-right text-gray-800">{{ $fmtNum($s) }}</td>
                            <td class="px-4 py-3 text-right {{ $stock >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                {{ $fmtNum($stock) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50">
                    {{-- KPIs --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-gray-500">Entradas</p>
                                    <p class="mt-1 text-2xl font-semibold">
                                        {{ $fmtNum($totalE) }} <span
                                            class="text-sm font-normal text-gray-500">m³</span>
                                    </p>
                                </div>
                                <x-heroicon-m-arrow-down-tray class="h-7 w-7 text-green-600" />
                            </div>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-gray-500">Salidas</p>
                                    <p class="mt-1 text-2xl font-semibold">
                                        {{ $fmtNum($totalS) }} <span
                                            class="text-sm font-normal text-gray-500">m³</span>
                                    </p>
                                </div>
                                <x-heroicon-m-arrow-up-tray class="h-7 w-7 text-amber-600" />
                            </div>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-gray-500">Stock</p>
                                    <p
                                        class="mt-1 text-2xl font-semibold {{ $totalStock >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                        {{ $fmtNum($totalStock) }} <span
                                            class="text-sm font-normal text-gray-500">m³</span>
                                    </p>
                                </div>
                                <x-heroicon-m-cube
                                    class="h-7 w-7 {{ $totalStock >= 0 ? 'text-green-700' : 'text-red-700' }}" />
                            </div>
                        </div>
                    </div>

                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-gray-800">Total</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-800">{{ $fmtNum($totalE) }}</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-800">{{ $fmtNum($totalS) }}</th>
                        <th
                            class="px-4 py-3 text-right font-semibold {{ $totalStock >= 0 ? 'text-green-700' : 'text-red-700' }}">
                            {{ $fmtNum($totalStock) }}
                        </th>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Móvil --}}
        <div class="md:hidden space-y-4 mt-4">
            @foreach ($todasEspecies as $esp)
                @php
                    $e = (float) ($entradasAgg[$esp] ?? 0);
                    $s = (float) ($salidasAgg[$esp] ?? 0);
                    $stock = $e - $s;
                @endphp
                <div class="rounded-xl border border-gray-300 bg-white shadow-sm p-4">
                    <p><span class="font-semibold">Especie:</span> {{ $esp }}</p>
                    <p><span class="font-semibold">Entradas:</span> {{ $fmtNum($e) }} m³</p>
                    <p><span class="font-semibold">Salidas:</span> {{ $fmtNum($s) }} m³</p>
                    <p>
                        <span class="font-semibold">Stock:</span>
                        <span class="{{ $stock >= 0 ? 'text-green-700' : 'text-red-700' }}">
                            {{ $fmtNum($stock) }} m³
                        </span>
                    </p>
                </div>
            @endforeach

            {{-- Totales --}}
            <div class="rounded-xl border border-gray-300 bg-white shadow-sm p-4">
                <p class="font-semibold mb-1">Totales</p>
                <p>Entradas: {{ $fmtNum((float) $entradasAgg->sum()) }} m³</p>
                <p>Salidas: {{ $fmtNum((float) $salidasAgg->sum()) }} m³</p>
                @php
                    $totalStockMovil = (float) $entradasAgg->sum() - (float) $salidasAgg->sum();
                @endphp
                <p>Stock:
                    <span class="{{ $totalStockMovil >= 0 ? 'text-green-700' : 'text-red-700' }}">
                        {{ $fmtNum($totalStockMovil) }} m³
                    </span>
                </p>
            </div>
        </div>
    @endif
</x-filament::section>
