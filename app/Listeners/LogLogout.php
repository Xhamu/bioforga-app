<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Logout;

class LogLogout
{
    public function handle(Logout $event): void
    {
        activity()
            ->causedBy($event->user instanceof \App\Models\User ? $event->user : null)
            ->withProperties([
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])
            ->log('Cierre de sesión');
    }
}
