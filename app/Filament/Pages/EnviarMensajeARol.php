<?php

// app/Filament/Pages/EnviarMensajeARol.php
namespace App\Filament\Pages;

use Filament\Forms;
use Filament\Pages\Page;
use Spatie\Permission\Models\Role;
use App\Services\ChatService;
use Filament\Forms\Get;
use Filament\Forms\Components;
use App\Models\User;

class EnviarMensajeARol extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';
    protected static ?string $navigationLabel = 'Mensaje a Rol';
    protected static string $view = 'filament.pages.enviar-mensaje-a-rol';

    public ?int $role_id = null;
    public ?string $body = null;

    protected function recipientsQuery(?int $roleId)
    {
        if (!$roleId) {
            return User::query()->whereRaw('1=0');
        }

        $role = Role::find($roleId);
        if (!$role) {
            return User::query()->whereRaw('1=0');
        }

        return User::role($role->name)
            ->where('id', '!=', auth()->id())
            ->whereDoesntHave('roles', fn($q) => $q->where('name', 'superadmin'));
    }

    protected function recipientsCount(?int $roleId): int
    {
        return $this->recipientsQuery($roleId)->count();
    }

    protected function recipientsPreview(?int $roleId): string
    {
        if (!$roleId) {
            return '—';
        }

        $names = $this->recipientsQuery($roleId)
            ->get()
            ->map(function ($u) {
                $name = trim($u->name);
                $apellidos = trim($u->apellidos ?? '');
                $inicial = $apellidos ? strtoupper(mb_substr($apellidos, 0, 1)) . '.' : '';
                return "{$name} {$inicial}";
            })
            ->implode(', ');

        return $names ?: '—';
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Destino')
                ->description('Selecciona el rol destinatario.')
                ->schema([
                    Forms\Components\Select::make('role_id')
                        ->label('Rol')
                        ->options(
                            Role::query()
                                ->where('name', '!=', 'superadmin')
                                ->orderBy('name')
                                ->pluck('name', 'id')
                        )
                        ->searchable()
                        ->preload()
                        ->required()
                        ->reactive(),

                    Forms\Components\Placeholder::make('recipients_count')
                        ->label('Usuarios destino')
                        ->content(fn(Get $get) => $this->recipientsCount($get('role_id')) . ' usuario(s)'),

                    Forms\Components\Placeholder::make('recipients_preview')
                        ->label('Destinatarios')
                        ->content(fn(Get $get) => $this->recipientsPreview($get('role_id'))),
                ])
                ->columns(3),

            Forms\Components\Section::make('Mensaje')
                ->schema([
                    Forms\Components\Textarea::make('body')
                        ->label('Mensaje')
                        ->rows(4)
                        ->autosize()
                        ->maxLength(2000)
                        ->placeholder('Escribe el mensaje que recibirán los miembros del rol seleccionado…')
                        ->required(),
                ]),
        ];
    }


    public function submit(): void
    {
        abort_unless(auth()->user()->hasAnyRole(['superadmin', 'administración']), 403);

        $role = Role::findOrFail($this->role_id);
        $svc = app(ChatService::class);

        // 📩 Enviar a cada usuario del rol (sin asunto)
        $sentCount = $svc->broadcastToRole(
            auth()->user(),
            $role,
            $this->body
        );

        \Filament\Notifications\Notification::make()
            ->title("Mensaje enviado a {$sentCount} usuario(s) del rol {$role->name}")
            ->success()
            ->send();

        $this->reset(['role_id', 'body']);
    }
}
