@php
    $conversation = $conversationId
        ? \App\Models\Conversation::with(['messages.author', 'participants'])->find($conversationId)
        : null;
@endphp

<div class="mt-4">
    @if ($conversation)
        @include('livewire.chat-panel', ['conversation' => $conversation])
    @else
        <div class="text-gray-500 text-sm p-4 text-center">
            Selecciona una conversación para verla
        </div>
    @endif
</div>
