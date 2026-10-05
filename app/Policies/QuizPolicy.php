<?php

namespace App\Policies;

use App\Models\Quiz;
use App\Models\User;

/**
 * A quiz that has been played cannot be deleted (game sessions reference it) — archive it instead.
 */
class QuizPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageContent();
    }

    public function view(User $user, Quiz $quiz): bool
    {
        return $user->canManageContent();
    }

    public function create(User $user): bool
    {
        return $user->canManageContent();
    }

    public function update(User $user, Quiz $quiz): bool
    {
        return $user->canManageContent();
    }

    public function delete(User $user, Quiz $quiz): bool
    {
        return $user->canManageContent() && ! $quiz->gameSessions()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
