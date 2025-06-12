<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Poblacion;
use Illuminate\Auth\Access\HandlesAuthorization;

class PoblacionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_poblacion');
    }

    public function view(User $user, Poblacion $poblacion): bool
    {
        return $user->can('view_poblacion');
    }

    public function create(User $user): bool
    {
        return $user->can('create_poblacion');
    }

    public function update(User $user, Poblacion $poblacion): bool
    {
        return $user->can('update_poblacion');
    }

    public function delete(User $user, Poblacion $poblacion): bool
    {
        return $user->can('delete_poblacion');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_poblacion');
    }

    public function forceDelete(User $user, Poblacion $poblacion): bool
    {
        return $user->can('force_delete_poblacion');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_poblacion');
    }

    public function restore(User $user, Poblacion $poblacion): bool
    {
        return $user->can('restore_poblacion');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_poblacion');
    }

    public function replicate(User $user, Poblacion $poblacion): bool
    {
        return $user->can('replicate_poblacion');
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_poblacion');
    }
}
