<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class AccountWidget extends Widget
{
    protected static string $view = 'filament.widgets.account-widget';

    protected int|string|array $columnSpan = 'full';

    public function getUserFullName(): string
    {
        $user = auth()->user();

        return "{$user->name} {$user->apellidos}";
    }
}
