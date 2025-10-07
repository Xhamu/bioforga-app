<x-filament::widget x-data
    x-on:open-modal.window="
        if ($event.detail?.id === 'statesModal') {
            // refresca datos cada vez que se abre
            $wire.refreshData()
        }
    ">
    @php
        $user = auth()->user();
        $nombre = $user->name;
        $apellidos = $user->apellidos;

        $inicialNombre = substr($nombre, 0, 1);
        $inicialApellido = substr($apellidos, 0, 1);
        $iniciales = strtoupper($inicialNombre . $inicialApellido);

        $isActive = (bool) ($active ?? null);
        $stateName = $active?->state?->name ?? 'Sin estado';
        if ($isActive) {
            $startedAt = $active->started_at->timezone('Europe/Madrid')->format('d/m/Y H:i:s');
        }
    @endphp

    <x-filament::card class="px-5 py-4 rounded-lg">
        <div class="flex items-center justify-between gap-6">

            {{-- Avatar + nombre + apellidos --}}
            <div class="flex items-center gap-5">
                <div
                    class="flex items-center justify-center w-12 h-12 rounded-full bg-black text-white text-base font-semibold uppercase">
                    {{ $iniciales }}
                </div>

                <div class="leading-tight">
                    <div class="text-[15px] font-semibold text-gray-900 dark:text-white">
                        Bienvenido/a
                    </div>
                    <div class="text-lg text-gray-500 dark:text-gray-400">
                        {{ $nombre }} {{ strtoupper(substr($apellidos, 0, 1)) }}.
                    </div>

                    {{-- Estado actual (si aplica) --}}
                    @if ($this->canSeeStates())
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            <span class="font-medium">Estado actual:</span>
                            {{ $isActive ? ucfirst($stateName) : 'Sin estado' }}
                            @if ($isActive)
                                <span>— desde {{ $startedAt }}</span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-2 ml-auto">
                {{-- Botón ESTADOS (abre modal por evento nativo de Filament) --}}
                @if ($this->canSeeStates())
                    <x-filament::button color="primary" size="md" icon="heroicon-o-adjustments-horizontal"
                        x-on:click="$dispatch('open-modal', { id: 'statesModal' })">
                        Estados
                    </x-filament::button>
                @endif

                {{-- Botón logout completamente a la derecha --}}
                <form method="POST" action="{{ filament()->getLogoutUrl() }}">
                    @csrf
                    <x-filament::button color="gray" size="md" type="submit"
                        icon="heroicon-o-arrow-left-on-rectangle">
                        Salir
                    </x-filament::button>
                </form>
            </div>
        </div>
    </x-filament::card>

    {{-- MODAL de selección de estados (no requiere CSS global) --}}
    @if ($this->canSeeStates())
        <x-filament::modal id="statesModal" width="lg" icon="heroicon-o-adjustments-horizontal"
            heading="Seleccionar estado">
            <div class="space-y-4">
                {{-- Si hay estado activo, mostrar tarjeta y opción de finalizar --}}
                @if ($isActive)
                    <div class="rounded-lg border p-3 flex items-center justify-between">
                        <div class="text-sm">
                            <div class="font-medium">
                                Estado actual: {{ ucfirst($stateName) }}
                            </div>
                            <div class="text-gray-600 dark:text-gray-400">
                                Desde {{ $startedAt }}
                            </div>
                        </div>
                        <x-filament::button color="warning" icon="heroicon-m-stop" wire:click="stop"
                            x-on:click="$dispatch('close-modal', { id: 'statesModal' })">
                            Finalizar
                        </x-filament::button>
                    </div>

                    <div class="text-xs text-gray-500">
                        Puedes seleccionar un nuevo estado cuando finalices el actual.
                    </div>
                @else
                    {{-- Grid de estados disponibles --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach ($states as $state)
                            <x-filament::button :color="$state['color'] ?? 'gray'" :icon="$state['icon'] ?? null"
                                wire:click="start({{ $state['id'] }})" class="w-full justify-start"
                                x-on:click="$dispatch('close-modal', { id: 'statesModal' })">
                                {{ $state['name'] }}
                            </x-filament::button>
                        @endforeach
                    </div>
                @endif
            </div>

            <x-slot name="footerActions">
                <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'statesModal' })">
                    Cerrar
                </x-filament::button>

                {{-- Botón para refrescar por si cambian estados en segundo plano --}}
                <x-filament::button color="gray" outlined wire:click="refreshData">
                    Actualizar
                </x-filament::button>
            </x-slot>
        </x-filament::modal>
    @endif
</x-filament::widget>
