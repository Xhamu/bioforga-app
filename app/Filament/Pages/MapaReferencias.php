<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ReferenciaResource;
use App\Models\Referencia;
use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Database\Eloquent\Builder;

class MapaReferencias extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $navigationLabel = 'Mapa';
    protected static ?string $title = 'Mapa de referencias';
    protected static ?string $slug = 'mapa';
    protected static ?int $navigationSort = 30;
    protected static string $view = 'filament.pages.mapa-referencias';

    // ⬇️ Usamos un array para el estado del formulario
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data') // ⬅️ IMPRESCINDIBLE
            ->schema([
                Section::make('Filtros')
                    ->columns(4)
                    ->schema([
                        DatePicker::make('desde')
                            ->label('Creación desde')
                            ->native(false)
                            ->live(debounce: 400),

                        DatePicker::make('hasta')
                            ->label('Creación hasta')
                            ->native(false)
                            ->live(debounce: 400),

                        Select::make('estado')
                            ->label('Estado')
                            ->searchable()
                            ->options([
                                'abierto' => 'Abierto',
                                'en_proceso' => 'En proceso',
                                'cerrado' => 'Cerrado',
                                'cerrado_no_procede' => 'Cerrado no procede',
                            ])
                            ->placeholder('Todos')
                            ->live(), // ⬅️ en lugar de ->reactive()

                        Select::make('sector')
                            ->label('Sector')
                            ->options([
                                '01' => 'Zona Norte Galicia',
                                '02' => 'Zona Sur Galicia',
                                '03' => 'Andalucía Oriental',
                                '04' => 'Andalucía Occidental y Sur Portugal',
                                '05' => 'Otros',
                            ])
                            ->multiple()
                            ->searchable()
                            ->placeholder('Todos')
                            ->live(), // ⬅️ en lugar de ->reactive()
                    ]),
            ]);
    }

    /** Query filtrada */
    protected function query(): Builder
    {
        // ⬇️ Lee SIEMPRE el estado del form
        $state = $this->form->getState();
        $estado = $state['estado'] ?? null;
        $sector = array_filter((array) ($state['sector'] ?? []));
        $desde = $state['desde'] ?? null;
        $hasta = $state['hasta'] ?? null;

        $q = Referencia::query()
            ->whereNotNull('ubicacion_gps');

        if (!blank($estado)) {
            $q->where('estado', $estado);
        }

        if (!empty($sector)) {
            // Si en BD guardas '01', '02'... asegúrate de comparar como string
            $q->whereIn('sector', array_map('strval', $sector));
        }

        if (!blank($desde)) {
            $q->whereDate('created_at', '>=', $desde);
        }
        if (!blank($hasta)) {
            $q->whereDate('created_at', '<=', $hasta);
        }

        return $q->latest('created_at');
    }

    /** Markers para el mapa */
    public function getMarkersProperty(): array
    {
        return $this->query()->get()
            ->map(function (Referencia $r) {
                [$lat, $lng] = $this->splitLatLng($r->ubicacion_gps);

                return [
                    'id' => $r->id,
                    'titulo' => $r->referencia,
                    'lat' => $lat,
                    'lng' => $lng,
                    'url' => ReferenciaResource::getUrl('edit', ['record' => $r]),
                ];
            })
            ->filter(fn($p) => is_finite($p['lat']) && is_finite($p['lng']))
            ->values()
            ->all();
    }

    protected function splitLatLng(?string $value): array
    {
        if (!$value)
            return [NAN, NAN];

        $value = str_replace([';', '  '], [',', ' '], trim($value));
        $parts = preg_split('/[,; ]+/', $value);
        $lat = isset($parts[0]) ? floatval(str_replace(',', '.', $parts[0])) : NAN;
        $lng = isset($parts[1]) ? floatval(str_replace(',', '.', $parts[1])) : NAN;

        return [$lat, $lng];
    }
}
