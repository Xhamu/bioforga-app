<?php

namespace App\Filament\Pages;

use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Spatie\Permission\Models\Role;
use App\Services\ChatService;
use Filament\Forms\Get;
use App\Models\User;
use Filament\Forms\Components\FileUpload;

class EnviarMensajeARol extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';
    protected static ?string $navigationLabel = 'Mensaje a Rol';
    protected static ?string $navigationGroup = 'Mensajería';
    protected static ?int $navigationSort = 4;
    protected static string $view = 'filament.pages.enviar-mensaje-a-rol';

    /** 
     * Estado del formulario (role_id, body, attachments, etc.)
     * Filament lo gestionará todo dentro de este array.
     */
    public ?array $data = [];

    /** 🔒 Ocultar del menú si no tiene rol permitido */
    public static function shouldRegisterNavigation(): bool
    {
        $user = Filament::auth()->user();
        return $user?->hasAnyRole(['superadmin', 'administración']) ?? false;
    }

    /** 🔒 Bloquear acceso directo por URL */
    public function mount(): void
    {
        $user = Filament::auth()->user();
        if (!$user?->hasAnyRole(['superadmin', 'administración'])) {
            abort(403);
        }

        // Rellenar el form con valores por defecto (vacíos)
        $this->form->fill();
    }

    /** Definir el form de Filament */
    public function form(Form $form): Form
    {
        return $form
            ->schema($this->getFormSchema())
            ->statePath('data'); // todo el estado irá en $this->data
    }

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

                    FileUpload::make('attachments')
                        ->label('Adjuntar archivos')
                        ->multiple()
                        ->disk('public')              // apunta a public_path('archivos')
                        ->directory('chat/adjuntos')  // => public/archivos/chat/adjuntos
                        ->preserveFilenames()
                        ->openable()
                        ->downloadable()
                        ->acceptedFileTypes([
                            // PDF
                            'application/pdf',
                            // Word
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            // Imágenes
                            'image/*',
                        ])
                        ->helperText('PDF, Word o imágenes (JPG, PNG, etc.)'),
                ]),
        ];
    }

    public function submit(): void
    {
        abort_unless(auth()->user()->hasAnyRole(['superadmin', 'administración']), 403);

        // Estado actual del formulario (tal como hace la Action en UserResource)
        $data = $this->form->getState();

        $roleId = $data['role_id'] ?? null;
        $body = $data['body'] ?? null;

        if (!$roleId || !$body) {
            // Por si acaso, aunque ya está validado por "required"
            return;
        }

        $role = Role::findOrFail($roleId);
        $svc = app(ChatService::class);

        // Normalizar adjuntos igual que en UserResource
        $attachments = collect($data['attachments'] ?? [])
            ->map(function ($item) {
                // Por si viene en formato ['path' => '...']
                if (is_array($item) && isset($item['path'])) {
                    return $item['path'];
                }
                return $item;
            })
            ->filter()
            ->values()
            ->all();

        // 📩 Enviar a cada usuario del rol (mensaje + adjuntos)
        $sentCount = $svc->broadcastToRole(
            auth()->user(),
            $role,
            $body,
            $attachments ?: null, // firma ?array en ChatService
        );

        \Filament\Notifications\Notification::make()
            ->title("Mensaje enviado a {$sentCount} usuario(s) del rol {$role->name}")
            ->success()
            ->send();

        // Reset del formulario
        $this->reset('data');
        $this->form->fill();
    }
}
