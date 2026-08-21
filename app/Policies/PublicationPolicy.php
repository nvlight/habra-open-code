<?php

namespace App\Policies;

use App\Models\Publication;
use App\Models\User;

class PublicationPolicy
{
    public function update(User $user, Publication $publication): bool
    {
        return $publication->user_id === $user->id;
    }

    public function delete(User $user, Publication $publication): bool
    {
        return $publication->user_id === $user->id;
    }
}
