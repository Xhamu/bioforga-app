<div>
    <div x-data x-init="$wire.markRead()" wire:key="chat-panel-{{ $conversation?->id ?? 'none' }}"
        class="flex flex-col h-[72vh] max-h-[60vh] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:bg-slate-900 dark:border-slate-800">
        @if ($conversation)
            <!-- Mensajes -->
            <div id="chat-scroll"
                class="flex-1 overflow-y-auto px-3 sm:px-4 py-3 bg-slate-50 dark:bg-slate-950 scroll-smooth"
                style="max-height: calc(72vh - 110px);">
                @php
                    $lastDay = null;
                    $prevUserId = null;
                @endphp

                @foreach ($conversation->messages->sortBy('id') as $msg)
                    @php
                        $isMine = $msg->user_id === auth()->id();
                        $author = $msg->author;
                        $full = (string) ($author->name ?? '');
                        $first = \Illuminate\Support\Str::of($full)->before(' ')->value();
                        $lastInitial = strtoupper(
                            \Illuminate\Support\Str::of($full)->after(' ')->substr(0, 1)->value(),
                        );
                        $displayName = trim($first) . ' ' . ($lastInitial ? $lastInitial . '.' : '');

                        $ts = $msg->created_at->setTimezone('Europe/Madrid');
                        $dayKey = $ts->toDateString();
                        $showDaySeparator = $dayKey !== $lastDay;
                        $isContinuation = !$showDaySeparator && $prevUserId === $msg->user_id;
                        $mtClass = $isContinuation ? 'mt-1' : 'mt-2.5';
                        $showHeader = !$isMine && !$isContinuation;
                    @endphp

                    @if ($showDaySeparator)
                        <!-- Separador de día -->
                        <div class="w-full flex items-center my-3 select-none" style="margin-top: 10px; margin-bottom: 10px;">
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
                                        {{ $displayName }}
                                    </div>
                                @endif

                                <div class="text-[14px] whitespace-pre-wrap">
                                    {{ $msg->body }}
                                </div>

                                <div style="font-size: 10px; color: grey;"
                                    class="mt-0.5 text-[10px] text-right {{ $isMine ? 'text-slate-600' : 'text-slate-500 dark:text-slate-400' }}">
                                    {{ $ts->format('H:i') }}
                                </div>
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

            <!-- Input -->
            <div class="border-t border-slate-200 bg-white p-3">
                <form wire:submit.prevent="send" class="flex items-center gap-2">
                    <input wire:model.defer="message" type="text" placeholder="Escribe un mensaje…"
                        class="flex-1 h-11 rounded-full border border-emerald-300 px-4 text-[15px] bg-white placeholder:text-slate-400 
                   focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 outline-none transition-all duration-200 shadow-sm" />
                    <button type="submit"
                        class="h-11 px-5 rounded-full bg-emerald-500 text-black font-medium hover:bg-emerald-600 active:scale-95 transition flex items-center gap-2 shadow-sm"
                        title="Enviar">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path
                                d="M2.94 2.94a1.5 1.5 0 0 1 1.66-.32l12 5a1.5 1.5 0 0 1 0 2.76l-12 5a1.5 1.5 0 0 1-2.05-1.72l1.18-4.72a.5.5 0 0 1 .48-.38h5.59a.5.5 0 0 0 0-1H4.21a.5.5 0 0 1-.48-.38L2.55 4.66a1.5 1.5 0 0 1 .39-1.72Z" />
                        </svg>
                        <span class="hidden sm:inline">Enviar</span>
                    </button>
                </form>
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
