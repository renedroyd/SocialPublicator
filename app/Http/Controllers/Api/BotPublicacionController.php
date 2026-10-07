<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Publication;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class BotPublicacionController extends Controller
{
    public function index(): JsonResponse
    {
        $ahora = now();
        $fechaActual = $ahora->toDateString();

        $publicaciones = Publication::query()
            /*
             * Cargamos los grupos de Facebook seleccionados
             * para evitar consultas adicionales dentro del foreach.
             */
            ->with([
                'facebookGroups' => function ($query) {
                    $query
                        ->where('active', true)
                        ->orderBy('name');
                },
            ])
            ->where('active', true)
            ->whereDate('fecha', '<=', $fechaActual)
            ->where(function ($query) use ($fechaActual) {
                $query
                    ->whereNull('fecha_fin')
                    ->orWhereDate('fecha_fin', '>=', $fechaActual);
            })
            ->orderBy('fecha')
            ->orderBy('hora')
            ->get();

        $resultado = [];

        foreach ($publicaciones as $publicacion) {
            $ocurrencias = $this->generarOcurrenciasDelDia(
                $publicacion,
                $ahora
            );

            foreach ($ocurrencias as $ocurrencia) {
                $resultado[] = [
                    'id' => $publicacion->id,

                    /*
                     * Cada ocurrencia tiene una identidad única.
                     *
                     * Ejemplo:
                     * 6_2026-10-06_12-30
                     * 6_2026-10-06_13-30
                     * 6_2026-10-06_14-30
                     */
                    'ocurrencia_id' =>
                        $publicacion->id .
                        '_' .
                        $ocurrencia->format('Y-m-d_H-i'),

                    'fecha' => $ocurrencia->format('Y-m-d'),

                    'hora' => $ocurrencia->format('H:i'),

                    'imagen' => $publicacion->imagen
                        ? url(Storage::url($publicacion->imagen))
                        : null,

                    'texto' => $publicacion->texto,

                    'destino' => $publicacion->destino,

                    /*
                     |--------------------------------------------------------------------------
                     | GRUPOS DE FACEBOOK
                     |--------------------------------------------------------------------------
                     |
                     | Solamente se envían cuando existen grupos
                     | seleccionados en Filament.
                     |
                     | Si la publicación es de WhatsApp, será [].
                     |
                     */
                    'facebook_groups' =>
                        in_array(
                            $publicacion->destino,
                            ['facebook', 'ambos'],
                            true
                        )
                            ? $publicacion->facebookGroups
                                ->map(function ($grupo) {
                                    return [
                                        'id' => $grupo->id,
                                        'facebook_id' => $grupo->facebook_id,
                                        'nombre' => $grupo->name,
                                        'url' => $grupo->url,
                                    ];
                                })
                                ->values()
                                ->all()
                            : [],

                    'tipo_recurrencia' =>
                        $publicacion->tipo_recurrencia,

                    'intervalo_horas' =>
                        $publicacion->intervalo_horas,

                    'hora_inicio_recurrencia' =>
                        $publicacion->hora_inicio_recurrencia,

                    'hora_fin_recurrencia' =>
                        $publicacion->hora_fin_recurrencia,

                    'fecha_fin' => $publicacion->fecha_fin
                        ? $publicacion->fecha_fin->format('Y-m-d')
                        : null,
                ];
            }
        }

        usort(
            $resultado,
            function (array $a, array $b): int {
                return strcmp(
                    $a['fecha'] . ' ' . $a['hora'],
                    $b['fecha'] . ' ' . $b['hora']
                );
            }
        );

        return response()->json([
            'data' => $resultado,
        ]);
    }

    /**
     * Genera las ocurrencias correspondientes al día actual.
     */
    private function generarOcurrenciasDelDia(
        Publication $publicacion,
        Carbon $ahora
    ): array {
        $inicioDia = $ahora->copy()->startOfDay();
        $finDia = $ahora->copy()->endOfDay();

        $fechaInicio = Carbon::parse(
            $publicacion->fecha
        )->startOfDay();

        /*
        |--------------------------------------------------------------------------
        | PUBLICACIÓN SIN RECURRENCIA
        |--------------------------------------------------------------------------
        */

        if (
            empty($publicacion->tipo_recurrencia) ||
            $publicacion->tipo_recurrencia === 'ninguna'
        ) {
            /*
             * La publicación normal solamente corresponde
             * al día configurado.
             */
            if (
                !$fechaInicio->isSameDay($ahora)
            ) {
                return [];
            }

            $fechaHora = Carbon::parse(
                $publicacion->fecha->format('Y-m-d') .
                ' ' .
                substr(
                    (string) $publicacion->hora,
                    0,
                    5
                )
            );

            if (
                $fechaHora->betweenIncluded(
                    $inicioDia,
                    $finDia
                )
            ) {
                return [$fechaHora];
            }

            return [];
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDACIÓN DE FECHA DE INICIO
        |--------------------------------------------------------------------------
        */

        if ($inicioDia->lt($fechaInicio)) {
            return [];
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDACIÓN DE FECHA FINAL
        |--------------------------------------------------------------------------
        */

        if (
            $publicacion->fecha_fin &&
            $inicioDia->gt(
                Carbon::parse(
                    $publicacion->fecha_fin
                )->endOfDay()
            )
        ) {
            return [];
        }

        /*
        |--------------------------------------------------------------------------
        | RECURRENCIA CADA X HORAS
        |--------------------------------------------------------------------------
        */

        if (
            $publicacion->tipo_recurrencia === 'cada_horas'
        ) {
            $intervalo = (int) $publicacion->intervalo_horas;

            /*
             * El intervalo debe ser como mínimo 1 hora.
             */
            if ($intervalo < 1) {
                return [];
            }

            /*
             * Para publicaciones recurrentes utilizamos
             * siempre hora_inicio_recurrencia.
             */
            if (
                empty(
                    $publicacion->hora_inicio_recurrencia
                )
            ) {
                return [];
            }

            $horaInicio =
                substr(
                    (string) $publicacion->hora_inicio_recurrencia,
                    0,
                    5
                );

            /*
             * Si no se define hora final, utilizamos 23:59.
             */
            $horaFin = !empty(
                $publicacion->hora_fin_recurrencia
            )
                ? substr(
                    (string) $publicacion->hora_fin_recurrencia,
                    0,
                    5
                )
                : '23:59';

            /*
             * Construimos las horas correspondientes
             * al día actual.
             */
            $inicio = Carbon::parse(
                $ahora->format('Y-m-d') .
                ' ' .
                $horaInicio
            );

            $fin = Carbon::parse(
                $ahora->format('Y-m-d') .
                ' ' .
                $horaFin
            );

            /*
             * La hora de inicio no puede ser posterior
             * a la hora final.
             */
            if ($inicio->gt($fin)) {
                return [];
            }

            $resultado = [];

            $actual = $inicio->copy();

            /*
             * Generamos:
             *
             * hora_inicio
             * + intervalo
             * + intervalo
             * ...
             * hasta hora_fin.
             */
            while ($actual->lte($fin)) {
                if ($actual->isSameDay($ahora)) {
                    $resultado[] = $actual->copy();
                }

                $actual->addHours($intervalo);
            }

            return $resultado;
        }

        /*
        |--------------------------------------------------------------------------
        | TIPO DE RECURRENCIA DESCONOCIDO
        |--------------------------------------------------------------------------
        */

        return [];
    }
}

