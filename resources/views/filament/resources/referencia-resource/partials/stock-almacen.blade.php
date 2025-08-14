<x-filament::section>
    @php
        $entradasAgg = collect();
        $salidasAgg = collect();

        if ($recordId) {
            $with = [
                'parteTrabajoSuministroTransporte.cliente',
                'parteTrabajoSuministroTransporte.usuario',
                'referencia',
            ];

            // Función para normalizar la especie:
            // - string => trim
            // - array con 1 elemento => ese elemento
            // - array con >1 => "Mixto"
            // - null/empty => "Sin especificar"
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

            // ===== ENTRADAS: desde referencia -> a ESTE almacén (sin cliente) =====
            $entradas = \App\Models\CargaTransporte::with($with)
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

            // Agrupar sumatorios por especie (entradas)
            $entradasAgg = $entradas->groupBy('especie')->map(fn($items) => (float) $items->sum('cantidad_total'));

            // ===== SALIDAS: desde ESTE almacén -> a cliente =====
            $salidas = \App\Models\CargaTransporte::with($with)
                ->whereNull('deleted_at')
                ->where('almacen_id', $recordId) // origen: este almacén
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

            // Agrupar sumatorios por especie (salidas)
            $salidasAgg = $salidas->groupBy('especie')->map(fn($items) => (float) $items->sum('cantidad_total'));
        }

        // Unificar todas las especies presentes
        $todasEspecies = $entradasAgg->keys()->merge($salidasAgg->keys())->unique()->sort()->values();

        // Helper formato
        $fmtNum = fn($n) => number_format((float) $n, 2, ',', '.');
    @endphp

    <x-slot name="heading">Stock por Especie</x-slot>

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
                    @php
                        // Si no los tienes ya calculados:
                        $totalE = (float) ($entradasAgg->sum() ?? 0);
                        $totalS = (float) ($salidasAgg->sum() ?? 0);
                        $totalStock = $totalE - $totalS;
                        $fmtNum = fn($n) => number_format((float) $n, 2, ',', '.');
                    @endphp

                    {{-- KPIs --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-gray-500">Entradas</p>
                                    <p class="mt-1 text-2xl font-semibold">{{ $fmtNum($totalE) }} <span
                                            class="text-sm font-normal text-gray-500">m³</span></p>
                                </div>
                                <x-heroicon-m-arrow-down-tray class="h-7 w-7 text-green-600" />
                            </div>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-gray-500">Salidas</p>
                                    <p class="mt-1 text-2xl font-semibold">{{ $fmtNum($totalS) }} <span
                                            class="text-sm font-normal text-gray-500">m³</span></p>
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
                    $totalStock = (float) $entradasAgg->sum() - (float) $salidasAgg->sum();
                @endphp
                <p>Stock:
                    <span class="{{ $totalStock >= 0 ? 'text-green-700' : 'text-red-700' }}">
                        {{ $fmtNum($totalStock) }} m³
                    </span>
                </p>
            </div>
        </div>
    @endif
</x-filament::section>
