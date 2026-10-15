<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('viewUsers');
    }

    public function create(User $user): bool
    {
        return $user->can('createUsers');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('editUsers');
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can('deleteUsers') && ! $user->is($model);
    }

    public function restore(User $user, User $model): bool
    {
        return $user->can('deleteUsers');
    }
}
