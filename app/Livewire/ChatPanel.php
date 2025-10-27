<?php

namespace App\Livewire;

use App\Models\Conversation;
use App\Services\ChatService;
use Livewire\Component;
use Livewire\WithFileUploads;

class ChatPanel extends Component
{
    use WithFileUploads;

    public ?int $conversationId = null;
    public string $message = '';
    public $photo = null; // TemporaryUploadedFile

    protected $listeners = ['open-chat-panel' => 'open'];

    public function mount(?int $conversationId = null): void
    {
        $this->conversationId = $conversationId;

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
        abort_unless(auth()->user()?->hasAnyRole(['superadmin', 'administración']), 403);

        $this->validate([
            'message' => 'required|string|min:1',
            'photo' => 'nullable|image|max:4096', // 4MB
        ]);

        $conv = Conversation::with('participants')->findOrFail($this->conversationId);
        abort_unless($conv->participants->contains('id', auth()->id()), 403);

        $attachments = [];
        if ($this->photo) {
            // Guarda en disco "public" dentro de /chat
            $path = $this->photo->store('chat', 'public');
            $attachments[] = $path;
        }

        $service->sendMessage($conv, auth()->user(), trim($this->message), $attachments ?: null);
        $service->markAsRead($conv, auth()->user());

        $this->reset('message', 'photo');

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
