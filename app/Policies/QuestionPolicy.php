<?php

namespace App\Policies;

use App\Enums\PublicationStatus;
use App\Models\Question;
use App\Models\User;

/**
 * Admins and editors manage content. Only archived questions can be deleted
 * (App\Actions\Admin\DeleteQuestion removes the games they were played in too).
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
        return $user->canManageContent() && $question->status === PublicationStatus::Archived;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
