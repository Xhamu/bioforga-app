<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\ActivityLogResource\Pages;
use Illuminate\Database\Eloquent\Model;

class ActivityLogResource extends Resource
{
    protected static ?string $model = \Spatie\Activitylog\Models\Activity::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Registro de actividad';
    protected static ?string $navigationGroup = 'Sistema';

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('description')
                    ->label('Descripción')
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        'updated' => 'Actualización',
                        'created' => 'Creación',
                        'deleted' => 'Eliminación',
                        'login' => 'Inicio de sesión',
                        'logout' => 'Cierre de sesión',
                        default => ucfirst($state),
                    })
                    ->searchable(),

                TextColumn::make('causer.name')
                    ->label('Usuario')
                    ->formatStateUsing(function ($state, $record) {
                        $name = $record->causer?->name ?? 'Sistema';
                        $apellidos = $record->causer?->apellidos ?? '';
                        return trim("{$name} {$apellidos}");
                    }),

                TextColumn::make('causer.email')
                    ->label('Email')
                    ->default('-'),

                TextColumn::make('changes')
                    ->label('Cambios')
                    ->formatStateUsing(function ($state, $record) {
                        $changes = $record->properties['attributes'] ?? [];
                        $old = $record->properties['old'] ?? [];

                        unset($changes['updated_at'], $old['updat ed_at']);

                        if (empty($changes)) {
                            return '—';
                        }

                        $formatDate = function ($val) {
                            try {
                                if (is_string($val) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $val)) {
                                    return \Carbon\Carbon::parse($val)->format('d/m/Y H:i');
                                }
                            } catch (\Exception $e) {
                            }

                            return is_string($val) ? $val : json_encode($val);
                        };

                        return collect($changes)->map(function ($new, $key) use ($old, $formatDate) {
                            $oldValue = $old[$key] ?? '—';
                            return "• <strong>{$key}</strong>: \"" . $formatDate($oldValue) . "\" → \"" . $formatDate($new) . "\"";
                        })->implode('<br>');
                    })
                    ->html()
                    ->wrap(),

                TextColumn::make('properties.ip')
                    ->label('IP'),

                TextColumn::make('properties.user_agent')
                    ->label('Navegador')
                    ->limit(30),

                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['administración', 'superadmin']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }


}
