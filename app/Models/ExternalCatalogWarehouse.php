<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class ExternalCatalogWarehouse extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'source',
        'external_id',
        'code',
        'name',
        'active',
        'online_fulfillment_enabled',
        'synced_at',
        'sync_run_id',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'online_fulfillment_enabled' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }
}
