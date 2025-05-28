<x-filament-widgets::widget>
    <x-filament::card class="space-y-4">
        <h2 class="text-xl font-bold">📋 Partes de trabajo activos</h2>

        @php
            $slugs = [
                \App\Models\ParteTrabajoAyudante::class => 'partes-trabajo-ayudantes',
                \App\Models\ParteTrabajoSuministroAveria::class => 'partes-trabajo-suministro-averia',
                \App\Models\ParteTrabajoSuministroDesplazamiento::class => 'partes-trabajo-suministro-desplazamiento',
                \App\Models\ParteTrabajoSuministroOperacionMaquina::class =>
                    'partes-trabajo-suministro-operacion-maquina',
                \App\Models\ParteTrabajoSuministroOtros::class => 'partes-trabajo-suministro-otros',
                \App\Models\ParteTrabajoSuministroTransporte::class => 'partes-trabajo-suministro-transporte',
                \App\Models\ParteTrabajoTallerMaquinaria::class => 'partes-trabajo-taller-maquinaria',
                \App\Models\ParteTrabajoTallerVehiculos::class => 'partes-trabajo-taller-vehiculos',
            ];

            $labels = [
                \App\Models\ParteTrabajoAyudante::class => 'Ayudante',
                \App\Models\ParteTrabajoSuministroAveria::class => 'Suministro - Avería',
                \App\Models\ParteTrabajoSuministroDesplazamiento::class => 'Suministro - Desplazamiento',
                \App\Models\ParteTrabajoSuministroOperacionMaquina::class => 'Suministro - Operación Máquina',
                \App\Models\ParteTrabajoSuministroOtros::class => 'Suministro - Otros',
                \App\Models\ParteTrabajoSuministroTransporte::class => 'Suministro - Transporte',
                \App\Models\ParteTrabajoTallerMaquinaria::class => 'Taller - Maquinaria',
                \App\Models\ParteTrabajoTallerVehiculos::class => 'Taller - Vehículos',
            ];

            $slug = $parte['slug'] ?? null;
            $nombreModelo = $parte['label'] ?? null;
        @endphp

        @if ($activos > 0)
            <p class="mt-2 text-green-600">
                Tienes <strong>{{ $activos }}</strong> parte(s) de trabajo sin finalizar.
            </p>
        @else
            <p class="mt-2 text-gray-500">No hay partes de trabajo activos para ti.</p>
        @endif

        @if (!empty($partesActivos))
            <div class="mt-6 overflow-x-auto w-full">
                <table class="w-full min-w-[600px] text-sm text-left border border-gray-200 rounded">
                    <thead class="bg-gray-100 text-gray-700">
                        <tr>
                            <th class="px-4 py-2 whitespace-nowrap">Tipo</th>
                            <th class="px-4 py-2 whitespace-nowrap">Inicio</th>
                            <th class="px-4 py-2 whitespace-nowrap"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach (array_slice($partesActivos, 0, 5) as $parte)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 py-2 align-middle whitespace-nowrap">{{ $parte['label'] }}</td>
                                <td class="px-4 py-2 align-middle whitespace-nowrap">
                                    {{ $parte['inicio'] ? \Carbon\Carbon::parse($parte['inicio'])->format('d/m/Y H:i') : '—' }}
                                </td>
                                <td class="px-4 py-2 align-middle whitespace-nowrap">
                                    @if ($parte['slug'])
                                        <a href="{{ url("/{$parte['slug']}/{$parte['id']}") }}"
                                            class="text-primary-600 hover:underline inline-flex items-center gap-1"
                                            target="_blank" rel="noopener">
                                            <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4" />
                                            Ver
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if (count($partesActivos) > 5)
                    <p class="text-sm text-gray-500 mt-2">
                        Mostrando los últimos 5 partes activos.
                    </p>
                @endif
            </div>
        @endif
    </x-filament::card>
</x-filament-widgets::widget>
