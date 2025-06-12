<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Pais;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaisPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_pais');
    }

    public function view(User $user, Pais $pais): bool
    {
        return $user->can('view_pais');
    }

    public function create(User $user): bool
    {
        return $user->can('create_pais');
    }

    public function update(User $user, Pais $pais): bool
    {
        return $user->can('update_pais');
    }

    public function delete(User $user, Pais $pais): bool
    {
        return $user->can('delete_pais');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_pais');
    }

    public function forceDelete(User $user, Pais $pais): bool
    {
        return $user->can('force_delete_pais');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_pais');
    }

    public function restore(User $user, Pais $pais): bool
    {
        return $user->can('restore_pais');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_pais');
    }

    public function replicate(User $user, Pais $pais): bool
    {
        return $user->can('replicate_pais');
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_pais');
    }
}