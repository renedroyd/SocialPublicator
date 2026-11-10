<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FacebookGroup extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'facebook_id',
        'name',
        'url',
        'active',
        'last_synchronized_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'last_synchronized_at' => 'datetime',
    ];

    public function publications(): BelongsToMany
    {
        return $this->belongsToMany(
            Publication::class,
            'facebook_group_publication'
        )->withTimestamps();
    }
}
