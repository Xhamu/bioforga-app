<div>
    <div x-data x-init="const root = $el.querySelector('#chat-scroll');
    const anchor = $el.querySelector('#chat-bottom-anchor');
    if (root && anchor) {
        const io = new IntersectionObserver((entries) => {
            entries.forEach((e) => {
                if (e.isIntersecting) { $wire.markRead(); }
            });
        }, { root, threshold: 1.0 });
        io.observe(anchor);
    }" wire:key="chat-panel-{{ $conversation?->id ?? 'none' }}"
        class="flex flex-col h-[60vh] max-h-[60vh] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:bg-slate-900 dark:border-slate-800">
        @if ($conversation)
            <!-- Mensajes -->
            <div id="chat-scroll"
                class="flex-1 overflow-y-auto px-3 sm:px-4 py-3 bg-slate-50 dark:bg-slate-950 scroll-smooth"
                style="max-height: calc(72vh - 110px);">
                @php
                    $lastDay = null;
                    $prevUserId = null;
                @endphp
                @php
                    $canSeeReceipts = auth()
                        ->user()
                        ?->hasAnyRole(['superadmin', 'administración']);
                @endphp

                @foreach ($conversation->messages->sortBy('id') as $msg)
                    @php
                        $isMine = $msg->user_id === auth()->id();
                        $author = $msg->author;
                        $roleLabel = ucfirst($author?->getRoleNames()->first() ?? 'Usuario');

                        $ts = $msg->created_at->setTimezone('Europe/Madrid');
                        $dayKey = $ts->toDateString();
                        $showDaySeparator = $dayKey !== ($lastDay ?? null);
                        $isContinuation = !$showDaySeparator && ($prevUserId ?? null) === $msg->user_id;
                        $mtClass = $isContinuation ? 'mt-1' : 'mt-2.5';
                        $showHeader = !$isContinuation;
                        $attachments = (array) ($msg->attachments ?? []);

                        // --- Read receipt (solo 1:1; en tu diseño de broadcast ya creas conversaciones por usuario) ---
                        // Tomamos al/los otros participantes de esta conversación y comprobamos su last_read_at
                        $others = $conversation->participants->where('id', '!=', $msg->user_id);
                        $totalOthers = $others->count();
                        $readByCount = $others
                            ->filter(function ($u) use ($msg) {
                                $lr = optional($u->pivot)->last_read_at;
                                return $lr && \Carbon\Carbon::parse($lr)->gte($msg->created_at);
                            })
                            ->count();

                        $isReadByAll = $totalOthers > 0 && $readByCount === $totalOthers;
                        // Para mostrar hora de lectura, usamos el máximo last_read_at de los otros
                        $lastReadAt = $others->map(fn($u) => optional($u->pivot)->last_read_at)->filter()->max();
                        $lastReadAtLocal = $lastReadAt
                            ? \Carbon\Carbon::parse($lastReadAt)->timezone('Europe/Madrid')->format('H:i')
                            : null;
                    @endphp

                    @if ($showDaySeparator)
                        <!-- Separador de día -->
                        <div class="w-full flex items-center my-3 select-none"
                            style="margin-top: 10px; margin-bottom: 10px;">
                            <div class="h-px flex-1 bg-slate-200 dark:bg-slate-800"></div>
                            <div
                                class="mx-3 text-[11px] font-medium text-slate-600 dark:text-slate-400 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-full px-3 py-0.5">
                                {{ $ts->isoFormat('DD [de] MMMM YYYY') }}
                            </div>
                            <div class="h-px flex-1 bg-slate-200 dark:bg-slate-800"></div>
                        </div>
                    @endif

                    <div
                        class="w-full {{ $mtClass }} mb-0.5 flex {{ $isMine ? 'justify-end pr-0.5 sm:pr-1' : 'justify-start pl-0.5 sm:pl-1' }}">
                        <div class="relative max-w-[88%] sm:max-w-[72%] md:max-w-[60%]">
                            <!-- Burbuja -->
                            <div class="px-3 py-2 leading-relaxed break-words rounded-2xl animate-fadeIn
                {{ $isMine
                    ? 'text-slate-900 rounded-l-2xl rounded-tr-2xl'
                    : 'text-slate-900 bg-white border border-slate-200 dark:text-slate-100 dark:bg-slate-800 dark:border-slate-700 rounded-r-2xl rounded-tl-2xl' }}"
                                style="border border-radius: 15px; {{ $isMine ? 'background-color:#DCF8C6' : '' }}">

                                @if ($showHeader)
                                    <div
                                        class="text-[10px] font-semibold {{ $isMine ? 'text-slate-700' : 'text-slate-700 dark:text-slate-300' }} mb-0.5">
                                        {{-- 🔹 Mostrar ROL en lugar del nombre --}}
                                        {{ $roleLabel }}
                                    </div>
                                @endif

                                <div class="text-[14px] whitespace-pre-wrap">
                                    {{ $msg->body }}
                                </div>

                                {{-- 🔹 Adjuntos (imágenes) --}}
                                @if (!empty($attachments))
                                    <div class="mt-2 space-y-2">
                                        @foreach ($attachments as $att)
                                            <img src="{{ asset('storage/' . ltrim($att, '/')) }}" alt="Adjunto"
                                                class="rounded max-w-xs">
                                        @endforeach
                                    </div>
                                @endif

                                <div style="font-size: 10px; color: grey;"
                                    class="mt-0.5 text-[10px] text-right {{ $isMine ? 'text-slate-600' : 'text-slate-500 dark:text-slate-400' }}">
                                    {{ $ts->format('H:i') }}
                                </div>

                                @if ($canSeeReceipts && $isMine)
                                    <div style="font-size: 10px; color: grey;" class="mt-0.5 text-[10px] text-right">
                                        @if ($isReadByAll)
                                            <span
                                                class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                                                {{-- Doble check --}}
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3"
                                                    viewBox="0 0 20 20" fill="currentColor">
                                                    <path
                                                        d="M7.707 10.293a1 1 0 0 0-1.414 1.414l2 2a1 1 0 0 0 1.414 0l6-6a1 1 0 1 0-1.414-1.414L9 11.586l-1.293-1.293z" />
                                                    <path
                                                        d="M5.707 10.293a1 1 0 0 0-1.414 1.414l2 2a1 1 0 0 0 1.414 0l.293-.293a1 1 0 0 0-1.414-1.414l-.879-.879z" />
                                                </svg>
                                                Leído{{ $lastReadAtLocal ? ' · ' . $lastReadAtLocal : '' }}
                                            </span>
                                        @else
                                            <span
                                                class="inline-flex items-center gap-1 text-gray-400 dark:text-gray-500">
                                                {{-- Check simple --}}
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3"
                                                    viewBox="0 0 20 20" fill="currentColor">
                                                    <path
                                                        d="M7.707 10.293a1 1 0 0 0-1.414 1.414l2 2a1 1 0 0 0 1.414 0l6-6a1 1 0 1 0-1.414-1.414L9 11.586l-1.293-1.293z" />
                                                </svg>
                                                Enviado
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            <!-- Cola de burbuja -->
                            @if ($isMine)
                                <span class="absolute -right-1 bottom-1 block w-2.5 h-2.5 rotate-45"
                                    style="background-color:#DCF8C6"></span>
                                <span
                                    class="hidden dark:block absolute -right-1 bottom-1 w-2.5 h-2.5 rotate-45 bg-emerald-700"></span>
                            @else
                                <span
                                    class="absolute -left-1 bottom-1 block w-2.5 h-2.5 rotate-45 bg-white border border-slate-200 dark:hidden"></span>
                                <span
                                    class="hidden dark:block absolute -left-1 bottom-1 w-2.5 h-2.5 rotate-45 bg-slate-800 border border-slate-700"></span>
                            @endif
                        </div>
                    </div>

                    @php
                        $lastDay = $dayKey;
                        $prevUserId = $msg->user_id;
                    @endphp
                @endforeach

                <div id="chat-bottom-anchor"></div>
            </div>

            <script>
                const scrollChat = () => {
                    const el = document.getElementById('chat-scroll');
                    if (!el) return;
                    el.scrollTop = el.scrollHeight;
                };
                document.addEventListener('livewire:update', () => requestAnimationFrame(scrollChat));
                document.addEventListener('livewire:load', () => setTimeout(scrollChat, 80));
                window.addEventListener('load', () => setTimeout(scrollChat, 120));
            </script>
        @else
            <div class="flex-1 flex items-center justify-center text-slate-400 text-sm">
                No hay conversación seleccionada
            </div>
        @endif
    </div>

    <style>
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(6px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fadeIn {
            animation: fadeIn .18s ease-out;
        }

        /* Scrollbar sutil */
        #chat-scroll::-webkit-scrollbar {
            width: 8px;
        }

        #chat-scroll::-webkit-scrollbar-track {
            background: transparent;
        }

        #chat-scroll::-webkit-scrollbar-thumb {
            background: rgba(100, 116, 139, .25);
            border-radius: 9999px;
        }

        #chat-scroll:hover::-webkit-scrollbar-thumb {
            background: rgba(100, 116, 139, .4);
        }
    </style>
</div>
