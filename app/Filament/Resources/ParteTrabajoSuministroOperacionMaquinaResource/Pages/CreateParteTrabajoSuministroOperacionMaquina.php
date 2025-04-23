<?php

namespace App\Filament\Resources\ParteTrabajoSuministroOperacionMaquinaResource\Pages;

use App\Filament\Resources\ParteTrabajoSuministroOperacionMaquinaResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateParteTrabajoSuministroOperacionMaquina extends CreateRecord
{
    protected static string $resource = ParteTrabajoSuministroOperacionMaquinaResource::class;
    public function getFormActions(): array
    {
        return [
            Action::make('iniciarTrabajo')
                ->label('Iniciar trabajo')
                ->color('success')
                ->modalHeading('Iniciar trabajo')
                ->modalSubmitActionLabel('Iniciar')
                ->modalWidth('xl')
                ->form([
                    Select::make('referencia_id')
                        ->label('Referencia')
                        ->relationship('referencia', 'referencia')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->getOptionLabelFromRecordUsing(function ($record) {
                            return "{$record->referencia} | {$record->proveedor->razon_social} ({$record->monte_parcela}, {$record->ayuntamiento})";
                        }),
                ])
                ->action(function (array $data) {
                    $this->form->fill(); // rellena lo que ya hay en el formulario
        
                    $formData = array_merge(
                        $this->form->getState(),
                        [
                            'referencia_id' => $data['referencia_id'],
                            'fecha_hora_inicio_trabajo' => now(),
                            'gps_inicio_trabajo' => '0.0000, 0.0000',
                        ]
                    );

                    $this->record = static::getModel()::create($formData);

                    Notification::make()
                        ->success()
                        ->title('Trabajo iniciado correctamente')
                        ->send();

                    $this->redirect(ParteTrabajoSuministroOperacionMaquinaResource::getUrl('edit', ['record' => $this->record]));
                }),
        ];
    }
}