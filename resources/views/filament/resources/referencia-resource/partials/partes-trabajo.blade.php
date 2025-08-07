<x-filament::section>
    <x-slot name="heading">Operación Máquina</x-slot>

    @if ($partesMaquina->isEmpty())
        <p class="text-sm text-gray-500">No hay partes de maquinaria asociados.</p>
    @else
        <!-- Tabla para pantallas medianas o mayores -->
        <div class="hidden md:block overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
            @php
                $totalCantidad = $partesMaquina->sum(fn($parte) => (float) $parte->cantidad_producida);
            @endphp

            <style>
                tr[onclick] {
                    transition: background 0.2s;
                }

                tr[onclick]:hover {
                    background: #f3f4f6;
                }
            </style>

            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Inicio / Fin</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Referencia</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Máquina / Trabajo / Horas</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Cantidad</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($partesMaquina as $parte)
                        <tr class="hover:bg-gray-50 cursor-pointer"
                            onclick="window.location='/partes-trabajo-suministro-operacion-maquina/{{ $parte->id }}/edit'">

                            <td class="px-4 py-3 text-gray-800">
                                <div>
                                    <span class="block font-semibold">
                                        {{ \Carbon\Carbon::parse($parte->fecha_hora_inicio_trabajo)->timezone('Europe/Madrid')->format('d/m/Y H:i') }}
                                    </span>
                                    <span class="block font-semibold">
                                        {{ $parte->fecha_hora_fin_trabajo
                                            ? \Carbon\Carbon::parse($parte->fecha_hora_fin_trabajo)->timezone('Europe/Madrid')->format('d/m/Y H:i')
                                            : '-' }}
                                    </span>
                                </div>
                            </td>

                            <td class="px-4 py-3 text-gray-800">
                                {{ $parte->referencia->referencia }}
                            </td>

                            <td class="px-4 py-3 text-gray-800">
                                <div>
                                    <span class="block font-semibold">
                                        {{ $parte->maquina?->marca . ' ' . $parte->maquina?->modelo ?? 'Sin asignar' }}
                                    </span>
                                    <span class="block text-gray-500">
                                        Tipo trabajo: {{ ucfirst($parte->tipo_trabajo) ?? 'N/D' }}
                                    </span>
                                    <span class="block text-gray-500">
                                        Horas:
                                        {{ $parte->horas_rotor ?? ($parte->horas_encendido ?? $parte->horas_trabajo) }}
                                        h
                                    </span>
                                </div>
                            </td>

                            <td class="px-4 py-3 text-gray-800">
                                {{ $parte->cantidad_producida }} {{ $parte->tipo_cantidad_producida }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-100 font-semibold">
                    <tr>
                        <td colspan="3" class="px-4 py-3 text-right text-gray-700">Total:</td>
                        <td class="px-4 py-3 text-gray-900">{{ $totalCantidad }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Vista en tarjetas para móviles -->
        <div class="md:hidden space-y-4 mt-4">
            @foreach ($partesMaquina as $parte)
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <p><span class="font-semibold">Referencia:</span> {{ $parte->referencia->referencia }}</p>
                    <p><span class="font-semibold">Inicio:</span>
                        {{ $parte->fecha_hora_inicio_trabajo?->format('d/m/Y H:i') }}</p>
                    <p><span class="font-semibold">Fin:</span>
                        {{ $parte->fecha_hora_fin_trabajo?->format('d/m/Y H:i') }}</p>
                    <p><span class="font-semibold">Máquina:</span>
                        {{ $parte->maquina?->marca . ' ' . $parte->maquina?->modelo ?? 'Sin asignar' }}</p>
                    <p><span class="font-semibold">Tipo de trabajo:</span> {{ ucfirst($parte->tipo_trabajo) ?? 'N/D' }}
                    </p>
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
            @php
                $totalCantidad = $partesTransporteAgrupados->sum(fn($parte) => (float) $parte->cantidad_total);
                $totalPeso = $partesTransporteAgrupados->sum(fn($parte) => (float) $parte->peso_neto);
            @endphp

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
                            onclick="window.location='/partes-trabajo-suministro-transporte/{{ $parte->id }}/edit'">

                            {{-- Referencia + Cliente --}}
                            <td class="px-4 py-3 font-semibold text-gray-900">
                                <div>{{ $parte->referencias->implode(', ') ?: 'N/D' }}</div>
                                <div class="text-sm text-gray-600">{{ $parte->cliente }}</div>
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
                            <td class="px-4 py-3 text-gray-800">{{ $parte->cantidad_total }}</td>

                            {{-- Peso neto --}}
                            <td class="px-4 py-3 text-gray-800">{{ $parte->peso_neto }}</td>
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
                                <td class="px-4 py-2 text-sm text-gray-600">{{ $carga->cantidad ?? 'N/D' }}</td>
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
                <tfoot class="bg-gray-100 font-semibold">
                    <tr>
                        <td colspan="2" class="px-4 py-3 text-right text-gray-700"></td>
                        <td class="px-4 py-3 text-gray-900">{{ $totalCantidad }} m³</td>
                        <td class="px-4 py-3 text-gray-900">{{ $totalPeso }} Tn</td>
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
