<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WhatsAppGroup extends Model
{
    protected $table = 'whatsapp_groups';

    protected $fillable = [
        'whatsapp_id',
        'name',
        'active',
        'last_synchronized_at',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'last_synchronized_at' => 'datetime',
        ];
    }

    public function publications(): BelongsToMany
    {
        return $this->belongsToMany(
            Publication::class,
            'whatsapp_group_publication'
        )->withTimestamps();
    }
}
