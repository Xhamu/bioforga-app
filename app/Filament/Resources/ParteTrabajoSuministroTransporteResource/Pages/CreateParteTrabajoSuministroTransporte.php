<?php

namespace App\Filament\Resources\ParteTrabajoSuministroTransporteResource\Pages;

use App\Filament\Resources\ParteTrabajoSuministroTransporteResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class CreateParteTrabajoSuministroTransporte extends CreateRecord
{
    protected static string $resource = ParteTrabajoSuministroTransporteResource::class;

    protected static bool $canCreateAnother = false;

    public function mount(): void
    {
        $model = static::getResource()::getModel();

        $abierto = $model::query()
            ->where('usuario_id', Auth::id())
            ->whereNull('fecha_hora_descarga')
            ->whereNull('peso_neto')
            ->whereNull('deleted_at')
            ->first();

        if ($abierto) {
            Notification::make()
                ->title('Ya tienes un parte abierto')
                ->body('Debes cerrarlo (registrar la descarga) antes de crear uno nuevo.')
                ->danger()
                ->send();

            $this->redirect(ParteTrabajoSuministroTransporteResource::getUrl('view', [
                'record' => $abierto->getKey(),
            ]));
        }

        parent::mount();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $model = static::getResource()::getModel();

        $yaAbierto = $model::query()
            ->where('usuario_id', Auth::id())
            ->whereNull('fecha_hora_descarga')
            ->exists();

        if ($yaAbierto) {
            // Mensaje genérico del formulario
            validator([], [])->after(function ($v) {
                $v->errors()->add('form', 'No puedes crear otro parte: tienes uno abierto. Ciérralo primero.');
            })->validate();
        }

        // Asigna automáticamente el usuario creador
        $data['usuario_id'] = Auth::id();

        return $data;
    }
}
