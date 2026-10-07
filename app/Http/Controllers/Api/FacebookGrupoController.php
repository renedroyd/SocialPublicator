<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FacebookGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FacebookGrupoController extends Controller
{
    public function sincronizar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'grupos' => ['required', 'array'],
            'grupos.*.facebook_id' => ['required', 'string', 'max:255'],
            'grupos.*.nombre' => ['required', 'string', 'max:255'],
            'grupos.*.url' => ['nullable', 'string', 'max:2048'],
        ]);

        $ahora = now();
        $idsRecibidos = [];

        DB::transaction(function () use ($datos, $ahora, &$idsRecibidos) {
            foreach ($datos['grupos'] as $grupo) {
                $registro = FacebookGroup::updateOrCreate(
                    ['facebook_id' => $grupo['facebook_id']],
                    [
                        'nombre' => $grupo['nombre'],
                        'url' => $grupo['url'] ?? null,
                        'activo' => true,
                        'ultima_sincronizacion' => $ahora,
                    ],
                );

                $idsRecibidos[] = $registro->id;
            }
        });

        return response()->json([
            'success' => true,
            'total' => count($idsRecibidos),
            'data' => FacebookGroup::query()
                ->whereIn('id', $idsRecibidos)
                ->orderBy('nombre')
                ->get(['id', 'facebook_id', 'nombre', 'url', 'activo', 'ultima_sincronizacion']),
        ]);
    }
}
