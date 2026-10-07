<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FacebookGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FacebookGroupController extends Controller
{
    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'groups' => ['required', 'array'],
            'groups.*.facebook_id' => ['required', 'string', 'max:255'],
            'groups.*.name' => ['required', 'string', 'max:255'],
            'groups.*.url' => ['nullable', 'string', 'max:2048'],
        ]);

        $synchronizedAt = now();
        $receivedIds = [];

        DB::transaction(function () use ($data, $synchronizedAt, &$receivedIds): void {
            foreach ($data['groups'] as $group) {
                $record = FacebookGroup::updateOrCreate(
                    ['facebook_id' => $group['facebook_id']],
                    [
                        'name' => $group['name'],
                        'url' => $group['url'] ?? null,
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
