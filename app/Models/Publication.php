<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Publication extends Model
{
    protected $fillable = [
        'fecha',
        'hora',
        'imagen',
        'texto',
        'destino',
        'activo',
        'tipo_recurrencia',
        'intervalo_horas',
        'hora_inicio_recurrencia',
        'hora_fin_recurrencia',
        'fecha_fin',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'activo' => 'boolean',
            'fecha_fin' => 'date',
            'intervalo_horas' => 'integer',
        ];
    }

    public function esRecurrente(): bool
    {
        return $this->tipo_recurrencia !== 'ninguna';
    }

    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'publicacion_producto'
        )
            ->withPivot([
                'cantidad',
                'precio_publicado',
                'orden',
            ])
            ->withTimestamps()
            ->orderByPivot('orden');
    }

    public function destinations(): BelongsToMany
    {
        return $this->belongsToMany(
            PublicationDestination::class,
            'publication_publication_destination'
        )->withTimestamps();
    }

    public function schedule(): HasOne
    {
        return $this->hasOne(PublicationSchedule::class);
    }

    public function facebookGroups(): BelongsToMany
    {
        return $this->belongsToMany(
            FacebookGroup::class,
            'facebook_group_publication'
        )->withTimestamps();
    }

    protected static function booted(): void
    {
        static::saving(function (Publication $publication): void {
            if (
                $publication->tipo_recurrencia === 'cada_horas' &&
                filled($publication->hora_inicio_recurrencia)
            ) {
                $publication->hora = $publication->hora_inicio_recurrencia;
            }
        });
    }
}
