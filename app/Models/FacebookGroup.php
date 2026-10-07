<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FacebookGroup extends Model
{
    protected $fillable = [
        'facebook_id',
        'nombre',
        'url',
        'activo',
        'ultima_sincronizacion',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'ultima_sincronizacion' => 'datetime',
    ];

    public function publicaciones(): BelongsToMany
    {
        return $this->belongsToMany(Publication::class, 'facebook_grupo_publicacion')
            ->withTimestamps();
    }
}
