<x-filament::widget>
    @php
        $nombre = auth()->user()->name;
        $apellidos = auth()->user()->apellidos;

        $inicialNombre = substr($nombre, 0, 1);
        $inicialApellido = substr($apellidos, 0, 1);

        $iniciales = strtoupper($inicialNombre . $inicialApellido);
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
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $nombre }} {{ $apellidos }}
                    </div>
                </div>
            </div>

            {{-- Botón logout completamente a la derecha --}}
            <form method="POST" action="{{ filament()->getLogoutUrl() }}" class="ml-auto">
                @csrf
                <x-filament::button
                    color="gray"
                    size="md"
                    type="submit"
                    icon="heroicon-o-arrow-left-on-rectangle"
                >
                    Salir
                </x-filament::button>
            </form>

        </div>
    </x-filament::card>
</x-filament::widget>
