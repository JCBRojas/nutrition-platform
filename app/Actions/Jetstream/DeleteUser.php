<?php

namespace App\Actions\Jetstream;

use App\Models\User;
use Laravel\Jetstream\Contracts\DeletesUsers;

class DeleteUser implements DeletesUsers
{
    /**
     * Delete the given user.
     */
    public function delete(User $user): void
    {
        $user->deleteProfilePhoto();
        $user->tokens->each->delete();

        // Soft delete: un borrado físico eliminaría en cascada las versiones de dieta (diet_histories.updated_by)
        $user->forceFill(['is_active' => false])->save();
        $user->delete();
    }
}
