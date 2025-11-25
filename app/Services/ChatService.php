<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class ChatService
{
    public function startDirect(User $from, User $to, ?string $subject = null): Conversation
    {
        if ($to->hasRole('superadmin')) {
            abort(403, 'No se puede enviar mensajes a superadmins.');
        }

        $existing = Conversation::query()
            ->where('is_broadcast', false)
            ->whereHas('participants', fn($q) => $q->where('user_id', $from->id))
            ->whereHas('participants', fn($q) => $q->where('user_id', $to->id))
            ->withCount('participants')
            ->having('participants_count', 2)
            ->first();

        if ($existing)
            return $existing;

        return DB::transaction(function () use ($from, $to, $subject) {
            $conv = Conversation::create([
                'subject' => $subject,
                'is_broadcast' => false,
                'created_by' => $from->id,
            ]);

            $conv->participants()->attach([$from->id, $to->id]);

            return $conv->refresh();
        });
    }

    public function startBroadcast(User $from, Role $role, ?string $subject = null): void
    {
        if (!$from->hasAnyRole(['superadmin', 'administración'])) {
            abort(403, 'No autorizado para enviar a roles.');
        }

        $targets = User::role($role->name)
            ->whereDoesntHave('roles', fn($q) => $q->where('name', 'superadmin'))
            ->where('id', '!=', $from->id)
            ->get();

        foreach ($targets as $user) {
            $conv = $this->startDirect($from, $user, $subject);
            $this->sendMessage($conv, $from, $subject ?? 'Mensaje al rol ' . $role->name, null);
        }
    }

    public function broadcastToRole(User $from, Role $role, string $body, ?array $attachments = null): int
    {
        if (!$from->hasAnyRole(['superadmin', 'administración'])) {
            abort(403, 'No autorizado para enviar a roles.');
        }

        $targets = User::role($role->name)
            ->whereDoesntHave('roles', fn($q) => $q->where('name', 'superadmin'))
            ->where('id', '!=', $from->id)
            ->get();

        $sent = 0;
        foreach ($targets as $user) {
            $conv = $this->startDirect($from, $user);
            $this->sendMessage($conv, $from, $body, $attachments ?: null);
            $sent++;
        }

        return $sent;
    }

    /**
     * @param string[]|null $attachments Rutas relativas en el disco público (p.ej. ["chat/abc.jpg"])
     */
    public function sendMessage(Conversation $conversation, User $from, string $body, ?array $attachments = null): Message
    {
        if (!$from->hasAnyRole(['superadmin', 'administración'])) {
            abort(403, 'No estás autorizado para enviar mensajes.');
        }

        if (!$conversation->participants()->where('user_id', $from->id)->exists()) {
            abort(403, 'No puedes escribir en esta conversación.');
        }

        $msg = $conversation->messages()->create([
            'user_id' => $from->id,
            'body' => $body,
            'attachments' => $attachments ?: [],
        ]);

        // Marca leído al autor
        $conversation->participants()->updateExistingPivot($from->id, ['last_read_at' => now()]);

        // Mostrar rol en notificación
        $roleLabel = $from->getRoleNames()->first() ?? 'Usuario';

        $recipients = $conversation->participants()->where('user_id', '!=', $from->id)->get();
        foreach ($recipients as $user) {
            \Filament\Notifications\Notification::make()
                ->title("Nuevo mensaje de {$roleLabel}")
                ->body(str($body)->limit(120))
                ->success()
                ->sendToDatabase($user);
        }

        return $msg;
    }

    public function markAsRead(Conversation $conversation, User $user): void
    {
        $conversation->participants()->updateExistingPivot($user->id, ['last_read_at' => now()]);
    }
}
