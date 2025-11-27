<x-filament::section>
    <x-slot name="heading">Operación máquina</x-slot>

    @if ($partesMaquina->isEmpty())
        <p class="text-sm text-gray-500">No hay partes de maquinaria asociados.</p>
    @else
        @php
            // Etiquetas legibles para tipos de trabajo
            $tipoTrabajoLabels = [
                'astillado' => 'Astillado',
                'triturado' => 'Triturado',
                'pretiturado' => 'Pretriturado',
                'saca' => 'Saca',
                'tala' => 'Tala',
                'cizallado' => 'Cizallado',
                'carga' => 'Carga',
                'transporte' => 'Transporte',
            ];

            // Colores suaves por tipo (simplificados a clases muy estándar)
            $tipoTrabajoColors = [
                'astillado' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                'triturado' => 'bg-sky-50 text-sky-800 border-sky-200',
                'pretiturado' => 'bg-indigo-50 text-indigo-800 border-indigo-200',
                'saca' => 'bg-amber-50 text-amber-800 border-amber-200',
                'tala' => 'bg-rose-50 text-rose-800 border-rose-200',
                'cizallado' => 'bg-fuchsia-50 text-fuchsia-800 border-fuchsia-200',
                'carga' => 'bg-gray-50 text-gray-800 border-gray-200',
                'transporte' => 'bg-cyan-50 text-cyan-800 border-cyan-200',
                'sin_tipo' => 'bg-gray-50 text-gray-800 border-gray-200',
            ];

            // Helper para formatear cantidades
            $formatCantidad = function ($valor) {
                if ($valor === null) {
                    return '0';
                }
                $str = number_format((float) $valor, 2, ',', '.');
                return rtrim(rtrim($str, '0'), ',');
            };

            // Helper para mostrar unidad de forma bonita
            $formatUnidad = function (?string $unidadRaw): string {
                $unidadRaw = $unidadRaw ?? '';

                return match (strtolower($unidadRaw)) {
                    'metros_cubicos', 'm3' => 'm³',
                    'toneladas', 'tn', 't' => 'Tn',
                    default => $unidadRaw,
                };
            };

            // Orden por inicio y agrupado por tipo de trabajo
            $partesOrdenados = $partesMaquina->sortBy('fecha_hora_inicio_trabajo');

            $partesPorTipo = $partesOrdenados->groupBy(fn($parte) => $parte->tipo_trabajo ?: 'sin_tipo');

            // Totales globales por tipo de cantidad
            $totalesGlobales = $partesOrdenados
                ->groupBy(fn($parte) => $parte->tipo_cantidad_producida ?: 'sin_tipo')
                ->map(fn($grupo) => $grupo->sum(fn($p) => (float) $p->cantidad_producida));
        @endphp

        {{-- ========= VISTA ESCRITORIO ========= --}}
        <div class="hidden md:block overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-4 py-2.5 bg-gray-50 flex items-center justify-between">
                <span class="text-xs font-medium text-gray-600">
                    {{ $partesMaquina->count() }} parte(s) de máquina
                </span>

                {{-- Totales globales por tipo de cantidad --}}
                <div class="flex flex-wrap items-center gap-2 text-[11px] text-gray-600">
                    @foreach ($totalesGlobales as $unidad => $total)
                        @php
                            $unidadLabel = $unidad === 'sin_tipo' ? '' : $formatUnidad($unidad);
                        @endphp
                        <span>
                            Total {{ $unidadLabel ? strtolower($unidadLabel) : 'sin tipo' }}:
                            <span class="font-semibold text-gray-900">
                                {{ $formatCantidad($total) }} {{ $unidadLabel }}
                            </span>
                        </span>
                    @endforeach
                </div>
            </div>

            <div class="max-h-[480px] overflow-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 z-10 bg-white border-b border-gray-200">
                        <tr>
                            <th
                                class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">
                                Inicio / fin
                            </th>
                            <th
                                class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">
                                Referencia
                            </th>
                            <th
                                class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">
                                Máquina · tipo trabajo · horas
                            </th>
                            <th
                                class="px-4 py-2.5 text-right text-xs font-semibold text-gray-600 uppercase tracking-wide">
                                Cantidad
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100">
                        @foreach ($partesPorTipo as $tipoTrabajo => $grupo)
                            @php
                                $labelTipo =
                                    $tipoTrabajoLabels[$tipoTrabajo] ??
                                    ($tipoTrabajo === 'sin_tipo' ? 'Sin tipo de trabajo' : ucfirst($tipoTrabajo));
                                $colorClass = $tipoTrabajoColors[$tipoTrabajo] ?? $tipoTrabajoColors['sin_tipo'];

                                // Totales por tipo de cantidad dentro de este tipo de trabajo
                                $totalesGrupo = $grupo
                                    ->groupBy(fn($parte) => $parte->tipo_cantidad_producida ?: 'sin_tipo')
                                    ->map(fn($g) => $g->sum(fn($p) => (float) $p->cantidad_producida));
                            @endphp

                            {{-- Cabecera de grupo (tipo de trabajo) --}}
                            <tr class="bg-gray-50">
                                <td colspan="4" class="px-4 py-2">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="inline-flex items-center gap-2">
                                            <span
                                                class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold {{ $colorClass }}">
                                                {{ $labelTipo }}
                                            </span>
                                            <span class="text-[11px] text-gray-500">
                                                {{ $grupo->count() }} parte(s)
                                            </span>
                                        </div>

                                        {{-- Totales por unidad en este tipo --}}
                                        @if ($totalesGrupo->isNotEmpty())
                                            <div class="flex flex-wrap items-center gap-2 text-[11px] text-gray-600">
                                                @foreach ($totalesGrupo as $unidad => $total)
                                                    @php
                                                        $unidadLabel =
                                                            $unidad === 'sin_tipo' ? '' : $formatUnidad($unidad);
                                                    @endphp
                                                    <span>
                                                        Total {{ strtolower($labelTipo) }}
                                                        @if ($unidadLabel)
                                                            · {{ strtolower($unidadLabel) }}
                                                        @endif
                                                        :
                                                        <span class="font-semibold text-gray-900">
                                                            {{ $formatCantidad($total) }} {{ $unidadLabel }}
                                                        </span>
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>

                            {{-- Filas del grupo --}}
                            @foreach ($grupo as $parte)
                                <tr class="group transition-colors hover:bg-gray-50 cursor-pointer"
                                    onclick="window.location='/partes-trabajo-suministro-operacion-maquina/{{ $parte->id }}'">
                                    {{-- Inicio / fin --}}
                                    <td class="px-4 py-2.5 align-top text-gray-800">
                                        <div class="space-y-0.5">
                                            <span class="block text-xs font-semibold text-gray-800">
                                                {{ \Carbon\Carbon::parse($parte->fecha_hora_inicio_trabajo)->timezone('Europe/Madrid')->format('d/m/Y H:i') }}
                                            </span>
                                            <span class="block text-xs text-gray-500">
                                                {{ $parte->fecha_hora_fin_trabajo
                                                    ? \Carbon\Carbon::parse($parte->fecha_hora_fin_trabajo)->timezone('Europe/Madrid')->format('d/m/Y H:i')
                                                    : '—' }}
                                            </span>
                                        </div>
                                    </td>

                                    {{-- Referencia --}}
                                    <td class="px-4 py-2.5 align-top text-gray-800">
                                        <span
                                            class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-[11px] font-medium text-gray-800">
                                            {{ $parte->referencia->referencia }}
                                        </span>
                                    </td>

                                    {{-- Máquina / trabajo / horas --}}
                                    <td class="px-4 py-2.5 align-top text-gray-800">
                                        <div class="space-y-0.5">
                                            <div class="text-xs font-semibold text-gray-900">
                                                {{ $parte->maquina?->marca . ' ' . $parte->maquina?->modelo ?? 'Sin asignar' }}
                                            </div>
                                            <div class="text-[11px] text-gray-500">
                                                Tipo trabajo:
                                                <span class="font-medium text-gray-700">
                                                    {{ $labelTipo }}
                                                </span>
                                            </div>
                                            <div class="text-[11px] text-gray-500">
                                                Horas:
                                                <span class="font-medium text-gray-700">
                                                    {{ $parte->horas_rotor ?? ($parte->horas_encendido ?? $parte->horas_trabajo) }}
                                                    h
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Cantidad --}}
                                    <td class="px-4 py-2.5 align-top text-right text-gray-900">
                                        @php
                                            $unidadBonita = $formatUnidad($parte->tipo_cantidad_producida);
                                        @endphp
                                        <div class="text-xs font-semibold">
                                            {{ $formatCantidad($parte->cantidad_producida) }}
                                            {{ $unidadBonita }}
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ========= VISTA MÓVIL ========= --}}
        <div class="md:hidden space-y-5 mt-4">
            @foreach ($partesPorTipo as $tipoTrabajo => $grupo)
                @php
                    $labelTipo =
                        $tipoTrabajoLabels[$tipoTrabajo] ??
                        ($tipoTrabajo === 'sin_tipo' ? 'Sin tipo de trabajo' : ucfirst($tipoTrabajo));
                    $colorClass = $tipoTrabajoColors[$tipoTrabajo] ?? $tipoTrabajoColors['sin_tipo'];

                    $totalesGrupo = $grupo
                        ->groupBy(fn($parte) => $parte->tipo_cantidad_producida ?: 'sin_tipo')
                        ->map(fn($g) => $g->sum(fn($p) => (float) $p->cantidad_producida));
                @endphp

                <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-4 py-2.5">
                        <div class="inline-flex items-center gap-2">
                            <span
                                class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold {{ $colorClass }}">
                                {{ $labelTipo }}
                            </span>
                            <span class="text-[11px] text-gray-500">
                                {{ $grupo->count() }} parte(s)
                            </span>
                        </div>

                        @if ($totalesGrupo->isNotEmpty())
                            <div class="flex flex-col items-end gap-0.5 text-[11px] text-gray-600">
                                @foreach ($totalesGrupo as $unidad => $total)
                                    @php
                                        $unidadLabel = $unidad === 'sin_tipo' ? '' : $formatUnidad($unidad);
                                    @endphp
                                    <span>
                                        Total:
                                        <span class="font-semibold text-gray-900">
                                            {{ $formatCantidad($total) }} {{ $unidadLabel }}
                                        </span>
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="px-4 py-3 space-y-3">
                        @foreach ($grupo as $parte)
                            @php
                                $unidadBonita = $formatUnidad($parte->tipo_cantidad_producida);
                            @endphp
                            <button type="button"
                                onclick="window.location='/partes-trabajo-suministro-operacion-maquina/{{ $parte->id }}'"
                                class="w-full text-left rounded-lg border border-gray-100 bg-gray-50 px-3 py-2.5 shadow-sm active:scale-[0.99] transition">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="text-[11px] text-gray-500">
                                            {{ \Carbon\Carbon::parse($parte->fecha_hora_inicio_trabajo)->timezone('Europe/Madrid')->format('d/m/Y H:i') }}
                                            @if ($parte->fecha_hora_fin_trabajo)
                                                <span class="text-gray-400"> · </span>
                                                {{ \Carbon\Carbon::parse($parte->fecha_hora_fin_trabajo)->timezone('Europe/Madrid')->format('d/m/Y H:i') }}
                                            @endif
                                        </p>
                                        <p class="mt-0.5 text-xs font-semibold text-gray-900">
                                            {{ $parte->referencia->referencia }}
                                        </p>
                                        <p class="mt-0.5 text-[11px] text-gray-500">
                                            Máquina:
                                            <span class="font-medium text-gray-700">
                                                {{ $parte->maquina?->marca . ' ' . $parte->maquina?->modelo ?? 'Sin asignar' }}
                                            </span>
                                        </p>
                                        <p class="text-[11px] text-gray-500">
                                            Horas:
                                            <span class="font-medium text-gray-700">
                                                {{ $parte->horas_rotor ?? ($parte->horas_encendido ?? $parte->horas_trabajo) }}
                                                h
                                            </span>
                                        </p>
                                    </div>

                                    <div class="text-right">
                                        <p class="text-[11px] font-semibold text-gray-900">
                                            {{ $formatCantidad($parte->cantidad_producida) }}
                                        </p>
                                        <p class="text-[10px] text-gray-500">
                                            {{ $unidadBonita }}
                                        </p>
                                    </div>
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-filament::section>

<br />

<x-filament::section>
    <x-slot name="heading">Suministros Transporte</x-slot>

    @if ($partesTransporteAgrupados->isEmpty())
        <p class="text-sm text-gray-500">No hay partes de transporte asociados.</p>
    @else
        {{-- VISTA ESCRITORIO --}}
        <div class="hidden md:block overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Referencia / Cliente</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Periodo</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Cantidad total (m³)</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Peso neto (Tn)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($partesTransporteAgrupados as $parte)
                        {{-- FILA PRINCIPAL CLICABLE --}}
                        <tr class="bg-gray-100 hover:bg-gray-200 cursor-pointer transition"
                            onclick="window.location='/partes-trabajo-suministro-transporte/{{ $parte->id }}'">

                            {{-- Referencia + Cliente --}}
                            <td class="px-4 py-3 font-semibold text-gray-900">
                                <div>{{ $parte->referencias->implode(', ') ?: 'N/D' }}</div>
                                <div class="text-sm text-gray-600">
                                    {{ $parte->cliente ?? ($parte->almacen ?? 'Sin destino') }}
                                </div>
                            </td>

                            {{-- Periodo --}}
                            <td class="px-4 py-3 text-gray-800">
                                <div>
                                    <span class="block font-semibold">
                                        {{ $parte->inicio ? \Carbon\Carbon::parse($parte->inicio)->timezone('Europe/Madrid')->format('d/m/Y H:i') : '-' }}
                                    </span>
                                    <span class="block text-gray-500">
                                        {{ $parte->fin ? \Carbon\Carbon::parse($parte->fin)->timezone('Europe/Madrid')->format('d/m/Y H:i') : '-' }}
                                    </span>
                                </div>
                            </td>

                            {{-- Cantidad total --}}
                            <td class="px-4 py-3 text-gray-800">{{ $parte->cantidad_total }} m³</td>

                            <td class="px-4 py-2 text-sm text-gray-600">
                                {{ $parte->peso_neto_ref !== null ? number_format($parte->peso_neto_ref, 2) . ' Tn' : '—' }}
                            </td>
                        </tr>

                        {{-- Subregistros de cargas (no clicables) --}}
                        @foreach ($parte->cargas as $carga)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 text-sm text-gray-600" colspan="1"></td>
                                <td class="px-4 py-2 text-sm text-gray-600">
                                    <div>
                                        <span class="block">
                                            {{ $carga->fecha_hora_inicio_carga
                                                ? \Carbon\Carbon::parse($carga->fecha_hora_inicio_carga)->timezone('Europe/Madrid')->format('d/m/Y H:i')
                                                : '-' }}
                                        </span>
                                        <span class="block text-gray-500">
                                            {{ $carga->fecha_hora_fin_carga
                                                ? \Carbon\Carbon::parse($carga->fecha_hora_fin_carga)->timezone('Europe/Madrid')->format('d/m/Y H:i')
                                                : '-' }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-600">{{ $carga->cantidad ?? 'N/D' }} m³</td>
                                <td></td>
                            </tr>
                        @endforeach

                        {{-- Separador --}}
                        <tr>
                            <td colspan="4" class="py-1">
                                <hr class="border-t border-gray-300">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                @php
                    $totalCantidad = $partesTransporteAgrupados->sum(fn($parte) => (float) $parte->cantidad_total);
                    $totalPeso = $partesTransporteAgrupados->sum(fn($parte) => (float) ($parte->peso_neto_ref ?? 0));
                @endphp
                <tfoot class="bg-gray-100 font-semibold">
                    <tr>
                        <td colspan="2" class="px-4 py-3 text-right text-gray-700"></td>
                        <td class="px-4 py-3 text-gray-900">{{ $totalCantidad }} m³</td>
                        <td class="px-4 py-3 text-gray-900">{{ number_format($totalPeso, 2) }} Tn</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- VISTA MÓVIL --}}
        <div class="md:hidden space-y-6 mt-4">
            @foreach ($partesTransporteAgrupados as $parte)
                <div class="rounded-xl border border-gray-300 bg-white shadow-sm p-4">
                    <p><span class="font-semibold">Referencia(s):</span>
                        {{ $parte->referencias->implode(', ') ?: 'N/D' }}</p>
                    <p><span class="font-semibold">Cliente:</span> {{ $parte->cliente }}</p>
                    <p><span class="font-semibold">Inicio:</span>
                        {{ $parte->inicio ? \Carbon\Carbon::parse($parte->inicio)->timezone('Europe/Madrid')->format('d/m/Y H:i') : '-' }}
                    </p>
                    <p><span class="font-semibold">Fin:</span>
                        {{ $parte->fin ? \Carbon\Carbon::parse($parte->fin)->timezone('Europe/Madrid')->format('d/m/Y H:i') : '-' }}
                    </p>
                    <p><span class="font-semibold">Cantidad total:</span> {{ $parte->cantidad_total }} m³</p>

                    <div class="mt-3 border-t pt-2 space-y-2">
                        @foreach ($parte->cargas as $carga)
                            <div class="text-sm bg-gray-50 rounded-md p-2">
                                <p><span class="font-medium">Inicio:</span>
                                    {{ $carga->fecha_hora_inicio_carga
                                        ? \Carbon\Carbon::parse($carga->fecha_hora_inicio_carga)->timezone('Europe/Madrid')->format('d/m/Y H:i')
                                        : '-' }}
                                </p>
                                <p><span class="font-medium">Fin:</span>
                                    {{ $carga->fecha_hora_fin_carga
                                        ? \Carbon\Carbon::parse($carga->fecha_hora_fin_carga)->timezone('Europe/Madrid')->format('d/m/Y H:i')
                                        : '-' }}
                                </p>
                                <p><span class="font-medium">Cantidad:</span> {{ $carga->cantidad ?? 'N/D' }} m³</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-filament::section>
