<?php

namespace App\Livewire;

use App\Models\AlmacenIntermedio;
use App\Models\PrioridadStock;
use App\Models\AjusteStock;
use App\Services\StockCalculator;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Get;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PrioridadStockPorAlmacen extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public int $almacenIntermedioId;
    public ?AlmacenIntermedio $almacen = null;

    public function mount(int $almacenIntermedioId): void
    {
        $this->almacenIntermedioId = $almacenIntermedioId;
        $this->almacen = AlmacenIntermedio::findOrFail($almacenIntermedioId);
    }

    /* ================== HELPERS (los mismos que en PrioridadStockResource) ================== */

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

    /* ================== FORM (para Create/Edit en el propio tab) ================== */

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Combinación de stock')
                ->schema([
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\Select::make('certificacion')
                            ->label('Certificación')
                            ->options(array_combine(PrioridadStock::CERTS, PrioridadStock::CERTS))
                            ->required()
                            ->live(debounce: 350)
                            ->rules([
                                fn(Get $get, ?PrioridadStock $record) =>
                                Rule::unique('prioridades_stock', 'certificacion')
                                    ->where(
                                        fn($q) => $q
                                            ->where('almacen_intermedio_id', $this->almacenIntermedioId)
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
                            ->required()
                            ->live(debounce: 350)
                            ->rules([
                                fn(Get $get, ?PrioridadStock $record) =>
                                Rule::unique('prioridades_stock', 'especie')
                                    ->where(
                                        fn($q) => $q
                                            ->where('almacen_intermedio_id', $this->almacenIntermedioId)
                                            ->where('certificacion', $get('certificacion'))
                                    )
                                    ->ignore($record?->id),
                            ])
                            ->validationMessages([
                                'unique' => 'Ya existe esta combinación (Almacén + Certificación + Especie).',
                            ]),
                    ]),
                ])
                ->collapsible()
                ->compact(),

            Forms\Components\Section::make('Prioridad y stock')
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
                            ->content(function (Get $get, ?PrioridadStock $record) {
                                $almacen = $this->almacen;

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
                        ->content(function (Get $get, ?PrioridadStock $record) {
                            $almacen = $this->almacen;

                            $cert = $record?->certificacion ?? $get('certificacion');
                            $esp = $record?->especie ?? $get('especie');

                            if (!$almacen || !$cert || !$esp) {
                                return new HtmlString('<span class="text-gray-500">Selecciona certificación y especie.</span>');
                            }

                            /** @var StockCalculator $calc */
                            $calc = app(StockCalculator::class);
                            $calcArr = $calc->calcular($almacen);

                            $key = strtoupper(trim($cert)) . '|' . strtoupper(trim($esp));
                            $totEntradas = (float) ($calcArr['entradas'][$key] ?? 0.0);
                            $totDisp = (float) ($calcArr['disponible'][$key] ?? 0.0);
                            $consumo = max(0.0, $totEntradas - $totDisp);

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
                                ->filter(
                                    fn($e) =>
                                    self::mapCert($e->cert_raw) === strtoupper(trim($cert)) &&
                                    self::mapEsp($e->esp_raw) === strtoupper(trim($esp))
                                )
                                ->map(fn($e) => (object) [
                                    'ref' => (string) $e->referencia,
                                    'first' => (string) $e->first_at,
                                    'm3_in' => (float) $e->m3_entrada,
                                ])
                                ->sortBy('first')
                                ->values()
                                ->all();

                            $consumir = $consumo;
                            foreach ($rows as $i => $row) {
                                if ($consumir <= 0) {
                                    break;
                                }
                                $usa = min($row->m3_in, $consumir);
                                $rows[$i]->m3_in -= $usa;
                                $consumir -= $usa;
                            }

                            $items = [];
                            foreach ($rows as $row) {
                                $disp = max(0.0, $row->m3_in);
                                if ($disp > 0) {
                                    $items[] = '<li class="flex justify-between"><span class="truncate max-w-[60%]">'
                                        . e($row->ref)
                                        . '</span><span class="tabular-nums">'
                                        . number_format($disp, 2, ',', '.')
                                        . ' m³</span></li>';

                                    if (count($items) >= 6) {
                                        break;
                                    }
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
                ->collapsible()
                ->compact(),
        ];
    }

    /* ================== TABLE ================== */

    public function table(Table $table): Table
    {
        return $table
            ->query(
                PrioridadStock::query()
                    ->where('almacen_intermedio_id', $this->almacenIntermedioId)
                    ->orderBy('prioridad')
                    ->orderBy('id')
            )
            ->reorderable('prioridad')
            ->columns([
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

                        /** @var StockCalculator $calc */
                        $calc = app(StockCalculator::class);
                        $agg = $calc->calcular($r->almacen);

                        $fmt = fn($n) => number_format((float) $n, 2, ',', '.');

                        $totalEntradas = (float) ($agg['entradas'][$key] ?? 0.0);
                        $totalSalidas = (float) ($agg['salidas'][$key] ?? 0.0);
                        $ajusteTotal = (float) ($agg['ajustes'][$key] ?? 0.0);
                        $totalDisp = (float) ($agg['disponible'][$key] ?? 0.0);

                        if ($totalDisp <= 0.0001) {
                            if (abs($ajusteTotal) > 0.0001) {
                                return 'Regularización — ' . $fmt($ajusteTotal) . ' m³';
                            }
                            return 'Sin stock disponible para esta combinación.';
                        }

                        $entradas = \DB::table('carga_transportes as ct')
                            ->join('parte_trabajo_suministro_transportes as pt', 'pt.id', '=', 'ct.parte_trabajo_suministro_transporte_id')
                            ->join('referencias as rf', 'rf.id', '=', 'ct.referencia_id')
                            ->whereNull('ct.deleted_at')
                            ->whereNull('rf.deleted_at')
                            ->where('pt.almacen_id', $almacenId)
                            ->whereNull('pt.cliente_id')
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

                        if (empty($entradas)) {
                            return 'Regularización — ' . $fmt($totalDisp) . ' m³';
                        }

                        $consumir = max(0.0, min($totalEntradas, $totalSalidas));

                        foreach ($entradas as $i => $row) {
                            if ($consumir <= 0) {
                                break;
                            }

                            $usa = min($row->m3_in, $consumir);
                            $entradas[$i]->m3_in -= $usa;
                            $consumir -= $usa;
                        }

                        $lineasRefs = [];
                        $restoRefs = 0.0;

                        foreach ($entradas as $row) {
                            $dispRef = max(0.0, (float) $row->m3_in);
                            if ($dispRef <= 0) {
                                continue;
                            }

                            $restoRefs += $dispRef;
                            $lineasRefs[] = "{$row->referencia} — " . $fmt($dispRef) . " m³";

                            if (count($lineasRefs) >= 8) {
                                break;
                            }
                        }

                        $lineas = [];

                        if ($restoRefs > 0.0001) {
                            $lineas = $lineasRefs;
                        }

                        $extraReg = $totalDisp - $restoRefs;

                        if ($extraReg > 0.0001) {
                            $lineas[] = 'Regularización — ' . $fmt($extraReg) . ' m³';
                        }

                        if (empty($lineas)) {
                            $lineas[] = 'Regularización — ' . $fmt($totalDisp) . ' m³';
                        }

                        return implode("\n", $lineas);
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Añadir prioridad')
                    ->using(function (array $data): PrioridadStock {
                        $data['almacen_intermedio_id'] = $this->almacenIntermedioId;
                        return PrioridadStock::create($data);
                    })
                    ->form($this->getFormSchema()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->form($this->getFormSchema()),

                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation(),

                Tables\Actions\Action::make('subir')
                    ->label('Subir')
                    ->icon('heroicon-m-chevron-up')
                    ->action(fn(PrioridadStock $record) => $this->move($record, -1))
                    ->color('gray'),

                Tables\Actions\Action::make('bajar')
                    ->label('Bajar')
                    ->icon('heroicon-m-chevron-down')
                    ->action(fn(PrioridadStock $record) => $this->move($record, +1))
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
                        AjusteStock::create([
                            'almacen_intermedio_id' => $r->almacen_intermedio_id,
                            'certificacion' => strtoupper($r->certificacion),
                            'especie' => strtoupper($r->especie),
                            'delta_m3' => (float) $data['delta_m3'],
                            'motivo' => $data['motivo'] ?? null,
                            'user_id' => \Filament\Facades\Filament::auth()->id(),
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Regularización añadida')
                            ->send();
                    })
                    ->color('warning'),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('renumerar')
                    ->label('Renumerar prioridades (asc)')
                    ->icon('heroicon-m-arrow-path')
                    ->action(function () {
                        $this->renumerar($this->almacenIntermedioId);
                    })
                    ->requiresConfirmation(),
            ]);
    }

    /* ======== helpers de reordenación (adaptados de tu Resource) ======== */

    protected function move(PrioridadStock $record, int $delta): void
    {
        \DB::transaction(function () use ($record, $delta) {
            $almacenId = $record->almacen_intermedio_id;

            $items = PrioridadStock::where('almacen_intermedio_id', $almacenId)
                ->orderBy('prioridad')->orderBy('id')
                ->get();

            $idx = $items->search(fn($r) => $r->id === $record->id);
            if ($idx === false) {
                return;
            }

            $targetIdx = $idx + ($delta < 0 ? -1 : 1);
            if ($targetIdx < 0 || $targetIdx >= $items->count()) {
                return;
            }

            $items->splice($idx, 1);
            $items->splice($targetIdx, 0, [$record]);

            foreach ($items->values() as $i => $item) {
                if ($item->prioridad !== ($i + 1)) {
                    $item->update(['prioridad' => $i + 1]);
                }
            }
        });

        $this->dispatch('$refresh');
    }

    protected function renumerar(int $almacenId): void
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

        $this->dispatch('$refresh');
    }

    public function render(): View
    {
        return view('livewire.prioridad-stock-por-almacen');
    }
}
