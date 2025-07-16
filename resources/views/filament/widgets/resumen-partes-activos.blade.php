<x-filament-widgets::widget>
    <x-filament::card class="space-y-4">
        <h2 class="text-xl font-bold text-red-700">Resumen de partes de trabajo activos</h2>

        @if ($total > 0)
            <p class="mt-2 text-gray-800">
                Actualmente hay <strong>{{ $total }}</strong> parte{{ $total === 1 ? '' : 's' }} de trabajo
                activo{{ $total === 1 ? '' : 's' }}.
            </p>

            {{-- Vista desktop/tablet --}}
            <div class="mt-6 w-full overflow-auto rounded-xl border hidden sm:block">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-100 text-gray-700">
                        <tr>
                            <th class="px-4 py-2 whitespace-nowrap">Tipo</th>
                            <th class="px-4 py-2 whitespace-nowrap">Inicio</th>
                            <th class="px-4 py-2 whitespace-nowrap hidden lg:table-cell">Usuario</th>
                            <th class="px-4 py-2 whitespace-nowrap"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($partesActivos as $parte)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 py-2 align-middle whitespace-nowrap truncate" style="max-width: 120px;"
                                    title="{{ $parte['label'] }}">
                                    {{ $parte['label'] }}
                                </td>

                                <td class="px-4 py-2 align-middle whitespace-nowrap">
                                    {{ $parte['inicio'] ? \Carbon\Carbon::parse($parte['inicio'])->timezone('Europe/Madrid')->format('d/m/Y H:i') : '—' }}
                                </td>

                                <td class="px-4 py-2 align-middle whitespace-nowrap hidden lg:table-cell">
                                    {{ $parte['usuario_nombre'] }}
                                </td>

                                <td class="px-4 py-2 align-middle whitespace-nowrap text-right">
                                    @if ($parte['slug'])
                                        <a href="{{ url("/{$parte['slug']}/{$parte['id']}") }}"
                                            class="text-primary-600 hover:underline inline-flex items-center gap-1">
                                            <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4" />
                                            Ver
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Vista móvil (<768px) --}}
            <div class="mt-6 w-full rounded-xl border sm:hidden divide-y">
                @foreach ($partesActivos as $parte)
                    <div class="p-4">
                        <div class="text-sm">
                            <span class="font-medium text-gray-700">Tipo:</span><br>
                            <span title="{{ $parte['label'] }}">{{ $parte['label'] }}</span>
                        </div>

                        <div class="text-sm mt-2">
                            <span class="font-medium text-gray-700">Inicio:</span><br>
                            {{ $parte['inicio'] ? \Carbon\Carbon::parse($parte['inicio'])->timezone('Europe/Madrid')->format('d/m/Y H:i') : '—' }}
                        </div>

                        <div class="text-sm mt-2">
                            <span class="font-medium text-gray-700">Usuario:</span><br>
                            {{ $parte['usuario_nombre'] }}
                        </div>

                        <div class="mt-3 text-right">
                            @if ($parte['slug'])
                                <a href="{{ url("/{$parte['slug']}/{$parte['id']}") }}"
                                    class="text-primary-600 hover:underline inline-flex items-center gap-1 text-sm">
                                    <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4" />
                                    Ver
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="mt-2 text-green-600">No hay partes activos en este momento.</p>
        @endif
    </x-filament::card>
</x-filament-widgets::widget>
