<?php

namespace App\Policies;

use App\Models\User;
use Estante\Shelf\Domain\Copy;

/** Shelves are private: only the owner can see or touch a copy. */
class CopyPolicy
{
    public function view(User $user, Copy $copy): bool
    {
        return $copy->isOwnedBy($user->id);
    }

    public function update(User $user, Copy $copy): bool
    {
        return $copy->isOwnedBy($user->id);
    }

    public function delete(User $user, Copy $copy): bool
    {
        return $copy->isOwnedBy($user->id);
    }
}
