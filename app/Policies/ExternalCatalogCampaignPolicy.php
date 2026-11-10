<?php

namespace App\Policies;

use App\Models\ExternalCatalogCampaign;
use App\Models\User;
use App\Policies\Concerns\ChecksWorkspacePermissions;

class ExternalCatalogCampaignPolicy
{
    use ChecksWorkspacePermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'campaigns.view');
    }

    public function view(User $user, ExternalCatalogCampaign $campaign): bool
    {
        return $this->allows($user, 'campaigns.view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'campaigns.create');
    }

    public function update(User $user, ExternalCatalogCampaign $campaign): bool
    {
        return $this->allows($user, 'campaigns.update');
    }

    public function delete(User $user, ExternalCatalogCampaign $campaign): bool
    {
        return $this->allows($user, 'campaigns.delete');
    }
}
