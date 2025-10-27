<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PrioridadStockResource\Pages;
use App\Models\AlmacenIntermedio;
use App\Models\PrioridadStock;
use App\Services\StockCalculator;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Form;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;

class PrioridadStockResource extends Resource
{
    protected static ?string $model = PrioridadStock::class;

    protected static ?string $navigationIcon = 'heroicon-o-bars-arrow-down';
    protected static ?string $navigationGroup = 'Parcelas';
    protected static ?string $navigationLabel = 'Prioridades de stock';
    protected static ?string $modelLabel = 'Prioridad de stock';
    protected static ?string $pluralModelLabel = 'Prioridades de stock';
    protected static ?int $navigationSort = 2;

    protected static array $rolesPermitidos = ['superadmin', 'administración', 'supervisión'];

    protected static function usuarioPermitido(): bool
    {
        $user = Filament::auth()->user();
        return $user?->hasAnyRole(static::$rolesPermitidos) ?? false;
    }

    /** 1) Ocultar del menú de navegación */
    public static function shouldRegisterNavigation(): bool
    {
        return static::usuarioPermitido();
    }

    /** 2) Autorización de páginas/acciones del recurso */
    public static function canViewAny(): bool
    {
        return static::usuarioPermitido();
    }

    public static function canCreate(): bool
    {
        return static::usuarioPermitido();
    }

    public static function canEdit(Model $record): bool
    {
        return static::usuarioPermitido();
    }

    public static function canDelete(Model $record): bool
    {
        return static::usuarioPermitido();
    }

    public static function canDeleteAny(): bool
    {
        return static::usuarioPermitido();
    }


    // Helpers de mapeo (coinciden con StockCalculator)
    private static function mapCert(?string $raw): string
    {
        $raw = $raw ? strtolower(trim($raw)) : '';
        return match ($raw) {
            'sure_induestrial', 'sure_industrial', 'industrial' => 'SURE INDUSTRIAL',
            'sure_foresal', 'sure_forestal', 'forestal' => 'SURE FORESTAL',
            'pefc' => 'PEFC',
            'sbp' => 'SBP',
            default => 'SURE FORESTAL',
        };
    }

    private static function mapEsp(?string $raw): string
    {
        $raw = $raw ? strtolower(trim($raw)) : '';
        return match ($raw) {
            'pino' => 'PINO',
            'eucalipto' => 'EUCALIPTO',
            'acacia' => 'ACACIA',
            'frondosa' => 'FRONDOSA',
            'otros' => 'OTROS',
            default => 'OTROS',
        };
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // ───────────── SECCIÓN 1: Almacén ─────────────
                Forms\Components\Section::make('Almacén')
                    ->description('Selecciona el almacén intermedio para el que vas a definir la prioridad.')
                    ->schema([
                        Forms\Components\Select::make('almacen_intermedio_id')
                            ->label('Almacén intermedio')
                            ->options(fn() => AlmacenIntermedio::query()
                                ->orderBy('referencia')
                                ->pluck('referencia', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(debounce: 350),
                    ])
                    ->icon('heroicon-m-building-office')
                    ->collapsible()
                    ->compact(),

                // ────────── SECCIÓN 2: Certificación + Especie ──────────
                Forms\Components\Section::make('Combinación de stock')
                    ->description('Elige certificación y especie que formarán la clave de prioridad.')
                    ->schema([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\Select::make('certificacion')
                                ->label('Certificación')
                                ->options(array_combine(PrioridadStock::CERTS, PrioridadStock::CERTS))
                                ->helperText('Debe coincidir con el etiquetado del stock (p. ej., “SURE INDUSTRIAL”).')
                                ->required()
                                ->live(debounce: 350)
                                ->disabled(fn(Get $get) => blank($get('almacen_intermedio_id')))
                                ->rules([
                                    fn(Get $get, ?PrioridadStock $record) =>
                                    Rule::unique('prioridades_stock', 'certificacion')
                                        ->where(
                                            fn($q) => $q
                                                ->where('almacen_intermedio_id', $get('almacen_intermedio_id'))
                                                ->where('especie', $get('especie'))
                                        )
                                        ->ignore($record?->id),
                                ])
                                ->validationMessages([
                                    'unique' => 'Ya existe esta combinación (Almacén + Certificación + Especie).',
                                ]),

                            Forms\Components\Select::make('especie')
                                ->label('Especie')
                                ->options(array_combine(PrioridadStock::ESPECIES, PrioridadStock::ESPECIES))
                                ->helperText('PINO, EUCALIPTO, ACACIA, FRONDOSA u OTROS.')
                                ->required()
                                ->live(debounce: 350)
                                ->disabled(fn(Get $get) => blank($get('almacen_intermedio_id')))
                                ->rules([
                                    fn(Get $get, ?PrioridadStock $record) =>
                                    Rule::unique('prioridades_stock', 'especie')
                                        ->where(
                                            fn($q) => $q
                                                ->where('almacen_intermedio_id', $get('almacen_intermedio_id'))
                                                ->where('certificacion', $get('certificacion'))
                                        )
                                        ->ignore($record?->id),
                                ])
                                ->validationMessages([
                                    'unique' => 'Ya existe esta combinación (Almacén + Certificación + Especie).',
                                ]),
                        ]),
                    ])
                    ->icon('heroicon-m-adjustments-horizontal')
                    ->collapsible()
                    ->compact(),

                // ────────── SECCIÓN 3: Prioridad & Stock (preview) ──────────
                Forms\Components\Section::make('Prioridad y stock')
                    ->description('Asigna prioridad y revisa stock disponible con desglose FIFO.')
                    ->schema([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('prioridad')
                                ->label('Prioridad')
                                ->numeric()
                                ->minValue(1)
                                ->required()
                                ->helperText('1 = más prioritario'),

                            Forms\Components\Placeholder::make('stock_calculado')
                                ->label('Stock disponible (m³)')
                                ->content(function (?PrioridadStock $record, Get $get) {
                                    $almacen = $record?->almacen
                                        ?? (($id = $get('almacen_intermedio_id')) ? AlmacenIntermedio::find($id) : null);

                                    $cert = $record?->certificacion ?? $get('certificacion');
                                    $esp = $record?->especie ?? $get('especie');

                                    if (!$almacen || !$cert || !$esp) {
                                        return '—';
                                    }

                                    /** @var StockCalculator $calc */
                                    $calc = app(StockCalculator::class);
                                    $m3 = (float) $calc->disponiblePara($almacen, (string) $cert, (string) $esp);

                                    return number_format($m3, 2, ',', '.') . ' m³';
                                })
                                ->reactive(),
                        ]),

                        Forms\Components\Placeholder::make('fifo_preview')
                            ->label('Desglose por referencia (FIFO)')
                            ->content(function (?PrioridadStock $record, Get $get) {
                                $almacen = $record?->almacen
                                    ?? (($id = $get('almacen_intermedio_id')) ? AlmacenIntermedio::find($id) : null);
                                $cert = $record?->certificacion ?? $get('certificacion');
                                $esp = $record?->especie ?? $get('especie');

                                if (!$almacen || !$cert || !$esp) {
                                    return new HtmlString('<span class="text-gray-500">Selecciona almacén, certificación y especie.</span>');
                                }

                                /** @var StockCalculator $calc */
                                $calc = app(StockCalculator::class);
                                $calcArr = $calc->calcular($almacen);

                                $key = strtoupper(trim($cert)) . '|' . strtoupper(trim($esp));
                                $totEntradas = (float) ($calcArr['entradas'][$key] ?? 0.0);
                                $totDisp = (float) ($calcArr['disponible'][$key] ?? 0.0);
                                $consumo = max(0.0, $totEntradas - $totDisp);

                                // Entradas por referencia para la combinación
                                $rows = \DB::table('carga_transportes as ct')
                                    ->join('parte_trabajo_suministro_transportes as pt', 'pt.id', '=', 'ct.parte_trabajo_suministro_transporte_id')
                                    ->join('referencias as rf', 'rf.id', '=', 'ct.referencia_id')
                                    ->whereNull('ct.deleted_at')
                                    ->whereNull('rf.deleted_at')
                                    ->where('pt.almacen_id', $almacen->id)
                                    ->whereNotNull('ct.referencia_id')
                                    ->selectRaw('
                                        rf.id as referencia_id,
                                        rf.referencia,
                                        rf.tipo_certificacion as cert_raw,
                                        rf.producto_especie   as esp_raw,
                                        MIN(ct.created_at)    as first_at,
                                        SUM(ct.cantidad)      as m3_entrada
                                    ')
                                    ->groupBy('rf.id', 'rf.referencia', 'rf.tipo_certificacion', 'rf.producto_especie')
                                    ->get()
                                    ->filter(fn($e) => self::mapCert($e->cert_raw) === strtoupper(trim($cert))
                                        && self::mapEsp($e->esp_raw) === strtoupper(trim($esp)))
                                    ->map(fn($e) => (object) [
                                        'ref' => (string) $e->referencia,
                                        'first' => (string) $e->first_at,
                                        'm3_in' => (float) $e->m3_entrada,
                                    ])
                                    ->sortBy('first')
                                    ->values()
                                    ->all();

                                // Aplica consumo FIFO
                                $consumir = $consumo;
                                foreach ($rows as $i => $row) {
                                    if ($consumir <= 0)
                                        break;
                                    $usa = min($row->m3_in, $consumir);
                                    $rows[$i]->m3_in -= $usa;
                                    $consumir -= $usa;
                                }

                                // Lista (máx. 6)
                                $items = [];
                                foreach ($rows as $row) {
                                    $disp = max(0.0, $row->m3_in);
                                    if ($disp > 0) {
                                        $items[] = '<li class="flex justify-between"><span class="truncate max-w-[60%]">'
                                            . e($row->ref)
                                            . '</span><span class="tabular-nums">'
                                            . number_format($disp, 2, ',', '.')
                                            . ' m³</span></li>';
                                        if (count($items) >= 6)
                                            break;
                                    }
                                }

                                if (empty($items)) {
                                    return new HtmlString('<span class="text-gray-500">Sin stock disponible tras consumo.</span>');
                                }

                                return new HtmlString('<ul class="pl-4 list-disc space-y-1">' . implode('', $items) . '</ul>');
                            })
                            ->reactive()
                            ->columnSpanFull(),
                    ])
                    ->icon('heroicon-m-sparkles')
                    ->collapsible()
                    ->compact(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('prioridad')
            ->defaultSort('almacen_intermedio_id')
            ->defaultSort('prioridad')
            ->columns([
                Tables\Columns\TextColumn::make('almacen.referencia')
                    ->label('Almacén')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('certificacion')
                    ->label('Certificación')
                    ->badge()
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('especie')
                    ->label('Especie')
                    ->badge()
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextInputColumn::make('prioridad')
                    ->label('Prioridad')
                    ->rules(['required', 'integer', 'min:1'])
                    ->sortable()
                    ->width('7rem'),

                Tables\Columns\TextColumn::make('stock_calculado')
                    ->label('Stock (m³)')
                    ->alignCenter()
                    ->state(function (PrioridadStock $r) {
                        /** @var StockCalculator $calc */
                        $calc = app(StockCalculator::class);
                        $m3 = (float) $calc->disponiblePara($r->almacen, $r->certificacion, $r->especie);
                        return number_format($m3, 2, ',', '.');
                    })
                    ->badge()
                    ->color(function (PrioridadStock $r) {
                        /** @var StockCalculator $calc */
                        $calc = app(StockCalculator::class);
                        $m3 = (float) $calc->disponiblePara($r->almacen, $r->certificacion, $r->especie);
                        return $m3 <= 0 ? 'danger' : 'success';
                    })
                    ->tooltip(function (PrioridadStock $r) {
                        $almacenId = (int) $r->almacen_intermedio_id;
                        $certLabel = strtoupper(trim($r->certificacion));
                        $espLabel = strtoupper(trim($r->especie));
                        $key = $certLabel . '|' . $espLabel;

                        // 1) Entradas por referencia (descargas en el almacén)
                        $entradas = \DB::table('carga_transportes as ct')
                            ->join('parte_trabajo_suministro_transportes as pt', 'pt.id', '=', 'ct.parte_trabajo_suministro_transporte_id')
                            ->join('referencias as rf', 'rf.id', '=', 'ct.referencia_id')
                            ->whereNull('ct.deleted_at')
                            ->whereNull('rf.deleted_at')
                            ->where('pt.almacen_id', $almacenId)
                            ->whereNotNull('ct.referencia_id')
                            ->selectRaw('
                                rf.referencia,
                                rf.tipo_certificacion as cert_raw,
                                rf.producto_especie   as esp_raw,
                                MIN(ct.created_at)    as first_at,
                                SUM(ct.cantidad)      as m3_in
                            ')
                            ->groupBy('rf.id', 'rf.referencia', 'rf.tipo_certificacion', 'rf.producto_especie')
                            ->get()
                            ->filter(
                                fn($e) =>
                                self::mapCert($e->cert_raw) === $certLabel &&
                                self::mapEsp($e->esp_raw) === $espLabel
                            )
                            ->sortBy('first_at')
                            ->values()
                            ->all();

                        /** @var \App\Services\StockCalculator $calc */
                        $calc = app(StockCalculator::class);
                        $agg = $calc->calcular($r->almacen);

                        $fmt = fn($n) => number_format((float) $n, 2, ',', '.');

                        // 2) Cálculos agregados (incluyen ajustes)
                        $totalEntradas = (float) ($agg['entradas'][$key] ?? 0.0);
                        $totalDisp = (float) ($agg['disponible'][$key] ?? 0.0);
                        $ajusteTotal = (float) ($agg['ajustes'][$key] ?? 0.0); // ← suma de regularizaciones
            
                        // Consumo a repartir por FIFO en las referencias
                        // Nota: totalEntradas - totalDisp = salidas - ajustes
                        $consumir = max(0.0, $totalEntradas - $totalDisp);

                        // 3) Aplica consumo FIFO sobre las entradas por referencia
                        foreach ($entradas as $i => $row) {
                            if ($consumir <= 0)
                                break;
                            $usa = min($row->m3_in, $consumir);
                            $entradas[$i]->m3_in -= $usa;
                            $consumir -= $usa;
                        }

                        // 4) Construye líneas: referencias con disponible > 0 (máx. 8)
                        $lineas = [];
                        foreach ($entradas as $row) {
                            $disp = round(max(0.0, (float) $row->m3_in), 2);
                            if ($disp > 0) {
                                $lineas[] = "{$row->referencia} — " . $fmt($disp) . " m³";
                                if (count($lineas) >= 8)
                                    break;
                            }
                        }

                        // 5) Añade la línea de Regularización si existe
                        if (abs($ajusteTotal) > 0.0001) {
                            $lineas[] = "Regularización — " . $fmt($ajusteTotal) . " m³";
                        }

                        return $lineas
                            ? implode("\n", $lineas)
                            : (abs($ajusteTotal) > 0.0001
                                ? "Regularización — " . $fmt($ajusteTotal) . " m³"
                                : 'Sin trazabilidad registrada para esta combinación');
                    })

            ])
            ->filters([
                Tables\Filters\SelectFilter::make('almacen_intermedio_id')
                    ->label('Almacén')
                    ->options(fn() => AlmacenIntermedio::query()
                        ->orderBy('referencia')
                        ->pluck('referencia', 'id'))
                    ->searchable(),

                Tables\Filters\SelectFilter::make('certificacion')
                    ->options(array_combine(PrioridadStock::CERTS, PrioridadStock::CERTS))
                    ->label('Certificación'),

                Tables\Filters\SelectFilter::make('especie')
                    ->options(array_combine(PrioridadStock::ESPECIES, PrioridadStock::ESPECIES))
                    ->label('Especie'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()->requiresConfirmation(),
                Tables\Actions\Action::make('subir')
                    ->label('Subir')
                    ->icon('heroicon-m-chevron-up')
                    ->action(fn(PrioridadStock $record) => static::move($record, -1))
                    ->color('gray'),
                Tables\Actions\Action::make('bajar')
                    ->label('Bajar')
                    ->icon('heroicon-m-chevron-down')
                    ->action(fn(PrioridadStock $record) => static::move($record, +1))
                    ->color('gray'),

                Tables\Actions\Action::make('regularizar')
                    ->label('Regularización')
                    ->icon('heroicon-m-plus-circle')
                    ->form([
                        Forms\Components\TextInput::make('delta_m3')
                            ->label('Cantidad (m³, puede ser negativa)')
                            ->numeric()->step('0.01')->required(),
                        Forms\Components\Textarea::make('motivo')->label('Motivo')->rows(3),
                    ])
                    ->action(function (array $data, PrioridadStock $r) {
                        \App\Models\AjusteStock::create([
                            'almacen_intermedio_id' => $r->almacen_intermedio_id,
                            'certificacion' => strtoupper($r->certificacion),
                            'especie' => strtoupper($r->especie),
                            'delta_m3' => (float) $data['delta_m3'],
                            'motivo' => $data['motivo'] ?? null,
                            'user_id' => \Filament\Facades\Filament::auth()->id(),
                        ]);
                        \Filament\Notifications\Notification::make()
                            ->success()->title('Regularización añadida')->send();
                    })
                    ->color('warning'),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('renumerar')
                    ->label('Renumerar prioridades (asc)')
                    ->icon('heroicon-m-arrow-path')
                    ->action(function (array $records) {
                        $ids = collect($records)->pluck('id');
                        $byAlmacen = PrioridadStock::whereIn('id', $ids)->get()->groupBy('almacen_intermedio_id');

                        foreach ($byAlmacen as $almacenId => $items) {
                            static::renumerar($almacenId);
                        }
                    })
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    /**
     * Intercambia prioridad con el registro anterior/siguiente
     * dentro del mismo almacén.
     */
    protected static function swapPrioridad(PrioridadStock $record, int $delta): void
    {
        $target = PrioridadStock::query()
            ->where('almacen_intermedio_id', $record->almacen_intermedio_id)
            ->where('id', '!=', $record->id)
            ->when(
                $delta < 0,
                fn($q) => $q->where('prioridad', '<', $record->prioridad)->orderBy('prioridad', 'desc'),
                fn($q) => $q->where('prioridad', '>', $record->prioridad)->orderBy('prioridad', 'asc'),
            )
            ->first();

        if (!$target) {
            return;
        }

        $tmp = $record->prioridad;
        $record->update(['prioridad' => $target->prioridad]);
        $target->update(['prioridad' => $tmp]);
    }

    /**
     * Mueve una fila una posición (±1) dentro del mismo almacén.
     * Siempre renumera contiguamente tras el movimiento.
     */
    protected static function move(PrioridadStock $record, int $delta): void
    {
        \DB::transaction(function () use ($record, $delta) {
            $almacenId = $record->almacen_intermedio_id;

            // Lista ordenada por prioridad,id dentro del almacén
            $items = PrioridadStock::where('almacen_intermedio_id', $almacenId)
                ->orderBy('prioridad')->orderBy('id')
                ->get();

            // índice actual
            $idx = $items->search(fn($r) => $r->id === $record->id);
            if ($idx === false)
                return;

            $targetIdx = $idx + ($delta < 0 ? -1 : 1);
            if ($targetIdx < 0 || $targetIdx >= $items->count()) {
                // ya está arriba del todo o abajo del todo
                return;
            }

            // swap en memoria
            $items->splice($idx, 1);
            $items->splice($targetIdx, 0, [$record]);

            // renumerar 1..N y guardar
            foreach ($items->values() as $i => $item) {
                if ($item->prioridad !== ($i + 1)) {
                    $item->update(['prioridad' => $i + 1]);
                }
            }
        });
    }

    /** Renumera 1..N por almacén (utilizado por bulk y para saneo) */
    protected static function renumerar(int $almacenId): void
    {
        \DB::transaction(function () use ($almacenId) {
            $items = PrioridadStock::where('almacen_intermedio_id', $almacenId)
                ->orderBy('prioridad')->orderBy('id')
                ->get()
                ->values();

            foreach ($items as $i => $item) {
                $p = $i + 1;
                if ($item->prioridad !== $p) {
                    $item->update(['prioridad' => $p]);
                }
            }
        });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrioridadStocks::route('/'),
            'create' => Pages\CreatePrioridadStock::route('/create'),
            'edit' => Pages\EditPrioridadStock::route('/{record}/edit'),
        ];
    }
}
