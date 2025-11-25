<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\Pages\ListUserActivities;
use App\Models\Maquina;
use App\Models\Referencia;
use App\Models\User;
use App\Models\Vehiculo;
use App\Services\ChatService;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Jenssegers\Agent\Agent;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-user';
    protected static ?string $slug = 'usuarios';
    public static ?string $label = 'usuario';
    public static ?string $pluralLabel = 'Usuarios';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make('Formulario')->tabs([
                Tabs\Tab::make('Información')
                    ->schema([
                        Section::make('Datos personales')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nombre')
                                    ->rules('required')
                                    ->required()
                                    ->columnSpan(1)
                                    ->validationMessages([
                                        'required' => 'El :attribute es obligatorio.',
                                    ]),

                                TextInput::make('apellidos')
                                    ->label('Apellidos')
                                    ->rules('required')
                                    ->required()
                                    ->columnSpan(2)
                                    ->validationMessages([
                                        'required' => 'Los :attribute son obligatorios.',
                                    ]),

                                TextInput::make('nif')
                                    ->label('NIF')
                                    ->rules('required')
                                    ->required()
                                    ->columnSpan(1)
                                    ->validationMessages([
                                        'required' => 'El NIF es obligatorio.',
                                    ]),

                                TextInput::make('email')
                                    ->label('Correo electrónico')
                                    ->email()
                                    ->required()
                                    ->rules('required')
                                    ->columnSpan(1)
                                    ->validationMessages([
                                        'email' => 'No es :attribute válido.',
                                        'required' => 'El correo electrónico es obligatorio.',
                                    ]),

                                TextInput::make('telefono')
                                    ->label('Teléfono')
                                    ->nullable()
                                    ->columnSpan(1),
                            ])
                            ->columns([
                                'sm' => 1,
                                'md' => 3,
                            ]),

                        Section::make('Empresa')
                            ->schema([
                                Checkbox::make('empresa_bioforga')
                                    ->label('BIOFORGA')
                                    ->default(true)
                                    ->reactive(),

                                Select::make('proveedor_id')
                                    ->label('Otro/a')
                                    ->searchable()
                                    ->preload()
                                    ->options(fn() => \App\Models\Proveedor::all()->pluck('razon_social', 'id')->toArray())
                                    ->visible(fn($get) => !$get('empresa_bioforga')),
                            ])
                            ->columns([
                                'sm' => 1,
                                'md' => 2,
                            ]),

                        Section::make('Sector')
                            ->schema([
                                Select::make('sector') // ← plural
                                    ->label('')
                                    ->options([
                                        '01' => 'Zona Norte Galicia',
                                        '02' => 'Zona Sur Galicia',
                                        '03' => 'Andalucía Oriental',
                                        '04' => 'Andalucía Occidental y Sur Portugal',
                                        '05' => 'Otros',
                                    ])
                                    ->multiple()          // ← clave
                                    ->searchable()
                                    ->preload()
                                    ->nullable()
                                    ->columnSpan(1),
                            ])
                            ->visible(fn($livewire) => $livewire->record?->hasRole('técnico') ?? true),

                        Section::make('Acceso')
                            ->schema([
                                TextInput::make('password')
                                    ->label('Contraseña')
                                    ->password()
                                    ->rules(fn($get) => $get('id') ? 'nullable' : 'required')
                                    ->default(fn($get) => $get('password') ? '******' : '')
                                    ->autocomplete('new-password')
                                    ->validationMessages([
                                        'required' => 'La :attribute es obligatoria.',
                                    ]),

                                Select::make('roles')
                                    ->relationship('roles', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->searchable()
                                    ->options(
                                        \Spatie\Permission\Models\Role::where('name', '!=', 'superadmin')->pluck('name', 'id')
                                    ),

                                Toggle::make('is_blocked')
                                    ->label('🔒 Usuario bloqueado')
                                    ->onColor('danger')
                                    ->offColor('gray') // o puedes quitar este si no quieres color
                                    ->onIcon('heroicon-o-no-symbol')
                                    ->offIcon(null)
                                    ->helperText('Si está activado, el usuario no podrá iniciar sesión.')
                                    ->default(false),
                            ])
                    ]),

                Tabs\Tab::make('Vinculaciones')->schema([
                    // REFERENCIAS
                    Select::make('referencias')
                        ->label('Referencias vinculadas')
                        ->relationship(
                            name: 'referencias',
                            titleAttribute: 'referencia',
                            modifyQueryUsing: fn($query) => $query->whereNull('referencias.deleted_at')
                        )
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->getOptionLabelFromRecordUsing(function (Referencia $referencia) {
                            $razon = $referencia->proveedor?->razon_social
                                ?? $referencia->cliente?->razon_social
                                ?? 'Sin interviniente';

                            $ubicacion = trim("{$referencia->monte_parcela}, {$referencia->ayuntamiento}");
                            return "{$referencia->referencia} ({$ubicacion}) | {$razon}";
                        })
                        ->columnSpanFull(),

                    // CAMIONES
                    Select::make('camiones')
                        ->label('Camiones vinculados')
                        ->relationship(
                            name: 'camiones',
                            titleAttribute: 'marca',
                            modifyQueryUsing: fn($query) => $query->whereNull('camiones.deleted_at') // Especificamos la tabla
                        )
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->getOptionLabelFromRecordUsing(function (\App\Models\Camion $camion) {
                            return "[{$camion->matricula_cabeza}] {$camion->marca} {$camion->modelo}";
                        })
                        ->columnSpanFull(),
                ]),

                Tabs\Tab::make('Historial de estados')->schema([
                    Forms\Components\Section::make()
                        ->columnSpanFull()
                        ->schema([
                            Forms\Components\Placeholder::make('tabla_historial_estados')
                                ->label('')
                                ->content(function ($record) {
                                    /** @var \App\Models\User|null $record */
                                    if (!$record) {
                                        return new HtmlString(
                                            '<p class="text-gray-500">No hay usuario cargado.</p>'
                                        );
                                    }

                                    $tz = 'Europe/Madrid';

                                    $items = $record->statuses()
                                        ->with('state')
                                        ->orderByDesc('started_at')
                                        ->orderByDesc('id')
                                        ->get();

                                    if ($items->isEmpty()) {
                                        return new HtmlString(
                                            '<p class="text-gray-500">Sin historial de estados.</p>'
                                        );
                                    }

                                    $fmtDate = function ($dt) use ($tz) {
                                        return $dt ? $dt->copy()->timezone($tz)->format('d/m/Y H:i') : '—';
                                    };

                                    $fmtDur = function ($start, $end) use ($tz) {
                                        if (!$start)
                                            return '—';
                                        $start = $start->copy()->timezone($tz);
                                        $end = ($end ?? Carbon::now($tz))->copy()->timezone($tz);
                                        $secs = $start->diffInSeconds($end); // siempre positivo
                                        $d = intdiv($secs, 86400);
                                        $secs %= 86400;
                                        $h = intdiv($secs, 3600);
                                        $m = intdiv($secs % 3600, 60);
                                        return $d > 0
                                            ? sprintf('%dd %02dh %02dm', $d, $h, $m)
                                            : sprintf('%02dh %02dm', $h, $m);
                                    };

                                    $rowsHtml = '';
                                    foreach ($items as $it) {
                                        /** @var \App\Models\UserStatus $it */
                                        $estado = e($it->state?->name ?? '—');
                                        $ini = $fmtDate($it->started_at);
                                        $fin = $fmtDate($it->ended_at);
                                        $dur = $fmtDur($it->started_at, $it->ended_at);
                                        $activo = $it->ended_at ? 'Finalizado' : 'Activo';

                                        // ✅ calcula la clase fuera del heredoc
                                        $statusClass = $it->ended_at ? 'bg-gray-100 text-gray-800' : 'bg-emerald-100 text-emerald-800';

                                        $rowsHtml .= <<<HTML
                                        <tr class="border-b last:border-0">
                                          <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">{$estado}</td>
                                          <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-700">{$ini}</td>
                                          <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-700">{$fin}</td>
                                          <td class="px-3 py-2 whitespace-nowrap text-sm tabular-nums text-gray-900">{$dur}</td>
                                          <td class="px-3 py-2 whitespace-nowrap text-xs">
                                            <span class="inline-flex items-center rounded-md px-2 py-1 font-medium {$statusClass}">
                                              {$activo}
                                            </span>
                                          </td>
                                        </tr>
                                        HTML;
                                    }


                                    $table = <<<HTML
                                    <div class="overflow-x-auto">
                                      <table class="min-w-full border-separate border-spacing-0 rounded-lg overflow-hidden">
                                        <thead>
                                          <tr class="bg-gray-50 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                                            <th class="px-3 py-2">Estado</th>
                                            <th class="px-3 py-2">Inicio</th>
                                            <th class="px-3 py-2">Fin</th>
                                            <th class="px-3 py-2">Duración</th>
                                            <th class="px-3 py-2">Situación</th>
                                          </tr>
                                        </thead>
                                        <tbody class="bg-white">{$rowsHtml}</tbody>
                                      </table>
                                    </div>
                                    HTML;

                                    return new HtmlString($table);
                                })
                        ])
                ]),

            ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {

        $agent = new Agent();

        if ($agent->isMobile()) {
            return $table
                ->defaultGroup('proveedor.razon_social')
                ->columns([
                    TextColumn::make('nombre_apellidos')
                        ->label('Nombre')
                        ->weight(FontWeight::Bold)
                        ->searchable(query: function ($query, $search) {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('apellidos', 'like', "%{$search}%");
                        }),

                    Panel::make([
                        Grid::make(['default' => 1, 'md' => 2]) // 1 columna en móvil, 2 en pantallas medianas y grandes
                            ->schema([
                                Stack::make([
                                    TextColumn::make('nif')
                                        ->label('NIF')
                                        ->searchable()
                                        ->icon('heroicon-m-identification'),
                                    TextColumn::make('telefono')
                                        ->label('Teléfono')
                                        ->searchable()
                                        ->icon('heroicon-m-phone'),

                                    BadgeColumn::make('is_blocked')
                                        ->label('Estado')
                                        ->formatStateUsing(fn($state) => $state == 1 ? 'Bloqueado' : 'Activo')
                                        ->colors([
                                            'danger' => fn($state) => $state == 1,
                                            'success' => fn($state) => $state == 0,
                                        ]),
                                ]),

                            ]),
                    ])->collapsed(false),
                ])
                ->persistFiltersInSession()
                ->filters(
                    [
                        TernaryFilter::make('empresa_bioforga')
                            ->label('Empresa')
                            ->trueLabel('Bioforga')
                            ->falseLabel('Proveedor externo')
                            ->placeholder('Todos')
                            ->native(false),

                        SelectFilter::make('roles')
                            ->relationship(
                                'roles',
                                'name',
                                fn(\Illuminate\Database\Eloquent\Builder $query) =>
                                $query->where('name', '!=', 'superadmin')
                            )
                            ->multiple()
                            ->preload(),
                    ],
                    layout: FiltersLayout::AboveContentCollapsible
                )
                ->filtersFormColumns(2)
                ->actions([
                    \STS\FilamentImpersonate\Tables\Actions\Impersonate::make()
                        ->label('Impersonar')
                        ->after(function ($record) {
                            $suplantador = auth()->user();
                            $suplantado = $record;

                            activity()
                                ->causedBy($suplantador)
                                ->performedOn($suplantado)
                                ->withProperties([
                                    'impersonator_id' => $suplantador->id,
                                    'impersonator_name' => $suplantador->nombre_apellidos ?? $suplantador->name,
                                    'impersonated_id' => $suplantado->id,
                                    'impersonated_name' => $suplantado->nombre_apellidos ?? $suplantado->name,
                                    'ip' => request()->ip(),
                                    'user_agent' => request()->userAgent(),
                                ])
                                ->log('impersonation');
                        }),

                    Tables\Actions\EditAction::make(),

                    Tables\Actions\DeleteAction::make()
                        ->before(function ($record, $action) {
                            // Máquinas vinculadas
                            $maquinas = Maquina::where('operario_id', $record->id)->get();

                            if ($maquinas->isNotEmpty()) {
                                $listaMaquinas = $maquinas
                                    ->map(fn($m) => "<li><strong>{$m->marca} {$m->modelo}</strong></li>")
                                    ->implode('');

                                Notification::make()
                                    ->title('No se puede eliminar')
                                    ->danger()
                                    ->icon('heroicon-o-exclamation-circle')
                                    ->body(new HtmlString(
                                        "Este usuario está asignado como operario en las siguientes máquinas:<br><ul>{$listaMaquinas}</ul>No se puede eliminar mientras tenga estas vinculaciones."
                                    ))
                                    ->duration(10000)
                                    ->send();

                                $action->cancel();
                                return;
                            }

                            // Vehículos vinculados
                            $vehiculos = Vehiculo::whereJsonContains('conductor_habitual', (string) $record->id)->get();

                            if ($vehiculos->isNotEmpty()) {
                                $listaVehiculos = $vehiculos
                                    ->map(fn($v) => "<li><strong>{$v->marca} {$v->modelo} ({$v->matricula})</strong></li>")
                                    ->implode('');

                                Notification::make()
                                    ->title('No se puede eliminar')
                                    ->icon('heroicon-o-exclamation-circle')
                                    ->danger()
                                    ->body(new HtmlString(
                                        "Este usuario figura como conductor habitual en los siguientes vehículos:<br><ul>{$listaVehiculos}</ul>No se puede eliminar mientras esté asignado."
                                    ))
                                    ->duration(10000)
                                    ->send();

                                $action->cancel();
                            }
                        })
                        ->requiresConfirmation()
                        ->modalHeading('¿Estás seguro de que quieres eliminar este usuario?')
                        ->modalDescription('Esta acción no se puede deshacer. Asegúrate de que el usuario no tenga datos relacionados.'),

                    Action::make('activities')
                        ->label('Actividad')
                        ->icon('heroicon-o-clock')
                        ->url(fn($record) => static::getUrl('activities', ['record' => $record]))
                        ->openUrlInNewTab()
                        ->visible(fn() => auth()->user()?->hasRole('superadmin')),
                ])
                ->bulkActions([
                    Tables\Actions\BulkActionGroup::make([
                        Tables\Actions\DeleteBulkAction::make(),
                    ]),
                ])
                ->paginated(true)
                ->paginationPageOptions([50, 100, 200])
                ->defaultSort('created_at', 'desc');
        } else {
            return $table
                ->modifyQueryUsing(
                    fn(\Illuminate\Database\Eloquent\Builder $query) =>
                    $query->with(['activeStatus.state', 'proveedor'])
                )
                ->defaultGroup('proveedor.razon_social')
                ->columns([
                    TextColumn::make('nombre_apellidos')
                        ->label('Nombre')
                        ->weight(FontWeight::Bold)
                        ->description(fn(User $record) => 'NIF: ' . $record->nif, position: 'below')
                        ->icon('heroicon-m-user')
                        ->searchable(query: function ($query, $search) {
                            $query->where(
                                fn($q) => $q
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhere('apellidos', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%")
                            );
                        }),

                    TextColumn::make('email')
                        ->label('Email')
                        ->icon('heroicon-m-envelope'),

                    TextColumn::make('telefono')
                        ->label('Teléfono')
                        ->icon('heroicon-m-phone'),

                    BadgeColumn::make('estado_actual')
                        ->label('Estado')
                        ->getStateUsing(fn(User $record) => $record->activeStatus?->state?->name ?? 'Sin estado')
                        ->icon(fn(User $record) => $record->activeStatus?->state?->icon)
                        ->color(fn(User $record) => $record->activeStatus?->state?->color ?? 'gray')
                        ->tooltip(function (User $record) {
                            if (!$record->activeStatus)
                                return null;
                            $started = $record->activeStatus->started_at?->timezone('Europe/Madrid');
                            if (!$started)
                                return null;
                            $diff = now()->diff($started);
                            $h = str_pad($diff->h + $diff->d * 24, 2, '0', STR_PAD_LEFT);
                            $m = str_pad($diff->i, 2, '0', STR_PAD_LEFT);
                            $s = str_pad($diff->s, 2, '0', STR_PAD_LEFT);
                            return 'Desde ' . $started->format('d/m/Y H:i') . " ({$h}h {$m}min)";
                        })
                        ->sortable(false)
                        ->searchable(false)
                        ->alignCenter(),

                    BadgeColumn::make('is_blocked')
                        ->label('Estado de acceso')
                        ->formatStateUsing(fn($state) => $state == 1 ? 'Bloqueado' : 'Activo')
                        ->colors([
                            'danger' => fn($state) => $state == 1,
                            'success' => fn($state) => $state == 0,
                        ])
                        ->tooltip(fn($state) => $state == 1 ? 'Usuario bloqueado' : 'Usuario activo')
                        ->alignCenter(),
                ])
                ->striped()
                ->persistFiltersInSession()
                ->filters(
                    [
                        TernaryFilter::make('empresa_bioforga')
                            ->label('Empresa')
                            ->trueLabel('Bioforga')
                            ->falseLabel('Proveedor externo')
                            ->placeholder('Todos')
                            ->native(false),

                        SelectFilter::make('roles')
                            ->relationship(
                                'roles',
                                'name',
                                fn(\Illuminate\Database\Eloquent\Builder $query) =>
                                $query->where('name', '!=', 'superadmin')
                            )
                            ->multiple()
                            ->preload(),

                        Tables\Filters\SelectFilter::make('is_blocked')
                            ->label('Estado de acceso')
                            ->searchable()
                            ->options([
                                '0' => 'Activo',
                                '1' => 'Bloqueado',
                            ]),
                    ],
                    layout: FiltersLayout::AboveContentCollapsible
                )
                ->filtersFormColumns(3)
                ->headerActions([
                    Action::make('exportar_partes_trabajo')
                        ->label('Exportar partes de trabajo')
                        ->action(function () {
                            return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\PartesTrabajoPorUsuarioExport, 'partes_trabajo.xlsx');
                        })
                        ->icon('heroicon-m-arrow-down-tray')
                        ->color('gray'),
                ])
                ->actions([
                    \STS\FilamentImpersonate\Tables\Actions\Impersonate::make()
                        ->label('Impersonar')
                        ->after(function ($record) {
                            $suplantador = auth()->user();
                            $suplantado = $record;

                            activity()
                                ->causedBy($suplantador)
                                ->performedOn($suplantado)
                                ->withProperties([
                                    'impersonator_id' => $suplantador->id,
                                    'impersonator_name' => $suplantador->nombre_apellidos ?? $suplantador->name,
                                    'impersonated_id' => $suplantado->id,
                                    'impersonated_name' => $suplantado->nombre_apellidos ?? $suplantado->name,
                                    'ip' => request()->ip(),
                                    'user_agent' => request()->userAgent(),
                                ])
                                ->log('impersonation');
                        }),

                    Action::make('Enviar mensaje')
                        ->label('Mensaje')
                        ->icon('heroicon-o-paper-airplane')
                        ->form([
                            Forms\Components\Textarea::make('body')
                                ->label('Mensaje')
                                ->required()
                                ->rows(4),

                            FileUpload::make('attachments')
                                ->label('Adjuntar archivos')
                                ->multiple()
                                ->disk('public')                  // apunta a public_path('archivos')
                                ->directory('chat/adjuntos')      // => public/archivos/chat/adjuntos
                                ->preserveFilenames()
                                ->acceptedFileTypes([
                                    // PDF
                                    'application/pdf',
                                    // Word
                                    'application/msword',
                                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                    // Imágenes
                                    'image/*',
                                ])
                                ->downloadable()
                                ->openable()
                                ->helperText('PDF, Word o imágenes (JPG, PNG, etc.)'),
                        ])
                        ->action(function (array $data, User $record) {
                            /** @var \App\Services\ChatService $service */
                            $service = app(ChatService::class);

                            // Inicia (o recupera) la conversación directa
                            $conv = $service->startDirect(auth()->user(), $record);

                            // Normalizar adjuntos a array de strings (rutas relativas en el disk public)
                            $attachments = collect($data['attachments'] ?? [])
                                ->map(function ($item) {
                                // Por si algún disk devuelve ['path' => '...']
                                if (is_array($item) && isset($item['path'])) {
                                    return $item['path'];
                                }
                                return $item;
                            })
                                ->filter()
                                ->values()
                                ->all();

                            // En tu ChatService: attachments se guardan tal cual (p.ej. "chat/adjuntos/archivo.png")
                            $service->sendMessage(
                                $conv,
                                auth()->user(),
                                $data['body'],
                                $attachments ?: null,
                            );

                            Notification::make()
                                ->title('Mensaje enviado')
                                ->success()
                                ->send();
                        })
                        ->modalHeading(fn(User $record) => 'Mensaje a ' . ($record->nombre_apellidos ?? $record->name)),

                    ActionGroup::make([
                        Tables\Actions\EditAction::make(),

                        Tables\Actions\DeleteAction::make()
                            ->before(function ($record, $action) {
                                // Máquinas vinculadas
                                $maquinas = Maquina::where('operario_id', $record->id)->get();

                                if ($maquinas->isNotEmpty()) {
                                    $listaMaquinas = $maquinas
                                        ->map(fn($m) => "<li><strong>{$m->marca} {$m->modelo}</strong></li>")
                                        ->implode('');

                                    Notification::make()
                                        ->title('No se puede eliminar')
                                        ->danger()
                                        ->icon('heroicon-o-exclamation-circle')
                                        ->body(new HtmlString(
                                            "Este usuario está asignado como operario en las siguientes máquinas:<br><ul>{$listaMaquinas}</ul>No se puede eliminar mientras tenga estas vinculaciones."
                                        ))
                                        ->duration(10000)
                                        ->send();

                                    $action->cancel();
                                    return;
                                }

                                // Vehículos vinculados
                                $vehiculos = Vehiculo::whereJsonContains('conductor_habitual', (string) $record->id)->get();

                                if ($vehiculos->isNotEmpty()) {
                                    $listaVehiculos = $vehiculos
                                        ->map(fn($v) => "<li><strong>{$v->marca} {$v->modelo} ({$v->matricula})</strong></li>")
                                        ->implode('');

                                    Notification::make()
                                        ->title('No se puede eliminar')
                                        ->icon('heroicon-o-exclamation-circle')
                                        ->danger()
                                        ->body(new HtmlString(
                                            "Este usuario figura como conductor habitual en los siguientes vehículos:<br><ul>{$listaVehiculos}</ul>No se puede eliminar mientras esté asignado."
                                        ))
                                        ->duration(10000)
                                        ->send();

                                    $action->cancel();
                                }
                            })
                            ->requiresConfirmation()
                            ->modalHeading('¿Estás seguro de que quieres eliminar este usuario?')
                            ->modalDescription('Esta acción no se puede deshacer. Asegúrate de que el usuario no tenga datos relacionados.'),

                        Action::make('activities')
                            ->label('Actividad')
                            ->icon('heroicon-o-clock')
                            ->url(fn($record) => static::getUrl('activities', ['record' => $record]))
                            ->openUrlInNewTab()
                            ->visible(fn() => auth()->user()?->hasRole('superadmin')),
                    ])
                        ->label('Acciones')               // Texto del botón del grupo
                        ->icon('heroicon-m-ellipsis-vertical'), // Icono del botón del grupo
                ])
                ->bulkActions([
                    Tables\Actions\BulkActionGroup::make([
                        Tables\Actions\DeleteBulkAction::make(),
                    ]),
                ])
                ->paginated(true)
                ->paginationPageOptions([50, 100, 200])
                ->defaultSort('created_at', 'desc');
        }
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
            'activities' => ListUserActivities::route('/{record}/activities'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->whereDoesntHave('roles', fn($query) => $query->where('name', 'superadmin'));
    }
}
