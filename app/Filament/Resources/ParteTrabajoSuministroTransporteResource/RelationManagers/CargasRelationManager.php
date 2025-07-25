<?php

namespace App\Filament\Resources\ParteTrabajoSuministroTransporteResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CargasRelationManager extends RelationManager
{
    protected static string $relationship = 'cargas';

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('referencia_id')
                    ->relationship('referencia', 'referencia')
                    ->searchable()
                    ->preload()
                    ->label('Referencia')
                    ->columnSpanFull()
                    ->reactive()
                    ->hidden(fn($get) => filled($get('almacen_id')))
                    ->requiredWithout('almacen_id')
                    ->rule('prohibited_if:almacen_id,!null'),

                Forms\Components\Select::make('almacen_id')
                    ->relationship('almacen', 'referencia')
                    ->searchable()
                    ->preload()
                    ->label('Almacén intermedio')
                    ->columnSpanFull()
                    ->reactive()
                    ->hidden(fn($get) => filled($get('referencia_id')))
                    ->requiredWithout('referencia_id')
                    ->rule('prohibited_if:referencia_id,!null'),

                Forms\Components\DateTimePicker::make('fecha_hora_inicio_carga')
                    ->label('Fecha/Hora inicio carga')
                    ->timezone('Europe/Madrid')
                    ->required(),

                Forms\Components\TextInput::make('gps_inicio_carga')
                    ->label('GPS inicio carga')
                    ->maxLength(255),

                Forms\Components\DateTimePicker::make('fecha_hora_fin_carga')
                    ->label('Fecha/Hora fin carga')
                    ->timezone('Europe/Madrid')
                    ->required(),

                Forms\Components\TextInput::make('gps_fin_carga')
                    ->label('GPS fin carga')
                    ->maxLength(255),

                Forms\Components\TextInput::make('cantidad')
                    ->label('Cantidad (m³)')
                    ->numeric()
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn($record) => $record->referencia?->referencia ?? $record->almacen?->referencia ?? 'Carga')
            ->columns([
                Tables\Columns\TextColumn::make('referencia_completa')
                    ->label('Referencia / Almacén')
                    ->searchable(query: function ($query, $search) {
                        $query
                            ->whereHas('referencia', fn($q) => $q->where('referencia', 'like', "%{$search}%"))
                            ->orWhereHas('almacen', fn($q) => $q->where('referencia', 'like', "%{$search}%"));
                    }),

                Tables\Columns\TextColumn::make('fecha_hora_inicio_carga')
                    ->label('Inicio carga')
                    ->sortable()
                    ->formatStateUsing(fn($state) => \Carbon\Carbon::parse($state)->timezone('Europe/Madrid')->format('d/m/Y H:i')),

                Tables\Columns\TextColumn::make('gps_inicio_carga')
                    ->label('Inicio carga')
                    ->formatStateUsing(function ($state) {
                        return $state
                            ? '<a href="https://www.google.com/maps/search/?api=1&query=' . urlencode($state) . '" target="_blank" class="text-primary-600 hover:underline">Ver ubicación</a>'
                            : '-';
                    })
                    ->html(),

                Tables\Columns\TextColumn::make('fecha_hora_fin_carga')
                    ->label('Fin carga')
                    ->sortable()
                    ->formatStateUsing(fn($state) => \Carbon\Carbon::parse($state)->timezone('Europe/Madrid')->format('d/m/Y H:i')),

                Tables\Columns\TextColumn::make('gps_fin_carga')
                    ->label('Fin carga')
                    ->formatStateUsing(function ($state) {
                        return $state
                            ? '<a href="https://www.google.com/maps/search/?api=1&query=' . urlencode($state) . '" target="_blank" class="text-primary-600 hover:underline">Ver ubicación</a>'
                            : '-';
                    })
                    ->html(),

                Tables\Columns\TextColumn::make('cantidad')
                    ->label('Cantidad (m³)')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Añadir carga')
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Editar'),
                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
