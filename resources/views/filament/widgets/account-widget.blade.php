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
        $stateName = $active?->state?->name ?? 'Activo';
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

                    {{-- Estado actual --}}
                    @if ($this->canSeeStates())
                        <div class="mt-2 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                            <span class="font-medium">Estado actual:</span>

                            @php
                                // Define color del badge según estado
                                $badgeColor = match (strtolower($stateName)) {
                                    'activo', 'en curso', 'trabajando' => 'success',
                                    default => 'gray',
                                };
                            @endphp

                            <x-filament::badge :color="$badgeColor">
                                {{ ucfirst($stateName) }}
                            </x-filament::badge>

                            @if ($isActive)
                                <span>desde {{ $startedAt }}</span>
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

                {{-- Botón MENSAJES con color dinámico si hay no leídos --}}
                @php
                    $unread = $this->getUnreadCount();
                    $color = $unread > 0 ? 'danger' : 'gray'; // rojo si hay mensajes sin leer
                @endphp

                <x-filament::button color="{{ $color }}" size="md" icon="heroicon-m-chat-bubble-left-right"
                    x-on:click="$dispatch('open-modal', { id: 'messagesModal' })">

                    <span class="inline-flex items-center gap-2">
                        <span>Mensajes</span>

                        @if ($unread > 0)
                            {{-- Badge sigue mostrando el número --}}
                            <x-filament::badge :color="$unread > 0 ? 'white' : 'gray'">
                                {{ $unread }}
                            </x-filament::badge>
                        @endif
                    </span>
                </x-filament::button>

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

    {{-- MODAL de mensajes estilo WhatsApp (con ChatPanel dinámico) --}}
    <x-filament::modal id="messagesModal" width="3xl" icon="heroicon-m-chat-bubble-left-right"
        heading="Mensajería interna"
        x-on:close-modal.window="if ($event.detail?.id === 'messagesModal') { $wire.$refresh() }">
        @php
            $uid = auth()->id();

            $conversations = \App\Models\Conversation::query()
                ->join('conversation_participants as cp', 'cp.conversation_id', '=', 'conversations.id')
                ->where('cp.user_id', $uid)
                ->select('conversations.*')
                ->selectSub(function ($q) use ($uid) {
                    $q->from('messages')
                        ->selectRaw('COUNT(*)')
                        ->whereColumn('messages.conversation_id', 'conversations.id')
                        ->where('messages.user_id', '!=', $uid)
                        ->whereRaw(
                            'messages.created_at > COALESCE((
                            SELECT cp2.last_read_at
                            FROM conversation_participants cp2
                            WHERE cp2.conversation_id = conversations.id
                              AND cp2.user_id = ?
                        ), "1970-01-01 00:00:00")',
                            [$uid],
                        );
                }, 'unread_count')
                ->with(['participants', 'messages' => fn($q) => $q->latest()])
                ->latest('conversations.updated_at')
                ->get();

            $firstConvId = optional($conversations->first())->id;
        @endphp

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4" wire:poll.10s>
            {{-- Sidebar: lista de conversaciones --}}
            <aside
                class="lg:col-span-4 h-[40vh] max-h-[40vh] overflow-y-auto rounded-xl border bg-white dark:bg-gray-900">
                <div class="p-3 border-b sticky top-0 bg-white/90 dark:bg-gray-900/90 backdrop-blur z-10">
                    <div class="text-sm font-semibold">Chats</div>
                </div>

                <div class="divide-y">
                    @php
                        $canSeeNames = auth()
                            ->user()
                            ?->hasAnyRole(['superadmin', 'administración']);
                    @endphp

                    @forelse ($conversations as $conv)
                        @php
                            $other = $conv->participants->firstWhere('id', '!=', $uid);

                            // Rol del otro participante
                            $otherRole = $other?->getRoleNames()->first() ?? 'Usuario';
                            $otherRoleLabel = ucfirst($otherRole);

                            // Nombre completo (si existe)
                            $name = $other ? trim(($other->name ?? '') . ' ' . ($other->apellidos ?? '')) : '—';

                            // Último mensaje (latest())
                            $last = $conv->messages->first();

                            // Prefijo de preview: por coherencia mantenemos el ROL del autor del último mensaje
                            $senderRole = $last?->author?->getRoleNames()->first() ?? 'Usuario';
                            $senderRoleLabel = ucfirst($senderRole);

                            $preview = $last
                                ? $senderRoleLabel . ': ' . \Illuminate\Support\Str::limit($last->body, 70)
                                : '—';

                            $time = $last ? $last->created_at->timezone('Europe/Madrid')->format('d/m H:i') : '';
                            $unread = (int) ($conv->unread_count ?? 0);

                            // Iniciales: si puede ver nombres -> de nombre; si no -> del rol
                            if ($canSeeNames) {
                                $firstInitial = mb_substr($other->name ?? 'U', 0, 1);
                                $lastInitial = mb_substr($other->apellidos ?? '', 0, 1);
                                $ini = mb_strtoupper($firstInitial . ($lastInitial ?: ''));
                            } else {
                                $ini = mb_strtoupper(mb_substr($otherRoleLabel, 0, 2));
                            }
                        @endphp

                        <button type="button"
                            class="w-full p-3 flex items-start gap-3 hover:bg-gray-50 dark:hover:bg-gray-800 transition text-left"
                            x-on:click="Livewire.dispatch('open-chat-panel', { id: {{ $conv->id }} })">

                            <div
                                class="w-10 h-10 rounded-full bg-gray-800 text-white flex items-center justify-center text-sm font-semibold">
                                {{ $ini }}
                            </div>

                            <div class="min-w-0 grow">
                                <div class="flex items-center gap-2">
                                    <div class="truncate">
                                        @if ($canSeeNames)
                                            {{-- Muestra NOMBRE en grande y el rol debajo en pequeño --}}
                                            <div class="font-medium truncate">{{ $name }}</div>
                                            <div class="text-[11px] text-gray-500 dark:text-gray-400 truncate">
                                                {{ $otherRoleLabel }}</div>
                                        @else
                                            {{-- Solo el ROL (como antes) --}}
                                            <div class="font-medium truncate">{{ $otherRoleLabel }}</div>
                                        @endif
                                    </div>
                                    <div class="ml-auto text-xs text-gray-500">{{ $time }}</div>
                                </div>

                                {{-- Preview mantiene el rol del autor del último mensaje --}}
                                <div class="text-sm text-gray-500 truncate">{{ $preview }}</div>
                            </div>

                            @if ($unread > 0)
                                <x-filament::badge color="danger">{{ $unread }}</x-filament::badge>
                            @endif
                        </button>
                    @empty
                        <div class="p-4 text-sm text-gray-500">Sin conversaciones.</div>
                    @endforelse
                </div>
            </aside>

            {{-- Panel de conversación: componente Livewire que reacciona al evento "open-chat-panel" --}}
            <section class="lg:col-span-8 h-[40vh] max-h-[40vh] rounded-xl border bg-white dark:bg-gray-900">
                <livewire:chat-panel :conversation-id="$firstConvId" :key="'chat-panel'" />
            </section>
        </div>

        <x-slot name="footerActions">
            <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'messagesModal' })">
                Cerrar
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

</x-filament::widget>
