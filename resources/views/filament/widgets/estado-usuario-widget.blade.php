{{-- resources/views/filament/widgets/estado-usuario-widget.blade.php --}}
<x-filament::widget>
    @php
        $isActive = (bool) $active;
        $stateName = $active?->state?->name ?? 'Sin estado';

        // Preferimos color HEX si existe
        $hex = null;
        if (
            isset($active?->state?->color_hex) &&
            is_string($active->state->color_hex) &&
            str_starts_with($active->state->color_hex, '#')
        ) {
            $hex = strtoupper($active->state->color_hex);
        }

        // Fallback semántico -> Tailwind hue
        $tw = match ($active?->state?->color) {
            'success' => 'green',
            'danger' => 'red',
            'warning' => 'amber',
            'info' => 'blue',
            'primary' => 'indigo',
            'gray' => 'gray',
            default => 'gray',
        };

        // Contraste texto sobre HEX simple
        $contrast = function (?string $hexColor): string {
            if (!$hexColor || !preg_match('/^#([0-9A-F]{6})$/i', $hexColor)) {
                return '#111827';
            } // gray-900
            $r = hexdec(substr($hexColor, 1, 2));
            $g = hexdec(substr($hexColor, 3, 2));
            $b = hexdec(substr($hexColor, 5, 2));
            $l = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
            return $l > 160 ? '#111827' : '#F9FAFB';
        };

        // Utilidades para generar variantes suaves del HEX
        $hexToRgb = function (string $hexColor): array {
            $hexColor = ltrim($hexColor, '#');
            return [hexdec(substr($hexColor, 0, 2)), hexdec(substr($hexColor, 2, 2)), hexdec(substr($hexColor, 4, 2))];
        };

        $mix = function (string $hexColor, array $target = [255, 255, 255], float $ratio = 0.85): string {
            // mezcla lineal con blanco por defecto
            [$r, $g, $b] = $hexToRgb($hexColor);
            $r = (int) round($r * (1 - $ratio) + $target[0] * $ratio);
            $g = (int) round($g * (1 - $ratio) + $target[1] * $ratio);
            $b = (int) round($b * (1 - $ratio) + $target[2] * $ratio);
            return sprintf('#%02X%02X%02X', $r, $g, $b);
        };

        $hexText = $contrast($hex);
        $hexSoft = $hex ? $mix($hex, [255, 255, 255], 0.92) : null; // fondo muy suave
        $hexRing = $hex ?: '#E5E7EB'; // ring fallback gray-200

        // Tiempo
        if ($isActive) {
            $diff = now()->diff($active->started_at);
            $h = str_pad($diff->h + $diff->d * 24, 2, '0', STR_PAD_LEFT);
            $m = str_pad($diff->i, 2, '0', STR_PAD_LEFT);
            $s = str_pad($diff->s, 2, '0', STR_PAD_LEFT);
            $elapsed = "{$h}:{$m}:{$s}";
            $startedAt = $active->started_at->timezone('Europe/Madrid')->format('d/m/Y H:i:s');
        }
    @endphp

    <x-filament::card :class="[
        // base
        'rounded-2xl shadow-sm border relative overflow-hidden',
        'border-gray-200/70 dark:border-white/10',
        'p-6 md:p-8',
    ]">
        {{-- Fondo decorativo dinámico --}}
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 opacity-60 dark:opacity-40"
            @if ($hex) style="
                    --grad-a: {{ $hexSoft }};
                    --grad-b: {{ $hex }};
                    background:
                        radial-gradient(1200px 600px at 100% -10%, var(--grad-b) 0%, transparent 60%),
                        radial-gradient(800px 400px at -10% 110%, var(--grad-a) 0%, transparent 60%),
                        linear-gradient(to bottom right, #fff, #f9fafb);
                "
            @else
                class="bg-gradient-to-br from-white to-gray-50 dark:from-gray-900 dark:to-gray-900/60" @endif>
        </div>

        {{-- Contenido --}}
        <div class="relative space-y-6">
            {{-- HEADER --}}
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-3">
                    <div class="relative">
                        {{-- “Punto” pulsante si activo --}}
                        <span class="absolute -top-1 -left-1 h-3 w-3">
                            @if ($isActive)
                                <span
                                    class="absolute inline-flex h-3 w-3 rounded-full bg-emerald-400 opacity-75 animate-ping"></span>
                                <span class="relative inline-flex h-3 w-3 rounded-full bg-emerald-500"></span>
                            @endif
                        </span>
                        <h2 class="text-xl font-semibold tracking-tight text-gray-900 dark:text-white">
                            {{ static::$heading }}
                        </h2>
                    </div>

                    {{-- Chip de estado --}}
                    @if ($isActive)
                        @if ($hex)
                            <span
                                class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-sm ring-1 ring-inset
                                       bg-[--badge-bg] text-[--badge-fg] ring-[--badge-ring] ring-opacity-30 backdrop-blur-sm"
                                style="--badge-bg: {{ $hexSoft }}; --badge-fg: {{ $hexText }}; --badge-ring: {{ $hexRing }};"
                                aria-label="Estado: {{ ucfirst($stateName) }}">
                                @if ($active->state?->icon)
                                    <x-dynamic-component :component="$active->state->icon" class="w-4 h-4" />
                                @else
                                    <x-heroicon-m-sparkles class="w-4 h-4" />
                                @endif
                                <span class="font-medium">{{ ucfirst($stateName) }}</span>
                            </span>
                        @else
                            <span
                                class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-sm ring-1 ring-inset
                                       bg-{{ $tw }}-50 text-{{ $tw }}-700 ring-{{ $tw }}-200
                                       dark:bg-{{ $tw }}-500/10 dark:text-{{ $tw }}-300 dark:ring-{{ $tw }}-500/30 backdrop-blur-sm"
                                aria-label="Estado: {{ ucfirst($stateName) }}">
                                @if ($active->state?->icon)
                                    <x-dynamic-component :component="$active->state->icon" class="w-4 h-4" />
                                @else
                                    <x-heroicon-m-sparkles class="w-4 h-4" />
                                @endif
                                <span class="font-medium">{{ ucfirst($stateName) }}</span>
                            </span>
                        @endif
                    @else
                        <span class="text-gray-500 dark:text-gray-400 italic">Sin estado activo</span>
                    @endif
                </div>

                {{-- Toolbar derecha --}}
                <div class="flex items-center gap-2">
                    <x-filament::button color="gray" outlined wire:click="refreshData" class="gap-2"
                        aria-label="Actualizar estado">
                        <x-slot name="icon">
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor" wire:loading.class="animate-spin">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 12a8 8 0 018-8m0 0v3m0-3l-2 2m2-2l2 2M20 12a8 8 0 01-8 8m0 0v-3m0 3l2-2m-2 2l-2-2" />
                            </svg>
                        </x-slot>
                        <span>Actualizar</span>
                    </x-filament::button>
                </div>
            </div>

            {{-- INFO / MÉTRICAS --}}
            @if ($isActive)
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4" wire:poll.1s>
                    <div class="col-span-2">
                        <div class="flex items-center gap-3 p-4 rounded-xl ring-1 ring-inset backdrop-blur
                            @if ($hex) ring-[--kpi-ring] bg-[--kpi-bg]
                            @else
                                ring-gray-200/70 bg-white/60 dark:bg-white/5 dark:ring-white/10 @endif"
                            @if ($hex) style="--kpi-ring: {{ $hexRing }}; --kpi-bg: {{ $hexSoft }}33;" @endif>
                            <div class="shrink-0">
                                <x-heroicon-m-clock class="w-5 h-5 text-gray-500 dark:text-gray-400" />
                            </div>
                            <div class="flex-1">
                                <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Tiempo</p>
                                <p class="font-mono tabular-nums text-2xl font-semibold text-gray-900 dark:text-white">
                                    {{ $elapsed }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="p-4 rounded-xl ring-1 ring-inset backdrop-blur
                            @if ($hex) ring-[--kpi-ring] bg-[--kpi-bg]
                            @else
                                ring-gray-200/70 bg-white/60 dark:bg-white/5 dark:ring-white/10 @endif"
                            @if ($hex) style="--kpi-ring: {{ $hexRing }}; --kpi-bg: {{ $hexSoft }}33;" @endif>
                            <div class="flex items-center gap-3">
                                <x-heroicon-m-calendar class="w-5 h-5 text-gray-500 dark:text-gray-400" />
                                <div class="min-w-0">
                                    <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Comenzó
                                    </p>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                        {{ $startedAt }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- DIVISOR --}}
            <div class="h-px bg-gray-200/80 dark:bg-white/10"></div>

            {{-- ACCIONES --}}
            <div class="flex flex-wrap items-center gap-3">
                @if (!$isActive)
                    {{-- Lista de estados como “pills” accionables --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 w-full">
                        @foreach ($states as $state)
                            <x-filament::button :color="$state->color ?? 'gray'" :icon="$state->icon ?? null"
                                wire:click="start({{ $state->id }})" size="lg"
                                class="justify-start px-5 py-3 rounded-xl group">
                                <span class="truncate">{{ $state->name }}</span>
                            </x-filament::button>
                        @endforeach
                    </div>
                @else
                    <x-filament::button color="warning" wire:click="stop" icon="heroicon-m-stop" size="lg"
                        class="px-6 py-3 rounded-xl">
                        Finalizar estado
                    </x-filament::button>
                @endif
            </div>

            {{-- FOOT / META --}}
            @if ($isActive)
                <div
                    class="pt-4 text-xs text-gray-500 dark:text-gray-400 border-t border-gray-200/60 dark:border-white/10">
                    <span class="font-medium">Comenzó:</span>
                    {{ $startedAt }}
                </div>
            @endif
        </div>
    </x-filament::card>
</x-filament::widget>
