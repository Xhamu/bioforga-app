<?php

namespace App\Livewire;

use App\Models\Conversation;
use App\Services\ChatService;
use Livewire\Component;

class ChatPanel extends Component
{
    public ?int $conversationId = null;
    public string $message = '';

    protected $listeners = ['open-chat-panel' => 'open'];

    public function mount(?int $conversationId = null): void
    {
        $this->conversationId = $conversationId;

        // 🧭 Si no se seleccionó conversación, abre la más reciente del usuario
        if (!$this->conversationId) {
            $this->conversationId = Conversation::query()
                ->join('conversation_participants as cp', 'cp.conversation_id', '=', 'conversations.id')
                ->where('cp.user_id', auth()->id())
                ->orderByDesc('conversations.updated_at')
                ->value('conversations.id');
        }
    }

    public function open(int $id): void
    {
        $this->conversationId = $id;
        $this->markRead();
        $this->dispatch('$refresh');
    }

    public function send(ChatService $service)
    {
        $this->validate(['message' => 'required|string|min:1']);

        $conv = Conversation::with('participants')->findOrFail($this->conversationId);

        abort_unless($conv->participants->contains('id', auth()->id()), 403);

        $service->sendMessage($conv, auth()->user(), trim($this->message));
        $service->markAsRead($conv, auth()->user());

        $this->reset('message');

        $this->dispatch('chat:scroll-bottom');
        $this->dispatch('$refresh');
    }

    public function markRead(): void
    {
        if (!$this->conversationId)
            return;

        if ($conv = Conversation::find($this->conversationId)) {
            app(ChatService::class)->markAsRead($conv, auth()->user());
        }
    }

    public function getConversationProperty()
    {
        if (!$this->conversationId)
            return null;

        return Conversation::with([
            'participants',
            'messages' => fn($q) => $q->orderBy('id', 'asc'),
            'messages.author',
        ])->find($this->conversationId);
    }

    public function render()
    {
        return view('livewire.chat-panel', [
            'conversation' => $this->conversation,
        ]);
    }
}
