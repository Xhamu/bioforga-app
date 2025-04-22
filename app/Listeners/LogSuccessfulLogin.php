<?php

// app/Listeners/LogSuccessfulLogin.php
namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Spatie\Activitylog\Models\Activity;

class LogSuccessfulLogin
{
    public function handle(Login $event)
    {
        activity()
            ->causedBy($event->user instanceof \App\Models\User ? $event->user : null)
            ->withProperties([
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'user_name' => $event->user->name,
                'user_email' => $event->user->email,
            ])
            ->log('Inicio de sesión');
    }
}
