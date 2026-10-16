<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TikTokAccount extends Model
{
    protected $fillable = [
        'open_id',
        'username',
        'access_token',
        'refresh_token',
        'access_token_expires_at',
        'refresh_token_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'access_token_expires_at' => 'datetime',
            'refresh_token_expires_at' => 'datetime',
        ];
    }
}
