<div class="space-y-4">
    @forelse ($logs as $log)
        @php
            $action = strtolower($log->description);

            // Color según tipo de acción
            $colorClass = match ($action) {
                'created' => 'border-green-300 bg-green-50',
                'updated' => 'border-blue-300 bg-blue-50',
                'deleted' => 'border-red-300 bg-red-50',
                default => 'border-gray-300 bg-gray-50',
            };

            // Descripción en español
            $accionTexto = match ($action) {
                'created' => 'Creado',
                'updated' => 'Actualizado',
                'deleted' => 'Eliminado',
                default => ucfirst($log->description),
            };

            $usuarioNombre = $log->causer
                ? trim(($log->causer->name ?? '') . ' ' . ($log->causer->apellidos ?? ''))
                : 'Sistema';
        @endphp

        <div class="rounded-xl border p-4 shadow-sm {{ $colorClass }}">
            {{-- Cabecera: acción + fecha --}}
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="font-semibold text-gray-900 text-sm">
                    {{ $accionTexto }}
                </h3>

                <span class="text-xs text-gray-600">
                    {{ $log->created_at->timezone('Europe/Madrid')->format('d/m/Y H:i') }}
                </span>
            </div>

            {{-- Usuario --}}
            <p class="text-sm text-gray-700 mt-1">
                <span class="font-semibold">Usuario:</span>
                {{ $usuarioNombre }}
            </p>

            {{-- Cambios detallados --}}
            @if ($log->properties?->has('attributes'))
                <details class="mt-3 group">
                    <summary class="cursor-pointer text-sm text-blue-600 hover:underline">
                        Ver detalles de los cambios
                    </summary>

                    <ul class="mt-2 ml-5 space-y-1 text-sm text-gray-700">
                        @foreach ($log->properties['attributes'] as $field => $value)
                            @continue($field === 'updated_at')

                            @php
                                $old = $log->properties['old'][$field] ?? null;
                            @endphp

                            <li class="flex flex-col sm:flex-row sm:items-center sm:gap-2">
                                <span class="font-semibold">
                                    {{ str_replace('_', ' ', $field) }}:
                                </span>

                                <span>
                                    @if ($old !== null)
                                        <span class="text-red-700 line-through">
                                            {{ $old }}
                                        </span>
                                        <span class="mx-1">→</span>
                                    @endif

                                    <span class="text-green-700 font-medium">
                                        {{ $value }}
                                    </span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </div>
    @empty
        <p class="text-sm text-gray-600 text-center py-4">
            No hay historial de cambios disponible.
        </p>
    @endforelse
</div>
