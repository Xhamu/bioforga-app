<?php

namespace App\Filament\Widgets;

use App\Models\State;
use App\Models\UserStatus;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class AccountWidget extends Widget
{
    protected static string $view = 'filament.widgets.account-widget';

    protected int|string|array $columnSpan = 'full';

    // --- Estados ---
    public ?UserStatus $active = null;
    /** @var array<int, array{id:int,name:string,icon:?string,color:string}> */
    public array $states = [];

    public function mount(): void
    {
        $this->refreshData();
    }

    public function getUserFullName(): string
    {
        $user = auth()->user();
        return "{$user->name} {$user->apellidos}";
    }

    /** Mostrar controles de estados solo si es Bioforga y no tiene proveedor */
    public function canSeeStates(): bool
    {
        $u = Auth::user();
        if (!$u)
            return false;

        $isBioforga = (bool) ($u->empresa_bioforga ?? false);
        $hasProveedor = !is_null($u->proveedor_id);

        return $isBioforga && !$hasProveedor;
    }

    public function refreshData(): void
    {
        $this->active = Auth::user()?->activeStatus()->with('state')->first();

        $this->states = State::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'icon' => $s->icon,
                'color' => $s->color ?? 'gray',
            ])
            ->all();
    }

    public function start(int $stateId): void
    {
        $user = Auth::user();

        if ($user->activeStatus()->exists()) {
            Notification::make()->title('Ya tienes un estado activo.')->warning()->send();
            $this->refreshData();
            return;
        }

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

        // El modal se cierra desde el Blade con close-modal, no hace falta tocar nada aquí.
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
