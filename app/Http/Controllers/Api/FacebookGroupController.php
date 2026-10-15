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
        if (! is_array($rawGroups)) {
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
        $ignoredGenericNames = 0;

        DB::transaction(function () use ($data, $synchronizedAt, &$receivedIds, &$ignoredGenericNames): void {
            foreach ($data['groups'] as $group) {
                $existing = FacebookGroup::query()
                    ->where('facebook_id', $group['facebook_id'])
                    ->first();

                if ($this->isGenericGroupName($group['name'])) {
                    // Never create placeholder records, and never overwrite a
                    // real name already known by Laravel with "Grupo Facebook 123".
                    if (! $existing) {
                        $ignoredGenericNames++;

                        continue;
                    }

                    $existing->forceFill([
                        'url' => $group['url'] ?: $existing->url,
                        'active' => true,
                        'last_synchronized_at' => $synchronizedAt,
                    ])->save();

                    $receivedIds[] = $existing->id;
                    $ignoredGenericNames++;

                    continue;
                }

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
            'total' => count(array_unique($receivedIds)),
            'ignored_generic_names' => $ignoredGenericNames,
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

    private function isGenericGroupName(string $name): bool
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name);

        return preg_match('/^(?:grupo\s+facebook|facebook\s+group)(?:\s+\d+)?$/iu', $normalized) === 1;
    }
}
