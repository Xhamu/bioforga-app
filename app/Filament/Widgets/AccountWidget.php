<?php

namespace App\Filament\Widgets;

use App\Models\State;
use App\Models\UserStatus;
use Filament\Actions;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class AccountWidget extends Widget
{
    use InteractsWithActions;

    protected static string $view = 'filament.widgets.account-widget';
    protected int|string|array $columnSpan = 'full';

    // --- Estados ---
    public ?UserStatus $active = null;
    /** @var array<int, array{id:int,name:string,icon:?string,color:string}> */
    public array $states = [];

    public function mount(): void
    {
        $this->refreshData();
    }

    public function getUserFullName(): string
    {
        $user = auth()->user();
        return "{$user->name} {$user->apellidos}";
    }

    /** Mostrar controles de estados solo si es Bioforga y no tiene proveedor */
    public function canSeeStates(): bool
    {
        $u = Auth::user();
        if (!$u)
            return false;

        $isBioforga = (bool) ($u->empresa_bioforga ?? false);
        $hasProveedor = !is_null($u->proveedor_id);

        return $isBioforga && !$hasProveedor;
    }

    public function refreshData(): void
    {
        $this->active = Auth::user()?->activeStatus()->with('state')->first();

        $this->states = State::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'icon' => $s->icon,
                'color' => $s->color ?? 'gray',
            ])
            ->all();
    }

    public function start(int $stateId): void
    {
        $user = Auth::user();

        if ($user->activeStatus()->exists()) {
            Notification::make()->title('Ya tienes un estado activo.')->warning()->send();
            $this->refreshData();
            return;
        }

        $state = State::where('id', $stateId)->where('is_active', true)->first();
        if (!$state) {
            Notification::make()->title('Estado no disponible.')->danger()->send();
            return;
        }

        $user->statuses()->create([
            'state_id' => $stateId,
            'started_at' => now(),
        ]);

        Notification::make()->title("{$state->name} iniciado")->success()->send();
        $this->refreshData();
    }

    public function stop(): void
    {
        $active = Auth::user()?->activeStatus()->first();

        if (!$active) {
            Notification::make()->title('No hay estado activo.')->warning()->send();
            return;
        }

        $active->update(['ended_at' => now()]);

        Notification::make()->title('Estado finalizado')->success()->send();
        $this->refreshData();
    }

    /** Cuenta de no leídos para la badget del botón */
    public function getUnreadCount(): int
    {
        $userId = Auth::id();

        return (int) \DB::table('conversations')
            ->join('conversation_participants as cp', 'cp.conversation_id', '=', 'conversations.id')
            ->where('cp.user_id', $userId)
            ->selectSub(function ($q) use ($userId) {
                $q->from('messages')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('messages.conversation_id', 'conversations.id')
                    ->where('messages.user_id', '!=', $userId)
                    ->whereRaw(
                        'messages.created_at > COALESCE((
                            SELECT cp2.last_read_at
                            FROM conversation_participants cp2
                            WHERE cp2.conversation_id = conversations.id
                              AND cp2.user_id = ?
                        ), "1970-01-01 00:00:00")',
                        [$userId]
                    );
            }, 'unread_count')
            ->pluck('unread_count')
            ->sum();
    }

    public function markAsRead(int $conversationId): void
    {
        if ($conv = \App\Models\Conversation::find($conversationId)) {
            app(\App\Services\ChatService::class)->markAsRead($conv, auth()->user());
        }
    }

    /** Enviar mensaje rápido desde el modal */
    public function sendMessage(int $conversationId, string $body): void
    {
        $body = trim($body);
        if ($body === '')
            return;

        $conv = \App\Models\Conversation::find($conversationId);
        if (!$conv)
            return;

        \App\Models\Message::create([
            'conversation_id' => $conv->id,
            'user_id' => auth()->id(),
            'body' => $body,
        ]);

        // Empuja updated_at y (opcional) marcar propio como leído
        $conv->touch();
    }

    protected function getViewData(): array
    {
        return [
            'active' => $this->active,
            'states' => $this->states,
        ];
    }
}
