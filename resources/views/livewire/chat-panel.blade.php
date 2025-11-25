<div>
    <div x-data="{
        previewOpen: false,
        previewType: null,
        previewUrl: null,
        previewName: null,
    }" x-init="const root = $el.querySelector('#chat-scroll');
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

                        /** @var array $attachments */
                        $attachments = (array) ($msg->attachments ?? []);

                        // Read receipts
                        $others = $conversation->participants->where('id', '!=', $msg->user_id);
                        $totalOthers = $others->count();
                        $readByCount = $others
                            ->filter(function ($u) use ($msg) {
                                $lr = optional($u->pivot)->last_read_at;
                                return $lr && \Carbon\Carbon::parse($lr)->gte($msg->created_at);
                            })
                            ->count();

                        $isReadByAll = $totalOthers > 0 && $readByCount === $totalOthers;
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
                                        {{ $roleLabel }}
                                    </div>
                                @endif

                                <div class="text-[14px] whitespace-pre-wrap">
                                    {{ $msg->body }}
                                </div>

                                {{-- 🔹 Adjuntos --}}
                                @if (!empty($attachments))
                                    <div class="mt-2 space-y-1.5">
                                        @foreach ($attachments as $att)
                                            @php
                                                $path = ltrim((string) $att, '/');

                                                // Por si alguna vez se guarda con 'archivos/' delante, lo normalizamos:
                                                $path = preg_replace('#^archivos/#', '', $path);

                                                // URL pública usando el disk 'public'
                                                $url = \Storage::disk('public')->url($path);

                                                $name = basename($path);
                                                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                                                $isImage = in_array($ext, [
                                                    'jpg',
                                                    'jpeg',
                                                    'png',
                                                    'gif',
                                                    'webp',
                                                    'bmp',
                                                    'svg',
                                                ]);
                                                $isPdf = $ext === 'pdf';
                                            @endphp

                                            <div
                                                class="flex flex-col sm:flex-row sm:items-center gap-1.5 px-2.5 py-1.5 rounded-md
                                                bg-slate-100 dark:bg-slate-700/70">
                                                {{-- Icono + nombre --}}
                                                <div class="flex items-center gap-2 min-w-0">
                                                    @if ($isImage)
                                                        <svg xmlns="http://www.w3.org/2000/svg"
                                                            class="w-4 h-4 text-slate-600 dark:text-slate-200"
                                                            viewBox="0 0 20 20" fill="currentColor">
                                                            <path
                                                                d="M4 3a2 2 0 0 0-2 2v10c0 1.1.9 2 2 2h12a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H4zm0 2h12v6.586l-2.293-2.293a1 1 0 0 0-1.414 0L9 13l-1.293-1.293a1 1 0 0 0-1.414 0L4 14.293V5z" />
                                                        </svg>
                                                    @elseif ($isPdf)
                                                        <svg xmlns="http://www.w3.org/2000/svg"
                                                            class="w-4 h-4 text-red-500" viewBox="0 0 20 20"
                                                            fill="currentColor">
                                                            <path
                                                                d="M6 2a2 2 0 0 0-2 2v12c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V8.414A2 2 0 0 0 15.414 7L11 2.586A2 2 0 0 0 9.586 2H6z" />
                                                        </svg>
                                                    @else
                                                        <svg xmlns="http://www.w3.org/2000/svg"
                                                            class="w-4 h-4 text-slate-500" viewBox="0 0 20 20"
                                                            fill="currentColor">
                                                            <path
                                                                d="M4 3a2 2 0 0 1 2-2h5.586A2 2 0 0 1 13 1.586L17.414 6A2 2 0 0 1 18 7.414V17a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V3z" />
                                                        </svg>
                                                    @endif

                                                    <span
                                                        class="text-[11px] leading-snug break-all sm:break-normal sm:truncate sm:max-w-[220px] text-slate-800 dark:text-slate-100">
                                                        {{ $name }}
                                                    </span>
                                                </div>

                                                {{-- Botones de acción --}}
                                                <div class="flex flex-wrap items-center gap-1.5 sm:ml-auto">

                                                    @if ($isImage || $isPdf)
                                                        {{-- Ver (abre modal) --}}
                                                        <button type="button"
                                                            @click.prevent="
                                previewOpen = true;
                                previewType = '{{ $isImage ? 'image' : 'pdf' }}';
                                previewUrl = '{{ $url }}';
                                previewName = '{{ $name }}';
                            "
                                                            class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-medium
                                   border border-sky-500 text-sky-700 bg-white
                                   hover:bg-sky-50 dark:bg-slate-800 dark:text-sky-300 dark:border-sky-400">
                                                            Ver
                                                        </button>

                                                        {{-- Descargar --}}
                                                        <a href="{{ $url }}" download
                                                            class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-medium
                                   border border-slate-300 text-slate-700 bg-white
                                   hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-100 dark:border-slate-500">
                                                            Descargar
                                                        </a>
                                                    @else
                                                        {{-- Solo descarga para otros tipos --}}
                                                        <a href="{{ $url }}" download
                                                            class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-medium
                                   border border-slate-300 text-slate-700 bg-white
                                   hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-100 dark:border-slate-500">
                                                            Descargar
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
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

        {{-- 🔹 Modal de preview adjuntos --}}
        <div x-cloak x-show="previewOpen" x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60">

            <div @click.away="previewOpen = false"
                class="
            bg-white dark:bg-slate-900 rounded-xl shadow-lg 
            p-4 flex flex-col 
            w-auto                      {{-- ancho adaptable --}}
            max-w-[100px]                {{-- ancho máximo --}}
        ">

                <!-- Header -->
                <div class="flex items-center justify-between mb-3 gap-4">
                    <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100 truncate" x-text="previewName">
                    </h3>

                    <button type="button" class="text-slate-500 hover:text-slate-800 dark:hover:text-slate-200"
                        @click="previewOpen = false">
                        ✕
                    </button>
                </div>

                <!-- Contenido -->
                <div class="flex-1 overflow-hidden">

                    {{-- Imagen --}}
                    <template x-if="previewType === 'image'">
                        <div class="w-full h-full flex items-center justify-center overflow-auto">
                            <img :src="previewUrl" alt=""
                                class="max-w-full max-h-full object-contain rounded-lg">
                        </div>
                    </template>

                    {{-- PDF --}}
                    <template x-if="previewType === 'pdf'">
                        <div class="w-full h-full overflow-hidden">
                            <iframe :src="previewUrl" class="w-full h-full rounded-lg border border-slate-200">
                            </iframe>
                        </div>
                    </template>

                </div>

                <!-- Footer -->
                <div class="mt-3 flex justify-end gap-2">
                    <a :href="previewUrl" download
                        class="inline-flex items-center px-3 py-1.5 rounded-md text-xs font-medium bg-slate-800 text-white hover:bg-slate-900 dark:bg-slate-100 dark:text-slate-900">
                        Descargar
                    </a>

                    <button type="button"
                        class="inline-flex items-center px-3 py-1.5 rounded-md text-xs font-medium bg-slate-200 text-slate-800 hover:bg-slate-300 dark:bg-slate-700 dark:text-slate-100"
                        @click="previewOpen = false">
                        Cerrar
                    </button>
                </div>
            </div>
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

            [x-cloak] {
                display: none !important;
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
