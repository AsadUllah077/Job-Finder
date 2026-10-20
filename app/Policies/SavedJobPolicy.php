<?php

namespace App\Policies;

use App\Models\SavedJob;
use App\Models\User;

class SavedJobPolicy
{
    public function update(User $user, SavedJob $savedJob): bool
    {
        return $user->id === $savedJob->user_id;
    }

    public function delete(User $user, SavedJob $savedJob): bool
    {
        return $user->id === $savedJob->user_id;
    }
}
