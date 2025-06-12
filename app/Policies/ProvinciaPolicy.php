<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Provincia;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProvinciaPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_provincia');
    }

    public function view(User $user, Provincia $provincia): bool
    {
        return $user->can('view_provincia');
    }

    public function create(User $user): bool
    {
        return $user->can('create_provincia');
    }

    public function update(User $user, Provincia $provincia): bool
    {
        return $user->can('update_provincia');
    }

    public function delete(User $user, Provincia $provincia): bool
    {
        return $user->can('delete_provincia');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_provincia');
    }

    public function forceDelete(User $user, Provincia $provincia): bool
    {
        return $user->can('force_delete_provincia');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_provincia');
    }

    public function restore(User $user, Provincia $provincia): bool
    {
        return $user->can('restore_provincia');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_provincia');
    }

    public function replicate(User $user, Provincia $provincia): bool
    {
        return $user->can('replicate_provincia');
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_provincia');
    }
}
