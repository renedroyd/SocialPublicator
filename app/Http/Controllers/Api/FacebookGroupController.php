<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FacebookGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FacebookGroupController extends Controller
{
    public function sync(Request $request): JsonResponse
    {
        $rawGroups = $request->input('groups');

        // Keep compatibility with the current stable bot while the API contract
        // is migrated to English.
        if (!is_array($rawGroups)) {
            $rawGroups = $request->input('grupos', []);
        }

        $groups = collect($rawGroups)
            ->map(static function (array $group): array {
                return [
                    'facebook_id' => $group['facebook_id'] ?? null,
                    'name' => $group['name'] ?? $group['nombre'] ?? null,
                    'url' => $group['url'] ?? null,
                ];
            })
            ->values()
            ->all();

        $data = Validator::make(
            ['groups' => $groups],
            [
                'groups' => ['required', 'array'],
                'groups.*.facebook_id' => ['required', 'string', 'max:255'],
                'groups.*.name' => ['required', 'string', 'max:255'],
                'groups.*.url' => ['nullable', 'string', 'max:2048'],
            ]
        )->validate();

        $synchronizedAt = now();
        $receivedIds = [];

        DB::transaction(function () use ($data, $synchronizedAt, &$receivedIds): void {
            foreach ($data['groups'] as $group) {
                $record = FacebookGroup::updateOrCreate(
                    ['facebook_id' => $group['facebook_id']],
                    [
                        'name' => $group['name'],
                        'url' => $group['url'],
                        'active' => true,
                        'last_synchronized_at' => $synchronizedAt,
                    ],
                );

                $receivedIds[] = $record->id;
            }
        });

        return response()->json([
            'success' => true,
            'total' => count($receivedIds),
            'data' => FacebookGroup::query()
                ->whereIn('id', $receivedIds)
                ->orderBy('name')
                ->get([
                    'id',
                    'facebook_id',
                    'name',
                    'url',
                    'active',
                    'last_synchronized_at',
                ]),
        ]);
    }
}
