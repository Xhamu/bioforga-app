<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class ChatService
{
    /** Inicia o recupera una conversación 1 a 1 (no importa el orden). */
    public function startDirect(User $from, User $to, ?string $subject = null): Conversation
    {
        if ($to->hasRole('superadmin')) {
            abort(403, 'No se puede enviar mensajes a superadmins.');
        }

        // ✅ Buscar conversación existente entre EXACTAMENTE estos dos usuarios
        $existing = Conversation::query()
            ->where('is_broadcast', false)
            ->whereHas('participants', fn($q) => $q->where('user_id', $from->id))
            ->whereHas('participants', fn($q) => $q->where('user_id', $to->id))
            ->withCount('participants')
            ->having('participants_count', 2)
            ->first();

        if ($existing) {
            return $existing;
        }

        // 🆕 Si no existe, crear una nueva
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

    /** Inicia o recupera una conversación 1 a 1 pero a un rol directo. */
    public function startBroadcast(User $from, Role $role, ?string $subject = null): void
    {
        if (!$from->hasAnyRole(['superadmin', 'administración'])) {
            abort(403, 'No autorizado para enviar a roles.');
        }

        // Todos los usuarios del rol, excepto superadmins y el emisor
        $targets = User::role($role->name)
            ->whereDoesntHave('roles', fn($q) => $q->where('name', 'superadmin'))
            ->where('id', '!=', $from->id)
            ->get();

        foreach ($targets as $user) {
            // Reutiliza o crea conversación directa
            $conv = $this->startDirect($from, $user, $subject);

            // Envía el mensaje directamente a esa conversación
            $this->sendMessage($conv, $from, $subject ?? 'Mensaje al rol ' . $role->name);
        }
    }

    public function broadcastToRole(User $from, Role $role, string $body): int
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
            // Reutiliza conversación si ya existe, sino crea una nueva
            $conv = $this->startDirect($from, $user);

            // Envía mensaje sin asunto
            $this->sendMessage($conv, $from, $body);
            $sent++;
        }

        return $sent;
    }

    /** Envía un mensaje dentro de una conversación (y notifica a receptores). */
    public function sendMessage(Conversation $conversation, User $from, string $body): Message
    {
        if (!$conversation->participants()->where('user_id', $from->id)->exists()) {
            abort(403, 'No puedes escribir en esta conversación.');
        }

        $msg = $conversation->messages()->create([
            'user_id' => $from->id,
            'body' => $body,
        ]);

        // Marca leído al autor
        $conversation->participants()->updateExistingPivot($from->id, ['last_read_at' => now()]);

        // Notifica a los demás
        $recipients = $conversation->participants()->where('user_id', '!=', $from->id)->get();
        foreach ($recipients as $user) {
            \Filament\Notifications\Notification::make()
                ->title('Nuevo mensaje')
                ->body(str($body)->limit(120))
                ->success()
                ->sendToDatabase($user); // aparece en la campanita de Filament
        }

        // (Opcional) broadcast para tiempo real si usas Echo:
        // broadcast(new \App\Events\MessageCreated($msg))->toOthers();

        return $msg;
    }

    /** Marca conversación como leída para un usuario. */
    public function markAsRead(Conversation $conversation, User $user): void
    {
        $conversation->participants()->updateExistingPivot($user->id, ['last_read_at' => now()]);
    }
}
