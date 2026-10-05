<?php

namespace App\Policies;

use App\Models\Question;
use App\Models\User;

/**
 * Admins and editors manage content. A question that players have already answered
 * cannot be deleted (answers keep a foreign key to it) — archive it instead.
 */
class QuestionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageContent();
    }

    public function view(User $user, Question $question): bool
    {
        return $user->canManageContent();
    }

    public function create(User $user): bool
    {
        return $user->canManageContent();
    }

    public function update(User $user, Question $question): bool
    {
        return $user->canManageContent();
    }

    public function delete(User $user, Question $question): bool
    {
        return $user->canManageContent() && ! $question->answers()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
