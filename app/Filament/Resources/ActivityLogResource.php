<?php

namespace App\Filament\Resources;

use App\Exports\ActivityLogExport;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Columns\TextColumn;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\ActivityLogResource\Pages;
use Illuminate\Database\Eloquent\Model;
use Filament\Tables\Actions\Action;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;
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
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'updated' => 'warning',
                        'created' => 'success',
                        'deleted' => 'danger',
                        'login' => 'success',
                        'logout' => 'gray',
                        default => 'primary',
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

                        unset($changes['updated_at'], $old['updated_at']);

                        $changes = collect($changes)->filter(function ($new, $key) use ($old) {
                            return !is_null($new) && ($old[$key] ?? null) !== $new;
                        });

                        if ($changes->isEmpty()) {
                            return '—';
                        }

                        $formatValue = function ($val) {
                            try {
                                if (is_string($val) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $val)) {
                                    return \Carbon\Carbon::parse($val)->format('d/m/Y H:i');
                                }
                            } catch (\Exception $e) {
                            }

                            $stringVal = is_string($val) ? $val : json_encode($val);
                            return mb_strlen($stringVal) > 25 ? mb_substr($stringVal, 0, 25) . '...' : $stringVal;
                        };

                        $rows = $changes->map(function ($new, $key) use ($old, $formatValue) {
                            $oldValue = $old[$key] ?? '—';

                            $fullOld = is_string($oldValue) ? $oldValue : json_encode($oldValue);
                            $fullNew = is_string($new) ? $new : json_encode($new);

                            return "
                                <tr class='border-b border-gray-100'>
                                    <td class='px-2 py-1 align-top font-medium text-sm text-gray-700'>{$key}</td>
                                    <td class='px-2 py-1 align-top text-sm text-gray-500 max-w-[200px] break-all'>
                                        <span title=\"" . e($fullOld) . "\">\"" . e($formatValue($oldValue)) . "\"</span>
                                    </td>
                                    <td class='px-2 py-1 align-top text-sm text-gray-400'>→</td>
                                    <td class='px-2 py-1 align-top text-sm text-green-700 max-w-[200px] break-all'>
                                        <span title=\"" . e($fullNew) . "\">\"" . e($formatValue($new)) . "\"</span>
                                    </td>
                                </tr>
                            ";
                        })->implode('');

                        return "
                            <div class='overflow-x-auto'>
                                <table class='min-w-full text-sm text-left table-auto border-collapse'>
                                    <tbody>{$rows}</tbody>
                                </table>
                            </div>
                        ";
                    })
                    ->html()
                    ->wrap(),

                TextColumn::make('properties.ip')
                    ->label('IP'),

                TextColumn::make('properties.user_agent')
                    ->label('Navegador')
                    ->formatStateUsing(function ($state) {
                        if (strpos($state, 'Firefox') !== false)
                            return 'Firefox';
                        if (strpos($state, 'Chrome') !== false && strpos($state, 'Chromium') === false)
                            return 'Chrome';
                        if (strpos($state, 'Safari') !== false && strpos($state, 'Chrome') === false)
                            return 'Safari';
                        if (strpos($state, 'Edge') !== false)
                            return 'Edge';
                        return 'Otro';
                    })
                    ->badge()
                    ->color('gray'),

                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->formatStateUsing(fn($state) => \Carbon\Carbon::parse($state)->timezone('Europe/Madrid')->format('d/m/Y H:i'))
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Action::make('exportar')
                    ->label('Exportar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function () {
                        $hayDatos = Activity::exists();

                        if (!$hayDatos) {
                            \Filament\Notifications\Notification::make()
                                ->title('Sin datos')
                                ->body('No hay registros de actividad para exportar.')
                                ->warning()
                                ->send();
                            return;
                        }

                        $filename = 'registro-actividad-' . now()->format('Y-m-d') . '.xlsx';
                        return Excel::download(new ActivityLogExport, $filename);
                    })
            ]);
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
