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

    <x-filament::card class="px-4 sm:px-5 py-4 rounded-lg">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 sm:gap-6">

            {{-- Avatar + nombre + apellidos --}}
            <div class="flex items-center sm:items-center gap-4 sm:gap-5">
                <div
                    class="flex items-center justify-center w-12 h-12 rounded-full bg-black text-white text-base font-semibold uppercase flex-shrink-0">
                    {{ $iniciales }}
                </div>

                <div class="leading-tight text-center sm:text-left">
                    <div class="text-[14px] sm:text-[15px] font-semibold text-gray-900 dark:text-white">
                        Bienvenido/a
                    </div>
                    <div class="text-base sm:text-lg text-gray-500 dark:text-gray-400">
                        {{ $nombre }} {{ strtoupper(substr($apellidos, 0, 1)) }}.
                    </div>

                    {{-- Estado actual --}}
                    @if ($this->canSeeStates())
                        <div
                            class="mt-2 flex flex-col sm:flex-row sm:items-center gap-1.5 sm:gap-2 text-[11px] sm:text-xs text-gray-500 dark:text-gray-400">
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
                                <span class="sm:ml-1">
                                    desde {{ $startedAt }}
                                </span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            {{-- Botones --}}
            <div class="flex flex-wrap items-center justify-center sm:justify-end gap-2 sm:gap-2.5 lg:gap-3 sm:ml-auto">

                {{-- Botón ESTADOS --}}
                @if ($this->canSeeStates())
                    <x-filament::button color="primary" size="sm" class="w-full xs:w-auto sm:w-auto justify-center"
                        icon="heroicon-o-adjustments-horizontal"
                        x-on:click="$dispatch('open-modal', { id: 'statesModal' })">
                        Estados
                    </x-filament::button>
                @endif

                {{-- Botón MENSAJES con color dinámico si hay no leídos --}}
                @php
                    $unread = $this->getUnreadCount();
                    $color = $unread > 0 ? 'danger' : 'gray'; // rojo si hay mensajes sin leer
                @endphp

                <x-filament::button color="{{ $color }}" size="sm"
                    class="w-full xs:w-auto sm:w-auto justify-center" icon="heroicon-m-chat-bubble-left-right"
                    x-on:click="$dispatch('open-modal', { id: 'messagesModal' })">
                    <span class="inline-flex items-center gap-1.5 sm:gap-2">
                        <span>Mensajes</span>

                        @if ($unread > 0)
                            <x-filament::badge :color="$unread > 0 ? 'white' : 'gray'" size="sm">
                                {{ $unread }}
                            </x-filament::badge>
                        @endif
                    </span>
                </x-filament::button>

                {{-- Botón logout --}}
                <form method="POST" action="{{ filament()->getLogoutUrl() }}" class="w-full xs:w-auto sm:w-auto">
                    @csrf
                    <x-filament::button color="gray" size="sm" type="submit"
                        class="w-full xs:w-auto sm:w-auto justify-center" icon="heroicon-o-arrow-left-on-rectangle">
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
        class="overflow-hidden max-h-[70vh] overscroll-none" x-data="{ activeId: @js(optional(optional($conversations ?? null)->first())->id ?? null) }"
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

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 h-[70vh] max-h-[calc(100vh-180px)]" wire:poll.10s>
            {{-- Sidebar: lista de conversaciones --}}
            <aside
                class="lg:col-span-4 h-full overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-gray-900 flex flex-col">
                {{-- Header sidebar --}}
                <div
                    class="p-3 border-b border-slate-200 dark:border-slate-800 sticky top-0 bg-white/95 dark:bg-gray-900/95 backdrop-blur z-10 flex items-center justify-between">
                    <div class="text-xs font-semibold tracking-wide text-slate-700 dark:text-slate-200 uppercase">
                        Chats
                    </div>
                    <div class="text-[11px] text-slate-400">
                        {{ $conversations->count() }}
                        {{ $conversations->count() === 1 ? 'conversación' : 'conversaciones' }}
                    </div>
                </div>

                {{-- Lista --}}
                <div class="flex-1 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800">
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

                            // Prefijo de preview: rol del autor del último mensaje
                            $senderRole = $last?->author?->getRoleNames()->first() ?? 'Usuario';
                            $senderRoleLabel = ucfirst($senderRole);

                            $preview = $last
                                ? $senderRoleLabel . ': ' . \Illuminate\Support\Str::limit($last->body, 70)
                                : 'Sin mensajes todavía';

                            $time = $last ? $last->created_at->timezone('Europe/Madrid')->format('d/m H:i') : '';
                            $unread = (int) ($conv->unread_count ?? 0);

                            // Iniciales: si puede ver nombres -> de nombre; si no -> del rol
                            if ($canSeeNames && $other) {
                                $firstInitial = mb_substr($other->name ?? 'U', 0, 1);
                                $lastInitial = mb_substr($other->apellidos ?? '', 0, 1);
                                $ini = mb_strtoupper($firstInitial . ($lastInitial ?: ''));
                            } else {
                                $ini = mb_strtoupper(mb_substr($otherRoleLabel, 0, 2));
                            }
                        @endphp

                        <button type="button" class="w-full p-3 flex items-start gap-3 text-left transition"
                            :class="activeId === {{ $conv->id }} ?
                                'bg-slate-100/80 dark:bg-slate-800/80' :
                                'hover:bg-slate-50 dark:hover:bg-slate-800/60'"
                            x-on:click="
                            activeId = {{ $conv->id }};
                            Livewire.dispatch('open-chat-panel', { id: {{ $conv->id }} });
                        ">
                            {{-- Avatar --}}
                            <div
                                class="w-10 h-10 rounded-full bg-slate-800 text-white flex items-center justify-center text-sm font-semibold shrink-0">
                                {{ $ini }}
                            </div>

                            {{-- Info --}}
                            <div class="min-w-0 grow">
                                <div class="flex items-center gap-2">
                                    <div class="truncate">
                                        @if ($canSeeNames && $other)
                                            <div
                                                class="font-medium text-sm text-slate-800 dark:text-slate-100 truncate">
                                                {{ $name }}
                                            </div>
                                            <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate">
                                                {{ $otherRoleLabel }}
                                            </div>
                                        @else
                                            <div
                                                class="font-medium text-sm text-slate-800 dark:text-slate-100 truncate">
                                                {{ $otherRoleLabel }}
                                            </div>
                                        @endif
                                    </div>
                                    <div class="ml-auto text-[11px] text-slate-400">
                                        {{ $time }}
                                    </div>
                                </div>

                                <div class="mt-0.5 flex items-center gap-2">
                                    <div class="text-xs text-slate-500 dark:text-slate-400 truncate">
                                        {{ $preview }}
                                    </div>

                                    @if ($unread > 0)
                                        <span class="ml-auto">
                                            <x-filament::badge color="danger" size="xs">
                                                {{ $unread }}
                                            </x-filament::badge>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </button>
                    @empty
                        <div class="p-6 text-sm text-slate-500 flex flex-col items-center justify-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-slate-300 dark:text-slate-600"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.985 9.985 0 01-4.244-.938L3 20l1.133-3.398A7.96 7.96 0 013 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                            <div>No tienes conversaciones todavía.</div>
                        </div>
                    @endforelse
                </div>
            </aside>

            {{-- Panel de conversación --}}
            <section
                class="lg:col-span-8 h-full rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-gray-900 overflow-hidden">
                <livewire:chat-panel :conversation-id="$firstConvId" :key="'chat-panel'" />
            </section>
        </div>

    </x-filament::modal>

</x-filament::widget>
