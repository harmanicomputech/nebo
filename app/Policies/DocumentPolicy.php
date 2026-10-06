<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * A document is visible to someone who can see documents AND the record it
 * belongs to; removable by someone who can manage documents AND update it.
 */
class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        $owner = $document->documentable;

        return $owner !== null && $user->can('documents.view') && Gate::forUser($user)->allows('view', $owner);
    }

    public function delete(User $user, Document $document): bool
    {
        $owner = $document->documentable;

        return $owner !== null && $user->can('documents.manage') && Gate::forUser($user)->allows('update', $owner);
    }
}
