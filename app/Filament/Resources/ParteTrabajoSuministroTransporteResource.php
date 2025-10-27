<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ParteTrabajoSuministroTransporteResource\Pages;
use App\Filament\Resources\ParteTrabajoSuministroTransporteResource\RelationManagers;
use App\Filament\Resources\ParteTrabajoSuministroTransporteResource\RelationManagers\CargasRelationManager;
use App\Models\AlmacenIntermedio;
use App\Models\Camion;
use App\Models\ParteTrabajoSuministroTransporte;
use App\Models\Poblacion;
use App\Models\Provincia;
use App\Models\Referencia;
use App\Services\AsignacionStockService;
use Arr;
use Awcodes\TableRepeater\Components\TableRepeater;
use Awcodes\TableRepeater\Header;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Form;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\View;
use Filament\Resources\RelationManagers\RelationGroup;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\Actions\Action as FormAction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\HtmlString;

class ParteTrabajoSuministroTransporteResource extends Resource
{
    protected static ?string $model = ParteTrabajoSuministroTransporte::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Partes de trabajo';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'partes-trabajo-suministro-transporte';
    public static ?string $label = 'suministro del transportista';
    public static ?string $pluralLabel = 'Suministros del transportista';

    // Eliminar el boton de create si tiene un parte activo.
    // Ahora mismo se muestra el botón, pero al darle redirige al parte activo.

    /*public static function canCreate(): bool
    {
        $model = static::getModel();

        return !$model::query()
            ->where('usuario_id', Auth::id())
            ->whereNull('fecha_hora_descarga')
            ->exists();
    }*/

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Datos del Transporte')
                    ->schema([
                        // ── USUARIO ────────────────────────────────────────────────────────────────
                        Select::make('usuario_id')
                            ->relationship(
                                name: 'usuario',
                                modifyQueryUsing: function ($query) {
                                    $user = Filament::auth()->user();

                                    if ($user->hasRole('transportista')) {
                                        $query->where('id', $user->id);
                                    } else {
                                        $query->whereHas(
                                            'roles',
                                            fn($q) => $q->whereIn('name', ['administración', 'administrador', 'transportista'])
                                        );
                                    }
                                }
                            )
                            ->getOptionLabelFromRecordUsing(function ($record) {
                                $ini = $record->apellidos
                                    ? mb_strtoupper(mb_substr($record->apellidos, 0, 1, 'UTF-8'), 'UTF-8') . '.'
                                    : '';
                                return trim($record->name . ' ' . $ini);
                            })
                            ->searchable()
                            ->preload()
                            ->default(fn() => Filament::auth()->id())
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state) {
                                // Reset dependientes
                                $set('camion_id', null);
                                $set('referencia_select', null);

                                if (!$state)
                                    return;

                                // Auto-seleccionar camión si solo hay 1
                                $user = \App\Models\User::find($state);
                                if (!$user)
                                    return;

                                $camiones = $user->proveedor_id
                                    ? Camion::where('proveedor_id', $user->proveedor_id)->pluck('id')
                                    : $user->camiones()->pluck('camiones.id');

                                if ($camiones->count() === 1) {
                                    $set('camion_id', $camiones->first());
                                }
                            }),

                        // ── CAMIÓN ────────────────────────────────────────────────────────────────
                        Select::make('camion_id')
                            ->label('Camión')
                            ->placeholder('- Selecciona primero un usuario -')
                            ->options(function (Get $get) {
                                $usuarioId = $get('usuario_id');
                                if (!$usuarioId)
                                    return [];

                                $usuario = \App\Models\User::find($usuarioId);

                                $camiones = $usuario?->proveedor_id
                                    ? Camion::where('proveedor_id', $usuario->proveedor_id)->get()
                                    : $usuario?->camiones()->get();

                                if (!$camiones || $camiones->isEmpty())
                                    return [];

                                return $camiones->mapWithKeys(fn($camion) => [
                                    $camion->id => '[' . $camion->matricula_cabeza . '] ' . $camion->marca . ' ' . $camion->modelo,
                                ])->toArray();
                            })
                            ->default(function (Get $get) {
                                $usuarioId = $get('usuario_id');
                                if (!$usuarioId)
                                    return null;

                                $usuario = \App\Models\User::find($usuarioId);

                                $ids = $usuario?->proveedor_id
                                    ? Camion::where('proveedor_id', $usuario->proveedor_id)->pluck('id')
                                    : $usuario?->camiones()->pluck('camiones.id');

                                return ($ids && $ids->count() === 1) ? $ids->first() : null;
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->validationMessages([
                                'required' => 'El :attribute es obligatorio.',
                            ]),

                        // ── INFO: PREVIEW ASIGNACIÓN ─────────────
                        Placeholder::make('preview_asignacion_actual')
                            ->label('Propuesta de carga')
                            ->content(function (callable $get, $record) {
                                /** @var \App\Models\ParteTrabajoSuministroTransporte $record */
                                if (!$record) {
                                    return new HtmlString(
                                        '<p class="text-gray-500">No hay información de parte seleccionada.</p>'
                                    );
                                }

                                $ultima = $record->cargas()->latest()->first();

                                // Debe ser una carga desde ALMACÉN
                                if (!$ultima || !$ultima->almacen_id || $ultima->referencia_id) {
                                    return new HtmlString(
                                        '<p class="text-gray-500">No hay carga desde almacén en curso.</p>'
                                    );
                                }

                                $fmt = fn($n) => number_format((float) $n, 2, ',', '.');

                                // 1) Si hay SNAPSHOT, lo mostramos SIEMPRE (verdad fuente)
                                // 1) Si hay SNAPSHOT, lo mostramos SIEMPRE (verdad fuente)
                                $snap = $ultima->asignacion_cert_esp;
                                if (!empty($snap) && is_array($snap)) {
                                    $fmt = fn($n) => number_format((float) $n, 2, ',', '.');

                                    // ← NUEVO: cargar meta de referencias en 1 query (para ayuntamiento y monte_parcela)
                                    $refIds = collect($snap)
                                        ->flatMap(fn($a) => collect($a['refs'] ?? [])->pluck('referencia_id'))
                                        ->filter()
                                        ->unique()
                                        ->values();

                                    $refMeta = \App\Models\Referencia::query()
                                        ->whereIn('id', $refIds)
                                        ->get(['id', 'referencia', 'ayuntamiento', 'monte_parcela'])
                                        ->keyBy('id');

                                    $rows = '';
                                    foreach ($snap as $a) {
                                        $cantidad = $fmt($a['cantidad'] ?? 0);
                                        $cert = e((string) ($a['certificacion'] ?? ''));
                                        $esp = e((string) ($a['especie'] ?? ''));

                                        // Sublista por referencias si existe
                                        $refsHtml = '';
                                        if (!empty($a['refs']) && is_array($a['refs'])) {
                                            $refLines = '';
                                            foreach ($a['refs'] as $r) {
                                                $rid = (int) ($r['referencia_id'] ?? 0);
                                                $info = $rid ? ($refMeta[$rid] ?? null) : null;

                                                // Etiqueta: REFERENCIA — AYUNTAMIENTO — MONTE_PARCELA
                                                $labelReferencia = $info?->referencia ?? (string) ($r['referencia'] ?? 'REF');
                                                $labelAyuntamiento = $info?->ayuntamiento ?? '—';
                                                $labelParcela = $info?->monte_parcela ?? '—';

                                                $refLines .= sprintf(
                                                    '<li class="flex justify-between text-xs text-gray-700">
                        <span class="truncate max-w-[65%%]">%s — %s (%s)</span>
                        <span class="tabular-nums">%s m³</span>
                     </li>',
                                                    e($labelReferencia),
                                                    e($labelParcela),
                                                    e($labelAyuntamiento),
                                                    $fmt($r['cantidad'] ?? 0)
                                                );
                                            }
                                            if ($refLines !== '') {
                                                $refsHtml = '<ul class="pl-6 mt-1 space-y-0.5">' . $refLines . '</ul>';
                                            }
                                        }

                                        $rows .= sprintf(
                                            '<li class="flex flex-col gap-1">
                <div class="flex items-start gap-2">
                    <span class="inline-flex min-w-[88px] justify-center rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-800">%s m³</span>
                    <span class="text-sm text-gray-900">%s — %s</span>
                </div>
                %s
            </li>',
                                            $cantidad,
                                            $cert,
                                            $esp,
                                            $refsHtml
                                        );
                                    }


                                    $html = <<<HTML
        <div class="space-y-2">
            <ul class="list-disc pl-5 space-y-2">
                {$rows}
            </ul>
        </div>
    HTML;

                                    return new \Illuminate\Support\HtmlString($html);
                                }

                                // 2) Fallback: si no hay snapshot (casos antiguos), calcular preview en vivo
                                $almacen = $ultima->almacen;
                                $cantidad = (float) ($ultima->cantidad ?? 0);

                                if (!$almacen || $cantidad <= 0) {
                                    return new HtmlString(
                                        '<p class="text-gray-500">No se puede mostrar la propuesta de carga.</p>'
                                    );
                                }

                                $res = app(AsignacionStockService::class)->preview($almacen, $cantidad);
                                if (empty($res['asignaciones'])) {
                                    return new HtmlString(
                                        '<p class="text-amber-600">No hay stock disponible para esta cantidad.</p>'
                                    );
                                }

                                $rows = '';
                                foreach ($res['asignaciones'] as $a) {
                                    $rows .= sprintf(
                                        '<li class="flex items-start gap-2">
                                            <span class="inline-flex min-w-[88px] justify-center rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-800">%s m³</span>
                                            <span class="text-sm text-gray-900">%s — %s</span>
                                        </li>',
                                        $fmt($a['cantidad']),
                                        e($a['certificacion']),
                                        e($a['especie'])
                                    );
                                }

                                $footer = '';
                                if (!empty($res['restante']) && $res['restante'] > 0) {
                                    $footer = '<p class="mt-2 text-sm text-amber-600">Faltan <strong>' . $fmt($res['restante']) . ' m³</strong> por falta de stock.</p>';
                                }

                                $html = <<<HTML
                                    <div class="space-y-2">
                                        <ul class="list-disc pl-5 space-y-1">
                                            {$rows}
                                        </ul>
                                        {$footer}
                                    </div>
                                HTML;

                                return new HtmlString($html);
                            })
                            ->visible(function ($get, $record) {
                                if (!$record)
                                    return false;
                                $ultima = $record->cargas()->latest()->first();
                                // Mostrar si es una carga desde almacén; el contenido ya decide si usa snapshot o preview:
                                return (bool) ($ultima && $ultima->almacen_id && !$ultima->referencia_id);
                            })
                            ->columnSpanFull()

                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ]),

                Section::make()
                    ->visible(fn($record) => static::hasCargasActions($record))
                    ->schema([
                        Grid::make(1)
                            ->schema(function ($record) {
                                if (!$record)
                                    return [];

                                if ($record->cliente_id && $record->albaran) {
                                    return [];
                                }

                                $ultimaCarga = $record->cargas()->latest()->first();

                                $acciones = [];

                                if (
                                    (!$ultimaCarga || $ultimaCarga->fecha_hora_fin_carga !== null)
                                    && is_null($record->cliente_id)
                                    && is_null($record->almacen_id)
                                ) {
                                    $acciones[] = Action::make('Iniciar carga')
                                        ->label('Iniciar carga')
                                        ->button()
                                        ->size('xl')
                                        ->modalHeading('Iniciar nueva carga')
                                        ->modalSubmitActionLabel('Iniciar')
                                        ->modalWidth('xl')
                                        ->extraAttributes(['class' => 'w-full']) // Hace que el botón ocupe todo el ancho disponible
                                        ->form([
                                            Select::make('eleccion')
                                                ->label('')
                                                ->options([
                                                    'referencia' => 'Referencia',
                                                    'almacen' => 'Almacén intermedio',
                                                ])
                                                ->searchable()
                                                ->reactive(), // Esto es necesario para reaccionar al cambio y actualizar el otro campo
                    
                                            Select::make('almacen_id')
                                                ->label('Almacén intermedio')
                                                ->options(function () use ($record) {
                                                    $usuarioId = $record?->usuario_id;

                                                    $almacenesIds = \DB::table('almacenes_users')
                                                        ->where('user_id', $usuarioId)
                                                        ->pluck('almacen_id');

                                                    $almacenes = $almacenesIds->isNotEmpty()
                                                        ? AlmacenIntermedio::whereIn('id', $almacenesIds)->get()
                                                        : AlmacenIntermedio::all();

                                                    return $almacenes->mapWithKeys(function ($almacen) {
                                                        $label = "{$almacen->referencia} ({$almacen->monte_parcela}, {$almacen->ayuntamiento})";
                                                        return [$almacen->id => mb_convert_encoding($label, 'UTF-8', 'UTF-8')];
                                                    });
                                                })
                                                ->default(function () use ($record) {
                                                    $usuarioId = $record?->usuario_id;

                                                    $almacenesIds = \DB::table('almacenes_users')
                                                        ->where('user_id', $usuarioId)
                                                        ->pluck('almacen_id');

                                                    $almacenes = $almacenesIds->isNotEmpty()
                                                        ? AlmacenIntermedio::whereIn('id', $almacenesIds)->pluck('id')
                                                        : AlmacenIntermedio::pluck('id');

                                                    return $almacenes->count() === 1 ? $almacenes->first() : null;
                                                })
                                                ->afterStateHydrated(function ($component, $state) use ($record) {
                                                    if (blank($state)) {
                                                        $usuarioId = $record?->usuario_id;

                                                        $almacenesIds = \DB::table('almacenes_users')
                                                            ->where('user_id', $usuarioId)
                                                            ->pluck('almacen_id');

                                                        $almacenes = $almacenesIds->isNotEmpty()
                                                            ? AlmacenIntermedio::whereIn('id', $almacenesIds)->pluck('id')
                                                            : AlmacenIntermedio::pluck('id');

                                                        if ($almacenes->count() === 1) {
                                                            $component->state($almacenes->first());
                                                        }
                                                    }
                                                })
                                                ->searchable()
                                                ->preload()
                                                ->required()
                                                ->visible(fn(callable $get) => $get('eleccion') === 'almacen'),

                                            TextInput::make('cantidad_m3')
                                                ->label('Cantidad a cargar (m³)')
                                                ->numeric()
                                                ->minValue(0.01)
                                                ->step('0.01')
                                                ->required()
                                                ->visible(fn(callable $get) => $get('eleccion') === 'almacen')
                                                ->reactive(),

                                            Placeholder::make('preview_asignacion')
                                                ->label('Propuesta de carga')
                                                ->content(function (callable $get) {
                                                    if ($get('eleccion') !== 'almacen') {
                                                        return null;
                                                    }

                                                    $almacenId = $get('almacen_id');
                                                    $cantidad = (float) ($get('cantidad_m3') ?? 0);

                                                    if (!$almacenId || $cantidad <= 0) {
                                                        return new HtmlString('<p class="text-gray-500">Selecciona almacén e indica cantidad para ver la propuesta.</p>');
                                                    }

                                                    $almacen = AlmacenIntermedio::find($almacenId);
                                                    if (!$almacen) {
                                                        return new HtmlString('<p class="text-red-600">Almacén no válido.</p>');
                                                    }

                                                    $res = app(AsignacionStockService::class)->preview($almacen, $cantidad);

                                                    if (empty($res['asignaciones'])) {
                                                        return new HtmlString('<p class="text-amber-600">No hay stock disponible para esta cantidad.</p>');
                                                    }

                                                    $fmt = fn($n) => number_format((float) $n, 2, ',', '.');

                                                    $rows = '';
                                                    foreach ($res['asignaciones'] as $a) {
                                                        $rows .= sprintf(
                                                            '<li class="flex items-start gap-2">
                                                                       <span class="inline-flex min-w-[88px] justify-center rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-800">%s m³</span>
                                                                       <span class="text-sm text-gray-900">%s — %s</span>
                                                                    </li>',
                                                            $fmt($a['cantidad']),
                                                            e($a['certificacion']),
                                                            e($a['especie'])
                                                        );
                                                    }

                                                    $footer = '';
                                                    if (!empty($res['restante']) && $res['restante'] > 0) {
                                                        $footer = '<p class="mt-2 text-sm text-amber-600">Faltan <strong>' . $fmt($res['restante']) . ' m³</strong> por falta de stock.</p>';
                                                    } else {
                                                        $footer = '<p class="mt-2 text-sm text-emerald-600"></p>';
                                                    }

                                                    $html = <<<HTML
                                                        <div class="space-y-2">
                                                            <ul class="list-disc pl-5 space-y-1">
                                                                {$rows}
                                                            </ul>
                                                            {$footer}
                                                        </div>
                                                    HTML;

                                                    return new HtmlString($html);
                                                })
                                                ->visible(fn(callable $get) => $get('eleccion') === 'almacen')
                                                ->hintColor('primary')
                                                ->columnSpanFull()
                                                ->hiddenLabel(),

                                            Select::make('referencia_select')
                                                ->label('Referencia')
                                                ->options(function () use ($record) {
                                                    $usuarioId = $record?->usuario_id;

                                                    if (!$usuarioId) {
                                                        return []; // No mostrar nada si no se ha seleccionado usuario
                                                    }

                                                    $referenciasIds = \DB::table('referencias_users')
                                                        ->where('user_id', $usuarioId)
                                                        ->pluck('referencia_id');

                                                    if ($referenciasIds->isEmpty()) {
                                                        return [];
                                                    }

                                                    $referencias = Referencia::whereIn('id', $referenciasIds)
                                                        ->with('proveedor', 'cliente')
                                                        ->get();

                                                    return $referencias->mapWithKeys(function ($referencia) {
                                                        $label = "{$referencia->referencia} | " .
                                                            ($referencia->proveedor?->razon_social ?? $referencia->cliente?->razon_social ?? 'Sin razón social') .
                                                            " ({$referencia->monte_parcela}, {$referencia->ayuntamiento})";

                                                        return [$referencia->id => mb_convert_encoding($label, 'UTF-8', 'UTF-8')];
                                                    });
                                                })
                                                ->searchable()
                                                ->preload()
                                                ->required()
                                                ->reactive() // Para que se recargue cuando cambia usuario_id
                                                ->visible(fn(callable $get) => $get('eleccion') === 'referencia'),

                                            TextInput::make('gps_inicio_carga')
                                                ->label('GPS')
                                                ->required()
                                                ->readOnly(fn() => !Auth::user()?->hasAnyRole(['administración', 'superadmin'])),

                                            View::make('livewire.location-inicio-carga')->columnSpanFull(),
                                        ])
                                        ->action(function (array $data, $record) {
                                            if ($data['eleccion'] === 'almacen') {
                                                $almacen = AlmacenIntermedio::findOrFail($data['almacen_id']);
                                                $cantidad = (float) ($data['cantidad_m3'] ?? 0);

                                                // 1) Propuesta con prioridades actuales
                                                $preview = app(AsignacionStockService::class)->preview($almacen, $cantidad);

                                                /** @var \App\Services\StockCalculator $calc */
                                                $calc = app(\App\Services\StockCalculator::class);

                                                // 2) Para cada CERT|ESP reparte por referencias (FIFO dentro de la clave)
                                                $detalle = collect($preview['asignaciones'])
                                                    ->filter(fn($a) => ($a['cantidad'] ?? 0) > 0)
                                                    ->map(function ($a) use ($calc, $almacen) {
                                                    $cert = strtoupper(trim($a['certificacion'] ?? ''));
                                                    $esp = strtoupper(trim($a['especie'] ?? ''));
                                                    $rest = (float) ($a['cantidad'] ?? 0);

                                                    $dispRefs = $calc->disponiblePorReferencia($almacen, $cert, $esp);

                                                    $refs = [];
                                                    foreach ($dispRefs as $r) {
                                                        if ($rest <= 0)
                                                            break;
                                                        $usa = min((float) $r['m3_disponible'], $rest);
                                                        if ($usa > 0) {
                                                            $refs[] = [
                                                                'referencia_id' => (int) $r['referencia_id'],
                                                                'referencia' => (string) $r['referencia'],
                                                                'cantidad' => round($usa, 4),
                                                            ];
                                                            $rest -= $usa;
                                                        }
                                                    }

                                                    return [
                                                        'certificacion' => $cert,
                                                        'especie' => $esp,
                                                        'cantidad' => round((float) $a['cantidad'], 4),
                                                        'refs' => $refs, // ← desglose por referencia
                                                    ];
                                                })
                                                    ->values()
                                                    ->all();

                                                // 3) Crea la carga guardando el snapshot
                                                $record->cargas()->create([
                                                    'almacen_id' => $data['almacen_id'],
                                                    'fecha_hora_inicio_carga' => now(),
                                                    'gps_inicio_carga' => $data['gps_inicio_carga'] ?? '0.0000, 0.0000',
                                                    'cantidad' => $cantidad,
                                                    'asignacion_cert_esp' => $detalle, // <<<<<<<<<<<<<<<<<<<<<< AQUI
                                                ]);
                                            } else {
                                                // ... tu rama referencia tal cual
                                                $record->cargas()->create([
                                                    'referencia_id' => $data['referencia_select'],
                                                    'fecha_hora_inicio_carga' => now(),
                                                    'gps_inicio_carga' => $data['gps_inicio_carga'] ?? '0.0000, 0.0000',
                                                ]);
                                            }

                                            Notification::make()->success()->title('Carga iniciada correctamente')->send();
                                            return redirect(request()->header('Referer'));
                                        })

                                        ->color('success');
                                }

                                if ($ultimaCarga && !$ultimaCarga->fecha_hora_fin_carga) {
                                    $acciones[] = Action::make('Finalizar carga')
                                        ->label('Finalizar carga')
                                        ->button()
                                        ->size('xl')
                                        ->modalHeading('Finalizar carga en curso')
                                        ->modalSubmitActionLabel('Finalizar')
                                        ->modalWidth('xl')
                                        ->extraAttributes(['class' => 'w-full'])
                                        ->form(function ($record) {
                                            $ultima = $record?->cargas()->latest()->first();

                                            if (!$ultima) {
                                                return [
                                                    Placeholder::make('sin_carga')
                                                        ->label('')
                                                        ->content('No hay ninguna carga en curso.'),
                                                ];
                                            }

                                            $esAlmacen = $ultima->almacen_id && !$ultima->referencia_id;

                                            $form = [];

                                            if (!$esAlmacen) {
                                                // Carga por REFERENCIA → pedir cantidad
                                                $form[] = TextInput::make('cantidad')
                                                    ->label('Cantidad (m³)')
                                                    ->required()
                                                    ->step(0.01)
                                                    ->dehydrateStateUsing(fn($state) => str_replace(',', '.', $state));
                                            } else {
                                                // Carga por ALMACÉN → mostrar cantidad y preview_asignacion
                                                $form[] = Placeholder::make('cantidad_info')
                                                    ->label('Cantidad (m³)')
                                                    ->content(function () use ($ultima) {
                                                    $m3 = (float) ($ultima->cantidad ?? 0);
                                                    return $m3 > 0 ? number_format($m3, 2, ',', '.') . ' m³' : '—';
                                                })
                                                    ->columnSpanFull();

                                                $form[] = Placeholder::make('preview_asignacion')
                                                    ->label('Propuesta de carga')
                                                    ->content(function () use ($ultima) {
                                                        $fmt = fn($n) => number_format((float) $n, 2, ',', '.');

                                                        // 1) Preferir snapshot guardado
                                                        $snap = $ultima->asignacion_cert_esp ?? [];
                                                        if (is_array($snap) && !empty($snap)) {
                                                            $rows = collect($snap)->map(function ($a) use ($fmt) {
                                                                return sprintf(
                                                                    '<li class="flex items-start gap-2">
                                                                        <span class="inline-flex min-w-[88px] justify-center rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-800">%s m³</span>
                                                                        <span class="text-sm text-gray-900">%s — %s</span>
                                                                    </li>',
                                                                    $fmt($a['cantidad'] ?? 0),
                                                                    e($a['certificacion'] ?? ''),
                                                                    e($a['especie'] ?? '')
                                                                );
                                                            })->implode('');
                                                            return new \Illuminate\Support\HtmlString('<ul class="list-disc pl-5 space-y-1">' . $rows . '</ul>');
                                                        }

                                                        // 2) Fallback: calcular propuesta en vivo (solo para cargas antiguas sin snapshot)
                                                        $almacen = $ultima?->almacen;
                                                        $cantidad = (float) ($ultima?->cantidad ?? 0);
                                                        if (!$almacen || $cantidad <= 0) {
                                                            return new \Illuminate\Support\HtmlString('<p class="text-gray-500">No se puede mostrar la propuesta de carga.</p>');
                                                        }

                                                        $res = app(\App\Services\AsignacionStockService::class)->preview($almacen, $cantidad);
                                                        if (empty($res['asignaciones'])) {
                                                            return new \Illuminate\Support\HtmlString('<p class="text-amber-600">No hay stock disponible para esta cantidad.</p>');
                                                        }

                                                        $rows = '';
                                                        foreach ($res['asignaciones'] as $a) {
                                                            $rows .= sprintf(
                                                                '<li class="flex items-start gap-2">
                                                                    <span class="inline-flex min-w-[88px] justify-center rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-800">%s m³</span>
                                                                    <span class="text-sm text-gray-900">%s — %s</span>
                                                                </li>',
                                                                $fmt($a['cantidad']),
                                                                e($a['certificacion']),
                                                                e($a['especie'])
                                                            );
                                                        }
                                                        return new \Illuminate\Support\HtmlString('<ul class="list-disc pl-5 space-y-1">' . $rows . '</ul>');
                                                    })
                                                    ->hintColor('primary')
                                                    ->columnSpanFull()
                                                    ->hiddenLabel();
                                            }

                                            $form[] = TextInput::make('gps_fin_carga')
                                                ->label('GPS')
                                                ->required()
                                                ->readOnly(fn() => !Auth::user()?->hasAnyRole(['administración', 'superadmin']));

                                            $form[] = View::make('livewire.location-fin-carga')->columnSpanFull();

                                            return $form;
                                        })
                                        ->action(function (array $data, $record) {
                                            $ultimaCarga = $record->cargas()->latest()->first();

                                            if (!$ultimaCarga || $ultimaCarga->fecha_hora_fin_carga) {
                                                Notification::make()
                                                    ->danger()
                                                    ->title('No hay ninguna carga en progreso.')
                                                    ->send();
                                                return;
                                            }

                                            $esAlmacen = $ultimaCarga->almacen_id && !$ultimaCarga->referencia_id;

                                            $cantidadFinal = $esAlmacen
                                                ? (float) ($ultimaCarga->cantidad ?? 0)
                                                : (float) ($data['cantidad'] ?? 0);

                                            if (!$esAlmacen && $cantidadFinal <= 0) {
                                                Notification::make()
                                                    ->danger()
                                                    ->title('Cantidad inválida')
                                                    ->body('Debes indicar una cantidad mayor que 0.')
                                                    ->send();
                                                return;
                                            }

                                            $ultimaCarga->update([
                                                'fecha_hora_fin_carga' => now(),
                                                'gps_fin_carga' => $data['gps_fin_carga'] ?? '0.0000, 0.0000',
                                                'cantidad' => $cantidadFinal,
                                            ]);

                                            $record->update([
                                                'cantidad_total' => $record->cargas()->sum('cantidad'),
                                            ]);

                                            Notification::make()
                                                ->success()
                                                ->title('Carga finalizada correctamente')
                                                ->send();

                                            return redirect(request()->header('Referer'));
                                        })
                                        ->color('danger');
                                }

                                if (
                                    $ultimaCarga &&
                                    $ultimaCarga->fecha_hora_fin_carga !== null &&
                                    is_null($record->cliente_id) &&
                                    is_null($record->almacen_id)
                                ) {
                                    $acciones[] = Action::make('Iniciar descarga')
                                        ->label('Iniciar descarga')
                                        ->button()
                                        ->size('xl')
                                        ->modalHeading('Iniciar descarga')
                                        ->modalSubmitActionLabel('Guardar descarga')
                                        ->modalWidth('xl')
                                        ->extraAttributes(['class' => 'w-full'])
                                        ->form([
                                            Select::make('eleccion')
                                                ->label('Tipo de destino')
                                                ->searchable()
                                                ->options([
                                                    'cliente' => 'Cliente',
                                                    'almacen_intermedio' => 'Almacén intermedio',
                                                ])
                                                ->required()
                                                ->reactive(),

                                            Select::make('cliente_id')
                                                ->label('Cliente')
                                                ->options(function () {
                                                    return \App\Models\Cliente::where('tipo_cliente', 'Suministro')
                                                        ->pluck('razon_social', 'id');
                                                })
                                                ->searchable()
                                                ->preload()
                                                ->required()
                                                ->visible(fn(callable $get) => $get('eleccion') === 'cliente'),

                                            Select::make('almacen_id')
                                                ->label('Almacén intermedio')
                                                ->options(function () {
                                                    $usuario = Auth::user();

                                                    // Si el usuario tiene el rol 'administración', mostrar todos los no eliminados
                                                    if ($usuario->hasRole('administración')) {
                                                        $almacenes = AlmacenIntermedio::whereNull('deleted_at')->get();
                                                    } else {
                                                        $almacenesIds = \DB::table('almacenes_users')
                                                            ->where('user_id', $usuario->id)
                                                            ->pluck('almacen_id');

                                                        $almacenes = $almacenesIds->isNotEmpty()
                                                            ? AlmacenIntermedio::whereIn('id', $almacenesIds)->whereNull('deleted_at')->get()
                                                            : collect();
                                                    }

                                                    return $almacenes->mapWithKeys(function ($almacen) {
                                                        $label = "{$almacen->referencia} ({$almacen->monte_parcela}, {$almacen->ayuntamiento})";
                                                        return [$almacen->id => mb_convert_encoding($label, 'UTF-8', 'UTF-8')];
                                                    });
                                                })
                                                ->searchable()
                                                ->preload()
                                                ->required(fn(callable $get) => $get('eleccion') === 'almacen_intermedio')
                                                ->visible(fn(callable $get) => $get('eleccion') === 'almacen_intermedio'),

                                            Select::make('tipo_biomasa')
                                                ->options([
                                                    'pino' => 'Pino',
                                                    'eucalipto' => 'Eucalipto',
                                                    'acacia' => 'Acacia',
                                                    'frondosa' => 'Frondosa',
                                                    'mezcla' => 'Mezcla',
                                                    'otros' => 'Otros',
                                                ])
                                                ->multiple()
                                                ->searchable()
                                                ->required(),

                                            TextInput::make('peso_neto')
                                                ->label('Peso neto (Tn)')
                                                ->numeric()
                                                ->required()
                                                ->minValue(0)
                                                ->maxValue(99)
                                                ->step(0.01),

                                            TextInput::make('cantidad_total')
                                                ->label('Cantidad total (m³)')
                                                ->numeric()
                                                ->disabled()
                                                ->helperText('Valor calculado automáticamente.')
                                                ->default(fn($record) => $record?->cargas?->sum('cantidad') ?? 0),

                                            FileUpload::make('albaran')
                                                ->label('Foto del ticket de pesada')
                                                ->disk('public')
                                                ->directory('albaranes')
                                                ->required(fn(callable $get) => $get('eleccion') === 'cliente')
                                                ->visible(fn(callable $get) => $get('eleccion') === 'cliente'),

                                            FileUpload::make('carta_porte')
                                                ->label('Carta de porte')
                                                ->disk('public')
                                                ->directory('albaranes')
                                                ->required(fn(callable $get) => $get('eleccion') === 'almacen_intermedio')
                                                ->visible(fn(callable $get) => $get('eleccion') === 'almacen_intermedio'),

                                            Textarea::make('observaciones')
                                                ->rows(4)
                                                ->maxLength(1000),

                                            TextInput::make('gps_descarga')
                                                ->label('GPS')
                                                ->required()
                                                ->readOnly(fn() => !Auth::user()?->hasAnyRole(['administración', 'superadmin'])),

                                            View::make('livewire.location-descarga')->columnSpanFull(),
                                        ])
                                        ->action(function (array $data, $record) {
                                            $record->update([
                                                'fecha_hora_descarga' => now(),
                                                'gps_descarga' => $data['gps_descarga'],
                                                'cliente_id' => $data['eleccion'] === 'cliente' ? $data['cliente_id'] : null,
                                                'almacen_id' => $data['eleccion'] === 'almacen_intermedio' ? $data['almacen_id'] : null,
                                                'tipo_biomasa' => $data['tipo_biomasa'],
                                                'peso_neto' => $data['peso_neto'],
                                                'albaran' => $data['albaran'] ?? null,
                                                'carta_porte' => $data['carta_porte'] ?? null,
                                                'observaciones' => $data['observaciones'] ?? null,
                                            ]);

                                            Notification::make()
                                                ->success()
                                                ->title('Descarga registrada correctamente')
                                                ->send();

                                            return redirect('/partes-trabajo-suministro-transporte');
                                        })
                                        ->color('primary');
                                }

                                return [
                                    Actions::make($acciones)
                                ];
                            }),
                    ])
                    ->columns(1),

                Section::make('Datos de la descarga')
                    ->visible(fn($record) => $record && ($record->cliente_id !== null || $record->almacen_id !== null))
                    ->schema([
                        DateTimePicker::make('fecha_hora_descarga')
                            ->timezone('Europe/Madrid')
                            ->label('Fecha descarga')
                            ->required()
                            ->visible(fn($record) => !is_null($record?->fecha_hora_descarga)),

                        Placeholder::make('gps_descarga_mostrar')
                            ->label('GPS descarga')
                            ->content(fn($record) => new HtmlString($record->gps_descarga_mostrar)),

                        Select::make('destino_tipo')
                            ->label('Destino')
                            ->options([
                                'cliente' => 'Cliente',
                                'almacen' => 'Almacén intermedio',
                            ])
                            ->live()
                            ->dehydrated(false) // 👈 NO se guarda en BD
                            // Al hidratar (editar), si no hay valor todavía, dedúcelo del registro:
                            ->afterStateHydrated(function (Get $get, Set $set, $record) {
                                if ($get('destino_tipo'))
                                    return;

                                if ($record?->almacen_id) {
                                    $set('destino_tipo', 'almacen');
                                } elseif ($record?->cliente_id) {
                                    $set('destino_tipo', 'cliente');
                                }
                            })
                            ->afterStateUpdated(function (string $state, Set $set) {
                                if ($state === 'cliente') {
                                    $set('almacen_id', null);
                                } elseif ($state === 'almacen') {
                                    $set('cliente_id', null);
                                }
                            }),

                        Select::make('cliente_id')
                            ->label('Cliente')
                            ->options(fn() => \App\Models\Cliente::where('tipo_cliente', 'suministro')->pluck('razon_social', 'id'))
                            ->searchable()
                            ->preload()
                            ->visible(fn(Get $get) => $get('destino_tipo') === 'cliente')
                            ->required(fn(Get $get) => $get('destino_tipo') === 'cliente'),

                        Select::make('almacen_id')
                            ->label('Almacén intermedio')
                            ->options(fn() => \App\Models\AlmacenIntermedio::pluck('referencia', 'id'))
                            ->searchable()
                            ->preload()
                            ->visible(fn(Get $get) => $get('destino_tipo') === 'almacen')
                            ->required(fn(Get $get) => $get('destino_tipo') === 'almacen'),

                        Select::make('tipo_biomasa')
                            ->label('Tipo de biomasa')
                            ->options([
                                'pino' => 'Pino',
                                'eucalipto' => 'Eucalipto',
                                'acacia' => 'Acacia',
                                'frondosa' => 'Frondosa',
                                'mezcla' => 'Mezcla',
                                'otros' => 'Otros',
                            ])
                            ->multiple()
                            ->required(),

                        TextInput::make('peso_neto')
                            ->label('Peso neto (Tn)')
                            ->numeric()
                            ->step(0.01)
                            ->required(),

                        TextInput::make('cantidad_total')
                            ->label('Cantidad total (m³)')
                            ->numeric()
                            ->required(),

                        FileUpload::make('albaran')
                            ->label('Foto del ticket de pesada')
                            ->disk('public')
                            ->directory('albaranes')
                            ->imageEditor()
                            ->openable()
                            ->required()
                            ->columnSpanFull()
                            ->visible(fn($record) => $record?->cliente_id !== null)
                            ->validationMessages([
                                'required' => 'Debes subir la foto del ticket de pesada.',
                                'image' => 'El archivo debe ser una imagen válida.',
                            ]),

                        FileUpload::make('carta_porte')
                            ->label('Carta de porte')
                            ->disk('public')
                            ->directory('albaranes')
                            ->imageEditor()
                            ->openable()
                            ->required()
                            ->columnSpanFull()
                            ->visible(fn($record) => $record?->almacen_id !== null)
                            ->validationMessages([
                                'required' => 'Debes subir la foto de la carta de porte.',
                                'image' => 'El archivo debe ser una imagen válida.',
                            ]),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ]),

                Section::make('Observaciones')
                    ->visible(fn($record) => $record)
                    ->schema([
                        Textarea::make('observaciones')
                            ->label('Observaciones')
                            ->placeholder('Escribe aquí cualquier detalle adicional...')
                            ->rows(8)
                            ->columnSpanFull()
                            ->maxLength(5000),

                        Actions::make([
                            FormAction::make('addObservaciones')
                                ->label('Añadir observaciones')
                                ->icon('heroicon-m-plus')
                                ->color('success')
                                ->modalHeading('Añadir observaciones')
                                ->modalSubmitActionLabel('Guardar')
                                ->modalWidth('lg')
                                ->form([
                                    Textarea::make('nueva_observacion')
                                        ->label('Nueva observación')
                                        ->placeholder('Escribe aquí la nueva observación...')
                                        ->rows(3)
                                        ->required(),
                                ])
                                ->action(function (Model $record, array $data) {
                                    $append = trim($data['nueva_observacion'] ?? '');
                                    if ($append === '') {
                                        return;
                                    }

                                    $stamp = now()->timezone('Europe/Madrid')->format('d/m/Y H:i');
                                    $prev = (string) ($record->observaciones ?? '');

                                    $nuevo = ($prev !== '' ? $prev . "\n" : '')
                                        . '[' . $stamp . '] ' . $append;

                                    $record->update(['observaciones' => $nuevo]);

                                    Notification::make()
                                        ->title('Observaciones añadidas')
                                        ->success()
                                        ->send();

                                    return redirect(request()->header('Referer'));
                                }),
                        ])->fullWidth()
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha y hora')
                    ->formatStateUsing(function ($state, $record) {
                        $primeraCarga = $record->cargas
                                ?->sortBy('created_at')
                                ?->first();

                        return $primeraCarga?->fecha_hora_inicio_carga
                            ? $primeraCarga->fecha_hora_inicio_carga->timezone('Europe/Madrid')->format('d/m/Y H:i')
                            : '-';
                    })
                    ->sortable()
                    ->weight(FontWeight::Bold),

                TextColumn::make('usuario_proveedor_camion')
                    ->label('Usuario / Proveedor / Camión')
                    ->html(),

                TextColumn::make('cargas_totales')
                    ->label('Cargas')
                    ->html(),

                TextColumn::make('id')
                    ->label('Destino')
                    ->html()
                    ->formatStateUsing(function ($record) {
                        $cantidad = $record->cantidad_total ? number_format($record->cantidad_total, 2, ',', '.') . ' m³' : '-';
                        $peso = $record->peso_neto ? number_format($record->peso_neto, 2, ',', '.') . ' Tn' : '-';

                        $hora_descarga = $record->fecha_hora_descarga
                            ? $record->fecha_hora_descarga->timezone('Europe/Madrid')->format('H:i')
                            : null;

                        $hora_html = $hora_descarga
                            ? "<span class='text-gray-700'>Hora:</span> $hora_descarga <br>"
                            : '';


                        if ($record->cliente && $record->cliente->razon_social) {
                            $provincia = Provincia::find($record->cliente->provincia);

                            $poblacion = Poblacion::find($record->cliente->poblacion);

                            $ubicacion = "{$provincia->nombre}, {$poblacion->nombre}";
                            return <<<HTML
                                <div class="leading-5">
                                    <strong>{$record->cliente->razon_social}</strong><br>
                                    <span class="text-gray-700">Ubicación:</span> $ubicacion<br>
                                    <span class="text-gray-700">Cantidad:</span> $cantidad<br>
                                    <span class="text-gray-700">Peso neto:</span> $peso <br>
                                    $hora_html
                                </div>
                            HTML;
                        }

                        if ($record->almacen && $record->almacen->referencia) {
                            $ubicacion = "{$record->almacen->ayuntamiento}, {$record->almacen->monte_parcela}";
                            return <<<HTML
                                <div class="leading-5">
                                    <strong>{$record->almacen->referencia}</strong><br>
                                    <span class="text-gray-700">Ubicación:</span> $ubicacion<br>
                                    <span class="text-gray-700">Cantidad:</span> $cantidad<br>
                                    <span class="text-gray-700">Peso neto:</span> $peso <br>
                                    $hora_html                                
                                </div>
                            HTML;
                        }

                        return '-';
                    }),
            ])
            ->persistFiltersInSession()
            ->filters(
                [
                    Filter::make('fecha_hora_inicio_carga')
                        ->columns(2)
                        ->form([
                            DatePicker::make('created_from')->label('Desde'),
                            DatePicker::make('created_until')->label('Hasta'),
                        ])
                        ->query(function ($query, array $data) {
                            $hasFrom = !empty($data['created_from']);
                            $hasUntil = !empty($data['created_until']);

                            if ($hasFrom || $hasUntil) {
                                $query->whereHas('cargas', function ($q) use ($data, $hasFrom, $hasUntil) {
                                    if ($hasFrom) {
                                        $q->whereDate('fecha_hora_inicio_carga', '>=', $data['created_from']);
                                    }
                                    if ($hasUntil) {
                                        $q->whereDate('fecha_hora_inicio_carga', '<=', $data['created_until']);
                                    }
                                });
                            }

                            return $query;
                        }),

                    SelectFilter::make('usuario_id')
                        ->label('Usuario')
                        ->options(function () {
                            $usuarioIds = ParteTrabajoSuministroTransporte::query()
                                ->distinct()
                                ->pluck('usuario_id')
                                ->filter()
                                ->unique();

                            return \App\Models\User::query()
                                ->whereIn('id', $usuarioIds)
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn($usuario) => [
                                    $usuario->id => trim($usuario->name . ' ' . ($usuario->apellidos ?? '')),
                                ])
                                ->toArray();
                        })
                        ->searchable()
                        ->preload()
                        ->placeholder('Todos'),

                    SelectFilter::make('camion_id')
                        ->label('Camión')
                        ->options(function () {
                            $camionIds = ParteTrabajoSuministroTransporte::query()
                                ->distinct()
                                ->pluck('camion_id')
                                ->filter()
                                ->unique();

                            return Camion::query()
                                ->whereIn('id', $camionIds)
                                ->orderBy('matricula_cabeza')
                                ->get()
                                ->mapWithKeys(fn($camion) => [
                                    $camion->id => $camion->matricula_cabeza,
                                ])
                                ->toArray();
                        })
                        ->searchable()
                        ->preload()
                        ->placeholder('Todos'),

                    SelectFilter::make('referencia_id')
                        ->label('Referencia')
                        ->options(function () {
                            $referenciaIds = \App\Models\CargaTransporte::query()
                                ->whereNotNull('referencia_id')
                                ->distinct()
                                ->pluck('referencia_id')
                                ->filter()
                                ->unique();

                            return Referencia::query()
                                ->whereIn('id', $referenciaIds)
                                ->orderBy('referencia')
                                ->get()
                                ->mapWithKeys(fn($referencia) => [
                                    $referencia->id => trim(
                                        $referencia->referencia . ' (' .
                                        ($referencia->ayuntamiento ?? '-') . ', ' .
                                        ($referencia->monte_parcela ?? '-') . ')'
                                    ),
                                ])
                                ->toArray();
                        })
                        ->searchable()
                        ->preload()
                        ->columnSpan(1)
                        ->placeholder('Todas')
                        ->query(function ($query, $data) {
                            return $query->when($data['value'], function ($query, $value) {
                                $query->whereHas('cargas', function ($subQuery) use ($value) {
                                    $subQuery->where('referencia_id', $value);
                                });
                            });
                        }),

                    SelectFilter::make('destino_filter')
                        ->label('Destino')
                        ->options([
                            'almacen' => 'Almacén intermedio',
                            'cliente' => 'Cliente',
                        ])
                        ->searchable()
                        ->preload()
                        ->placeholder('Todos')
                        ->modifyQueryUsing(function ($query, $state) {
                            $value = $state['value'] ?? null;

                            if ($value === 'almacen') {
                                $query->whereNotNull('almacen_id')->whereNull('cliente_id');
                            } elseif ($value === 'cliente') {
                                $query->whereNull('almacen_id')->whereNotNull('cliente_id');
                            }

                            return $query;
                        }),

                    SelectFilter::make('cliente_id')
                        ->label('Cliente')
                        ->relationship(
                            'cliente', // relación directa, no a través de referencia
                            'razon_social',
                            fn($query) => $query->whereIn(
                                'id',
                                ParteTrabajoSuministroTransporte::query()
                                    ->whereNotNull('cliente_id')
                                    ->distinct()
                                    ->pluck('cliente_id')
                            )
                        )
                        ->searchable()
                        ->preload()
                        ->placeholder('Todos'),
                ],
                layout: FiltersLayout::AboveContentCollapsible
            )
            ->filtersFormColumns(3)
            ->headerActions([
                Tables\Actions\Action::make('toggle_trashed')
                    ->label(fn() => request('trashed') === 'true' ? 'Ver activos' : 'Ver eliminados')
                    ->icon(fn() => request('trashed') === 'true' ? 'heroicon-o-eye' : 'heroicon-o-trash')
                    ->color(fn() => request('trashed') === 'true' ? 'gray' : 'danger')
                    ->visible(fn() => Filament::auth()->user()?->hasRole('superadmin'))
                    ->action(function () {
                        $verEliminados = request('trashed') !== 'true';

                        if ($verEliminados && ParteTrabajoSuministroTransporte::onlyTrashed()->count() === 0) {
                            Notification::make()
                                ->title('No hay registros eliminados')
                                ->body('Actualmente no existen registros en la papelera.')
                                ->warning()
                                ->send();

                            return;
                        }

                        // Redirige a la misma URL con o sin `trashed=true`
                        return redirect()->to(request()->fullUrlWithQuery([
                            'trashed' => $verEliminados ? 'true' : null,
                        ]));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->paginated(true)
            ->paginationPageOptions([50, 100, 200]);
    }

    public static function getRelations(): array
    {
        return [
            RelationGroup::make('Cargas', [
                CargasRelationManager::class,
            ]),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListParteTrabajoSuministroTransportes::route('/'),
            'create' => Pages\CreateParteTrabajoSuministroTransporte::route('/create'),
            'view' => Pages\ViewParteTrabajoSuministroTransporte::route('/{record}'),
            'edit' => Pages\EditParteTrabajoSuministroTransporte::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            // ORDENAR POR FECHA DE PRIMERA CARGA
            ->withMin('cargas', 'fecha_hora_inicio_carga')
            ->orderBy('cargas_min_fecha_hora_inicio_carga', 'desc')
            // 
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        if (request('trashed') === 'true') {
            $query->onlyTrashed();
        }

        $user = Filament::auth()->user();
        $rolesPermitidos = ['superadmin', 'administración', 'administrador', 'técnico'];

        if (!$user->hasAnyRole($rolesPermitidos)) {
            $query->where('usuario_id', $user->id);
        }

        if ($user->hasRole('técnico')) {
            $sectores = array_filter(Arr::wrap($user->sector ?? []));

            if (!empty($sectores)) {
                $query->where(function (Builder $q) use ($sectores) {
                    // A) Partes con cargas cuya referencia es de alguno de sus sectores
                    $q->whereHas('cargas', function (Builder $c) use ($sectores) {
                        $c->whereHas('referencia', fn(Builder $r) => $r->whereIn('sector', $sectores));
                    })
                        // OR
                        // B) Partes con cargas en almacén intermedio (cualquier almacén)
                        ->orWhereHas('cargas', function (Builder $c) {
                            $c->whereNotNull('almacen_id');
                        })
                        // (opcional) si también quieres incluir partes cuyo DESTINO FINAL es un almacén:
                        ->orWhere(function (Builder $p) {
                            $p->whereNotNull('almacen_id'); // campo en el propio parte
                        });
                });
            } else {
                // Si el técnico no tiene sectores, dejamos el comportamiento que ya tenías (sin ampliar).
                // Si prefieres que, aun sin sectores, vea los de almacén intermedio, descomenta:
                // $query->whereHas('cargas', fn (Builder $c) => $c->whereNotNull('almacen_id'));
            }
        }

        return $query;
    }

    public static function hasCargasActions($record): bool
    {
        if (!$record || ($record->cliente_id && $record->albaran)) {
            return false;
        }

        $ultimaCarga = $record->cargas()->latest()->first();

        return
            !$ultimaCarga || $ultimaCarga->fecha_hora_fin_carga ||

            ($ultimaCarga && !$ultimaCarga->fecha_hora_fin_carga) ||

            ($ultimaCarga && $ultimaCarga->fecha_hora_fin_carga);
    }
}
