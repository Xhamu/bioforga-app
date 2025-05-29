<x-filament-widgets::widget>
    <x-filament::card class="space-y-4">
        <h2 class="text-xl font-bold text-red-700">Partes de trabajo activos en el sistema</h2>

        @if ($total > 0)
            <p class="mt-2 text-gray-800">Actualmente hay <strong>{{ $total }}</strong> parte(s) de trabajo
                activos.
            </p>

            <div class="mt-6 w-full overflow-auto rounded-xl border">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-100 text-gray-700">
                        <tr>
                            <th class="px-4 py-2 whitespace-nowrap">Tipo</th>
                            <th class="px-4 py-2 whitespace-nowrap hidden md:table-cell">Inicio</th>
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

                                <td class="px-4 py-2 align-middle whitespace-nowrap hidden md:table-cell">
                                    {{ $parte['inicio'] ? \Carbon\Carbon::parse($parte['inicio'])->format('d/m/Y H:i') : '—' }}
                                </td>

                                <td class="px-4 py-2 align-middle whitespace-nowrap hidden lg:table-cell">
                                    {{ $parte['usuario_nombre'] }}
                                </td>

                                <td class="px-4 py-2 align-middle whitespace-nowrap sm:text-left text-right">
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
        @else
            <p class="mt-2 text-green-600">No hay partes activos en este momento.</p>
        @endif
    </x-filament::card>
</x-filament-widgets::widget>
