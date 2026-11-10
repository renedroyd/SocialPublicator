<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalCatalogWarehouse extends Model
{
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
