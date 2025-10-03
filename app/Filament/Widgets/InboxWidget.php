<?php

namespace App\Filament\Widgets;

use App\Models\Conversation;
use App\Services\ChatService;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as BaseBuilder;

class InboxWidget extends BaseWidget
{
    protected static ?string $heading = 'Mensajería interna';
    protected int|string|array $columnSpan = 'full';
    public ?int $openConversationId = null;

    protected static ?string $pollingInterval = '10s';

    public function table(Table $table): Table
    {
        $userId = auth()->id();

        return $table
            ->query(function () use ($userId): Builder {
                return Conversation::query()
                    ->join('conversation_participants as cp', 'cp.conversation_id', '=', 'conversations.id')
                    ->where('cp.user_id', $userId)
                    ->select('conversations.*')
                    ->selectSub(function (BaseBuilder $q) use ($userId) {
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
                    ->with([
                        'participants',
                        'messages' => fn($q) => $q->with('author')->latest(),
                    ])
                    ->latest('conversations.updated_at');
            })

            ->columns([
                Tables\Columns\TextColumn::make('user')
                    ->label('Usuario')
                    ->state(function ($record) use ($userId) {
                        $other = $record->participants->firstWhere('id', '!=', $userId);
                        return $other
                            ? trim("{$other->name} {$other->apellidos}")
                            : 'Usuario desconocido';
                    })
                    ->icon('heroicon-o-user')
                    ->searchable(query: function (Builder $query, string $search) {
                        $query->whereHas('participants', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('apellidos', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('ultimo_mensaje')
                    ->label('Último mensaje')
                    ->state(function ($record) use ($userId) {
                        $last = $record->messages->first(); // ya vienen ordenados por latest()
                        if (!$last)
                            return '—';

                        $author = $last->author;
                        $isMine = $author->id === $userId;
                        $name = $isMine ? 'Tú' : "{$author->name} {$author->apellidos}";
                        $hora = $last->created_at->setTimezone('Europe/Madrid')->format('d/m H:i');

                        return "{$name}: {$last->body} ({$hora})";
                    })
                    ->limit(70)
                    ->wrap()
                    ->visibleFrom('md'),

                Tables\Columns\BadgeColumn::make('unread_count')
                    ->label('No leídos')
                    ->colors([
                        'gray' => fn($state) => (int) $state === 0,
                        'danger' => fn($state) => (int) $state > 0,
                    ])
                    ->formatStateUsing(fn($state) => (int) $state > 0 ? $state : '-')
                    ->alignCenter(),
            ])

            ->recordAction('abrir')
            ->actions([
                Tables\Actions\Action::make('abrir')
                    ->label('Abrir')
                    ->icon('heroicon-m-chat-bubble-left-right')
                    ->modalHeading(fn($record) => 'Conversación con ' . $record->participants
                        ->where('id', '!=', auth()->id())
                        ->map(fn($u) => "{$u->name} {$u->apellidos}")
                        ->implode(', '))
                    ->modalWidth('xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->action(function ($record) {
                        if ($conv = Conversation::find($record->id)) {
                            app(ChatService::class)->markAsRead($conv, auth()->user());
                        }
                    })
                    ->modalContent(fn($record) => view('filament.widgets.modals.chat', [
                        'conversationId' => $record->id,
                    ])),
            ])

            ->striped()
            ->defaultSort('updated_at', 'desc')
            ->persistSortInSession()
            ->persistSearchInSession()
            ->emptyStateHeading('Sin conversaciones')
            ->emptyStateDescription('No tienes conversaciones aún.');
    }

    protected function subjectFromParticipants(Conversation $conv): string
    {
        $names = $conv->participants
            ->where('id', '!=', auth()->id())
            ->map(function ($p) {
                $name = (string) $p->name;
                $first = \Illuminate\Support\Str::of($name)->before(' ')->value();
                $lastInitial = strtoupper(\Illuminate\Support\Str::of($name)->after(' ')->substr(0, 1)->value());
                return trim($first) . ' ' . ($lastInitial ? $lastInitial . '.' : '');
            })
            ->take(3)
            ->implode(', ');

        return $names ?: 'Conversación';
    }
}
