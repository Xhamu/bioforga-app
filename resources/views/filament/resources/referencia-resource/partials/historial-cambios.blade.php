<div>
    @forelse ($logs as $log)
        <div class="border rounded p-4 mb-3 bg-gray-50">
            <p class="text-sm mb-1">
                <strong>Acción:</strong> {{ ucfirst($log->description) }}
            </p>

            <p class="text-sm mb-1">
                <strong>Usuario:</strong> {{ $log->causer?->name . ' ' . $log->causer?->apellidos ?? 'Sistema' }}
            </p>

            <p class="text-sm mb-1">
                <strong>Fecha:</strong> {{ $log->created_at->timezone('Europe/Madrid')->format('d/m/Y H:i') }}
            </p>

            @if ($log->properties?->has('attributes'))
                <details class="text-sm mt-2">
                    <summary class="cursor-pointer text-blue-600 hover:underline">Ver cambios</summary>
                    <ul class="list-disc ml-5 mt-1">
                        @foreach ($log->properties['attributes'] as $field => $value)
                            @continue($field === 'updated_at')

                            @php
                                $old = $log->properties['old'][$field] ?? null;
                            @endphp
                            <li>
                                <strong>{{ $field }}:</strong>
                                {{ $old !== null ? "$old → $value" : $value }}
                            </li>
                        @endforeach

                    </ul>
                </details>
            @endif
        </div>
    @empty
        <p class="text-sm text-gray-600">No hay historial disponible.</p>
    @endforelse
</div>
