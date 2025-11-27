@if ($alertas->isNotEmpty())
    <div x-data="{
        open: true,
        async aceptarReferencias() {
            try {
                const response = await fetch('{{ route('referencias.alertas.aceptar') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({}),
                    credentials: 'same-origin',
                });
    
                if (!response.ok) {
                    throw new Error('Error HTTP ' + response.status);
                }
    
                this.open = false;
                window.location.reload();
            } catch (e) {
                alert('Ha ocurrido un error al aceptar las referencias.');
            }
        },
    }" x-show="open" x-cloak
        class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60">
        <div
            class="fi-modal-panel bg-white border border-solid border-black rounded-xl shadow-2xl max-w-3xl w-full mx-4 p-6">
            {{-- Cabecera --}}
            <div class="flex items-start justify-between gap-3 mb-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">
                        Nuevas referencias de suministro
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Se han creado las siguientes referencias.
                    </p>
                </div>

                <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-600">
                    {{ $alertas->count() }} nuevas
                </span>
            </div>

            {{-- Tabla --}}
            <div class="border rounded-lg overflow-hidden max-h-72 overflow-y-auto mb-4">
                <table class="w-full text-sm">
                    <thead class="bg-gray-100 sticky top-0">
                        <tr class="text-xs text-gray-600">
                            <th class="px-3 py-2 text-left font-medium">Fecha creación</th>
                            <th class="px-3 py-2 text-left font-medium">Referencia</th>
                            <th class="px-3 py-2 text-left font-medium">Ubicación</th>
                            <th class="px-3 py-2 text-left font-medium">Producto</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white">
                        @foreach ($alertas as $alerta)
                            @php
                                $ref = $alerta->referencia;
                                $url = $ref ? url('/referencias/' . $ref->id . '/edit') : null;
                            @endphp
                            <tr @if ($url) onclick="window.location.href='{{ $url }}'" @endif
                                class="border-t cursor-pointer hover:bg-gray-50 transition-colors">
                                <td class="px-3 py-2 text-xs text-gray-700 whitespace-nowrap">
                                    {{ optional(optional($ref)->created_at)->timezone('Europe/Madrid')->format('d/m/Y H:i') ?? '-' }}
                                </td>
                                <td class="px-3 py-2">
                                    @if ($ref)
                                        <div class="flex flex-col">
                                            <span class="text-sm font-semibold text-gray-900">
                                                {{ $ref->referencia }}
                                            </span>
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-xs text-gray-700">
                                    @if ($ref && $ref->ayuntamiento)
                                        {{ $ref->ayuntamiento }} ({{ $ref->provincia }})
                                    @else
                                        <span class="text-[11px] text-gray-400">Sin ubicación</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-xs text-gray-700">
                                    @if ($ref && ($ref->producto_especie || $ref->producto_tipo))
                                        {{ ucfirst($ref->producto_especie) }}
                                        @if ($ref->producto_tipo)
                                            ({{ ucfirst($ref->producto_tipo) }})
                                        @endif
                                    @else
                                        <span class="text-[11px] text-gray-400">Sin producto</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pie --}}
            <div class="flex justify-end gap-2">
                <button type="button" x-on:click="aceptarReferencias()"
                    class="fi-btn fi-btn-primary inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold">
                    Aceptar referencias
                </button>
            </div>
        </div>
    </div>
@endif
