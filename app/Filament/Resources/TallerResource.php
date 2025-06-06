<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TallerResource\Pages;
use App\Filament\Resources\TallerResource\RelationManagers;
use App\Models\Taller;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\File;

class TallerResource extends Resource
{
    protected static ?string $model = Taller::class;

    protected static ?string $navigationGroup = 'Gestión';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'talleres';

    public static ?string $label = 'taller';

    public static ?string $pluralLabel = 'Talleres';

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    public static function form(Form $form): Form
    {
        $ubicaciones = json_decode(File::get(resource_path('data/ubicaciones.json')), true);

        return $form
            ->schema([
                TextInput::make('nombre')
                    ->label(__('Nombre'))
                    ->required()
                    ->rules('required')
                    ->autofocus()
                    ->validationMessages([
                        'required' => 'El :attribute es obligatorio.',
                    ])
                    ->columnSpanFull(),

                Section::make('Ubicación')
                    ->schema([
                        Select::make('pais')
                            ->label(__('País'))
                            ->options([
                                'es' => 'España',
                            ])
                            ->searchable()
                            ->required()
                            ->reactive()
                            ->validationMessages([
                                'required' => 'El :attribute es obligatorio.',
                            ])
                            ->columnSpan(['default' => 2, 'md' => 1]),

                        Select::make('provincia')
                            ->label('Provincia')
                            ->options(function () use ($ubicaciones) {
                                return collect($ubicaciones)
                                    ->flatMap(function ($ccaa) {
                                        return collect($ccaa['provinces'] ?? [])
                                            ->mapWithKeys(function ($prov) use ($ccaa) {
                                                return [$ccaa['code'] . '-' . $prov['code'] => $prov['label']];
                                            });
                                    });
                            })
                            ->searchable()
                            ->required()
                            ->reactive(),

                        Select::make('poblacion')
                            ->label('Población')
                            ->options(function (callable $get) use ($ubicaciones) {
                                $provKey = $get('provincia');
                                if (!str_contains($provKey, '-'))
                                    return [];

                                [$ccaaCode, $provCode] = explode('-', $provKey);

                                foreach ($ubicaciones as $ccaa) {
                                    if ($ccaa['code'] === $ccaaCode) {
                                        foreach ($ccaa['provinces'] as $provincia) {
                                            if ($provincia['code'] === $provCode) {
                                                return collect($provincia['towns'] ?? [])
                                                    ->mapWithKeys(fn($town) => [$town['label'] => $town['label']]);
                                            }
                                        }
                                    }
                                }

                                return [];
                            })
                            ->searchable()
                            ->required(),

                        TextInput::make('codigo_postal')
                            ->label(__('Código postal'))
                            ->rules('required|numeric')
                            ->required()
                            ->validationMessages([
                                'required' => 'El :attribute es obligatorio.',
                                'numeric' => 'El :attribute no es válido.'
                            ])
                            ->columnSpan(['default' => 2, 'md' => 1]),

                        TextInput::make('direccion')
                            ->label(__('Dirección'))
                            ->required()
                            ->rules('required')
                            ->validationMessages([
                                'required' => 'La :attribute es obligatorio.',
                            ])
                            ->columnSpan(['default' => 2, 'md' => 2]),
                    ])
                    ->columns(['default' => 1, 'md' => 2])
                    ->columnSpanFull(),

                Section::make('Observaciones')
                    ->schema([
                        Textarea::make('observaciones')->label('')->rows(4)->nullable(),
                    ]),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('direccion')
                    ->icon('heroicon-m-map-pin'),
            ])
            ->filters([
                // Filtros si los necesitas
            ])
            ->actions([
                //Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->striped()
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListTallers::route('/'),
            'create' => Pages\CreateTaller::route('/create'),
            'edit' => Pages\EditTaller::route('/{record}/edit'),
        ];
    }
}
