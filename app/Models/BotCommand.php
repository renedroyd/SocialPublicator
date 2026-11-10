<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class BotCommand extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'bot_name',
        'type',
        'payload',
        'idempotency_key',
        'status',
        'attempts',
        'max_attempts',
        'available_at',
        'claimed_at',
        'completed_at',
        'error_message',
        'result',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'available_at' => 'datetime',
            'claimed_at' => 'datetime',
            'completed_at' => 'datetime',
            'result' => 'array',
            'attempts' => 'integer',
            'max_attempts' => 'integer',
        ];
    }
}
