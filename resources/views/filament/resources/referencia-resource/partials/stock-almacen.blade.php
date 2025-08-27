<x-filament::section>
    @php
        // ====== Catálogo fijo de certificaciones (mostrar siempre) ======
        $certCatalog = [
            'sure_induestrial' => 'SURE - Industrial',
            'sure_foresal' => 'SURE - Forestal',
            'sbp' => 'SBP',
            'pefc' => 'PEFC',
            '__none__' => 'Sin certificar',
        ];
        // Orden visual
        $ordenCerts = ['sure_induestrial', 'sure_foresal', 'sbp', 'pefc', '__none__'];

        // Helpers
        $fmtNum = fn($n) => number_format((float) $n, 2, ',', '.');

        // Normaliza especie:
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

        // Normaliza certificación (devuelve la CLAVE del catálogo: sure_induestrial, pefc, sbp, sure_foresal, o __none__)
        $normCertKey = function ($parte) {
            // 1) Si existe un flag 'certificable' y es falso → sin certificar
            $certificableParte = data_get($parte, 'certificable', null);
            $certificableRef = data_get($parte, 'referencia.certificable', null);
            if ($certificableParte === false || $certificableRef === false) {
                return '__none__';
            }

            // 2) Coge el código del parte; si no hay, de la referencia
            $code =
                data_get($parte, 'tipo_certificacion') ?? (data_get($parte, 'referencia.tipo_certificacion') ?? null);

            $code = (string) ($code ?? '');

            // Normaliza variaciones comunes (por si vinieran mal escritas)
            $mapAliases = [
                'sure-industrial' => 'sure_induestrial',
                'sure_industrial' => 'sure_induestrial',
                'sure-foresal' => 'sure_foresal',
                'sure_forestal' => 'sure_foresal', // si alguna vez usaste 'forestal'
            ];
            $code = $mapAliases[$code] ?? $code;

            // 3) Si llega vacío o no reconocido → sin certificar
            $valid = ['sure_induestrial', 'sure_foresal', 'sbp', 'pefc'];
            if ($code === '' || !in_array($code, $valid, true)) {
                return '__none__';
            }

            return $code;
        };

        // Vars de agregación
        $entradasAgg = collect(); // por especie
        $salidasAgg = collect(); // por especie
        $entradasCertAgg = collect(); // por certificación (clave)
        $salidasCertAgg = collect(); // por certificación (clave)

        if ($recordId) {
            $with = [
                'parteTrabajoSuministroTransporte.cliente',
                'parteTrabajoSuministroTransporte.usuario',
                'referencia',
            ];

            // ===== ENTRADAS: desde referencia -> a ESTE almacén (sin cliente) =====
            $entradasByParte = \App\Models\CargaTransporte::with($with)
                ->whereNull('deleted_at')
                ->whereHas(
                    'parteTrabajoSuministroTransporte',
                    fn($q) => $q->where('almacen_id', $recordId)->whereNull('cliente_id'),
                )
                ->get()
                ->groupBy('parte_trabajo_suministro_transporte_id')
                ->map(function ($cargas) use ($normEspecie, $normCertKey) {
                    $parte = $cargas->first()->parteTrabajoSuministroTransporte;
                    return (object) [
                        'especie' => $normEspecie($parte),
                        'certKey' => $normCertKey($parte),
                        'cantidad_total' => (float) $cargas->sum('cantidad'),
                    ];
                })
                ->values();

            // ===== SALIDAS: desde ESTE almacén -> a cliente =====
            $salidasByParte = \App\Models\CargaTransporte::with($with)
                ->whereNull('deleted_at')
                ->where('almacen_id', $recordId) // origen: este almacén
                ->whereHas('parteTrabajoSuministroTransporte', fn($q) => $q->whereNotNull('cliente_id'))
                ->get()
                ->groupBy('parte_trabajo_suministro_transporte_id')
                ->map(function ($cargas) use ($normEspecie, $normCertKey) {
                    $parte = $cargas->first()->parteTrabajoSuministroTransporte;
                    return (object) [
                        'especie' => $normEspecie($parte),
                        'certKey' => $normCertKey($parte),
                        'cantidad_total' => (float) $cargas->sum('cantidad'),
                    ];
                })
                ->values();

            // ===== Agregados por ESPECIE =====
            $entradasAgg = $entradasByParte
                ->groupBy('especie')
                ->map(fn($items) => (float) collect($items)->sum('cantidad_total'));
            $salidasAgg = $salidasByParte
                ->groupBy('especie')
                ->map(fn($items) => (float) collect($items)->sum('cantidad_total'));

            // ===== Agregados por CERTIFICACIÓN (inicializa todas en 0 para que siempre aparezcan) =====
            $entradasCertAgg = collect(array_fill_keys(array_keys($certCatalog), 0.0));
            $salidasCertAgg = collect(array_fill_keys(array_keys($certCatalog), 0.0));

            foreach ($entradasByParte as $row) {
                $key = $row->certKey ?? '__none__';
                $entradasCertAgg[$key] += $row->cantidad_total;
            }
            foreach ($salidasByParte as $row) {
                $key = $row->certKey ?? '__none__';
                $salidasCertAgg[$key] += $row->cantidad_total;
            }
        }

        // ===== Conjuntos de claves y orden =====
        $todasEspecies = $entradasAgg->keys()->merge($salidasAgg->keys())->unique()->sort()->values();

        $certKeysOrdenadas = collect($ordenCerts);
        // Totales KPIs (especie)
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
                        <td class="px-4 py-3 text-right text-gray-800">{{ $fmtNum($s) }}</td>
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
