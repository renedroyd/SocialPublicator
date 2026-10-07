<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Publication;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class BotPublicationController extends Controller
{
    public function index(): JsonResponse
    {
        $now = now();
        $currentDate = $now->toDateString();

        $publications = Publication::query()
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
            ->whereDate('fecha', '<=', $currentDate)
            ->where(function ($query) use ($currentDate) {
                $query
                    ->whereNull('fecha_fin')
                    ->orWhereDate('fecha_fin', '>=', $currentDate);
            })
            ->orderBy('fecha')
            ->orderBy('hora')
            ->get();

        $result = [];

        foreach ($publications as $publication) {
            $occurrences = $this->generateDailyOccurrences(
                $publication,
                $now
            );

            foreach ($occurrences as $occurrence) {
                $result[] = [
                    'id' => $publication->id,

                    /*
                     * Cada ocurrencia tiene una identidad única.
                     *
                     * Ejemplo:
                     * 6_2026-10-06_12-30
                     * 6_2026-10-06_13-30
                     * 6_2026-10-06_14-30
                     */
                    'ocurrencia_id' =>
                        $publication->id .
                        '_' .
                        $occurrence->format('Y-m-d_H-i'),

                    'date' => $occurrence->format('Y-m-d'),
                    'fecha' => $occurrence->format('Y-m-d'),

                    'time' => $occurrence->format('H:i'),
                    'hora' => $occurrence->format('H:i'),

                    'imagen' => $publication->imagen
                        ? url(Storage::url($publication->imagen))
                        : null,

                    'texto' => $publication->texto,

                    'destino' => $publication->destino,

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
                            $publication->destino,
                            ['facebook', 'ambos'],
                            true
                        )
                            ? $publication->facebookGroups
                                ->map(function ($grupo) {
                                    return [
                                        'id' => $grupo->id,
                                        'facebook_id' => $grupo->facebook_id,
                                        'nombre' => $grupo->nombre,
                                        'url' => $grupo->url,
                                    ];
                                })
                                ->values()
                                ->all()
                            : [],

                    'tipo_recurrencia' =>
                        $publication->tipo_recurrencia,

                    'intervalo_horas' =>
                        $publication->intervalo_horas,

                    'hora_inicio_recurrencia' =>
                        $publication->hora_inicio_recurrencia,

                    'hora_fin_recurrencia' =>
                        $publication->hora_fin_recurrencia,

                    'fecha_fin' => $publication->fecha_fin
                        ? $publication->fecha_fin->format('Y-m-d')
                        : null,
                ];
            }
        }

        usort(
            $result,
            function (array $a, array $b): int {
                return strcmp(
                    $a['fecha'] . ' ' . $a['hora'],
                    $b['fecha'] . ' ' . $b['hora']
                );
            }
        );

        return response()->json([
            'data' => $result,
        ]);
    }

    /**
     * Genera las ocurrencias correspondientes al día actual.
     */
    private function generateDailyOccurrences(
        Publication $publication,
        Carbon $now
    ): array {
        $dayStart = $now->copy()->startOfDay();
        $dayEnd = $now->copy()->endOfDay();

        $startDate = Carbon::parse(
            $publication->fecha
        )->startOfDay();

        /*
        |--------------------------------------------------------------------------
        | PUBLICACIÓN SIN RECURRENCIA
        |--------------------------------------------------------------------------
        */

        if (
            empty($publication->tipo_recurrencia) ||
            $publication->tipo_recurrencia === 'ninguna'
        ) {
            /*
             * La publicación normal solamente corresponde
             * al día configurado.
             */
            if (
                !$startDate->isSameDay($now)
            ) {
                return [];
            }

            $fechaHora = Carbon::parse(
                $publication->fecha->format('Y-m-d') .
                ' ' .
                substr(
                    (string) $publication->hora,
                    0,
                    5
                )
            );

            if (
                $fechaHora->betweenIncluded(
                    $dayStart,
                    $dayEnd
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

        if ($dayStart->lt($startDate)) {
            return [];
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDACIÓN DE FECHA FINAL
        |--------------------------------------------------------------------------
        */

        if (
            $publication->fecha_fin &&
            $dayStart->gt(
                Carbon::parse(
                    $publication->fecha_fin
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
            $publication->tipo_recurrencia === 'cada_horas'
        ) {
            $intervalHours = (int) $publication->intervalo_horas;

            /*
             * El intervalo debe ser como mínimo 1 hora.
             */
            if ($intervalHours < 1) {
                return [];
            }

            /*
             * Para publicaciones recurrentes utilizamos
             * siempre hora_inicio_recurrencia.
             */
            if (
                empty(
                    $publication->hora_inicio_recurrencia
                )
            ) {
                return [];
            }

            $startTime =
                substr(
                    (string) $publication->hora_inicio_recurrencia,
                    0,
                    5
                );

            /*
             * Si no se define hora final, utilizamos 23:59.
             */
            $endTime = !empty(
                $publication->hora_fin_recurrencia
            )
                ? substr(
                    (string) $publication->hora_fin_recurrencia,
                    0,
                    5
                )
                : '23:59';

            /*
             * Construimos las horas correspondientes
             * al día actual.
             */
            $start = Carbon::parse(
                $now->format('Y-m-d') .
                ' ' .
                $startTime
            );

            $end = Carbon::parse(
                $now->format('Y-m-d') .
                ' ' .
                $endTime
            );

            /*
             * La hora de inicio no puede ser posterior
             * a la hora final.
             */
            if ($start->gt($end)) {
                return [];
            }

            $result = [];

            $current = $start->copy();

            /*
             * Generamos:
             *
             * hora_inicio
             * + intervalo
             * + intervalo
             * ...
             * hasta hora_fin.
             */
            while ($current->lte($end)) {
                if ($current->isSameDay($now)) {
                    $result[] = $current->copy();
                }

                $current->addHours($intervalHours);
            }

            return $result;
        }

        /*
        |--------------------------------------------------------------------------
        | TIPO DE RECURRENCIA DESCONOCIDO
        |--------------------------------------------------------------------------
        */

        return [];
    }
}

