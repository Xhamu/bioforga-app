<?php

namespace App\Filament\Resources\UserResource\Pages;

use pxlrbt\FilamentActivityLog\Pages\ListActivities;

class ListUserActivities extends ListActivities
{
    protected static string $resource = \App\Filament\Resources\UserResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->hasRole('superadmin');
    }
}
