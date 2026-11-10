<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class BotAgent extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'name',
        'instance_id',
        'token_hash',
        'social_account_ids',
        'is_active',
        'last_seen_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'social_account_ids' => 'array',
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }
}
