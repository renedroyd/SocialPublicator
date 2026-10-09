<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotStatus extends Model
{
    protected $fillable = [
        'name',
        'platform',
        'status',
        'last_seen_at',
        'version',
        'host',
        'message',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function isOnline(int $staleAfterSeconds = 120): bool
    {
        return $this->status === 'online'
            && $this->last_seen_at?->greaterThanOrEqualTo(now()->subSeconds($staleAfterSeconds));
    }
}
