<?php

namespace App\Policies;

use App\Models\User;
use App\Models\AlmacenIntermedio;
use Illuminate\Auth\Access\HandlesAuthorization;

class AlmacenIntermedioPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_almacen::intermedio');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AlmacenIntermedio $almacenIntermedio): bool
    {
        return $user->can('view_almacen::intermedio');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_almacen::intermedio');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AlmacenIntermedio $almacenIntermedio): bool
    {
        return $user->can('update_almacen::intermedio');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AlmacenIntermedio $almacenIntermedio): bool
    {
        return $user->can('delete_almacen::intermedio');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_almacen::intermedio');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, AlmacenIntermedio $almacenIntermedio): bool
    {
        return $user->can('force_delete_almacen::intermedio');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_almacen::intermedio');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, AlmacenIntermedio $almacenIntermedio): bool
    {
        return $user->can('restore_almacen::intermedio');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_almacen::intermedio');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, AlmacenIntermedio $almacenIntermedio): bool
    {
        return $user->can('replicate_almacen::intermedio');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_almacen::intermedio');
    }
}
