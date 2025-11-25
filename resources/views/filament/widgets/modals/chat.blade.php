@php
    $conversation = \App\Models\Conversation::with(['messages.author', 'participants'])->find($conversationId);
@endphp

<div class="w-full h-full max-h-[calc(80vh-140px)] flex flex-col">

    @if ($conversation)
        {{-- Contenedor del chat dentro del modal --}}
        <div
            class="flex-1 overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
            <livewire:chat-panel :conversation-id="$conversationId" :key="'chat-modal-' . $conversationId" />
        </div>
    @else
        {{-- Estado vacío dentro del modal --}}
        <div
            class="flex flex-col items-center justify-center h-[50vh] w-full text-center 
                    rounded-2xl border border-slate-200 dark:border-slate-800 
                    bg-white dark:bg-slate-900 shadow-sm px-6">

            <div class="text-slate-400 dark:text-slate-500 text-sm mt-2">
                No se ha encontrado la conversación
            </div>

            <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 mt-4 text-slate-300 dark:text-slate-600"
                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.985 9.985 0 01-4.244-.938L3 20l1.133-3.398A7.96 7.96 0 013 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
            </svg>
        </div>
    @endif

</div>
