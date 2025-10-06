<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ReferenciaResource;
use App\Models\Referencia;
use App\Models\Proveedor;
use Filament\Facades\Filament;
use Filament\Forms\Components\Toggle;
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

    public ?array $data = [];

    /** ⛔️ Ocultar en navegación si el usuario no tiene rol permitido */
    public static function shouldRegisterNavigation(): bool
    {
        $user = Filament::auth()->user();

        return $user?->hasAnyRole(['superadmin', 'administración']) ?? false;
    }

    public function mount(): void
    {
        /** ⛔️ Bloquear acceso directo por URL */
        $user = Filament::auth()->user();
        if (!$user?->hasAnyRole(['superadmin', 'administración'])) {
            abort(403);
        }

        $this->form->fill();
    }
    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Section::make('Filtros')
                    ->icon('heroicon-o-map-pin')
                    ->collapsible()
                    ->columns(3)
                    ->schema([
                        Select::make('estado')
                            ->label('Estado (Referencias)')
                            ->searchable()
                            ->options([
                                'abierto' => 'Abierto',
                                'en_proceso' => 'En proceso',
                                'cerrado' => 'Cerrado',
                                'cerrado_no_procede' => 'Cerrado no procede',
                            ])
                            ->placeholder('Todos')
                            ->live(),

                        Select::make('sector')
                            ->label('Sector (Referencias)')
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
                            ->live(),

                        Select::make('tipo_proveedor')
                            ->label('Tipo de servicio (Proveedor)')
                            ->options([
                                'Logística' => 'Logística',
                                'Servicios' => 'Servicios',
                                'Suministro' => 'Suministro',
                                'Combustible' => 'Combustible',
                                'Alojamiento' => 'Alojamiento',
                                'Taller' => 'Taller',
                                'Materia Prima' => 'Materia Prima',
                                'Otros' => 'Otros',
                            ])
                            ->multiple()
                            ->searchable()
                            ->placeholder('Todos')
                            ->live(),
                    ]),

                Section::make('Mostrar')
                    ->icon('heroicon-o-eye')
                    ->collapsible()
                    ->columns(2)
                    ->schema([
                        Toggle::make('mostrar_referencias')
                            ->label('Ver referencias')
                            ->default(true)
                            ->inline(false)
                            ->live(),

                        Toggle::make('mostrar_proveedores')
                            ->label('Ver proveedores')
                            ->default(true)
                            ->inline(false)
                            ->live(),
                    ]),
            ]);
    }

    /** Query de referencias (con filtros del formulario) */
    protected function referenciasQuery(): Builder
    {
        $state = $this->form->getState();
        $estado = $state['estado'] ?? null;
        $sector = array_filter((array) ($state['sector'] ?? []));

        $q = Referencia::query()->whereNotNull('ubicacion_gps');

        if (!blank($estado)) {
            $q->where('estado', $estado);
        }

        if (!empty($sector)) {
            $q->whereIn('sector', array_map('strval', $sector));
        }

        return $q->latest('created_at');
    }

    /** Query de proveedores con ubicación (aplicando filtros) */
    protected function proveedoresQuery(): Builder
    {
        $state = $this->form->getState();
        $tipos = array_filter((array) ($state['tipo_proveedor'] ?? []));

        $q = Proveedor::query()
            ->whereNotNull('ubicacion_gps');

        if (!empty($tipos)) {
            // cambia 'tipo_proveedor' por el nombre real de tu columna
            $q->whereIn('tipo_servicio', $tipos);
        }

        return $q->latest('updated_at');
    }

    /** Marcadores combinados para el mapa */
    public function getMarkersProperty(): array
    {
        $state = $this->form->getState();
        $verRefs = $state['mostrar_referencias'] ?? true;
        $verProvs = $state['mostrar_proveedores'] ?? true;

        $refMarkers = collect();
        $provMarkers = collect();

        if ($verRefs) {
            $refMarkers = $this->referenciasQuery()->get()->map(function (Referencia $r) {
                [$lat, $lng] = $this->splitLatLng($r->ubicacion_gps);
                return [
                    'id' => 'ref-' . $r->id,
                    'type' => 'referencia',
                    'titulo' => $r->referencia . ' - ' . $r->monte_parcela . ' (' . $r->ayuntamiento . ')',
                    'lat' => $lat,
                    'lng' => $lng,
                    'color' => '#3B82F6',
                    'url' => ReferenciaResource::getUrl('edit', ['record' => $r]),
                ];
            });
        }

        if ($verProvs) {
            $provMarkers = $this->proveedoresQuery()->get()->map(function (Proveedor $p) {
                [$lat, $lng] = $this->splitLatLng($p->ubicacion_gps);
                $url = class_exists(\App\Filament\Resources\ProveedorResource::class)
                    ? \App\Filament\Resources\ProveedorResource::getUrl('edit', ['record' => $p])
                    : null;

                return [
                    'id' => 'prov-' . $p->id,
                    'type' => 'proveedor',
                    'titulo' => $p->razon_social ?? $p->email,
                    'lat' => $lat,
                    'lng' => $lng,
                    'color' => '#10B981',
                    'url' => $url,
                ];
            });
        }

        return collect()
            ->merge($refMarkers)
            ->merge($provMarkers)
            ->filter(fn($m) => is_finite($m['lat']) && is_finite($m['lng']))
            ->values()
            ->all();
    }

    protected function splitLatLng(?string $value): array
    {
        if (!$value) {
            return [NAN, NAN];
        }

        // Normalización básica
        $raw = trim((string) $value);
        $raw = str_replace([';', '|'], ',', $raw);      // uniformar separadores
        $raw = preg_replace('/\s+/', ' ', $raw);        // colapsar espacios

        // Intento 1: separar por coma
        $parts = array_values(array_filter(explode(',', $raw), fn($v) => trim($v) !== ''));

        // Caso especial: valores con coma decimal separados por comas (ej: "42,123, -8,543")
        if (
            count($parts) > 2 &&
            preg_match('/^-?\d+$/', trim($parts[0])) &&
            preg_match('/^\d+$/', trim($parts[1]))
        ) {
            $parts = [
                $parts[0] . '.' . $parts[1],
                $parts[2] . (isset($parts[3]) ? '.' . $parts[3] : ''),
            ];
        }

        // Intento 2: si no hay al menos 2 partes, probar con espacio
        if (count($parts) < 2) {
            $parts = preg_split('/\s+/', $raw);
            if (count($parts) < 2) {
                return [NAN, NAN];
            }
        }

        $convert = function (string $coord): float {
            $coord = trim($coord);

            // DMS → Decimal (acepta formatos tipo: 42°27'13"N, 42 27 13 N, 42° 27' 13" N)
            // grupos: grados, minutos, segundos(dec), dirección opcional
            if (preg_match('/^\s*(\d+)[°\s]+(\d+)[\'\s]+([\d.,]+)"?\s*([NSEW])?\s*$/i', $coord, $m)) {
                $deg = (float) $m[1];
                $min = (float) $m[2];
                $sec = (float) str_replace(',', '.', $m[3]);
                $dir = strtoupper($m[4] ?? '');

                $decimal = $deg + ($min / 60) + ($sec / 3600);
                if (in_array($dir, ['S', 'W'], true)) {
                    $decimal *= -1;
                }
                return $decimal;
            }

            // Decimal con coma
            $coord = str_replace(',', '.', $coord);
            return (float) $coord;
        };

        $lat = $convert((string) $parts[0]);
        $lng = $convert((string) $parts[1]);

        // Validaciones: rango y descartar 0,0 (suele ser placeholder)
        if ($lat === 0.0 || $lng === 0.0 || abs($lat) > 90 || abs($lng) > 180) {
            return [NAN, NAN];
        }

        return [$lat, $lng];
    }
}
