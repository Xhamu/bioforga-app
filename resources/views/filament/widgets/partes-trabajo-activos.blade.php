<x-filament-widgets::widget>
    <x-filament::card class="space-y-4">
        @php
            $slug = $parte['slug'] ?? null;
            $nombreModelo = $parte['label'] ?? null;
        @endphp

        @if ($activos > 0)
            <p class="text-l">
                Tienes <strong>{{ $activos }}</strong> parte{{ $activos === 1 ? '' : 's' }} de trabajo sin
                finalizar.
            </p>
        @else
            <p class="mt-2 text-gray-500">No tienes partes de trabajo activos.</p>
        @endif

        @if (!empty($partesActivos))
            @php $partesActivos = array_reverse($partesActivos); @endphp
            <div class="mt-6 w-full rounded-xl border">
                <table class="w-full text-sm text-left table-fixed">
                    <thead class="bg-gray-100 text-gray-700">
                        <tr>
                            <th class="px-4 py-2 whitespace-nowrap w-1/2">Tipo</th>
                            <th class="px-4 py-2 whitespace-nowrap w-1/4 hidden sm:table-cell">Inicio</th>
                            <th class="px-4 py-2 whitespace-nowrap w-1/4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($partesActivos as $parte)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 py-2 align-middle truncate whitespace-nowrap" style="max-width: 140px;"
                                    title="{{ $parte['label'] }}">
                                    {{ $parte['label'] }}
                                </td>

                                <td class="px-4 py-2 align-middle whitespace-nowrap hidden sm:table-cell">
                                    {{ $parte['inicio'] ? \Carbon\Carbon::parse($parte['inicio'])->format('d/m/Y H:i') : '—' }}
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
        @endif
    </x-filament::card>
</x-filament-widgets::widget>
