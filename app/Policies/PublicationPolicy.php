<?php

namespace App\Policies;

use App\Models\Publication;
use App\Models\User;
use App\Policies\Concerns\ChecksWorkspacePermissions;

class PublicationPolicy
{
    use ChecksWorkspacePermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'publications.view');
    }

    public function view(User $user, Publication $publication): bool
    {
        return $this->allows($user, 'publications.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'publications.create');
    }

    public function update(User $user, Publication $publication): bool
    {
        return $this->allows($user, 'publications.update');
    }

    public function delete(User $user, Publication $publication): bool
    {
        return $this->allows($user, 'publications.delete');
    }
}
