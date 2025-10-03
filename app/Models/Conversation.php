<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class Conversation extends Model
{
    protected $fillable = [
        'subject',
        'is_broadcast',
        'role_id',
        'created_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withTimestamps()
            ->withPivot(['last_read_at']);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(\App\Models\Message::class);
    }

    public function scopeForUser(Builder $q, User $user): Builder
    {
        return $q->whereExists(function ($sub) use ($user) {
            $sub->from('conversation_participants as cp')
                ->select(DB::raw(1))
                ->whereColumn('cp.conversation_id', 'conversations.id')
                ->where('cp.user_id', $user->id);
        });
    }

    // (opcional, por id)
    public function scopeForUserId(Builder $q, int $userId): Builder
    {
        return $q->whereExists(function ($sub) use ($userId) {
            $sub->from('conversation_participants as cp')
                ->select(DB::raw(1))
                ->whereColumn('cp.conversation_id', 'conversations.id')
                ->where('cp.user_id', $userId);
        });
    }

    public function unreadCountFor(User $user): int
    {
        $lastRead = optional($this->participants()->where('user_id', $user->id)->first())->pivot?->last_read_at;
        return $this->messages()
            ->when($lastRead, fn($q) => $q->where('created_at', '>', $lastRead))
            ->where('user_id', '!=', $user->id)
            ->count();
    }
}
