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
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Referencia</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Cliente</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Inicio</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Fin</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Cantidad total (m³)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($partesTransporteAgrupados as $parte)
                        <tr class="bg-gray-100">
                            <td class="px-4 py-3 font-semibold text-gray-900">
                                {{ $parte->referencias->implode(', ') ?: 'N/D' }}
                            </td>
                            <td class="px-4 py-3 text-gray-800">{{ $parte->cliente }}</td>
                            <td class="px-4 py-3 text-gray-800">
                                {{ \Carbon\Carbon::parse($parte->inicio)->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-4 py-3 text-gray-800">
                                {{ \Carbon\Carbon::parse($parte->fin)->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-4 py-3 text-gray-800">{{ $parte->cantidad_total }}</td>
                        </tr>

                        @foreach ($parte->cargas as $carga)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 text-sm text-gray-600" colspan="2">
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-600">
                                    {{ $carga->fecha_hora_inicio_carga?->format('d/m/Y H:i') }}
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-600">
                                    {{ $carga->fecha_hora_fin_carga?->format('d/m/Y H:i') ?? 'N/D' }}
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-600">{{ $carga->cantidad ?? 'N/D' }}</td>
                            </tr>
                        @endforeach

                        <tr>
                            <td colspan="5" class="py-1">
                                <hr class="border-t border-gray-300">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
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
                        {{ \Carbon\Carbon::parse($parte->inicio)->format('d/m/Y H:i') }}</p>
                    <p><span class="font-semibold">Fin:</span>
                        {{ \Carbon\Carbon::parse($parte->fin)->format('d/m/Y H:i') }}</p>
                    <p><span class="font-semibold">Cantidad total:</span> {{ $parte->cantidad_total }} m³</p>

                    <div class="mt-3 border-t pt-2 space-y-2">
                        @foreach ($parte->cargas as $carga)
                            <div class="text-sm bg-gray-50 rounded-md p-2">
                                <p><span class="font-medium">Inicio:</span>
                                    {{ $carga->fecha_hora_inicio_carga?->format('d/m/Y H:i') }}</p>
                                <p><span class="font-medium">Fin:</span>
                                    {{ $carga->fecha_hora_fin_carga?->format('d/m/Y H:i') ?? 'N/D' }}</p>
                                <p><span class="font-medium">Cantidad:</span> {{ $carga->cantidad ?? 'N/D' }} m³</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-filament::section>

<br>

<x-filament::section>
    <x-slot name="heading">Operación Máquina</x-slot>

    @if ($partesMaquina->isEmpty())
        <p class="text-sm text-gray-500">No hay partes de maquinaria asociados.</p>
    @else
        <!-- Tabla para pantallas medianas o mayores -->
        <div class="hidden md:block overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Referencia</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Inicio</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Fin</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Máquina</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-700">Tipo de trabajo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($partesMaquina as $parte)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-800">{{ $parte->referencia->referencia }}</td>
                            <td class="px-4 py-3 text-gray-800">
                                {{ $parte->fecha_hora_inicio_trabajo?->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 text-gray-800">
                                {{ $parte->fecha_hora_fin_trabajo?->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 text-gray-800">
                                {{ $parte->maquina?->marca . ' ' . $parte->maquina?->modelo ?? 'Sin asignar' }}</td>
                            <td class="px-4 py-3 text-gray-800">{{ ucfirst($parte->tipo_trabajo) ?? 'N/D' }}</td>
                        </tr>
                    @endforeach
                </tbody>
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
