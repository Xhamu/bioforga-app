<?php

// app/Filament/Widgets/EstadoUsuarioWidget.php
namespace App\Filament\Widgets;

use App\Models\State;
use App\Models\UserStatus;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class EstadoUsuarioWidget extends Widget
{
    protected static string $view = 'filament.widgets.estado-usuario-widget';
    protected static ?string $heading = 'Mi estado';

    protected int|string|array $columnSpan = 'full';

    public ?UserStatus $active = null;
    public $states = [];

    public function mount(): void
    {
        $this->refreshData();
    }

    public function refreshData(): void
    {
        $this->active = Auth::user()?->activeStatus()->with('state')->first();
        $this->states = State::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function start(int $stateId): void
    {
        $user = Auth::user();

        if ($user->activeStatus()->exists()) {
            Notification::make()->title('Ya tienes un estado activo.')->warning()->send();
            $this->refreshData();
            return;
        }

        // Validamos que el estado exista y esté activo
        $state = State::where('id', $stateId)->where('is_active', true)->first();
        if (!$state) {
            Notification::make()->title('Estado no disponible.')->danger()->send();
            return;
        }

        $user->statuses()->create([
            'state_id' => $stateId,
            'started_at' => now(),
        ]);

        Notification::make()->title("{$state->name} iniciado")->success()->send();
        $this->refreshData();
    }

    public function stop(): void
    {
        $active = Auth::user()?->activeStatus()->first();

        if (!$active) {
            Notification::make()->title('No hay estado activo.')->warning()->send();
            return;
        }

        $active->update(['ended_at' => now()]);

        Notification::make()->title('Estado finalizado')->success()->send();
        $this->refreshData();
    }

    protected function getViewData(): array
    {
        return [
            'active' => $this->active,
            'states' => $this->states,
        ];
    }
}
