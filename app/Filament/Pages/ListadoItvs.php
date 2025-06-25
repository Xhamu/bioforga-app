<?php

namespace App\Filament\Pages;

use App\Models\ITV_Maquinas;
use App\Models\ITV_Vehiculos;
use App\Models\ItvVehiculo;
use Filament\Forms\Components\FileUpload;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class ListadoItvs extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard';
    protected static string $view = 'filament.pages.listado-itvs';

    protected static ?string $navigationGroup = 'Gestión de flota';
    protected static ?int $navigationSort = 5;
    protected static ?string $title = 'ITVs';

    public function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // No se usa realmente porque los registros los controlamos con getTableRecords()
        return ITV_Maquinas::query()->whereRaw('1 = 0');
    }

    public function getTableColumns(): array
    {
        return [
            TextColumn::make('tipo')->label('Tipo'),

            TextColumn::make('nombre')
                ->label('Nombre')
                ->formatStateUsing(fn($state) => '🔗 ' . $state) // ← Añade el ícono al texto
                ->url(function ($record) {
                    if ($record->tipo === 'Vehículo' && $record->vehiculo) {
                        return url("/vehiculos/{$record->vehiculo->id}/edit");
                    }

                    if ($record->tipo === 'Máquina' && $record->maquina) {
                        return url("/maquinas/{$record->maquina->id}/edit");
                    }

                    return null;
                })
                ->openUrlInNewTab(),

            TextColumn::make('fecha')
                ->label('Fecha ITV')
                ->formatStateUsing(fn($state) => \Carbon\Carbon::parse($state)->format('d/m/Y')),

            TextColumn::make('lugar')->label('Lugar'),

            TextColumn::make('resultado')
                ->label('Resultado')
                ->badge()
                ->color(fn($state) => match (strtolower($state)) {
                    'favorable' => 'success',
                    'desfavorable' => 'warning',
                    'negativo' => 'danger',
                    default => 'gray',
                }),

            TextColumn::make('documento')
                ->label('Documento')
                ->formatStateUsing(fn($state) => $state ? '🔗 Ver archivo' : '-')
                ->url(fn($record) => $record->documento ? url('/archivos/' . $record->documento) : null)
                ->openUrlInNewTab()
        ];
    }

    public function getTableRecords(): LengthAwarePaginator
    {
        $maquinas = ITV_Maquinas::whereNull('deleted_at')->get()->filter(function ($itv) {
            return $itv->maquina && is_null($itv->maquina->deleted_at);
        })->map(function ($itv) {
            $itv->tipo = 'Máquina';
            $itv->nombre = $itv->maquina->marca . ' ' . $itv->maquina->modelo;
            return $itv;
        });

        $vehiculos = ITV_Vehiculos::whereNull('deleted_at')->get()->filter(function ($itv) {
            return $itv->vehiculo && is_null($itv->vehiculo->deleted_at);
        })->map(function ($itv) {
            $itv->tipo = 'Vehículo';
            $itv->nombre = $itv->vehiculo->marca . ' ' . $itv->vehiculo->modelo . ' (' . $itv->vehiculo->matricula . ')';
            return $itv;
        });

        $combined = $maquinas->concat($vehiculos)->sortByDesc('fecha')->values();

        // Paginación manual
        $page = request()->get('page', 1);
        $perPage = 10;
        $items = $combined->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $combined->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['superadmin', 'administración', 'Administrador']);
    }

}
