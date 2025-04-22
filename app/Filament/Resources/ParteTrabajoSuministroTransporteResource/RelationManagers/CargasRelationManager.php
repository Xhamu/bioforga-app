<?php

namespace App\Filament\Resources\ParteTrabajoSuministroTransporteResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
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
                    ->required()
                    ->label('Referencia')
                    ->columnSpanFull(),

                Forms\Components\DateTimePicker::make('fecha_hora_inicio_carga')
                    ->label('Fecha/Hora inicio carga')
                    ->required(),

                Forms\Components\TextInput::make('gps_inicio_carga')
                    ->label('GPS inicio carga')
                    ->maxLength(255),

                Forms\Components\DateTimePicker::make('fecha_hora_fin_carga')
                    ->label('Fecha/Hora fin carga')
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
            ->recordTitleAttribute('referencia.referencia')
            ->columns([
                Tables\Columns\TextColumn::make('referencia.referencia')
                    ->label('Referencia')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('fecha_hora_inicio_carga')
                    ->label('Inicio carga')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('gps_inicio_carga')
                    ->label('GPS inicio'),

                Tables\Columns\TextColumn::make('fecha_hora_fin_carga')
                    ->label('Fin carga')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('gps_fin_carga')
                    ->label('GPS fin'),

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
