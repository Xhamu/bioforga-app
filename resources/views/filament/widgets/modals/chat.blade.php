@php
    $conversation = \App\Models\Conversation::with(['messages.author', 'participants'])->find($conversationId);
@endphp

<div class="h-[75vh] max-h-[75vh] flex flex-col">
    {{-- Cuerpo: reusa tu bloque “WhatsApp-like” --}}
    <div class="flex-1 overflow-hidden">
        <livewire:chat-panel :conversation-id="$conversationId" :key="'chat-modal-' . $conversationId" />
    </div>
</div>
